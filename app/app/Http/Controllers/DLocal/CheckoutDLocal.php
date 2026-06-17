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
     * @param float       $total        Monto final a cobrar (ya con fee de cuotas incluido).
     * @param int         $userId
     * @param int         $orderId
     * @param string      $currency     Moneda local (ARS por defecto).
     * @param string      $country      Código de país ISO (AR por defecto).
     * @param int         $installments Cantidad de cuotas con tarjeta (1 = pago único). Solo aplica a CREDIT_CARD.
     * @param string|null $paymentType  dLocal Go payment_type: CREDIT_CARD, DEBIT_CARD, BANK_TRANSFER, VOUCHER (coma-separados). null = todos los métodos.
     * @param float       $feeRate      Factor de interés/descuento ya aplicado al total (-0.05, 0.05, 0.10, 0.20, ...). Informativo.
     * @return string|null redirect_url del checkout, o null si falló.
     */
    public function processPayment(
        $total, $userId, $orderId,
        string $currency = 'ARS', string $country = 'AR',
        int $installments = 1, ?string $paymentType = null, float $feeRate = 0,
        ?int $expirationDays = null
    ) {
        try {
            if (floatval($total) <= 0) {
                throw new \Exception('El monto del pago dLocal debe ser mayor a 0 (recibido: ' . $total . ')');
            }

            $user = User::find($userId);

            $dlocal = DLocalGo::getInstance();

            $baseUrl = rtrim(env('URL_WEB', env('FRONT_URL')), '/');
            $successUrl = $baseUrl . '/checkout?status=approved&payment_method=dlocal&order_id=' . $orderId
                . '&installments=' . $installments
                . ($feeRate != 0 ? '&fee_rate=' . $feeRate : '');

            $payload = [
                'amount'           => floatval($total),
                'currency'         => $currency,
                'country'          => $country,
                'order_id'         => (string) $orderId,
                'notification_url' => DLocalGo::getWebhookUrl(),
                'success_url'      => $successUrl,
                'back_url'         => $baseUrl . '/checkout?status=cancelled&payment_method=dlocal&order_id=' . $orderId,
                'error_url'        => $baseUrl . '/checkout?status=error&payment_method=dlocal&order_id=' . $orderId,
                'payer' => [
                    'name'  => $user->name ?? '',
                    'email' => $user->email ?? '',
                ],
            ];

            // Restringir los métodos de pago del checkout (campo real de dLocal Go).
            // CREDIT_CARD | DEBIT_CARD | BANK_TRANSFER | VOUCHER (coma-separados).
            // Si es null, dLocal muestra todos los métodos habilitados.
            if ($paymentType) {
                $payload['payment_type'] = $paymentType;
            }

            // Cuotas: SOLO aplican a CREDIT_CARD (débito/transferencia no soportan cuotas).
            // El recargo (+5/10/20%) ya viene incluido en $total.
            //
            // NOTA: NO enviar 'installments_fee_responsible' salvo que la cuenta tenga
            // habilitado el override de cuotas; de lo contrario dLocal responde
            // 400 {"code":5000,"message":"Merchant cannot modify installments payer
            // configuration"}. Validado en sandbox (merchant 4383): sin ese campo,
            // POST /v1/payments con max_installments=3 devuelve 200 + redirect_url.
            if ($installments > 1) {
                $payload['max_installments'] = $installments;
            }

            // Expiración custom del link (para links manuales compartidos con el cliente).
            // Por defecto dLocal expira el link a las 24h; con esto lo extendemos.
            if ($expirationDays !== null && $expirationDays > 0) {
                $payload['expiration_type'] = 'DAYS';
                $payload['expiration_value'] = $expirationDays;
            }

            $result = $dlocal->createPayment($payload);

            if (!$result['success'] || empty($result['data'])) {
                throw new \Exception($result['message'] ?? 'Error desconocido creando el pago en dLocal Go');
            }

            $data = $result['data'];

            // Respuesta de POST /v1/payments: id (ej. "DP-54354") y redirect_url.
            $paymentId = $data['id'] ?? null;
            $redirectUrl = $data['redirect_url'] ?? null;

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

    /**
     * Inicia una suscripción para "cuotas sin interés" por transferencia/débito.
     *
     * NOTA DE ALCANCE: este método NO se usa desde el checkout del carrito.
     * Queda reservado para el flujo de "creación de orden manual" (administrativo),
     * que se implementará más adelante. El carrito solo usa processPayment().
     *
     * Débito/transferencia NO soportan cuotas (installments) en dLocal: se modela
     * como un plan recurrente MENSUAL de monto÷N con max_periods = N, de modo que
     * dLocal corta los cobros solo al alcanzar las N cuotas. Devuelve el
     * subscribe_url (redirect) que el frontend abre para que el cliente se suscriba.
     *
     * @param float  $total        Monto total de la orden (base, sin interés).
     * @param int    $userId
     * @param int    $orderId
     * @param int    $installments Cantidad de cuotas (3 o 6).
     * @param string $currency
     * @param string $country
     * @return string|null subscribe_url, o null si falló.
     */
    public function processSubscription(
        $total, $userId, $orderId, int $installments,
        string $currency = 'ARS', string $country = 'AR'
    ) {
        try {
            if (floatval($total) <= 0 || $installments < 1) {
                throw new \Exception('Monto o cuotas inválidos para la suscripción dLocal (total: ' . $total . ', cuotas: ' . $installments . ')');
            }

            $dlocal = DLocalGo::getInstance();

            // monto por cuota = total / N (sin interés)
            $amountPerInstallment = round(floatval($total) / $installments, 2);

            $baseUrl = rtrim(env('URL_WEB', env('FRONT_URL')), '/');

            $payload = [
                'name'             => 'Orden ' . $orderId . ' - ' . $installments . ' cuotas sin interés',
                'description'      => 'SinapsisVet - Orden #' . $orderId . ' en ' . $installments . ' cuotas',
                'country'          => $country,
                'currency'         => $currency,
                'amount'           => $amountPerInstallment,
                'frequency_type'   => 'MONTHLY',
                'frequency_value'  => 1,
                'max_periods'      => $installments, // dLocal corta solo al llegar a N cobros
                'notification_url' => DLocalGo::getWebhookUrl(),
                'success_url'      => $baseUrl . '/checkout?status=approved&payment_method=dlocal&order_id=' . $orderId . '&installments=' . $installments,
                'back_url'         => $baseUrl . '/checkout?status=cancelled&payment_method=dlocal&order_id=' . $orderId,
                'error_url'        => $baseUrl . '/checkout?status=error&payment_method=dlocal&order_id=' . $orderId,
            ];

            $result = $dlocal->createSubscriptionPlan($payload);

            if (!$result['success'] || empty($result['data'])) {
                throw new \Exception($result['message'] ?? 'Error creando el plan de suscripción en dLocal Go');
            }

            $data = $result['data'];

            // Respuesta de POST /v1/subscription/plan: id (numérico) y subscribe_url.
            $planId = isset($data['id']) ? (string) $data['id'] : null;
            $subscribeUrl = $data['subscribe_url'] ?? null;

            // external_id NO es un campo del plan: se adjunta como query param en el
            // subscribe_url para que dLocal lo devuelva en cada cobro (ejecución) y
            // así poder vincular el webhook de la suscripción con nuestra Order.
            if ($subscribeUrl) {
                $sep = (strpos($subscribeUrl, '?') !== false) ? '&' : '?';
                $subscribeUrl .= $sep . 'external_id=' . urlencode((string) $orderId);
            }

            $responseDLocal = new ResponseDLocal();
            $responseDLocal->user_id = $userId;
            $responseDLocal->order_id = $orderId;
            $responseDLocal->subscription_id = $planId; // guardamos el plan; la suscripción concreta llega por webhook
            $responseDLocal->redirect_url = $subscribeUrl;
            $responseDLocal->currency = $currency;
            $responseDLocal->status = 'pending';
            $responseDLocal->save();

            return $subscribeUrl;
        } catch (\Exception $e) {
            Log::error('Error iniciando suscripción dLocal Go: ' . $e->getMessage());
            $telegram = new TelegramNotification();
            $telegram->toTelegram('Error suscripción dLocal Go: ' . $e->getMessage());
            return null;
        }
    }
}
