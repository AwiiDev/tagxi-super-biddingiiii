<?php

namespace App\Http\Controllers\Web\Admin;

use App\Base\Filters\Master\CommonMasterFilter;
use App\Base\Libraries\QueryFilter\QueryFilterContract;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\BaseController;
use App\Http\Requests\Admin\Promo\CreatePromoRequest;
use App\Http\Requests\Admin\Promo\UpdatePromoRequest;
use App\Models\Admin\Promo;
use App\Models\Admin\ServiceLocation;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use App\Models\Admin\PromoCodeUser;
use DB;
class PromoCodeController extends BaseController
{
    protected $promo;

    /**
     * PromoController constructor.
     *
     * @param \App\Models\Admin\Promo $promo
     */
    public function __construct(Promo $promo)
    {
        $this->promo = $promo;
    }

    public function index()
    {
        $page = trans('pages_names.view_promo');

        $main_menu = 'manage-promo';
        $sub_menu = '';

        return view('admin.promo.index', compact('page', 'main_menu', 'sub_menu'));
    }

    public function fetch(QueryFilterContract $queryFilter)
    {
        $query = $this->promo->query();

        $results = $queryFilter->builder($query)->customFilter(new CommonMasterFilter)->paginate();

        return view('admin.promo._promo', compact('results'));
    }

    public function create()
    {
        $page = trans('pages_names.add_promo');
        $cities = ServiceLocation::companyKey()->whereActive(true)->get();
        $main_menu = 'manage-promo';
        $sub_menu = '';

        return view('admin.promo.create', compact('cities', 'page', 'main_menu', 'sub_menu'));
    }

    // public function store(CreatePromoRequest $request)
    // {

    //     $created_params = $request->only(['code','service_location_id','minimum_trip_amount','maximum_discount_amount','discount_percent','total_uses','uses_per_user','transport_type']);

    //     $created_params['from'] = now()->parse($request->from)->startOfDay()->toDateTimeString();
    //     $created_params['to'] = now()->parse($request->to)->endOfDay()->toDateTimeString();

    //     $this->promo->create($created_params);

    //     $message = trans('succes_messages.promo_added_succesfully');

    //     return redirect('promo')->with('success', $message);
    // }
    public function store(Request $request)
    {
    //dd($request->all());
        $validator = Validator::make($request->all(), [
            'service_location_id' => 'required|exists:service_locations,id',
            'code' => 'required|unique:promo,code',
            'minimum_trip_amount' => 'required|integer',
            'maximum_discount_amount' => 'required|integer',
            'discount_percent' => 'required|integer|max:100',
            // 'total_uses' => 'required|integer',
            'uses_per_user' => '',
            'from' => 'required|date_format:Y-m-d',
            'to' => 'required|date_format:Y-m-d|after:from',
        ]);
        if(isset($request->transport_type)){
            $transport_type = $request->transport_type;
        }else{
            $transport_type = 'food_delivery';
        }


        $promo_Exists = Promo::where('code', $request->code)->where('transport_type', $transport_type)->exists();

        if ($promo_Exists)
        {
            throw ValidationException::withMessages(['code' => __('Promo Code already exists')]);
        }

        $created_params = $request->only(['code','minimum_trip_amount','maximum_discount_amount','discount_percent','total_uses','uses_per_user','transport_type','promo_code_users_availabe']);

        $created_params['from'] = now()->parse($request->from)->startOfDay()->toDateTimeString();
        $created_params['to'] = now()->parse($request->to)->endOfDay()->toDateTimeString();

        $promoCode = $this->promo->create($created_params);
        $serviceLocationID = $request->service_location_id;


        // if($request->promo_code_users_availabe == "no")
        // {
            foreach ($request->service_location_id as $serviceLocationID)
            {
                $promo_user_params['promo_code_id'] = $promoCode->id;
                $promo_user_params['service_location_id'] = $serviceLocationID;
                 PromoCodeUser::create($promo_user_params);
            }


        //}
        // else{
        //     $validator = Validator::make($request->all(), [
        //         'user' => 'required|',
        //     ]);
        //     if ($validator->fails()) {
        //         throw ValidationException::withMessages(['user' => __('Please select the user')]);
        //     }
        //     foreach ($request->service_location_id as $serviceLocationID)
        //     {
        //         foreach($request->user as $key=>$user){

        //             $promo_user_params['promo_code_id'] = $promoCode->id;
        //             $promo_user_params['user_id'] = $user;
        //             $promo_user_params['service_location_id'] = $serviceLocationID;

        //             // PromoCodeUser::create($promo_user_params);

        //         }
        //     }


        // }
        $message = trans('succes_messages.promo_added_succesfully');
        return redirect('promo')->with('success', $message);
    }
    public function getById(Promo $promo)
    {

        $page = trans('pages_names.edit_promo');
        $cities = ServiceLocation::whereActive(true)->get();
        $main_menu = 'others';
        $sub_menu = 'manage-promo';
        $item = $promo;
        $cities = DB::table('service_locations')
        ->leftJoin(
            DB::raw('(SELECT service_location_id FROM promo_code_users WHERE promo_code_id = "' . $promo->id . '") AS promo_counts'),
            'service_locations.id',
            '=',
            'promo_counts.service_location_id'
        )
        ->select('service_locations.*', DB::raw('CASE WHEN promo_counts.service_location_id IS NOT NULL THEN 1 ELSE 0 END AS service_location_status'))
        ->where('service_locations.active',1)
        ->get();
        $users = DB::table('users')
        ->join('role_user','role_user.user_id','=','users.id')
        ->leftJoin(
            DB::raw('(SELECT user_id FROM promo_code_users WHERE promo_code_id = "' . $promo->id . '") AS promo_counts'),
            'users.id',
            '=',
            'promo_counts.user_id'
        )
        ->where('role_user.role_id',2)
        ->select('users.*', DB::raw('CASE WHEN promo_counts.user_id IS NOT NULL THEN 1 ELSE 0 END AS user_status'))
        ->get();
        // $users = PromoCodeUser::where();
        // dd($users);
        // dd($promo,$cities,$users);

        return view('admin.promo.update', compact('cities','users', 'item', 'page', 'main_menu', 'sub_menu'));
    }

    // public function getById(Promo $promo)
    // {
    //     $page = trans('pages_names.edit_promo');
    //     $cities = ServiceLocation::whereActive(true)->get();
    //     $main_menu = 'manage-promo';
    //     $sub_menu = '';
    //     $item = $promo;

    //     return view('admin.promo.update', compact('cities', 'item', 'page', 'main_menu', 'sub_menu'));
    // }

    // public function update(UpdatePromoRequest $request, Promo $promo)
    // {
    //     $updated_params = $request->all();
    //     $promo->update($updated_params);

    //     $message = trans('succes_messages.promo_updated_succesfully');

    //     return redirect('promo')->with('success', $message);
    // }



    public function update(UpdatePromoRequest $request, Promo $promo)
    {

        // dd($request->all());
        $validator = Validator::make($request->all(), [
            'service_location_id' => 'required|exists:service_locations,id',
            'code' => 'required|unique:promo,code',
            'minimum_trip_amount' => 'required|integer',
            'maximum_discount_amount' => 'required|integer',
            'discount_percent' => 'required|integer|max:100',
            // 'total_uses' => 'required|integer',

            'uses_per_user' => '',
            'from' => 'required|date_format:Y-m-d',
            'to' => 'required|date_format:Y-m-d|after:from',
        ]);
        if(isset($request->transport_type)){
            $transport_type = $request->transport_type;
        }else{
            $transport_type = 'food_delivery';
        }
        $promo_Exists = Promo::where('code', $request->code)->where('id','!=', $promo->id)->where('transport_type', $transport_type)->exists();

        if ($promo_Exists)
        {
            throw ValidationException::withMessages(['code' => __('Promo Code already exists')]);
        }
        $created_params = $request->only(['module','code','minimum_trip_amount','maximum_discount_amount','discount_percent','total_uses','uses_per_user','transport_type','promo_code_users_availabe']);

        $created_params['from'] = now()->parse($request->from)->startOfDay()->toDateTimeString();
        $created_params['to'] = now()->parse($request->to)->endOfDay()->toDateTimeString();
        $created_params['promo_code_users_availabe'] = $request->promo_code_users_availabe;



        Promo::where('id', $promo->id)->update($created_params);


        // if($request->promo_code_users_availabe == "no")
        // {
            PromoCodeUser::where('promo_code_id', $promo->id)->delete();
            foreach ($request->service_location_id as $serviceLocationID)
            {
                $promo_user_params['promo_code_id'] = $promo->id;
                $promo_user_params['service_location_id'] = $serviceLocationID;

                PromoCodeUser::create($promo_user_params);
            }


        // }
        // else{
        //     $validator = Validator::make($request->all(), [
        //         'user' => 'required|',
        //     ]);
        //     if ($validator->fails()) {
        //         throw ValidationException::withMessages(['user' => __('Please select the user')]);
        //     }
        //     PromoCodeUser::where('promo_code_id', $promo->id)->delete();
        //     foreach ($request->service_location_id as $serviceLocationID)
        //     {
        //         foreach($request->user as $key=>$user){

        //             $promo_user_params['promo_code_id'] = $promo->id;
        //             $promo_user_params['user_id'] = $user;
        //             $promo_user_params['service_location_id'] = $serviceLocationID;

        //             PromoCodeUser::create($promo_user_params);

        //         }
        //     }

        // }
        $message = trans('succes_messages.promo_added_succesfully');
        return redirect('promo')->with('success', $message);

        $message = trans('succes_messages.promo_updated_succesfully');

        return redirect('promo')->with('success', $message);
    }
    public function toggleStatus(Promo $promo)
    {
        $status = $promo->isActive() ? false: true;
        $promo->update(['active' => $status]);

        $message = trans('succes_messages.promo_status_changed_succesfully');
        return redirect('promo')->with('success', $message);
    }

    public function delete(Promo $promo)
    {
        $promo->delete();

        $message = trans('succes_messages.promo_deleted_succesfully');
        return redirect('promo')->with('success', $message);
    }
}
