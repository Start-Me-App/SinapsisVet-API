<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use App\Models\{ShoppingCart, User,ShoppingCartContent,Inscriptions,Workshops,Order,OrderDetail,Courses,Discounts};

use Illuminate\Support\Facades\DB;

use App\Support\TokenManager;

use App\Http\Controllers\MercadoPago\CheckoutPro;
use App\Http\Controllers\Stripe\PaymentIntentController;
use App\Helper\TelegramNotification;

class ShoppingCartController extends Controller
{   

    /**
     * Get active shopping cart
     *
     * @param $provider
     * @return JsonResponse
     */
    public function get(Request $request)
    {
        try{
            $accessToken = TokenManager::getTokenFromRequest();
            $user = TokenManager::getUserFromToken($accessToken);

        $shoppingCart = ShoppingCart::with(['items.course'])->where('user_id', $user->id)->where('active', 1)->first();

        foreach($shoppingCart->items as $item){
            #check if course has workshops
            $workshops = Workshops::where('course_id', $item->course_id)->get();
            
            $item->course->workshops = $workshops;
        }
        if(!$shoppingCart){
            return response()->json(['message' => 'Carrito no encontrado'], 404);
        }
        }catch(\Exception $e){
            $telegram = new TelegramNotification();
            $telegram->toTelegram($e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json(['data' => $shoppingCart], 200);

    }


     /**
     * Add item to shopping cart
     *
     * @param $provider
     * @return JsonResponse
     */
    public function addItem(Request $request)
    {
        try{
            $accessToken = TokenManager::getTokenFromRequest();
            $user = TokenManager::getUserFromToken($accessToken);

        $shoppingCart = ShoppingCart::with(['items'])->where('user_id', $user->id)->where('active', 1)->first();

        if(!$shoppingCart){
            return response()->json(['message' => 'Carrito no encontrado'], 404);
        }

        #check if user is already inscribed
        $inscripcion = Inscriptions::where('user_id', $user->id)->where('course_id', $request->course_id)->first();

        if($inscripcion){
            return response()->json(['message' => 'Ya estás inscripto en este curso'], 400);
        }

        #check if course is active
        $course = Courses::find($request->course_id);
        if(!$course){
            return response()->json(['message' => 'Curso no encontrado'], 404);
        }   

        if($course->active == 0){
            return response()->json(['message' => 'Este curso no está activo'], 400);
        }   
        

        #check if course has workshops
        $workshops = Workshops::where('course_id', $request->course_id)->get();
   
        if($workshops->count() == 0 && $request->with_workshop == 1){
            return response()->json(['message' => 'Este curso no tiene talleres'], 400);
        }

        #check if item already exists
        $item = ShoppingCartContent::where('course_id', $request->course_id)->where('shopping_cart_id', $shoppingCart->id)->first();

        if($item){
            $item->with_workshop = $request->with_workshop;
            $item->save();
        }else{
            #add item to shopping cart
            $shoppingCartContent = new ShoppingCartContent();
            $shoppingCartContent->course_id = $request->course_id;
            $shoppingCartContent->with_workshop = $request->with_workshop;
            $shoppingCartContent->shopping_cart_id = $shoppingCart->id;
            $shoppingCartContent->save();
        }


        $shoppingCart = ShoppingCart::with(['items.course'])->where('user_id', $user->id)->where('active', 1)->first();

        }catch(\Exception $e){
            $telegram = new TelegramNotification();
            $telegram->toTelegram($e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }   

        return response()->json(['data' => $shoppingCart], 200);

    }


     /**
     * Remove item to shopping cart
     *
     * @param $provider
     * @return JsonResponse
     */
    public function removeItem(Request $request)
    {
        try{    

            $accessToken = TokenManager::getTokenFromRequest();
            $user = TokenManager::getUserFromToken($accessToken);

        $shoppingCart = ShoppingCart::with(['items'])->where('user_id', $user->id)->where('active', 1)->first();

        if(!$shoppingCart){
            return response()->json(['message' => 'Carrito no encontrado'], 404);
        }

        #remove item to shopping cart
        ShoppingCartContent::where('course_id', $request->course_id)->where('shopping_cart_id', $shoppingCart->id)->delete();

        $shoppingCart = ShoppingCart::with(['items.course'])->where('user_id', $user->id)->where('active', 1)->first();

        }catch(\Exception $e){
            $telegram = new TelegramNotification();
            $telegram->toTelegram($e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }   

        return response()->json(['data' => $shoppingCart], 200);

    }


    /**
     * Processs a shopping cart
     *
     * @param $provider
     * @return JsonResponse
     */
    public function process(Request $request)
    {
        try{

            $accessToken = TokenManager::getTokenFromRequest();
            $user = TokenManager::getUserFromToken($accessToken);

        $shoppingCart = ShoppingCart::with(['items.course'])->where('user_id', $user->id)->where('active', 1)->first();

        if(!$shoppingCart){
            return response()->json(['message' => 'Carrito no encontrado'], 404);
        }

        $paymentMethodId = $request->input('paymentMethodId');
       
       foreach ($shoppingCart->items as $item) {

            #check if user is already inscribed
            $inscripcion = Inscriptions::where('user_id', $user->id)->where('course_id', $item->course_id)->first();

            if($inscripcion){
                if($inscripcion->with_workshop == 1){
                    return response()->json(['message' => 'Ya estás inscripto en este curso y taller', 'course_id' => $item->course_id], 500);
                }
            }
       }

       $order = new Order();
       $order->user_id = $user->id;
       $order->shopping_cart_id = $shoppingCart->id;
       $order->payment_method_id = $paymentMethodId;
       $order->status = 'pending';
       $order->date_created = date('Y-m-d H:i:s');
       $order->date_last_updated = date('Y-m-d H:i:s');
       $order->date_closed = null;
       $order->date_paid = null;
       $order->save();

        #obtengo el descuento
        $discount = self::getDiscountsForUser();

        $discount_percentage = 0;
        if($discount){
        $discount_percentage = $discount->discount;
        }

       foreach($shoppingCart->items as $item){
           #check if user is already inscribed
           $inscripcion = Inscriptions::where('user_id', $user->id)->where('course_id', $item->course_id)->first();

           if($inscripcion){
               if($inscripcion->with_workshop != 1){     
                   #Es por que está comprando solo el taller
                   $orderDetail = new OrderDetail();
                   $orderDetail->order_id = $order->id;
                   $orderDetail->course_id = $item->course_id;
                   if($paymentMethodId == 1 || $paymentMethodId == 2){
                    $orderDetail->price = env('WORKSHOP_PRICE_ARS') - (env('WORKSHOP_PRICE_ARS') * $discount_percentage / 100);
                   }else{
                    $orderDetail->price = env('WORKSHOP_PRICE_USD') - (env('WORKSHOP_PRICE_USD') * $discount_percentage / 100);
                   }
                   $orderDetail->with_workshop = $item->with_workshop;
                   $orderDetail->quantity = 1;
                   $orderDetail->save();
               }
           }else{
            
               $orderDetail = new OrderDetail();
               $orderDetail->order_id = $order->id;
               $orderDetail->course_id = $item->course_id;
               $orderDetail->with_workshop = $item->with_workshop;
               
               if($item->with_workshop == 1){
                   if($paymentMethodId == 1 || $paymentMethodId == 2){
                    $price = $item->course->price_ars;
                    $price = $price + env('WORKSHOP_PRICE_ARS');
                   }else{
                    $price = $item->course->price_usd;
                    $price = $price + env('WORKSHOP_PRICE_USD');
                   }
               }else{
                if($paymentMethodId == 1 || $paymentMethodId == 2){
                    $price = $item->course->price_ars;
                }else{
                    $price = $item->course->price_usd;
                }
               }
               $final_price = $price - ($price * $discount_percentage / 100);
               $orderDetail->price = $final_price;
               $orderDetail->quantity = 1;
               $orderDetail->save();

           }
       }
        
       #obtengo el total de la orden
       $total = OrderDetail::where('order_id', $order->id)->sum('price');

      
     
       switch($paymentMethodId){
        case 1: #Mercado Pago
                #creo la preferencia de pago
                $client_secret = null;
                $checkoutPro = new CheckoutPro();
                $preference = $checkoutPro->processPreference(floatval($total),$user->id,$order->id);                
                if(!$preference){
                    #delete order details
                    OrderDetail::where('order_id', $order->id)->delete();
                    $order->delete();
                    return response()->json(['message' => $preference], 500);
                }
            break;
        case 2: #Transferencia
            $preference = null;
            $client_secret = null;
            break;
        case 3: #Stripe
            $paymentIntent = new PaymentIntentController();
            $client_secret = $paymentIntent->createPaymentIntent(floatval($total),$user->id,$order->id);
            $preference = null;
            break;
       }
     

       $shoppingCart->active = 0;
       $shoppingCart->save();

        $newShoppingCart = new ShoppingCart();
        $newShoppingCart->user_id = $user->id;
        $newShoppingCart->active = 1;
        $newShoppingCart->save();
        
        $shoppingCart = ShoppingCart::with(['items.course'])->where('user_id', $user->id)->where('active', 1)->first();
    }catch(\Exception $e){
        $telegram = new TelegramNotification();
        $telegram->toTelegram($e->getMessage());
        return response()->json(['message' => $e->getMessage()], 500);
    }

        return response()->json(['msg' => 'Carrito procesado correctamente', 'preference_id' => $preference, 'order' => $order, 'client_secret' => $client_secret], 200);

    }


    public function getDiscountsForUser()
    {
        try{
            $accessToken = TokenManager::getTokenFromRequest();
            $user = TokenManager::getUserFromToken($accessToken);

        #count inscriptions of user
        $inscriptions = Inscriptions::where('user_id', $user->id)->count();

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

    public function getDiscounts()
    {
        $accessToken = TokenManager::getTokenFromRequest();
        $user = TokenManager::getUserFromToken($accessToken);

        #count inscriptions of user
        $inscriptions = Inscriptions::where('user_id', $user->id)->count();

        #count courses of user
        $discounts = Discounts::where('courses_amount', '<=', $inscriptions)->orderBy('courses_amount', 'desc')->first();

        if(!$discounts){
            return response()->json(['data' => []], 200);
        }

        return response()->json(['data' => $discounts], 200);
    }

}
    
