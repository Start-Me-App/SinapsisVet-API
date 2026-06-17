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

            // dLocal Go tiene DOS formatos de webhook (confirmados en producción):
            //
            //  PAGO:        { "id": "DP-...", "order_id": "966", "status": "PAID" }
            //  SUSCRIPCIÓN: { "invoiceId": "ST-...-0", "subscriptionId": 10127,
            //                 "externalId": "966" }   (sin status: el cobro ya ocurrió)
            $isSubscription = isset($payload['invoiceId']) || isset($payload['subscriptionId']) || isset($payload['externalId']);

            $paymentId = $payload['id'] ?? ($payload['payment_id'] ?? ($payload['invoiceId'] ?? null));
            $externalId = $payload['externalId'] ?? null;
            $status = strtoupper((string) ($payload['status'] ?? ''));

            // order_id: del pago viene en order_id; de la suscripción viene en externalId.
            $rawOrderId = $payload['order_id'] ?? null;
            $resolvedOrderId = is_numeric($rawOrderId) ? $rawOrderId
                : (is_numeric($externalId) ? $externalId : null);

            // En suscripción no llega status; que el webhook de ejecución haya llegado
            // significa que el cobro de la cuota se realizó -> lo tratamos como PAID.
            if ($isSubscription && !$status) {
                $status = DLocalGo::STATUS_PAID;
            }

            // Si es un PAGO y aún faltan datos, los completamos consultando dLocal.
            if (!$isSubscription && $paymentId && (!$resolvedOrderId || !$status)) {
                $fetched = DLocalGo::getInstance()->getPayment((string) $paymentId);
                if ($fetched['success'] && !empty($fetched['data'])) {
                    $status = $status ?: strtoupper((string) ($fetched['data']['status'] ?? ''));
                    $fetchedOrderId = $fetched['data']['order_id'] ?? null;
                    $resolvedOrderId = $resolvedOrderId ?: (is_numeric($fetchedOrderId) ? $fetchedOrderId : null);
                }
            }

            $responseDLocal = null;
            if ($resolvedOrderId) {
                $responseDLocal = ResponseDLocal::where('order_id', $resolvedOrderId)->first();
            }
            if (!$responseDLocal && $paymentId) {
                $responseDLocal = ResponseDLocal::where('payment_id', $paymentId)->first();
            }
            $orderId = $resolvedOrderId ?: ($responseDLocal->order_id ?? null);

            // Diagnóstico: dejar rastro de cómo se resolvió cada webhook. Clave para
            // depurar cobros de suscripción (donde order_id viene como "ST-..." y el
            // nuestro llega por external_id). Revisar en storage/logs ante un fallo.
            Log::info('Webhook dLocal Go resuelto', [
                'is_subscription'  => $isSubscription,
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

                    // Obtener balance_amount y balance_fee desde dLocal y convertir a ARS.
                    // Solo para PAGOS: en suscripción el id es "ST-..." (ejecución), no
                    // consultable por /v1/payments, así que se omite el detalle de balance.
                    $paymentIdToFetch = $isSubscription ? null : ($paymentId ?? $responseDLocal->payment_id);
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
