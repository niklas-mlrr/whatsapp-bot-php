<?php

use Illuminate\Support\Facades\Route;
use App\Events\TestEvent;

// Route to trigger test event via HTTP
Route::get('/trigger-test-event', function () {
    event(new TestEvent('Test message from HTTP request'));
    return response()->json(['status' => 'success', 'message' => 'Test event triggered']);
});

Route::post('/trigger-test-event', function () {
    $message = request()->input('message', 'Test message from HTTP POST request');
    event(new TestEvent($message));
    return response()->json(['status' => 'success', 'message' => 'Test event triggered']);
});
