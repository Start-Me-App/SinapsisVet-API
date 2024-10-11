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
    public function register(Request $request)
    {
        $params = $request->only('email', 'password','name','role_id','dob','lastname');
        
        #verify is the request has all the required fields
        $validator = validator($params, [
            'email' => 'required|email',
            'password' => 'required|min:6',
            'name' => 'required',
            'role_id' => 'required',
            'lastname' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        #create user

        $userCreated = User::firstOrCreate(
            [
                'email' => $params['email'],
                'password' => md5($params['password']),
                'dob' => isset($params['dob']) ? $params['dob'] : null,
                'name' => $params['name'],
                'role_id' => 3,
                'lastname' => $params['lastname']
            ],
            [
                'email_verified_at' => null
            ]
        );
        
        #get user data from database    
        $userCreated = User::with(['role'])->where('email', $params['email'])->first();


        return response()->json(['msg' => 'Usuario creado con éxito'], 200);
        
    }

     /**
     * Obtain the user information from Provider.
     *
     * @param $provider
     * @return JsonResponse
     */
    public function loginSocial(Request $request, $provider)
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

        /* $verifiedIdToken = $this->auth->verifyIdToken($socialTokenId);
        $user = new User();
        $user->displayName = $verifiedIdToken->claims()->get('name');
        $user->email = $verifiedIdToken->claims()->get('email');
        $user->uid = $verifiedIdToken->claims()->get('sub'); */
        
        #create user

        $userCreated = User::firstOrCreate(
            [
                'email' => $user->email,
                'role_id' => 3,
                'dob' => isset($params['dob']) ? $params['dob'] : null,
                'name' => $user->displayName,
                'role_id' => 3,
                'uid' => $user->uid
            ],
            [
                'email_verified_at' => null
            ]
        );
        
        #get user data from database    
        $userCreated = User::with(['role'])->where('email', $user->email)->first();
        
        
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
        if (!in_array($provider, ['google'])) {
            return response()->json(['error' => 'Please login using google'], 422);
        }
    }


    /**
     * Obtain the user information from Provider.
     *
     * @param $provider
     * @return JsonResponse
     */
    public function login(Request $request)
    {
        $params = $request->only('email', 'password');
        
        #verify is the request has all the required fields
        $validator = validator($params, [
            'email' => 'required|email',
            'password' => 'required|min:6'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }
        
        #get user data from database    
        $user = User::with(['role'])->where('email', $params['email'])->where('password', md5($params['password']))->first();
        
        if(!$user){
            return response()->json(['error' => 'Usuario o contraseña incorrectos'], 401);
        }    

        if($user->email_verified_at == null){
            return response()->json(['error' => 'Email no verificado'], 401);
        }

        #create jwt token
        $token = TokenManager::makeToken($user);


        return response()->json(['token' => $token], 200);
        
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
            return response()->json(['error' => 'La sesion expiró'], 401);
        }

        $user = User::with(['role'])->find($user->id);
        return response()->json($user);
    }
   
   
   
    /**
     * Verify the email.
     *
     * @return JsonResponse
     */
    public function verifyEmail(Request $request)
    {
        $params = $request->only('email', 'token');
        
        #verify is the request has all the required fields
        $validator = validator($params, [
            'email' => 'required|email',
            'token' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        
             
        #get user data from database    
        $user = User::with(['role'])->where('email', $params['email'])->where('verification_token', $params['token'])->first();
            
        if(!$user){
            return response()->json(['error' => 'Token no válido'], 401);
        }

        if($user->email_verified_at != null){
            return response()->json(['error' => 'Email ya verificado'], 401);
        }

        $user->email_verified_at = date('Y-m-d H:i:s');
        $user->save();
        
        return response()->json(['msg' => 'Email verificado con éxito'], 200);
    }

    /**
     * Request to reset password
     *
     * @return JsonResponse
     */
    public function requestResetPassword(Request $request)
    {
        $params = $request->only('email');
        
        #verify is the request has all the required fields
        $validator = validator($params, [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        
             
        #get user data from database    
        $user = User::with(['role'])->where('email', $params['email'])->first();
            
        if(!$user){
            return response()->json(['error' => 'No se encontró un usuario con ese email'], 401);
        }

        $user->password_reset_token = bin2hex(random_bytes(32));
        $user->save();

        #TODO send email
        
        return response()->json(['msg' => 'Recupero de contraseña enviado correctamente'], 200);
    }




    /**
     * Reset password
     *
     * @return JsonResponse
     */
    public function resetPassword(Request $request)
    {
        $params = $request->only('email','password','token');
        
        #verify is the request has all the required fields
        $validator = validator($params, [
            'email' => 'required|email',
            'password' => 'required|min:6',
            'token' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        #get user data from database    
        $user = User::with(['role'])->where('email', $params['email'])->where('password_reset_token',$params['token'])->first();
            
        if(!$user){
            return response()->json(['error' => 'Token inválido'], 401);
        }

        $user->password = md5($params['password']);
        $user->save();
       
        return response()->json(['msg' => 'Contraseña actualizada correctamente'], 200);
    }

}