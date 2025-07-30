<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add columns to messages table if they don't exist
        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'reactions')) {
                $table->json('reactions')->nullable()->after('sending_time');
            }
            if (!Schema::hasColumn('messages', 'metadata')) {
                $table->json('metadata')->nullable()->after('reactions');
            }
            if (!Schema::hasColumn('messages', 'status')) {
                $table->string('status')->default('delivered')->after('metadata');
            }
            if (!Schema::hasColumn('messages', 'direction')) {
                $table->string('direction')->after('status');
            }
            if (!Schema::hasColumn('messages', 'sender_id')) {
                $table->foreignId('sender_id')->constrained('users')->after('direction');
            }
            if (!Schema::hasColumn('messages', 'chat_id')) {
                $table->foreignId('chat_id')->constrained('chats')->after('sender_id');
            }
        });

        // Add whatsapp_id to chat_user if it doesn't exist
        Schema::table('chat_user', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_user', 'whatsapp_id')) {
                $table->string('whatsapp_id')->nullable()->after('user_id');
            }
        });
    }

    public function down(): void
    {
        // For safety, we won't automatically drop columns in the down method
        // Manual schema changes should be handled separately if needed
    }
};
