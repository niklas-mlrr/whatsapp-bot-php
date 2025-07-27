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
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('last_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->json('participants');
            $table->unsignedInteger('unread_count')->default(0);
            $table->boolean('is_group')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->boolean('is_muted')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['last_message_at', 'is_archived']);
            $table->index('is_group');
            
            // For searching participants
            $table->rawIndex(
                "(cast(json_extract(`participants`, '$[*]') as char(36) array))",
                'chats_participants_index'
            );
        });
        
        // Add full-text search index for chat names
        DB::statement('ALTER TABLE chats ADD FULLTEXT chats_name_fulltext (name)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};
