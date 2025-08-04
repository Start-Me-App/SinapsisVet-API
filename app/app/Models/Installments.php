<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Installments extends Model
{
    use HasFactory; 

    public $timestamps = false;
    
    protected $table = 'installments';

    protected $fillable = ['order_id','due_date','status'];

 
    public function order()
    {
        return $this->hasOne(Order::class, 'id', 'order_id');
    }

    public function installmentDetails()
    {
        return $this->hasMany(InstallmentDetail::class, 'installment_id', 'id');
    }

    public function installmentMovements()
    {
        return $this->hasManyThrough(Movements::class, InstallmentDetail::class, 'installment_id', 'id', 'id', 'movement_id');
    }

 

}