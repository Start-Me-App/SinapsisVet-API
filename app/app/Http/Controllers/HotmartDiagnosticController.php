<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Support\Hotmart;
use GuzzleHttp\Client;
use Exception;

class HotmartDiagnosticController extends Controller
{
    /**
     * Diagnóstico completo de la configuración de Hotmart
     *
     * @return JsonResponse
     */
    public function diagnose(): JsonResponse
    {
        $diagnostics = [
            'timestamp' => now()->toDateTimeString(),
            'environment_variables' => [],
            'configuration' => [],
            'connectivity' => [],
            'authentication' => [],
            'recommendations' => []
        ];

        // 1. Verificar variables de entorno
        $diagnostics['environment_variables'] = [
            'HOTMART_CLIENT_ID' => [
                'configured' => !empty(env('HOTMART_CLIENT_ID')),
                'value' => env('HOTMART_CLIENT_ID') ? substr(env('HOTMART_CLIENT_ID'), 0, 10) . '...' : null,
                'length' => env('HOTMART_CLIENT_ID') ? strlen(env('HOTMART_CLIENT_ID')) : 0
            ],
            'HOTMART_CLIENT_SECRET' => [
                'configured' => !empty(env('HOTMART_CLIENT_SECRET')),
                'value' => env('HOTMART_CLIENT_SECRET') ? substr(env('HOTMART_CLIENT_SECRET'), 0, 10) . '...' : null,
                'length' => env('HOTMART_CLIENT_SECRET') ? strlen(env('HOTMART_CLIENT_SECRET')) : 0
            ],
            'HOTMART_BASIC_AUTH' => [
                'configured' => !empty(env('HOTMART_BASIC_AUTH')),
                'value' => env('HOTMART_BASIC_AUTH') ? substr(env('HOTMART_BASIC_AUTH'), 0, 10) . '...' : null,
                'length' => env('HOTMART_BASIC_AUTH') ? strlen(env('HOTMART_BASIC_AUTH')) : 0
            ],
            'HOTMART_API_URL' => [
                'configured' => !empty(env('HOTMART_API_URL')),
                'value' => env('HOTMART_API_URL')
            ],
            'HOTMART_WEBHOOK_PATH' => [
                'configured' => !empty(env('HOTMART_WEBHOOK_PATH')),
                'value' => env('HOTMART_WEBHOOK_PATH')
            ]
        ];

        // 2. Verificar configuración básica
        $diagnostics['configuration']['webhook_url'] = Hotmart::getWebhookUrl();

        // 3. Verificar conectividad con Hotmart
        try {
            $client = new Client([
                'timeout' => 10,
                'connect_timeout' => 5
            ]);

            $response = $client->get('https://developers.hotmart.com', [
                'http_errors' => false
            ]);

            $diagnostics['connectivity'] = [
                'can_reach_hotmart' => true,
                'status_code' => $response->getStatusCode(),
                'message' => 'Conexión exitosa con Hotmart'
            ];
        } catch (Exception $e) {
            $diagnostics['connectivity'] = [
                'can_reach_hotmart' => false,
                'error' => $e->getMessage(),
                'message' => 'No se puede conectar con Hotmart'
            ];
        }

        // 4. Intentar autenticación
        if (env('HOTMART_CLIENT_ID') && env('HOTMART_CLIENT_SECRET') && env('HOTMART_BASIC_AUTH')) {
            try {
                $client = new Client([
                    'base_uri' => env('HOTMART_API_URL', 'https://developers.hotmart.com'),
                    'timeout' => 15
                ]);

                $response = $client->post('/security/oauth/token', [
                    'headers' => [
                        'Authorization' => 'Basic ' . env('HOTMART_BASIC_AUTH'),
                        'Content-Type' => 'application/x-www-form-urlencoded'
                    ],
                    'form_params' => [
                        'grant_type' => 'client_credentials',
                        'client_id' => env('HOTMART_CLIENT_ID'),
                        'client_secret' => env('HOTMART_CLIENT_SECRET')
                    ],
                    'http_errors' => false
                ]);

                $statusCode = $response->getStatusCode();
                $body = json_decode($response->getBody()->getContents(), true);

                if ($statusCode === 200 && isset($body['access_token'])) {
                    $diagnostics['authentication'] = [
                        'success' => true,
                        'status_code' => $statusCode,
                        'has_access_token' => true,
                        'token_type' => $body['token_type'] ?? null,
                        'expires_in' => $body['expires_in'] ?? null,
                        'message' => '✅ Autenticación exitosa'
                    ];
                } else {
                    $diagnostics['authentication'] = [
                        'success' => false,
                        'status_code' => $statusCode,
                        'error' => $body['error'] ?? 'Unknown error',
                        'error_description' => $body['error_description'] ?? 'No description provided',
                        'response' => $body,
                        'message' => '❌ Autenticación fallida'
                    ];
                }
            } catch (Exception $e) {
                $diagnostics['authentication'] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'message' => '❌ Error al intentar autenticar: ' . $e->getMessage()
                ];
            }
        } else {
            $diagnostics['authentication'] = [
                'success' => false,
                'message' => '⚠️ Credenciales incompletas'
            ];
        }

        // 5. Recomendaciones
        if (!$diagnostics['environment_variables']['HOTMART_CLIENT_ID']['configured']) {
            $diagnostics['recommendations'][] = 'Configura HOTMART_CLIENT_ID en tu archivo .env';
        }
        if (!$diagnostics['environment_variables']['HOTMART_CLIENT_SECRET']['configured']) {
            $diagnostics['recommendations'][] = 'Configura HOTMART_CLIENT_SECRET en tu archivo .env';
        }
        if (!$diagnostics['environment_variables']['HOTMART_BASIC_AUTH']['configured']) {
            $diagnostics['recommendations'][] = 'Configura HOTMART_BASIC_AUTH en tu archivo .env';
        }
        if (!$diagnostics['connectivity']['can_reach_hotmart']) {
            $diagnostics['recommendations'][] = 'Verifica tu conexión a internet o firewall';
        }
        if (!$diagnostics['authentication']['success']) {
            $diagnostics['recommendations'][] = 'Verifica que las credenciales sean correctas en el panel de Hotmart';
            $diagnostics['recommendations'][] = 'El BASIC_AUTH debe ser base64(client_id:client_secret)';
        }

        // Determinar resultado general
        $allGood = $diagnostics['environment_variables']['HOTMART_CLIENT_ID']['configured']
            && $diagnostics['environment_variables']['HOTMART_CLIENT_SECRET']['configured']
            && $diagnostics['environment_variables']['HOTMART_BASIC_AUTH']['configured']
            && $diagnostics['connectivity']['can_reach_hotmart']
            && $diagnostics['authentication']['success'];

        return response()->json([
            'overall_status' => $allGood ? 'OK' : 'ERROR',
            'message' => $allGood
                ? '✅ Hotmart está configurado correctamente'
                : '❌ Hay problemas con la configuración de Hotmart',
            'diagnostics' => $diagnostics
        ], $allGood ? 200 : 500);
    }

    /**
     * Generar BASIC_AUTH desde CLIENT_ID y CLIENT_SECRET
     *
     * @return JsonResponse
     */
    public function generateBasicAuth(): JsonResponse
    {
        $clientId = env('HOTMART_CLIENT_ID');
        $clientSecret = env('HOTMART_CLIENT_SECRET');

        if (!$clientId || !$clientSecret) {
            return response()->json([
                'success' => false,
                'message' => 'HOTMART_CLIENT_ID y HOTMART_CLIENT_SECRET son requeridos'
            ], 400);
        }

        $basicAuth = base64_encode($clientId . ':' . $clientSecret);

        return response()->json([
            'success' => true,
            'message' => 'BASIC_AUTH generado correctamente',
            'data' => [
                'client_id' => substr($clientId, 0, 10) . '...',
                'client_secret' => substr($clientSecret, 0, 10) . '...',
                'basic_auth' => $basicAuth,
                'instruction' => 'Agrega esta línea a tu .env: HOTMART_BASIC_AUTH=' . $basicAuth
            ]
        ], 200);
    }
}
