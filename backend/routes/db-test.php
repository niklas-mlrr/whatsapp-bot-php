<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Route::get('/db-test', function () {
    try {
        $output = [];
        
        // Check if migrations table exists
        $output['migrations_table_exists'] = Schema::hasTable('migrations');
        
        if ($output['migrations_table_exists']) {
            $output['migrations'] = DB::table('migrations')->get();
        }
        
        // List all tables
        $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        $output['tables'] = array_column($tables, 'name');
        
        // Check users table structure if it exists
        if (in_array('users', $output['tables'])) {
            $output['users_columns'] = Schema::getColumnListing('users');
        }
        
        // Check if the problematic foreign key exists
        if (in_array('chats', $output['tables'])) {
            $output['chats_has_last_message_id'] = Schema::hasColumn('chats', 'last_message_id');
            
            if ($output['chats_has_last_message_id']) {
                $output['chats_last_message_id_nullable'] = !DB::selectOne("PRAGMA table_info(chats)", [])->notnull;
            }
        }
        
        return response()->json($output);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
});
