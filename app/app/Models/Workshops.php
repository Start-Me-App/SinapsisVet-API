<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workshops extends Model
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
        'date',
        'time',
        'zoom_meeting_id',
        'zoom_passcode'
    ];
   

    public function course()
    {
        return $this->hasOne(Courses::class, 'id', 'course_id');
    }

    public function materials()
    {
        return $this->hasMany(Materials::class, 'workshop_id', 'id');
    } 

}
