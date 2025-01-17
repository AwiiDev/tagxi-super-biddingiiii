<?php

namespace App\Http\Controllers\Web\Dispatcher;

use App\Jobs\NotifyViaMqtt;
use App\Models\Admin\Driver;
use App\Jobs\NotifyViaSocket;
use App\Models\Admin\ZoneType;
use App\Models\Request\Request;
use Salman\Mqtt\MqttClass\Mqtt;
use Illuminate\Support\Facades\DB;
use App\Models\Request\RequestMeta;
use Illuminate\Support\Facades\Log;
use App\Base\Constants\Masters\PushEnums;
use App\Base\Constants\Masters\EtaConstants;
use App\Http\Controllers\Web\BaseController;
use App\Http\Requests\Request\CreateTripRequest;
use App\Transformers\Requests\TripRequestTransformer;
use Carbon\Carbon;
use App\Base\Constants\Setting\Settings;
use Sk\Geohash\Geohash;
use Kreait\Firebase\Contract\Database;
use App\Jobs\Notifications\AndroidPushNotification;
use App\Jobs\Notifications\SendPushNotification;
use App\Helpers\Rides\FetchDriversFromFirebaseHelpers;


/**
 * @group Dispatcher-trips-apis
 *
 * APIs for Dispatcher-trips apis
 */
class DispatcherCreateRequestController extends BaseController
{
    use FetchDriversFromFirebaseHelpers;

    protected $request;

    public function __construct(Request $request,Database $database)
    {
        $this->request = $request;
        $this->database = $database;
    }
    /**
    * Create Request
    * @bodyParam pick_lat double required pikup lat of the user
    * @bodyParam pick_lng double required pikup lng of the user
    * @bodyParam drop_lat double required drop lat of the user
    * @bodyParam drop_lng double required drop lng of the user
    * @bodyParam vehicle_type string required id of zone_type_id
    * @bodyParam payment_opt tinyInteger required type of ride whther cash or card, wallet('0 => card,1 => cash,2 => wallet)
    * @bodyParam pick_address string required pickup address of the trip request
    * @bodyParam drop_address string required drop address of the trip request
    * @bodyParam pickup_poc_name string required customer name for the request
    * @bodyParam pickup_poc_mobile string required customer name for the request
    * @responseFile responses/requests/create-request.json
    *
    */
    public function createRequest(CreateTripRequest $request)
    {

        /**
        * Validate payment option is available.
        * if card payment choosen, then we need to check if the user has added thier card.
        * if the paymenr opt is wallet, need to check the if the wallet has enough money to make the trip request
        * Check if thge user created a trip and waiting for a driver to accept. if it is we need to cancel the exists trip and create new one
        * Find the zone using the pickup coordinates & get the nearest drivers
        * create request along with place details
        * assing driver to the trip depends the assignment method
        * send emails and sms & push notifications to the user& drivers as well.
        */
        
        // Validate payment option is available.
        if ($request->has('is_later') && $request->is_later) {
            return $this->createRideLater($request);
        }
        // @TODO
        // get type id
        $zone_type_detail = ZoneType::where('id', $request->vehicle_type)->first();
        $type_id = $zone_type_detail->type_id;

        // Get currency code of Request
        $service_location = $zone_type_detail->zone->serviceLocation;
        $currency_code = $service_location->currency_code;
        $currency_symbol = $service_location->currency_symbol;


        // fetch unit from zone
        $unit = $zone_type_detail->zone->unit;
        // Fetch user detail
        $user_detail = auth()->user();
        // Get last request's request_number
        $request_number = $this->request->orderBy('updated_at', 'DESC')->pluck('request_number')->first();
        if ($request_number) {
            $request_number = explode('_', $request_number);
            $request_number = $request_number[1]?:000000;
        } else {
            $request_number = 000000;
        }
        // Generate request number
        $request_number = 'REQ_'.sprintf("%06d", $request_number+1);

        $request_params = [
            'request_number'=>$request_number,
            'zone_type_id'=>$request->vehicle_type,
            'if_dispatch'=>true,
            'dispatcher_id'=>$user_detail->admin->id,
            'payment_opt'=>$request->payment_opt,
            'unit'=>$unit,
            'requested_currency_code'=>$currency_code,
            'requested_currency_symbol'=>$currency_symbol,
            'timezone'=>$service_location->timezone,
            'service_location_id'=>$service_location->id,
            'transport_type'=>'taxi'];
        if($request->has('request_eta_amount') && $request->request_eta_amount){

           $request_params['request_eta_amount'] = $request->request_eta_amount;

        }
        // store request details to db
        // DB::beginTransaction();
        // try {
        $request_detail = $this->request->create($request_params);


         if ($request->has('stops')) {

            // Log::info($request->stops);
            $order = 1;

            foreach (json_decode($request->stops) as $key => $stop) {
                $request_detail->requestStops()->create([
                'address'=>$stop->address,
                'latitude'=>$stop->latitude,
                'longitude'=>$stop->longitude,
                'order'=>$order]); 
                $order++; 
            }
        }
        
        // request place detail params
        $request_place_params = [
            'pick_lat'=>$request->pick_lat,
            'pick_lng'=>$request->pick_lng,
            'drop_lat'=>$request->drop_lat,
            'drop_lng'=>$request->drop_lng,
            'pick_address'=>$request->pick_address,
            'drop_address'=>$request->drop_address];
        // store request place details
        $request_detail->requestPlace()->create($request_place_params);
        // $ad_hoc_user_params = $request->only(['name','phone_number']);
        $ad_hoc_user_params['name'] = $request->pickup_poc_name;
        $ad_hoc_user_params['mobile'] = $request->pickup_poc_mobile;

        // Store ad hoc user detail of this request
        $request_detail->adHocuserDetail()->create($ad_hoc_user_params);

         $request_result =  fractal($request_detail, new TripRequestTransformer)->parseIncludes('userDetail');
         
        $nearest_drivers =  $this->fetchDriversFromFirebase($request_detail);


        // } catch (\Exception $e) {
        //     DB::rollBack();
        //     Log::error($e);
        //     Log::error('Error while Create new request. Input params : ' . json_encode($request->all()));
        //     return $this->respondBadRequest('Unknown error occurred. Please try again later or contact us if it continues.');
        // }
        DB::commit();

        return $this->respondSuccess($request_result, 'Request Created Successfully');
    }


   

   
    /**
    * Create Ride later trip
    */
    public function createRideLater(CreateTripRequest $request)
    {
        /**
        * @TODO validate if the user has any trip with same time period
        *
        */
        // get type id
        $zone_type_detail = ZoneType::where('id', $request->vehicle_type)->first();
        $type_id = $zone_type_detail->type_id;

        // Get currency code of Request
        $service_location = $zone_type_detail->zone->serviceLocation;
        $currency_code = $service_location->currency_code;
        $currency_symbol = $service_location->currency_symbol;
        
        // $currency_code = get_settings('currency_code');;

        // fetch unit from zone
        $unit = $zone_type_detail->zone->unit;
        // Fetch user detail
        $user_detail = auth()->user();
        // Get last request's request_number
        $request_number = $this->request->orderBy('updated_at', 'DESC')->pluck('request_number')->first();
        if ($request_number) {
            $request_number = explode('_', $request_number);
            $request_number = $request_number[1]?:000000;
        } else {
            $request_number = 000000;
        }
        // Generate request number
        $request_number = 'REQ_'.sprintf("%06d", $request_number+1);

        // Convert trip start time as utc format
        $timezone = auth()->user()->timezone?:env('SYSTEM_DEFAULT_TIMEZONE');

        $trip_start_time = Carbon::parse($request->trip_start_time, $timezone)->setTimezone('UTC')->toDateTimeString();


        $request_params = [
            'request_number'=>$request_number,
            'is_later'=>true,
            'zone_type_id'=>$request->vehicle_type,
            'trip_start_time'=>$trip_start_time,
            'if_dispatch'=>true,
            'dispatcher_id'=>$user_detail->admin->id,
            'payment_opt'=>$request->payment_opt,
            'unit'=>$unit,
            'requested_currency_code'=>$currency_code,
            'requested_currency_symbol'=>$currency_symbol,
            'service_location_id'=>$service_location->id,
            'timezone'=>$service_location->timezone,
            'transport_type'=>'taxi'
            'on_search'=>false,
            
        ];
        if($request->has('request_eta_amount') && $request->request_eta_amount){

           $request_params['request_eta_amount'] = $request->request_eta_amount;

        }
        // store request details to db
        DB::beginTransaction();
        try {
            $request_detail = $this->request->create($request_params);
            // request place detail params
            $request_place_params = [
            'pick_lat'=>$request->pick_lat,
            'pick_lng'=>$request->pick_lng,
            'drop_lat'=>$request->drop_lat,
            'drop_lng'=>$request->drop_lng,
            'pick_address'=>$request->pick_address,
            'drop_address'=>$request->drop_address];
            // store request place details
            $request_detail->requestPlace()->create($request_place_params);

            // $ad_hoc_user_params = $request->only(['name','phone_number']);
            $ad_hoc_user_params['name'] = $request->pickup_poc_name;
            $ad_hoc_user_params['mobile'] = $request->pickup_poc_mobile;

            // Store ad hoc user detail of this request
            $request_detail->adHocuserDetail()->create($ad_hoc_user_params);

            $request_result =  fractal($request_detail, new TripRequestTransformer)->parseIncludes('userDetail');
            // @TODO send sms & email to the user
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
            Log::error('Error while Create new schedule request. Input params : ' . json_encode($request->all()));
            return $this->respondBadRequest('Unknown error occurred. Please try again later or contact us if it continues.');
        }
        DB::commit();

        return $this->respondSuccess($request_result, 'Request Scheduled Successfully');
    }
}
