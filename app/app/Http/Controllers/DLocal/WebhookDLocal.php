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
            $payload = $request->all();
            Log::info('Webhook dLocal Go recibido', $payload);

            // TODO (sandbox): confirmar el header de firma real para validar HMAC.
            $signature = $request->header('X-Signature') ?? $request->header('Signature');

            if (!DLocalGo::validateWebhook($payload, $signature)) {
                return response()->json(['error' => 'Webhook inválido'], 400);
            }

            // TODO (sandbox): confirmar las claves reales del payload.
            $paymentId = $payload['payment_id'] ?? ($payload['id'] ?? null);
            $orderId = $payload['order_id'] ?? null;
            $status = strtoupper((string) ($payload['status'] ?? ''));

            // Si dLocal Go sólo manda el id del pago, consultamos el estado real.
            if ($paymentId && (!$orderId || !$status)) {
                $fetched = DLocalGo::getInstance()->getPayment((string) $paymentId);
                if ($fetched['success'] && !empty($fetched['data'])) {
                    $orderId = $orderId ?: ($fetched['data']['order_id'] ?? null);
                    $status = $status ?: strtoupper((string) ($fetched['data']['status'] ?? ''));
                }
            }

            $responseDLocal = null;
            if ($paymentId) {
                $responseDLocal = ResponseDLocal::where('payment_id', $paymentId)->first();
            }
            if (!$responseDLocal && $orderId) {
                $responseDLocal = ResponseDLocal::where('order_id', $orderId)->first();
            }

            if ($responseDLocal) {
                $responseDLocal->status = $status;
                if ($status === DLocalGo::STATUS_PAID) {
                    $responseDLocal->approved_at = date('Y-m-d H:i:s');
                }
                $responseDLocal->save();
                $orderId = $orderId ?: $responseDLocal->order_id;
            }

            if ($status === DLocalGo::STATUS_PAID && $orderId) {
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

        foreach ($orderDetail as $item) {
            $course = Courses::find($item->course_id);

            $description = 'Pago por dLocal - Orden #' . $order->id . ' - Curso: ' . $course->title;
            $movement = Movements::where('description', $description)->first();
            if (!$movement) {
                $movement = new Movements();
                $precioConDescuento = $item->price * $factorDescuento;

                $movement->amount = $precioConDescuento;
                $movement->amount_neto = $precioConDescuento; // TODO: descontar comisión dLocal real
                $movement->currency = $isUsd ? 1 : 2; // 1 = USD, 2 = ARS
                $movement->description = $description;
                $movement->course_id = $item->course_id;
                $movement->period = date('m-Y');
                $movement->account_id = self::ACCOUNT_ID_DLOCAL;
                $movement->save();
            }
        }
    }
}
