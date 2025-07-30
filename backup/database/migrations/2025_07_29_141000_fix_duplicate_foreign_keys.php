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
        // This is a placeholder to ensure our previous migration runs first
        // The actual work is done in the previous migration
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // No need to do anything on down
    }

    /**
     * Get the foreign key constraints for a table
     */
    protected function getForeignKeyList($tableName)
    {
        return DB::select(
            DB::raw(
                "SELECT CONSTRAINT_NAME 
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_NAME = '{$tableName}'
                AND REFERENCED_TABLE_NAME IS NOT NULL"
            )
        );
    }
};
