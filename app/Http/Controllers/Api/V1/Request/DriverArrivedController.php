<?php

namespace App\Http\Controllers\Api\V1\Request;

use App\Models\User;
use App\Jobs\NotifyViaMqtt;
use Illuminate\Http\Request;
use App\Jobs\NotifyViaSocket;
use App\Base\Constants\Masters\PushEnums;
use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Request\Request as RequestModel;
use App\Jobs\Notifications\AndroidPushNotification;
use App\Transformers\Requests\TripRequestTransformer;
use App\Jobs\Notifications\SendPushNotification;

/**
 * @group Driver-trips-apis
 *
 * APIs for Driver-trips apis
 */
class DriverArrivedController extends BaseController
{
    protected $request;

    public function __construct(RequestModel $request)
    {
        $this->request = $request;
    }
    /**
    * Driver Arrived
    * @bodyParam request_id uuid required id request
    * @response {
    "success": true,
    "message": "driver_arrived"}
    */
    public function arrivedRequest(Request $request)
    {
        // Validate Request id
        $request->validate([
        'request_id' => 'required|exists:requests,id',
        ]);
        // Get Request Detail
        $request_detail = $this->request->where('id', $request->input('request_id'))->first();
        // Validate Trip request data
        $this->validateRequest($request_detail);
        // Update the Request detail with arrival state
        $request_detail->update(['is_driver_arrived'=>true,'arrived_at'=>date('Y-m-d H:i:s')]);
        if ($request_detail->if_dispatch) {
            goto dispatch_notify;
        }
        // Send Push notification to the user
        $user = User::find($request_detail->user_id);
        $title = trans('push_notifications.driver_arrived_title');
        $body = trans('push_notifications.driver_arrived_body');

        $request_result =  fractal($request_detail, new TripRequestTransformer)->parseIncludes('driverDetail');

        $pus_request_detail = $request_result->toJson();
        $push_data = ['notification_enum'=>PushEnums::DRIVER_ARRIVED,'result'=>(string)$pus_request_detail];

        dispatch(new SendPushNotification($user,$title,$body));
        dispatch_notify:
        return $this->respondSuccess(null, 'driver_arrived');
    }


    /**
    * Validate Request
    */
    public function validateRequest($request_detail)
    {
        if ($request_detail->driver_id!=auth()->user()->driver->id) {
            $this->throwAuthorizationException();
        }

        if ($request_detail->is_driver_arrived) {
            $this->throwCustomException('arrived already');
        }

        if ($request_detail->is_completed) {
            $this->throwCustomException('request completed already');
        }
        if ($request_detail->is_cancelled) {
            $this->throwCustomException('request cancelled');
        }
    }
}
