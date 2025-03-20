<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupons extends Model
{
    use HasFactory; 

    public $timestamps = false;
    
    protected $table = 'coupons';

    protected $fillable = ['code', 'amount_value_usd', 'amount_value_ars', 'discount_percentage', 'expiration_date', 'used_times', 'max_uses'];

    protected $hidden = [];

 /*    protected $appends = ['amount_usd', 'amount_ars', 'discount'];

    public function getAmountUsdAttribute()
    {
        return $this->amount_value_usd;
    }

    public function getAmountArsAttribute()
    {
        return $this->amount_value_ars;
    }

    public function getDiscountAttribute()
    {
        return $this->discount_percentage;
    } */

}