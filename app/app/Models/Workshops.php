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
        'video_url'
    ];
   

    public function course()
    {
        return $this->hasOne(Courses::class, 'id', 'course_id');
    }

}
