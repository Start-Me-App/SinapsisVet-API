<?php

declare(strict_types=1);


namespace App\Support\Email;


use Illuminate\Http\JsonResponse;
use PHPMailer\PHPMailer\PHPMailer;

class OrdenDeCompraEmail
{
    /**
     * Request password reset email.    
     */
    public static function sendOrderEmail($order): bool|JsonResponse
    {
        $mail = new PHPMailer(true);

        try {
            self::SMTPServerSettings($mail);

            self::recipientSettings($mail, env('SINAPSIS_VET_EMAIL'));

            self::accountMailerBcc($mail);

            self::sendOrder($mail,$order);

            self::sendEmail($mail);

            return true;
        } catch (\Exception $e) {
            return response()->json(
                data  : $e->getMessage(),
                status: $e->getCode() <= 0 ? 500 : $e->getCode()
            );
        }
    }

     
    protected static function SMTPServerSettings(PHPMailer $mail): void
    {
        $mail->isSMTP();
        $mail->Host     = env('ACCOUNT_MAILER_HOST');
        $mail->SMTPAuth = true;
        $mail->SMTPDebug = 0; 
        $mail->Port     = env('ACCOUNT_MAILER_PORT');
        $mail->Username = env('ACCOUNT_MAILER_USERNAME');
        $mail->Password = env('ACCOUNT_MAILER_PASSWORD');
        $mail->CharSet  = 'UTF-8';
    }

    protected static function recipientSettings(PHPMailer $mail, $user): void
    {
        $mail->setFrom(env('ACCOUNT_MAILER_USERNAME'), 'Sinapsis Vet');
        $mail->addAddress($user, 'Sinapsis Vet');
    }

    protected static function accountMailerBcc(PHPMailer $mail): void
    {
        if (!empty(env('ACCOUNT_MAILER_BCC_SINAPSIS_VET'))) {
            $mail->addBCC(env('ACCOUNT_MAILER_BCC_SINAPSIS_VET'));
        }
    }

    protected static function sendOrder(PHPMailer $mail, $order): void
    {
        try {
            $mail->isHTML(true);
            $mail->Subject = 'Nueva orden de compra';

            // Construir el cuerpo del email
            $emailBody = '
                <!DOCTYPE html>
                <html lang="es">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Nueva Orden de Compra</title>
                    <style>
                       body {
                            font-family: Arial, sans-serif;
                            background-color: #f4f4f4;
                            margin: 0;
                            padding: 20px;
                            color: #333;
                        }
                        .container {
                            margin: 0 auto;
                            background-color: #ffffff;
                            padding: 40px;
                            border-radius: 8px;
                        }
                        .header {
                            text-align: center;
                            margin-bottom: 40px;
                        }
                        .logo {
                            width: 200px;
                            margin-bottom: 20px;
                        }
                        h1 {
                            color: #3E0F53;
                            font-size: 28px;
                            margin: 0;
                            font-weight: normal;
                        }
                        .content-grid {
                            width: 100%;
                            margin-bottom: 40px;
                        } 
                        .section {
                            width: 32%;
                            background-color: #f8f9fa;
                            border-radius: 8px;
                            padding: 10px;
                            display: inline-block;
                            vertical-align: top;
                            box-sizing: border-box;
                        }
                        .section-title {
                            color: #3E0F53;
                            font-size: 18px;
                            margin-bottom: 20px;
                            font-weight: bold;
                        }
                        .info-item {
                            margin-bottom: 15px;
                        }
                        .info-label {
                            color: #666;
                            font-size: 14px;
                            margin-bottom: 5px;
                        }
                        .info-value {
                            color: #333;
                            font-size: 15px;
                        }
                        .details-section {
                            margin-top: 20px;
                            text-align: center;
                            max-width: 600px;
                            margin-left: auto;
                            margin-right: auto;
                        }
                        .details-title {
                            color: #3E0F53;
                            font-size: 20px;
                            margin-bottom: 20px;
                            font-weight: bold;
                        }
                        ul {
                            list-style: none;
                            padding: 0;
                            margin: 0;
                        }
                        li {
                            background-color: #f8f9fa;
                            padding: 15px 20px;
                            margin-bottom: 10px;
                            border-radius: 8px;
                        }
                        .highlight {
                            background-color: #3E0F53;
                            color: white;
                            padding: 3px 8px;
                            border-radius: 4px;
                            display: inline-block;
                        }
                        .curso-label {
                            font-weight: bold;
                            font-size: 20px;
                        }
                        .curso-value {
                            font-size: 14px;
                        }
                        
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <img src="https://api.sinapsisvet.com/sinapsisvet-logo.jpg" alt="Logo" class="logo">
                            <h1>Nueva orden de compra</h1>
                        </div>

                        <div class="content-grid">
                            <div class="section">
                                <div class="section-title">Información de la orden</div>
                                <div class="info-item">
                                    <div class="info-label">Número de orden</div>
                                    <div class="info-value">' . $order->id . '</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Fecha de compra</div>
                                    <div class="info-value">' . $order->date_created . '</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Estado</div>
                                    <div class="info-value">' . $order->status . '</div>
                                </div>
                            </div>

                            <div class="section">
                                <div class="section-title">Totales y descuentos</div>
                                ' . ($order->total_amount_ars != null ? '
                                <div class="info-item">
                                    <div class="info-label">Total ARS</div>
                                    <div class="info-value"><span class="highlight">$' . $order->total_amount_ars . '</span></div>
                                </div>' : '') . '
                                ' . ($order->total_amount_usd != null ? '
                                <div class="info-item">
                                    <div class="info-label">Total USD</div>
                                    <div class="info-value"><span class="highlight">$' . $order->total_amount_usd . '</span></div>
                                </div>' : '') . '
                                ' . ($order->discount_percentage != null ? '
                                <div class="info-item">
                                    <div class="info-label">Descuento</div>
                                    <div class="info-value"><span class="highlight">' . $order->discount_percentage . '%</span></div>
                                </div>' : '') . '
                            </div>

                            <div class="section">
                                <div class="section-title">Información del usuario</div>
                                <div class="info-item">
                                    <div class="info-label">Nombre completo</div>
                                    <div class="info-value">' . $order->user->name . ' ' . $order->user->last_name . '</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Email</div>
                                    <div class="info-value">' . $order->user->email . '</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Teléfono</div>
                                    <div class="info-value">' . $order->user->full_phone . '</div>
                                </div>
                            </div>
                        </div>

                        <div class="details-section">
                            <div class="details-title">Detalles de los cursos</div>
                            <ul>
                                ' . implode('', array_map(function($detail) {
                                    return '<li>
                                        <div class="curso-label">' . $detail['course']['title'] . '</div>
                                        <div class="curso-value">
                                            Con taller: ' . ($detail['with_workshop'] ? 'Sí' : 'No') . ' | 
                                            Precio: <span class="highlight">$' . $detail['price'] . '</span>
                                        </div>
                                    </li>';
                                }, $order->orderDetails->toArray())) . '
                            </ul>
                        </div>
                    </div>
                </body>
                </html>';

            $mail->Body = $emailBody;

        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), 500);
        }
    }


    protected static function sendEmail(PHPMailer $mail): void
    {
        if (!$mail->send()) {
            throw new \Exception("Error al enviar el correo electrónico: {$mail->ErrorInfo}", 500);
        }
    }
}
