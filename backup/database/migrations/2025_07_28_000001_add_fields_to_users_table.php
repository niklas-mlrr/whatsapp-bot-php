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
    Schema::table('users', function (Blueprint $table) {
        if (!Schema::hasColumn('users', 'phone')) {
            $table->string('phone', 20)->nullable()->after('name');
        }
        
        if (!Schema::hasColumn('users', 'status')) {
            $table->string('status', 20)->default('active');
        }
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
