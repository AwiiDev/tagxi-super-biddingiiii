<?php

namespace App\Base\Filters\Admin;

use App\Base\Libraries\QueryFilter\FilterContract;
use Illuminate\Http\Request;
/**
 * Test filter to demonstrate the custom filter usage.
 * Delete later.
 */
class RequestFilter implements FilterContract
{
    
    /**
     * The available filters.
     *
     * @return array
     */
    public function filters()
    {
        return [
            'is_completed','is_cancelled','is_trip_start','is_paid','payment_opt','vehicle_type','is_assigned','is_later','on_trip','date_option'
        ];
    }

    /**
    * Default column to sort.
    *
    * @return string
    */
    public function defaultSort()
    {
        return '-created_at';
    }

    public function is_completed($builder, $value=0)
    {
       

        if($value){
            if(request()->transport_type == "food_delivery")
            {
                $builder->where('is_completed', $value)
                ->where('is_cancelled', 0)
                ->where('transport_type', "food_delivery"); 
            }
            else{
                $builder->whereHas('requestBill',function($query){
                })->where('is_completed', $value)
                ->where('is_cancelled', 0)
                ->where('transport_type', '!=', 'food_delivery');
                
            } 
           
        }else{
            $builder->where('is_completed', $value)->where('is_cancelled', 0);

        }
    }

    
    public function on_trip($builder,$value=0){

        if($value==1)
        {
            $builder->where('is_cancelled', false)->where('user_rated', false)->where('is_driver_started', true);        
        }


    }
  
    public function is_cancelled($builder, $value=0)
    {  

        $builder->where('is_cancelled', $value);
    }

    public function is_later($builder, $value=0)
    {        
       $builder->where('is_later', $value)->where('is_completed',false)->where('is_cancelled',false)->where('is_driver_started', false);
        
    }

    public function is_trip_start($builder, $value = 0)
    {
        $builder->where('is_trip_start', $value)->where('is_cancelled', 0);
    }

    public function is_paid($builder, $value = 0)
    {
        $builder->where('is_paid', $value);
    }

    public function payment_opt($builder, $value = 0)
    {
        $builder->where('payment_opt', $value);
    }

    public function vehicle_type($builder, $value = 0)
    {
        $builder->whereHas('zoneType.vehicleType', function ($q) use ($value) {// --------------------------------------
            $q->where('id', $value);
        });
    }
    public function is_assigned($builder, $value = 0)
    {
        $builder->where('is_trip_start', $value)->where('is_cancelled', 0)->where('is_completed', 0);
    }

    public function date_option($builder, $value = 0)
    {
        if ($value == 'today') {
            $from = now()->toDateString();
            $to = now()->toDateString();
        } elseif ($value == 'week') {
            $from = now()->startOfWeek()->toDateString();
            $to = now()->endOfDay()->toDateString();
        } elseif ($value == 'month') {
            $from = now()->startOfMonth()->toDateString();
            $to = now()->endOfMonth()->toDateString();
        } elseif ($value == 'year') {
            $from = now()->startOfYear()->toDateString();
            $to = now()->endOfYear()->toDateString();
        } else {
            $date = explode('<>', $value);
            $from = Carbon::parse($date[0])->startOfDay(); // Adjust to the start of the day if needed
            $to = Carbon::parse($date[1])->endOfDay(); // Adjust to the end of the day if needed
        }
        // dd($from,$to);
            $builder->whereBetween('created_at', [$from, $to]);

    }


}
