<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Events\TestEvent;

class TriggerTestEvent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:trigger-event {message?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trigger a test WebSocket event';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $message = $this->argument('message') ?? 'Test message from command line';
        
        event(new TestEvent($message));
        
        $this->info("Test event triggered with message: {$message}");
        
        return 0;
    }
}
