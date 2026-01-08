<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Create test image paths (format matching existing vehicles)
$vehicleId = 'f592d439-378e-4fd0-9e88-845094091e54';
$timestamp = date('Y-m-d');
$hash1 = substr(md5(uniqid()), 0, 13);
$hash2 = substr(md5(uniqid()), 0, 13);

$images = [
    "/root/new/vehicle/document/{$timestamp}-{$hash1}.webp", // car_front
    "/root/new/vehicle/document/{$timestamp}-{$hash2}.webp", // car_back
];

// Update the vehicle with test images
DB::table('vehicles')
    ->where('id', $vehicleId)
    ->update([
        'documents' => json_encode($images)
    ]);

echo "✅ Test images added to vehicle: {$vehicleId}\n";
echo "Images:\n";
foreach($images as $img) {
    echo "  - {$img}\n";
}
echo "\n";
echo "Note: These are PLACEHOLDER paths. For real images:\n";
echo "1. Upload actual image files to the server\n";
echo "2. Update the documents field with real paths\n";
echo "3. Or use the mobile app to upload images properly\n";

