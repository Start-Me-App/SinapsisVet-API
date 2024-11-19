<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use App\Models\User;

use Illuminate\Support\Facades\DB;

use App\Support\TokenManager;

class UserController extends Controller
{


    /**
     * Update user profile
     *
     * @param $provider
     * @return JsonResponse
     */
    public function update(Request $request)
    {

        $accessToken = TokenManager::getTokenFromRequest();
        $user        = TokenManager::getUserFromToken($accessToken);

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if(!($user->id == $request->user_id)){
            if($user->role->id != 1){
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }

        
        $data = $request->all();        
        $validator = validator($data, [
            'name' => 'required',
            'lastname' => 'required',
            'user_id' => 'required',
            'telephone' => 'required',
            'area_code' => 'required',
            'nationality_id' => 'required',
            'gender' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }
        $userData = User::find($data['user_id']);

        if(!$userData){
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        if (isset($data['dob'])) {
            $data['dob'] = date('Y-m-d', strtotime($data['dob']));
            $userData->dob = $data['dob'];
        }
        $userData->name = $data['name'];
        $userData->lastname = $data['lastname'];
        $userData->telephone = $data['telephone'];
        $userData->area_code = $data['area_code'];
        $userData->nationality_id = $data['nationality_id'];   
        $userData->sex = $data['gender']; 


        
        if($user->role->id == 1){
            $userData->role_id = $data['role_id'];
            if(isset($data['active'])){
                $userData->active = $data['active'];
            }
           
        }

        if($userData->save()){
            return response()->json(['message' => 'Usuario actualizado correctamente', 'data' => User::with(['role','nationality'])->find($data['user_id']) ], 200);
        }

        return response()->json(['error' => 'Error al actualizar el usuario'], 500);

    }

     /**
     * delete user 
     *
     * @param $provider
     * @return JsonResponse
     */
    public function delete(Request $request,$userId)
    {

        $accessToken = TokenManager::getTokenFromRequest();
        $user        = TokenManager::getUserFromToken($accessToken);
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }   
       
        if($user->role->id != 1){
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $data = $request->all();
        
        
        $userObject = User::find($userId);
        
        if(!$userObject){
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }
        $userObject->active = 0;
        if($userObject->save()){
            return response()->json(['message' => 'Usuario eliminado correctamente'], 200);
        }   
        return response()->json(['error' => 'Error al eliminar el usuario'], 500);
    }


    /**
     * Listado de usuarios
     *
     * @param $provider
     * @return JsonResponse
     */
    public function listUsers(Request $request)
    {   

        $params = $request->all();
        if(isset($params['active'])){
            $active = $params['active'];
        }else{
            $active = 1;
        }

        if(isset($params['name'])){
            $list = User::with(['nationality'])->where('active',$active)->where('role_id',3)->where('name','like','%'.$params['name'].'%')->get();
            return response()->json(['data' => $list], 200);
        }else{
            $list = User::with(['nationality'])->where('active',$active)->where('role_id',3)->get();
        }

        
  

        return response()->json(['data' => $list], 200);
    }


    /**
     * Listado de usuarios
     *
     * @param $provider
     * @return JsonResponse
     */
    public function getProfessors(Request $request)
    {   

        $params = $request->all();
        if(isset($params['active'])){
            $active = $params['active'];
        }else{
            $active = 1;
        }

        if(isset($params['name'])){
            $list = User::where('active',$active)->where('role_id',2)->where('name','like','%'.$params['name'].'%')->get();
            return response()->json(['data' => $list], 200);
        }else{
            $list = User::where('active',$active)->where('role_id',2)->get();
        }

        
  

        return response()->json(['data' => $list], 200);
    }
}