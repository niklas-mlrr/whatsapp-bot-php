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
        Schema::table('users', function (Blueprint $table) {
            // Add phone number (unique)
            $table->string('phone', 20)->unique()->nullable()->after('email');
            
            // Add avatar path
            $table->string('avatar')->nullable()->after('phone');
            
            // Add user status (online, offline, away, etc.)
            $table->string('status', 20)->default('offline')->after('avatar');
            
            // Track when user was last seen
            $table->timestamp('last_seen_at')->nullable()->after('status');
            
            // JSON settings field for user preferences
            $table->json('settings')->nullable()->after('remember_token');
            
            // Indexes
            $table->index('phone');
            $table->index('status');
            $table->index('last_seen_at');
        });
        
        // Create chat_user pivot table for many-to-many relationship
        Schema::create('chat_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chat_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_admin')->default(false);
            $table->timestamp('muted_until')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            // Ensure each user can only be in a chat once
            $table->unique(['user_id', 'chat_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_user');
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'avatar',
                'status',
                'last_seen_at',
                'settings',
            ]);
            
            $table->dropIndex(['phone']);
            $table->dropIndex(['status']);
            $table->dropIndex(['last_seen_at']);
        });
    }
};
