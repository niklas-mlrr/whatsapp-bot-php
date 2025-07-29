<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

DB::table('migrations')->insert([
    'migration' => '2025_07_29_142000_fix_last_message_id_column',
    'batch' => DB::table('migrations')->max('batch') + 1
]);

echo "Migration marked as completed successfully\n";
