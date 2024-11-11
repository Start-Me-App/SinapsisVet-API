<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShoppingCart extends Model
{
    use HasFactory;
    
    public $timestamps = true;

    
    protected $table = 'shopping_cart';
    
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
        'active'
    ];
   

    public function items()
    {
        return $this->hasMany(ShoppingCartContent::class, 'shopping_cart_id', 'id');
    }

/*     public function materials()
    {
        return $this->hasMany(Materials::class, 'lesson_id', 'id');
    } */

}
