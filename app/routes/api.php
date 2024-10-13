<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserDecisionController;
use Illuminate\Support\Facades\Route;


Route::post('/login', [AuthController::class,'login']);
Route::post('/register', [AuthController::class,'register']);
Route::post('/register/verify', [AuthController::class,'verifyEmail']);
Route::post('/login/{provider}', [AuthController::class,'loginSocial']);
Route::post('/requestResetPassword', [AuthController::class,'requestResetPassword']);
Route::post('/resetPassword', [AuthController::class,'resetPassword']);
Route::get('/user', [AuthController::class,'getUser']);




Route::patch('/user', [UserController::class,'update']);
Route::delete('/user', [UserController::class,'delete']);

