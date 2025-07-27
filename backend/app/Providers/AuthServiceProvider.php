<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\\Models\\Model' => 'App\\Policies\\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Define the 'sanctum' guard
        config(['auth.guards.sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ]]);

        // Set the default guard to 'sanctum' for API routes
        config(['auth.defaults.guard' => 'sanctum']);
    }
}
