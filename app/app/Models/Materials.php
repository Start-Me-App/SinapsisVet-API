<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Materials extends Model
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
        'lesson_id',
        'name',
        'file_path',
        'active'
    ];
   

    
    protected $appends = ['file_path_url'];

    public function getFilePathUrlAttribute()
    {
        $path = explode('/', $this->attributes['file_path']);

        if(count($path) <= 2){
            return null;
        }

        return env('STATIC_URL') .'/api/'.$path[2].'/materials/'.$path[3].'/'.$path[5];
        
    }
   
    
    public function lessons()
    {
        return $this->hasOne(Lessons::class, 'id', 'lesson_id');
    }

    public function workshop()
    {
        return $this->hasOne(Workshops::class, 'id', 'workshop_id');
    }

}
