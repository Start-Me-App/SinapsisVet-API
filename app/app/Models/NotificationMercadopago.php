<?php

namespace App\Models;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Eloquent\Model;

class NotificationMercadoPago extends Model
{
    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'notification_mercadopago';

    /**
     * Indica si el modelo debe tener marcas de tiempo.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array
     */
    protected $fillable = [
        'id_webhook',
        'live_mode',
        'date_created',
        'user_id_mercadopago',
        'api_version',
        'action',
        'data_id'
    ];
} 