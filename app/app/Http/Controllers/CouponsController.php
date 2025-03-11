<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use App\Models\{Coupons};

use Illuminate\Support\Facades\DB;

use App\Support\TokenManager;

class CouponsController extends Controller
{   

  
    /**
     * Listado de descuentos
     *
     * @param $provider
     * @return JsonResponse
     */
    public function getAll(Request $request)
    {   

        $params = $request->all();
      
        $list = Coupons::all();

    
        return response()->json(['data' => $list], 200);
    }


    /**
     * create categories
     *
     * @param $provider
     * @return JsonResponse
     */
    public function create(Request $request)
    {   

        $params = $request->all();
      
        $create = new Coupons();
        $create->code = $params['code'];

        if(Coupons::where('code', $params['code'])->exists()){
            return response()->json(['error' => 'El código del cupón ya existe'], 400);
        }

        #you can only create a coupon with amount_value or discount_percentage, not both
        if(isset($params['amount_value_usd']) && isset($params['amount_value_ars']) && !is_null($params['amount_value_usd']) && !is_null($params['amount_value_ars'])){
            $create->amount_value_usd = $params['amount_value_usd'];
            $create->amount_value_ars = $params['amount_value_ars'];
        }else if(isset($params['discount_percentage']) && !is_null($params['discount_percentage'])){
            $create->discount_percentage = $params['discount_percentage'];
        }else{
            return response()->json(['error' => 'No se puede crear un cupón sin valor o porcentaje de descuento'], 400);
        }

        $create->expiration_date = $params['expiration_date'];
        $create->used_times = 0;
        $create->max_uses = $params['max_uses'];
        $create->save();


      
        return response()->json(['data' => $create, 'msg' => 'Cupón creado correctamente'], 200);
    }


     /**
     * update discount
     *
     * @param $provider
     * @return JsonResponse
     */
    public function update(Request $request,$coupon_id)
    {   

        $params = $request->all();
      
        $coupon = Coupons::find($coupon_id);

        if(!$coupon){
            return response()->json(['error' => 'Cupón no encontrado'], 400);
        }

        #validate if the coupon code exists and is not the same as the current coupon code
        if(isset($params['code']) && $params['code'] != $coupon->code && Coupons::where('code', $params['code'])->exists()){
            return response()->json(['error' => 'El código del cupón ya existe'], 400);
        }

        $coupon->code = $params['code'];

        #you can only create a coupon with amount_value or discount_percentage, not both
        if(isset($params['amount_value_usd']) && isset($params['amount_value_ars']) && !is_null($params['amount_value_usd']) && !is_null($params['amount_value_ars'])){
            $coupon->amount_value_usd = $params['amount_value_usd'];
            $coupon->amount_value_ars = $params['amount_value_ars'];
            $coupon->discount_percentage = null;
        }else if(isset($params['discount_percentage']) && !is_null($params['discount_percentage'])){
            $coupon->discount_percentage = $params['discount_percentage'];
            $coupon->amount_value_usd = null;
            $coupon->amount_value_ars = null;
        }else{
            return response()->json(['error' => 'No se puede crear un cupón sin valor o porcentaje de descuento'], 400);
        }

        $coupon->expiration_date = $params['expiration_date'];
        $coupon->max_uses = $params['max_uses'];
        $coupon->save();


      
        return response()->json(['data' => $coupon, 'msg' => 'Cupón modificado correctamente'], 200);
    }


     /**
     * delete discount
     *
     * @param $provider
     * @return JsonResponse
     */
    public function delete(Request $request,$coupon_id)
    {   

        $params = $request->all();
        
        $coupon = Coupons::find($coupon_id);

        if(!$coupon){
            return response()->json(['error' => 'Cupón no encontrado'], 500);
        }

        if($coupon->used_times > 0){
            return response()->json(['error' => 'No se puede eliminar un cupón que se ha utilizado'], 500);
        }

        
        $coupon->delete();

      
        return response()->json(['data' => $coupon, 'msg' => 'Cupón eliminado correctamente'], 200);
    }



    public function validateCoupon(Request $request)
    {
        $params = $request->all();

        $coupon = Coupons::where('code', $params['code'])->first();

        if(!$coupon){
            return response()->json(['error' => 'Cupón no encontrado'], 400);
        }

        if($coupon->expiration_date < now()){
            return response()->json(['error' => 'Cupón expirado'], 400);
        }

        if($coupon->max_uses <= $coupon->used_times){
            return response()->json(['error' => 'Cupón agotado'], 400);
        }
        
        return response()->json(['data' => $coupon], 200);  
        
    }

    
}