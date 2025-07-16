<?php

declare(strict_types=1);
namespace App\Http\Controllers\Stripe;

use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Subscription;
use App\Http\Controllers\Controller;
use App\Models\ResponseStripe;
use App\Helper\TelegramNotification;
use App\Models\Order;
use App\Models\User;

class SubscriptionController extends Controller
{
    public function createSubscription($total, $userId, $orderId)
    {
        try {
            Stripe::setApiKey(config('services.stripe.secret'));

            $user = User::find($userId);
            $order = Order::find($orderId);

            // Crear o recuperar el cliente de Stripe
            $customer = $this->getOrCreateCustomer($user);

            // Crear el producto
            $product = \Stripe\Product::create([
                'name' => 'Suscripción SinapsisVet - 6 meses',
                'description' => 'Suscripción de 6 meses a SinapsisVet',
            ]);

            // Crear el precio
            $price = \Stripe\Price::create([
                'product' => $product->id,
                'unit_amount' => $total * 100,
                'currency' => 'usd',
                'recurring' => [
                    'interval' => 'month',
                ],
            ]);

            // Crear la suscripción
            $subscription = Subscription::create([
                'customer' => $customer->id,
                'items' => [
                    [
                        'price' => $price->id,
                    ],
                ],
                'payment_behavior' => 'default_incomplete',
                'payment_settings' => [
                    'payment_method_types' => ['card'],
                    'save_default_payment_method' => 'on_subscription',
                ],
                'metadata' => [
                    'order_id' => $orderId,
                    'user_id' => $userId
                ],
                'trial_end' => strtotime('+6 months'),
                'cancel_at_period_end' => true,
            ]);


            // Guardar la respuesta de Stripe
            $responseStripe = new ResponseStripe();
            $responseStripe->user_id = $userId;
            $responseStripe->order_id = $orderId;
            $responseStripe->client_secret = null;
            $responseStripe->status = 'pending';
            $responseStripe->subscription_id = $subscription->id;
            $responseStripe->save();

            return $subscription->id;

        } catch (\Exception $e) {
            $telegram = new TelegramNotification();
            $telegram->toTelegram($e->getMessage());
            throw $e;
        }
    }

    private function getOrCreateCustomer($user)
    {
        try {
            // Buscar si el usuario ya tiene un customer_id en Stripe
            if ($user->stripe_customer_id) {
                return \Stripe\Customer::retrieve($user->stripe_customer_id);
            }

            // Crear nuevo cliente en Stripe
            $customer = \Stripe\Customer::create([
                'email' => $user->email,
                'name' => $user->name,
                'metadata' => [
                    'user_id' => $user->id
                ]
            ]);

            // Guardar el customer_id en el usuario
            $user->stripe_customer_id = $customer->id;
            $user->save();

            return $customer;

        } catch (\Exception $e) {
            $telegram = new TelegramNotification();
            $telegram->toTelegram($e->getMessage());
            throw $e;
        }
    }
} 