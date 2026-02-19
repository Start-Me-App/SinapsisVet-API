<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ViewLesson extends Model
{
    use HasFactory;
    
    public $timestamps = true;

    
    protected $table = 'view_lesson';

    
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
        'user_id',
        'lesson_id'
    ];

    /**
     * Relación con la lección
     */
    public function lesson()
    {
        return $this->belongsTo(Lessons::class, 'lesson_id');
    }

    /**
     * Relación con el usuario
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
