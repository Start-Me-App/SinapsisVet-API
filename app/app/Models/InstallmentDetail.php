<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstallmentDetail extends Model
{
    use HasFactory; 

    public $timestamps = false;
    
    protected $table = 'installment_detail';

    protected $fillable = ['installment_id','installment_number','due_date','paid_date','url_payment','paid'];

 
    public function installment()
    {
        return $this->hasOne(Installments::class, 'id', 'installment_id');
    }

    

}