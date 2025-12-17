<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * CRITICAL: Add spatial column for efficient nearest driver queries
     * Expected impact: Driver search 2-3s -> <20ms at 10K drivers
     */
    public function up(): void
    {
        // Check if column already exists
        $hasColumn = DB::select("SHOW COLUMNS FROM user_last_locations LIKE 'location_point'");

        if (empty($hasColumn)) {
            // Add location_point column (POINT with SRID 4326 for GPS coordinates)
            // First add as NULL to allow backfilling
            DB::statement('ALTER TABLE user_last_locations ADD COLUMN location_point POINT SRID 4326 NULL AFTER longitude');
        }

        // Backfill existing location data
        DB::statement('
            UPDATE user_last_locations
            SET location_point = ST_SRID(POINT(longitude, latitude), 4326)
            WHERE latitude IS NOT NULL AND longitude IS NOT NULL
        ');

        // Now make it NOT NULL (required for spatial index)
        DB::statement('ALTER TABLE user_last_locations MODIFY COLUMN location_point POINT SRID 4326 NOT NULL');

        // Check if spatial index exists
        $hasIndex = DB::select("SHOW INDEX FROM user_last_locations WHERE Key_name = 'idx_location_point'");

        if (empty($hasIndex)) {
            // Add spatial index
            DB::statement('ALTER TABLE user_last_locations ADD SPATIAL INDEX idx_location_point (location_point)');
        }

        Schema::table('user_last_locations', function (Blueprint $table) {
            // Add index for zone-based queries
            $table->index(['zone_id', 'type'], 'idx_location_zone_type');

            // Add index for user lookups
            $table->index(['user_id', 'updated_at'], 'idx_location_user');
        });

        // Create triggers to auto-sync latitude/longitude with location_point
        DB::unprepared('
            CREATE TRIGGER before_location_insert
            BEFORE INSERT ON user_last_locations
            FOR EACH ROW
            BEGIN
                IF NEW.latitude IS NOT NULL AND NEW.longitude IS NOT NULL THEN
                    SET NEW.location_point = ST_SRID(POINT(NEW.longitude, NEW.latitude), 4326);
                END IF;
            END
        ');

        DB::unprepared('
            CREATE TRIGGER before_location_update
            BEFORE UPDATE ON user_last_locations
            FOR EACH ROW
            BEGIN
                IF NEW.latitude IS NOT NULL AND NEW.longitude IS NOT NULL THEN
                    SET NEW.location_point = ST_SRID(POINT(NEW.longitude, NEW.latitude), 4326);
                END IF;
            END
        ');

        // Backfill existing location data
        DB::statement('
            UPDATE user_last_locations
            SET location_point = ST_SRID(POINT(longitude, latitude), 4326)
            WHERE latitude IS NOT NULL AND longitude IS NOT NULL AND location_point IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop triggers
        DB::unprepared('DROP TRIGGER IF EXISTS before_location_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS before_location_update');

        Schema::table('user_last_locations', function (Blueprint $table) {
            $table->dropIndex('idx_location_zone_type');
            $table->dropIndex('idx_location_user');
        });

        // Drop spatial index and column
        DB::statement('ALTER TABLE user_last_locations DROP INDEX idx_location_point');
        DB::statement('ALTER TABLE user_last_locations DROP COLUMN location_point');
    }
};
