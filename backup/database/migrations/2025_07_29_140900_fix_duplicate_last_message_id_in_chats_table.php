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
        // Check if the column already exists before trying to add it
        Schema::table('chats', function (Blueprint $table) {
            if (!Schema::hasColumn('chats', 'last_message_id')) {
                $table->foreignId('last_message_id')
                    ->nullable()
                    ->after('updated_at')
                    ->constrained('whats_app_messages')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // We won't drop the column in the down method to be safe
        // If you need to rollback, you should create a new migration
    }
};
