<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResponseStripe extends Model
{
    use HasFactory;
    
    public $timestamps = true;

    
    protected $table = 'response_stripe';

    
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
        'client_secret',
        'approved_at',
        'error_message'

    ];

   
 
}
