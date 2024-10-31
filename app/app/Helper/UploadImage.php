<?php

declare(strict_types=1);

namespace App\Helper;

class UploadImage2
{
    /**
     * Upload image to server
     */
    public static function uploadServer($file): array
    {
        $URL_PATH = 'u?key='.env('TOKEN_SERVICE_STATIC');

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'imagen' => $file,
            'temp'   => 0,
        ]);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_URL, $_ENV['URL_SERVICE_STATIC'].$URL_PATH);

        $response   = curl_exec($ch);
        $codeStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            "response" => json_decode($response, false, 512, JSON_THROW_ON_ERROR),
            "code"     => $codeStatus
        ];
    }


    /**
     * Make image permanent
     */
    public static function makePermanent($img): array
    {
        $URL_PATH = 't?key='.$_ENV['TOKEN_SERVICE_STATIC'];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_POSTFIELDS, urldecode(http_build_query((['imagen' => $img]))));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_URL, $_ENV['URL_SERVICE_STATIC'].$URL_PATH);

        $response   = curl_exec($ch);
        $codeStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return [
            "response" => json_decode($response, false, 512, JSON_THROW_ON_ERROR),
            "code"     => $codeStatus
        ];
    }
}
