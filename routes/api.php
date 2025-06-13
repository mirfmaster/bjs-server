<?php

use App\Http\Controllers\Api\TelegramWebhookController;
use App\Http\Controllers\Api\WorkerController;
use App\Http\Controllers\API\WorkerController as APIWorkerController;
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
    Route::post('/import', [WorkerController::class, 'import']);
    Route::get('/version', [WorkerController::class, 'getLatestVersion']);
});
Route::prefix('order')->group(function () {
    Route::get('/info', [OrderController::class, 'getInfo']);
});
Route::prefix('telegram')->group(function () {
    Route::get('/webhook-tiktok', [TelegramWebhookController::class, 'handleWebhook']);
});

Route::prefix('v2')->group(function () {
    Route::post('workers/upsert', [APIWorkerController::class, 'upsert'])
        ->name('workers.upsert');

    Route::apiResource('workers', APIWorkerController::class);
});
