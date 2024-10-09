<?php

declare(strict_types=1);


namespace App\Support\Email;


use Illuminate\Http\JsonResponse;
use PHPMailer\PHPMailer\PHPMailer;

class AddInventory
{
    /**
     * Send email to user.
     */
    public static function send($user): bool|JsonResponse
    {
        $mail = new PHPMailer(true);

        try {
            self::SMTPServerSettings($mail);

            self::recipientSettings($mail, $user);

            self::accountMailerBcc($mail);

            self::emailContent($mail);

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
        $mail->Port     = env('ACCOUNT_MAILER_PORT');
        $mail->Username = env('ACCOUNT_MAILER_USERNAME');
        $mail->Password = env('ACCOUNT_MAILER_PASSWORD');
        $mail->CharSet  = 'UTF-8';
    }

    protected static function recipientSettings(PHPMailer $mail, $user): void
    {
        $mail->setFrom(env('ACCOUNT_MAILER_USERNAME'), 'Precios Gamer');
        $mail->addAddress($user->email, $user->destiny);
    }

    protected static function accountMailerBcc(PHPMailer $mail): void
    {
        if (!empty(env('ACCOUNT_MAILER_BCC'))) {
            $mail->addBCC(env('ACCOUNT_MAILER_BCC'));
        }
    }

    protected static function emailContent(PHPMailer $mail): void
    {
        $mail->isHTML(true);
        $mail->Subject = 'Registro de nuevo comercio.';
        $mail->Body    = '<p>Gracias por completar el formulario. Analizaremos su solicitud de
                                 alta en nuestro catálogo para poder agregarlo lo antes posible.
                                 Es posible que alguien de nuestro equipo se ponga en contacto con
                                 usted si se necesitan más detalles. ¡Saludos!</p>';
    }

    protected static function sendEmail(PHPMailer $mail): void
    {
        if (!$mail->send()) {
            throw new \Exception("Error al enviar el correo electrónico: {$mail->ErrorInfo}", 500);
        }
    }
}
