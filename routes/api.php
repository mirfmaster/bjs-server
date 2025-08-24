<?php

use App\Http\Controllers\API\OrderController as APIOrderController;
use App\Http\Controllers\API\SystemController;
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
    // NOTE: API for mass update
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

Route::prefix('v2')
    ->name('v2.')
    // ->middleware('throttle:2400,1')
    ->group(function () {
        Route::post('workers/upsert', [APIWorkerController::class, 'upsert'])
            ->name('workers.upsert');
        Route::get('workers/executors', [APIWorkerController::class, 'getExecutors']);

        Route::apiResource('workers', APIWorkerController::class);

        Route::post('orders/{order}/processing', [APIOrderController::class, 'processing']);
        Route::post('orders/{order}/processed', [APIOrderController::class, 'processed']);
        Route::post('orders/{order}/failed', [APIOrderController::class, 'failed']);
        Route::post('orders/{order}/duplicate', [APIOrderController::class, 'duplicate']);
        Route::patch('orders/{order}/status', [APIOrderController::class, 'updateStatus']);
        Route::apiResource('orders', APIOrderController::class);

        // SYSTEM
        Route::post('system/worker-version/{version}', [SystemController::class, 'addWorkerVersion']);
    });
