<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use App\Models\{Order,OrderDetail,Inscriptions,Installments,InstallmentDetail,Discounts,Movements,Courses};

use App\Helper\TelegramNotification;
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
        $account_id = $request->input('account_id');
        $commission_percentage = $request->input('commission_percentage', 0);
        $currency = $request->input('currency');
        if(!$installments && !$account_id){
            return response()->json(['error' => 'Se necesita una cuenta para registrar el pago cuando no hay cuotas'], 500);
        }

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
                } else {
                    // Crear movimientos cuando no hay cuotas
                    foreach($orderDetails as $item){
                        $course = Courses::find($item->course_id);
                        $movement = new Movements();
                        $movement->amount = $item->price;
                        $movement->amount_neto = $item->price - ($item->price * $commission_percentage / 100);
                        $movement->currency = $currency; // ARS para transferencia
                        $movement->description = 'Pago por transferencia - Orden #'.$order->id.' - Curso: '.$course->title;
                        $movement->course_id = $item->course_id;
                        $movement->period = date('m-Y');
                        $movement->account_id = $account_id;
                        $movement->save();
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

        $account_id = $request->input('account_id');
        if(!$account_id){
            return response()->json(['error' => 'Se necesita una cuenta para registrar el pago'], 500);
        }

        $commission_percentage = $request->input('commission_percentage');

        $installmentDetail = InstallmentDetail::find($installment_id);
        
        $installmentDetail->url_payment = $request->input('url_payment');   
        #$installmentDetail->due_date = $request->input('due_date');
        if($request->input('paid')){
            $installmentDetail->paid_date = date('Y-m-d H:i:s');


            #find installment 
            $installment = Installments::find($installmentDetail->installment_id);
            $order = Order::find($installment->order_id);

            #find order details
            $orderDetails = OrderDetail::where('order_id', $order->id)->get();
            foreach($orderDetails as $item){
                $course = Courses::find($item->course_id);
                if($installmentDetail->paid == 0){
                $movement = new Movements();
                $movement->amount = $item->price / $installment->amount;
                $movement->amount_neto = ($item->price / $installment->amount) - ($item->price / $installment->amount) * $commission_percentage / 100;
                $movement->currency = 2;
                $movement->description = 'Pago de cuota #'.$installmentDetail->id.' - Curso: '.$course->title;
                $movement->course_id = $item->course_id;
                $movement->period = date('m-Y');
                $movement->account_id = $request->input('account_id');
                $movement->save();
                }
            }
        }else{
            $installmentDetail->paid_date = null;
        }
        $installmentDetail->paid = $request->input('paid');
        $installmentDetail->save();


        $header_installment = Installments::find($installmentDetail->installment_id);
        $header_installment->date_last_updated = date('Y-m-d H:i:s');
        $header_installment->save();


        $installment_aux = Installments::with('installmentDetails')->find($installmentDetail->installment_id);
      
        $all_paid = true;
        foreach($installment_aux->installmentDetails as $item){
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
                   $next_due_url_payment = $detail->url_payment;
                   $item->setAttribute('next_due_date', $next_due_date);
                   $item->setAttribute('next_due_url_payment', $next_due_url_payment);
                   break;
                }
            }
        }
        return response()->json(['data' => $installments], 200);
    }


    public function createOrder(Request $request)
    {

        $accessToken = TokenManager::getTokenFromRequest();
        $user = TokenManager::getUserFromToken($accessToken);

        $request_data = $request->all();

        if(!isset($request_data['account_id']) && $request_data['installments'] == 0){
            return response()->json(['error' => 'Se necesita una cuenta para registrar el pago'], 500);
        }

        $commission_percentage = isset($request_data['commission_percentage']) ? $request_data['commission_percentage'] : 0;
        
        $discount_percentage = isset($request_data['discount_percentage']) ? $request_data['discount_percentage'] : 0;
        $currency = null;


        $order = new Order();
        $order->user_id = $request->input('user_id');
        $order->status = 'paid';
        $order->date_created = date('Y-m-d H:i:s');
        $order->date_last_updated = date('Y-m-d H:i:s');
        $order->date_closed = date('Y-m-d H:i:s');
        $order->date_paid = date('Y-m-d H:i:s');
        $order->payment_method_id = $request_data['payment_method_id'];
        $order->shopping_cart_id = null;
        $order->save();

        $total = 0;

       
        foreach($request_data['items'] as $item){
            $orderDetails = new OrderDetail();
            $orderDetails->order_id = $order->id;
            $orderDetails->course_id = $item['course_id'];
            $orderDetails->price = $item['unit_price'];
            $orderDetails->with_workshop = $item['with_workshop'];
            if($item['with_workshop'] == 1){
                if($request_data['payment_method_id'] == 1 || $request_data['payment_method_id'] == 2){ # 1 mp y 2 transf
                    $orderDetails->price = $item['unit_price'] + env('WORKSHOP_PRICE_ARS');
                }else{ # 3 stripe y 4 paypal
                    $orderDetails->price = $item['unit_price'] + env('WORKSHOP_PRICE_USD');
                }
            }
            $orderDetails->quantity = 1;
            $orderDetails->save();

            $total += $item['unit_price'];
        }
        if($discount_percentage > 0){
            $order->discount_amount_ars = $total * $discount_percentage / 100;
            $order->discount_amount_usd = $total * $discount_percentage / 100;
            $total = $total - ($total * $discount_percentage / 100);
            $order->discount_percentage = $discount_percentage;
            $order->save();
        }

        if($request_data['payment_method_id'] == 1 || $request_data['payment_method_id'] == 2){ # 1 mp y 2 transf
            $order->total_amount_usd = null;
            $order->total_amount_ars = $total;         
            $order->discount_amount_usd = null;
            $currency = 2;

        }else{ # 3 stripe y 4 paypal
            $order->total_amount_usd = $total;
            $order->total_amount_ars = null;
            $order->discount_amount_ars = null;
            $currency = 1;
        }

        if($request_data['installments'] > 0){
            $installment = new Installments();
            $installment->order_id = $order->id;
            if(is_null($request_data['installments_date'])){
                $start_date =date('Y-m-d');
            }else{
                $start_date = $request_data['installments_date'];
            }
            $installment->due_date = date('Y-m-d', strtotime($start_date . ' +' . $request_data['installments'] . ' months'));
            $installment->status = 'pending';
            $installment->amount = $request_data['installments'];
            $installment->date_created = date('Y-m-d H:i:s');
            $installment->date_last_updated = date('Y-m-d H:i:s');
            $installment->save();

            for($i = 1; $i <= $request_data['installments']; $i++){
                $installmentDetail = new InstallmentDetail();
                $installmentDetail->installment_id = $installment->id;
                $installmentDetail->installment_number = $i;
                $installmentDetail->due_date = date('Y-m-d', strtotime($start_date . ' +' . $i . ' months'));
                if($i <= $request_data['installments_paid']){
                    $installmentDetail->paid = 1;
                    $installmentDetail->paid_date = date('Y-m-d H:i:s');
                }
                $installmentDetail->save();
            }

            $order->installments = $request_data['installments'];
            $order->save(); 
        }

        $order->save();


        #inscriptions
        if($request_data['inscription']){
            foreach($request_data['items'] as $item){
                $inscriptions = new Inscriptions();
                $inscriptions->user_id = $user->id;
                $inscriptions->course_id = $item['course_id'];
                $inscriptions->with_workshop = $item['with_workshop'];
                $inscriptions->save();
            }
        }


        if($request_data['installments'] == 0){
            $account_id = $request->input('account_id');
           
            foreach($request_data['items'] as $item){
                $movement = new Movements();
                $movement->amount = $item['unit_price'];    
                $movement->amount_neto = $item['unit_price'] - ($item['unit_price'] * $commission_percentage / 100);
                $movement->currency = $currency;
                #find course name
                $course = Courses::find($item['course_id']);
                $movement->description = 'Pago de orden #'.$order->id.' - Curso: '.$course->title;
                $movement->course_id = $item['course_id'];
                $movement->period = date('m-Y');
                $movement->created_at = date('Y-m-d H:i:s');
                $movement->account_id = $account_id;
                $movement->save();
            }
        }


        return response()->json(['data' => $order], 200);
    }


    public function getDiscountsForUser(Request $request,$user_id)
    {
        try{

        #count inscriptions of user
        $inscriptions = Inscriptions::where('user_id', $user_id)->count();

        #count courses of user
        $discounts = Discounts::where('courses_amount', '<=', $inscriptions)->orderBy('courses_amount', 'desc')->first();

        if(!$discounts){
            return  null;
        }

        return $discounts;

        }catch(\Exception $e){
            $telegram = new TelegramNotification();
            $telegram->toTelegram($e->getMessage());
            return null;
        }
    }
}