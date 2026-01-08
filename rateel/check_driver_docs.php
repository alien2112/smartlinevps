<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$driverId = '449aec33-a410-477a-aa32-e3238566264d';
$driver = Modules\Common\Entities\Driver::find($driverId);

if (!$driver) {
    echo "Driver not found\n";
    exit(1);
}

echo "Driver: {$driver->name}\n";
echo "Email: {$driver->email}\n";
echo "Documents count: " . $driver->documents()->count() . "\n\n";

if ($driver->documents()->count() > 0) {
    echo "Documents:\n";
    foreach ($driver->documents as $doc) {
        echo "- Type: {$doc->document_type}\n";
        echo "  Path: {$doc->document_path}\n";
        echo "  Status: {$doc->status}\n";
        echo "  Created: {$doc->created_at}\n\n";
    }
} else {
    echo "No documents found.\n";
}
