<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use App\Models\{Courses, Exams, User, Lessons};

use Illuminate\Support\Facades\DB;

use App\Support\TokenManager;

class ExamsController extends Controller
{   

    /**
     * Create exam
     *
     * @param $provider
     * @return JsonResponse
     */
    public function create(Request $request)
    {

        $data = $request->all();    
        $validator = validator($data, [
            'name' => 'required',
            'active' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        if(!isset($data['course_id'])){
            return response()->json(['error' => 'Faltan datos'], 422);
        }

        if(isset($data['course_id'])){
            #validate if course exists
            $course = Courses::where('id',$data['course_id'])->first();

            if(!$course){
                return response()->json(['error' => 'El curso no existe'], 409);
            }

        }
    

        $exam = new Exams();
        $exam->course_id = $data['course_id'];
        $exam->name = $data['name'];    
        $exam->active = $data['active'];


        if($exam->save()){
            return response()->json(['message' => 'Examen creado correctamente', 'data' => $exam ], 200);
        }

        return response()->json(['error' => 'Error al crear el examen'], 500);

    }


    /**
     * Update course
     *
     * @param $provider
     * @return JsonResponse
     */
    public function update(Request $request,$exam_id)
    {

        $data = $request->all();    
        $validator = validator($data, [
            'name' => 'required',
            'active' => 'required|integer'
        ]);
        

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        if(!isset($data['course_id'])){
            return response()->json(['error' => 'Faltan datos'], 422);
        }

    

        if(isset($data['course_id'])){
            #validate if course exists
            $course = Courses::where('id',$data['course_id'])->first();

            if(!$course){
                return response()->json(['error' => 'El curso no existe'], 409);
            }

        }

        #validate if exam exists
        $exam = Exams::where('id',$exam_id)->first();

        if(!$exam){
            return response()->json(['error' => 'El examen no existe'], 409);
        }
        
        $exam->course_id = $data['course_id'];
        $exam->name = $data['name'];    
        $exam->active = $data['active'];


        if($exam->save()){
            return response()->json(['message' => 'Examen actualizado correctamente', 'data' => $exam ], 200);
        }

        return response()->json(['error' => 'Error al actualizar el examen'], 500);

    }

     /**
     * delete lesson 
     *
     * @param $provider
     * @return JsonResponse
     */
    public function delete(Request $request,$exam_id)
    {
        $data = $request->all();
        $exam = Exams::find($exam_id);   
        
        if(!$exam){
            return response()->json(['error' => 'Examen no encontrado'], 404);
        }
        $exam->active = 0;

        if($exam->save()){
            return response()->json(['message' => 'Examen eliminado correctamente'], 200);
        }   
        return response()->json(['error' => 'Error al eliminar el examen'], 500);
    }


}