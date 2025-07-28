<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Add chat_id column if it doesn't exist
            if (!Schema::hasColumn('messages', 'chat_id')) {
                $table->foreignId('chat_id')->nullable()->after('chat')->constrained('chats')->nullOnDelete();
            }
            
            // Add sender_id column if it doesn't exist
            if (!Schema::hasColumn('messages', 'sender_id')) {
                $table->foreignId('sender_id')->nullable()->after('sender')->constrained('users')->nullOnDelete();
            }
            
            // We'll handle the chat_phone column in a separate migration
            // if needed, after we've confirmed the basic structure is working
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
