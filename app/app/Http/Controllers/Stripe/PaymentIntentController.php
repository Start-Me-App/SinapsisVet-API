<?php

declare(strict_types=1);
namespace App\Http\Controllers\Stripe;

use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Charge;
use App\Http\Controllers\Controller;
use App\Models\ResponseStripe;
use App\Helper\TelegramNotification;
use Stripe\PaymentIntent;


class PaymentIntentController extends Controller
{
    public function createPaymentIntent($total,$userId,$orderId)
    {
        // Configura tu clave secreta de Stripe
        Stripe::setApiKey(config('services.stripe.secret'));

        // Crea un PaymentIntent
        try {
            $paymentIntent = PaymentIntent::create([
                'amount' => $total * 100,
                'currency' => 'usd',
                'metadata' => ['integration_check' => 'accept_a_payment'],
            ]);


            $responseStripe = new ResponseStripe();
            $responseStripe->user_id = $userId;
            $responseStripe->order_id = $orderId;
            $responseStripe->client_secret = $paymentIntent->client_secret;
            $responseStripe->status = 'pending';
            $responseStripe->save();

            return $paymentIntent->client_secret;
            
        } catch (\Exception $e) {
            $telegram = new TelegramNotification();
            $telegram->toTelegram($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}