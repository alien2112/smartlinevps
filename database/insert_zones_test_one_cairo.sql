-- Insert Zones into test_one_cairo database
-- This script adds Cairo zone with proper MySQL spatial polygon format

USE drivemond_db;

-- Cairo Test Zone
-- Coordinates form a polygon around Cairo city center
-- Format: POLYGON((lng lat, lng lat, ...)) - Note: MySQL uses longitude first, then latitude
-- The polygon must be closed (first and last point must be the same)

INSERT INTO zones (
    id,
    name,
    coordinates,
    is_active,
    created_at,
    updated_at
) VALUES (
    UUID(),
    'Cairo Test Zone',
    ST_GeomFromText('POLYGON((31.1 30.1, 31.4 30.1, 31.4 29.9, 31.1 29.9, 31.1 30.1))', 4326),
    1,
    NOW(),
    NOW()
);

-- Verify the zone was created
SELECT 
    id, 
    name, 
    is_active, 
    ST_AsText(coordinates) as coordinates_wkt,
    ST_AsGeoJSON(coordinates) as coordinates_geojson,
    created_at 
FROM zones 
WHERE name = 'Cairo Test Zone';

-- Test if coordinates fall within this zone
-- Test coordinates: lat=30.0444, lng=31.2357
SELECT 
    name,
    ST_Contains(
        coordinates, 
        ST_GeomFromText('POINT(31.2357 30.0444)', 4326)
    ) as contains_test_point
FROM zones 
WHERE name = 'Cairo Test Zone';

