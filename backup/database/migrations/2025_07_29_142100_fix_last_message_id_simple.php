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

        try {
            // Check if the column exists
            $columns = DB::select('SHOW COLUMNS FROM chats');
            $hasLastMessageId = false;
            
            foreach ($columns as $column) {
                if ($column->Field === 'last_message_id') {
                    $hasLastMessageId = true;
                    break;
                }
            }

            if (!$hasLastMessageId) {
                // Add the column if it doesn't exist
                DB::statement('ALTER TABLE chats ADD COLUMN last_message_id BIGINT UNSIGNED NULL AFTER updated_at');
            }

            // Add the foreign key constraint if it doesn't exist
            $foreignKeys = DB::select(
                "SELECT * FROM information_schema.TABLE_CONSTRAINTS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'chats' 
                AND CONSTRAINT_TYPE = 'FOREIGN_KEY'
                AND CONSTRAINT_NAME LIKE '%last_message_id%'"
            );

            if (empty($foreignKeys)) {
                DB::statement('ALTER TABLE chats ADD CONSTRAINT fk_chats_last_message_id 
                    FOREIGN KEY (last_message_id) REFERENCES messages(id) ON DELETE SET NULL');
            }

        } catch (\Exception $e) {
            // Log the error but don't fail the migration
            \Log::error('Error in migration: ' . $e->getMessage());
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
