<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('messages', function (Blueprint $table) {
            // Add new columns
            $table->enum('direction', ['incoming', 'outgoing'])->default('incoming')->after('type');
            $table->enum('status', ['pending', 'sent', 'delivered', 'read', 'failed'])->default('pending')->after('direction');
            $table->timestamp('read_at')->nullable()->after('sending_time');
            $table->json('reactions')->nullable()->after('mimetype');
            $table->json('metadata')->nullable()->after('reactions');
            
            // Add indexes
            $table->index('chat');
            $table->index('sender');
            $table->index('sending_time');
            $table->index(['chat', 'sending_time']);
            
            // Make content and media text type for larger content
            $table->text('content')->change();
            $table->text('media')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('messages', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex(['chat']);
            $table->dropIndex(['sender']);
            $table->dropIndex(['sending_time']);
            $table->dropIndex(['chat', 'sending_time']);
            
            // Drop columns
            $table->dropColumn(['direction', 'status', 'read_at', 'reactions', 'metadata']);
            
            // Revert column types
            $table->string('content')->change();
            $table->string('media')->nullable()->change();
        });
    }
};
