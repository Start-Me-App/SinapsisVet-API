<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShoppingCartContent extends Model
{
    use HasFactory;
    
    public $timestamps = false;

    
    protected $table = 'shopping_cart_content';
    
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
        'course_id',
        'with_workshop',
        'shopping_cart_id'
    ];
   

    public function course()
    {
        return $this->hasOne(Courses::class, 'id', 'course_id');
    }

}
