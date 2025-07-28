<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// Test route that completely bypasses middleware
Route::any('/test-bypass', function (Request $request) {
    return response()->json([
        'message' => 'Test bypass endpoint is accessible',
        'method' => $request->method(),
        'input' => $request->all()
    ]);
})->withoutMiddleware([
    \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
]);
