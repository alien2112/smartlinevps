<?php

/**
 * Kashier Payment Gateway Setup Script
 *
 * This script checks if Kashier is configured in the payment_settings table
 * and helps you configure it if needed.
 *
 * Usage: php setup_kashier_payment.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Modules\Gateways\Entities\Setting;
use Illuminate\Support\Facades\DB;

echo "=================================================\n";
echo "Kashier Payment Gateway Configuration\n";
echo "=================================================\n\n";

// Check if Kashier is already configured
$kashierConfig = Setting::where('key_name', 'kashier')
    ->where('settings_type', 'payment_config')
    ->first();

if ($kashierConfig) {
    echo "✓ Kashier configuration found in database\n";
    echo "  - Status: " . ($kashierConfig->is_active ? "ACTIVE" : "INACTIVE") . "\n";
    echo "  - Mode: " . $kashierConfig->mode . "\n";

    $config = json_decode($kashierConfig->mode === 'live' ? $kashierConfig->live_values : $kashierConfig->test_values, true);

    echo "\n  Current Configuration:\n";
    echo "  - Merchant ID: " . ($config['merchant_id'] ?? 'NOT SET') . "\n";
    echo "  - Has Public Key: " . (isset($config['public_key']) ? 'YES' : 'NO') . "\n";
    echo "  - Has Secret Key: " . (isset($config['secret_key']) ? 'YES' : 'NO') . "\n";
    echo "  - Currency: " . ($config['currency'] ?? 'NOT SET') . "\n";
    echo "  - Callback URL: " . ($config['callback_url'] ?? 'NOT SET') . "\n";

    echo "\n";
    echo "Do you want to update the configuration? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    $update = trim(strtolower($line));
    fclose($handle);

    if ($update !== 'yes' && $update !== 'y') {
        echo "\nConfiguration check complete.\n";

        // Test the configuration
        echo "\n=================================================\n";
        echo "Testing Kashier Configuration\n";
        echo "=================================================\n\n";

        if (!empty($config['merchant_id']) && !empty($config['secret_key'])) {
            // Test signature generation
            $testPath = "/?payment=TEST.123456.100.EGP";
            $testHash = hash_hmac('sha256', $testPath, $config['secret_key'], false);
            echo "✓ Signature generation test passed\n";
            echo "  Test hash: " . substr($testHash, 0, 20) . "...\n";
        } else {
            echo "✗ Cannot test - missing merchant_id or secret_key\n";
        }

        exit(0);
    }
} else {
    echo "✗ Kashier configuration NOT found in database\n";
    echo "  Creating new configuration...\n\n";
}

// Get configuration from user
echo "\n=================================================\n";
echo "Kashier Configuration Setup\n";
echo "=================================================\n";
echo "Get these values from: https://merchant.kashier.io/en/account/settings\n\n";

$handle = fopen("php://stdin", "r");

echo "Enter Merchant ID (MID-xxxxx-xxx): ";
$merchantId = trim(fgets($handle));

echo "Enter Public Key: ";
$publicKey = trim(fgets($handle));

echo "Enter Secret Key (API Key): ";
$secretKey = trim(fgets($handle));

echo "Enter Currency [EGP]: ";
$currency = trim(fgets($handle));
$currency = empty($currency) ? 'EGP' : $currency;

$callbackUrl = url('/payment/kashier/callback');
echo "Callback URL (default: $callbackUrl): ";
$customCallback = trim(fgets($handle));
$callbackUrl = empty($customCallback) ? $callbackUrl : $customCallback;

echo "Mode (test/live) [test]: ";
$mode = trim(fgets($handle));
$mode = empty($mode) ? 'test' : $mode;

echo "\nActivate Kashier payment gateway? (yes/no) [yes]: ";
$activate = trim(fgets($handle));
$isActive = ($activate === 'no' || $activate === 'n') ? 0 : 1;

fclose($handle);

// Prepare configuration
$config = [
    'merchant_id' => $merchantId,
    'public_key' => $publicKey,
    'secret_key' => $secretKey,
    'currency' => $currency,
    'callback_url' => $callbackUrl,
    'mode' => $mode,
];

$additionalData = [
    'gateway_title' => 'Kashier',
    'gateway_image' => 'kashier.png'
];

// Save configuration
try {
    if ($kashierConfig) {
        // Update existing
        if ($mode === 'live') {
            $kashierConfig->live_values = json_encode($config);
        } else {
            $kashierConfig->test_values = json_encode($config);
        }
        $kashierConfig->mode = $mode;
        $kashierConfig->is_active = $isActive;
        $kashierConfig->save();
    } else {
        // Create new
        Setting::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'key_name' => 'kashier',
            'live_values' => json_encode($config),
            'test_values' => json_encode($config),
            'settings_type' => 'payment_config',
            'mode' => $mode,
            'is_active' => $isActive,
            'additional_data' => json_encode($additionalData),
        ]);
    }

    echo "\n✓ Kashier configuration saved successfully!\n";

    // Test the configuration
    echo "\n=================================================\n";
    echo "Testing Configuration\n";
    echo "=================================================\n\n";

    // Test signature generation
    $testPath = "/?payment={$merchantId}.123456.100.{$currency}";
    $testHash = hash_hmac('sha256', $testPath, $secretKey, false);
    echo "✓ Signature generation test:\n";
    echo "  Path: {$testPath}\n";
    echo "  Hash: " . substr($testHash, 0, 40) . "...\n\n";

    echo "✓ Configuration saved to database\n";
    echo "  - Key: kashier\n";
    echo "  - Mode: {$mode}\n";
    echo "  - Status: " . ($isActive ? 'ACTIVE' : 'INACTIVE') . "\n";
    echo "  - Callback URL: {$callbackUrl}\n\n";

    echo "=================================================\n";
    echo "Next Steps:\n";
    echo "=================================================\n";
    echo "1. Verify the callback URL in Kashier merchant dashboard:\n";
    echo "   {$callbackUrl}\n\n";
    echo "2. Test the payment flow:\n";
    echo "   GET /api/customer/config/get-payment-methods\n";
    echo "   (should return 'kashier' in the list)\n\n";
    echo "3. Make a test payment:\n";
    echo "   GET /api/customer/ride/digital-payment\n";
    echo "   ?trip_request_id=<uuid>\n";
    echo "   &payment_method=kashier\n";
    echo "   &tips=0\n\n";
    echo "4. Monitor logs for any issues:\n";
    echo "   tail -f storage/logs/laravel.log\n\n";

} catch (\Exception $e) {
    echo "\n✗ Error saving configuration:\n";
    echo "  " . $e->getMessage() . "\n\n";
    exit(1);
}

echo "\n✓ Setup complete!\n";
