<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use App\Models\{Order,OrderDetail,Inscriptions};

use Illuminate\Support\Facades\DB;

use App\Support\TokenManager;

class OrdersController extends Controller
{   

  
    /**
     * Listado de ordenes
     *
     * @param $provider
     * @return JsonResponse
     */
    public function getAll(Request $request)
    {   
        $params = $request->all();
      
        $list = Order::with('user','orderDetails')->get();

        foreach($list as $item){          
            $total =  $item->orderDetails->sum('price');
            $item->total = $total;
        }   
      
        return response()->json(['data' => $list], 200);
    }

    public function getOrderDetails($order_id)
    {
        $order = OrderDetail::with('course.workshops','order')->where('order_id', $order_id)->get();
        return response()->json(['data' => $order], 200);
    }

    public function acceptOrder($order_id)
    {
        $order = Order::find($order_id);

        if($order->payment_method_id == 2 ){  
            if($order->status != 'pending'){
                return response()->json(['error' => 'La orden ya fue procesada'], 500);
            }
            try{
                $orderDetails = OrderDetail::where('order_id', $order_id)->get();
                foreach($orderDetails as $item){
                    $inscripcion = Inscriptions::where('user_id', $order->user_id)->where('course_id', $item->course_id)->first();
                    if(!$inscripcion){
                        $inscripcion = new Inscriptions();
                        $inscripcion->user_id = $order->user_id;
                        $inscripcion->course_id = $item->course_id;
                        $inscripcion->with_workshop = $item->with_workshop;
                        $inscripcion->save();
                    }else{
                        if($inscripcion->with_workshop == 0 && $item->with_workshop == 1){
                            $inscripcion->with_workshop = 1;
                            $inscripcion->save();
                        }
                    }
                }
            }catch(\Exception $e){
                return response()->json(['error' => $e->getMessage()], 500);
            }
            $order->status = 'paid';
            $order->date_paid = date('Y-m-d H:i:s');
            $order->date_closed = date('Y-m-d H:i:s');
            $order->save();
        }else{
            return response()->json(['error' => 'El pago no se pudo procesar, solo es posible con transferencia'], 500);
        }
        return response()->json(['order' => $order], 200);
    }


    public function rejectOrder($order_id)  
    {
        $order = Order::find($order_id);

        if($order->payment_method_id == 2){
            if($order->status != 'pending'){
                return response()->json(['error' => 'La orden ya fue procesada'], 500);
            }
        }else{
            return response()->json(['error' => 'El pago no se pudo procesar, solo es posible con transferencia'], 500);
        }

        $order->status = 'rejected';
        $order->date_closed = date('Y-m-d H:i:s');
        $order->save();
        return response()->json(['data' => $order], 200);
    }

      /**
     * Listado de ordenes del usuario
     *
     * @param $provider
     * @return JsonResponse
     */
    public function getMyOrders(Request $request)
    {   
        $params = $request->all();

        
        $accessToken = TokenManager::getTokenFromRequest();
        $user = TokenManager::getUserFromToken($accessToken);


      
        $list = Order::with('orderDetails')->where('user_id', $user->id)->get();

        foreach($list as $item){          
            $total =  $item->orderDetails->sum('price');
            $item->total = $total;
        }


        return response()->json(['data' => $list], 200);
    }




    public function cleanUpOrders()
    {   
        #get all orders with status pending older than 2 weeks
        $orders = Order::where('status', 'pending')->where('date_created', '<', now()->subWeek(2))->get();

        foreach($orders as $order){
            $order->status = 'annulled';
            $order->date_closed = date('Y-m-d H:i:s');
            $order->save();
        }
        return response()->json(['message' => 'Orders cleaned up'], 200);
    }


}