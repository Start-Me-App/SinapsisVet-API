<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use App\Models\{Courses, User, Workshops};

use Illuminate\Support\Facades\DB;

use App\Support\TokenManager;

class WorkshopsController extends Controller
{   

    /**
     * Create workshop
     *
     * @param $provider
     * @return JsonResponse
     */
    public function create(Request $request)
    {

        $data = $request->all();    
        $validator = validator($data, [
            'course_id' => 'required',
            'name' => 'required',
            'description' => 'required',
            'active' => 'required|integer',
            'video_url' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }


        #validate if course exists
        $course = Courses::where('id',$data['course_id'])->first();
        
        if(!$course){
            return response()->json(['error' => 'El curso no existe'], 409);
        }

        $workshop = new Workshops();
        $workshop->course_id = $data['course_id'];
        $workshop->name = $data['name'];
        $workshop->description = $data['description'];    
        $workshop->active = $data['active'];
        $workshop->video_url = $data['video_url'];


        if($workshop->save()){
            return response()->json(['message' => 'Taller creada correctamente', 'data' => $workshop ], 200);
        }

        return response()->json(['error' => 'Error al crear la Taller'], 500);

    }


    /**
     * Update workshop
     *
     * @param $provider
     * @return JsonResponse
     */
    public function update(Request $request,$workshop_id)
    {

        $data = $request->all();    
        $validator = validator($data, [
            'name' => 'required',
            'description' => 'required',
            'active' => 'required|integer',
            'video_url' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        #validate if workshop already exists
        $workshop = Workshops::where('id',$workshop_id)->first();
        if(!$workshop){
            return response()->json(['error' => 'El taller no existe'], 409);
        }

        $workshop->name = $data['name'];
        $workshop->description = $data['description'];    
        $workshop->active = $data['active'];
        $workshop->video_url = $data['video_url'];


        if($workshop->save()){
            return response()->json(['message' => 'Taller actualizado correctamente', 'data' => $workshop ], 200);
        }

        return response()->json(['error' => 'Error al actualizar el taller'], 500);

    }

     /**
     * delete workshop 
     *
     * @param $provider
     * @return JsonResponse
     */
    public function delete(Request $request,$workshop_id)
    {
        $data = $request->all();
        $workshop = Workshops::find($workshop_id);   
        
        if(!$workshop){
            return response()->json(['error' => 'Taller no encontrada'], 404);
        }
        $workshop->active = 0;

        if($workshop->save()){
            return response()->json(['message' => 'Taller eliminada correctamente'], 200);
        }   
        return response()->json(['error' => 'Error al eliminar la Taller'], 500);
    }
     
    
    
    /**
     * get workshop 
     *
     * @param $provider
     * @return JsonResponse
     */
    public function getWorkshop(Request $request,$workshop_id)
    {
        $data = $request->all();
        $workshop = Workshops::find($workshop_id);   
        
        if(!$workshop){
            return response()->json(['error' => 'Taller no encontrada'], 404);
        }
       
        return response()->json(['data' => $workshop ], 200);
    }


}