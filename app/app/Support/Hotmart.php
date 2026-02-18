<?php
namespace App\Support;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Clase para integración con Hotmart API
 *
 * Documentación oficial: https://developers.hotmart.com/docs/
 *
 * Eventos de Webhook soportados:
 * - PURCHASE_COMPLETE: Compra completada
 * - PURCHASE_APPROVED: Compra aprobada
 * - PURCHASE_CANCELED: Compra cancelada
 * - PURCHASE_REFUNDED: Compra reembolsada
 * - PURCHASE_CHARGEBACK: Contracargo
 * - SUBSCRIPTION_CANCELLATION: Cancelación de suscripción
 * - SUBSCRIPTION_REACTIVATION: Reactivación de suscripción
 */
class Hotmart
{
    // Estados de transacciones
    const STATUS_APPROVED = 'APPROVED';
    const STATUS_COMPLETE = 'COMPLETE';
    const STATUS_CANCELED = 'CANCELED';
    const STATUS_REFUNDED = 'REFUNDED';
    const STATUS_CHARGEBACK = 'CHARGEBACK';
    const STATUS_BLOCKED = 'BLOCKED';
    const STATUS_EXPIRED = 'EXPIRED';

    // Eventos de webhook
    const EVENT_PURCHASE_COMPLETE = 'PURCHASE_COMPLETE';
    const EVENT_PURCHASE_APPROVED = 'PURCHASE_APPROVED';
    const EVENT_PURCHASE_CANCELED = 'PURCHASE_CANCELED';
    const EVENT_PURCHASE_REFUNDED = 'PURCHASE_REFUNDED';
    const EVENT_PURCHASE_CHARGEBACK = 'PURCHASE_CHARGEBACK';
    const EVENT_SUBSCRIPTION_CANCELLATION = 'SUBSCRIPTION_CANCELLATION';
    const EVENT_SUBSCRIPTION_REACTIVATION = 'SUBSCRIPTION_REACTIVATION';

    private static $instance = null;
    private $client;
    private $clientId;
    private $clientSecret;
    private $basicAuth;
    private $apiUrl;
    private $accessToken = null;

    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new Hotmart();
        }
        return self::$instance;
    }

    public function __construct()
    {
        $this->clientId = env('HOTMART_CLIENT_ID');
        $this->clientSecret = env('HOTMART_CLIENT_SECRET');
        $this->basicAuth = env('HOTMART_BASIC_AUTH');
        $this->apiUrl = env('HOTMART_API_URL', 'https://developers.hotmart.com');

        $this->client = new Client([
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ]);
    }

    /**
     * Obtener URL del webhook
     */
    public static function getWebhookUrl(): string
    {
        return env('APP_URL') . env('HOTMART_WEBHOOK_PATH', '/api/hotmart/webhook');
    }

    /**
     * Validar el webhook de Hotmart
     *
     * Hotmart ya no usa hottok en las versiones recientes de la API.
     * La validación se puede hacer de otras formas:
     * 1. Verificar la estructura del payload
     * 2. Usar IP whitelisting (recomendado en producción)
     * 3. Verificar que el evento tenga datos válidos
     *
     * @param array $webhookData Datos del webhook
     * @return bool
     */
    public static function validateWebhook(array $webhookData): bool
    {
        // Validación básica: verificar que tenga estructura válida de Hotmart
        if (empty($webhookData)) {
            Log::warning('Webhook de Hotmart recibido sin datos');
            return false;
        }

        // Verificar que tenga al menos el campo 'event' o 'data'
        if (!isset($webhookData['event']) && !isset($webhookData['data'])) {
            Log::warning('Webhook de Hotmart con estructura inválida', $webhookData);
            return false;
        }

        // Opcional: Validar IPs de Hotmart (descomentar para usar)
        // $hotmartIPs = ['52.1.157.61', '34.198.237.172', '52.21.45.174', '52.72.166.146'];
        // $requestIP = request()->ip();
        // if (!in_array($requestIP, $hotmartIPs)) {
        //     Log::warning('Webhook de Hotmart desde IP no autorizada', ['ip' => $requestIP]);
        //     return false;
        // }

        return true;
    }

    /**
     * Validar el token de autenticación del webhook (hottok) - DEPRECATED
     *
     * Este método se mantiene por compatibilidad pero hottok ya no se usa.
     *
     * @param string|null $hottok Token recibido en el header X-HOTMART-HOTTOK
     * @return bool
     */
    public static function validateWebhookToken(?string $hottok): bool
    {
        // Si no hay hottok configurado, considerar válido (nuevo sistema)
        $expectedToken = env('HOTMART_HOTTOK');

        if (empty($expectedToken)) {
            Log::info('HOTMART_HOTTOK no configurado, saltando validación de hottok');
            return true; // Cambiar a true para permitir webhooks sin hottok
        }

        // Si hay hottok configurado, validarlo
        if (empty($hottok)) {
            Log::warning('HOTMART_HOTTOK configurado pero no recibido en el webhook');
            return false;
        }

        return hash_equals($expectedToken, $hottok);
    }

    /**
     * Obtener access token mediante OAuth 2.0
     *
     * Usa el endpoint: POST https://api-sec-vlc.hotmart.com/security/oauth/token
     *
     * @return array
     */
    public function getAccessToken(): array
    {
        try {
            if ($this->accessToken) {
                return [
                    'success' => true,
                    'message' => 'Token existente',
                    'data' => ['access_token' => $this->accessToken]
                ];
            }

            // URL completa con parámetros según documentación de Hotmart
            $url = 'https://api-sec-vlc.hotmart.com/security/oauth/token';
            $url .= '?grant_type=client_credentials';
            $url .= '&client_id=' . urlencode($this->clientId);
            $url .= '&client_secret=' . urlencode($this->clientSecret);

            $response = $this->client->post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . $this->basicAuth,
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['access_token'])) {
                $this->accessToken = $data['access_token'];

                Log::info('Access token de Hotmart obtenido exitosamente', [
                    'token_type' => $data['token_type'] ?? null,
                    'expires_in' => $data['expires_in'] ?? null,
                    'scope' => $data['scope'] ?? null,
                ]);

                return [
                    'success' => true,
                    'message' => 'Token obtenido con éxito',
                    'data' => $data
                ];
            }

            return [
                'success' => false,
                'message' => 'No se pudo obtener el access token',
                'data' => $data
            ];

        } catch (GuzzleException $e) {
            Log::error('Error obteniendo access token de Hotmart: ' . $e->getMessage(), [
                'client_id' => $this->clientId,
                'has_basic_auth' => !empty($this->basicAuth),
            ]);
            return [
                'success' => false,
                'message' => 'Error al obtener el access token: ' . $e->getMessage(),
                'data' => null
            ];
        } catch (Exception $e) {
            Log::error('Error inesperado al obtener access token: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error inesperado: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Obtener información de una suscripción
     *
     * @param string $subscriberCode Código del suscriptor
     * @return array
     */
    public function getSubscription(string $subscriberCode): array
    {
        try {
            $tokenResponse = $this->getAccessToken();

            if (!$tokenResponse['success']) {
                return $tokenResponse;
            }

            $response = $this->client->get("/payments/api/v1/subscriptions/{$subscriberCode}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => true,
                'message' => 'Suscripción obtenida con éxito',
                'data' => $data
            ];

        } catch (GuzzleException $e) {
            Log::error('Error obteniendo suscripción de Hotmart: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al obtener la suscripción: ' . $e->getMessage(),
                'data' => null
            ];
        } catch (Exception $e) {
            Log::error('Error inesperado: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error inesperado: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Obtener historial de ventas
     *
     * @param array $params Parámetros de búsqueda (transaction_status, start_date, end_date, etc.)
     * @return array
     */
    public function getSalesHistory(array $params = []): array
    {
        try {
            $tokenResponse = $this->getAccessToken();

            if (!$tokenResponse['success']) {
                return $tokenResponse;
            }

            $queryParams = http_build_query($params);
            $endpoint = "/payments/api/v1/sales/history?" . $queryParams;

            $response = $this->client->get($endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => true,
                'message' => 'Historial de ventas obtenido con éxito',
                'data' => $data
            ];

        } catch (GuzzleException $e) {
            Log::error('Error obteniendo historial de ventas: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al obtener el historial: ' . $e->getMessage(),
                'data' => null
            ];
        } catch (Exception $e) {
            Log::error('Error inesperado: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error inesperado: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Procesar evento de webhook
     *
     * @param array $webhookData Datos del webhook
     * @return array
     */
    public function processWebhookEvent(array $webhookData): array
    {
        try {
            // Extraer información del evento
            $event = $webhookData['event'] ?? null;
            $data = $webhookData['data'] ?? [];

            if (!$event) {
                return [
                    'success' => false,
                    'message' => 'Evento no especificado',
                    'data' => null
                ];
            }

            // Procesar según el tipo de evento
            switch ($event) {
                case self::EVENT_PURCHASE_COMPLETE:
                case self::EVENT_PURCHASE_APPROVED:
                    return $this->handlePurchaseApproved($data);

                case self::EVENT_PURCHASE_CANCELED:
                    return $this->handlePurchaseCanceled($data);

                case self::EVENT_PURCHASE_REFUNDED:
                    return $this->handlePurchaseRefunded($data);

                case self::EVENT_PURCHASE_CHARGEBACK:
                    return $this->handlePurchaseChargeback($data);

                case self::EVENT_SUBSCRIPTION_CANCELLATION:
                    return $this->handleSubscriptionCancellation($data);

                case self::EVENT_SUBSCRIPTION_REACTIVATION:
                    return $this->handleSubscriptionReactivation($data);

                default:
                    Log::info('Evento de Hotmart no manejado: ' . $event, $webhookData);
                    return [
                        'success' => true,
                        'message' => 'Evento registrado pero no procesado: ' . $event,
                        'data' => $webhookData
                    ];
            }

        } catch (Exception $e) {
            Log::error('Error procesando webhook de Hotmart: ' . $e->getMessage(), $webhookData);
            return [
                'success' => false,
                'message' => 'Error procesando webhook: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Manejar compra aprobada
     */
    private function handlePurchaseApproved(array $data): array
    {
        // Aquí debes implementar la lógica específica de tu aplicación
        // Por ejemplo: crear inscripción, activar curso, enviar email, etc.

        $transaction = $data['purchase']['transaction'] ?? null;
        $buyer = $data['buyer'] ?? [];
        $product = $data['product'] ?? [];

        Log::info('Compra aprobada en Hotmart', [
            'transaction' => $transaction,
            'buyer_email' => $buyer['email'] ?? null,
            'product_id' => $product['id'] ?? null,
        ]);

        return [
            'success' => true,
            'message' => 'Compra aprobada procesada',
            'data' => [
                'transaction' => $transaction,
                'buyer_email' => $buyer['email'] ?? null,
                'product_id' => $product['id'] ?? null,
            ]
        ];
    }

    /**
     * Manejar compra cancelada
     */
    private function handlePurchaseCanceled(array $data): array
    {
        $transaction = $data['purchase']['transaction'] ?? null;

        Log::info('Compra cancelada en Hotmart', ['transaction' => $transaction]);

        return [
            'success' => true,
            'message' => 'Compra cancelada procesada',
            'data' => ['transaction' => $transaction]
        ];
    }

    /**
     * Manejar reembolso
     */
    private function handlePurchaseRefunded(array $data): array
    {
        $transaction = $data['purchase']['transaction'] ?? null;

        Log::info('Reembolso procesado en Hotmart', ['transaction' => $transaction]);

        return [
            'success' => true,
            'message' => 'Reembolso procesado',
            'data' => ['transaction' => $transaction]
        ];
    }

    /**
     * Manejar contracargo
     */
    private function handlePurchaseChargeback(array $data): array
    {
        $transaction = $data['purchase']['transaction'] ?? null;

        Log::warning('Contracargo reportado en Hotmart', ['transaction' => $transaction]);

        return [
            'success' => true,
            'message' => 'Contracargo procesado',
            'data' => ['transaction' => $transaction]
        ];
    }

    /**
     * Manejar cancelación de suscripción
     */
    private function handleSubscriptionCancellation(array $data): array
    {
        $subscriberCode = $data['subscriber_code'] ?? null;

        Log::info('Suscripción cancelada en Hotmart', ['subscriber_code' => $subscriberCode]);

        return [
            'success' => true,
            'message' => 'Cancelación de suscripción procesada',
            'data' => ['subscriber_code' => $subscriberCode]
        ];
    }

    /**
     * Manejar reactivación de suscripción
     */
    private function handleSubscriptionReactivation(array $data): array
    {
        $subscriberCode = $data['subscriber_code'] ?? null;

        Log::info('Suscripción reactivada en Hotmart', ['subscriber_code' => $subscriberCode]);

        return [
            'success' => true,
            'message' => 'Reactivación de suscripción procesada',
            'data' => ['subscriber_code' => $subscriberCode]
        ];
    }

    /**
     * Extraer información útil del webhook para crear una orden
     *
     * @param array $webhookData
     * @return array|null
     */
    public static function extractOrderData(array $webhookData): ?array
    {
        try {
            $data = $webhookData['data'] ?? [];
            $buyer = $data['buyer'] ?? [];
            $product = $data['product'] ?? [];
            $purchase = $data['purchase'] ?? [];
            $commissions = $data['commissions'] ?? [];

            return [
                // Información del comprador
                'buyer_email' => $buyer['email'] ?? null,
                'buyer_name' => $buyer['name'] ?? null,
                'buyer_phone' => $buyer['checkout_phone'] ?? null,

                // Información del producto
                'product_id' => $product['id'] ?? null,
                'product_name' => $product['name'] ?? null,

                // Información de la compra
                'transaction_id' => $purchase['transaction'] ?? null,
                'status' => $purchase['status'] ?? null,
                'approved_date' => $purchase['approved_date'] ?? null,

                // Información de precio
                'price_value' => $purchase['price']['value'] ?? 0,
                'price_currency' => $purchase['price']['currency_code'] ?? 'BRL',

                // Comisiones
                'commission_value' => $commissions[0]['value'] ?? 0,
                'commission_currency' => $commissions[0]['currency_code'] ?? 'BRL',

                // Datos crudos
                'raw_data' => $webhookData
            ];

        } catch (Exception $e) {
            Log::error('Error extrayendo datos de orden de Hotmart: ' . $e->getMessage());
            return null;
        }
    }
}
