<?php

use App\Events\TestWebSocketEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/trigger-test-event', function (Request $request) {
    $message = $request->input('message', 'Hello from the server!');
    
    // Broadcast the test event
    broadcast(new TestWebSocketEvent($message));
    
    return response()->json([
        'status' => 'success',
        'message' => 'Test event broadcasted',
        'data' => $message
    ]);
});
