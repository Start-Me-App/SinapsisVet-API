<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Support\Hotmart;
use App\Models\{Order, OrderDetail, Inscriptions, Courses, User, HotmartEvent};
use Exception;

class HotmartController extends Controller
{
    /**
     * Procesar webhook de Hotmart
     *
     * Hotmart envía notificaciones POST a esta URL cuando ocurren eventos
     * como compras, cancelaciones, reembolsos, etc.
     *
     * Nota: Hotmart ya no usa hottok en las versiones recientes de la API.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function webhook(Request $request): JsonResponse
    {
        try {
            // Obtener datos del webhook
            $webhookData = $request->all();

            // Validar estructura del webhook
            if (!Hotmart::validateWebhook($webhookData)) {
                Log::warning('Webhook de Hotmart con estructura inválida', [
                    'ip' => $request->ip(),
                    'data' => $webhookData
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Webhook inválido'
                ], 400);
            }

            // Validar hottok si está presente (backward compatibility)
            $hottok = $request->header('X-Hotmart-Hottok');
            if ($hottok && !Hotmart::validateWebhookToken($hottok)) {
                Log::warning('Webhook de Hotmart con hottok inválido', [
                    'hottok' => $hottok,
                    'ip' => $request->ip()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Token de autenticación inválido'
                ], 401);
            }

            Log::info('Webhook de Hotmart recibido', [
                'event' => $webhookData['event'] ?? 'unknown',
                'ip' => $request->ip(),
                'has_hottok' => !empty($hottok),
                'data' => $webhookData
            ]);

            // Procesar el evento
            $hotmart = Hotmart::getInstance();
            $result = $hotmart->processWebhookEvent($webhookData);

            // Si es un evento de compra aprobada, crear la orden e inscripción
            if (
                in_array($webhookData['event'] ?? null, [
                    Hotmart::EVENT_PURCHASE_COMPLETE,
                    Hotmart::EVENT_PURCHASE_APPROVED
                ])
            ) {
                $this->processApprovedPurchase($webhookData);
            }

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message']
            ], $result['success'] ? 200 : 500);

        } catch (Exception $e) {
            Log::error('Error procesando webhook de Hotmart: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Procesar compra aprobada y crear orden/inscripción
     *
     * @param array $webhookData
     * @return void
     */
    private function processApprovedPurchase(array $webhookData): void
    {
        try {
            $orderData = Hotmart::extractOrderData($webhookData);

            if (!$orderData) {
                Log::error('No se pudieron extraer datos de la compra de Hotmart');
                return;
            }

            // Registrar el evento en la base de datos
            $hotmartEvent = HotmartEvent::create([
                'event_type' => $webhookData['event'] ?? 'UNKNOWN',
                'transaction_id' => $orderData['transaction_id'],
                'product_id' => $orderData['product_id'],
                'product_name' => $orderData['product_name'],
                'buyer_email' => $orderData['buyer_email'],
                'buyer_name' => $orderData['buyer_name'],
                'status' => $orderData['status'],
                'price_value' => $orderData['price_value'],
                'price_currency' => $orderData['price_currency'],
                'commission_value' => $orderData['commission_value'],
                'approved_date' => $orderData['approved_date'],
                'raw_data' => $webhookData,
                'processed' => false
            ]);

            // Buscar o crear usuario
            $user = User::where('email', $orderData['buyer_email'])->first();

            if (!$user) {
                // Crear usuario si no existe
                $user = new User();
                $user->email = $orderData['buyer_email'];
                $user->name = $orderData['buyer_name'];
                $user->telephone = $orderData['buyer_phone'];
                $user->password = bcrypt(\Illuminate\Support\Str::random(16)); // Password temporal
                $user->save();

                Log::info('Usuario creado automáticamente desde Hotmart', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
            }

            // Buscar si ya existe una orden con esta transacción
            $existingOrder = Order::where('external_reference', $orderData['transaction_id'])->first();

            if ($existingOrder) {
                Log::info('Orden ya existe para esta transacción de Hotmart', [
                    'order_id' => $existingOrder->id,
                    'transaction_id' => $orderData['transaction_id']
                ]);
                return;
            }

            // Crear orden
            $order = new Order();
            $order->user_id = $user->id;
            $order->status = 'paid';
            $order->date_created = now();
            $order->date_last_updated = now();
            $order->date_paid = $orderData['approved_date'] ?? now();
            $order->date_closed = now();
            $order->payment_method_id = 5; // 5 = Hotmart (debes agregarlo a tu tabla de métodos de pago)
            $order->external_reference = $orderData['transaction_id'];
            $order->shopping_cart_id = null;

            // Asignar total según moneda
            if ($orderData['price_currency'] === 'USD') {
                $order->total_amount_usd = $orderData['price_value'];
                $order->total_amount_ars = null;
            } else {
                $order->total_amount_ars = $orderData['price_value'];
                $order->total_amount_usd = null;
            }

            $order->save();

            // Buscar curso por el product_id de Hotmart
            // Nota: Deberás tener una relación entre tu course_id y el product_id de Hotmart
            // Por ahora, esto es un placeholder - ajusta según tu lógica
            $course = $this->findCourseByHotmartProductId($orderData['product_id']);

            if ($course) {
                // Crear detalle de orden
                $orderDetail = new OrderDetail();
                $orderDetail->order_id = $order->id;
                $orderDetail->course_id = $course->id;
                $orderDetail->price = $orderData['price_value'];
                $orderDetail->quantity = 1;
                $orderDetail->with_workshop = 0;
                $orderDetail->save();

                // Crear inscripción
                $inscription = Inscriptions::where('user_id', $user->id)
                    ->where('course_id', $course->id)
                    ->first();

                if (!$inscription) {
                    $inscription = new Inscriptions();
                    $inscription->user_id = $user->id;
                    $inscription->course_id = $course->id;
                    $inscription->with_workshop = 0;
                    $inscription->save();

                    Log::info('Inscripción creada desde Hotmart', [
                        'user_id' => $user->id,
                        'course_id' => $course->id,
                        'order_id' => $order->id
                    ]);
                }
            } else {
                Log::warning('No se encontró curso para el product_id de Hotmart', [
                    'product_id' => $orderData['product_id'],
                    'product_name' => $orderData['product_name']
                ]);
            }

            // Marcar el evento como procesado y asociar con la orden
            $hotmartEvent->order_id = $order->id;
            $hotmartEvent->processed = true;
            $hotmartEvent->save();

            Log::info('Orden creada desde webhook de Hotmart', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'transaction_id' => $orderData['transaction_id'],
                'hotmart_event_id' => $hotmartEvent->id
            ]);

        } catch (Exception $e) {
            Log::error('Error creando orden desde Hotmart: ' . $e->getMessage(), [
                'exception' => $e,
                'webhook_data' => $webhookData
            ]);
        }
    }

    /**
     * Encontrar curso por product_id de Hotmart
     *
     * Busca un curso que tenga configurado el hotmart_product_id
     *
     * @param string|null $hotmartProductId
     * @return Courses|null
     */
    private function findCourseByHotmartProductId(?string $hotmartProductId): ?Courses
    {
        if (!$hotmartProductId) {
            return null;
        }

        // Buscar por el campo hotmart_product_id
        return Courses::where('hotmart_product_id', $hotmartProductId)->first();
    }

    /**
     * Obtener información de una suscripción
     *
     * @param Request $request
     * @param string $subscriberCode
     * @return JsonResponse
     */
    public function getSubscription(Request $request, string $subscriberCode): JsonResponse
    {
        try {
            $hotmart = Hotmart::getInstance();
            $result = $hotmart->getSubscription($subscriberCode);

            return response()->json($result, $result['success'] ? 200 : 400);

        } catch (Exception $e) {
            Log::error('Error obteniendo suscripción de Hotmart: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo suscripción',
                'data' => null
            ], 500);
        }
    }

    /**
     * Obtener historial de ventas
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getSalesHistory(Request $request): JsonResponse
    {
        try {
            $params = $request->only([
                'transaction_status',
                'start_date',
                'end_date',
                'product_id',
                'buyer_email',
                'max_results',
                'page_token'
            ]);

            $hotmart = Hotmart::getInstance();
            $result = $hotmart->getSalesHistory($params);

            return response()->json($result, $result['success'] ? 200 : 400);

        } catch (Exception $e) {
            Log::error('Error obteniendo historial de ventas de Hotmart: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo historial de ventas',
                'data' => null
            ], 500);
        }
    }

    /**
     * Probar conexión con Hotmart
     *
     * @return JsonResponse
     */
    public function testConnection(): JsonResponse
    {
        try {
            $hotmart = Hotmart::getInstance();
            $result = $hotmart->getAccessToken();

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success']
                    ? 'Conexión exitosa con Hotmart'
                    : 'Error en la conexión con Hotmart',
                'data' => $result['success'] ? ['connected' => true] : $result['data']
            ], $result['success'] ? 200 : 400);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error probando conexión: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
