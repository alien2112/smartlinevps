<?php
/**
 * Standalone script to update GeoLink API key in the database
 * 
 * Usage: php update_geolink_api_key.php
 */

// Load Laravel bootstrap
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\BusinessManagement\Entities\BusinessSetting;
use Illuminate\Support\Facades\Cache;

// API Key to set
$apiKey = '4a3eb528-befa-4300-860d-9442ae141310';

echo "Updating GeoLink API key...\n";

// Find existing business setting
$businessSetting = BusinessSetting::where('key_name', GOOGLE_MAP_API)
    ->where('settings_type', GOOGLE_MAP_API)
    ->first();

$value = [];
if ($businessSetting && $businessSetting->value) {
    $value = is_array($businessSetting->value) ? $businessSetting->value : json_decode($businessSetting->value, true) ?? [];
}

// Update both client and server keys
$value['map_api_key'] = $apiKey;
$value['map_api_key_server'] = $apiKey;

// Save or create the setting
if ($businessSetting) {
    $businessSetting->update(['value' => $value]);
    echo "✓ API key updated successfully!\n";
} else {
    BusinessSetting::create([
        'key_name' => GOOGLE_MAP_API,
        'settings_type' => GOOGLE_MAP_API,
        'value' => $value
    ]);
    echo "✓ API key created successfully!\n";
}

// Clear cache
Cache::forget(CACHE_BUSINESS_SETTINGS);
echo "✓ Cache cleared.\n";

echo "\nDone! The GeoLink API key has been set to: $apiKey\n";




