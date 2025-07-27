<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Http\Controllers\Api\WhatsAppMessageController;
use App\Http\Controllers\Api\MessageStatusController;
use App\Http\Controllers\WebSocketController;

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

// Public routes
Route::post('/test', function () {
    return response()->json(['status' => 'ok']);
});

// WebSocket authentication
Route::post('/broadcasting/auth', [WebSocketController::class, 'authenticate'])
    ->middleware('auth:api');

// WebSocket webhook (for presence/status updates)
Route::post('/broadcasting/webhook', [WebSocketController::class, 'webhook']);

// Webhook endpoint (public)
Route::post('/whatsapp-webhook', [WhatsAppWebhookController::class, 'handle']);

// Message routes
Route::apiResource('messages', WhatsAppMessageController::class)
    ->only(['index', 'show', 'destroy', 'store']);

// Chat management
Route::get('/chats', [WhatsAppMessageController::class, 'chats']);
Route::post('/upload', [WhatsAppMessageController::class, 'upload']);

// Protected routes (require authentication)
Route::middleware('auth:api')->group(function () {
    // Message status and reactions
    Route::post('/messages/{message}/read', [MessageStatusController::class, 'markAsRead'])
        ->name('messages.read');
    
    Route::post('/messages/{message}/status', [MessageStatusController::class, 'updateStatus'])
        ->name('messages.status');
    
    Route::post('/messages/{message}/reactions', [MessageStatusController::class, 'addReaction'])
        ->name('messages.reactions.add');
    
    Route::delete('/messages/{message}/reactions/{userId}', [MessageStatusController::class, 'removeReaction'])
        ->name('messages.reactions.remove');
    
    // Chat management
    Route::prefix('chats')->group(function () {
        // Create a new direct chat
        Route::post('/direct', [ChatController::class, 'createDirectChat']);
        
        // Create a new group chat
        Route::post('/group', [ChatController::class, 'createGroupChat']);
        
        // Update chat details
        Route::put('/{chat}', [ChatController::class, 'update']);
        
        // Add participants to a group chat
        Route::post('/{chat}/participants', [ChatController::class, 'addParticipants']);
        
        // Remove participants from a group chat
        Route::delete('/{chat}/participants', [ChatController::class, 'removeParticipants']);
        
        // Leave a chat
        Route::post('/{chat}/leave', [ChatController::class, 'leaveChat']);
        
        // Mute/unmute chat
        Route::post('/{chat}/mute', [ChatController::class, 'toggleMute']);
        
        // Mark chat as read
        Route::post('/{chat}/read', [ChatController::class, 'markAsRead']);
        
        // Get chat messages
        Route::get('/{chat}/messages', [ChatController::class, 'messages']);
    });
    
    // User presence
    Route::post('/presence/online', [PresenceController::class, 'setOnline']);
    Route::post('/presence/away', [PresenceController::class, 'setAway']);
    Route::post('/presence/typing/{chat}', [PresenceController::class, 'setTyping']);
});
