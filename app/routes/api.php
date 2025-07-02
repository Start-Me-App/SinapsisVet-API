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
use App\Http\Controllers\MercadoPago\MercadoPago;
use App\Http\Controllers\MercadoPago\CheckoutPro;
use App\Http\Controllers\MercadoPago\WebHook;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\Stripe\{Charges,StripeWebhookController,PaymentIntentController};  
use App\Http\Controllers\DiscountsController;
use App\Http\Controllers\CouponsController;
use App\Http\Controllers\MovementsController;
use App\Http\Controllers\AccountsController;

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
    Route::post('/professor', [UserController::class,'createProfessor']);

    Route::get('/countries', [CountriesController::class,'getAll']);
    Route::get('/categories', [CategoriesController::class,'getAll']);
    Route::get('/professors', [UserController::class,'getProfessors']);

    Route::get('/home/courses', [CoursesController::class,'listAllCourses']);

    #get file from storage
    Route::get('/{lessonOrWorkshop}/materials/{id}/{checksum}',[FileController::class,'downloadFile']);
    Route::get('/images/url/{checksum}',[FileController::class,'getImageByUrl']);


    Route::get('assistance', [PDFController::class, 'generateAssistancePdf']);
    Route::get('approved', [PDFController::class, 'generateApprovedPdf']);
    Route::get('workshop', [PDFController::class, 'generateWorkshopPdf']);


    #mercadopago
    
    Route::post('/mercadopago/processPreference', [CheckoutPro::class,'processPreference']);
    Route::post('/mercadopago/webhook', [WebHook::class,'notification']);

    #stripe
    Route::post('/stripe/charge', [Charges::class,'charge']);
    Route::post('/stripe/paymentIntent', [PaymentIntentController::class,'createPaymentIntent']);
    Route::post('/stripe/webhook', [StripeWebhookController::class,'handleWebhook']);


    Route::post('/cleanUpOrders', [OrdersController::class,'cleanUpOrders']);

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


        Route::get('/discounts', [ShoppingCartController::class,'getDiscounts']);
        
    });


    Route::group(['prefix' => 'coupons'], function () {
        Route::get('', [CouponsController::class,'validateCoupon']);
    });


    #create route group for orders
    Route::group(['prefix' => 'orders'], function () {
        Route::get('', [OrdersController::class,'getMyOrders']);
    });





    #create route group for admin
    Route::group(['prefix' => 'admin'], function () {
        
        #create route group for courses
        Route::group(['prefix' => 'courses'], function () {
            Route::post('/', [CoursesController::class,'create'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::post('/{course_id}', [CoursesController::class,'update'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::delete('/{course_id}', [CoursesController::class,'delete'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::get('/list', [CoursesController::class,'listAllCourses'])->middleware(ControlAccessMiddleware::class.':admin');

            Route::get('/{course_id}', [CoursesController::class,'getCourse'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::get('/{course_id}/lessons', [CoursesController::class,'getLessonsByCourse'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::get('/{course_id}/exams', [CoursesController::class,'getExamsByCourse'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::get('/{course_id}/workshops', [CoursesController::class,'getWorkshopsByCourse'])->middleware(ControlAccessMiddleware::class.':admin');
        
            Route::get('/{course_id}/students', [CoursesController::class,'getStudents'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::post('/{course_id}/students', [CoursesController::class,'addStudent'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::delete('/{course_id}/students/{student_id}', [CoursesController::class,'removeStudent'])->middleware(ControlAccessMiddleware::class.':admin');
        });


        #create route group for lessons
        Route::group(['prefix' => 'lessons'], function () {
            Route::post('/', [LessonsController::class,'create'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::post('/{lesson_id}', [LessonsController::class,'update'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::delete('/{lesson_id}', [LessonsController::class,'delete'])->middleware(ControlAccessMiddleware::class.':admin');

            Route::get('/{lesson_id}', [LessonsController::class,'getLesson'])->middleware(ControlAccessMiddleware::class.':admin');
        });

        #create route group for workshops
        Route::group(['prefix' => 'workshops'], function () {
            Route::post('/', [WorkshopsController::class,'create'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::post('/{workshop_id}', [WorkshopsController::class,'update'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::delete('/{workshop_id}', [WorkshopsController::class,'delete'])->middleware(ControlAccessMiddleware::class.':admin');

            Route::get('/{workshop_id}', [WorkshopsController::class,'getWorkshop'])->middleware(ControlAccessMiddleware::class.':admin');
        });

        #create route group for exams
        Route::group(['prefix' => 'exams'], function () {
            Route::post('/', [ExamsController::class,'create'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::patch('/{exam_id}', [ExamsController::class,'update'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::delete('/{exam_id}', [ExamsController::class,'delete'])->middleware(ControlAccessMiddleware::class.':admin');


            Route::post('/{exam_id}/questions', [ExamsController::class,'addQuestion'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::get('/{exam_id}/questions', [ExamsController::class,'getQuestions'])->middleware(ControlAccessMiddleware::class.':admin');

            Route::get('/{exam_id}', [ExamsController::class,'getExam'])->middleware(ControlAccessMiddleware::class.':admin');
        
            Route::patch('/{exam_id}/questions/{question_id}', [ExamsController::class,'updateQuestion'])->middleware(ControlAccessMiddleware::class.':admin');      
            Route::delete('/{exam_id}/questions/{question_id}', [ExamsController::class,'deleteQuestion'])->middleware(ControlAccessMiddleware::class.':admin');

            Route::get('/{exam_id}/results', [ExamsController::class,'getResults'])->middleware(ControlAccessMiddleware::class.':admin');
        });

   

        #create route group for categories
        Route::group(['prefix' => 'categories'], function () {
            Route::post('/', [CategoriesController::class,'create'])->middleware(ControlAccessMiddleware::class.':admin')   ;
            Route::patch('/{category_id}', [CategoriesController::class,'update'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::delete('/{category_id}', [CategoriesController::class,'delete'])->middleware(ControlAccessMiddleware::class.':admin');

        });


        #create route group for orders
        Route::group(['prefix' => 'orders'], function () {
            Route::get('/all', [OrdersController::class,'getAll'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::get('/{order_id}', [OrdersController::class,'getOrderDetails'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::post('/{order_id}/accept', [OrdersController::class,'acceptOrder'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::post('/{order_id}/reject', [OrdersController::class,'rejectOrder'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::get('/{order_id}/installments', [OrdersController::class,'getInstallments'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::patch('/{installment_id}/update', [OrdersController::class,'updateInstallmentDetail'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::get('/installments/all', [OrdersController::class,'getAllInstallments'])->middleware(ControlAccessMiddleware::class.':admin');
      
            Route::post('/create', [OrdersController::class,'createOrder'])->middleware(ControlAccessMiddleware::class.':admin');
        }); 


        #create route group for discounts
        Route::group(['prefix' => 'discounts'], function () {
            Route::get('/', [DiscountsController::class,'getAll'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::post('/', [DiscountsController::class,'create'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::patch('/', [DiscountsController::class,'update'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::delete('/{discount_id}', [DiscountsController::class,'delete'])->middleware(ControlAccessMiddleware::class.':admin');
       
            Route::get('/user/{user_id}', [OrdersController::class,'getDiscountsForUser'])->middleware(ControlAccessMiddleware::class.':admin');
        });

        #create route group for coupons
        Route::group(['prefix' => 'coupons'], function () {
            Route::get('/', [CouponsController::class,'getAll'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::post('/', [CouponsController::class,'create'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::patch('/{coupon_id}', [CouponsController::class,'update'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::delete('/{coupon_id}', [CouponsController::class,'delete'])->middleware(ControlAccessMiddleware::class.':admin');
        });

        #create route group for movements
        Route::group(['prefix' => 'movements'], function () {
            Route::get('', [MovementsController::class,'getAll'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::get('/currencies', [MovementsController::class,'getCurrencies'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::get('/statistics', [MovementsController::class,'getStatistics'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::get('/period/{period}', [MovementsController::class,'getByPeriod'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::get('/year/{year}', [MovementsController::class,'getByYear'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::get('/course/{course_id}', [MovementsController::class,'getByCourse'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::get('/{movement_id}', [MovementsController::class,'getById'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::post('', [MovementsController::class,'create'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::put('/{movement_id}', [MovementsController::class,'update'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::delete('/{movement_id}', [MovementsController::class,'delete'])->middleware(ControlAccessMiddleware::class.':admin');
        });

        #create route group for accounts
        Route::group(['prefix' => 'accounts'], function () {
            Route::get('', [AccountsController::class,'getAll'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::get('/{account_id}', [AccountsController::class,'getById'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::get('/{account_id}/stats', [AccountsController::class,'getStats'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::post('', [AccountsController::class,'create'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::put('/{account_id}', [AccountsController::class,'update'])->middleware(ControlAccessMiddleware::class.':admin');
            Route::delete('/{account_id}', [AccountsController::class,'delete'])->middleware(ControlAccessMiddleware::class.':admin');
        });

       

    })->middleware(ControlAccessMiddleware::class.':admin');
