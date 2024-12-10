<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfessorByCourse extends Model
{
    use HasFactory;
    
    public $timestamps = false;

    
    protected $table = 'professor_by_course';
    
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
        'professor_id'
    ];

    #hide professor_id
    protected $hidden = ['professor_id'];


    public function professor()
    {
        return $this->hasOne(User::class, 'id', 'professor_id');
    }
   


}
