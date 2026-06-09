<?php

namespace App\Http\Controllers\DLocal;

use App\Http\Controllers\Controller;
use App\Models\ResponseDLocal;
use App\Models\User;
use App\Support\DLocalGo;
use App\Helper\TelegramNotification;
use Illuminate\Support\Facades\Log;

/**
 * Inicia un pago en dLocal Go (checkout unificado por API).
 *
 * Espeja el patrón de App\Http\Controllers\Stripe\PaymentIntentController:
 * recibe el total ya calculado (con descuento/recargo según modalidad),
 * el usuario y la orden, llama a dLocal Go, persiste un ResponseDLocal y
 * devuelve el redirect_url al que el frontend debe redirigir al usuario.
 *
 * NOTA: los nombres exactos de los campos del payload y de la respuesta de
 * dLocal Go DEBEN validarse contra el sandbox del cliente. Los TODO marcan
 * los puntos a confirmar.
 */
class CheckoutDLocal extends Controller
{
    /**
     * @param float  $total     Monto final a cobrar (ya con descuento/recargo).
     * @param int    $userId
     * @param int    $orderId
     * @param string $currency  Moneda local a cobrar (ARS, USD, ...).
     * @param string $country   Código de país ISO (AR por defecto).
     * @return string|null      redirect_url del checkout, o null si falló.
     */
    public function processPayment($total, $userId, $orderId, string $currency = 'ARS', string $country = 'AR')
    {
        try {
            $user = User::find($userId);

            $dlocal = DLocalGo::getInstance();

            // TODO (sandbox): confirmar nombres de campos exactos del payload de dLocal Go.
            $payload = [
                'amount' => floatval($total),
                'currency' => $currency,
                'country' => $country,
                'order_id' => (string) $orderId,
                'notification_url' => DLocalGo::getWebhookUrl(),
                'success_url' => rtrim(env('URL_WEB', env('FRONT_URL')), '/') . '/checkout',
                'back_url' => rtrim(env('URL_WEB', env('FRONT_URL')), '/') . '/checkout',
                'payer' => [
                    'name' => $user->name ?? '',
                    'email' => $user->email ?? '',
                ],
            ];

            $result = $dlocal->createPayment($payload);

            if (!$result['success'] || empty($result['data'])) {
                throw new \Exception($result['message'] ?? 'Error desconocido creando el pago en dLocal Go');
            }

            $data = $result['data'];

            // TODO (sandbox): confirmar las claves reales de la respuesta.
            $paymentId = $data['id'] ?? ($data['payment_id'] ?? null);
            $redirectUrl = $data['redirect_url'] ?? ($data['checkout_url'] ?? null);

            $responseDLocal = new ResponseDLocal();
            $responseDLocal->user_id = $userId;
            $responseDLocal->order_id = $orderId;
            $responseDLocal->payment_id = $paymentId;
            $responseDLocal->redirect_url = $redirectUrl;
            $responseDLocal->currency = $currency;
            $responseDLocal->status = 'pending';
            $responseDLocal->save();

            return $redirectUrl;
        } catch (\Exception $e) {
            Log::error('Error iniciando pago dLocal Go: ' . $e->getMessage());
            $telegram = new TelegramNotification();
            $telegram->toTelegram('Error dLocal Go: ' . $e->getMessage());
            return null;
        }
    }
}
