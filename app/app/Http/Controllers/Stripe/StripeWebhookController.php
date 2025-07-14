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
use App\Models\Movements;
use App\Models\Courses;
use App\Http\Controllers\Controller;
use App\Support\Email\OrdenDeCompraEmail;

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
                $this->handlePaymentIntentSucceeded($event);
                break;
            case 'payment_intent.payment_failed':
                $this->handlePaymentIntentFailed($event);
                break;
            case 'invoice.payment_succeeded':
                $this->handleInvoicePaymentSucceeded($event);
                break;
            case 'invoice.payment_failed':
                $this->handleInvoicePaymentFailed($event);
                break;
            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($event);
                break;
            default:
                Log::info('Evento no manejado:', ['type' => $event->type]);
                break;
        }

        return response()->json(['success' => true]);
    }

    private function handlePaymentIntentSucceeded($event)
    {
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

        $order_email = Order::with(['orderDetails.course'])->find($order->id);
        OrdenDeCompraEmail::sendOrderEmail($order_email);

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

        // Crear movimientos para cada item del pedido
        foreach($orderDetail as $item){
            $course = Courses::find($item->course_id);
            $movement = new Movements();
            $movement->amount = $item->price;
            $movement->amount_neto = $item->price; // Para Stripe no aplicamos comisión aquí
            $movement->currency = 1; // USD para Stripe
            $movement->description = 'Pago de suscripción Stripe - Orden #'.$order->id.' - Curso: '.$course->title;
            $movement->course_id = $item->course_id;
            $movement->period = date('m-Y');
            $movement->account_id = 2; // Stripe no tiene account_id específico
            $movement->save();
        }
        
        Log::info('Pago exitoso:', ['payment_intent' => $event->data->object->id]);
    }

    private function handlePaymentIntentFailed($event)
    {
        $responseStripe = ResponseStripe::where('client_secret', $event->data->object->client_secret)->first();
        $responseStripe->status = 'failed';
        $responseStripe->save();

        $order = Order::where('id', $responseStripe->order_id)->first();
        $order->status = 'failed';
        $order->date_closed = date('Y-m-d H:i:s');
        $order->save();
        
        Log::info('Pago fallido:', ['payment_intent' => $event->data->object->id]);
    }

    private function handleInvoicePaymentSucceeded($event)
    {
        $invoice = $event->data->object;
        $subscriptionId = $invoice->subscription;
        
        $responseStripe = ResponseStripe::where('subscription_id', $subscriptionId)->first();
        if ($responseStripe) {
            $responseStripe->status = 'approved';
            $responseStripe->approved_at = now();
            $responseStripe->save();

            $order = Order::where('id', $responseStripe->order_id)->first();
            if ($order) {
                $order->status = 'paid';
                $order->date_paid = date('Y-m-d H:i:s');
                $order->date_closed = date('Y-m-d H:i:s');
                $order->save();

                $orderDetail = OrderDetail::where('order_id', $order->id)->get();
                foreach($orderDetail as $item){
                    $inscripcion = Inscriptions::where('user_id', $order->user_id)
                        ->where('course_id', $item->course_id)
                        ->first();
                    if(!$inscripcion){
                        $inscripcion = new Inscriptions();
                        $inscripcion->user_id = $order->user_id;
                        $inscripcion->course_id = $item->course_id;
                        $inscripcion->with_workshop = $item->with_workshop;
                        $inscripcion->save();
                    }
                }

              
            }
        }
        
        Log::info('Pago de factura exitoso:', ['invoice' => $invoice->id]);
    }

    private function handleInvoicePaymentFailed($event)
    {
        $invoice = $event->data->object;
        $subscriptionId = $invoice->subscription;
        
        $responseStripe = ResponseStripe::where('subscription_id', $subscriptionId)->first();
        if ($responseStripe) {
            $responseStripe->status = 'failed';
            $responseStripe->save();

            $order = Order::where('id', $responseStripe->order_id)->first();
            if ($order) {
                $order->status = 'failed';
                $order->date_closed = date('Y-m-d H:i:s');
                $order->save();
            }
        }
        
        Log::info('Pago de factura fallido:', ['invoice' => $invoice->id]);
    }

    private function handleSubscriptionDeleted($event)
    {
        $subscription = $event->data->object;
        $responseStripe = ResponseStripe::where('subscription_id', $subscription->id)->first();
        
        if ($responseStripe) {
            $responseStripe->status = 'cancelled';
            $responseStripe->save();
            
            Log::info('Suscripción cancelada:', ['subscription' => $subscription->id]);
        }
    }
}