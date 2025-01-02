<?php

use App\Http\Controllers\Api\WorkerController;
use App\Http\Controllers\OrderController;
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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('worker')->group(function () {
    Route::get('/info', [WorkerController::class, 'getInfo']);
    Route::get('/update-status', [WorkerController::class, 'updateStatus']);
});
Route::prefix('order')->group(function () {
    Route::get('/info', [OrderController::class, 'getInfo']);
});
