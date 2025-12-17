<?php

/**
 * Test script to verify the centralized logging implementation
 * Run with: php test_logging.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\LogService;
use Illuminate\Support\Facades\Log;

echo "Testing Centralized Logging Implementation\n";
echo str_repeat("=", 50) . "\n\n";

// Test 1: Basic Log Context
echo "Test 1: Setting log context with correlation ID...\n";
Log::withContext([
    'correlation_id' => 'test-' . uniqid(),
    'vps_id' => gethostname(),
    'test_suite' => 'logging_verification',
]);

Log::info('test_log_context_set', [
    'message' => 'Log context has been set successfully',
]);
echo "✓ Log context test completed\n\n";

// Test 2: Trip Event Logging
echo "Test 2: Testing trip event logging...\n";
$mockTrip = (object) [
    'id' => 12345,
    'customer_id' => 100,
    'driver_id' => 200,
    'current_status' => 'pending',
    'vehicle_category_id' => 1,
    'zone_id' => 50,
];

LogService::tripEvent('trip_created', $mockTrip, [
    'type' => 'test_ride',
    'estimated_fare' => 25.50,
]);
echo "✓ Trip event logging test completed\n\n";

// Test 3: Authentication Event Logging
echo "Test 3: Testing auth event logging...\n";
$mockUser = (object) [
    'id' => 500,
    'user_type' => 'customer',
    'phone' => '+1234567890',
];

LogService::authEvent('login_success', $mockUser);
echo "✓ Auth event logging test completed\n\n";

// Test 4: Security Event Logging
echo "Test 4: Testing security event logging...\n";
LogService::securityEvent('rate_limit_exceeded', [
    'endpoint' => '/api/test',
    'attempts' => 100,
]);
echo "✓ Security event logging test completed\n\n";

// Test 5: Performance Metric Logging
echo "Test 5: Testing performance metric logging...\n";
LogService::performanceMetric('api_response_time', 1250.75, [
    'endpoint' => '/api/customer/trips',
    'threshold_exceeded' => true,
]);
echo "✓ Performance metric logging test completed\n\n";

// Test 6: External API Call Logging
echo "Test 6: Testing external API call logging...\n";
LogService::externalApiCall('geolink', '/api/v2/directions', [
    'status' => 200,
    'duration_ms' => 345.50,
]);
echo "✓ External API call logging test completed\n\n";

// Test 7: Payment Event Logging
echo "Test 7: Testing payment event logging...\n";
$mockTripForPayment = (object) [
    'id' => 67890,
    'customer_id' => 100,
    'driver_id' => 200,
    'actual_fare' => 45.00,
    'payment_method' => 'stripe',
];

LogService::paymentEvent('payment_success', $mockTripForPayment, [
    'transaction_id' => 'txn_test_12345',
]);
echo "✓ Payment event logging test completed\n\n";

echo str_repeat("=", 50) . "\n";
echo "All logging tests completed successfully!\n\n";
echo "Check log files at:\n";
echo "  - storage/logs/api-" . date('Y-m-d') . ".log (general API logs)\n";
echo "  - storage/logs/security-" . date('Y-m-d') . ".log (security events)\n";
echo "  - storage/logs/finance-" . date('Y-m-d') . ".log (payment events)\n";
echo "  - storage/logs/performance-" . date('Y-m-d') . ".log (performance metrics)\n\n";

echo "Expected log format: JSON with correlation_id, vps_id, and event details\n";
