<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Courses extends Model
{
    use HasFactory;
    
    public $timestamps = false;

    
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
        'profesor_id',
        'name',
        'price',
        'description',
        'active',
        'category_id'

    ];


        /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'category_id'
    ];
   

    public function profesor()
    {
        return $this->hasOne(User::class, 'id', 'profesor_id');
    }

    public function category()
    {
        return $this->hasOne(Categories::class, 'id', 'category_id');
    }

}
