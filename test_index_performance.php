<?php

/**
 * Database Index Performance Test
 *
 * Tests query performance BEFORE and AFTER applying indexes
 * Run this script twice:
 * 1. Before applying indexes (baseline)
 * 2. After applying indexes (to see improvement)
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Modules\TripManagement\Entities\TripRequest;

echo "================================================================================\n";
echo "DATABASE INDEX PERFORMANCE TEST\n";
echo "================================================================================\n";
echo "Database: " . config('database.connections.mysql.database') . "\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
echo "================================================================================\n\n";

// Store results
$results = [];

/**
 * Test 1: Trip Status Query
 * This is one of the most common queries
 */
echo "Test 1: Trip Status Query (pending rides)\n";
echo "Expected index: idx_trips_status_created\n";
echo "-------------------------------------------\n";

DB::statement('SET profiling = 1');
$start = microtime(true);

$trips = DB::table('trip_requests')
    ->where('current_status', 'pending')
    ->orderBy('created_at', 'desc')
    ->limit(20)
    ->get();

$end = microtime(true);
$time1 = ($end - $start) * 1000;

echo "Query time: " . round($time1, 2) . "ms\n";
echo "Rows returned: " . count($trips) . "\n";

// Get EXPLAIN
$explain = DB::select("
    EXPLAIN SELECT * FROM trip_requests
    WHERE current_status = 'pending'
    ORDER BY created_at DESC
    LIMIT 20
");
echo "EXPLAIN type: " . $explain[0]->type . "\n";
echo "EXPLAIN key: " . ($explain[0]->key ?? 'NULL (no index!)') . "\n";
echo "EXPLAIN rows: " . $explain[0]->rows . "\n\n";

$results['Trip Status Query'] = [
    'time' => $time1,
    'rows_scanned' => $explain[0]->rows,
    'index_used' => $explain[0]->key ?? 'none'
];

/**
 * Test 2: Driver Pending Rides by Zone
 * Critical for driver app performance
 */
echo "Test 2: Driver Pending Rides by Zone\n";
echo "Expected index: idx_trips_zone_status\n";
echo "-------------------------------------------\n";

// Get a zone_id from database
$zoneId = DB::table('trip_requests')->whereNotNull('zone_id')->value('zone_id');

if ($zoneId) {
    $start = microtime(true);

    $trips = DB::table('trip_requests')
        ->where('zone_id', $zoneId)
        ->where('current_status', 'pending')
        ->limit(20)
        ->get();

    $end = microtime(true);
    $time2 = ($end - $start) * 1000;

    echo "Query time: " . round($time2, 2) . "ms\n";
    echo "Rows returned: " . count($trips) . "\n";

    $explain = DB::select("
        EXPLAIN SELECT * FROM trip_requests
        WHERE zone_id = ? AND current_status = 'pending'
        LIMIT 20
    ", [$zoneId]);

    echo "EXPLAIN type: " . $explain[0]->type . "\n";
    echo "EXPLAIN key: " . ($explain[0]->key ?? 'NULL (no index!)') . "\n";
    echo "EXPLAIN rows: " . $explain[0]->rows . "\n\n";

    $results['Pending Rides by Zone'] = [
        'time' => $time2,
        'rows_scanned' => $explain[0]->rows,
        'index_used' => $explain[0]->key ?? 'none'
    ];
} else {
    echo "No zone_id found, skipping test\n\n";
}

/**
 * Test 3: Customer Trip History
 * Common in customer app
 */
echo "Test 3: Customer Trip History\n";
echo "Expected index: idx_trips_customer\n";
echo "-------------------------------------------\n";

$customerId = DB::table('trip_requests')->whereNotNull('customer_id')->value('customer_id');

if ($customerId) {
    $start = microtime(true);

    $trips = DB::table('trip_requests')
        ->where('customer_id', $customerId)
        ->orderBy('created_at', 'desc')
        ->limit(20)
        ->get();

    $end = microtime(true);
    $time3 = ($end - $start) * 1000;

    echo "Query time: " . round($time3, 2) . "ms\n";
    echo "Rows returned: " . count($trips) . "\n";

    $explain = DB::select("
        EXPLAIN SELECT * FROM trip_requests
        WHERE customer_id = ?
        ORDER BY created_at DESC
        LIMIT 20
    ", [$customerId]);

    echo "EXPLAIN type: " . $explain[0]->type . "\n";
    echo "EXPLAIN key: " . ($explain[0]->key ?? 'NULL (no index!)') . "\n";
    echo "EXPLAIN rows: " . $explain[0]->rows . "\n\n";

    $results['Customer Trip History'] = [
        'time' => $time3,
        'rows_scanned' => $explain[0]->rows,
        'index_used' => $explain[0]->key ?? 'none'
    ];
} else {
    echo "No customer_id found, skipping test\n\n";
}

/**
 * Test 4: User Location by Zone
 * Used for finding available drivers
 */
echo "Test 4: User Locations by Zone\n";
echo "Expected index: idx_location_zone_type\n";
echo "-------------------------------------------\n";

$zoneId = DB::table('user_last_locations')->whereNotNull('zone_id')->value('zone_id');

if ($zoneId) {
    $start = microtime(true);

    $locations = DB::table('user_last_locations')
        ->where('zone_id', $zoneId)
        ->where('type', 'driver')
        ->limit(20)
        ->get();

    $end = microtime(true);
    $time4 = ($end - $start) * 1000;

    echo "Query time: " . round($time4, 2) . "ms\n";
    echo "Rows returned: " . count($locations) . "\n";

    $explain = DB::select("
        EXPLAIN SELECT * FROM user_last_locations
        WHERE zone_id = ? AND type = 'driver'
        LIMIT 20
    ", [$zoneId]);

    echo "EXPLAIN type: " . $explain[0]->type . "\n";
    echo "EXPLAIN key: " . ($explain[0]->key ?? 'NULL (no index!)') . "\n";
    echo "EXPLAIN rows: " . $explain[0]->rows . "\n\n";

    $results['Location by Zone'] = [
        'time' => $time4,
        'rows_scanned' => $explain[0]->rows,
        'index_used' => $explain[0]->key ?? 'none'
    ];
} else {
    echo "No zone_id in user_last_locations, skipping test\n\n";
}

/**
 * Test 5: User Login by Phone
 * Critical for authentication
 */
echo "Test 5: User Login by Phone\n";
echo "Expected index: idx_users_phone_active\n";
echo "-------------------------------------------\n";

$phone = DB::table('users')->whereNotNull('phone')->value('phone');

if ($phone) {
    $start = microtime(true);

    $user = DB::table('users')
        ->where('phone', $phone)
        ->where('is_active', 1)
        ->first();

    $end = microtime(true);
    $time5 = ($end - $start) * 1000;

    echo "Query time: " . round($time5, 2) . "ms\n";
    echo "User found: " . ($user ? 'Yes' : 'No') . "\n";

    $explain = DB::select("
        EXPLAIN SELECT * FROM users
        WHERE phone = ? AND is_active = 1
        LIMIT 1
    ", [$phone]);

    echo "EXPLAIN type: " . $explain[0]->type . "\n";
    echo "EXPLAIN key: " . ($explain[0]->key ?? 'NULL (no index!)') . "\n";
    echo "EXPLAIN rows: " . $explain[0]->rows . "\n\n";

    $results['User Login'] = [
        'time' => $time5,
        'rows_scanned' => $explain[0]->rows,
        'index_used' => $explain[0]->key ?? 'none'
    ];
} else {
    echo "No phone found, skipping test\n\n";
}

/**
 * Test 6: Check for Spatial Index on user_last_locations
 */
echo "Test 6: Spatial Index Check\n";
echo "Expected: idx_location_point (SPATIAL)\n";
echo "-------------------------------------------\n";

$spatialIndexes = DB::select("
    SHOW INDEX FROM user_last_locations
    WHERE Key_name = 'idx_location_point'
");

if (!empty($spatialIndexes)) {
    echo "✓ Spatial index exists: idx_location_point\n";
    echo "  Index type: " . $spatialIndexes[0]->Index_type . "\n";
    echo "  Column: " . $spatialIndexes[0]->Column_name . "\n\n";

    $results['Spatial Index'] = [
        'exists' => true,
        'type' => $spatialIndexes[0]->Index_type
    ];
} else {
    echo "✗ Spatial index NOT found (will be created)\n";
    echo "  Current lat/lng columns are VARCHAR (inefficient)\n\n";

    $results['Spatial Index'] = [
        'exists' => false
    ];
}

/**
 * Summary
 */
echo "================================================================================\n";
echo "SUMMARY\n";
echo "================================================================================\n\n";

$totalTime = 0;
$totalRowsScanned = 0;
$indexesUsed = 0;

foreach ($results as $test => $data) {
    if (isset($data['time'])) {
        $totalTime += $data['time'];
        $totalRowsScanned += $data['rows_scanned'];
        if ($data['index_used'] !== 'none' && !str_starts_with($data['index_used'], 'PRIMARY')) {
            $indexesUsed++;
        }

        $status = ($data['index_used'] !== 'none') ? '✓' : '✗';
        $color = ($data['index_used'] !== 'none') ? '' : ' (NO INDEX!)';

        echo "{$status} {$test}:\n";
        echo "   Time: " . round($data['time'], 2) . "ms\n";
        echo "   Rows scanned: " . $data['rows_scanned'] . "\n";
        echo "   Index used: " . $data['index_used'] . $color . "\n\n";
    }
}

if (isset($results['Spatial Index'])) {
    $status = $results['Spatial Index']['exists'] ? '✓' : '✗';
    echo "{$status} Spatial Index: " . ($results['Spatial Index']['exists'] ? 'EXISTS' : 'NOT FOUND') . "\n\n";
}

echo "Total query time: " . round($totalTime, 2) . "ms\n";
echo "Total rows scanned: " . $totalRowsScanned . "\n";
echo "Indexes used: " . $indexesUsed . "/" . count(array_filter($results, fn($r) => isset($r['time']))) . "\n\n";

// Performance rating
if ($indexesUsed >= 4 && $totalTime < 100) {
    echo "Performance Rating: ★★★★★ EXCELLENT (Indexes working!)\n";
} elseif ($indexesUsed >= 3 && $totalTime < 500) {
    echo "Performance Rating: ★★★★☆ GOOD (Some indexes working)\n";
} elseif ($indexesUsed >= 2) {
    echo "Performance Rating: ★★★☆☆ FAIR (Few indexes working)\n";
} elseif ($indexesUsed >= 1) {
    echo "Performance Rating: ★★☆☆☆ POOR (Very few indexes)\n";
} else {
    echo "Performance Rating: ★☆☆☆☆ CRITICAL (No indexes! Full table scans)\n";
}

echo "\n================================================================================\n";
echo "RECOMMENDATION\n";
echo "================================================================================\n";

if ($indexesUsed < 3) {
    echo "⚠️  WARNING: Most queries are NOT using indexes!\n";
    echo "   This will cause severe performance issues at scale.\n";
    echo "   Run the migrations to add indexes:\n";
    echo "   php artisan migrate\n\n";
} else {
    echo "✓ Good! Indexes are being used.\n";
    echo "  Continue monitoring query performance.\n\n";
}

echo "================================================================================\n";
