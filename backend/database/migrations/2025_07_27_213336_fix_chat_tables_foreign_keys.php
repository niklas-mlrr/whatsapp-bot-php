<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // First, drop the existing foreign key constraints if they exist
        if (Schema::hasTable('chats')) {
            Schema::table('chats', function (Blueprint $table) {
                // This will drop the foreign key constraint if it exists
                if (Schema::hasColumn('chats', 'last_message_id')) {
                    $table->dropForeign(['last_message_id']);
                }
            });
        }

        // Recreate the chats table with the correct foreign key constraints
        if (Schema::hasTable('chats')) {
            Schema::table('chats', function (Blueprint $table) {
                // Add the foreign key constraint with the correct table name
                if (Schema::hasColumn('chats', 'last_message_id')) {
                    $table->foreignId('last_message_id')
                          ->nullable()
                          ->constrained('messages')  // Changed from whatsapp_messages to messages
                          ->nullOnDelete();
                }
            });
        }

        // Update the message_reads table
        if (Schema::hasTable('message_reads')) {
            Schema::table('message_reads', function (Blueprint $table) {
                // Drop existing foreign key if it exists
                if (Schema::hasColumn('message_reads', 'message_id')) {
                    $table->dropForeign(['message_id']);
                }
                
                // Recreate with correct table reference
                $table->foreign('message_id')
                      ->references('id')
                      ->on('messages')  // Changed from whatsapp_messages to messages
                      ->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // Drop the foreign key constraints
        if (Schema::hasTable('chats') && Schema::hasColumn('chats', 'last_message_id')) {
            Schema::table('chats', function (Blueprint $table) {
                $table->dropForeign(['last_message_id']);
            });
        }

        if (Schema::hasTable('message_reads') && Schema::hasColumn('message_reads', 'message_id')) {
            Schema::table('message_reads', function (Blueprint $table) {
                $table->dropForeign(['message_id']);
            });
        }
    }
};
