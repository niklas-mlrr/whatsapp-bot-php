<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InspectDatabase extends Command
{
    protected $signature = 'db:inspect';
    protected $description = 'Inspect the database structure';

    public function handle()
    {
        try {
            $this->info('Checking database connection...');
            
            // List all tables
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            $this->info('\nTables in the database:');
            
            foreach ($tables as $table) {
                $this->line("- {$table->name}");
                
                // List columns for each table
                $columns = DB::select("PRAGMA table_info({$table->name})");
                
                $this->table(
                    ['Name', 'Type', 'Nullable', 'Default', 'Primary Key'],
                    array_map(function($col) {
                        return [
                            'Name' => $col->name,
                            'Type' => $col->type,
                            'Nullable' => $col->notnull ? 'NO' : 'YES',
                            'Default' => $col->dflt_value ?? 'NULL',
                            'Primary Key' => $col->pk ? 'YES' : 'NO',
                        ];
                    }, $columns)
                );
                
                $this->line('');
            }
            
            // Check migrations table
            if (Schema::hasTable('migrations')) {
                $this->info('\nApplied migrations:');
                $migrations = DB::table('migrations')->get();
                $this->table(
                    ['Migration', 'Batch'],
                    $migrations->map(function($migration) {
                        return [
                            'Migration' => $migration->migration,
                            'Batch' => $migration->batch,
                        ];
                    })->toArray()
                );
            } else {
                $this->warn('\nMigrations table does not exist.');
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
