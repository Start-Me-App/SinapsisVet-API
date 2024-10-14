<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use Illuminate\Support\Facades\DB;
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::table('roles')->insert([
            ['name' => 'Admin'],
            [ 'name' => 'Profesor' ],
            [ 'name' => 'Alumno' ]]);  
    
        DB::table('users')->insert([
            ['name' => 'Admin', 'lastname' => 'Admin', 'email' => 'admin@gmail.com','email_verified_at' => now(), 'password' => md5('12345678'), 'role_id' => 1,'active' => 1],
            ['name' => 'Profesor', 'lastname' => 'Profesor', 'email' => 'profe@gmail.com','email_verified_at' => now(), 'password' => md5('12345678'), 'role_id' => 2,'active' => 1],
            ['name' => 'Alumno', 'lastname' => 'Alumno', 'email' => 'alumno@gmail.com','email_verified_at' => now(), 'password' => md5('12345678'), 'role_id' => 3,'active' => 1]]);  
         
        DB::table('module')->insert([
            ['name' => 'users']
        ]);

        DB::table('module_by_role')->insert([
            ['role_id' => 1, 'module_id' => 1,'list' => 1,'create' => 1,'update' => 1,'delete' => 1],
            ['role_id' => 2, 'module_id' => 1,'list' => 0,'create' => 0,'update' => 1,'delete' => 0],
            ['role_id' => 3, 'module_id' => 1,'list' => 0,'create' => 0,'update' => 1,'delete' => 0]
        ]);
    }
}
