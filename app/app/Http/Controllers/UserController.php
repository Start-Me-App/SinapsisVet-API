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
            'user_id' => 'required'
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
        
        if($user->role->id == 1){
            $userData->role_id = $data['role_id'];
        }
       
        if($userData->save()){
            return response()->json(['message' => 'Usuario actualizado correctamente', 'data' => User::with(['role'])->find($user->id) ], 200);
        }

        return response()->json(['error' => 'Error al actualizar el usuario'], 500);

    }

     /**
     * delete user 
     *
     * @param $provider
     * @return JsonResponse
     */
    public function delete(Request $request)
    {

        $accessToken = TokenManager::getTokenFromRequest();
        $user        = TokenManager::getUserFromToken($accessToken);
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }   
        
        if(!$user->role->id != 1){
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $data = $request->all();
        
        $validator = validator($data, [
            'user_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }
        $userObject = User::find($data['user_id']);
        
        if(!$userObject){
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        if($userObject->delete()){
            return response()->json(['message' => 'Usuario eliminado correctamente'], 200);
        }   
        return response()->json(['error' => 'Error al eliminar el usuario'], 500);
    }

}