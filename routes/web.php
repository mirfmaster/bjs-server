<?php

use App\Http\Controllers\Api\WorkerController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\UserProfileController;
use Illuminate\Support\Facades\Auth;
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
    return view('welcome');
});

Auth::routes();

// Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::get('/', function () {
    return redirect('/dashboard');
})->middleware('auth');
Route::get('/login', [LoginController::class, 'show'])->middleware('guest')->name('login');
Route::post('/login', [LoginController::class, 'login'])->middleware('guest')->name('login.perform');
Route::get('/dashboard', [HomeController::class, 'index'])->name('home')->middleware('auth');
Route::group(['middleware' => 'auth'], function () {
    Route::get('/virtual-reality', [PageController::class, 'vr'])->name('virtual-reality');
    Route::get('/rtl', [PageController::class, 'rtl'])->name('rtl');
    Route::get('/profile', [UserProfileController::class, 'show'])->name('profile');
    Route::post('/profile', [UserProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile-static', [PageController::class, 'profile'])->name('profile-static');
    Route::get('/sign-in-static', [PageController::class, 'signin'])->name('sign-in-static');
    Route::get('/sign-up-static', [PageController::class, 'signup'])->name('sign-up-static');

    Route::get('/workers', [WorkerController::class, 'index'])->name('workers');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::post('/orders/{order}/increment-priority', [OrderController::class, 'incrementPriority'])
        ->name('orders.increment-priority');
    Route::post('/orders/{order}/decrement-priority', [OrderController::class, 'decrementPriority'])
        ->name('orders.decrement-priority');
    Route::delete('/orders/{order}', [OrderController::class, 'destroy'])
        ->name('orders.destroy');
    Route::post('/orders/{order}/refill', [OrderController::class, 'refill'])->name('orders.refill');

    Route::get('/devices', [DeviceController::class, 'index'])->name('devices');
    Route::post('/devices/{device}/mode', [DeviceController::class, 'updateMode'])->name('devices.mode');

    Route::get('/api-docs', [PageController::class, 'apiDocs'])->name('api-docs');
    Route::post('/system/toggle-bjs-login', [SystemController::class, 'toggleBJSLoginState'])->name('system.toggle-bjs-login');
    Route::post('/system/toggle-global-work', [SystemController::class, 'toggleGlobalWork'])->name('system.toggle-global-work');

    Route::get('/{page}', [PageController::class, 'index'])->name('page');
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');
});
