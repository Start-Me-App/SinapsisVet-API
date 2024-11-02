<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Results extends Model
{
    use HasFactory;

    protected $table = 'exams_results';

       
    
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
        'user_id',
        'final_grade',
    ];

    protected $appends = ['approved'];

    public function getApprovedAttribute()
    {
        if($this->final_grade >= 6){
            return true;
        }
        return false;
    }
   
   

    public function exam()
    {
        return $this->belongsTo(Exams::class, 'id', 'exam_id');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }


}
