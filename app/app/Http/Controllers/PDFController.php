<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\{Exams,User,Results,Courses};

class PDFController extends Controller
{
    public function generatePDF(Request $request)
    {

        $data = $request->all();

        #Validate if student approved all the exams of the course
        $student = User::where('id',$data['user_id'])->first();
        $exams = Exams::where('course_id',$data['course_id'])->get();


        foreach ($exams as $exam) {
            $result = Results::where('user_id', $student->id)->where('exam_id', $exam->id)->first();

            if(!$result || !$result->approved ){
                return response()->json(['message' => 'No aprobaste todos los examenes del curso'], 409);
            }
        }

        $course = Courses::where('id',$data['course_id'])->first();

        $data = [
            'title' => $course->title,
            'date' => date('m/d/Y'),
            'student' => $student->name,
            'course_data' => $course->description
        ];

        $pdf = PDF::loadView('myPDF', $data)->setPaper('a4', 'landscape');

        return $pdf->download('document.pdf');
    }
}