<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    

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
        'name',
        'email',
        'password',
        'uid',
        'role_id',
        'dob',
        'lastname',
        'telephone',
        'area_code',
        'tyc',
        'nationality_id',
        'sex'

    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'role_id',
        'created_at',
        'updated_at',
        'verification_token',
        'password_reset_token',
        'email_verified_at',
        'uid',
        'nationality_id',
        'tyc',
        'active'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    } 

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            $user->verification_token = bin2hex(random_bytes(32));
        });
    }

    public function moduleByRole()
    {
        return $this->hasMany(ModuleByRole::class,'role_id','role_id');
    }


    public function nationality()
    {
        return $this->belongsTo(Countries::class,'nationality_id','id');
    } 


    protected $appends = ['full_phone','register_completed'];

    public function getFullPhoneAttribute()
    {
        return '+'.$this->area_code . $this->telephone;
    }


    public function getRegisterCompletedAttribute()
    {
       if($this->nationality_id != null ){
           return true;
       }
        return false;
    }

    





 /*    public function providers()
    {
        return $this->hasMany(Provider::class,'user_id','id');
    }



    public function genre()
    {
        return $this->belongsTo(Genre::class);
    }

    */
}