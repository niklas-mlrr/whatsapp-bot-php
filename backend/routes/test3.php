<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Route::get('/test-db-structure', function () {
    try {
        // Get all tables
        $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
        
        $result = [];
        
        foreach ($tables as $table) {
            $tableName = $table->name;
            $columns = DB::select("PRAGMA table_info($tableName)");
            $result[$tableName] = [
                'columns' => array_map(function($col) {
                    return [
                        'name' => $col->name,
                        'type' => $col->type,
                        'notnull' => $col->notnull,
                        'dflt_value' => $col->dflt_value,
                        'pk' => $col->pk,
                    ];
                }, $columns)
            ];
        }
        
        return response()->json($result);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
});
