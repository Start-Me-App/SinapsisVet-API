<?php

declare(strict_types=1);

namespace App\Support;

use Firebase\JWT\{
    JWT,
    Key,
    ExpiredException,
    BeforeValidException,
    SignatureInvalidException
};

class TokenManager
{

    public static function makeToken($user): string
    {
        # Hora de expiracion, definida en variable de entorno.
        $timestamp_exp = strtotime(env('JWT_EXPIRATION_TIME', 'now + 3 hours'));
        $timestamp_now = strtotime('now');
        $payload = array(
            'exp'  => $timestamp_exp + (60 * 60 * 3),
            'aud'  => self::getAudFromHttp(),
            'user' => $user,
            'iat'  => $timestamp_exp,
            'nbf'  => $timestamp_now
        );


        return JWT::encode($payload, md5($_ENV["JWT_SIGNATURE_KEY"]),'HS256');
    }


    private static function getAudFromHttp(): string
    {
        $aud = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $aud = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $aud = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $aud = $_SERVER['REMOTE_ADDR'];
        }

        $aud .= @$_SERVER['HTTP_USER_AGENT'];
        $aud .= gethostname();

        return sha1($aud);
    }

    public static function getTokenFromRequest(): ?string
    {
        $headers = self::getAuthorizationHeader();

        try {
            if (!empty($headers)) {
                if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                    return $matches[1];
                }
            }
            if (is_null($headers)) {
                return null;
            }
            throw new \Exception("No autenticado.", 401);
        } catch (\Throwable $th) {
            return $th->getMessage();
        }
    }

    public static function getAuthorizationHeader(): ?string
    {
        $headers = null;

        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER["REDIRECT_HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();

            /**
             * Server-side fix for bug in old Android versions (a nice side-effect of
             * this fix means we don't care about capitalization for Authorization)
             */

            $requestHeaders = array_combine(
                array_map('ucwords', array_keys($requestHeaders)),
                array_values($requestHeaders)
            );

            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }

        return $headers;
    }

    public static function getUserFromToken($token)
    {
        try {
            $payload = JWT::decode($token, new Key(md5($_ENV["JWT_SIGNATURE_KEY"]), 'HS256'));
            return $payload->user;
        } catch (BeforeValidException|ExpiredException $e) {
            throw new \Exception("El token ha expirado.", 401);
        } catch (SignatureInvalidException|\Exception $e) {
            throw new \Exception("El token no es válido.", 401);
        }
    }
}
