<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\{Exams,User,Results,Courses,Lessons,Workshops};
use App\Support\TokenManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PDFController extends Controller
{
    public function generateApprovedPdf(Request $request)
    {
        $accessToken = TokenManager::getTokenFromRequest();
        
        if(!$accessToken){
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = TokenManager::getUserFromToken($accessToken);


        $data = $request->all();

        $student = User::where('id',$user->id)->first();
        $exams = Exams::where('course_id',$data['course_id'])->get();


       foreach ($exams as $exam) {

            $result = Results::where('user_id', $student->id)->where('exam_id', $exam->id)->first();

            if(!$result){
                return response()->json(['message' => 'No aprobaste todos los examenes del curso'], 409);
            }


            if(!$exam->lesson_id && $result->approved){
                $date = $result->date;
            }

            if(!$result || !$result->approved ){
                return response()->json(['message' => 'No aprobaste todos los examenes del curso'], 409);
            }
        }  

        $course = Courses::where('id',$data['course_id'])->first();


       # $date = date('d/m/Y', strtotime($date));
        
        $data = [
            'title' => strtoupper($course->title),
            'subtitle' => $course->subtitle,
            'student' => $student->name.' '.$student->lastname,
            'type' => 'aprobado',
            'date' => $this->formatearFechaEscrita($course->date)
        ];

        return $data;

    }


    public function generateAssistancePdf(Request $request)
    {
        $accessToken = TokenManager::getTokenFromRequest();
        
        if(!$accessToken){
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = TokenManager::getUserFromToken($accessToken);

        $data = $request->all();

        $student = User::where('id',$user->id)->first();
        $course = Courses::where('id',$data['course_id'])->first();
        $list = Lessons::with(['materials','professor','exam'])
            ->leftJoin('view_lesson', function($join) use ($user) {
                $join->on('lessons.id', '=', 'view_lesson.lesson_id')
                    ->where('view_lesson.user_id', '=', $user->id);
            })
            ->select('lessons.*', DB::raw('COALESCE(view_lesson.id, 0) as viewed'))
            ->where('course_id', $data['course_id'])
            ->get();

            
        foreach ($list as $lesson) {
            if(!$lesson->viewed){
               # return response()->json(['message' => 'No viste todas las lecciones del curso'], 409);
            }
        } 



        $data = [
            'title' => $course->title,
            'subtitle' => $course->subtitle,
            'date' => $this->formatearFechaEscrita($course->date),
            'student' => $student->name .' '.$student->lastname,
            'course_data' => $course->description,
            'type' => 'asistido'
        ];

        return $data;

    }


    
    public function generateWorkshopPdf(Request $request)
    {
        $accessToken = TokenManager::getTokenFromRequest();
        
        if(!$accessToken){
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = TokenManager::getUserFromToken($accessToken);

        $data = $request->all();

        $student = User::where('id',$user->id)->first();
        $course = Courses::where('id',$data['course_id'])->first();
       
        $workshop = Workshops::where('course_id',$data['course_id'])->first();

        if(!$workshop){
            throw new \Exception('No hay taller para este curso');
        }

       


        $data = [
            'title' => strtoupper('Taller de '.$course->title),
            'date' => $this->formatearFechaEscrita($workshop->date),
            'student' => $student->name .' '.$student->lastname,
            'course_data' => $course->description,
            'subtitle' => $course->subtitle,
            'type' => 'asistido'
        ];

        return $data;
    }


    private function formatearFechaEscrita($fecha) {
        $meses = array(
            '01' => 'enero',
            '02' => 'febrero', 
            '03' => 'marzo',
            '04' => 'abril',
            '05' => 'mayo',
            '06' => 'junio',
            '07' => 'julio',
            '08' => 'agosto',
            '09' => 'septiembre',
            '10' => 'octubre',
            '11' => 'noviembre',
            '12' => 'diciembre'
        );
        
        $fecha = Carbon::parse($fecha);
        $dia = $fecha->format('d');
        $mes = $meses[$fecha->format('m')];
        $anio = $fecha->format('Y');
        
        return $dia . ' de ' . $mes . ' de ' . $anio;
    }
    


}