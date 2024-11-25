<?php

namespace App\Http\Controllers;

use App\Support\UploadServer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use App\Models\{Courses, Lessons, User, Workshops,Exams};

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
        #TODO -> cambiar photo_url por photo y recibe un file
        $data = $request->all();        
        $validator = validator($data, [
            'title' => 'required',
            'description' => 'required',
            'profesor_id' => 'required',
            'price_ars' => 'required',
            'price_usd' => 'required',
            'active' => 'required|integer',
            'category_id' => 'required',
            'photo_file' => 'required',
            'starting_date' => 'required',
            'inscription_date' => 'required',
            'objective' => 'required',
            'presentation' => 'required'
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
        $course = Courses::where('title',$data['title'])->first();
        
        if($course){
            return response()->json(['error' => 'Curso ya existe'], 409);
        }


        $upload = UploadServer::uploadImage($data['photo_file'],'images');
        
        if(!$upload){
            return response()->json(['error' => 'Error al subir la imagen'], 500);
        } 

 
        $course = new Courses();
        $course->title = $data['title'];
        $course->description = $data['description'];
        $course->profesor_id = $data['profesor_id'];
        $course->price_ars = $data['price_ars'];
        $course->price_usd = $data['price_usd'];
        $course->active = $data['active'];
        $course->category_id = $data['category_id'];
        $course->photo_url = $upload;
        $course->starting_date = $data['starting_date'];
        $course->inscription_date = $data['inscription_date'];
        $course->objective = $data['objective'];
        $course->presentation = $data['presentation'];

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
            'title' => 'required',
            'description' => 'required',
            'profesor_id' => 'required',
            'price_ars' => 'required',
            'price_usd' => 'required',
            'active' => 'required|integer',
            'category_id' => 'required',
            'starting_date' => 'required',
            'inscription_date' => 'required',
            'objective' => 'required',
            'presentation' => 'required'
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

        if(isset($data['photo_file'])){
            if(!is_null($data['photo_file'])){
                
                $upload = UploadServer::uploadImage($data['photo_file'],'images');
                if(!$upload){
                    return response()->json(['error' => 'Error al subir la imagen'], 500);
                } 
                $course->photo_url = $upload;
            }
        }
  
        $course->title = $data['title'];
        $course->description = $data['description'];
        $course->profesor_id = $data['profesor_id'];
        $course->price_ars = $data['price_ars'];
        $course->price_usd = $data['price_usd'];
        $course->active = $data['active'];
        $course->category_id = $data['category_id'];
        $course->starting_date = $data['starting_date'];
        $course->inscription_date = $data['inscription_date'];
        $course->objective = $data['objective'];
        $course->presentation = $data['presentation'];
        
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

    /**
     * Listado de cursos
     *
     * @param $provider
     * @return JsonResponse
     */
    public function getCourse(Request $request,$course_id)
    {   
        
        $list = Courses::with(['profesor','category','lessons.materials','workshops','exams','inscriptions.student'])->find($course_id);
      
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


        $list = Lessons::with(['materials'])->where('course_id',$course_id)->get();

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

        $list = Workshops::where('course_id',$course_id)->get();
      
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
            ['user_id' => $data['user_id'], 'course_id' => $course_id,'with_workshop' => $data['with_workshop']]
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
            $studentsList[] = $studentData;
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
            $list = Courses::with(['profesor','category'])->get();
        }else{
            $user = TokenManager::getUserFromToken($accessToken);
    
            $list = Courses::with(['profesor'])
             ->select('courses.*', 'inscriptions.id as inscribed')
            ->leftJoin('inscriptions', 'courses.id', '=', 'inscriptions.course_id')
            ->where('courses.active', 1)
            ->where(function($query) use ($user) {
                $query->where('inscriptions.user_id', $user->id)
                    ->orWhereNull('inscriptions.user_id');
            })
            ->get();
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
        $list = Courses::with(['profesor','category'])->find($course_id);
      
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
            $list = Lessons::with(['materials'])->where('course_id',$course_id)->get();
            foreach($list as $lesson){
                $lesson->video_url = null;
                foreach ($lesson->materials as $m) {
                    $m->file_path = null;
                    $m->file_path_url = null;
                }
            }
        }else{
            $user = TokenManager::getUserFromToken($accessToken);
            $inscription = DB::table('inscriptions')->where('user_id',$user->id)->where('course_id',$course_id)->first();
            if(!$inscription){
                $list = Lessons::with(['materials'])->where('course_id',$course_id)->get();
                foreach($list as $lesson){
                    $lesson->video_url = null;
                    foreach ($lesson->materials as $m) {
                        $m->file_path = null;
                        $m->file_path_url = null;
                    }
                }
                return response()->json(['data' => $list], 200);
            }
          
            $list = Lessons::with(['materials'])
            ->leftJoin('view_lesson', function($join) use ($user) {
                $join->on('lessons.id', '=', 'view_lesson.lesson_id')
                    ->where('view_lesson.user_id', '=', $user->id);
            })
            ->select('lessons.*', DB::raw('COALESCE(view_lesson.id, 0) as viewed'))
            ->where('course_id', $course_id)
            ->get();
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
            }

            $list = Exams::leftJoin('exams_results', function($join) use ($user) {
                $join->on('exams.id', '=', 'exams_results.exam_id')
                    ->where('exams_results.user_id', '=', $user->id);
            })
            ->select('exams.*', DB::raw('CASE WHEN exams_results.final_grade > 6 THEN 1 ELSE 0 END as approved'))
            ->where('course_id', $course_id)->where('active',1)->get();
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
            $list = Workshops::where('course_id',$course_id)->where('active',1)->get();
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
          
            $list = Workshops::where('course_id',$course_id)->where('active',1)->get();
      
            return response()->json(['data' => $list], 200);
        }
    }
    



    
}