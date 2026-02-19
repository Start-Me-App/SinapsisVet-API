<?php

namespace App\Http\Controllers;

use App\Support\UploadServer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use App\Models\{Courses, Lessons, User, Workshops,Exams,ProfessorByCourse,CoursesCustomField,Results,Materials};

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
            'title' => 'required',
            'description' => 'required',
            'price_ars' => 'required',
            'price_usd' => 'required',
            'active' => 'required|integer',
            'category_id' => 'required',
            'photo_file' => 'required',
            'starting_date' => 'required',
            'inscription_date' => 'required',
            'objective' => 'required',
            'presentation' => 'required',
            'professors' => 'required',
            'academic_duration' => 'required',
            'comission' => 'nullable|numeric',
            'video_preview_url' => 'nullable',
            'recorded_course' => 'nullable|boolean',
            'masterclass' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }




        #validate if course already exists
        $course = Courses::where('title',$data['title'])->first();
        
        if($course){
            return response()->json(['error' => 'Curso ya existe'], 409);
        }

        #validate if photo is a valid image
        if(!UploadServer::validateImage($data['photo_file'])){
            return response()->json(['error' => 'El archivo no es una imagen'], 409);
        }

        $upload = UploadServer::uploadImage($data['photo_file'],'images');
        
        if(!$upload){
            return response()->json(['error' => 'Error al subir la imagen'], 500);
        } 

        if(count($data['professors']) == 0){
            return response()->json(['error' => 'Debe haber al menos un profesor'], 409);
        }


        #validate if starting date is greater than inscription date
        if(strtotime($data['starting_date']) < strtotime($data['inscription_date'])){
            return response()->json(['error' => 'La fecha de inicio no puede ser menor que la fecha de inscripcion'], 409);
        }

        if(isset($data['asociation_file'])){
            if(!UploadServer::validateImage($data['asociation_file'])){
                return response()->json(['error' => 'El archivo no es un archivo'], 409);
            }
    
            $upload_asociation = UploadServer::uploadImage($data['asociation_file'],'asociations');
            
            if(!$upload_asociation){
                return response()->json(['error' => 'Error al subir la imagen'], 500);
            } 

        }else{
            $upload_asociation = null;
        }

        $subtitle = isset($data['subtitle']) ? $data['subtitle'] : null;
        $destined_to = isset($data['destined_to']) ? $data['destined_to'] : null;
        $certifications = isset($data['certifications']) ? $data['certifications'] : null;

      
 
        $course = new Courses();
        $course->title = $data['title'];
        $course->description = $data['description'];
        $course->price_ars = $data['price_ars'];
        $course->price_usd = $data['price_usd'];
        $course->active = $data['active'];
        $course->category_id = $data['category_id'];
        $course->photo_url = $upload;
        $course->starting_date = $data['starting_date'];
        $course->inscription_date = $data['inscription_date'];
        $course->objective = $data['objective'];
        $course->presentation = $data['presentation'];        
        $course->asociation_path = $upload_asociation;
        $course->subtitle = $subtitle;
        $course->destined_to = $destined_to;
        $course->certifications = $certifications;
        $course->academic_duration = $data['academic_duration'];
        $course->comission = $data['comission'] ?? 100;
        $course->video_preview_url = isset($data['video_preview_url']) ? $data['video_preview_url'] : null;
        $course->recorded_course = isset($data['recorded_course']) ? $data['recorded_course'] : false;
        $course->masterclass = isset($data['masterclass']) ? $data['masterclass'] : false;


        if($course->save()){

            foreach($data['professors'] as $professor_id){
                #validate if profesor_id is a valid user
                $profesor = User::where('id',$professor_id)->   where('role_id',2)->first();
                if(!$profesor){
                    return response()->json(['error' => 'Profesor no encontrado'], 409);
                }
    
                $professorByCourse = new ProfessorByCourse();
                $professorByCourse->course_id = $course->id;
                $professorByCourse->professor_id = $professor_id;
                $professorByCourse->save();
            }

            
            if(isset($data['custom_fields'])){
                $aux_custom_fields = json_decode($data['custom_fields'],true);
                if(count($aux_custom_fields) > 0){
                foreach($aux_custom_fields as $custom_field){
                        $course_custom_field = new CoursesCustomField();
                        $course_custom_field->name = $custom_field['name'];
                        $course_custom_field->value = $custom_field['value'];
                        $course_custom_field->course_id = $course->id;
                        $course_custom_field->save();
                    }
                }
            }

            $course_rta = Courses::with(['professors','custom_fields'])->find($course->id);

            return response()->json(['message' => 'Curso creado correctamente', 'data' => $course_rta ], 200);
        }

        return response()->json(['error' => 'Error al crear el curso'], 500);

    }


    /**
     * Create masterclass (course + lesson in one request)
     *
     * @param $provider
     * @return JsonResponse
     */
    public function createMasterclass(Request $request)
    {
        $data = $request->all();

        $validator = validator($data, [
            // Course fields
            'title' => 'required',
            'description' => 'required',
            'price_ars' => 'required',
            'price_usd' => 'required',
            'active' => 'required|integer',
            'category_id' => 'required',
            'photo_file' => 'required',
            'starting_date' => 'required',
            'inscription_date' => 'required',
            'objective' => 'required',
            'presentation' => 'required',
            'professors' => 'required',
            'academic_duration' => 'required',
            'comission' => 'nullable|numeric',
            'video_preview_url' => 'nullable',
            'recorded_course' => 'nullable|boolean',
            // Lesson fields
            'lesson_name' => 'required',
            'lesson_professor_id' => 'required',
            'lesson_active' => 'nullable|integer',
            'lesson_video_url' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        #validate if course already exists
        $course = Courses::where('title',$data['title'])->first();
        if($course){
            return response()->json(['error' => 'Curso ya existe'], 409);
        }

        #validate if photo is a valid image
        if(!UploadServer::validateImage($data['photo_file'])){
            return response()->json(['error' => 'El archivo no es una imagen'], 409);
        }

        $upload = UploadServer::uploadImage($data['photo_file'],'images');
        if(!$upload){
            return response()->json(['error' => 'Error al subir la imagen'], 500);
        }

        if(count($data['professors']) == 0){
            return response()->json(['error' => 'Debe haber al menos un profesor'], 409);
        }

        #validate if starting date is greater than inscription date
        if(strtotime($data['starting_date']) < strtotime($data['inscription_date'])){
            return response()->json(['error' => 'La fecha de inicio no puede ser menor que la fecha de inscripcion'], 409);
        }

        if(isset($data['asociation_file'])){
            if(!UploadServer::validateImage($data['asociation_file'])){
                return response()->json(['error' => 'El archivo no es un archivo'], 409);
            }
            $upload_asociation = UploadServer::uploadImage($data['asociation_file'],'asociations');
            if(!$upload_asociation){
                return response()->json(['error' => 'Error al subir la imagen'], 500);
            }
        }else{
            $upload_asociation = null;
        }

        #validate lesson professor
        $lessonProfessor = User::where('id',$data['lesson_professor_id'])->where('role_id',2)->first();
        if(!$lessonProfessor){
            return response()->json(['error' => 'Profesor de leccion no encontrado'], 409);
        }

        $subtitle = isset($data['subtitle']) ? $data['subtitle'] : null;
        $destined_to = isset($data['destined_to']) ? $data['destined_to'] : null;
        $certifications = isset($data['certifications']) ? $data['certifications'] : null;

        #create course with masterclass = true
        $course = new Courses();
        $course->title = $data['title'];
        $course->description = $data['description'];
        $course->price_ars = $data['price_ars'];
        $course->price_usd = $data['price_usd'];
        $course->active = $data['active'];
        $course->category_id = $data['category_id'];
        $course->photo_url = $upload;
        $course->starting_date = $data['starting_date'];
        $course->inscription_date = $data['inscription_date'];
        $course->objective = $data['objective'];
        $course->presentation = $data['presentation'];
        $course->asociation_path = $upload_asociation;
        $course->subtitle = $subtitle;
        $course->destined_to = $destined_to;
        $course->certifications = $certifications;
        $course->academic_duration = $data['academic_duration'];
        $course->comission = $data['comission'] ?? 100;
        $course->video_preview_url = isset($data['video_preview_url']) ? $data['video_preview_url'] : null;
        $course->recorded_course = isset($data['recorded_course']) ? $data['recorded_course'] : false;
        $course->masterclass = true;

        if(!$course->save()){
            return response()->json(['error' => 'Error al crear el curso'], 500);
        }

        #create professors
        foreach($data['professors'] as $professor_id){
            $profesor = User::where('id',$professor_id)->where('role_id',2)->first();
            if(!$profesor){
                ProfessorByCourse::where('course_id',$course->id)->delete();
                $course->delete();
                return response()->json(['error' => 'Profesor no encontrado'], 409);
            }
            $professorByCourse = new ProfessorByCourse();
            $professorByCourse->course_id = $course->id;
            $professorByCourse->professor_id = $professor_id;
            $professorByCourse->save();
        }

        #create custom fields if provided
        if(isset($data['custom_fields'])){
            $aux_custom_fields = json_decode($data['custom_fields'],true);
            if(count($aux_custom_fields) > 0){
                foreach($aux_custom_fields as $custom_field){
                    $course_custom_field = new CoursesCustomField();
                    $course_custom_field->name = $custom_field['name'];
                    $course_custom_field->value = $custom_field['value'];
                    $course_custom_field->course_id = $course->id;
                    $course_custom_field->save();
                }
            }
        }

        #create lesson linked to this course
        $lesson = new Lessons();
        $lesson->course_id = $course->id;
        $lesson->name = $data['lesson_name'];
        $lesson->description = $data['description'];
        $lesson->active = isset($data['lesson_active']) ? $data['lesson_active'] : 1;
        $lesson->video_url = isset($data['lesson_video_url']) ? $data['lesson_video_url'] : null;
        $lesson->professor_id = $lessonProfessor->id;
        $lesson->zoom_meeting_id = 0;
        $lesson->zoom_passcode = 0;

        if(!$lesson->save()){
            ProfessorByCourse::where('course_id',$course->id)->delete();
            CoursesCustomField::where('course_id',$course->id)->delete();
            $course->delete();
            return response()->json(['error' => 'Error al crear la leccion'], 500);
        }

        #handle lesson materials
        $materials = $request->file('lesson_materials');
        try{
            if ($materials) {
                if (!is_array($materials)) {
                    $materials = [$materials];
                }
                foreach ($materials as $file) {
                    if (!$file || !$file->isValid()) {
                        continue;
                    }
                    if ($file->getSize() === 0) {
                        continue;
                    }
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
            Materials::where('lesson_id',$lesson->id)->delete();
            $lesson->delete();
            ProfessorByCourse::where('course_id',$course->id)->delete();
            CoursesCustomField::where('course_id',$course->id)->delete();
            $course->delete();
            return response()->json(['error' => 'Error al subir los materiales', 'data' => $e->getMessage()], 500);
        }

        $course_rta = Courses::with(['professors','custom_fields','lessons.materials','lessons.professor'])->find($course->id);

        return response()->json(['message' => 'Masterclass creada correctamente', 'data' => $course_rta ], 200);
    }


    /**
     * Update masterclass (course + lesson in one request)
     *
     * @param $provider
     * @return JsonResponse
     */
    public function updateMasterclass(Request $request,$course_id)
    {
        $data = $request->all();

        $validator = validator($data, [
            // Course fields
            'title' => 'required',
            'description' => 'required',
            'price_ars' => 'required',
            'price_usd' => 'required',
            'active' => 'required|integer',
            'category_id' => 'required',
            'starting_date' => 'required',
            'inscription_date' => 'required',
            'objective' => 'required',
            'presentation' => 'required',
            'professors' => 'required',
            'academic_duration' => 'required',
            'comission' => 'nullable|numeric',
            'video_preview_url' => 'nullable',
            'recorded_course' => 'nullable|boolean',
            // Lesson fields
            'lesson_name' => 'required',
            'lesson_professor_id' => 'required',
            'lesson_active' => 'nullable|integer',
            'lesson_video_url' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        if(count($data['professors']) == 0){
            return response()->json(['error' => 'Debe haber al menos un profesor'], 409);
        }

        #validate if course exists
        $course = Courses::where('id',$course_id)->first();
        if(!$course){
            return response()->json(['error' => 'El curso no existe'], 409);
        }

        #update photo if provided
        if(isset($data['photo_file'])){
            if(!is_null($data['photo_file'])){
                $upload = UploadServer::uploadImage($data['photo_file'],'images');
                if(!$upload){
                    return response()->json(['error' => 'Error al subir la imagen'], 500);
                }
                $course->photo_url = $upload;
            }
        }

        #update asociation file if provided
        if(isset($data['asociation_file'])){
            if(!UploadServer::validateImage($data['asociation_file'])){
                return response()->json(['error' => 'El archivo no es un archivo'], 409);
            }
            $upload = UploadServer::uploadImage($data['asociation_file'],'asociations');
            if(!$upload){
                return response()->json(['error' => 'Error al subir la imagen'], 500);
            }
            $course->asociation_path = $upload;
        }

        #validate lesson professor
        $lessonProfessor = User::where('id',$data['lesson_professor_id'])->where('role_id',2)->first();
        if(!$lessonProfessor){
            return response()->json(['error' => 'Profesor de leccion no encontrado'], 409);
        }

        $subtitle = isset($data['subtitle']) ? $data['subtitle'] : null;
        $destined_to = isset($data['destined_to']) ? $data['destined_to'] : null;
        $certifications = isset($data['certifications']) ? $data['certifications'] : null;

        #update course fields
        $course->title = $data['title'];
        $course->description = $data['description'];
        $course->price_ars = $data['price_ars'];
        $course->price_usd = $data['price_usd'];
        $course->active = $data['active'];
        $course->category_id = $data['category_id'];
        $course->starting_date = $data['starting_date'];
        $course->inscription_date = $data['inscription_date'];
        $course->objective = $data['objective'];
        $course->presentation = $data['presentation'];
        $course->subtitle = $subtitle;
        $course->destined_to = $destined_to;
        $course->certifications = $certifications;
        $course->academic_duration = $data['academic_duration'];
        $course->comission = $data['comission'] ?? 100;
        $course->video_preview_url = isset($data['video_preview_url']) ? $data['video_preview_url'] : null;
        $course->recorded_course = isset($data['recorded_course']) ? $data['recorded_course'] : false;
        $course->masterclass = true;

        #update professors
        foreach($data['professors'] as $professor_id){
            $profesor = User::where('id',$professor_id)->where('role_id',2)->first();
            if(!$profesor){
                return response()->json(['error' => 'Profesor no encontrado'], 409);
            }
            $professorByCourse = ProfessorByCourse::where('course_id',$course_id)->where('professor_id',$professor_id)->first();
            if(!$professorByCourse){
                $professorByCourse = new ProfessorByCourse();
                $professorByCourse->course_id = $course_id;
                $professorByCourse->professor_id = $professor_id;
                $professorByCourse->save();
            }
        }
        ProfessorByCourse::where('course_id',$course_id)->whereNotIn('professor_id',$data['professors'])->delete();

        #update custom fields
        if(isset($data['custom_fields'])){
            $aux_custom_fields = json_decode($data['custom_fields'],true);
            CoursesCustomField::where('course_id',$course_id)->delete();
            if(count($aux_custom_fields) > 0){
                foreach($aux_custom_fields as $custom_field){
                    $course_custom_field = new CoursesCustomField();
                    $course_custom_field->name = $custom_field['name'];
                    $course_custom_field->value = $custom_field['value'];
                    $course_custom_field->course_id = $course_id;
                    $course_custom_field->save();
                }
            }
        }

        if(!$course->save()){
            return response()->json(['error' => 'Error al actualizar el curso'], 500);
        }

        #update lesson (get first lesson of this masterclass)
        $lesson = Lessons::where('course_id',$course_id)->first();
        if(!$lesson){
            #create lesson if it doesn't exist
            $lesson = new Lessons();
            $lesson->course_id = $course_id;
        }

        $lesson->name = $data['lesson_name'];
        $lesson->description = $data['description'];
        $lesson->active = isset($data['lesson_active']) ? $data['lesson_active'] : 1;
        $lesson->video_url = isset($data['lesson_video_url']) ? $data['lesson_video_url'] : null;
        $lesson->professor_id = $lessonProfessor->id;
        $lesson->zoom_meeting_id = 0;
        $lesson->zoom_passcode = 0;

        if(!$lesson->save()){
            return response()->json(['error' => 'Error al actualizar la leccion'], 500);
        }

        #handle lesson materials
        $new_materials = $request->file('lesson_new_materials');
        $existing_materials = $request->input('lesson_materials');
        $array_ids = [];

        if ($new_materials) {
            try{
                if (!is_array($new_materials)) {
                    $new_materials = [$new_materials];
                }
                foreach ($new_materials as $file) {
                    if (!$file || !$file->isValid()) {
                        continue;
                    }
                    if ($file->getSize() === 0) {
                        continue;
                    }
                    $path = UploadServer::uploadFile($file,'lessons/'. $lesson->id.'/materials');
                    $material = new Materials();
                    $material->lesson_id = $lesson->id;
                    $material->file_path = $path;
                    $material->name = $file->getClientOriginalName();
                    $material->active = 1;
                    $material->save();
                    $array_ids[] = $material->id;
                }
            }catch(\Exception $e){
                return response()->json(['error' => 'Error al subir los materiales', 'data' => $e->getMessage()], 500);
            }
        }

        if($existing_materials){
            foreach($existing_materials as $material){
                $array_ids[] = $material['id'];
            }
        }
        Materials::where('lesson_id',$lesson->id)->whereNotIn('id',$array_ids)->delete();

        $course_rta = Courses::with(['professors','custom_fields','lessons.materials','lessons.professor'])->find($course->id);

        return response()->json(['message' => 'Masterclass actualizada correctamente', 'data' => $course_rta ], 200);
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
            'title' => 'required',
            'description' => 'required',
            'price_ars' => 'required',
            'price_usd' => 'required',
            'active' => 'required|integer',
            'category_id' => 'required',
            'starting_date' => 'required',
            'inscription_date' => 'required',
            'objective' => 'required',
            'presentation' => 'required',
            'professors' => 'required',
            'academic_duration' => 'required',
            'comission' => 'nullable|numeric',
            'video_preview_url' => 'nullable',
            'recorded_course' => 'nullable|boolean',
            'masterclass' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        if(count($data['professors']) == 0){
            return response()->json(['error' => 'Debe haber al menos un profesor'], 409);
        }


        #validate if course already exists
        $course = Courses::where('id',$course_id)->first();
        if(!$course){
            return response()->json(['error' => 'El curso no existe'], 409);
        }

        if(isset($data['photo_file'])){
            if(!is_null($data['photo_file'])){
                
                $upload = UploadServer::uploadImage($data['photo_file'],'images');
                if(!$upload){
                    return response()->json(['error' => 'Error al subir la imagen'], 500);
                } 
                $course->photo_url = $upload;
            }
        }


        if(isset($data['asociation_file'])){
            if(!UploadServer::validateImage($data['asociation_file'])){
                return response()->json(['error' => 'El archivo no es un archivo'], 409);
            }
    
            $upload = UploadServer::uploadImage($data['asociation_file'],'asociations');
            
            if(!$upload){
                return response()->json(['error' => 'Error al subir la imagen'], 500);
            } 

            $course->asociation_path = $upload;
        }

        $subtitle = isset($data['subtitle']) ? $data['subtitle'] : null;
        $destined_to = isset($data['destined_to']) ? $data['destined_to'] : null;
        $certifications = isset($data['certifications']) ? $data['certifications'] : null;  
  
        $course->title = $data['title'];
        $course->description = $data['description'];
        $course->price_ars = $data['price_ars'];
        $course->price_usd = $data['price_usd'];
        $course->active = $data['active'];
        $course->category_id = $data['category_id'];
        $course->starting_date = $data['starting_date'];
        $course->inscription_date = $data['inscription_date'];
        $course->objective = $data['objective'];
        $course->presentation = $data['presentation'];
        $course->subtitle = $subtitle;
        $course->destined_to = $destined_to;
        $course->certifications = $certifications;
        $course->academic_duration = $data['academic_duration'];
        $course->comission = $data['comission'] ?? 100;
        $course->video_preview_url = isset($data['video_preview_url']) ? $data['video_preview_url'] : null;
        $course->recorded_course = isset($data['recorded_course']) ? $data['recorded_course'] : false;
        $course->masterclass = isset($data['masterclass']) ? $data['masterclass'] : false;

        #check if course is active
        if(isset($data['active'])){
            $course->active = $data['active'];
        }

        foreach($data['professors'] as $professor_id){
            #validate if profesor_id is a valid user
            $profesor = User::where('id',$professor_id)->where('role_id',2)->first();
            if(!$profesor){
                return response()->json(['error' => 'Profesor no encontrado'], 409);
            }

            #check if professor is already in the course
            $professorByCourse = ProfessorByCourse::where('course_id',$course_id)->where('professor_id',$professor_id)->first();
            if(!$professorByCourse){
                $professorByCourse = new ProfessorByCourse();
                $professorByCourse->course_id = $course_id;
                $professorByCourse->professor_id = $professor_id;
                $professorByCourse->save();
            }



            if(isset($data['custom_fields'])){
                $aux_custom_fields = json_decode($data['custom_fields'],true);
                $course_custom_fields = CoursesCustomField::where('course_id',$course_id)->delete();
                if(count($aux_custom_fields) > 0){
                    foreach($aux_custom_fields as $custom_field){
                        $course_custom_field = new CoursesCustomField();
                        $course_custom_field->name = $custom_field['name'];
                        $course_custom_field->value = $custom_field['value'];
                        $course_custom_field->course_id = $course_id;
                        $course_custom_field->save();
                    }
                }
            }
        }

        #delete professors that are not in the request
        $professors = ProfessorByCourse::where('course_id',$course_id)->whereNotIn('professor_id',$data['professors'])->delete();   
        
        if($course->save()){

            $course_rta = Courses::with(['professors','custom_fields'])->find($course->id);
       
            return response()->json(['message' => 'Curso actualizado correctamente', 'data' => $course_rta ], 200);
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

        if($course->delete()){

            #delete all lessons workshops etc
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
      
        $list = Courses::with(['category','professors','custom_fields'])->get();
      
        return response()->json(['data' => $list], 200);
    }

    /**
     * Listado de cursos
     *
     * @param $provider
     * @return JsonResponse
     */
    public function getCourse(Request $request,$course_id)
    {   
        
        $list = Courses::with(['category','professors','lessons.materials','lessons.professor','workshops.professor','exams','inscriptions.student','custom_fields'])->find($course_id);
      
        return response()->json(['data' => $list], 200);
    }


    /**
     * Listado de lecciones de un curso
     *
     * @param $provider
     * @return JsonResponse
     */
    public function getLessonsByCourse(Request $request,$course_id)
    {   


        $list = Lessons::with(['materials','professor'])->where('course_id',$course_id)->orderBy('date','asc')->get();

        return response()->json(['data' => $list], 200);
    }

    /**
     * Listado de lecciones de un curso
     *
     * @param $provider
     * @return JsonResponse
     */
    public function getExamsByCourse(Request $request,$course_id)
    {   

        $list = Exams::where('course_id',$course_id)->get();
        return response()->json(['data' => $list], 200);
    }

    /**
     * Listado de lecciones de un curso
     *
     * @param $provider
     * @return JsonResponse
     */
    public function getWorkshopsByCourse(Request $request,$course_id)
    {   

        $list = Workshops::with(['materials','professor'])->where('course_id',$course_id)->get();
      
        return response()->json(['data' => $list], 200);
    }

    /**
     * Agregar un estudiante a un curso 
     *
     * @param $provider
     * @return JsonResponse
     */
    public function addStudent(Request $request,$course_id)
    {   
        $data = $request->all();
        #validate if course already exists
        $course = Courses::where('id',$course_id)->first();
        if(!$course){
            return response()->json(['error' => 'El curso no existe'], 409);
        }

        #validate if student already exists
        $student = User::where('id',$data['user_id'])->where('role_id',3)->first();
        if(!$student){
            return response()->json(['error' => 'Estudiante no encontrado'], 409);
        }

        #validate if student is already inscripted
        $inscription = DB::table('inscriptions')->where('user_id',$data['user_id'])->where('course_id',$course_id)->first();
        
        if($inscription){
            return response()->json(['error' => 'Estudiante ya inscripto'], 409);
        }

        $inscription = DB::table('inscriptions')->insert(
            ['user_id' => $data['user_id'], 'course_id' => $course_id,'with_workshop' => $data['with_workshop'], 'scholarship' => true]
        );

        if($inscription){
            return response()->json(['message' => 'Estudiante inscripto correctamente','data' => $inscription], 200);
        }

        return response()->json(['error' => 'Error al inscribir al estudiante'], 500);
    }
   
   
   
    /**
     * Obtener los estudiantes de un curso
     *
     * @param $provider
     * @return JsonResponse
     */
    public function getStudents(Request $request,$course_id)
    {   
       
        $course = Courses::where('id',$course_id)->first();
        if(!$course){
            return response()->json(['error' => 'El curso no existe'], 409);
        }

        #get students from inscriptions
        $students = DB::table('inscriptions')->where('course_id',$course_id)->get();
        $studentsList = [];
        foreach($students as $student){
            $studentData = User::where('id',$student->user_id)->first();
            $studentData->with_workshop = $student->with_workshop;
            $studentData->scholarship = $student->scholarship;
            $studentsList[] = $studentData;
        }

        // Check if download parameter is set
        $download = $request->input('download', 0);
        
        if($download == 1) {
            // Generate CSV
            $courseName = str_replace([' ', '/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $course->title);
            $filename = $courseName . '_estudiantes.csv';
            
            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0',
                'Pragma' => 'public',
            ];
            
            $callback = function() use ($studentsList) {
                $file = fopen('php://output', 'w');
                
                // Add BOM for UTF-8
                fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
                
                // CSV Headers
                fputcsv($file, ['name', 'lastname', 'email', 'dob', 'telephone', 'with_workshop', 'scholarship'], ';');
                
                // CSV Data
                foreach($studentsList as $student) {
                    fputcsv($file, [
                        $student->name,
                        $student->lastname,
                        $student->email,
                        $student->dob,
                        $student->telephone,
                        $student->with_workshop ? 'Sí' : 'No',
                        $student->scholarship ? 'Sí' : 'No'
                    ], ';');
                }
                
                fclose($file);
            };
            
            return response()->stream($callback, 200, $headers);
        }

        return response()->json(['data' => $studentsList], 200);
        
    }
    
 
    /**
     * Elimina a un estudiante de un curso
     *
     * @param $provider
     * @return JsonResponse
     */
    public function removeStudent(Request $request,$course_id,$student_id)
    {   
        $course = Courses::where('id',$course_id)->first();
        if(!$course){
            return response()->json(['error' => 'El curso no existe'], 409);
        }

        $student = User::where('id',$student_id)->where('role_id',3)->first();
        if(!$student){
            return response()->json(['error' => 'Estudiante no encontrado'], 409);
        }

        $inscription = DB::table('inscriptions')->where('user_id',$student_id)->where('course_id',$course_id)->first();
        
        if(!$inscription){
            return response()->json(['error' => 'Estudiante no inscripto'], 409);
        }

        $inscription = DB::table('inscriptions')->where('user_id',$student_id)->where('course_id',$course_id)->delete();
        
        if($inscription){
            return response()->json(['message' => 'Estudiante eliminado correctamente'], 200);
        }

        return response()->json(['error' => 'Error al eliminar al estudiante'], 500);
    }
    

    /**
     * obtiene los cursos para el usuario
     *
     * @param $provider
     * @return JsonResponse
     */
    public function listCourses(Request $request)
    {   
       
        $accessToken = TokenManager::getTokenFromRequest();

        if(is_null($accessToken)){
            $list = Courses::with(['category','professors','lessons.professor','workshops.professor','custom_fields'])->where('masterclass', 0)->orderBy('id','desc')->get();
            foreach($list as $course){
                foreach ($course->lessons as $lesson) {
                    $lesson->video_url = null;
                    $lesson->zoom_meeting_id = null;
                    $lesson->zoom_passcode = null;
                    unset($lesson->materials);
                }
                foreach ($course->workshops as $workshop) {
                    $workshop->video_url = null;
                    $workshop->zoom_meeting_id = null;
                    $workshop->zoom_passcode = null;
                    unset($workshop->materials);
                }
            }
        }else{
            $user = TokenManager::getUserFromToken($accessToken);
            #order by id desc
            $list = Courses::with(['category','professors','lessons.professor','workshops.professor','custom_fields'])
            ->select('courses.*', 'inscriptions.id as inscribed')
            ->leftJoin('inscriptions', function($join) use ($user) {
                $join->on('courses.id', '=', 'inscriptions.course_id')
                     ->where('inscriptions.user_id', $user->id);
            })
            ->where('courses.masterclass', 0)
            ->orderBy('courses.id', 'desc')
            ->get();

            foreach($list as $course){
                if(!$course->inscribed){
                    foreach ($course->lessons as $lesson) {
                        $lesson->video_url = null;
                        $lesson->zoom_meeting_id = null;
                        $lesson->zoom_passcode = null;
                        unset($lesson->materials);
                    }
                    foreach ($course->workshops as $workshop) {
                        $workshop->video_url = null;
                        $workshop->zoom_meeting_id = null;
                        $workshop->zoom_passcode = null;
                        unset($workshop->materials);
                    }
                }
            }
        }



    

        return response()->json(['data' => $list], 200);
    }


      /**
     * obtiene un curso
     *
     * @param $provider
     * @return JsonResponse
     */
    public function listCourse(Request $request,$course_id)
    {   
        $list = Courses::with(['category','professors','lessons.professor','workshops.professor','custom_fields'])->find($course_id);

        $accessToken = TokenManager::getTokenFromRequest();
        if(!is_null($accessToken)){
            $user = TokenManager::getUserFromToken($accessToken);
            $inscription = DB::table('inscriptions')->where('user_id',$user->id)->where('course_id',$course_id)->first();
            if(!$inscription){
                foreach ($list->lessons as $lesson) {
                    $lesson->video_url = null;
                    $lesson->zoom_meeting_id = null;
                    $lesson->zoom_passcode = null;
                    unset($lesson->materials);
                }
                foreach ($list->workshops as $workshop) {
                    $workshop->video_url = null;
                    $workshop->zoom_meeting_id = null;
                    $workshop->zoom_passcode = null;
                    unset($workshop->materials);
                }
                $list->inscribed_workshop = 0;
            }else{
                
                $lessons = Lessons::where('course_id',$course_id)->orderBy('date','asc')->get();
                $lessons_ids = [];
                foreach($lessons as $lesson){
                    $lessons_ids[] = $lesson->id;
                }
                $viewed_lessons = DB::table('view_lesson')->where('user_id',$user->id)->whereIn('lesson_id',$lessons_ids)->get();
                if(count($viewed_lessons) == count($lessons)){
                    $list->all_lessons_viewed = 1;
                }else{
                    $list->all_lessons_viewed = 0;
                }

                $list->inscribed_workshop = $inscription->with_workshop;

                return response()->json(['data' => $list], 200);
            }
        }else{
            foreach ($list->lessons as $lesson) {
                $lesson->video_url = null;
                $lesson->zoom_meeting_id = null;
                $lesson->zoom_passcode = null;
                unset($lesson->materials);
            }
            foreach ($list->workshops as $workshop) {
                $workshop->video_url = null;
                $workshop->zoom_meeting_id = null;
                $workshop->zoom_passcode = null;
                unset($workshop->materials);
            }
        }
        return response()->json(['data' => $list], 200);
      

    }


    

     /**
     * obtiene los lecciones de un curso
     *
     * @param $provider
     * @return JsonResponse
     */
    public function listLessons(Request $request,$course_id)
    {   
       
        $accessToken = TokenManager::getTokenFromRequest();

        if(is_null($accessToken)){
            $list = Lessons::with(['materials','professor'])->where('course_id',$course_id)->orderBy('date','asc')->get();
            foreach($list as $lesson){
                $lesson->video_url = null;
                $lesson->zoom_meeting_id = null;
                $lesson->zoom_passcode = null;
                foreach ($lesson->materials as $m) {
                    $m->file_path = null;
                    $m->file_path_url = null;
                }
            }
        }else{
            $user = TokenManager::getUserFromToken($accessToken);
            $inscription = DB::table('inscriptions')->where('user_id',$user->id)->where('course_id',$course_id)->first();
            if(!$inscription){
                $list = Lessons::with(['materials','professor'])->where('course_id',$course_id)->orderBy('date','asc')->get();
                foreach($list as $lesson){
                    $lesson->video_url = null;
                    $lesson->zoom_meeting_id = null;
                    $lesson->zoom_passcode = null;
                    foreach ($lesson->materials as $m) {
                        $m->file_path = null;
                        $m->file_path_url = null;
                    }
                }
                return response()->json(['data' => $list], 200);
            }

            $list = Lessons::with(['materials','professor','exam'])
            ->leftJoin('view_lesson', function($join) use ($user) {
                $join->on('lessons.id', '=', 'view_lesson.lesson_id')
                    ->where('view_lesson.user_id', '=', $user->id);
            })
            ->select('lessons.*', DB::raw('COALESCE(view_lesson.id, 0) as viewed'))
            ->where('course_id', $course_id)
            ->orderBy('lessons.date','asc')
            ->get();

            foreach($list as $lesson){
                if($lesson->exam){
                    $exam_result = Results::where('user_id',$user->id)->where('exam_id',$lesson->exam->id)->first();
                    if($exam_result){
                        if($exam_result->final_grade >= 6){
                            $lesson->exam->approved = 1;
                        }else{
                            $lesson->exam->approved = 0;
                        }
                    }else{
                        $lesson->exam->approved = 0;
                    }
                }
            }
        }


        return response()->json(['data' => $list], 200);
    }


    
     /**
     * obtiene los examenes de un curso
     *
     * @param $provider
     * @return JsonResponse
     */
    public function listExams(Request $request,$course_id)
    {   
       
        $accessToken = TokenManager::getTokenFromRequest();

        if(is_null($accessToken)){          
            $list = Exams::where('course_id',$course_id)->where('active',1)->get();
        }else{
            $user = TokenManager::getUserFromToken($accessToken);    

            $inscription = DB::table('inscriptions')->where('user_id',$user->id)->where('course_id',$course_id)->first();            
            if(!$inscription){
                $list = Exams::where('course_id',$course_id)->where('active',1)->get();
                
                return response()->json(['data' => $list], 200);
            }


            $list = Exams::leftJoin('exams_results', function($join) use ($user) {
                $join->on('exams.id', '=', 'exams_results.exam_id')
                    ->where('exams_results.user_id', '=', $user->id);
            })
            ->select('exams.*', DB::raw('CASE WHEN exams_results.final_grade >= 6 THEN 1 ELSE 0 END as approved'))
            ->where('course_id', $course_id)->where('active',1)->get();

          /*   $lessons = Lessons::where('course_id',$course_id)->get();
            $lessons_ids = [];
            foreach($lessons as $lesson){
                $lessons_ids[] = $lesson->id;
            }
            
            $list_2 = Exams::leftJoin('exams_results', function($join) use ($user) {
                $join->on('exams.id', '=', 'exams_results.exam_id')
                    ->where('exams_results.user_id', '=', $user->id);
            })
            ->select('exams.*', DB::raw('CASE WHEN exams_results.final_grade > 6 THEN 1 ELSE 0 END as approved'))
            ->whereIn('lesson_id',$lessons_ids)->where('active',1)->get();

            #merge lists
            $aux = array_merge($list->toArray(), $list_2->toArray());
            $list = collect($aux); */

        }

        return response()->json(['data' => $list], 200);
    }
    
    
     /**
     * obtiene los workshops de un curso
     *
     * @param $provider
     * @return JsonResponse
     */
    public function listWorkshops(Request $request,$course_id)
    {   
       
        $accessToken = TokenManager::getTokenFromRequest();

        if(is_null($accessToken)){
            $list = Workshops::with(['professor'])->where('course_id',$course_id)->where('active',1)->get();
            foreach ($list as $w) {
                $w->video_url = null;
            }
            return response()->json(['data' => $list], 200);
        }else{

            $user = TokenManager::getUserFromToken($accessToken);
            $inscription = DB::table('inscriptions')->where('user_id',$user->id)->where('course_id',$course_id)->where('with_workshop',1)->first();
            if(!$inscription){
                $list = Workshops::where('course_id',$course_id)->where('active',1)->get();

                foreach ($list as $w) {
                    $w->video_url = null;
                }
      
                return response()->json(['data' => $list], 200);
            }
          
            $list = Workshops::with(['materials','professor'])->where('course_id',$course_id)->where('active',1)->get();
      
            return response()->json(['data' => $list], 200);
        }
    }
    



    
}