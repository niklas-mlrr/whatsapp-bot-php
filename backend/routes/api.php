<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Http\Controllers\Api\WhatsAppMessageController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/test', function () {
    return response()->json(['status' => 'ok']);
});

Route::post('/whatsapp-webhook', [WhatsAppWebhookController::class, 'handle']);
Route::apiResource('messages', WhatsAppMessageController::class)->only(['index', 'show', 'destroy', 'store']);
Route::get('/chats', [WhatsAppMessageController::class, 'chats']);
Route::post('/upload', [WhatsAppMessageController::class, 'upload']);
