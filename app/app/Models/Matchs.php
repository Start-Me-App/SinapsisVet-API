<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Matchs extends Model
{
    use HasFactory;
    protected $table = 'match';

    
    public $timestamps = true;


       
    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'user_1_id',
        'user_2_id',
        'event_id'
    ];


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'user_1_id',
        'user_2_id',
        'updated_at',
        'event_id',
    ];

    // Define the relationship to the User model for user_1_id
    public function user1()
    {
        return $this->belongsTo(User::class, 'user_1_id');
    }

    // Define the relationship to the User model for user_2_id
    public function user2()
    {
        return $this->belongsTo(User::class, 'user_2_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
