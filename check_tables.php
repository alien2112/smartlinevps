<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $tables = Illuminate\Support\Facades\DB::select('SHOW TABLES');
    echo "Tables in " . env('DB_DATABASE') . ":\n";
    echo "---------------------------------\n";
    $count = 0;
    foreach ($tables as $table) {
        foreach ($table as $key => $value) {
            echo $value . "\n";
            $count++;
        }
    }
    echo "---------------------------------\n";
    echo "Total Tables: " . $count . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
