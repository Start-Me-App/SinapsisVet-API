<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inscriptions extends Model
{
    use HasFactory; 

    public $timestamps = false;
    
    protected $table = 'inscriptions';

    protected $fillable = ['user_id','course_id','with_workshop'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'user_id',
        'course_id',
        'id'
    ];


    public function course()
    {
        return $this->hasOne(Courses::class, 'id', 'course_id');
    }
    
    public function student()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

}