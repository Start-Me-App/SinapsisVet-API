<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use App\Support\UploadServer;
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
        if(isset($data['cv_file'])){

            $cv_path = UploadServer::uploadFile($data['cv_file'],'cvs');
            $userData->cv_path = $cv_path;
        }

        if(isset($data['photo_file'])){
            $photo_path = UploadServer::uploadFile($data['photo_file'],'photos');
            $userData->photo_path = $photo_path;
        }

        if(isset($data['description'])){
            $userData->description = $data['description'];
        }

        
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
        
        if($userObject->delete()){
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

        if(isset($params['role'])){
            $roleId = [$params['role']];
        }else{
            $roleId = [1,2,3];
        }

        if(isset($params['name'])){
            $list = User::with(['nationality','role'])->where('active',$active)->whereIn('role_id',$roleId)->where('name','like','%'.$params['name'].'%')->get();
            return response()->json(['data' => $list], 200);
        }else{
            $list = User::with(['nationality','role'])->whereIn('role_id',$roleId)->where('active',$active)->get();
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
            $list = User::with(['nationality','role'])->where('active',$active)->where('role_id',2)->where('name','like','%'.$params['name'].'%')->get();
            return response()->json(['data' => $list], 200);
        }else{
            $list = User::with(['nationality','role'])->where('active',$active)->where('role_id',2)->get();
        }

        
  

        return response()->json(['data' => $list], 200);
    }


    #create professor
    public function createProfessor(Request $request){

        $accessToken = TokenManager::getTokenFromRequest();
        $user        = TokenManager::getUserFromToken($accessToken);

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if($user->role->id != 1){
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->all();

        $validator = validator($data, [
            'email' => 'required|email',
            'password' => 'required|min:6',
            'name' => 'required',
            'lastname' => 'required',
            'telephone' => 'required',
            'area_code' => 'required',
            'nationality_id' => 'required',
            'gender' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        #validate email
        $user = User::where('email',$data['email'])->first();
        if($user){
            return response()->json(['error' => 'El email ya existe'], 400);
        }

        if(isset($data['cv_file'])){

            $cv_path = UploadServer::uploadFile($data['cv_file'],'cvs');
            $data['cv_path'] = $cv_path;
        }

        if(isset($data['profile_photo'])){
            $photo_path = UploadServer::uploadFile($data['profile_photo'],'photos');
            $data['photo_path'] = $photo_path;
        }

        if(isset($data['description'])){
            $data['description'] = $data['description'];
        }
        $userCreated = User::firstOrCreate(
            [
                'email' => $data['email'],
                'password' => md5($data['password']),
                'dob' => isset($data['dob']) ? $data['dob'] : null,
                'name' => $data['name'],
                'role_id' => 2,
                'lastname' => $data['lastname'],
                'telephone' => $data['telephone'],
                'area_code' => $data['area_code'],
                'tyc' => 1,
                'nationality_id' => $data['nationality_id'],
                'sex'   => $data['gender'],
                'cv_path' => $data['cv_path'],
                'photo_path' => $data['photo_path'],
                'description' => $data['description'],
                'email_verified_at' => date('Y-m-d H:i:s'),
            ],
            [
                'email_verified_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ]
        );
        
        #get user data from database    
        $userCreated = User::with(['role'])->where('email', $data['email'])->first();

        return response()->json(['message' => 'Profesor creado correctamente', 'data' => $userCreated], 200); 

    }
}
