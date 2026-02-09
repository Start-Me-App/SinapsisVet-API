<?php
namespace App\Libs;

use App\Models\MercadoPago\MPPreference;
use App\Models\Users\User;
use Exception;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\MerchantOrder\MerchantOrderClient;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\Net\MPSearchRequest;

//https://github.com/mercadopago/sdk-php

class MercadoPagoUtils {

    //Al crear un pago es posible recibir 3 estados diferentes: Pendiente, Rechazado y Aprobado
    const STATE_APPROVED = 'approved';
    const STATE_PENDING = 'pending';
    const STATE_IN_PROCESS = 'in_process';
    const STATE_REJECTED = 'rejected';

    const SATATE_DETAIL_ACCREDITED = 'accredited';

    private static $instance = null;
    public static function getInstance(){
        if(self::$instance == null){
            self::$instance = new MercadoPagoUtils();
        }
        return self::$instance;
    }

    public function __construct()
    {
        MercadoPagoConfig::setAccessToken(env("MP_ACCESS_TOKEN"));

        //TESTUSER1656989451
        /*
        Checkout PRO
        Usuarios de prueba: cattaneo.mn@gmail.com
            Yegoo: TESTUSER1656989451 / LEoh3Z3Z22
            Seller: TESTUSER1702960013 / RrBPWhrBq2

        Pasos:
        1- Crear Preference
            //Devolver el id de la preferencia
            //Devolver el link de pago
        2- Esperar a que el Usuario pague, y llega una notificacion a la url de notificacion con el payment_id
            //Buscar los datos del payment
            //Validar estado y dastos del payment para actualizar las entidades
        */
    }

    public static function getWebhookUrl() : string {
        return env('APP_URL').env('MP_NOTIFICATION_PATH');
    }

    public static function getPreferenceLink(string $preference_id) : string {
        //https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=187195799-82f38879-085f-4337-81c9-74e438742e96
        return "https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=".$preference_id;
    }

    public function createPreference(array $order_data,array $personal_data){        
        try {
            $payer = array(
                "name" => $personal_data['name'],
                "email" => $personal_data['email'],
            );

            $items = [
                [
                    "id" => $order_data['id'],
                    "title" => $order_data['title'],
                    "description" => $order_data['description'],
                    "currency_id" => "ARS",
                    "quantity" => $order_data['quantity'],
                    "unit_price" =>  (float)$order_data['unit_price']
                ]
            ];

            $paymentMethods = [
                "excluded_payment_methods" => [],
                "installments" => 12,
                "default_installments" => 1
            ];
        
            $backUrls = array(
                "success" => env('FRONT_URL')."orders/payment/".$order_data['id']."/success",
                "failure" =>  env('FRONT_URL')."orders/payment/".$order_data['id']."/failure",
                "pending" =>  env('FRONT_URL')."orders/payment/".$order_data['id']."/pending",
            );
        
            $request = [
                "items" => $items,
                "payer" => $payer,
                "payment_methods" => $paymentMethods,
                "back_urls" => $backUrls,
                "statement_descriptor" => "NAME_DISPLAYED_IN_USER_BILLING",
                "external_reference" => $order_data['external_reference'],
                "expires" => false,
                "auto_return" => 'success',

                "notification_url" => self::getWebhookUrl(),
            ];

            $request_options = new RequestOptions();
            $request_options->setCustomHeaders(["X-Idempotency-Key: ".env('MP_IDEMPOTENCY_KEY')]);

            $preference = new PreferenceClient();
            $preference_response = $preference->create($request,$request_options);
                                    
            $response = [
                "id" => $preference_response->id,
                "items" => $preference_response->items,
                "payer" => $preference_response->payer,
                "payment_methods" => $preference_response->payment_methods,
                "statement_descriptor" => $preference_response->statement_descriptor,
                "external_reference" => $preference_response->external_reference,
                "expires" => $preference_response->expires,
                "date_of_expiration" => $preference_response->date_of_expiration,
                "expiration_date_to" => $preference_response->expiration_date_to,
                "operation_type" => $preference_response->operation_type,
                "metadata" => $preference_response->metadata,
                "init_point" => $preference_response->init_point,
                "sandbox_init_point" => $preference_response->sandbox_init_point,                
            ];
                         
            if(isset($response['id']) && $response['id'] != null){                
                return WSResponse::GetResponse(true,'Preferencia de pago creada con exito',[
                    'request' => $request,
                    'response' => $response
                ]);
            }
            return WSResponse::GetResponse(false,'Error al crear la preferencia de pago',$response);
        }catch(Exception $e) {
            report($e);
            return WSResponse::GetResponse(false,'Error al crear la preferencia de pago',$e->getMessage());
        }
    }


    public function getMerchantOrder($order_id){
        try {
            $mo = new MerchantOrderClient();
            $response = $mo->get($order_id);
            return WSResponse::GetResponse(true,'Orden obtenida con exito',$response);   
        } catch (Exception $e) {
            report($e);
            return WSResponse::GetResponse(false,'Error al obtener la orden',$e->getMessage());
        }
    }

    public function getPreference($preference_id){
        try {
            $preference = new PreferenceClient();
            $response = $preference->get($preference_id);
            return WSResponse::GetResponse(true,'Preferencia obtenida con exito',$response);
        } catch (Exception $e) {
            report($e);
            return WSResponse::GetResponse(false,'Error al obtener la preferencia de pago',$e->getMessage());
        }
    }


    public function getPayment($payment_id){
        try {            
            $payment = new PaymentClient();
            $payment = $payment->get($payment_id);
            /*
            $params = new MPSearchRequest(
                1,
                10,
                [
                    'preference_id' => $payment_id
                ]
            );
            
            $payment = $payment->search($params);
            */
            return WSResponse::GetResponse(true,'Pago obtenido con exito',$payment);
        }catch(MPApiException $e){ 
            report($e);              
            return WSResponse::GetResponse(false,$e->getMessage(),$e->getApiResponse()->getStatusCode());
        } catch (Exception $e) {
            report($e);
            return WSResponse::GetResponse(false,'Error al obtener el pago',$e->getMessage());
        }
        /*