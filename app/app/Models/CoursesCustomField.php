<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoursesCustomField extends Model
{
    protected $table = 'courses_custom_fields';
    
    protected $fillable = [
        'name',
        'value',
        'course_id'
    ];

    public $timestamps = false;

    public function course()
    {
        return $this->belongsTo(Courses::class);
    }
} 