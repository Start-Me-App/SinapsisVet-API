<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserDecisionController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\ControlAccessMiddleware;

Route::post('/login', [AuthController::class,'login']);
Route::post('/register', [AuthController::class,'register']);
Route::post('/register/verify', [AuthController::class,'verifyEmail']);
Route::post('/login/{provider}', [AuthController::class,'loginSocial']);
Route::post('/requestResetPassword', [AuthController::class,'requestResetPassword']);
Route::post('/resetPassword', [AuthController::class,'resetPassword']);
#use control access middleware to protect getUser but send info to the middleware
Route::get('/user', [AuthController::class,'getUser'])->middleware(ControlAccessMiddleware::class);




Route::patch('/user', [UserController::class,'update']);
Route::delete('/user/{user_id}', [UserController::class,'delete'])->middleware(ControlAccessMiddleware::class.':admin');
Route::get('/user/list', [UserController::class,'listUsers'])->middleware(ControlAccessMiddleware::class.':admin');

