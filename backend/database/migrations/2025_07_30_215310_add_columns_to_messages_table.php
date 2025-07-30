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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['sender_id']);
            $table->dropForeign(['chat_id']);
            $table->dropColumn(['reactions', 'metadata', 'status', 'direction', 'sender_id', 'chat_id']);
        });
    }
};
