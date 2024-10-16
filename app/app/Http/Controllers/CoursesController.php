<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use App\Models\{Courses, User};

use Illuminate\Support\Facades\DB;

use App\Support\TokenManager;

class CoursesController extends Controller
{   

    /**
     * Create course
     *
     * @param $provider
     * @return JsonResponse
     */
    public function create(Request $request)
    {
        
        $data = $request->all();        
        $validator = validator($data, [
            'name' => 'required',
            'description' => 'required',
            'profesor_id' => 'required',
            'price' => 'required',
            'active' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        #validate if profesor_id is a valid user
        $profesor = User::where('id',$data['profesor_id'])->where('role_id',2)->first();
        
        if(!$profesor){
            return response()->json(['error' => 'Profesor no encontrado'], 409);
        }


        #validate if course already exists
        $course = Courses::where('name',$data['name'])->first();
        
        if($course){
            return response()->json(['error' => 'Curso ya existe'], 409);
        }
      
        $course = new Courses();
        $course->name = $data['name'];
        $course->description = $data['description'];
        $course->profesor_id = $data['profesor_id'];
        $course->price = $data['price'];
        $course->active = $data['active'];

        if($course->save()){
            return response()->json(['message' => 'Curso creado correctamente', 'data' => $course ], 200);
        }

        return response()->json(['error' => 'Error al crear el curso'], 500);

    }


    /**
     * Update course
     *
     * @param $provider
     * @return JsonResponse
     */
    public function update(Request $request,$course_id)
    {

        
        $data = $request->all();        
        $validator = validator($data, [
            'name' => 'required',
            'description' => 'required',
            'profesor_id' => 'required',
            'price' => 'required',
            'active' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        #validate if course already exists
        $course = Courses::where('id',$course_id)->first();
        if(!$course){
            return response()->json(['error' => 'El curso no existe'], 409);
        }

        #validate if profesor_id is a valid user
        $profesor = User::where('id',$data['profesor_id'])->where('role_id',2)->first();
     
        if(!$profesor){
            return response()->json(['error' => 'Profesor no encontrado'], 409);
        }
  
        $course->name = $data['name'];
        $course->description = $data['description'];
        $course->profesor_id = $data['profesor_id'];
        $course->price = $data['price'];
        $course->active = $data['active'];
        
        if($course->save()){
            return response()->json(['message' => 'Curso actualizado correctamente', 'data' => $course ], 200);
        }

        return response()->json(['error' => 'Error al actualizar el curso'], 500);

    }

     /**
     * delete course 
     *
     * @param $provider
     * @return JsonResponse
     */
    public function delete(Request $request,$course_id)
    {
        $data = $request->all();
        $course = Courses::find($course_id);   
        
        if(!$course){
            return response()->json(['error' => 'Curso no encontrado'], 404);
        }
        $course->active = 0;

        if($course->save()){
            return response()->json(['message' => 'Curso eliminado correctamente'], 200);
        }   
        return response()->json(['error' => 'Error al eliminar el curso'], 500);
    }


    /**
     * Listado de cursos
     *
     * @param $provider
     * @return JsonResponse
     */
    public function listAllCourses(Request $request)
    {   

        $params = $request->all();
      
        $list = Courses::with(['profesor','category'])->get();
      
        return response()->json(['data' => $list], 200);
    }
}