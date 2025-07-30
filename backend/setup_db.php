<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Create tables
Schema::create('users', function($table) {
    $table->bigIncrements('id');
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->rememberToken();
    $table->timestamps();
});

Schema::create('cache', function($table) {
    $table->string('key')->primary();
    $table->mediumText('value');
    $table->integer('expiration');
});

// Record migrations
DB::table('migrations')->insert([
    ['migration' => '0001_01_01_000000_create_users_table', 'batch' => 1],
    ['migration' => '0001_01_01_000001_create_cache_table', 'batch' => 1]
]);

echo "Core tables created and migrations recorded successfully.\n";
