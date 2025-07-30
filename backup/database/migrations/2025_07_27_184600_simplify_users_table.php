<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Store the old users data temporarily
        $users = DB::table('users')->get();
        
        // Drop the old users table
        Schema::dropIfExists('users');
        
        // Create the simplified users table
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
        
        // Re-insert the first user with a default password if needed
        if ($users->isNotEmpty()) {
            DB::table('users')->insert([
                'id' => 1,
                'name' => 'Admin',
                'password' => Hash::make('admin123'), // Default password, should be changed after first login
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
        // This is a destructive migration, so the down method will just recreate the original structure
        Schema::dropIfExists('users');
        
        // Recreate the original users table structure
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }
};
