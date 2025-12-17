<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// All 27 Egyptian Governorates with their capitals' coordinates
$governorates = [
    // Greater Cairo Region
    ['name' => 'القاهرة', 'name_en' => 'Cairo', 'lat' => 30.0444, 'lng' => 31.2357, 'radius' => 0.25],
    ['name' => 'الجيزة', 'name_en' => 'Giza', 'lat' => 30.0131, 'lng' => 31.2089, 'radius' => 0.30],
    ['name' => 'القليوبية', 'name_en' => 'Qalyubia', 'lat' => 30.2975, 'lng' => 31.2128, 'radius' => 0.25],
    
    // Alexandria Region
    ['name' => 'الإسكندرية', 'name_en' => 'Alexandria', 'lat' => 31.2001, 'lng' => 29.9187, 'radius' => 0.25],
    ['name' => 'البحيرة', 'name_en' => 'Beheira', 'lat' => 30.8481, 'lng' => 30.3436, 'radius' => 0.40],
    ['name' => 'مطروح', 'name_en' => 'Matrouh', 'lat' => 31.3543, 'lng' => 27.2373, 'radius' => 0.50],
    
    // Delta Region
    ['name' => 'الدقهلية', 'name_en' => 'Dakahlia', 'lat' => 31.0409, 'lng' => 31.3785, 'radius' => 0.30],
    ['name' => 'الشرقية', 'name_en' => 'Sharqia', 'lat' => 30.5877, 'lng' => 31.5020, 'radius' => 0.35],
    ['name' => 'الغربية', 'name_en' => 'Gharbia', 'lat' => 30.8754, 'lng' => 31.0335, 'radius' => 0.25],
    ['name' => 'كفر الشيخ', 'name_en' => 'Kafr El Sheikh', 'lat' => 31.1107, 'lng' => 30.9388, 'radius' => 0.30],
    ['name' => 'المنوفية', 'name_en' => 'Monufia', 'lat' => 30.5972, 'lng' => 30.9876, 'radius' => 0.25],
    ['name' => 'دمياط', 'name_en' => 'Damietta', 'lat' => 31.4175, 'lng' => 31.8144, 'radius' => 0.20],
    
    // Canal Zone
    ['name' => 'بورسعيد', 'name_en' => 'Port Said', 'lat' => 31.2653, 'lng' => 32.3019, 'radius' => 0.15],
    ['name' => 'الإسماعيلية', 'name_en' => 'Ismailia', 'lat' => 30.5965, 'lng' => 32.2715, 'radius' => 0.25],
    ['name' => 'السويس', 'name_en' => 'Suez', 'lat' => 29.9668, 'lng' => 32.5498, 'radius' => 0.20],
    
    // Upper Egypt
    ['name' => 'الفيوم', 'name_en' => 'Fayoum', 'lat' => 29.3084, 'lng' => 30.8428, 'radius' => 0.30],
    ['name' => 'بني سويف', 'name_en' => 'Beni Suef', 'lat' => 29.0661, 'lng' => 31.0994, 'radius' => 0.25],
    ['name' => 'المنيا', 'name_en' => 'Minya', 'lat' => 28.1099, 'lng' => 30.7503, 'radius' => 0.30],
    ['name' => 'أسيوط', 'name_en' => 'Asyut', 'lat' => 27.1783, 'lng' => 31.1859, 'radius' => 0.30],
    ['name' => 'سوهاج', 'name_en' => 'Sohag', 'lat' => 26.5591, 'lng' => 31.6948, 'radius' => 0.30],
    ['name' => 'قنا', 'name_en' => 'Qena', 'lat' => 26.1551, 'lng' => 32.7160, 'radius' => 0.30],
    ['name' => 'الأقصر', 'name_en' => 'Luxor', 'lat' => 25.6872, 'lng' => 32.6396, 'radius' => 0.20],
    ['name' => 'أسوان', 'name_en' => 'Aswan', 'lat' => 24.0889, 'lng' => 32.8998, 'radius' => 0.25],
    
    // Red Sea & Sinai
    ['name' => 'البحر الأحمر', 'name_en' => 'Red Sea', 'lat' => 27.2579, 'lng' => 33.8116, 'radius' => 0.50],
    ['name' => 'شمال سيناء', 'name_en' => 'North Sinai', 'lat' => 31.1311, 'lng' => 33.7980, 'radius' => 0.50],
    ['name' => 'جنوب سيناء', 'name_en' => 'South Sinai', 'lat' => 28.2308, 'lng' => 33.6177, 'radius' => 0.50],
    
    // New Valley
    ['name' => 'الوادي الجديد', 'name_en' => 'New Valley', 'lat' => 25.4390, 'lng' => 30.5586, 'radius' => 0.80],
];

// Additional major cities within governorates
$majorCities = [
    // Cairo area cities
    ['name' => 'مدينة نصر', 'name_en' => 'Nasr City', 'lat' => 30.0511, 'lng' => 31.3656, 'radius' => 0.08],
    ['name' => 'مصر الجديدة', 'name_en' => 'Heliopolis', 'lat' => 30.0877, 'lng' => 31.3228, 'radius' => 0.08],
    ['name' => 'المعادي', 'name_en' => 'Maadi', 'lat' => 29.9602, 'lng' => 31.2569, 'radius' => 0.08],
    ['name' => 'القاهرة الجديدة', 'name_en' => 'New Cairo', 'lat' => 30.0300, 'lng' => 31.4700, 'radius' => 0.15],
    ['name' => 'مدينتي', 'name_en' => 'Madinaty', 'lat' => 30.1075, 'lng' => 31.6306, 'radius' => 0.10],
    ['name' => 'الشروق', 'name_en' => 'El Shorouk', 'lat' => 30.1167, 'lng' => 31.6000, 'radius' => 0.08],
    ['name' => 'الرحاب', 'name_en' => 'El Rehab', 'lat' => 30.0603, 'lng' => 31.4903, 'radius' => 0.06],
    ['name' => 'العبور', 'name_en' => 'Obour', 'lat' => 30.2283, 'lng' => 31.4831, 'radius' => 0.08],
    ['name' => 'بدر', 'name_en' => 'Badr City', 'lat' => 30.1358, 'lng' => 31.7053, 'radius' => 0.10],
    
    // Giza area cities
    ['name' => '6 أكتوبر', 'name_en' => '6th of October', 'lat' => 29.9285, 'lng' => 30.9188, 'radius' => 0.18],
    ['name' => 'الشيخ زايد', 'name_en' => 'Sheikh Zayed', 'lat' => 30.0392, 'lng' => 30.9833, 'radius' => 0.10],
    ['name' => 'الحوامدية', 'name_en' => 'Hawamdeya', 'lat' => 29.9000, 'lng' => 31.2500, 'radius' => 0.08],
    
    // Industrial cities
    ['name' => 'العاشر من رمضان', 'name_en' => '10th of Ramadan', 'lat' => 30.2917, 'lng' => 31.7500, 'radius' => 0.15],
    ['name' => 'السادات', 'name_en' => 'Sadat City', 'lat' => 30.3667, 'lng' => 30.5167, 'radius' => 0.12],
    ['name' => 'برج العرب', 'name_en' => 'Borg El Arab', 'lat' => 30.8500, 'lng' => 29.5500, 'radius' => 0.10],
    
    // Tourist cities
    ['name' => 'شرم الشيخ', 'name_en' => 'Sharm El Sheikh', 'lat' => 27.9158, 'lng' => 34.3300, 'radius' => 0.15],
    ['name' => 'الغردقة', 'name_en' => 'Hurghada', 'lat' => 27.2579, 'lng' => 33.8116, 'radius' => 0.18],
    ['name' => 'دهب', 'name_en' => 'Dahab', 'lat' => 28.5000, 'lng' => 34.5167, 'radius' => 0.08],
    ['name' => 'مرسى علم', 'name_en' => 'Marsa Alam', 'lat' => 25.0590, 'lng' => 34.8910, 'radius' => 0.12],
    ['name' => 'العين السخنة', 'name_en' => 'Ain Sokhna', 'lat' => 29.5833, 'lng' => 32.3167, 'radius' => 0.10],
    ['name' => 'رأس سدر', 'name_en' => 'Ras Sedr', 'lat' => 29.5833, 'lng' => 32.7167, 'radius' => 0.08],
    
    // Coastal cities
    ['name' => 'العريش', 'name_en' => 'El Arish', 'lat' => 31.1311, 'lng' => 33.7980, 'radius' => 0.12],
    ['name' => 'رشيد', 'name_en' => 'Rosetta', 'lat' => 31.4000, 'lng' => 30.4167, 'radius' => 0.08],
    ['name' => 'مرسى مطروح', 'name_en' => 'Marsa Matrouh', 'lat' => 31.3543, 'lng' => 27.2373, 'radius' => 0.12],
    ['name' => 'العلمين', 'name_en' => 'El Alamein', 'lat' => 30.8333, 'lng' => 28.9500, 'radius' => 0.15],
    
    // Delta cities
    ['name' => 'المنصورة', 'name_en' => 'Mansoura', 'lat' => 31.0409, 'lng' => 31.3785, 'radius' => 0.12],
    ['name' => 'طنطا', 'name_en' => 'Tanta', 'lat' => 30.7865, 'lng' => 31.0004, 'radius' => 0.12],
    ['name' => 'الزقازيق', 'name_en' => 'Zagazig', 'lat' => 30.5877, 'lng' => 31.5020, 'radius' => 0.12],
    ['name' => 'المحلة الكبرى', 'name_en' => 'Mahalla El Kubra', 'lat' => 30.9686, 'lng' => 31.1636, 'radius' => 0.10],
    ['name' => 'شبين الكوم', 'name_en' => 'Shebin El Kom', 'lat' => 30.5578, 'lng' => 31.0131, 'radius' => 0.08],
    ['name' => 'بنها', 'name_en' => 'Benha', 'lat' => 30.4667, 'lng' => 31.1833, 'radius' => 0.10],
    ['name' => 'دسوق', 'name_en' => 'Desouk', 'lat' => 31.1306, 'lng' => 30.6450, 'radius' => 0.08],
];

$allZones = array_merge($governorates, $majorCities);

// Get the next readable_id
$maxReadableId = DB::table('zones')->max('readable_id') ?? 0;
$readableId = $maxReadableId + 1;

$inserted = 0;
$skipped = 0;

foreach ($allZones as $zone) {
    // Check if zone with this name already exists
    $exists = DB::table('zones')->where('name', $zone['name'])->exists();
    if ($exists) {
        echo "Skipped (exists): {$zone['name']} ({$zone['name_en']})\n";
        $skipped++;
        continue;
    }

    $lat = $zone['lat'];
    $lng = $zone['lng'];
    $r = $zone['radius'];

    // Create polygon (rectangular approximation)
    $polygon = sprintf(
        'POLYGON((%f %f, %f %f, %f %f, %f %f, %f %f))',
        $lng - $r, $lat - $r,
        $lng + $r, $lat - $r,
        $lng + $r, $lat + $r,
        $lng - $r, $lat + $r,
        $lng - $r, $lat - $r
    );

    try {
        DB::statement("
            INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, extra_fare_reason, created_at, updated_at)
            VALUES (?, ?, ?, ST_GeomFromText(?, 4326), 1, 0, 0, NULL, NOW(), NOW())
        ", [
            Str::uuid()->toString(),
            $zone['name'],
            $readableId,
            $polygon
        ]);

        echo "Created: {$zone['name']} ({$zone['name_en']})\n";
        $inserted++;
        $readableId++;
    } catch (Exception $e) {
        echo "Error: {$zone['name']}: {$e->getMessage()}\n";
    }
}

echo "\n==============================\n";
echo "Inserted: {$inserted} new zones\n";
echo "Skipped: {$skipped} (already existed)\n";
echo "Total zones now: " . DB::table('zones')->count() . "\n";
