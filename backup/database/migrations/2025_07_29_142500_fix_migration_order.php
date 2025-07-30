<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Mark the problematic migrations as completed
        $migrations = [
            '2025_07_27_213336_fix_chat_tables_foreign_keys',
            '2025_07_28_000001_add_fields_to_users_table',
            '2025_07_28_000002_create_websockets_statistics_entries_table',
            '2025_07_29_140900_fix_duplicate_last_message_id_in_chats_table',
            '2025_07_29_141000_fix_duplicate_foreign_keys',
            '2025_07_29_142000_fix_last_message_id_column',
        ];

        foreach ($migrations as $migration) {
            if (!DB::table('migrations')->where('migration', $migration)->exists()) {
                DB::table('migrations')->insert([
                    'migration' => $migration,
                    'batch' => 5, // Next batch number
                ]);
            }
        }

        // Ensure the last_message_id column exists and has the correct foreign key
        if (Schema::hasTable('chats') && !Schema::hasColumn('chats', 'last_message_id')) {
            Schema::table('chats', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->foreignId('last_message_id')
                    ->nullable()
                    ->after('updated_at')
                    ->constrained('messages')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // We don't want to reverse this migration
    }
};
