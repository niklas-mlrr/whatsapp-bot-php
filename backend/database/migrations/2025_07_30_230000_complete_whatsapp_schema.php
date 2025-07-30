<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create chats table if not exists
        if (!Schema::hasTable('chats')) {
            Schema::create('chats', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->boolean('is_group')->default(false);
                $table->json('metadata')->nullable();
                $table->foreignId('last_message_id')->nullable()->constrained('messages');
                $table->timestamps();
            });
        }

        // Create messages table if not exists
        if (!Schema::hasTable('messages')) {
            Schema::create('messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sender_id')->constrained('users');
                $table->foreignId('chat_id')->constrained('chats');
                $table->string('type');
                $table->text('content')->nullable();
                $table->string('media')->nullable();
                $table->string('mimetype')->nullable();
                $table->dateTime('sending_time')->nullable();
                $table->json('reactions')->nullable();
                $table->json('metadata')->nullable();
                $table->string('status')->default('delivered');
                $table->string('direction');
                $table->timestamps();
            });
        }

        // Create chat_user pivot table if not exists
        if (!Schema::hasTable('chat_user')) {
            Schema::create('chat_user', function (Blueprint $table) {
                $table->foreignId('chat_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('whatsapp_id')->nullable();
                $table->boolean('is_admin')->default(false);
                $table->timestamp('last_read_at')->nullable();
                $table->timestamps();
                $table->primary(['chat_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        // For safety, we won't automatically drop tables
        // Manual schema changes should be handled separately if needed
    }
};
