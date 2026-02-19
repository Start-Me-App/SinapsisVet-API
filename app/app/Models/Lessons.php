<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
class Lessons extends Model
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
        'name',
        'description',
        'active',
        'video_url',
        'professor_id',
        'date',
        'time',
        'zoom_meeting_id',
        'zoom_passcode'
    ];

    #hidden fields
    protected $hidden = ['professor_id'];

   
    public function course()
    {
        return $this->hasOne(Courses::class, 'id', 'course_id');
    }

    public function materials()
    {
        return $this->hasMany(Materials::class, 'lesson_id', 'id');
    }

    public function professor()
    {
        return $this->hasOne(User::class, 'id', 'professor_id');
    }

    public function exam()
    {
        return $this->hasOne(Exams::class, 'lesson_id', 'id');
    }

    /**
     * Relación con las vistas/asistencias de la lección
     */
    public function attendances()
    {
        return $this->hasMany(ViewLesson::class, 'lesson_id', 'id');
    }

    /**
     * Relación con los usuarios que vieron la lección (muchos a muchos a través de view_lesson)
     */
    public function attendees()
    {
        return $this->belongsToMany(User::class, 'view_lesson', 'lesson_id', 'user_id')
                    ->withTimestamps();
    }
}
