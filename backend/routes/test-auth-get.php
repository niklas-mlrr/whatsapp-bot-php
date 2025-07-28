<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// Add a GET route for testing purposes
Route::get('/test-auth', function (Request $request) {
    return response()->json([
        'message' => 'Test auth endpoint is accessible',
        'method' => 'GET'
    ]);
});
