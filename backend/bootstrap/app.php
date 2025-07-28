<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Reverb\ReverbServiceProvider;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        ReverbServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        ]);
        
        // Completely disable CSRF middleware
        $middleware->remove(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        
        // Allow WebSocket connections from the same origin
        $middleware->web(\Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
