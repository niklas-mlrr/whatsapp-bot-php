<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    // Add the email column if it doesn't exist
    if (!Schema::hasColumn('users', 'email')) {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->unique()->nullable()->after('name');
        });
    }

    // Add the phone column if it doesn't exist
    if (!Schema::hasColumn('users', 'phone')) {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->unique()->nullable()->after('email');
        });
    }

    // Add the avatar column if it doesn't exist
    if (!Schema::hasColumn('users', 'avatar')) {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar')->nullable()->after('phone');
        });
    }

    // Add the status column if it doesn't exist
    if (!Schema::hasColumn('users', 'status')) {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 20)->default('offline')->after('avatar');
        });
    }

    // Add the last_seen_at column if it doesn't exist
    if (!Schema::hasColumn('users', 'last_seen_at')) {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('status');
        });
    }

    // Add the settings column if it doesn't exist
    if (!Schema::hasColumn('users', 'settings')) {
        Schema::table('users', function (Blueprint $table) {
            $table->json('settings')->nullable()->after('remember_token');
        });
    }

    // Add indexes if they don't exist
    Schema::table('users', function (Blueprint $table) {
        if (!Schema::hasIndex('users', 'users_phone_index')) {
            $table->index('phone', 'users_phone_index');
        }
        
        if (!Schema::hasIndex('users', 'users_status_index')) {
            $table->index('status', 'users_status_index');
        }
        
        if (!Schema::hasIndex('users', 'users_last_seen_at_index')) {
            $table->index('last_seen_at', 'users_last_seen_at_index');
        }
    });

    // Update existing users with default values if needed
    DB::table('users')->whereNull('email')->update([
        'email' => DB::raw('CONCAT("user_", id, "@whatsapp.local")')
    ]);

    DB::table('users')->whereNull('settings')->update([
        'settings' => json_encode([])
    ]);
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a non-destructive migration, so we don't need to do anything in the down method
    }
};
