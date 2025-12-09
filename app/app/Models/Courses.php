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
        'title',
        'price',
        'description',
        'active',
        'category_id',
        'photo_url',
        'video_preview_url',
        'starting_date',
        'inscription_date',
        'subtitle',
        'destined_to',
        'certifications',
        'academic_duration',
        'comission'
    ];


        /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'category_id'
    ];


    protected $appends = ['photo_url'];

    public function getPhotoUrlAttribute()
    {
        return env('STATIC_URL') . $this->attributes['photo_url'];
    }
   
    public function getAsociationPathAttribute()
    {
        return env('STATIC_URL') . $this->attributes['asociation_path'];
    }
   


    public function professors()
    {
        return $this->belongsToMany(User::class, 'professor_by_course', 'course_id', 'professor_id');
    }

    public function category()
    {
        return $this->hasOne(Categories::class, 'id', 'category_id');
    }

    public function lessons()
    {
        return $this->hasMany(Lessons::class, 'course_id', 'id');
    }


    public function exams()
    {
        return $this->hasMany(Exams::class, 'course_id', 'id');
    }

    public function workshops()
    {
        return $this->hasMany(Workshops::class, 'course_id', 'id');
    }

    public function inscriptions()
    {
        return $this->hasMany(Inscriptions::class, 'course_id', 'id');
    }

    public function custom_fields()
    {
        return $this->hasMany(CoursesCustomField::class, 'course_id', 'id');
    }
}
