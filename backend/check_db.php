<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Current tables:\n";
print_r(DB::select('SHOW TABLES'));

echo "\nMigrations table contents:\n";
try {
    print_r(DB::table('migrations')->get()->toArray());
} catch (Exception $e) {
    echo "Error: ".$e->getMessage()."\n";
}
