<?php

namespace App\Console\Commands;

use App\Models\Chat;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CleanupInactiveChats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chats:cleanup 
                            {--days=90 : Remove chats inactive for this many days} 
                            {--dry-run : List chats that would be deleted without actually deleting them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up inactive chats';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $cutoffDate = now()->subDays($days);

        $this->info("Finding chats inactive since {$cutoffDate->toDateString()}...");

        $query = Chat::where('last_message_at', '<', $cutoffDate)
            ->orWhere(function ($query) use ($cutoffDate) {
                $query->whereNull('last_message_at')
                    ->where('created_at', '<', $cutoffDate);
            });

        $count = $query->count();

        if ($count === 0) {
            $this->info('No inactive chats found.');
            return 0;
        }

        if ($dryRun) {
            $this->info("Found {$count} chats that would be deleted (dry run):");
            
            $query->withCount('messages')
                ->orderBy('last_message_at', 'asc')
                ->chunk(50, function ($chats) {
                    foreach ($chats as $chat) {
                        $lastActivity = $chat->last_message_at?->format('Y-m-d H:i:s') ?? 'Never';
                        $this->line("- #{$chat->id}: {$chat->name} (Messages: {$chat->messages_count}, Last Active: {$lastActivity})");
                    }
                });
                
            return 0;
        }

        if ($this->confirm("This will permanently delete {$count} inactive chats. Continue?", true)) {
            $bar = $this->output->createProgressBar($count);
            $bar->start();

            $query->chunkById(100, function ($chats) use ($bar) {
                foreach ($chats as $chat) {
                    $chat->delete();
                    $bar->advance();
                }
            });

            $bar->finish();
            $this->newLine(2);
            $this->info("Successfully deleted {$count} inactive chats.");
        } else {
            $this->info('Operation cancelled.');
        }

        return 0;
    }
}
