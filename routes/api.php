<?php

use App\Http\Controllers\API\AccountController;
use App\Http\Controllers\API\OrdersController;
use App\Http\Controllers\API\VendorController;
use App\Http\Controllers\ParserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});


Route::namespace('API\Auth')->group(function () {
    //Register route
    Route::post('/register', 'RegisterController@index');
    // Login route
    Route::post('/login', 'LoginController@index');
    //Forgot password
    Route::post('/forgot-password', 'ForgotPasswordController@index');
});


Route::get('/redirect/outlook', [AccountController::class,'redirectFromOutlook']);
Route::get('/redirect', [AccountController::class,'redirectFromGmail']);

Route::namespace('API')->group(function () {

    Route::middleware('auth:sanctum')->group(function () {
        //Profile routes
        Route::get('/profile', 'ProfileController@index');
        Route::put('/profile', 'ProfileController@update');
        Route::put('/change-password', 'ChangePasswordController@index');
        Route::get('/logout', 'ProfileController@logout');

        //Orders routes
        Route::get('/orders', 'OrdersController@index');
        Route::put('/orders/{id}/archive/add', [OrdersController::class,'addToArchive']);
        Route::put('/orders/{id}/archive/remove', [OrdersController::class,'removeFromArchive']);
        Route::get('/tracking-details', 'OrdersController@trackingDetails');

        Route::get('/emails', [AccountController::class,'index'])->name('emails');
        Route::get('/emails/refresh', [AccountController::class,'fetchNewMails'])->name('emails.get');
        Route::get('/user/emails', [AccountController::class,'userEmails'])->name('user.emails');
        Route::get('/email/{id}', [AccountController::class,'show'])->name('email.show');
        Route::get('/verify-email', [AccountController::class,'getGmailAccessToken'])->name('verify');
        Route::get('/outlook', [AccountController::class,'outlook'])->name('outlook');
        Route::get('/save', [AccountController::class,'fetchNewEmails'])->name('saveToDb');
        Route::delete('/account/{id}', [AccountController::class,'remove']);
        Route::post('/other', [AccountController::class,'other'])->name('other.providers');
        Route::apiResource('vendors', 'VendorController');
        Route::get('/parse-emails', [ParserController::class,'mailScraper'])->name('readEmails');
       
    });
});
