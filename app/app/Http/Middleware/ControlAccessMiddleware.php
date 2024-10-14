<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\TokenManager;
use Closure;
use Illuminate\Http\Request;

class ControlAccessMiddleware
{
    const ADMIN = 1;
    const PROFESSOR  = 2;
    const STUDENT = 3;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, $permission = null): mixed
    {
        try {
            $accessToken = TokenManager::getTokenFromRequest();
            $user        = TokenManager::getUserFromToken($accessToken);

            $userData = User::with(['role','moduleByRole.module'])->find($user->id);

            if (!$userData) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            if($permission){
                switch($permission){
                    case 'admin':
                        if($userData->role->id != self::ADMIN){
                            return response()->json(['error' => 'Unauthorized'], 401);
                        }
                        break;
                    case 'professor':
                        if($userData->role->id != self::PROFESSOR){
                            return response()->json(['error' => 'Unauthorized'], 401);
                        }
                        break;
                    case 'student':
                        if($userData->role->id != self::STUDENT){
                            return response()->json(['error' => 'Unauthorized'], 401);
                        }
                        break;
                }
            }
            
            return $next($request);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 401);
        }
    }
}
