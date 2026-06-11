<?php
namespace App\Support;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Cliente para integración con dLocal Go (https://dlocalgo.com).
 *
 * IMPORTANTE: dLocal Go (autoservicio) es un producto DISTINTO de dLocal
 * "enterprise" (api.dlocal.com). Esta clase apunta a la API de dLocal Go.
 *
 * Entornos:
 *   - Sandbox: https://api-sbx.dlocalgo.com
 *   - Producción: https://api.dlocalgo.com
 * Autenticación: header "Authorization: Bearer <API_KEY>:<API_SECRET>".
 *
 * Endpoints (confirmados contra https://docs.dlocalgo.com/integration-api):
 *   - POST   /v1/payments                         crear pago (devuelve redirect_url)
 *   - GET    /v1/payments/{id}                     consultar pago (status, balance_amount, card.bin, ...)
 *   - POST   /v1/subscription/plan                 crear plan (soporta max_periods para limitar cobros)
 *   - POST   /v1/subscription/plan/{id}/cancel     cancelar plan
 *   - POST   /v1/subscription/{id}/cancel          cancelar suscripción
 *   - GET    /v1/subscription/{id}/execution       ejecuciones (cobros) de la suscripción
 *
 * Webhook: dLocal Go envía POST {"payment_id": "..."} firmado con HMAC-SHA256.
 * Header: "Authorization: V2-HMAC-SHA256, Signature: <hex>".
 * signature = HMAC_SHA256(secret_key, api_key + raw_body).
 *
 * Patrón de respuesta de todos los métodos: ['success' => bool, 'message' => string, 'data' => mixed]
 * (igual que App\Support\Hotmart para mantener consistencia en el codebase).
 */
class DLocalGo
{
    // Estados de pago de dLocal Go
    const STATUS_PAID = 'PAID';
    const STATUS_PENDING = 'PENDING';
    const STATUS_REJECTED = 'REJECTED';
    const STATUS_CANCELLED = 'CANCELLED';
    const STATUS_EXPIRED = 'EXPIRED';
    const STATUS_REFUNDED = 'REFUNDED';

    private static $instance = null;
    private $client;
    private $apiKey;
    private $apiSecret;
    private $baseUrl;

    public static function getInstance(): DLocalGo
    {
        if (self::$instance == null) {
            self::$instance = new DLocalGo();
        }
        return self::$instance;
    }

    public function __construct()
    {
        $this->apiKey = env('DLOCALGO_API_KEY');
        $this->apiSecret = env('DLOCALGO_API_SECRET');
        // Default a sandbox; en producción setear DLOCALGO_BASE_URL=https://api.dlocalgo.com
        $this->baseUrl = env('DLOCALGO_BASE_URL', 'https://api-sbx.dlocalgo.com');

        $this->client = new Client([
            'base_uri' => rtrim($this->baseUrl, '/') . '/',
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                // dLocal Go usa "Bearer <API_KEY>:<API_SECRET>"
                'Authorization' => 'Bearer ' . $this->apiKey . ':' . $this->apiSecret,
            ],
        ]);
    }

    /**
     * URL del webhook (notification_url) que dLocal Go llamará.
     */
    public static function getWebhookUrl(): string
    {
        return rtrim(env('APP_URL'), '/') . env('DLOCALGO_WEBHOOK_PATH', '/api/dlocal/webhook');
    }

    /**
     * Crear un pago en dLocal Go.
     *
     * @param array $payload Campos de POST /v1/payments:
     *   - amount (number)*          monto a cobrar
     *   - currency (string)*        ISO-4217 (ARS, USD, ...). Si no coincide con country, dLocal convierte (FX).
     *   - country (string)          ISO 3166-1 alpha-2 (AR, ...)
     *   - order_id (string)         referencia externa = id de nuestra Order
     *   - notification_url (string) webhook
     *   - success_url / back_url    redirección post-pago
     *   - payer (object)            name, email, document, ...
     *   - payment_type (string)     CREDIT_CARD, DEBIT_CARD, BANK_TRANSFER, VOUCHER (coma-separados) para restringir métodos
     *   - max_installments (number) máximo de cuotas (solo aplica a CREDIT_CARD)
     *   - installments_fee_responsible (string) BUYER | MERCHANT
     * @return array
     */
    public function createPayment(array $payload): array
    {
        try {
            $response = $this->client->post('v1/payments', [
                'json' => $payload,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => true,
                'message' => 'Pago creado con éxito',
                'data' => $data,
            ];
        } catch (GuzzleException $e) {
            Log::error('Error creando pago en dLocal Go: ' . $e->getMessage(), $payload);
            return [
                'success' => false,
                'message' => 'Error al crear el pago: ' . $e->getMessage(),
                'data' => null,
            ];
        } catch (Exception $e) {
            Log::error('Error inesperado creando pago en dLocal Go: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error inesperado: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Consultar el estado de un pago.
     */
    public function getPayment(string $paymentId): array
    {
        try {
            $response = $this->client->get("v1/payments/{$paymentId}");
            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => true,
                'message' => 'Pago obtenido con éxito',
                'data' => $data,
            ];
        } catch (GuzzleException $e) {
            Log::error('Error obteniendo pago de dLocal Go: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al obtener el pago: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Crear un plan de suscripción (usado para cuotas sin interés por
     * transferencia/débito: N cobros de monto÷N).
     *
     * dLocal Go SÍ limita los cobros de forma nativa con `max_periods`, así
     * que NO hace falta contar cobros ni cancelar manualmente: el plan se
     * detiene solo al alcanzar max_periods. La respuesta trae `subscribe_url`
     * (el link de redirección para que el cliente se suscriba).
     *
     * @param array $payload Campos de POST /v1/subscription/plan:
     *   - name*, description*, currency*, amount*, frequency_type* (DAILY|WEEKLY|MONTHLY|YEARLY)
     *   - country, frequency_value, day_of_month
     *   - max_periods            límite de cobros (3 o 6 para cuotas sin interés)
     *   - notification_url, success_url, back_url, error_url
     * @return array
     */
    public function createSubscriptionPlan(array $payload): array
    {
        try {
            $response = $this->client->post('v1/subscription/plan', [
                'json' => $payload,
            ]);
            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => true,
                'message' => 'Plan de suscripción creado con éxito',
                'data' => $data,
            ];
        } catch (GuzzleException $e) {
            Log::error('Error creando plan de suscripción en dLocal Go: ' . $e->getMessage(), $payload);
            return [
                'success' => false,
                'message' => 'Error al crear el plan: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Obtener las ejecuciones (cobros) de una suscripción.
     * Se usa para contar cuántas cuotas se cobraron y decidir la cancelación.
     */
    public function getSubscriptionExecutions(string $subscriptionId): array
    {
        try {
            $response = $this->client->get("v1/subscription/{$subscriptionId}/execution");
            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => true,
                'message' => 'Ejecuciones obtenidas con éxito',
                'data' => $data,
            ];
        } catch (GuzzleException $e) {
            Log::error('Error obteniendo ejecuciones de suscripción en dLocal Go: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al obtener ejecuciones: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Cancelar (desactivar) la suscripción de un suscriptor concreto.
     * Endpoint real: PATCH /v1/subscription/plan/{planId}/subscription/{subscriptionId}/deactivate
     */
    public function cancelSubscription(string $planId, string $subscriptionId): array
    {
        try {
            $response = $this->client->patch("v1/subscription/plan/{$planId}/subscription/{$subscriptionId}/deactivate");
            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => true,
                'message' => 'Suscripción cancelada con éxito',
                'data' => $data,
            ];
        } catch (GuzzleException $e) {
            Log::error('Error cancelando suscripción en dLocal Go: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al cancelar la suscripción: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Cancelar (desactivar) un plan completo y sus suscripciones.
     * Endpoint real: PATCH /v1/subscription/plan/{planId}/deactivate
     * Verificado en sandbox: devuelve el plan con "active": false.
     */
    public function cancelPlan(string $planId): array
    {
        try {
            $response = $this->client->patch("v1/subscription/plan/{$planId}/deactivate");
            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => true,
                'message' => 'Plan cancelado con éxito',
                'data' => $data,
            ];
        } catch (GuzzleException $e) {
            Log::error('Error cancelando plan en dLocal Go: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al cancelar el plan: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Validar la autenticidad de un webhook entrante mediante HMAC-SHA256.
     *
     * dLocal Go firma cada notificación. El header tiene la forma:
     *   "Authorization: V2-HMAC-SHA256, Signature: <hex>"
     * y la firma se calcula como:
     *   signature = HMAC_SHA256(secret_key, api_key + raw_body)
     *
     * Se compara contra el raw body EXACTO recibido (no re-serializado),
     * porque cualquier diferencia de formato cambia el hash.
     *
     * @param string      $rawBody   Cuerpo crudo del request (request()->getContent()).
     * @param string|null $authHeader Header Authorization recibido.
     * @return bool
     */
    public static function validateWebhook(string $rawBody, ?string $authHeader): bool
    {
        $apiKey = env('DLOCALGO_API_KEY');
        $secretKey = env('DLOCALGO_API_SECRET');

        if (empty($rawBody)) {
            Log::warning('Webhook de dLocal Go recibido sin cuerpo');
            return false;
        }

        if (empty($authHeader)) {
            Log::warning('Webhook de dLocal Go sin header Authorization');
            return false;
        }

        // Extraer la firma del header "V2-HMAC-SHA256, Signature: <hex>"
        if (!preg_match('/Signature:\s*([a-f0-9]+)/i', $authHeader, $m)) {
            Log::warning('Webhook de dLocal Go con header de firma malformado', ['header' => $authHeader]);
            return false;
        }
        $receivedSignature = strtolower($m[1]);

        $expectedSignature = hash_hmac('sha256', $apiKey . $rawBody, $secretKey);

        if (!hash_equals($expectedSignature, $receivedSignature)) {
            Log::warning('Webhook de dLocal Go con firma inválida', [
                'expected' => $expectedSignature,
                'received' => $receivedSignature,
            ]);
            return false;
        }

        return true;
    }
}
