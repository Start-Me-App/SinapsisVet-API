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
        $mail->Body    = '<p> Ingresa a este link: '.env('RESET_PW_URL').$token.'</p>';
    }
  
  
    protected static function verify(PHPMailer $mail,$token): void
    {
        $mail->isHTML(true);
        $mail->Subject = 'Verifica tu email';
        $mail->Body    = '<p> Ingresa a este link: '.env('VERIFY_PW_URL').$token.'</p>';
    }

    protected static function sendEmail(PHPMailer $mail): void
    {
        if (!$mail->send()) {
            throw new \Exception("Error al enviar el correo electrónico: {$mail->ErrorInfo}", 500);
        }
    }
}
