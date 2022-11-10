<?php

use Illuminate\Support\Facades\Route;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    echo phpinfo(); die;
    return view('welcome');
});

Auth::routes();

Route::post('/reset-password', 'API\Auth\ResetPasswordController@index');

Route::get('/home', 'HomeController@index')->name('home');


//Logout controller routes
Route::get('/logout', '\App\Http\Controllers\Auth\LoginController@logout');

Route::get('/clear-cache', function () {
    $exitCode = Artisan::call('cache:clear');
    // return what you want
});
// 

Route::get('/parse-emails', 'ParserController@mailScraper');