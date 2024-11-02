<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Console\Question\Question;

class Answers extends Model
{
    use HasFactory;
    
    protected $table = 'questions_answers';
    
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
        'question_id',
        'answer_title',
        'is_correct'
    ];

      /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'question_id'
    ];
   
   

    public function question()
    {
        return $this->belongsTo(Question::class, 'id', 'question_id');
    }
}
