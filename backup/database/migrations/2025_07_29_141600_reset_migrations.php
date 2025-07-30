<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // This migration is a no-op as we'll handle the reset manually
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // This is intentionally left blank
    }

    /**
     * This method will be called by the migrate:fresh command
     */
    public function __destruct()
    {
        // This will be called when the migration is run
        if (app()->runningInConsole() && app('request')->has('--fresh')) {
            $this->cleanDatabase();
        }
    }

    /**
     * Clean up the database
     */
    protected function cleanDatabase()
    {
        $tables = [];
        
        // Get all tables in the database
        $result = DB::select('SHOW TABLES');
        
        // Get the database name from config
        $databaseName = DB::connection()->getDatabaseName();
        
        // Extract table names
        foreach ($result as $row) {
            $row = (array)$row;
            $tables[] = $row["Tables_in_{$databaseName}"];
        }

        if (empty($tables)) {
            return;
        }

        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Drop all tables
        foreach ($tables as $table) {
            if ($table !== 'migrations') { // Don't drop migrations table
                Schema::dropIfExists($table);
            }
        }

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        
        // Truncate migrations table
        DB::table('migrations')->truncate();
    }
};
