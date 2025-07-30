# Laravel Reverb WebSocket Setup Guide

## Prerequisites

1. PHP 8.2 or higher
2. Laravel 10+ with Reverb installed
3. Composer dependencies installed

## Configuration

### 1. Environment Variables

Make sure your `.env` file contains the following Reverb configuration:

```
BROADCAST_DRIVER=reverb
REVERB_APP_ID=whatsapp-bot
REVERB_APP_KEY=whatsapp-bot-key
REVERB_APP_SECRET=whatsapp-bot-secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
```

### 2. Broadcasting Configuration

Ensure `config/broadcasting.php` has the Reverb connection configured:

```php
'connections' => [
    'reverb' => [
        'driver' => 'reverb',
        'key' => env('REVERB_APP_KEY'),
        'secret' => env('REVERB_APP_SECRET'),
        'app_id' => env('REVERB_APP_ID'),
        'options' => [
            'host' => env('REVERB_HOST', '127.0.0.1'),
            'port' => env('REVERB_PORT', 8080),
            'scheme' => env('REVERB_SCHEME', 'http'),
            'useTLS' => env('REVERB_SCHEME') === 'https',
        ],
    ],
],
```

### 3. Reverb Configuration

In `config/reverb.php`, ensure scaling is disabled since Redis is not running:

```php
'scaling' => [
    'enabled' => false, // Disable scaling since Redis is not running
    // ... other settings
],
```

## Starting the WebSocket Server

### Method 1: Using the Batch Script

Run the provided `start-reverb.bat` file:

```
start-reverb.bat
```

### Method 2: Direct Command

From the project root directory, run:

```
php artisan reverb:start --host=127.0.0.1 --port=8080 --debug
```

## Testing the WebSocket Connection

1. Start the Reverb server using one of the methods above
2. Visit `/websocket-test` in your browser
3. Click the "Connect" button
4. If the connection is successful, you should see "Connected to WebSocket server"
5. Try sending a test message or triggering a broadcast

## Troubleshooting

### Common Issues

1. **Reverb server terminates immediately**
   - Ensure scaling is disabled in `config/reverb.php` if Redis is not running
   - Check that the host and port are correct

2. **Authentication fails with 500 errors**
   - Verify that `REVERB_APP_KEY` and `REVERB_APP_SECRET` are correctly set in `.env`
   - Check the Laravel logs for detailed error messages

3. **CORS issues**
   - Ensure the `allowed_origins` in `config/reverb.php` includes your frontend domain

### Debugging Steps

1. Check Laravel logs: `storage/logs/laravel.log`
2. Enable debug mode in Reverb by adding `--debug` flag
3. Verify environment variables are loaded correctly
4. Test the authentication endpoint directly with a tool like Postman

## WebSocket Events

### Private Channels

Private channels require authentication and are prefixed with `private-`:

- Channel: `private-test-channel`
- Event: `test.message`

### Public Channels

Public channels don't require authentication:

- Channel: `test-channel`
- Event: `test.event`

## Frontend Integration

To integrate WebSockets in your Vue.js frontend:

1. Install Laravel Echo and Pusher JS:
   ```bash
   npm install --save laravel-echo pusher-js
   ```

2. Configure Laravel Echo in your `resources/js/bootstrap.js`:
   ```javascript
   import Echo from 'laravel-echo';
   import Pusher from 'pusher-js';
   
   window.Pusher = Pusher;
   
   window.Echo = new Echo({
       broadcaster: 'reverb',
       key: import.meta.env.VITE_REVERB_APP_KEY,
       wsHost: import.meta.env.VITE_REVERB_HOST,
       wsPort: import.meta.env.VITE_REVERB_PORT,
       wssPort: import.meta.env.VITE_REVERB_PORT,
       forceTLS: false,
       enabledTransports: ['ws'],
   });
   ```

3. Listen for events in your Vue components:
   ```javascript
   mounted() {
       window.Echo.private('private-test-channel')
           .listen('TestEvent', (e) => {
               console.log('Received test message:', e.message);
               // Update your component data
           });
   }
   ```
