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
        // Fix foreign key for chats table
        if (Schema::hasTable('chats') && Schema::hasColumn('chats', 'last_message_id')) {
            Schema::table('chats', function (Blueprint $table) {
                $table->dropForeign(['last_message_id']);
                $table->foreign('last_message_id')
                      ->references('id')
                      ->on('messages')
                      ->nullOnDelete();
            });
        }
    
        // Fix foreign key for message_reads table
        if (Schema::hasTable('message_reads') && Schema::hasColumn('message_reads', 'message_id')) {
            Schema::table('message_reads', function (Blueprint $table) {
                $table->dropForeign(['message_id']);
                $table->foreign('message_id')
                      ->references('id')
                      ->on('messages')
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
