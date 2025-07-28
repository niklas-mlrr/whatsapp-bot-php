<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use App\WebSockets\WebSocketHandler;

class StartWebSocketServer extends Command
{
    protected $signature = 'websockets:serve';
    protected $description = 'Start the WebSocket server';

    public function handle()
    {
        $this->info('Starting WebSocket server on port 6001...');
        
        $server = IoServer::factory(
            new HttpServer(
                new WsServer(
                    new WebSocketHandler()
                )
            ),
            6001
        );

        $server->run();
    }
}
