<?php

use App\Events\TestEvent;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

Route::get('/test-broadcast', function () {
    // Create a test user for the private channel
    $user = new stdClass();
    $user->id = 1;
    
    // Authenticate the user for the private channel
    Auth::loginUsingId(1);
    
    $message = 'Test message at ' . now()->toDateTimeString();
    
    // Broadcast the event
    broadcast(new TestEvent($message));
    
    return response()->json([
        'status' => 'success',
        'message' => 'Test message broadcasted to private-test-channel',
        'data' => $message,
        'user_id' => $user->id
    ]);
});
