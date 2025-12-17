-- ====================================================================
-- PRIORITY 1 DATABASE INDEXES (CRITICAL - Deploy Immediately)
-- ====================================================================
-- Apply these indexes to smartline_indexed_copy database first
-- After testing, use the Laravel migrations to apply to production
--
-- Expected Impact:
-- - Trip status queries: 5-10 seconds -> <50ms
-- - Driver pending rides: 3-8 seconds -> <100ms
-- - Nearest driver queries: 2-3 seconds -> <20ms
-- ====================================================================

USE smartline_indexed_copy;

-- --------------------------------------------------------------------
-- 1. TRIP REQUESTS - Most Frequent Queries
-- --------------------------------------------------------------------

-- Trip status queries (most common filter)
-- Used in: trip listings, driver pending rides, customer trip history
CREATE INDEX idx_trips_status_created
ON trip_requests(current_status, created_at DESC);

-- Driver pending rides by zone (real-time critical)
-- Used in: GET /rides/pending endpoint
CREATE INDEX idx_trips_zone_status
ON trip_requests(zone_id, current_status);

-- Customer trip queries
-- Used in: customer trip history, active trips
CREATE INDEX idx_trips_customer
ON trip_requests(customer_id, current_status);

-- Driver trip queries
-- Used in: driver trip history, active trips
CREATE INDEX idx_trips_driver
ON trip_requests(driver_id, current_status);

-- --------------------------------------------------------------------
-- 2. SPATIAL INDEXES FOR COORDINATES
-- --------------------------------------------------------------------

-- Check if trip_request_coordinates table exists and has the right columns
-- Add spatial index for pickup coordinates
-- Note: Ensure pickup_coordinates is already POINT type
-- If it's VARCHAR, you'll need to add a new column (see migration)
ALTER TABLE trip_request_coordinates
ADD SPATIAL INDEX idx_pickup_coords (pickup_coordinates);

-- Add spatial index for dropoff coordinates
ALTER TABLE trip_request_coordinates
ADD SPATIAL INDEX idx_dropoff_coords (dropoff_coordinates);

-- Add index for trip lookups
CREATE INDEX idx_coordinates_trip
ON trip_request_coordinates(trip_request_id);

-- --------------------------------------------------------------------
-- 3. USER LAST LOCATIONS - Driver Location Tracking
-- --------------------------------------------------------------------

-- Add new POINT column for spatial queries (if not exists)
-- This enables efficient nearest driver queries
ALTER TABLE user_last_locations
ADD COLUMN location_point POINT SRID 4326 AFTER longitude;

-- Add spatial index on the new column
ALTER TABLE user_last_locations
ADD SPATIAL INDEX idx_location_point (location_point);

-- Add index for zone-based queries
CREATE INDEX idx_location_zone_type
ON user_last_locations(zone_id, type);

-- Add index for user lookups
CREATE INDEX idx_location_user
ON user_last_locations(user_id, updated_at DESC);

-- Update trigger to sync latitude/longitude with location_point
DELIMITER //
CREATE TRIGGER before_location_insert
BEFORE INSERT ON user_last_locations
FOR EACH ROW
BEGIN
    IF NEW.latitude IS NOT NULL AND NEW.longitude IS NOT NULL THEN
        SET NEW.location_point = ST_SRID(POINT(NEW.longitude, NEW.latitude), 4326);
    END IF;
END//

CREATE TRIGGER before_location_update
BEFORE UPDATE ON user_last_locations
FOR EACH ROW
BEGIN
    IF NEW.latitude IS NOT NULL AND NEW.longitude IS NOT NULL THEN
        SET NEW.location_point = ST_SRID(POINT(NEW.longitude, NEW.latitude), 4326);
    END IF;
END//
DELIMITER ;

-- Backfill existing location data
UPDATE user_last_locations
SET location_point = ST_SRID(POINT(longitude, latitude), 4326)
WHERE latitude IS NOT NULL AND longitude IS NOT NULL AND location_point IS NULL;

-- --------------------------------------------------------------------
-- 4. ZONES - Spatial Index for Point-in-Polygon
-- --------------------------------------------------------------------

-- Add spatial index on zones for faster point-in-polygon checks
-- Note: Ensure coordinates column is POLYGON/GEOMETRY type
ALTER TABLE zones
ADD SPATIAL INDEX idx_zone_coordinates (coordinates);

-- --------------------------------------------------------------------
-- VERIFICATION QUERIES
-- --------------------------------------------------------------------

-- Check index sizes and usage
SELECT
    TABLE_NAME,
    INDEX_NAME,
    INDEX_TYPE,
    COLUMN_NAME,
    CARDINALITY
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'smartline_indexed_copy'
    AND TABLE_NAME IN ('trip_requests', 'trip_request_coordinates', 'user_last_locations', 'zones')
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- Test query performance (before/after comparison)
-- Example: Pending rides in a zone
EXPLAIN SELECT * FROM trip_requests
WHERE zone_id = 'some-zone-id'
    AND current_status = 'pending'
ORDER BY created_at DESC
LIMIT 20;

-- Example: Nearest drivers query with spatial index
EXPLAIN SELECT
    u.id,
    u.name,
    ST_Distance_Sphere(l.location_point, ST_SRID(POINT(31.2357, 30.0444), 4326)) AS distance_meters
FROM user_last_locations l
JOIN users u ON l.user_id = u.id
WHERE l.type = 'driver'
    AND ST_Distance_Sphere(l.location_point, ST_SRID(POINT(31.2357, 30.0444), 4326)) <= 5000
ORDER BY distance_meters
LIMIT 10;

-- ====================================================================
-- EXPECTED RESULTS:
-- ====================================================================
-- - All EXPLAIN queries should show "Using index" or "Using where; Using index"
-- - Spatial queries should show "range" type with spatial index
-- - NO "Using filesort" or "Using temporary" for these queries
-- ====================================================================
