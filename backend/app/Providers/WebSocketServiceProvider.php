<?php

namespace App\Providers;

use App\Services\EnhancedWebSocketService;
use Illuminate\Support\ServiceProvider;

class WebSocketServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('websocket', function ($app) {
            return new EnhancedWebSocketService();
        });
        
        // Alias for backward compatibility
        $this->app->alias('websocket', EnhancedWebSocketService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
