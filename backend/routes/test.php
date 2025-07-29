<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Route::get('/test-db', function () {
    try {
        // Check if users table exists
        if (!Schema::hasTable('users')) {
            return response()->json(['error' => 'Users table does not exist'], 500);
        }
        
        // Get the columns in the users table
        $columns = Schema::getColumnListing('users');
        
        // Get the first user (if any)
        $user = DB::table('users')->first();
        
        return response()->json([
            'columns' => $columns,
            'first_user' => $user,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
});
