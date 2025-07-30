<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MonitorWebSockets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'websockets:monitor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor WebSocket connections and statistics';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting WebSocket monitoring...');
        $this->info('Press Ctrl+C to stop monitoring.');
        $this->newLine();

        $headers = ['Time', 'Event', 'Channel', 'Connections', 'Message Count'];
        $this->table($headers, []);

        // Listen for WebSocket events
        $this->listenForEvents();

        return 0;
    }

    /**
     * Listen for WebSocket events and display them in the console.
     *
     * @return void
     */
    protected function listenForEvents()
    {
        // Listen for connection events
        $this->laravel['events']->listen('connection.*', function ($event, $payload) {
            $connection = $payload[0] ?? null;
            $this->handleConnectionEvent($event, $connection);
        });

        // Listen for channel events
        $this->laravel['events']->listen('channel.*', function ($event, $payload) {
            $connection = $payload[0] ?? null;
            $channel = $payload[1] ?? null;
            $this->handleChannelEvent($event, $connection, $channel);
        });

        // Listen for message events
        $this->laravel['events']->listen('message.*', function ($event, $payload) {
            $connection = $payload[0] ?? null;
            $message = $payload[1] ?? null;
            $this->handleMessageEvent($event, $connection, $message);
        });

        // Keep the command running
        while (true) {
            $this->displayStatistics();
            sleep(5);
        }
    }

    /**
     * Handle connection events.
     *
     * @param  string  $event
     * @param  mixed  $connection
     * @return void
     */
    protected function handleConnectionEvent($event, $connection)
    {
        $eventName = str_replace('connection.', '', $event);
        $connectionId = $connection && method_exists($connection, 'getSocketId') ? $connection->getSocketId() : 'unknown';

        $this->table([], [
            [
                now()->format('H:i:s'),
                strtoupper($eventName),
                'N/A',
                $this->getConnectionCount(),
                $this->getMessageCount(),
            ],
        ]);
    }

    /**
     * Handle channel events.
     *
     * @param  string  $event
     * @param  mixed  $connection
     * @param  string  $channel
     * @return void
     */
    protected function handleChannelEvent($event, $connection, $channel)
    {
        $eventName = str_replace('channel.', '', $event);
        $channelName = $channel ?? 'unknown';

        $this->table([], [
            [
                now()->format('H:i:s'),
                strtoupper($eventName),
                $channelName,
                $this->getConnectionCount(),
                $this->getMessageCount(),
            ],
        ]);
    }

    /**
     * Handle message events.
     *
     * @param  string  $event
     * @param  mixed  $connection
     * @param  mixed  $message
     * @return void
     */
    protected function handleMessageEvent($event, $connection, $message)
    {
        $eventName = str_replace('message.', '', $event);
        $messageData = method_exists($message, 'getPayload') ?
            json_decode($message->getPayload(), true) : [];
        $channel = $messageData['channel'] ?? 'unknown';

        $this->table([], [
            [
                now()->format('H:i:s'),
                strtoupper($eventName),
                $channel,
                $this->getConnectionCount(),
                $this->getMessageCount(),
            ],
        ]);
    }

    /**
     * Display current WebSocket statistics.
     *
     * @return void
     */
    protected function displayStatistics()
    {
        $this->table([], [
            [
                now()->format('H:i:s'),
                'STATS',
                'All Channels',
                $this->getConnectionCount(),
                $this->getMessageCount(),
            ],
        ]);
    }

    /**
     * Get the current number of WebSocket connections.
     *
     * @return int
     */
    protected function getConnectionCount(): int
    {
        try {
            // Alternative way to get connection count if the table exists
            return DB::table('websockets_statistics_entries')
                ->where('created_at', '>', now()->subMinutes(5))
                ->sum('peak_connection_count') ?: 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get the total number of messages processed.
     *
     * @return int
     */
    protected function getMessageCount(): int
    {
        try {
            return DB::table('websockets_statistics_entries')
                ->sum('websocket_message_count') +
                DB::table('websockets_statistics_entries')
                    ->sum('api_message_count');
        } catch (\Exception $e) {
            return 0;
        }
    }
}
