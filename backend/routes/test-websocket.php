<?php

use App\Events\MessageSent;
use App\Models\User;
use App\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Test WebSocket broadcast
Route::get('/test-broadcast', function () {
    // Create a test message if none exists
    $message = WhatsAppMessage::first();
    
    if (!$message) {
        $user = User::first();
        if (!$user) {
            $user = User::factory()->create();
        }
        
        $message = WhatsAppMessage::create([
            'sender' => $user->phone,
            'receiver' => '1234567890',
            'message' => 'Test message',
            'status' => 'sent',
            'direction' => 'outbound',
            'chat' => 'test-chat',
            'timestamp' => now(),
        ]);
    }
    
    // Broadcast the message
    broadcast(new MessageSent($message, 'test'))->toOthers();
    
    return response()->json(['status' => 'Broadcast sent']);
});
