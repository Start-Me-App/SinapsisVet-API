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
 * "enterprise" (api.dlocal.com, firma HMAC). Esta clase apunta a la API de
 * dLocal Go, cuya autenticación es un Bearer token con el formato
 * "<API_KEY>:<API_SECRET>".
 *
 * Endpoints (confirmados contra el cliente Ruby oficial y la doc pública;
 * los campos exactos de request/response DEBEN validarse contra el sandbox
 * del cliente antes de ir a producción):
 *   - POST   /v1/payments                         crear pago (devuelve redirect_url)
 *   - GET    /v1/payments/{id}                     consultar pago
 *   - POST   /v1/subscription/plan                 crear plan de suscripción
 *   - POST   /v1/subscription/plan/{id}/cancel     cancelar plan
 *   - POST   /v1/subscription/{id}/cancel          cancelar suscripción
 *   - GET    /v1/subscription/{id}/execution       ejecuciones (cobros) de la suscripción
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
        $this->baseUrl = env('DLOCALGO_BASE_URL', 'https://api.dlocalgo.com');

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
     * @param array $payload Campos esperados (validar contra sandbox):
     *   - amount (float)            monto a cobrar
     *   - currency (string)         moneda local (ARS, USD, ...)
     *   - country (string)          código de país ISO (AR, ...)
     *   - order_id (string)         referencia externa = id de nuestra Order
     *   - notification_url (string) webhook
     *   - success_url / back_url    redirección post-pago
     *   - payment_method_flow / payment_method_id  restricción de método (1 pago vs cuotas)
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
     * transferencia/débito). El límite de N cobros NO lo controla dLocal Go:
     * se controla localmente y se cancela con cancelSubscription() al llegar a N.
     *
     * @param array $payload  amount, currency, country, frequency_type, frequency_value, etc.
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
     * Cancelar una suscripción (corta el ciclo de cobros recurrentes).
     * Núcleo del control de "límite estricto de cuotas" pedido por el cliente.
     */
    public function cancelSubscription(string $subscriptionId): array
    {
        try {
            $response = $this->client->post("v1/subscription/{$subscriptionId}/cancel");
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
     * Cancelar un plan de suscripción completo.
     */
    public function cancelPlan(string $planId): array
    {
        try {
            $response = $this->client->post("v1/subscription/plan/{$planId}/cancel");
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
     * Validar la autenticidad de un webhook entrante.
     *
     * dLocal Go firma los webhooks; el esquema exacto (header de firma / HMAC
     * con el secret) DEBE confirmarse contra el sandbox. Por ahora se valida
     * estructura mínima, igual que el patrón provisional de Hotmart.
     *
     * @param array       $payload   Cuerpo del webhook
     * @param string|null $signature Header de firma recibido
     * @return bool
     */
    public static function validateWebhook(array $payload, ?string $signature = null): bool
    {
        if (empty($payload)) {
            Log::warning('Webhook de dLocal Go recibido sin datos');
            return false;
        }

        // TODO: confirmar contra sandbox el header de firma y validar HMAC con DLOCALGO_API_SECRET.
        // Estructura mínima esperada: debe traer un identificador de pago/suscripción.
        if (!isset($payload['payment_id']) && !isset($payload['id']) && !isset($payload['subscription_id'])) {
            Log::warning('Webhook de dLocal Go con estructura inválida', $payload);
            return false;
        }

        return true;
    }
}
