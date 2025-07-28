<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

// WebSocket authentication endpoint for Laravel Reverb
Route::post('/broadcasting/auth', function (Request $request) {
    // Validate inputs
    $socketId = $request->input('socket_id');
    $channelName = $request->input('channel_name');
    
    if (!$socketId) {
        return response()->json([
            'error' => 'missing_socket_id',
            'message' => 'socket_id is required'
        ], 400);
    }
    
    if (!$channelName) {
        return response()->json([
            'error' => 'missing_channel_name',
            'message' => 'channel_name is required'
        ], 400);
    }
    
    // Log the request for debugging
    Log::debug('WebSocket auth request', [
        'socket_id' => $socketId,
        'channel_name' => $channelName,
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent()
    ]);
    
    // Get the app key and secret from config
    $appKey = config('reverb.apps.apps.0.key');
    $appSecret = config('reverb.apps.apps.0.secret');
    
    // Fallback to broadcasting config if Reverb config is not found
    if (!$appKey) {
        $appKey = config('broadcasting.connections.reverb.key');
    }
    
    if (!$appSecret) {
        $appSecret = config('broadcasting.connections.reverb.secret');
    }
    
    if (!$appKey) {
        Log::error('Missing Reverb app key in configuration');
        return response()->json([
            'error' => 'server_error',
            'message' => 'Server configuration error: Missing app key'
        ], 500);
    }
    
    if (!$appSecret) {
        Log::error('Missing Reverb app secret in configuration');
        return response()->json([
            'error' => 'server_error',
            'message' => 'Server configuration error: Missing app secret'
        ], 500);
    }
    
    // Generate the auth signature
    $stringToSign = $socketId . ':' . $channelName;
    $signature = hash_hmac('sha256', $stringToSign, $appSecret);
    
    // For private channels, we can add user data if needed
    $channelData = null;
    if (strpos($channelName, 'private-') === 0) {
        // For testing purposes, we'll create a simple user object
        // In a real application, you would authenticate the actual user
        $user = (object)['id' => 1];
        $channelData = json_encode([
            'user_id' => $user->id,
            'user_info' => [
                'name' => 'Test User',
                'time' => now()->toDateTimeString()
            ]
        ]);
    }
    
    $response = [
        'auth' => $appKey . ':' . $signature,
    ];
    
    if ($channelData) {
        $response['channel_data'] = $channelData;
    }
    
    Log::debug('WebSocket auth response', $response);
    
    return response()->json($response);
})->withoutMiddleware([
    \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
]);
