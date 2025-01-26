<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use App\Models\{Courses, User, Workshops,Materials};

use Illuminate\Support\Facades\DB;

use App\Support\TokenManager;

use App\Support\UploadServer;

use Carbon\Carbon;

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
            'active' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }


        #validate if course exists
        $course = Courses::where('id',$data['course_id'])->first();
        
        if(!$course){
            return response()->json(['error' => 'El curso no existe'], 409);
        }

        $zoom_meeting_id = isset($data['zoom_meeting_id']) ? $data['zoom_meeting_id'] : null;   
        $zoom_passcode = isset($data['zoom_passcode']) ? $data['zoom_passcode'] : null;

        $date = isset($data['date']) ? $data['date'] : null;
        $time = isset($data['time']) ? $data['time'] : null;

      
        $workshop = new Workshops();
        $workshop->course_id = $data['course_id'];
        $workshop->name = $data['name'];
        $workshop->description = $data['description'];    
        $workshop->active = $data['active'];
        $workshop->video_url = isset($data['video_url']) ? $data['video_url'] : null;
    
        $workshop->zoom_meeting_id = $zoom_meeting_id;
        $workshop->zoom_passcode = $zoom_passcode;
        $workshop->date = $date;
        $workshop->time = $time;

        if(isset($data['professor_id'])){
            $profesor = User::where('id',$data['professor_id'])->where('role_id',2)->first();
            if(!$profesor){
                return response()->json(['error' => 'Profesor no encontrado'], 409);
            }
            $workshop->professor_id = $profesor->id;
        }


        if($workshop->save()){

             #get materials from request
                // Retrieve all files from 'materials' input field
                $materials = $request->file('materials');
               
                if ($materials && is_array($materials)) {
                    foreach ($materials as $file) {
                    
                        $path = UploadServer::uploadFile($file, 'workshop/'.$workshop->id.'/materials');

                        $material = new Materials();
                        $material->workshop_id = $workshop->id;
                        $material->file_path = $path;
                        $material->name = $file->getClientOriginalName();
                        $material->active = 1;
                        $material->save();

                    }
                }
            $workshop_aux = Workshops::with(['materials','professor'])->where('id',$workshop->id)->first();    

            return response()->json(['message' => 'Taller creada correctamente', 'data' => $workshop_aux ], 200);
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
            'active' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        #validate if workshop already exists
        $workshop = Workshops::where('id',$workshop_id)->first();
        if(!$workshop){
            return response()->json(['error' => 'El taller no existe'], 409);
        }

        $zoom_meeting_id = isset($data['zoom_meeting_id']) ? $data['zoom_meeting_id'] : null;   
        $zoom_passcode = isset($data['zoom_passcode']) ? $data['zoom_passcode'] : null;
        if(isset($data['date'])){
            $workshop->date = Carbon::parse($data['date'])->format('Y-m-d');
        }
        if(isset($data['time']) && isset($data['date'])){
            $workshop->time = Carbon::parse($data['date'].' '.$data['time'])->format('H:i:s');
        }
        $workshop->name = $data['name'];
        $workshop->description = $data['description'];    
        $workshop->active = $data['active'];
        $workshop->video_url = isset($data['video_url']) ? $data['video_url'] : null;
        $workshop->zoom_meeting_id = $zoom_meeting_id;
        $workshop->zoom_passcode = $zoom_passcode;
  
        if(isset($data['professor_id'])){
            $profesor = User::where('id',$data['professor_id'])->where('role_id',2)->first();
            if(!$profesor){
                return response()->json(['error' => 'Profesor no encontrado'], 409);
            }
            $workshop->professor_id = $profesor->id;
        }

        if($workshop->save()){

            $materials = $request->input('materials');
            $new_materials = $request->file('new_materials');
            $array_ids = [];
            if ($new_materials) {
                foreach ($new_materials as $file) {
                    if(is_file($file)){
                    
                        $path = UploadServer::uploadFile($file, 'workshop/'.$workshop->id.'/materials');

                        $material = new Materials();
                        $material->workshop_id = $workshop->id;
                        $material->file_path = $path;
                        $material->name = $file->getClientOriginalName();
                        $material->active = 1;
                        $material->save();
                        $array_ids[] = $material->id;                           
                    }
                }
            }
            if($materials){
                foreach($materials as $material){
                    $array_ids[] = $material['id'];
                }
            }
            Materials::where('workshop_id',$workshop_id)->whereNotIn('id',$array_ids)->delete();
            $workshop = Workshops::with('materials','professor')->where('id',$workshop_id)->first();
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
            return response()->json(['error' => 'Taller no encontrado'], 404);
        }

        if($workshop->delete()){
            return response()->json(['message' => 'Taller eliminado correctamente'], 200);
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
        $workshop = Workshops::with(['materials','professor'])->find($workshop_id);   
        
        if(!$workshop){
            return response()->json(['error' => 'Taller no encontrada'], 404);
        }
       
        return response()->json(['data' => $workshop ], 200);
    }


}