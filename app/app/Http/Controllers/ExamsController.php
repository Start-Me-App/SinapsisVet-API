<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use App\Models\{Courses, Exams, Questions,Answers,Results};


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


     /**
     * get exam 
     *
     * @param $provider
     * @return JsonResponse
     */
    public function getExam(Request $request,$exam_id)
    {
        $data = $request->all();
        
        $exam = Exams::with('questions.answers')->where('id',$exam_id)->first();
        if(!$exam){
            return response()->json(['error' => 'Examen no encontrado'], 404);
        }
       
        return response()->json(['data' => $exam], 500);
    }


    
     /**
     * add questions to exam 
     *
     * @param $provider
     * @return JsonResponse
     */
    public function addQuestion(Request $request,$exam_id)
    {
        $data = $request->all();
        $exam = Exams::find($exam_id);   
        
        if(!$exam){
            return response()->json(['error' => 'Examen no encontrado'], 404);
        }
       
        foreach ($data as $question) {
            
           if(!isset($question['question_title'])){
                return response()->json(['error' => 'El titulo de la pregunta no puede estar vacío.', 'data' => $question], 422);
            }
            $correct = false;
            foreach ($question['answers'] as $answer) {
                if(!isset($answer['answer_title'])){
                    return response()->json(['error' => 'El titulo de la respuesta no puede estar vacío.', 'data' => $question], 422);
                }

                if($answer['correct'] == 1 ){
                    if($correct){
                        return response()->json(['error' => 'Solo puede haber una respuesta correcta.', 'data' => $question], 422);
                    }
                    $correct = true;
                }                
            }

            if(!$correct){
                return response()->json(['error' => 'Debe haber al menos una respuesta correcta.', 'data' => $question], 422);
            }


        }
        foreach ($data as $question) {
            
            $question_create = Questions::create([
                'exam_id' => $exam_id,
                'question_title' => $question['question_title']
            ]);


            foreach ($question['answers'] as $answer) {
                Answers::create([
                    'question_id' => $question_create->id,
                    'answer_title' => $answer['answer_title'],
                    'is_correct' => $answer['correct']
                ]);
            }

        }

        return response()->json(['message' => 'Preguntas añadidas correctamente'], 200);
    }


      /**
     * get questions of exam 
     *
     * @param $provider
     * @return JsonResponse
     */
    public function getQuestions(Request $request,$exam_id)
    {
        $data = $request->all();
        $exam = Exams::find($exam_id);   
        
        if(!$exam){
            return response()->json(['error' => 'Examen no encontrado'], 404);
        }
       
        $questions = Questions::with('answers')->where('exam_id',$exam_id)->get();
        return response()->json(['data' => $questions], 200);
    }
  
  
    /**
     * get results of exam 
     *
     * @param $provider
     * @return JsonResponse
     */
    public function getResults(Request $request,$exam_id)
    {
        $data = $request->all();
        $exam = Exams::find($exam_id);   
        
        if(!$exam){
            return response()->json(['error' => 'Examen no encontrado'], 404);
        }
       
        $results = Results::with(['user'])->where('exam_id',$exam_id)->get();

        return response()->json(['data' => $results], 200);
    }


      /**
     * update question of exam 
     *
     * @param $provider
     * @return JsonResponse
     */
    public function updateQuestion(Request $request,$exam_id,$question_id)
    {
        $data = $request->all();
        $exam = Exams::find($exam_id);   
        
        if(!$exam){
            return response()->json(['error' => 'Examen no encontrado'], 404);
        }
       
        $question = Questions::find($question_id);
        if(!$question){
            return response()->json(['error' => 'Pregunta no encontrada'], 404);
        }

        $question->question_title = $data['question_title'];
        $question->save();


        $correct = false;
        foreach ($data['answers'] as $answer) {
            
            if(!isset($answer['answer_title'])){
                return response()->json(['error' => 'El titulo de la respuesta no puede estar vacío.', 'data' => $question], 422);
            }

            if($answer['correct'] == 1 ){
                if($correct){
                    return response()->json(['error' => 'Solo puede haber una respuesta correcta.', 'data' => $question], 422);
                }
                $correct = true;
            }                

        }
        if(!$correct){
            return response()->json(['error' => 'Debe haber al menos una respuesta correcta.', 'data' => $question], 422);
        }

        foreach ($data['answers'] as $answer) {
            $answer_db = Answers::find($answer['id']);
            if(!$answer_db){
                return response()->json(['error' => 'Respuesta no encontrada'], 404);
            }
            $answer_db->answer_title = $answer['answer_title'];
            $answer_db->is_correct = $answer['correct'];
            $answer_db->save();
        }

        return response()->json(['message' => 'Pregunta actualizada correctamente'], 200);
    }


    /**
     * delete question of exam 
     *
     * @param $provider
     * @return JsonResponse
     */
    public function deleteQuestion(Request $request,$exam_id,$question_id){
        $data = $request->all();
        $exam = Exams::find($exam_id);   
        
        if(!$exam){
            return response()->json(['error' => 'Examen no encontrado'], 404);
        }
       
        $question = Questions::find($question_id);
        if(!$question){
            return response()->json(['error' => 'Pregunta no encontrada'], 404);
        }

        $question->delete();

        #delete answers
        Answers::where('question_id',$question_id)->delete();
        
        return response()->json(['message' => 'Pregunta eliminada correctamente'], 200);
    }

}