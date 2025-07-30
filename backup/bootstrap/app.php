<?php

$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

// Initialize facades before loading routes
Illuminate\Support\Facades\Facade::setFacadeApplication($app);

// Load configuration
$app->bootstrapWith([
    'Illuminate\Foundation\Bootstrap\LoadConfiguration',
]);

// Load routes
$app->router->group([], function ($router) {
    require __DIR__.'/../routes/web.php';
    require __DIR__.'/../routes/api.php';
    require __DIR__.'/../routes/webhook.php';
});

return $app;
