<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModuleByRole extends Model
{
    use HasFactory;
    

    protected $table = 'module_by_role';

       
     /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';
    
    
    protected $fillable = ['role_id', 'module_id', 'list', 'create', 'update', 'delete'];

     /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'role_id',
        'id',
        'module_id'
    ];


    public function role()
    {
        return $this->belongsTo(Role::class);
    }
    public function module()
    {
        return $this->belongsTo(Module::class);
    }
}