<?php

declare(strict_types=1);

namespace App\Http\Controllers\MercadoPago;

use App\Http\Controllers\Controller;
use MercadoPago\MercadoPagoConfig;

class MercadoPago extends Controller
{

    protected $notification_url;

    public function __construct()
    {

        MercadoPagoConfig::setAccessToken($_ENV['MERCADOPAGO_ACCESS_TOKEN']);
        
        # Indica la URL desde la que se recibirán las notificaciones.
        $this->notification_url = env('APP_URL') . "/api/mercadopago/webhook";
    }


    /**
     * Default collection creation messages
     *
     * @param Payment $payment.
     * @return void
     */
    protected function _convertMessageResponse($payment)
    {
        $status = $payment->status_detail;

        $message = [
            "accredited"                            => "¡Listo! Se acreditó tu pago.",
            "pending_contingency"                   => "Estamos procesando tu pago.",
            "pending_review_manual"                 => "No te preocupes, menos de 2 días hábiles te avisaremos por e-mail si se acreditó o si necesitamos más información.",
            "cc_rejected_bad_filled_card_number"    => "Revisa el número de tarjeta.",
            "cc_rejected_bad_filled_date"           => "Revisa la fecha de vencimiento.",
            "cc_rejected_bad_filled_other"          => "Revisa los datos.",
            "cc_rejected_bad_filled_security_code"  => "Revisa el código de seguridad de la tarjeta.",
            "cc_rejected_blacklist"                 => "No pudimos procesar tu pago.",
            "cc_rejected_call_for_authorize"        => "Debes autorizar ante $payment->payment_method_id el pago de $ $payment->transaction_amount.",
            "cc_rejected_card_disabled"             => "Llama a $payment->payment_method_id para activar tu tarjeta o usa otro medio de pago.",
            "cc_rejected_card_error"                => "No pudimos procesar tu pago.",
            "cc_rejected_duplicated_payment"        => "Si necesitas volver a pagar usa otra tarjeta u otro medio de pago.",
            "cc_rejected_high_risk"                 => "Tu pago fue rechazado.",
            "cc_rejected_insufficient_amount"       => "Tu $payment->payment_method_id no tiene fondos suficientes.",
            "cc_rejected_invalid_installments"      => "ment_method_id no procesa pagos en installments cuotas.",
            "cc_rejected_max_attempts"              => "Llegaste al límite de intentos permitidos.",
            "cc_rejected_other_reason"              => "$payment->payment_method_id no procesó el pago.",
        ];

        return $message[$status];
    }


}
