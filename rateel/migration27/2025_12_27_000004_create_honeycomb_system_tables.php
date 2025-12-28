<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Honeycomb System Migration
 * 
 * Creates the database structure for H3-based hexagonal grid dispatch system.
 * Similar to DiDi/Uber's approach for:
 * - Faster dispatch (cell + neighbor search)
 * - Supply/demand heatmaps
 * - Driver hotspot recommendations
 * - Cell-based surge pricing (optional)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Honeycomb Settings (per city/zone)
        Schema::create('dispatch_honeycomb_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('zone_id')->nullable();
            $table->string('city_name', 100)->nullable()->comment('Human-readable city name');
            
            // Core toggles
            $table->boolean('enabled')->default(false)->comment('Master toggle for honeycomb mode');
            $table->boolean('dispatch_enabled')->default(false)->comment('Use honeycomb for dispatch filtering');
            $table->boolean('heatmap_enabled')->default(false)->comment('Enable heatmap visualization');
            $table->boolean('hotspots_enabled')->default(false)->comment('Show hotspots to drivers');
            $table->boolean('surge_enabled')->default(false)->comment('Enable cell-based surge pricing');
            $table->boolean('incentives_enabled')->default(false)->comment('Enable cell-based driver incentives');
            
            // H3 Configuration
            $table->tinyInteger('h3_resolution')->default(8)->comment('H3 resolution: 7=~5km, 8=~1.5km, 9=~500m');
            $table->tinyInteger('search_depth_k')->default(1)->comment('Number of hex rings to include in search');
            $table->integer('update_interval_seconds')->default(60)->comment('How often to recompute cell stats');
            $table->integer('min_drivers_to_color_cell')->default(1)->comment('Min drivers for heatmap coloring');
            
            // Surge configuration
            $table->decimal('surge_threshold', 5, 2)->default(1.50)->comment('Demand/supply ratio to trigger surge');
            $table->decimal('surge_cap', 3, 2)->default(2.00)->comment('Maximum surge multiplier');
            $table->decimal('surge_step', 3, 2)->default(0.10)->comment('Surge increment per threshold breach');
            
            // Incentive configuration
            $table->decimal('incentive_threshold', 5, 2)->default(2.00)->comment('Imbalance ratio for incentive');
            $table->decimal('max_incentive_amount', 10, 2)->default(50.00)->comment('Max incentive per trip');
            
            // Metadata
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('zone_id');
            $table->index('enabled');
            $table->unique(['zone_id'], 'unique_zone_honeycomb');
        });

        // 2. Honeycomb Cell Metrics (optional persistence for analytics)
        Schema::create('honeycomb_cell_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('zone_id')->nullable();
            $table->string('h3_index', 20)->comment('H3 cell index as string');
            $table->dateTime('window_start')->comment('Start of the time window');
            $table->integer('window_minutes')->default(5)->comment('Duration of window in minutes');
            
            // Supply metrics
            $table->integer('supply_total')->default(0)->comment('Total available drivers');
            $table->integer('supply_budget')->default(0)->comment('Budget category drivers');
            $table->integer('supply_pro')->default(0)->comment('Pro category drivers');
            $table->integer('supply_vip')->default(0)->comment('VIP category drivers');
            
            // Demand metrics
            $table->integer('demand_total')->default(0)->comment('Total ride requests');
            $table->integer('demand_budget')->default(0)->comment('Budget category requests');
            $table->integer('demand_pro')->default(0)->comment('Pro category requests');
            $table->integer('demand_vip')->default(0)->comment('VIP category requests');
            
            // Computed metrics
            $table->decimal('imbalance_score', 8, 4)->default(0)->comment('demand/max(supply,1)');
            $table->decimal('surge_multiplier', 5, 2)->default(1.00)->comment('Applied surge multiplier');
            $table->decimal('incentive_amount', 10, 2)->default(0)->comment('Active incentive amount');
            
            // Cell center for quick display
            $table->decimal('center_lat', 10, 7)->nullable();
            $table->decimal('center_lng', 10, 7)->nullable();
            
            $table->timestamps();
            
            // Indexes for efficient querying
            $table->index(['zone_id', 'window_start']);
            $table->index(['h3_index', 'window_start']);
            $table->index('window_start');
            $table->unique(['zone_id', 'h3_index', 'window_start'], 'unique_cell_window');
        });

        // 3. Driver H3 Cell History (for analytics/hotspot prediction)
        Schema::create('driver_h3_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('driver_id');
            $table->uuid('zone_id')->nullable();
            $table->string('h3_index', 20);
            $table->dateTime('entered_at');
            $table->dateTime('left_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->integer('trips_completed')->default(0);
            $table->decimal('earnings', 10, 2)->default(0);
            
            $table->timestamps();
            
            // Indexes
            $table->index(['driver_id', 'entered_at']);
            $table->index(['h3_index', 'entered_at']);
            $table->index('zone_id');
        });

        // 4. Honeycomb Surge History (audit trail)
        Schema::create('honeycomb_surge_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('zone_id')->nullable();
            $table->string('h3_index', 20);
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->decimal('surge_multiplier', 5, 2);
            $table->decimal('imbalance_score', 8, 4);
            $table->integer('supply_count');
            $table->integer('demand_count');
            
            $table->timestamps();
            
            // Indexes
            $table->index(['zone_id', 'started_at']);
            $table->index(['h3_index', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('honeycomb_surge_history');
        Schema::dropIfExists('driver_h3_history');
        Schema::dropIfExists('honeycomb_cell_metrics');
        Schema::dropIfExists('dispatch_honeycomb_settings');
    }
};
