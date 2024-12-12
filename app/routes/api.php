<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserDecisionController;
use App\Http\Controllers\CountriesController;
use App\Http\Controllers\PDFController;
use App\Http\Controllers\{CategoriesController, CoursesController,LessonsController,WorkshopsController,ExamsController,MaterialsController,FileController,ShoppingCartController};
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\ControlAccessMiddleware;
use App\Models\Categories;

Route::post('/login', [AuthController::class,'login']);
Route::post('/register', [AuthController::class,'register']);
Route::post('/register/verify', [AuthController::class,'verifyEmail']);
Route::post('/register/resend', [AuthController::class,'resendEmail']);
Route::post('/login/{provider}', [AuthController::class,'loginSocial']);
Route::post('/requestResetPassword', [AuthController::class,'requestResetPassword']);
Route::post('/resetPassword', [AuthController::class,'resetPassword']);


Route::post('/user', [UserController::class,'update']);
Route::delete('/user/{user_id}', [UserController::class,'delete'])->middleware(ControlAccessMiddleware::class.':admin');
Route::get('/user/list', [UserController::class,'listUsers'])->middleware(ControlAccessMiddleware::class.':admin');
Route::get('/user', [AuthController::class,'getUser']);


Route::get('/countries', [CountriesController::class,'getAll']);
Route::get('/categories', [CategoriesController::class,'getAll']);
Route::get('/professors', [UserController::class,'getProfessors']);

Route::get('/home/courses', [CoursesController::class,'listAllCourses']);

#get file from storage
Route::get('/{lessonOrWorkshop}/materials/{id}/{checksum}',[FileController::class,'downloadFile']);
Route::get('/images/url/{checksum}',[FileController::class,'getImageByUrl']);


Route::get('generate-pdf', [PDFController::class, 'generatePDF']);


#Courses

#create route group for courses
Route::group(['prefix' => 'courses'], function () {
    Route::get('', [CoursesController::class,'listCourses']);
    Route::get('/{course_id}', [CoursesController::class,'listCourse']);
    Route::get('/{course_id}/lessons', [CoursesController::class,'listLessons']);
    Route::get('/{course_id}/exams', [CoursesController::class,'listExams']);
    Route::get('/{course_id}/workshops', [CoursesController::class,'listWorkshops']);
});

Route::group(['prefix' => 'exams'], function () {
    Route::get('/{exam_id}', [ExamsController::class,'showExam']);
    Route::post('/{exam_id}/submit', [ExamsController::class,'submitExam']);
});


Route::group(['prefix' => 'lessons'], function () {
    Route::post('/{lesson_id}/view', [LessonsController::class,'viewLesson']);
});

#create route group for courses
Route::group(['prefix' => 'shoppingCart'], function () {
    Route::get('', [ShoppingCartController::class,'get']);
    Route::post('/addItem', [ShoppingCartController::class,'addItem']);
    Route::post('/removeItem', [ShoppingCartController::class,'removeItem']);
    Route::post('/process', [ShoppingCartController::class,'process']);
    
});






#create route group for admin
Route::group(['prefix' => 'admin'], function () {
    
    #create route group for courses
    Route::group(['prefix' => 'courses'], function () {
        Route::post('/', [CoursesController::class,'create'])->middleware(ControlAccessMiddleware::class.':admin');
        Route::post('/{course_id}', [CoursesController::class,'update']);
        Route::delete('/{course_id}', [CoursesController::class,'delete']);
        Route::get('/list', [CoursesController::class,'listAllCourses']);

        Route::get('/{course_id}', [CoursesController::class,'getCourse']);
        Route::get('/{course_id}/lessons', [CoursesController::class,'getLessonsByCourse']);
        Route::get('/{course_id}/exams', [CoursesController::class,'getExamsByCourse']);
        Route::get('/{course_id}/workshops', [CoursesController::class,'getWorkshopsByCourse']);
    
        Route::get('/{course_id}/students', [CoursesController::class,'getStudents']);
        Route::post('/{course_id}/students', [CoursesController::class,'addStudent']);
        Route::delete('/{course_id}/students/{student_id}', [CoursesController::class,'removeStudent']);
    });



    #create route group for lessons
    Route::group(['prefix' => 'lessons'], function () {
        Route::post('/', [LessonsController::class,'create']);
        Route::post('/{lesson_id}', [LessonsController::class,'update']);
        Route::delete('/{lesson_id}', [LessonsController::class,'delete']);

        Route::get('/{lesson_id}', [LessonsController::class,'getLesson']);
    });

    #create route group for workshops
    Route::group(['prefix' => 'workshops'], function () {
        Route::post('/', [WorkshopsController::class,'create']);
        Route::post('/{workshop_id}', [WorkshopsController::class,'update']);
        Route::delete('/{workshop_id}', [WorkshopsController::class,'delete']);

        Route::get('/{workshop_id}', [WorkshopsController::class,'getWorkshop']);
    });

    #create route group for exams
    Route::group(['prefix' => 'exams'], function () {
        Route::post('/', [ExamsController::class,'create']);
        Route::patch('/{exam_id}', [ExamsController::class,'update']);
        Route::delete('/{exam_id}', [ExamsController::class,'delete']);


        Route::post('/{exam_id}/questions', [ExamsController::class,'addQuestion']);
        Route::get('/{exam_id}/questions', [ExamsController::class,'getQuestions']);

        Route::get('/{exam_id}', [ExamsController::class,'getExam']);
       
        Route::patch('/{exam_id}/questions/{question_id}', [ExamsController::class,'updateQuestion']);      
        Route::delete('/{exam_id}/questions/{question_id}', [ExamsController::class,'deleteQuestion']);

        Route::get('/{exam_id}/results', [ExamsController::class,'getResults']);
    });

/*     #create route group for materials
    Route::group(['prefix' => 'materials'], function () {
        Route::post('/', [MaterialsController::class,'create']);
        Route::patch('/{material_id}', [MaterialsController::class,'update']);
        Route::delete('/{material_id}', [MaterialsController::class,'delete']);

        Route::get('/{material_id}', [MaterialsController::class,'getMaterial']);
    }); */

    #create route group for categories
    Route::group(['prefix' => 'categories'], function () {
        Route::post('/', [CategoriesController::class,'create']);
        Route::patch('/{category_id}', [CategoriesController::class,'update']);
        Route::delete('/{category_id}', [CategoriesController::class,'delete']);

    });
});

