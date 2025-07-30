<?php

namespace App\Providers;

use App\Models\Chat;
use App\Policies\ChatPolicy;
use App\Services\ChatService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        Chat::class => ChatPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register the ChatService
        $this->app->singleton(ChatService::class, function ($app) {
            return new ChatService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register policies
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Set up model observers
        $this->registerObservers();

        Schema::defaultStringLength(191);
    }

    /**
     * Register model observers.
     */
    protected function registerObservers(): void
    {
        // Register model observers here
        // Example: Chat::observe(ChatObserver::class);
    }
}
