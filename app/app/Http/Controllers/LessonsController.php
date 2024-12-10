<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use App\Models\{Courses, User, Lessons,Materials,ViewLesson};
use Illuminate\Support\Facades\Input;

use Illuminate\Support\Facades\DB;

use App\Support\TokenManager;
use App\Support\UploadServer;

class LessonsController extends Controller
{   

    /**
     * Create lesson
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
            'professor_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

     
        #validate if course exists
        $course = Courses::where('id',$data['course_id'])->first();
        
        if(!$course){
            return response()->json(['error' => 'El curso no existe'], 409);
        }

        $lesson = new Lessons();
        $lesson->course_id = $data['course_id'];
        $lesson->name = $data['name'];
        $lesson->description = $data['description'];    
        $lesson->active = $data['active'];
        $lesson->video_url = isset($data['video_url']) ? $data['video_url'] : null;
        $lesson->date = $data['date'];
        $profesor = User::where('id',$data['professor_id'])->where('role_id',2)->first();
        if(!$profesor){
            return response()->json(['error' => 'Profesor no encontrado'], 409);
        }
        $lesson->professor_id = $profesor->id;

        if($lesson->save()){

                #get materials from request
                // Retrieve all files from 'materials' input field
                $materials = $request->file('materials');
               
                try{
                    if ($materials && is_array($materials)) {
                        foreach ($materials as $file) {
                    
                        $path = UploadServer::uploadFile($file,'lessons/'. $lesson->id.'/materials');

                        $material = new Materials();
                        $material->lesson_id = $lesson->id;
                        $material->file_path = $path;
                        $material->name = $file->getClientOriginalName();
                        $material->active = 1;
                        $material->save();

                        }
                    }
                }catch(\Exception $e){
                    #rollback lesson
                    $lesson->delete();
                    return response()->json(['error' => 'Error al subir los materiales', 'data' => $e->getMessage()], 500);
                }
            

            $lesson = Lessons::with('materials','professor')->where('id',$lesson->id)->first();

            return response()->json(['message' => 'Leccion creada correctamente', 'data' => $lesson ], 200);
        }

        return response()->json(['error' => 'Error al crear la leccion'], 500);

    }


    /**
     * Update course
     *
     * @param $provider
     * @return JsonResponse
     */
    public function update(Request $request,$lesson_id)
    {
        $data =  $request->all();    

        $validator = validator($data, [
            'name' => 'required',
            'description' => 'required',
            'active' => 'required|integer',
            'professor_id' => 'required'
        ]);


        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        #validate if course already exists
        $lesson = Lessons::where('id',$lesson_id)->first();
        if(!$lesson){
            return response()->json(['error' => 'La leccion no existe'], 409);
        }

        $lesson->name = $data['name'];
        $lesson->description = $data['description'];    
        $lesson->active = $data['active'];
        $lesson->video_url = isset($data['video_url']) ? $data['video_url'] : null;
        $lesson->date = $data['date'];
        $profesor = User::where('id',$data['professor_id'])->where('role_id',2)->first();
        if(!$profesor){
            return response()->json(['error' => 'Profesor no encontrado'], 409);
        }
        $lesson->professor_id = $profesor->id;


        if($lesson->save()){

                $materials = $request->input('materials');
                $new_materials = $request->file('new_materials');
                $array_ids = [];
                if ($new_materials) {
                   
                   try{
                        foreach ($new_materials as $file) {
                            $path = UploadServer::uploadFile($file,'lessons/'. $lesson->id.'/materials');
                            $material = new Materials();
                            $material->lesson_id = $lesson->id;
                            $material->file_path = $path;
                            $material->name = $file->getClientOriginalName();
                            $material->active = 1;
                            $material->save();
                        }
                   }catch(\Exception $e){
                        $lesson->delete();
                        return response()->json(['error' => 'Error al subir los materiales', 'data' => $e->getMessage()], 500);
                   }
                }
                if($materials){
                    foreach($materials as $material){
                        $array_ids[] = $material['id'];
                    }
                }
                Materials::where('lesson_id',$lesson_id)->whereNotIn('id',$array_ids)->delete();
                $lesson = Lessons::with('materials','professor')->where('id',$lesson_id)->first();
                return response()->json(['message' => 'Leccion actualizada correctamente', 'data' => $lesson ], 200);
        }

        return response()->json(['error' => 'Error al actualizar la leccion'], 500);

    }

     /**
     * delete lesson 
     *
     * @param $provider
     * @return JsonResponse
     */
    public function delete(Request $request,$lesson_id)
    {
        $data = $request->all();
        $lesson = Lessons::find($lesson_id);   
        
        if(!$lesson){
            return response()->json(['error' => 'Leccion no encontrada'], 404);
        }
       
       
        #delete materials from lesson
        Materials::where('lesson_id',$lesson_id)->delete();

        #delete files from storage
        $path = storage_path('app/public/lessons/'.$lesson_id.'/materials');
        
        if(file_exists($path)){
            $files = glob($path.'/*'); // get all file names
            foreach($files as $file){ // iterate files
                if(is_file($file)){
                    unlink($file); // delete file
                }
            }
            rmdir($path);
            rmdir(storage_path('app/public/lessons/'.$lesson_id));

        }

        if($lesson->delete()){
            return response()->json(['message' => 'Leccion eliminada correctamente'], 200);
        }


        return response()->json(['error' => 'Error al eliminar la leccion'], 500);
    }


     /**
     * get lesson 
     *
     * @param $provider
     * @return JsonResponse
     */
    public function getLesson(Request $request,$lesson_id)
    {
        $data = $request->all();
        $lesson = Lessons::with('materials','professor')->find($lesson_id);           
        
        if(!$lesson){
            return response()->json(['error' => 'Leccion no encontrada'], 404);
        }
        return response()->json(['data' => $lesson ], 200);

    }



    /**
     * view lesson 
     *
     * @param $provider
     * @return JsonResponse
     */
    public function viewLesson(Request $request,$lesson_id)
    {
        $data = $request->all();
        $lesson = Lessons::find($lesson_id);           
        
        if(!$lesson){
            return response()->json(['error' => 'Leccion no encontrada'], 404);
        }

        $accessToken = TokenManager::getTokenFromRequest();
        $user = TokenManager::getUserFromToken($accessToken);

        #mark lesson as viewed
        $viewLesson = ViewLesson::where('user_id',$user->id)->where('lesson_id',$lesson_id)->first();
        if(!$viewLesson){
            $viewLesson = new ViewLesson();
            $viewLesson->user_id = $user->id;
            $viewLesson->lesson_id = $lesson_id;
            $viewLesson->save();
        }

        return response()->json(['data' => $viewLesson ], 200);

    }


}