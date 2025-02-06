<?php

declare(strict_types=1);
namespace App\Http\Controllers\Stripe;

use Illuminate\Http\Request;
use Stripe\Webhook;
use Stripe\Stripe;
use Illuminate\Support\Facades\Log;
use App\Models\Order;   
use App\Models\OrderDetail;
use App\Models\Inscriptions;
use App\Models\ResponseStripe;
use App\Http\Controllers\Controller;

class StripeWebhookController extends Controller
{
    public function handleWebhook(Request $request)
    {
        // Configura la clave secreta de Stripe
        Stripe::setApiKey(config('services.stripe.secret'));

        // Obtén el payload y la firma del webhook
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        try {
            // Verifica la firma del webhook
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\Exception $e) {
            // Si la firma no es válida, devuelve un error
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Maneja el evento
        Log::info('Evento recibido:', ['event' => $event->type]);
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $responseStripe = ResponseStripe::where('client_secret', $event->data->object->client_secret)->first();
                $responseStripe->status = 'approved';
                $responseStripe->approved_at = now();
                $responseStripe->save();


                $order = Order::where('id', $responseStripe->order_id)->first();
                $order->status = 'paid';
                $order->date_paid = date('Y-m-d H:i:s');
                $order->date_closed = date('Y-m-d H:i:s');
                $order->save();
    
                $orderDetail = OrderDetail::where('order_id', $order->id)->get();
                foreach($orderDetail as $item){
    
                    #check if the inscription already exists
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
                $paymentIntent = $event->data->object;
                Log::info('Pago exitoso:', ['payment_intent' => $paymentIntent->id]);
                break;
            case 'payment_intent.payment_failed':
                $responseStripe = ResponseStripe::where('client_secret', $event->data->object->client_secret)->first();
                $responseStripe->status = 'failed';
                $responseStripe->save();


                $order = Order::where('id', $responseStripe->order_id)->first();
                $order->status = 'failed';
                $order->date_closed = date('Y-m-d H:i:s');
                $order->save();
                
                $paymentIntent = $event->data->object;
                Log::info('Pago fallido:', ['payment_intent' => $paymentIntent]);
                break;
            default:
                Log::info('Evento no manejado:', ['type' => $event->type]);
                break;
        }

        return response()->json(['success' => true]);
    }
}