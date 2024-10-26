<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use App\Models\{Courses, Lessons, Materials, User, Workshops};

use Illuminate\Support\Facades\DB;

use App\Support\TokenManager;

class MaterialsController extends Controller
{   

    /**
     * Create material
     *
     * @param $provider
     * @return JsonResponse
     */
    public function create(Request $request)
    {

        $data = $request->all();    
        $validator = validator($data, [
            'lesson_id' => 'required',
            'name' => 'required',
            'active' => 'required|integer',
            'file_path' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }


        #validate if course exists
        $course = Lessons::where('id',$data['lesson_id'])->first();
        
        if(!$course){
            return response()->json(['error' => 'La leccion no existe'], 409);
        }

        $material = new Materials();
        $material->lesson_id = $data['lesson_id'];
        $material->name = $data['name'];
        $material->active = $data['active'];
        $material->file_path = $data['file_path'];


        if($material->save()){
            return response()->json(['message' => 'Material creado correctamente', 'data' => $material ], 200);
        }

        return response()->json(['error' => 'Error al crear el material'], 500);

    }


    /**
     * Update material
     *
     * @param $provider
     * @return JsonResponse
     */
    public function update(Request $request,$material_id)
    {

        $data = $request->all();    
        $validator = validator($data, [
            'name' => 'required',
            'active' => 'required|integer',
            'file_path' => 'required',
            'lesson_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        #validate if material already exists
        $material = Materials::where('id',$material_id)->first();
        if(!$material){
            return response()->json(['error' => 'El material no existe'], 409);
        }

        #validate if lesson exists
        $lesson = Lessons::where('id',$data['lesson_id'])->first();
        if(!$lesson){
            return response()->json(['error' => 'La leccion no existe'], 409);
        }

        $material->name = $data['name'];
        $material->active = $data['active'];
        $material->file_path = $data['file_path'];
        $material->lesson_id = $data['lesson_id'];

        if($material->save()){
            return response()->json(['message' => 'Material actualizado correctamente', 'data' => $material ], 200);
        }

        return response()->json(['error' => 'Error al actualizar el taller'], 500);

    }

     /**
     * delete material 
     *
     * @param $provider
     * @return JsonResponse
     */
    public function delete(Request $request,$workshop_id)
    {
        $data = $request->all();
        $material = Materials::find($workshop_id);   
        
        if(!$material){
            return response()->json(['error' => 'Material no encontrado'], 404);
        }
        $material->active = 0;

        if($material->save()){
            return response()->json(['message' => 'Material eliminado correctamente'], 200);
        }   
        return response()->json(['error' => 'Error al eliminar el material'], 500);
    }


     /**
     * get material 
     *
     * @param $provider
     * @return JsonResponse
     */
    public function getMaterial(Request $request,$workshop_id)
    {
        $data = $request->all();
        $material = Materials::find($workshop_id);   
        
        if(!$material){
            return response()->json(['error' => 'Material no encontrado'], 404);
        }

        return response()->json(['data' => $material], 200);
    }


}