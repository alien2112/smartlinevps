-- Check if zones are inserted in the database
-- Run this query to verify zones exist

USE drivemond_db;

-- Count total zones
SELECT COUNT(*) as total_zones FROM zones;

-- List all zones with details
SELECT 
    id, 
    name, 
    is_active, 
    ST_AsText(coordinates) as coordinates_wkt,
    ST_AsGeoJSON(coordinates) as coordinates_geojson,
    created_at,
    updated_at
FROM zones 
ORDER BY created_at DESC;

-- Check specifically for Cairo Test Zone
SELECT 
    id, 
    name, 
    is_active, 
    ST_AsText(coordinates) as coordinates_wkt,
    ST_AsGeoJSON(coordinates) as coordinates_geojson,
    created_at 
FROM zones 
WHERE name = 'Cairo Test Zone';

