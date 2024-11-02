<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Console\Question\Question;

class Questions extends Model
{
    use HasFactory;
    
    protected $table = 'questions_exams';

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
        'exam_id',
        'question_title'
    ];


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'exam_id'
    ];
   

    public function exams()
    {
        return $this->belongsTo(Exams::class, 'id', 'exam_id');
    }

    public function answers()
    {
        return $this->hasMany(Answers::class, 'question_id', 'id');
    }
    
    

}
