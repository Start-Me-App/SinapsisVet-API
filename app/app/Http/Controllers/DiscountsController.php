<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use App\Models\{Discounts};

use Illuminate\Support\Facades\DB;

use App\Support\TokenManager;

class DiscountsController extends Controller
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
      
        $list = Discounts::all();

    
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
      
        $create = new Discounts();
        $create->courses_amount = $params['amount'];
        $create->discount_percentage = $params['discount'];
        $create->save();


      
        return response()->json(['data' => $create, 'msg' => 'Descuento creado correctamente'], 200);
    }


     /**
     * update discount
     *
     * @param $provider
     * @return JsonResponse
     */
    public function update(Request $request)
    {   

        $params = $request->all();
      
        $discount = Discounts::find($params['id']);

        if(!$discount){
            return response()->json(['error' => 'Descuento no encontrado'], 400);
        }

        $discount->courses_amount = $params['amount'];
        $discount->discount_percentage = $params['discount'];
        $discount->save();


      
        return response()->json(['data' => $discount, 'msg' => 'Descuento modificado correctamente'], 200);
    }


     /**
     * delete discount
     *
     * @param $provider
     * @return JsonResponse
     */
    public function delete(Request $request,$discount_id)
    {   

        $params = $request->all();
      
        $discount = Discounts::find($discount_id);

        if(!$discount){
            return response()->json(['error' => 'Descuento no encontrado'], 500);
        }


        $discount->delete();

      
        return response()->json(['data' => $discount, 'msg' => 'Descuento eliminado correctamente'], 200);
    }


    
}