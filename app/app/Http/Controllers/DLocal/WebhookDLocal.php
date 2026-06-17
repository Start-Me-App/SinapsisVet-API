<?php

namespace App\Http\Controllers\DLocal;

use App\Http\Controllers\Controller;
use App\Models\ResponseDLocal;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Inscriptions;
use App\Models\Movements;
use App\Models\Courses;
use App\Support\DLocalGo;
use App\Support\ExchangeRate;
use App\Support\Email\OrdenDeCompraEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook de dLocal Go.
 *
 * Espeja el flujo post-pago de App\Http\Controllers\MercadoPago\WebHook:
 *   validar -> actualizar ResponseDLocal -> si está pago:
 *   actualizar Order -> enviar email -> crear Inscriptions -> crear Movements.
 *
 * account_id = 3 reservado para la cuenta de dLocal Go en Movements
 * (1 = MercadoPago, 2 = Stripe).
 *
 * NOTA: el esquema exacto del payload del webhook (claves, header de firma)
 * DEBE confirmarse contra el sandbox. Los TODO marcan los puntos a validar.
 */
class WebhookDLocal extends Controller
{
    const ACCOUNT_ID_DLOCAL = 3;

    public function notification(Request $request)
    {
        try {
            // Raw body EXACTO: la firma HMAC se calcula sobre el cuerpo sin re-serializar.
            $rawBody = $request->getContent();
            $authHeader = $request->header('Authorization');
            $payload = json_decode($rawBody, true) ?: [];
            Log::info('Webhook dLocal Go recibido', $payload);

            // Validación HMAC-SHA256: HMAC(secret, api_key + raw_body) vs header Signature.
            if (!DLocalGo::validateWebhook($rawBody, $authHeader)) {
                return response()->json(['error' => 'Webhook inválido'], 400);
            }

            // El webhook real de dLocal Go trae { "id": "DP-...", "order_id": "..", "status": ".." }.
            // (Algunos casos traen solo { "payment_id": "DP-..." }; contemplamos ambos.)
            $paymentId = $payload['id'] ?? ($payload['payment_id'] ?? null);
            $orderId   = $payload['order_id'] ?? null;
            $status    = strtoupper((string) ($payload['status'] ?? ''));

            // Si faltan order_id o status, consultamos el pago en dLocal.
            // En cobros de SUSCRIPCIÓN, el order_id que llega es el id de la ejecución
            // (ej. "ST-...-0"), NO nuestro order_id; el nuestro viaja en external_id.
            $externalId = null;
            if ($paymentId && (!$orderId || !$status || !is_numeric($orderId))) {
                $fetched = DLocalGo::getInstance()->getPayment((string) $paymentId);
                if ($fetched['success'] && !empty($fetched['data'])) {
                    $status     = $status ?: strtoupper((string) ($fetched['data']['status'] ?? ''));
                    $externalId = $fetched['data']['external_id'] ?? null;
                    // order_id del pago: solo lo usamos si es numérico (= nuestra Order).
                    $fetchedOrderId = $fetched['data']['order_id'] ?? null;
                    if (!$orderId || !is_numeric($orderId)) {
                        $orderId = is_numeric($fetchedOrderId) ? $fetchedOrderId : null;
                    }
                }
            }

            // Resolver nuestra Order: por payment_id, por order_id numérico, o por
            // external_id (suscripciones). external_id contiene nuestro order_id.
            $resolvedOrderId = is_numeric($orderId) ? $orderId : (is_numeric($externalId) ? $externalId : null);

            $responseDLocal = null;
            if ($paymentId) {
                $responseDLocal = ResponseDLocal::where('payment_id', $paymentId)->first();
            }
            if (!$responseDLocal && $resolvedOrderId) {
                $responseDLocal = ResponseDLocal::where('order_id', $resolvedOrderId)->first();
            }
            $orderId = $resolvedOrderId;

            // Diagnóstico: dejar rastro de cómo se resolvió cada webhook. Clave para
            // depurar cobros de suscripción (donde order_id viene como "ST-..." y el
            // nuestro llega por external_id). Revisar en storage/logs ante un fallo.
            Log::info('Webhook dLocal Go resuelto', [
                'payment_id'       => $paymentId,
                'order_id_raw'     => $payload['order_id'] ?? null,
                'external_id'      => $externalId,
                'resolved_order'   => $resolvedOrderId,
                'status'           => $status,
                'response_dlocal'  => $responseDLocal ? $responseDLocal->id : null,
            ]);

            if ($responseDLocal) {
                $responseDLocal->status = $status;
                if ($status === DLocalGo::STATUS_PAID) {
                    $responseDLocal->approved_at = date('Y-m-d H:i:s');

                    // Obtener balance_amount y balance_fee desde dLocal y convertir a ARS
                    $paymentIdToFetch = $paymentId ?? $responseDLocal->payment_id;
                    if ($paymentIdToFetch) {
                        $detail = DLocalGo::getInstance()->getPayment((string) $paymentIdToFetch);
                        if ($detail['success'] && !empty($detail['data'])) {
                            $netUsd  = (float) ($detail['data']['balance_amount'] ?? 0);
                            $feeUsd  = (float) ($detail['data']['balance_fee'] ?? 0);
                            $rate    = ExchangeRate::usdToArs();
                            $responseDLocal->fee_usd        = $feeUsd ?: null;
                            $responseDLocal->net_amount_usd = $netUsd ?: null;
                            $responseDLocal->exchange_rate  = $rate;
                            $responseDLocal->net_amount_ars = ($netUsd && $rate) ? round($netUsd * $rate, 2) : null;
                        }
                    }
                }
                $responseDLocal->save();
                $orderId = $orderId ?: $responseDLocal->order_id;
            }

            if ($status === DLocalGo::STATUS_PAID && $orderId) {
                // Suscripción: marcamos la orden como pagada con la PRIMERA cuota
                // (el alumno ya empezó a pagar). Las renovaciones siguientes llegan
                // como webhooks adicionales y fulfillOrder() es idempotente.
                $this->fulfillOrder((int) $orderId, $responseDLocal);
            }

            return response()->json(['message' => 'Webhook procesado correctamente'], 200);
        } catch (\Throwable $e) {
            Log::error('Error procesando webhook dLocal Go: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Marca la orden como pagada y crea inscripciones + movimientos + email.
     * Reutiliza la misma lógica de prorrateo de descuentos que MercadoPago.
     */
    private function fulfillOrder(int $orderId, ?ResponseDLocal $responseDLocal): void
    {
        $order = Order::where('id', $orderId)->first();
        if (!$order || $order->status === 'paid') {
            return; // idempotencia: no procesar dos veces
        }

        $order->status = 'paid';
        $order->date_paid = date('Y-m-d H:i:s');
        $order->date_closed = date('Y-m-d H:i:s');
        $order->save();

        $orderDetail = OrderDetail::where('order_id', $order->id)->get();

        $order_email = Order::with(['orderDetails.course'])->find($order->id);
        OrdenDeCompraEmail::sendOrderEmail($order_email);

        foreach ($orderDetail as $item) {
            $inscripcion = Inscriptions::where('user_id', $order->user_id)
                ->where('course_id', $item->course_id)->first();
            if (!$inscripcion) {
                $inscripcion = new Inscriptions();
                $inscripcion->user_id = $order->user_id;
                $inscripcion->course_id = $item->course_id;
                $inscripcion->with_workshop = $item->with_workshop;
                $inscripcion->save();
            } elseif ($inscripcion->with_workshop == 0 && $item->with_workshop == 1) {
                $inscripcion->with_workshop = 1;
                $inscripcion->save();
            }
        }

        // Prorrateo de descuentos sobre cada item (mismo cálculo que MercadoPago).
        $totalOriginal = $orderDetail->sum('price');
        $totalConDescuento = $totalOriginal;

        if ($order->discount_percentage > 0) {
            $totalConDescuento -= $totalConDescuento * $order->discount_percentage / 100;
        }
        if ($order->discount_percentage_coupon > 0) {
            $totalConDescuento -= $totalConDescuento * $order->discount_percentage_coupon / 100;
        }

        // Moneda del cobro: si el ResponseDLocal trae USD usamos esa, si no ARS.
        $currencyCode = $responseDLocal->currency ?? 'ARS';
        $isUsd = strtoupper($currencyCode) === 'USD';
        if ($isUsd) {
            if ($order->discount_amount_usd > 0) {
                $totalConDescuento -= $order->discount_amount_usd;
            }
        } else {
            if ($order->discount_amount_ars > 0) {
                $totalConDescuento -= $order->discount_amount_ars;
            }
        }

        $totalConDescuento = max(0, $totalConDescuento);
        $factorDescuento = $totalOriginal > 0 ? $totalConDescuento / $totalOriginal : 0;

        // Factor neto: proporción de lo acreditado por dLocal vs el total cobrado.
        // Si tenemos net_amount_ars lo usamos; si no, fallback al monto bruto.
        $totalNetoArs = $responseDLocal?->net_amount_ars ?? $totalConDescuento;
        $factorNeto = $totalConDescuento > 0 ? $totalNetoArs / $totalConDescuento : 1;

        foreach ($orderDetail as $item) {
            $course = Courses::find($item->course_id);

            $description = 'Pago por dLocal - Orden #' . $order->id . ' - Curso: ' . $course->title;
            $movement = Movements::where('description', $description)->first();
            if (!$movement) {
                $movement = new Movements();
                $precioConDescuento = $item->price * $factorDescuento;

                $movement->amount      = $precioConDescuento;
                $movement->amount_neto = round($precioConDescuento * $factorNeto, 2);
                $movement->currency    = $isUsd ? 1 : 2; // 1 = USD, 2 = ARS
                $movement->description = $description;
                $movement->course_id   = $item->course_id;
                $movement->period      = date('m-Y');
                $movement->account_id  = self::ACCOUNT_ID_DLOCAL;
                $movement->save();
            }
        }
    }
}
