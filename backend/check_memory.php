<?php
// Check current memory limit
echo 'Current memory limit: ' . ini_get('memory_limit') . "\n";

// Try to set a higher memory limit
ini_set('memory_limit', '512M');
echo 'New memory limit: ' . ini_get('memory_limit') . "\n";

// Test if we can use more memory
$test = str_repeat('a', 10000000);
echo 'Memory usage after allocation: ' . memory_get_usage(true) / 1024 / 1024 . " MB\n";
?>
