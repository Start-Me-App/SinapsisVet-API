<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotmartEvent extends Model
{
    /**
     * La tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'hotmart_events';

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array
     */
    protected $fillable = [
        'event_type',
        'transaction_id',
        'product_id',
        'product_name',
        'buyer_email',
        'buyer_name',
        'status',
        'price_value',
        'price_currency',
        'commission_value',
        'approved_date',
        'raw_data',
        'order_id',
        'processed'
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'raw_data' => 'array',
        'price_value' => 'decimal:2',
        'commission_value' => 'decimal:2',
        'approved_date' => 'datetime',
        'processed' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relación con la orden
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
