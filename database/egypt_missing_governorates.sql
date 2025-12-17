-- ============================================================================
-- Egypt Missing Governorates - Add to Existing Zones
-- ============================================================================
-- This script ADDS the missing governorates WITHOUT deleting existing zones
-- Run this if you want to keep your existing zones and just add the missing ones
-- All coordinates use SRID 4326 (WGS 84 - GPS standard)
-- ============================================================================

USE drivemond_db;

-- Get the next readable_id (continues from existing max)
SET @next_id = (SELECT COALESCE(MAX(readable_id), 0) + 1 FROM zones WHERE deleted_at IS NULL);

-- ============================================================================
-- MISSING GOVERNORATES (not in original 20-zone seed)
-- ============================================================================

-- 1. Cairo Governorate (القاهرة) - Separate from "Greater Cairo"
-- Only add if not exists
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
SELECT UUID(), 'Cairo', @next_id, 
ST_GeomFromText('POLYGON((31.1500 30.1500, 31.4500 30.1500, 31.4500 29.9500, 31.1500 29.9500, 31.1500 30.1500))', 4326), 
1, 0, 0, NOW(), NOW()
FROM dual WHERE NOT EXISTS (SELECT 1 FROM zones WHERE name = 'Cairo' AND deleted_at IS NULL);
SET @next_id = @next_id + 1;

-- 2. Giza Governorate (الجيزة) - Separate from "Greater Cairo"
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
SELECT UUID(), 'Giza', @next_id, 
ST_GeomFromText('POLYGON((30.7000 30.2000, 31.3000 30.2000, 31.3000 29.2000, 30.0000 29.2000, 30.0000 30.0000, 30.7000 30.2000))', 4326), 
1, 0, 0, NOW(), NOW()
FROM dual WHERE NOT EXISTS (SELECT 1 FROM zones WHERE name = 'Giza' AND deleted_at IS NULL);
SET @next_id = @next_id + 1;

-- 3. Qalyubia Governorate (القليوبية)
-- Capital: Banha | North of Cairo
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
SELECT UUID(), 'Qalyubia', @next_id, 
ST_GeomFromText('POLYGON((31.0500 30.4500, 31.4500 30.4500, 31.4500 30.1000, 31.0500 30.1000, 31.0500 30.4500))', 4326), 
1, 0, 0, NOW(), NOW()
FROM dual WHERE NOT EXISTS (SELECT 1 FROM zones WHERE name = 'Qalyubia' AND deleted_at IS NULL);
SET @next_id = @next_id + 1;

-- 4. Dakahlia Governorate (الدقهلية) - replaces city-only "Mansoura"
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
SELECT UUID(), 'Dakahlia', @next_id, 
ST_GeomFromText('POLYGON((31.2000 31.6000, 31.7500 31.6000, 31.7500 30.9000, 31.2000 30.9000, 31.2000 31.6000))', 4326), 
1, 0, 0, NOW(), NOW()
FROM dual WHERE NOT EXISTS (SELECT 1 FROM zones WHERE name = 'Dakahlia' AND deleted_at IS NULL);
SET @next_id = @next_id + 1;

-- 5. Sharqia Governorate (الشرقية) - replaces city-only "Zagazig"
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
SELECT UUID(), 'Sharqia', @next_id, 
ST_GeomFromText('POLYGON((31.3000 30.9500, 32.0000 30.9500, 32.0000 30.3000, 31.3000 30.3000, 31.3000 30.9500))', 4326), 
1, 0, 0, NOW(), NOW()
FROM dual WHERE NOT EXISTS (SELECT 1 FROM zones WHERE name = 'Sharqia' AND deleted_at IS NULL);
SET @next_id = @next_id + 1;

-- 6. Gharbia Governorate (الغربية) - replaces city-only "Tanta"
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
SELECT UUID(), 'Gharbia', @next_id, 
ST_GeomFromText('POLYGON((30.7500 31.1500, 31.2500 31.1500, 31.2500 30.7000, 30.7500 30.7000, 30.7500 31.1500))', 4326), 
1, 0, 0, NOW(), NOW()
FROM dual WHERE NOT EXISTS (SELECT 1 FROM zones WHERE name = 'Gharbia' AND deleted_at IS NULL);
SET @next_id = @next_id + 1;

-- 7. Monufia Governorate (المنوفية)
-- Capital: Shibin El Kom | Central Delta
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
SELECT UUID(), 'Monufia', @next_id, 
ST_GeomFromText('POLYGON((30.8000 30.7500, 31.2000 30.7500, 31.2000 30.3500, 30.8000 30.3500, 30.8000 30.7500))', 4326), 
1, 0, 0, NOW(), NOW()
FROM dual WHERE NOT EXISTS (SELECT 1 FROM zones WHERE name = 'Monufia' AND deleted_at IS NULL);
SET @next_id = @next_id + 1;

-- 8. Beheira Governorate (البحيرة) - replaces city-only "Damanhur"
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
SELECT UUID(), 'Beheira', @next_id, 
ST_GeomFromText('POLYGON((30.0000 31.2500, 30.8000 31.2500, 30.8000 30.4000, 30.0000 30.4000, 30.0000 31.2500))', 4326), 
1, 0, 0, NOW(), NOW()
FROM dual WHERE NOT EXISTS (SELECT 1 FROM zones WHERE name = 'Beheira' AND deleted_at IS NULL);
SET @next_id = @next_id + 1;

-- 9. Kafr El Sheikh Governorate (كفر الشيخ)
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
SELECT UUID(), 'Kafr El Sheikh', @next_id, 
ST_GeomFromText('POLYGON((30.5000 31.6000, 31.3000 31.6000, 31.3000 31.0500, 30.5000 31.0500, 30.5000 31.6000))', 4326), 
1, 0, 0, NOW(), NOW()
FROM dual WHERE NOT EXISTS (SELECT 1 FROM zones WHERE name = 'Kafr El Sheikh' AND deleted_at IS NULL);
SET @next_id = @next_id + 1;

-- 10. Damietta Governorate (دمياط)
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
SELECT UUID(), 'Damietta', @next_id, 
ST_GeomFromText('POLYGON((31.6000 31.6500, 32.0000 31.6500, 32.0000 31.3000, 31.6000 31.3000, 31.6000 31.6500))', 4326), 
1, 0, 0, NOW(), NOW()
FROM dual WHERE NOT EXISTS (SELECT 1 FROM zones WHERE name = 'Damietta' AND deleted_at IS NULL);
SET @next_id = @next_id + 1;

-- 11. North Sinai Governorate (شمال سيناء)
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
SELECT UUID(), 'North Sinai', @next_id, 
ST_GeomFromText('POLYGON((32.5000 31.3000, 34.9000 31.3000, 34.9000 30.0000, 32.5000 30.0000, 32.5000 31.3000))', 4326), 
1, 0, 0, NOW(), NOW()
FROM dual WHERE NOT EXISTS (SELECT 1 FROM zones WHERE name = 'North Sinai' AND deleted_at IS NULL);
SET @next_id = @next_id + 1;

-- 12. South Sinai Governorate (جنوب سيناء)
-- Replaces city-only "Sharm El Sheikh"
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
SELECT UUID(), 'South Sinai', @next_id, 
ST_GeomFromText('POLYGON((32.5000 30.0000, 34.9000 30.0000, 34.9000 27.7000, 33.5000 27.7000, 32.5000 29.0000, 32.5000 30.0000))', 4326), 
1, 0, 0, NOW(), NOW()
FROM dual WHERE NOT EXISTS (SELECT 1 FROM zones WHERE name = 'South Sinai' AND deleted_at IS NULL);
SET @next_id = @next_id + 1;

-- 13. Red Sea Governorate (البحر الأحمر)
-- Replaces city-only "Hurghada"
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
SELECT UUID(), 'Red Sea', @next_id, 
ST_GeomFromText('POLYGON((32.5000 29.0000, 33.5000 27.7000, 35.8000 23.5000, 35.0000 22.0000, 33.0000 22.0000, 32.0000 24.5000, 32.5000 29.0000))', 4326), 
1, 0, 0, NOW(), NOW()
FROM dual WHERE NOT EXISTS (SELECT 1 FROM zones WHERE name = 'Red Sea' AND deleted_at IS NULL);
SET @next_id = @next_id + 1;

-- 14. New Valley Governorate (الوادي الجديد)
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
SELECT UUID(), 'New Valley', @next_id, 
ST_GeomFromText('POLYGON((25.0000 30.0000, 30.5000 30.0000, 31.5000 24.0000, 31.5000 22.0000, 25.0000 22.0000, 25.0000 30.0000))', 4326), 
1, 0, 0, NOW(), NOW()
FROM dual WHERE NOT EXISTS (SELECT 1 FROM zones WHERE name = 'New Valley' AND deleted_at IS NULL);
SET @next_id = @next_id + 1;

-- 15. Matrouh Governorate (مطروح)
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
SELECT UUID(), 'Matrouh', @next_id, 
ST_GeomFromText('POLYGON((25.0000 31.6000, 30.0000 31.6000, 30.0000 30.4000, 25.0000 30.0000, 25.0000 31.6000))', 4326), 
1, 0, 0, NOW(), NOW()
FROM dual WHERE NOT EXISTS (SELECT 1 FROM zones WHERE name = 'Matrouh' AND deleted_at IS NULL);

-- ============================================================================
-- OPTIONAL: Delete old city-only zones and keep only governorate-level zones
-- Uncomment the lines below if you want to remove the old city-based zones
-- ============================================================================

-- DELETE FROM zones WHERE name = 'Greater Cairo' AND deleted_at IS NULL;
-- DELETE FROM zones WHERE name = 'Mansoura' AND deleted_at IS NULL;
-- DELETE FROM zones WHERE name = 'Zagazig' AND deleted_at IS NULL;
-- DELETE FROM zones WHERE name = 'Tanta' AND deleted_at IS NULL;
-- DELETE FROM zones WHERE name = 'Damanhur' AND deleted_at IS NULL;
-- DELETE FROM zones WHERE name = 'Sharm El Sheikh' AND deleted_at IS NULL;
-- DELETE FROM zones WHERE name = 'Hurghada' AND deleted_at IS NULL;
-- DELETE FROM zones WHERE name = 'New Administrative Capital' AND deleted_at IS NULL;

-- ============================================================================
-- VERIFICATION
-- ============================================================================

-- List all zones
SELECT 
    readable_id,
    name,
    is_active,
    created_at
FROM zones 
WHERE deleted_at IS NULL
ORDER BY readable_id;

-- Total zone count
SELECT COUNT(*) as total_zones FROM zones WHERE deleted_at IS NULL;












