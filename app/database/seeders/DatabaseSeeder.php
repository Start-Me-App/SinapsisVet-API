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
            ['name' => 'Admin', 'lastname' => 'Admin', 'email' => 'admin@gmail.com','email_verified_at' => now(), 'password' => md5('12345678'), 'role_id' => 1,'active' => 1,'nationality_id' => 1],
            ['name' => 'Profesor', 'lastname' => 'Profesor', 'email' => 'profe@gmail.com','email_verified_at' => now(), 'password' => md5('12345678'), 'role_id' => 2,'active' => 1,'nationality_id' => 1],
            ['name' => 'Alumno', 'lastname' => 'Alumno', 'email' => 'alumno@gmail.com','email_verified_at' => now(), 'password' => md5('12345678'), 'role_id' => 3,'active' => 1,'nationality_id' => 1],
            ['name' => 'Fernando', 'lastname' => 'Pellegrino', 'email' => 'fpellegrino@gmail.com','email_verified_at' => now(), 'password' => md5('12345678'), 'role_id' => 2,'active' => 1,'nationality_id' => 1],
            ['name' => 'Javier', 'lastname' => 'Ochoa', 'email' => 'jochoa@gmail.com','email_verified_at' => now(), 'password' => md5('12345678'), 'role_id' => 2,'active' => 1,'nationality_id' => 1],
        ]);  
         
        DB::table('module')->insert([
            ['name' => 'users']
        ]);

        DB::table('module_by_role')->insert([
            ['role_id' => 1, 'module_id' => 1,'list' => 1,'create' => 1,'update' => 1,'delete' => 1],
            ['role_id' => 2, 'module_id' => 1,'list' => 0,'create' => 0,'update' => 1,'delete' => 0],
            ['role_id' => 3, 'module_id' => 1,'list' => 0,'create' => 0,'update' => 1,'delete' => 0]
        ]);

        DB::table('courses')->insert([
            ['title' => 'Curso 1', 'description' => 'Curso 1', 'active' => 1,'price_ars' => 100,'price_usd' => 100, 'category_id' => 1, 'photo_url' => 'asd', 'starting_date' => '2021-01-01', 'inscription_date' => '2021-01-01', 'objective' => 'Curso 1', 'presentation' => 'Curso 1','destined_to' => 'Curso 1','subtitle' => 'Curso 1','certifications' => 'Curso 1'],
            ['title' => 'Curso 2', 'description' => 'Curso 2', 'active' => 1,'price_ars' => 100,'price_usd' => 100, 'category_id' => 2, 'photo_url' => 'asd', 'starting_date' => '2021-01-01', 'inscription_date' => '2021-01-01', 'objective' => 'Curso 2', 'presentation' => 'Curso 2','destined_to' => 'Curso 2','subtitle' => 'Curso 2','certifications' => 'Curso 2'],
            ['title' => 'Curso 3', 'description' => 'Curso 3', 'active' => 1,'price_ars' => 100,'price_usd' => 100,'category_id' => 1, 'photo_url' => 'asd', 'starting_date' => '2021-01-01', 'inscription_date' => '2021-01-01', 'objective' => 'Curso 3', 'presentation' => 'Curso 3','destined_to' => 'Curso 3','subtitle' => 'Curso 3','certifications' => 'Curso 3']]);


        DB::table('professor_by_course')->insert([
            ['course_id' => 1, 'professor_id' => 2],
            ['course_id' => 2, 'professor_id' => 2],
            ['course_id' => 3, 'professor_id' => 2]
        ]);

        DB::table('lessons')->insert([
            ['name' => 'Leccion 1', 'description' => 'Leccion 1', 'active' => 1, 'course_id' => 1, 'video_url' => 'https:123123', 'professor_id' => 2,'date' => '2021-01-01'],
            ['name' => 'Leccion 2', 'description' => 'Leccion 2', 'active' => 1, 'course_id' => 1, 'video_url' => 'https:123123', 'professor_id' => 2,'date' => '2021-01-01'],
            ['name' => 'Leccion 2-1', 'description' => 'Leccion 2-1', 'active' => 1, 'course_id' => 2, 'video_url' => 'https:123123', 'professor_id' => 2,'date' => '2021-01-01']]);

        DB::table('workshops')->insert([
            ['name' => 'Taller 1', 'description' => 'Taller 1', 'active' => 1, 'course_id' => 1, 'video_url' => 'https:123123'],
            ['name' => 'Taller 2', 'description' => 'Taller 2', 'active' => 1, 'course_id' => 1, 'video_url' => 'https:123123'],
            ['name' => 'Taller 2-1', 'description' => 'Taller 2-1', 'active' => 1, 'course_id' => 2, 'video_url' => 'https:123123']]);

            DB::table('materials')->insert([
            ['name' => 'mat 1',  'active' => 1, 'lesson_id' => 1,  'file_path' => 'https:123',],
            ['name' => 'mat 2',  'active' => 1, 'lesson_id' => 1,  'file_path' => 'https:123',],
            ['name' => 'mat 2-1', 'active' => 1, 'lesson_id' => 2,  'file_path' => 'https:123',]]);


            DB::table('exams')->insert([
                ['name' => 'exa 1', 'active' => 1, 'course_id' => 1,'lesson_id' => null],
                ['name' => 'exa 2', 'active' => 1, 'course_id' => 1,'lesson_id' => null],
                ['name' => 'eax 2-1', 'active' => 1, 'course_id' => null,'lesson_id' => 2]]);

            DB::table('courses_category')->insert([
                ['name' => 'Categoria 1'],
                ['name' => 'Categoria 2'],
                ['name' => 'Categoria 3']]);
        }
}
