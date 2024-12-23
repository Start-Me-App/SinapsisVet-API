<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResponseMercadoPago extends Model
{
    use HasFactory;
    
    public $timestamps = true;

    
    protected $table = 'response_mercadopago';

    
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
        'data_id',
        'order_id',
        'status',
        'preference_id',
        'status_detail',
        'approved_at',
        'payment_method_id',
        'payment_type_id',
        'error_message'
    ];
   
 
}
