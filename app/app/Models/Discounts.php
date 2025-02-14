<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Discounts extends Model
{
    use HasFactory; 

    public $timestamps = false;
    
    protected $table = 'discounts';

    protected $fillable = ['courses_amount', 'discount_percentage'];

    protected $hidden = ['courses_amount', 'discount_percentage'];

    protected $appends = ['amount', 'discount'];

    public function getAmountAttribute()
    {
        return $this->courses_amount;
    }

    public function getDiscountAttribute()
    {
        return $this->discount_percentage;
    }

}