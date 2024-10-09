<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDecision extends Model
{
    use HasFactory;
    protected $table = 'user_decision';
    public $timestamps = false;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'user_owner_id',
        'user_match_id',
        'event_id',
        'decision'
    ];

}
