<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Route::get('/test-tables', function () {
    try {
        $tables = DB::select('SELECT name FROM sqlite_master WHERE type="table"');
        $tableNames = array_column($tables, 'name');
        
        $tableStructures = [];
        foreach ($tableNames as $tableName) {
            $columns = Schema::getColumnListing($tableName);
            $tableStructures[$tableName] = $columns;
        }
        
        return response()->json([
            'tables' => $tableNames,
            'table_structures' => $tableStructures,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
});
