<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use App\Models\{Order,OrderDetail,Inscriptions,Installments,InstallmentDetail};

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
      
        return response()->json(['data' => $list], 200);
    }

    public function getOrderDetails($order_id)
    {
        $order = OrderDetail::with('course.workshops','order')->where('order_id', $order_id)->get();
        return response()->json(['data' => $order], 200);
    }

    public function acceptOrder(Request $request,$order_id)
    {
        $installments = $request->input('installments');


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

                if($installments){
                    $installment = new Installments();
                    $installment->order_id = $order_id;
                    $installment->due_date = date('Y-m-d', strtotime('+' . $installments . ' months'));
                    $installment->status = 'pending';
                    $installment->amount = $installments;
                    $installment->date_created = date('Y-m-d H:i:s');
                    $installment->date_last_updated = date('Y-m-d H:i:s');
                    $installment->save();

                    for($i = 1; $i <= $installments; $i++){
                        $installmentDetail = new InstallmentDetail();
                        $installmentDetail->installment_id = $installment->id;
                        $installmentDetail->installment_number = $i;
                        $installmentDetail->due_date = date('Y-m-d', strtotime('+' . $i . ' months'));
                        $installmentDetail->save();
                    }

                    $order->installments = $installments;
                    $order->save(); 
                    
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
            $item->setAttribute('total', $total);
    
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


    public function getInstallments($order_id)
    {
        $installments = Installments::with('installmentDetails','order.orderDetails.course')->where('order_id', $order_id)->get();
        return response()->json(['data' => $installments], 200);
    }


    public function updateInstallmentDetail($installment_id,Request $request)
    {
        $installmentDetail = InstallmentDetail::find($installment_id);
        $installmentDetail->paid = $request->input('paid');
        $installmentDetail->url_payment = $request->input('url_payment');   
        #$installmentDetail->due_date = $request->input('due_date');
        if($request->input('paid')){
            $installmentDetail->paid_date = date('Y-m-d H:i:s');
        }else{
            $installmentDetail->paid_date = null;
        }
        $installmentDetail->save();


        $header_installment = Installments::find($installmentDetail->installment_id);
        $header_installment->date_last_updated = date('Y-m-d H:i:s');
        $header_installment->save();



        #check if all installment details are paid
        $detail_aux = InstallmentDetail::where('installment_id', $installment_id)->get();
        $all_paid = true;
        foreach($detail_aux as $item){
            if(!$item->paid){
                $all_paid = false;
            }
        }
        if($all_paid){
            $installment = Installments::find($installmentDetail->installment_id);
            $installment->status = 'paid';
            $installment->save();
        }
        return response()->json(['data' => $installmentDetail], 200);
    }





    public function getAllInstallments(Request $request)
    {
        $filters['status'] = $request->input('status');

        if($filters['status']){
            $installments = Installments::with('order.user','installmentDetails')->where('status', $filters['status'])->get();
        }else{
            $installments = Installments::with('order.user','installmentDetails')->get();
        }

        foreach($installments as $item){
            $item->setAttribute('next_due_date', null);
            foreach($item->installmentDetails as $detail){
                if(!$detail->paid){
                   $next_due_date = $detail->due_date;
                   $item->setAttribute('next_due_date', $next_due_date);
                   break;
                }
            }
        }
        return response()->json(['data' => $installments], 200);
    }
}