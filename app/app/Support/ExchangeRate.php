<?php

namespace App\Support;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * Cotización USD/ARS desde Bluelytics (API pública, sin key).
 * Usa el tipo oficial (value_sell) por defecto.
 */
class ExchangeRate
{
    const BLUELYTICS_URL = 'https://api.bluelytics.com.ar/v2/latest';

    /**
     * Devuelve el tipo de cambio oficial USD → ARS (precio de venta).
     * Si falla la consulta, devuelve null.
     */
    public static function usdToArs(): ?float
    {
        try {
            $client = new Client(['timeout' => 5]);
            $response = $client->get(self::BLUELYTICS_URL);
            $data = json_decode($response->getBody()->getContents(), true);
            return (float) $data['oficial']['value_sell'];
        } catch (\Throwable $e) {
            Log::warning('ExchangeRate: no se pudo obtener cotización USD/ARS: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Convierte un monto en USD a ARS usando el tipo oficial.
     * Si no puede obtener la cotización, devuelve null.
     */
    public static function convertUsdToArs(float $amountUsd): ?float
    {
        $rate = self::usdToArs();
        if ($rate === null) {
            return null;
        }
        return round($amountUsd * $rate, 2);
    }
}
