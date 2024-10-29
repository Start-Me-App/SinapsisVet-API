<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exams extends Model
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
        'course_id',
        'lesson_id',
        'name',
        'active',
    ];
   

    public function course()
    {
        return $this->hasOne(Courses::class, 'id', 'course_id');
    }
    public function lesson()
    {
        return $this->hasOne(Lessons::class, 'id', 'lesson_id');
    }

    public function questions()
    {
        return $this->hasMany(Questions::class, 'exam_id', 'id');
    }
    

}
