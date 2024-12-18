<?php
declare(strict_types=1);

namespace App\Models;

final class ResponseMercadoPagoModel
{

    public int    $client_id;
    public int    $branch;
    public int    $order_id;
    public int    $data_id = 0;
    public string $status = 'null';
    public string $preference_id = 'null';
    public string $status_detail = 'null';
    public mixed  $date_approved = 'null';
    public string $payment_method_id = 'null';
    public string $payment_type_id   = 'null';
    public string $error_message     = 'null';
}
