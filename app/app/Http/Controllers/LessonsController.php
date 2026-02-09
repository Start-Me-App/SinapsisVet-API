<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use App\Models\{Courses, User, Lessons,Materials,ViewLesson,Inscriptions};
use Illuminate\Support\Facades\Input;
use Carbon\Carbon;
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

        
        $zoom_meeting_id = isset($data['zoom_meeting_id']) ? $data['zoom_meeting_id'] : null;
        $zoom_passcode = isset($data['zoom_passcode']) ? $data['zoom_passcode'] : null;
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
        $lesson->zoom_meeting_id = (int)$zoom_meeting_id;
        $lesson->zoom_passcode = (int)$zoom_passcode;

        if(isset($data['date'])){
            $lesson->date = Carbon::parse($data['date'])->format('Y-m-d');
        }
        if(isset($data['time']) && isset($data['date'])){
            $lesson->time = Carbon::parse($data['date'].' '.$data['time'])->format('H:i:s');
        }

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
                    if ($materials) {
                        // Validar que materials sea un array
                        if (!is_array($materials)) {
                            $materials = [$materials];
                        }
                        
                        foreach ($materials as $file) {
                            // Validar que el archivo existe y es válido
                            if (!$file || !$file->isValid()) {
                                continue; // Saltar archivos inválidos
                            }
                            
                            // Validar que el archivo no esté vacío
                            if ($file->getSize() === 0) {
                                continue; // Saltar archivos vacíos
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

        if(isset($data['date'])){
            $lesson->date = Carbon::parse($data['date'])->format('Y-m-d');
        }
        if(isset($data['time']) && isset($data['date'])){
            $lesson->time = Carbon::parse($data['date'].' '.$data['time'])->format('H:i:s');
        }

        $zoom_meeting_id = isset($data['zoom_meeting_id']) ? $data['zoom_meeting_id'] : null;
        $zoom_passcode = isset($data['zoom_passcode']) ? $data['zoom_passcode'] : null;

        $lesson->name = $data['name'];
        $lesson->description = $data['description'];    
        $lesson->active = $data['active'];
        $lesson->video_url = isset($data['video_url']) ? $data['video_url'] : null;
        $lesson->zoom_meeting_id = (int)$zoom_meeting_id;
        $lesson->zoom_passcode = (int)$zoom_passcode;
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
                        // Validar que new_materials sea un array
                        if (!is_array($new_materials)) {
                            $new_materials = [$new_materials];
                        }
                        
                        foreach ($new_materials as $file) {
                            // Validar que el archivo existe y es válido
                        /*     if (!$file || !$file->isValid()) {
                                continue; // Saltar archivos inválidos
                            }
                            
                            // Validar que el archivo no esté vacío
                            if ($file->getSize() === 0) {
                                continue; // Saltar archivos vacíos
                            } */
                            
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

    /**
     * Obtener lista de asistencias de una lección
     *
     * Retorna los usuarios que ya marcaron asistencia
     *
     * @param Request $request
     * @param int $lesson_id
     * @return JsonResponse
     */
    public function getAttendances(Request $request, $lesson_id): JsonResponse
    {
        try {
            $lesson = Lessons::find($lesson_id);

            if (!$lesson) {
                return response()->json(['error' => 'Lección no encontrada'], 404);
            }

            // Obtener todas las asistencias con información del usuario
            $attendances = ViewLesson::with('user:id,name,email,telephone')
                ->where('lesson_id', $lesson_id)
                ->orderBy('created_at', 'desc')
                ->get();

            // Formatear la respuesta
            $formattedAttendances = $attendances->map(function ($attendance) {
                return [
                    'id' => $attendance->id,
                    'user_id' => $attendance->user_id,
                    'user_name' => $attendance->user->name ?? 'N/A',
                    'user_email' => $attendance->user->email ?? 'N/A',
                    'user_phone' => $attendance->user->telephone ?? 'N/A',
                    'marked_at' => $attendance->created_at,
                ];
            });

            return response()->json([
                'lesson_id' => $lesson_id,
                'lesson_name' => $lesson->name,
                'total_attendances' => $attendances->count(),
                'attendances' => $formattedAttendances
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener asistencias',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener lista de usuarios elegibles para una lección
     *
     * Retorna todos los usuarios inscritos en el curso,
     * indicando quiénes ya marcaron asistencia
     *
     * @param Request $request
     * @param int $lesson_id
     * @return JsonResponse
     */
    public function getEligibleStudents(Request $request, $lesson_id): JsonResponse
    {
        try {
            $lesson = Lessons::with('course')->find($lesson_id);

            if (!$lesson) {
                return response()->json(['error' => 'Lección no encontrada'], 404);
            }

            if (!$lesson->course) {
                return response()->json(['error' => 'Curso no encontrado para esta lección'], 404);
            }

            // Obtener todos los usuarios inscritos en el curso
            $inscriptions = Inscriptions::with('student:id,name,email,telephone')
                ->where('course_id', $lesson->course_id)
                ->get();

            // Obtener los IDs de usuarios que ya tienen asistencia marcada
            $attendedUserIds = ViewLesson::where('lesson_id', $lesson_id)
                ->pluck('user_id')
                ->toArray();

            // Formatear la lista de estudiantes
            $students = $inscriptions->map(function ($inscription) use ($attendedUserIds, $lesson_id) {
                $hasAttendance = in_array($inscription->user_id, $attendedUserIds);

                // Obtener el registro de asistencia si existe
                $attendanceRecord = null;
                if ($hasAttendance) {
                    $attendanceRecord = ViewLesson::where('lesson_id', $lesson_id)
                        ->where('user_id', $inscription->user_id)
                        ->first();
                }

                return [
                    'user_id' => $inscription->user_id,
                    'name' => $inscription->student->name ?? 'N/A',
                    'email' => $inscription->student->email ?? 'N/A',
                    'phone' => $inscription->student->telephone ?? 'N/A',
                    'has_attendance' => $hasAttendance,
                    'attendance_id' => $attendanceRecord ? $attendanceRecord->id : null,
                    'attended_at' => $attendanceRecord ? $attendanceRecord->created_at : null,
                ];
            });

            // Estadísticas
            $totalStudents = $students->count();
            $attendedCount = $students->where('has_attendance', true)->count();
            $missingCount = $totalStudents - $attendedCount;

            return response()->json([
                'lesson_id' => $lesson_id,
                'lesson_name' => $lesson->name,
                'course_id' => $lesson->course_id,
                'course_name' => $lesson->course->title ?? 'N/A',
                'statistics' => [
                    'total_students' => $totalStudents,
                    'attended' => $attendedCount,
                    'missing' => $missingCount,
                    'attendance_percentage' => $totalStudents > 0
                        ? round(($attendedCount / $totalStudents) * 100, 2)
                        : 0
                ],
                'students' => $students
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener estudiantes',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar asistencia de un usuario a una lección
     *
     * Permite a un admin marcar la asistencia de cualquier usuario
     *
     * @param Request $request
     * @param int $lesson_id
     * @return JsonResponse
     */
    public function markAttendance(Request $request, $lesson_id): JsonResponse
    {
        try {
            $validator = validator($request->all(), [
                'user_id' => 'required|integer|exists:users,id'
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }

            $lesson = Lessons::find($lesson_id);

            if (!$lesson) {
                return response()->json(['error' => 'Lección no encontrada'], 404);
            }

            $userId = $request->input('user_id');

            // Verificar que el usuario esté inscrito en el curso
            $inscription = Inscriptions::where('user_id', $userId)
                ->where('course_id', $lesson->course_id)
                ->first();

            if (!$inscription) {
                return response()->json([
                    'error' => 'El usuario no está inscrito en este curso'
                ], 403);
            }

            // Verificar si ya existe la asistencia
            $existingAttendance = ViewLesson::where('user_id', $userId)
                ->where('lesson_id', $lesson_id)
                ->first();

            if ($existingAttendance) {
                return response()->json([
                    'message' => 'La asistencia ya fue marcada previamente',
                    'data' => [
                        'id' => $existingAttendance->id,
                        'user_id' => $existingAttendance->user_id,
                        'lesson_id' => $existingAttendance->lesson_id,
                        'marked_at' => $existingAttendance->created_at
                    ]
                ], 200);
            }

            // Crear la asistencia
            $attendance = new ViewLesson();
            $attendance->user_id = $userId;
            $attendance->lesson_id = $lesson_id;
            $attendance->save();

            // Obtener información del usuario
            $user = User::select('id', 'name', 'email')->find($userId);

            return response()->json([
                'message' => 'Asistencia marcada correctamente',
                'data' => [
                    'id' => $attendance->id,
                    'user_id' => $attendance->user_id,
                    'user_name' => $user->name ?? 'N/A',
                    'user_email' => $user->email ?? 'N/A',
                    'lesson_id' => $attendance->lesson_id,
                    'lesson_name' => $lesson->name,
                    'marked_at' => $attendance->created_at
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al marcar asistencia',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar asistencia de un usuario a una lección
     *
     * Permite desmarcar la asistencia de un usuario
     *
     * @param Request $request
     * @param int $lesson_id
     * @param int $user_id
     * @return JsonResponse
     */
    public function removeAttendance(Request $request, $lesson_id, $user_id): JsonResponse
    {
        try {
            $lesson = Lessons::find($lesson_id);

            if (!$lesson) {
                return response()->json(['error' => 'Lección no encontrada'], 404);
            }

            $attendance = ViewLesson::where('lesson_id', $lesson_id)
                ->where('user_id', $user_id)
                ->first();

            if (!$attendance) {
                return response()->json([
                    'error' => 'No se encontró asistencia para este usuario en esta lección'
                ], 404);
            }

            $attendanceData = [
                'id' => $attendance->id,
                'user_id' => $attendance->user_id,
                'lesson_id' => $attendance->lesson_id,
                'marked_at' => $attendance->created_at
            ];

            $attendance->delete();

            return response()->json([
                'message' => 'Asistencia eliminada correctamente',
                'data' => $attendanceData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al eliminar asistencia',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar asistencia múltiple
     *
     * Permite marcar asistencia de varios usuarios a la vez
     *
     * @param Request $request
     * @param int $lesson_id
     * @return JsonResponse
     */
    public function markMultipleAttendances(Request $request, $lesson_id): JsonResponse
    {
        try {
            $validator = validator($request->all(), [
                'user_ids' => 'required|array',
                'user_ids.*' => 'integer|exists:users,id'
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }

            $lesson = Lessons::find($lesson_id);

            if (!$lesson) {
                return response()->json(['error' => 'Lección no encontrada'], 404);
            }

            $userIds = $request->input('user_ids');
            $results = [
                'created' => [],
                'already_exists' => [],
                'not_enrolled' => []
            ];

            foreach ($userIds as $userId) {
                // Verificar inscripción
                $inscription = Inscriptions::where('user_id', $userId)
                    ->where('course_id', $lesson->course_id)
                    ->first();

                if (!$inscription) {
                    $user = User::find($userId);
                    $results['not_enrolled'][] = [
                        'user_id' => $userId,
                        'user_name' => $user->name ?? 'N/A'
                    ];
                    continue;
                }

                // Verificar si ya existe
                $existingAttendance = ViewLesson::where('user_id', $userId)
                    ->where('lesson_id', $lesson_id)
                    ->first();

                if ($existingAttendance) {
                    $results['already_exists'][] = [
                        'user_id' => $userId,
                        'attendance_id' => $existingAttendance->id
                    ];
                    continue;
                }

                // Crear asistencia
                $attendance = new ViewLesson();
                $attendance->user_id = $userId;
                $attendance->lesson_id = $lesson_id;
                $attendance->save();

                $results['created'][] = [
                    'user_id' => $userId,
                    'attendance_id' => $attendance->id
                ];
            }

            return response()->json([
                'message' => 'Proceso de asistencias completado',
                'lesson_id' => $lesson_id,
                'lesson_name' => $lesson->name,
                'results' => $results,
                'summary' => [
                    'created' => count($results['created']),
                    'already_exists' => count($results['already_exists']),
                    'not_enrolled' => count($results['not_enrolled']),
                    'total_processed' => count($userIds)
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al marcar asistencias múltiples',
                'message' => $e->getMessage()
            ], 500);
        }
    }


}