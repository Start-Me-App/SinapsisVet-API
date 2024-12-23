<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\OrderDetail;

class Order extends Model
{
    /**
     * La tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'order';

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'status',
        'date_created',
        'date_last_updated',
        'date_closed',
        'date_paid',
        'shopping_cart_id'
    ];

    /**
     * Indica si el modelo debe tener marcas de tiempo.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'date_created' => 'datetime',
        'date_last_updated' => 'datetime',
        'date_closed' => 'datetime',
        'date_paid' => 'datetime',
    ];

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }
} 