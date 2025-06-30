<?php

declare(strict_types=1);

namespace App\Http\Controllers\MercadoPago;


use App\Models\NotificationMercadoPago;
use App\Models\ResponseMercadoPago;
use App\Helper\JsonRequest;
use Illuminate\Support\Facades\Log;

use Psr\Http\Message\{
    ResponseInterface as Response,
    ServerRequestInterface as Request
};
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\Client\Common\RequestOptions;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Inscriptions;
use App\Support\Email\OrdenDeCompraEmail;
use App\Models\Movements;
use App\Models\Courses;

final class WebHook extends MercadoPago
{
    /**
     * Notification from MercadoPago [webhooks]
     *
     * @param Request  $request
     * @param Response $response
     * @return void
     */
    public function notification(Request $request)
    {
        try {
            
            self::_processWebhook($request);

            return response()->json(['message' => 'Webhook procesado correctamente'], 200);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Process the webhook response
     *
     * @param Request $request
     * @return void
     */
    public function _processWebhook(Request $request)
    {

        $webhook = self::_getAttributeWebHook($request);
        Log::info(json_encode($webhook));

        $notification =  new NotificationMercadoPago();
        $notification->id_webhook          = (int)$webhook['id'];
        $notification->data_id             = (int)$webhook['data']['id'];
        $notification->live_mode           = $webhook['live_mode'];
        $notification->type                = $webhook['type'];
        $notification->date_created        = $webhook['date_created'];
        $notification->user_id_mercadopago = $webhook['user_id'];
        $notification->api_version         = $webhook['api_version'];
        $notification->action              = $webhook['action'];

        # Notification received from MercadoPago after payment process is completed.
       try{
        $client = new PaymentClient();
        $payment = $client->get($notification->data_id, new RequestOptions(
            $_ENV['MERCADOPAGO_ACCESS_TOKEN']
        ));
     
       } catch (MPApiException $error) {
       throw new \Exception($error->getMessage(),500);
       }

       if(!$payment){
        throw new \Exception('Payment not found',500);
       }
        $paymenStateById = (object)[
            "data_id"          => $payment->id,
            "status"           => $payment->status,
            "status_detail"    => $payment->status_detail,
            "date_approved"    => $payment->date_approved,
            "payment_method_id"=> $payment->payment_method_id,
            "payment_type_id"  => $payment->payment_type_id,
            "order_id"         => $payment->external_reference
        ];

        $notification->save();  
        $preference = ResponseMercadoPago::where('order_id', $payment->external_reference)->first();

        if($preference){
            $preference->status = $payment->status;
            $preference->status_detail = $payment->status_detail;
            if($payment->status == 'approved'){
                $preference->approved_at = date('Y-m-d H:i:s', strtotime($payment->date_approved));
            }
            $preference->save();
        }

        if($payment->status == 'approved'){
            $order = Order::where('id', $payment->external_reference)->first();
            $order->status = 'paid';
            $order->date_paid = date('Y-m-d H:i:s');
            $order->date_closed = date('Y-m-d H:i:s');
            $order->save();

            $orderDetail = OrderDetail::where('order_id', $order->id)->get();


            $order_email = Order::with(['orderDetails.course'])->find($order->id);
            OrdenDeCompraEmail::sendOrderEmail($order_email);

            foreach($orderDetail as $item){

                #check if the inscription already exists
                $inscripcion = Inscriptions::where('user_id', $order->user_id)->where('course_id', $item->course_id)->first();
                if(!$inscripcion){
                $inscripcion = new Inscriptions();
                $inscripcion->user_id = $order->user_id;
                $inscripcion->course_id = $item->course_id;
                $inscripcion->with_workshop = $item->with_workshop;
                $inscripcion->save();
                }else{
                    if($inscripcion->with_workshop == 0 && $item->with_workshop == 1){
                        $inscripcion->with_workshop = 1;
                        $inscripcion->save();
                    }
                }

            }

              // Crear movimientos para cada item del pedido
              foreach($orderDetail as $item){
                $course = Courses::find($item->course_id);
                $movement = new Movements();
                $movement->amount = $item->price;
                $movement->amount_neto = $item->price; // Averiguar comision
                $movement->currency = 2; // Pesos argentinos para mercado pago
                $movement->description = 'Pago por mercado pago - Orden #'.$order->id.' - Curso: '.$course->title;
                $movement->course_id = $item->course_id;
                $movement->period = date('m-Y');
                $movement->account_id = 1; // guardar en account_id de mercado pago
                $movement->save();
            }
        }

    }


    /**
     * Contains the payment data to process it
     *
     * @param Request $request
     * @return array
     */
    private function _getAttributeWebHook($request)
    {
        $formData = (array) $request->getParsedBody();
       
        // Validar y formatear los datos
        $data = [
            'id' => (int) ($formData['id'] ?? 0),
            'type' => (string) ($formData['type'] ?? ''),
            'action' => (string) ($formData['action'] ?? ''),
            'user_id' => (int) ($formData['user_id'] ?? 0),
            'live_mode' => (string) ($formData['live_mode'] ?? ''),
            'data' => [
                'id' => (int) ($formData['data']['id'] ?? 0)
            ],
            'api_version' => (string) ($formData['api_version'] ?? ''),
            'date_created' => (int) ($formData['date_created'] ?? 0)
        ];

        // Validar que todos los campos requeridos estén presentes
        if (!$data['id'] || !$data['type'] || !$data['action'] || !$data['user_id'] || !$data['data']['id']) {
            throw new \InvalidArgumentException('Faltan campos requeridos en el webhook',500);
        }

        return $data;
    }
}
