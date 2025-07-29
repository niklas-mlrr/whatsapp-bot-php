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
        // First, check if the chats table exists
        if (!Schema::hasTable('chats')) {
            return;
        }

        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Check if the column already exists
        if (!Schema::hasColumn('chats', 'last_message_id')) {
            // Add the column if it doesn't exist
            Schema::table('chats', function (Blueprint $table) {
                $table->foreignId('last_message_id')
                      ->nullable()
                      ->after('updated_at')
                      ->constrained('messages')
                      ->nullOnDelete();
            });
        } else {
            // If the column exists, just make sure the foreign key is set up correctly
            Schema::table('chats', function (Blueprint $table) {
                // Drop any existing foreign key constraints on this column
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                $tableName = $table->getTable();
                $indexes = $sm->listTableForeignKeys($tableName);
                
                foreach ($indexes as $index) {
                    if (in_array('last_message_id', $index->getColumns())) {
                        $constraintName = $index->getName();
                        $table->dropForeign($constraintName);
                    }
                }
                
                // Add the foreign key constraint
                $table->foreign('last_message_id')
                      ->references('id')
                      ->on('messages')
                      ->nullOnDelete();
            });
        }

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // We don't want to reverse this migration as it's fixing issues
    }
};
