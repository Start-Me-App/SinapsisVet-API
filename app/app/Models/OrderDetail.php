<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderDetail extends Model
{
    protected $table = 'order_detail';
    
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'course_id',
        'with_workshop',
        'price',
        'quantity'
    ];

    // Relación con la orden
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function course()
    {
        return $this->belongsTo(Courses::class);
    }

} 