<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use App\Models\{Courses, Lessons, Materials, User, Workshops,Inscriptions};

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

use App\Support\TokenManager;

class FileController extends Controller
{   

    /**
     * downloadfile
     *
     * @param $provider
     * @return JsonResponse
     */
    public function downloadFile($lessonOrWorkshop,$id, $filename)
    {
        if($lessonOrWorkshop == 'lessons'){
            $whereId = 'lesson_id';
        }else{
            $whereId = 'workshop_id';
        }
          
        $material = Materials::with([$lessonOrWorkshop])->where($whereId,$id)->where('file_path','/storage/'.$lessonOrWorkshop.'/'.$id.'/materials/'.$filename)->first();
        if(!$material){
            return response()->json(['error' => 'El material no existe'], 409);
        }

        #get token from request
        $accessToken = TokenManager::getTokenFromRequest();
        $user        = TokenManager::getUserFromToken($accessToken);

        if($user->role->id != 1){            
            #if user is not admin, validate if user is inscribed in course
            $is_inscribed_lesson = Inscriptions::where('user_id',$user->id)->where('course_id',$material->lesson->course_id)->first();
            $is_inscribed_workshop = Inscriptions::where('user_id',$user->id)->where('course_id',$material->workshop->course_id)->first();
            if(!$is_inscribed_workshop && !$is_inscribed_lesson){
                return response()->json(['error' => 'No tienes permisos para descargar este archivo'], 409);
            }
        }   

        $path = storage_path('app/public/'.$lessonOrWorkshop.'/'.$id.'/materials/'.$filename);
        return response()->download($path);

    }


    public function getImageByUrl(Request $request, $filename)
    {
        $url = null;
        if (Storage::disk('public')->exists('images/'.$filename)) {
            $url =  Storage::url('images/'.$filename);
        }

        if ($url) {
            return response()->json(['url' => env('STATIC_URL').$url], 200);
        }

        return response()->json(['message' => 'Imagen no encontrada'], 404);
    }


}