<?php

return [
    /*
     * Set a custom dashboard configuration
     */
    'dashboard' => [
        'port' => env('LARAVEL_WEBSOCKETS_PORT', 6001),
    ],

    /*
     * This package comes with multi tenancy out of the box. Here you can
     * configure a different class for each model that needs tenant scoping.
     */
    'tenant' => [
        'enabled' => false,
    ],

    /*
     * This array contains the hosts of which you want to allow incoming requests.
     * This allows you to secure your websocket server.
     */
    'allowed_origins' => [
        //
    ],

    /*
     * The maximum request size in kilobytes that is allowed for an incoming WebSocket request.
     */
    'max_request_size_in_kb' => 250,

    /*
     * This path will be used to register the necessary routes for the package.
     */
    'path' => 'laravel-websockets',

    /*
     * Dashboard routes middleware.
     *
     * These middleware will be assigned to every dashboard route, giving you the chance
     * to add your own middleware to this list or change any of the existing middleware.
     */
    'middleware' => [
        'web',
        \App\Http\Middleware\Authenticate::class,
    ],

    'statistics' => [
        /*
         * This model will be used to store the statistics of the WebSockets server.
         * The only requirement is that the model should extend
         * `\BeyondCode\LaravelWebSockets\Statistics\Models\WebSocketsStatistics`.
         */
        'model' => \BeyondCode\LaravelWebSockets\Statistics\Models\WebSocketsStatistics::class,

        /*
         * Here you can specify the interval in seconds at which statistics should be logged.
         */
        'interval_in_seconds' => 60,

        /*
         * When the clean-command is executed, all recorded statistics older than
         * the number of days specified here will be deleted.
         */
        'delete_statistics_older_than_days' => 60,

        /*
         * Use an DNS resolver to make the requests to the statistics logger
         * default is to resolve everything to 127.0.0.1.
         */
        'perform_dns_lookup' => false,
    ],

    /*
     * Define the optional SSL context for your WebSocket connections.
     * You can see all available options at: http://php.net/manual/en/context.ssl.php.
     */
    'ssl' => [
        /*
         * Path to local certificate file on the filesystem. It must be a PEM encoded file which
         * contains your certificate and private key. It can optionally contain the
         * certificate chain of issuers. The private key also may be contained
         * in a separate file specified by local_pk.
         */
        'local_cert' => env('LARAVEL_WEBSOCKETS_SSL_LOCAL_CERT', null),

        /*
         * Path to local private key file on the filesystem in case of separate files for
         * certificate (local_cert) and private key.
         */
        'local_pk' => env('LARAVEL_WEBSOCKETS_SSL_LOCAL_PK', null),

        /*
         * Passphrase for your local_cert file.
         */
        'passphrase' => env('LARAVEL_WEBSOCKETS_SSL_PASSPHRASE', null),
    ],

    /*
     * Channel Manager
     * This class handles how channel persistence is handled.
     * By default, persistence is stored in an array by the running webserver.
     * The only requirement is that the class should implement
     * `ChannelManager` interface provided by this package.
     */
    'channel_manager' => \BeyondCode\LaravelWebSockets\WebSockets\Channels\ChannelManagers\ArrayChannelManager::class,

    /*
     * Here you can define the class that should be used to handle the WebSocket connections.
     * The default driver will use the `Ratchet` WebSocket server, but you can replace it with
     * any custom class that implements `MessageComponentInterface`.
     */
    'replication' => [
        'enabled' => false,
        'handler' => \BeyondCode\LaravelWebSockets\Server\Logger\HttpStatisticsLogger::class,
    ],

    /*
     * This class is responsible for finding the users. The default provider
     * will use the user model with the given guard and the given fields.
     */
    'users' => [
        'provider' => 'users',
        'model' => \App\Models\User::class,
        'field' => 'id',
    ],

    /*
     * This class is responsible for finding the users. The default provider
     * will use the user model with the given guard and the given fields.
     */
    'apps' => [
        [
            'id' => env('PUSHER_APP_ID'),
            'name' => env('APP_NAME'),
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'path' => env('PUSHER_APP_PATH'),
            'capacity' => null,
            'enable_client_messages' => true,
            'enable_statistics' => true,
        ],
    ],

    /*
     * This class is responsible for finding the users. The default provider
     * will use the user model with the given guard and the given fields.
     */
    'statistics' => [
        'model' => \BeyondCode\LaravelWebSockets\Statistics\Models\WebSocketsStatistics::class,
        'interval_in_seconds' => 60,
        'delete_statistics_older_than_days' => 60,
    ],
];
