<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Models\{User,ShoppingCart};
use App\Support\Email\Emailing;
use Laravel\Socialite\Facades\Socialite;
use App\Support\TokenManager;

use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Kreait\Firebase\Value\Email;
use App\Helper\TelegramNotification;
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
        try{
            $params = $request->only('email', 'password','name','role_id','dob','lastname','telephone','area_code','tyc','nationality_id','gender');
            
        #verify is the request has all the required fields
        $validator = validator($params, [
            'email' => 'required|email',
            'password' => 'required|min:6',
            'name' => 'required',
            'lastname' => 'required',
            'telephone' => 'required',
            'area_code' => 'required',
            'tyc' => 'required',
            'nationality_id' => 'required',
            'gender' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        #verify if the email is already in use
        $user = User::where('email', $params['email'])->first();
        if($user){
            return response()->json(['error' => 'Email ya registrado'], 422);
        }

        if($params['tyc'] != 1){
            return response()->json(['error' => 'Debe aceptar los términos y condiciones'], 422);
        }

        $userCreated = User::firstOrCreate(
            [
                'email' => $params['email'],
                'password' => md5($params['password']),
                'dob' => isset($params['dob']) ? $params['dob'] : null,
                'name' => $params['name'],
                'role_id' => 3,
                'lastname' => $params['lastname'],
                'telephone' => $params['telephone'],
                'area_code' => $params['area_code'],
                'tyc' => $params['tyc'],
                'nationality_id' => $params['nationality_id'],
                'sex'   => $params['gender']
            ],
            [
                'email_verified_at' => null,
                'created_at' => date('Y-m-d H:i:s')
            ]
        );
        
        #get user data from database    
        $userCreated = User::with(['role'])->where('email', $params['email'])->first();


        Emailing::verifyEmail($userCreated);

        return response()->json(['msg' => 'Usuario creado con éxito'], 200);

        }catch(\Exception $e){
            $telegram = new TelegramNotification();
            $telegram->toTelegram($e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
        
    }

     /**
     * Obtain the user information from Provider.
     *
     * @param $provider
     * @return JsonResponse
     */
    public function loginSocial(Request $request, $provider)
    {
        try{
            $validated = $this->validateProvider($provider);
            if (!is_null($validated)) {
                return $validated;
        }
       
        $accessToken = $request->input("token-id");
 
        if (empty($accessToken)) {
            return response()->json(['error' => 'Token is required'], 401);
        }
        try {     
            $verifiedIdToken = $this->auth->verifyIdToken($accessToken);


            $user = new User();
            $user->name = $verifiedIdToken->claims()->get('name');
            $user->email = $verifiedIdToken->claims()->get('email');
            $user->uid = $verifiedIdToken->claims()->get('sub');
        } catch (Exception $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ]);
        }
      
        $check_user = User::with(['role','moduleByRole.module','nationality'])->where('email', $user->email)->first();
        
      
        if($check_user){
            $token = TokenManager::makeToken($check_user);
            return response()->json(['token' => $token], 200);
        }
    
        $userCreated = User::firstOrCreate(
            [
                'email' => $user->email,
                'role_id' => 3,
                'dob' => null,
                'name' => $user->name,
                'lastname' => $user->name,
                'role_id' => 3,
                'uid' => $user->uid,
                'password' => md5($user->uid),
                'tyc' => 1
            ],
            [
                'email_verified_at' => date('Y-m-d H:i:s'),
            ]
        );

        $userCreated =  User::with(['role','moduleByRole.module','nationality'])->where('email', $user->email)->first();
        
        if($userCreated){
            #create jwt token
            $token = TokenManager::makeToken($userCreated);
    
    
            return response()->json(['token' => $token], 200);

        }
        }catch(\Exception $e){
            $telegram = new TelegramNotification();
            $telegram->toTelegram($e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
        
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

   /*      if($user->email_verified_at == null){
            return response()->json(['error' => 'Email no verificado'], 402);
        } */

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




        $user = User::with(['role','moduleByRole.module','nationality'])->find($user->id);

        #check if has a active shopping cart
        
        $cart = ShoppingCart::where('user_id', $user->id)->where('active',1)->first();
        if(!$cart){
            #create a new shopping cart
            $cart = new ShoppingCart();
            $cart->user_id = $user->id;
            $cart->active = 1;
            $cart->save();
        }

        $user->shopping_cart_id = $cart->id;



        return response()->json($user);
    }
   
   
   
    /**
     * Verify the email.
     *
     * @return JsonResponse
     */
    public function verifyEmail(Request $request)
    {
        $params = $request->only('token');
        
        #verify is the request has all the required fields
        $validator = validator($params, [
            'token' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        
             
        #get user data from database    
        $user = User::with(['role'])->where('verification_token', $params['token'])->first();
            
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
        
        Emailing::resetPassword($user);
        
        return response()->json(['msg' => 'Recupero de contraseña enviado correctamente'], 200);
    }




    /**
     * Reset password
     *
     * @return JsonResponse
     */
    public function resetPassword(Request $request)
    {
        $params = $request->only('password','token');
        
        #verify is the request has all the required fields
        $validator = validator($params, [
            'password' => 'required|min:6',
            'token' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        #get user data from database    
        $user = User::with(['role'])->where('password_reset_token',$params['token'])->first();
            
        if(!$user){
            return response()->json(['error' => 'Token inválido'], 401);
        }

        $user->password = md5($params['password']);
        $user->password_reset_token = null;
        $user->save();
       
        return response()->json(['msg' => 'Contraseña actualizada correctamente'], 200);
    }


    
    /**
     * resend email
     *
     * @return JsonResponse
     */
    public function resendEmail(Request $request)
    {
        $params = $request->only('email');
        
        #verify is the request has all the required fields
        $validator = validator($params, [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $user = User::where('email', $params['email'])->first();

        if(!$user){
            return response()->json(['error' => 'Email no registrado'], 400);
        }

        Emailing::verifyEmail($user);
       
        return response()->json(['msg' => 'Email reenviado'], 200);
    }

}