<?php

use Illuminate\Support\Facades\Route;

Route::get('/test-connection', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Connection to server successful',
        'time' => now()->toDateTimeString(),
        'app_key' => config('broadcasting.connections.reverb.key'),
        'app_secret' => !empty(config('broadcasting.connections.reverb.secret')) ? '***' : 'Not set',
        'app_id' => config('broadcasting.connections.reverb.app_id')
    ]);
});
