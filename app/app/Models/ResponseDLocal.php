<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResponseDLocal extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected $table = 'response_dlocal';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'user_id',
        'order_id',
        'status',
        'payment_id',       // id del pago en dLocal Go
        'redirect_url',     // URL de checkout a la que se redirige al usuario
        'subscription_id',  // id de la suscripción (cuotas sin interés)
        'currency',         // moneda cobrada (ARS, USD, u otra local)
        'fee_usd',          // comisión dLocal (balance_fee) en USD
        'net_amount_usd',   // neto acreditado (balance_amount) en USD
        'net_amount_ars',   // neto convertido a ARS
        'exchange_rate',    // cotización USD→ARS usada
        'approved_at',
        'error_message',
    ];
}
