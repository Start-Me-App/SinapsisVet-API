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
        
        #   $validated = $this->validateUpdate($request);
        $data = $request->all();        
        $userData = User::find($user->id);
        
        
        $data['dob'] = date('Y-m-d', strtotime($data['dob']));
        $userData->dob = $data['dob'];
        $userData->age = $this->calculateAge($data['dob']);
        $userData->description = $data['description'];
        $userData->genre_id = $data['genre'];
        $userData->sexual_orientation_id = $data['sexual_orientation'];
        $userData->save();

        $users = User::with(['genre','sexualOrientation'])->find($user->id);


        return response()->json($users);
    }

    private function calculateAge($dob)
    {
        $dob = date('Y-m-d', strtotime($dob));
        $dobObject = new \DateTime($dob);
        $nowObject = new \DateTime();
        $diff = $dobObject->diff($nowObject);
        return $diff->y;
    }

    private function validateUpdate(Request $request)
    {
        $rules = [
            'dob' => 'required|date',
            'description' => 'string',
            'genre' => 'required|string',
            'sexual_orientation' => 'required|string'
        ];
        $this->validate($request, $rules);
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
        
       
        $userObject = User::find($user->id);
        
        $userObject->delete();

        return response()->json($userObject);
    }

}