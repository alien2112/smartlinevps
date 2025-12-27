<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->enum('type', ['string', 'integer', 'float', 'boolean', 'json', 'array'])->default('string');
            $table->string('group')->default('general')->index();
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->text('validation_rules')->nullable()->comment('JSON validation constraints');
            $table->text('default_value')->nullable();
            $table->foreignUuid('updated_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['group', 'key']);
        });

        // Insert default tracking settings
        $this->seedDefaultSettings();
    }

    /**
     * Seed default settings
     */
    private function seedDefaultSettings(): void
    {
        $settings = [
            // Tracking Settings
            [
                'key' => 'tracking.update_interval_seconds',
                'value' => '3',
                'type' => 'integer',
                'group' => 'tracking',
                'label' => 'Location Update Interval',
                'description' => 'How often drivers should send location updates (seconds)',
                'validation_rules' => json_encode(['min' => 1, 'max' => 60]),
                'default_value' => '3',
            ],
            [
                'key' => 'tracking.min_distance_meters',
                'value' => '10',
                'type' => 'integer',
                'group' => 'tracking',
                'label' => 'Minimum Distance Change',
                'description' => 'Minimum distance change (meters) before accepting location update',
                'validation_rules' => json_encode(['min' => 5, 'max' => 100]),
                'default_value' => '10',
            ],
            [
                'key' => 'tracking.stale_timeout_seconds',
                'value' => '300',
                'type' => 'integer',
                'group' => 'tracking',
                'label' => 'Stale Location Timeout',
                'description' => 'Seconds after which a driver location is considered stale',
                'validation_rules' => json_encode(['min' => 60, 'max' => 900]),
                'default_value' => '300',
            ],
            [
                'key' => 'tracking.heartbeat_interval_seconds',
                'value' => '30',
                'type' => 'integer',
                'group' => 'tracking',
                'label' => 'Heartbeat Interval',
                'description' => 'Driver online/offline heartbeat interval (seconds)',
                'validation_rules' => json_encode(['min' => 10, 'max' => 120]),
                'default_value' => '30',
            ],
            [
                'key' => 'tracking.batch_size',
                'value' => '10',
                'type' => 'integer',
                'group' => 'tracking',
                'label' => 'Location Batch Size',
                'description' => 'Number of location updates to batch before processing',
                'validation_rules' => json_encode(['min' => 1, 'max' => 50]),
                'default_value' => '10',
            ],
            [
                'key' => 'tracking.batch_flush_interval_ms',
                'value' => '1000',
                'type' => 'integer',
                'group' => 'tracking',
                'label' => 'Batch Flush Interval',
                'description' => 'Maximum time to wait before flushing location batch (milliseconds)',
                'validation_rules' => json_encode(['min' => 100, 'max' => 5000]),
                'default_value' => '1000',
            ],

            // Dispatch Settings
            [
                'key' => 'dispatch.search_radius_km',
                'value' => '5',
                'type' => 'float',
                'group' => 'dispatch',
                'label' => 'Default Search Radius',
                'description' => 'Default radius (km) to search for drivers',
                'validation_rules' => json_encode(['min' => 1, 'max' => 50]),
                'default_value' => '5',
            ],
            [
                'key' => 'dispatch.max_search_radius_km',
                'value' => '15',
                'type' => 'float',
                'group' => 'dispatch',
                'label' => 'Maximum Search Radius',
                'description' => 'Maximum radius (km) for driver search expansion',
                'validation_rules' => json_encode(['min' => 5, 'max' => 100]),
                'default_value' => '15',
            ],
            [
                'key' => 'dispatch.max_drivers_to_notify',
                'value' => '10',
                'type' => 'integer',
                'group' => 'dispatch',
                'label' => 'Max Drivers to Notify',
                'description' => 'Maximum number of drivers to notify for a ride request',
                'validation_rules' => json_encode(['min' => 1, 'max' => 50]),
                'default_value' => '10',
            ],
            [
                'key' => 'dispatch.match_timeout_seconds',
                'value' => '60',
                'type' => 'integer',
                'group' => 'dispatch',
                'label' => 'Match Timeout',
                'description' => 'Seconds to wait for driver acceptance before timeout',
                'validation_rules' => json_encode(['min' => 30, 'max' => 300]),
                'default_value' => '60',
            ],
            [
                'key' => 'dispatch.category_fallback_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'dispatch',
                'label' => 'Category Fallback',
                'description' => 'Allow higher-tier drivers to accept lower-tier rides',
                'validation_rules' => null,
                'default_value' => 'true',
            ],
            [
                'key' => 'dispatch.prioritize_same_category',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'dispatch',
                'label' => 'Prioritize Same Category',
                'description' => 'Prioritize drivers of the same category before fallback',
                'validation_rules' => null,
                'default_value' => 'true',
            ],

            // Travel Mode Settings
            [
                'key' => 'travel.search_radius_km',
                'value' => '30',
                'type' => 'float',
                'group' => 'travel',
                'label' => 'Travel Search Radius',
                'description' => 'Radius (km) to search for VIP drivers for travel rides',
                'validation_rules' => json_encode(['min' => 10, 'max' => 100]),
                'default_value' => '30',
            ],
            [
                'key' => 'travel.timeout_minutes',
                'value' => '5',
                'type' => 'integer',
                'group' => 'travel',
                'label' => 'Travel Request Timeout',
                'description' => 'Minutes to wait before marking travel request as expired',
                'validation_rules' => json_encode(['min' => 2, 'max' => 30]),
                'default_value' => '5',
            ],
            [
                'key' => 'travel.vip_only',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'travel',
                'label' => 'VIP Only',
                'description' => 'Restrict travel mode to VIP drivers only',
                'validation_rules' => null,
                'default_value' => 'true',
            ],
            [
                'key' => 'travel.surge_disabled',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'travel',
                'label' => 'Disable Surge',
                'description' => 'Disable surge pricing for travel mode',
                'validation_rules' => null,
                'default_value' => 'true',
            ],

            // VIP Abuse Prevention
            [
                'key' => 'vip.low_category_trip_limit',
                'value' => '5',
                'type' => 'integer',
                'group' => 'dispatch',
                'label' => 'VIP Low Category Limit',
                'description' => 'Daily limit of low-category trips for VIP drivers before deprioritization',
                'validation_rules' => json_encode(['min' => 1, 'max' => 20]),
                'default_value' => '5',
            ],
            [
                'key' => 'vip.deprioritization_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'dispatch',
                'label' => 'VIP Deprioritization',
                'description' => 'Enable VIP driver deprioritization for low-category rides',
                'validation_rules' => null,
                'default_value' => 'true',
            ],

            // Map Settings
            [
                'key' => 'map.tile_provider_url',
                'value' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                'type' => 'string',
                'group' => 'map',
                'label' => 'Tile Provider URL',
                'description' => 'OpenStreetMap tile provider URL pattern',
                'validation_rules' => null,
                'default_value' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            ],
            [
                'key' => 'map.default_center_lat',
                'value' => '30.0444',
                'type' => 'float',
                'group' => 'map',
                'label' => 'Default Center Latitude',
                'description' => 'Default map center latitude',
                'validation_rules' => json_encode(['min' => -90, 'max' => 90]),
                'default_value' => '30.0444',
            ],
            [
                'key' => 'map.default_center_lng',
                'value' => '31.2357',
                'type' => 'float',
                'group' => 'map',
                'label' => 'Default Center Longitude',
                'description' => 'Default map center longitude',
                'validation_rules' => json_encode(['min' => -180, 'max' => 180]),
                'default_value' => '31.2357',
            ],
            [
                'key' => 'map.default_zoom',
                'value' => '12',
                'type' => 'integer',
                'group' => 'map',
                'label' => 'Default Zoom Level',
                'description' => 'Default map zoom level',
                'validation_rules' => json_encode(['min' => 1, 'max' => 18]),
                'default_value' => '12',
            ],
            [
                'key' => 'map.enable_clustering',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'map',
                'label' => 'Enable Marker Clustering',
                'description' => 'Enable marker clustering for performance with many markers',
                'validation_rules' => null,
                'default_value' => 'true',
            ],
            [
                'key' => 'map.routing_provider',
                'value' => 'osrm',
                'type' => 'string',
                'group' => 'map',
                'label' => 'Routing Provider',
                'description' => 'Routing provider for route display (osrm, graphhopper)',
                'validation_rules' => null,
                'default_value' => 'osrm',
            ],
            [
                'key' => 'map.osrm_server_url',
                'value' => 'https://router.project-osrm.org',
                'type' => 'string',
                'group' => 'map',
                'label' => 'OSRM Server URL',
                'description' => 'OSRM routing server URL',
                'validation_rules' => null,
                'default_value' => 'https://router.project-osrm.org',
            ],
        ];

        foreach ($settings as $setting) {
            \DB::table('app_settings')->insert(array_merge($setting, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
