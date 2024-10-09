<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use App\Support\TokenManager;

class Authcontroller extends Controller
{

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $auth;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(FirebaseAuth $auth)
    {
        $this->auth =app("firebase.auth");
    }

    /**
     * Obtain the user information from Provider.
     *
     * @param $provider
     * @return JsonResponse
     */
    public function login(Request $request, $provider)
    {
        $validated = $this->validateProvider($provider);
        if (!is_null($validated)) {
            return $validated;
        }
       
        $socialTokenId = $request->input("token-id");
        
        if (empty($socialTokenId)) {
            return response()->json(['error' => 'Token is required'], 401);
        }
        #get data from laravel-firebase-auth

        $verifiedIdToken = $this->auth->verifyIdToken($socialTokenId);
        $user = new User();
        $user->displayName = $verifiedIdToken->claims()->get('name');
        $user->email = $verifiedIdToken->claims()->get('email');
        $user->uid = $verifiedIdToken->claims()->get('sub');
        
        $userCreated = User::firstOrCreate(
            [
                'email' => $user->email
            ],
            [
                'email_verified_at' => now(),
                'name' => $user->displayName,
                'status' => true,
                'uid' => $user->uid
            ]
        );
        
        #get user data from database
        $userCreated = User::with(['genre','sexualOrientation'])->where('email', $user->email)->first();

        #create jwt token
        $token = TokenManager::makeToken($userCreated);


        return response()->json(['token' => $token], 200);
        
    }

    /**
     * @param $provider
     * @return JsonResponse
     */
    protected function validateProvider($provider)
    {
        if (!in_array($provider, ['facebook', 'github', 'google'])) {
            return response()->json(['error' => 'Please login using facebook, github or google'], 422);
        }
    }


    /**
     * Get the authenticated User.
     *
     * @return JsonResponse
     */
    public function getUser()
    {
        $accessToken = TokenManager::getTokenFromRequest();
        $user = TokenManager::getUserFromToken($accessToken);
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $user = User::with(['genre','sexualOrientation'])->find($user->id);
        return response()->json($user);
    }

}