<?php

declare(strict_types=1);


namespace App\Support\Email;


use Illuminate\Http\JsonResponse;
use PHPMailer\PHPMailer\PHPMailer;

class Emailing
{
    /**
     * Request password reset email.    
     */
    public static function resetPassword($user): bool|JsonResponse
    {
        $mail = new PHPMailer(true);

        try {
            self::SMTPServerSettings($mail);

            self::recipientSettings($mail, $user);

            self::accountMailerBcc($mail);

            self::resetPw($mail,$user->password_reset_token);

            self::sendEmail($mail);

            return true;
        } catch (\Exception $e) {
            return response()->json(
                data  : $e->getMessage(),
                status: $e->getCode() <= 0 ? 500 : $e->getCode()
            );
        }
    }

      /**
     * Verify email.    
     */
    public static function verifyEmail($user): bool|JsonResponse
    {
        $mail = new PHPMailer(true);

        try {
            self::SMTPServerSettings($mail);

            self::recipientSettings($mail, $user);

            self::accountMailerBcc($mail);

            self::verify($mail,$user->verification_token);

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
        $mail->SMTPSecure = 'ssl'; 
        $mail->Port     = env('ACCOUNT_MAILER_PORT');
        $mail->Username = env('ACCOUNT_MAILER_USERNAME');
        $mail->Password = env('ACCOUNT_MAILER_PASSWORD');
        $mail->CharSet  = 'UTF-8';
    }

    protected static function recipientSettings(PHPMailer $mail, $user): void
    {
        $mail->setFrom(env('ACCOUNT_MAILER_USERNAME'), 'Sinapsis Vet');
        $mail->addAddress($user->email, $user->name);
    }

    protected static function accountMailerBcc(PHPMailer $mail): void
    {
        if (!empty(env('ACCOUNT_MAILER_BCC'))) {
            $mail->addBCC(env('ACCOUNT_MAILER_BCC'));
        }
    }

    protected static function resetPw(PHPMailer $mail,$token): void
    {
        $mail->isHTML(true);
        $mail->Subject = 'Recupero de contraseña';
        $mail->Body    = '<!DOCTYPE html>
                            <html lang="es">
                            <head>
                            <meta charset="UTF-8">
                            <meta name="viewport" content="width=device-width, initial-scale=1.0">
                            <title>Recuperación de Contraseña</title>
                            <style>
                                body {
                                font-family: Arial, sans-serif;
                                background-color: #f4f4f4;
                                margin: 0;
                                padding: 0;
                                display: flex;
                                justify-content: center;
                                align-items: center;
                                height: 100vh;
                                }
                                .container {
                                max-width: 600px;
                                margin: 20px;
                                padding: 20px;
                                background-color: #ffffff;
                                border-radius: 8px;
                                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                                text-align: center;
                                }
                                .logo {
                                width: 100px;
                                margin-bottom: 20px;
                                }
                                h1 {
                                color: #333333;
                                }
                                p {
                                color: #555555;
                                line-height: 1.6;
                                font-size: 16px;
                                }
                                .button {
                                display: inline-block;
                                padding: 12px 24px;
                                margin-top: 20px;
                                font-size: 16px;
                                background-color: #3E0F53;
                                text-decoration: none;
                                border-radius: 4px;
                                color: #ffffff !important;
                                }
                                .button:hover {
                                background-color: #3E0F53;
                                }
                                
                            </style>
                            </head>
                            <body>
                            <div class="container">
                                <img src="https://sinapsisvet.com/sinapsisvet-logo.jpg" alt="Logo" class="logo">
                                <h1>Recuperación de Contraseña</h1>
                                <p>Has solicitado restablecer tu contraseña. Para continuar con el proceso, por favor haz clic en el siguiente botón:</p>
                                <a href="'.env('RESET_PW_URL').$token.'" class="button">Restablecer Contraseña</a>
                                <p>Si el botón no funciona, puedes copiar el link y pegarlo en el navegador</p>
                                <href style="font-size:12px" >'.env('RESET_PW_URL').$token.'</href>
                            </div>
                            </body>
                            </html>
                            ';
   
    }
  
  
    protected static function verify(PHPMailer $mail,$token): void
    {
        $mail->isHTML(true);
        $mail->Subject = 'Verifica tu email';
        $mail->Body    = ' <!DOCTYPE html>
            <html lang="es">
            <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Activación de Cuenta</title>
            <style>
                body {
                font-family: Arial, sans-serif;
                background-color: #f4f4f4;
                margin: 0;
                padding: 0;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                }
                .container {
                max-width: 600px;
                margin: 20px;
                padding: 20px;
                background-color: #ffffff;
                border-radius: 8px;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                text-align: center;
                }
                .logo {
                width: 100px;
                margin-bottom: 20px;
                }
                h1 {
                color: #333333;
                }
                p {
                color: #555555;
                line-height: 1.6;
                font-size: 16px;
                }
                .button {
                display: inline-block;
                padding: 12px 24px;
                margin-top: 20px;
                font-size: 16px;
                color: #ffffff !important;
                background-color: #3E0F53;
                text-decoration: none;
                border-radius: 4px;
                }
            </style>
            </head>
            <body>
            <div class="container">
                <img src="https://sinapsisvet.com/sinapsisvet-logo.jpg" alt="Logo" class="logo">
                <h1>Activación de Cuenta</h1>
                <p>Gracias por registrarte en nuestro servicio. Para activar tu cuenta, por favor haz clic en el siguiente botón:</p>
                <a href="'.env('VERIFY_PW_URL').$token.'" class="button">Activar Cuenta</a>
                <p>Si el botón no funciona, puedes copiar el link y pegarlo en el navegador</p>
                <href style="font-size:12px" >'.env('VERIFY_PW_URL').$token.'</href>
            </div>
            </body>
            </html>';
    }

    protected static function sendEmail(PHPMailer $mail): void
    {
        if (!$mail->send()) {
            throw new \Exception("Error al enviar el correo electrónico: {$mail->ErrorInfo}", 500);
        }
    }
}
