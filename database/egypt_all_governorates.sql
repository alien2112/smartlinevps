-- ============================================================================
-- Egypt All 27 Governorates - Complete Zone Coverage
-- ============================================================================
-- This script creates zones for ALL Egyptian governorates with proper spatial data
-- All coordinates use SRID 4326 (WGS 84 - GPS standard)
-- Polygons are simplified boundaries suitable for ride-hailing zone detection
-- ============================================================================

USE drivemond_db;

-- First, clear existing zones to avoid duplicates
-- WARNING: This will delete ALL existing zones. Comment out if you want to keep them.
DELETE FROM zones WHERE deleted_at IS NULL;

-- Reset auto-increment for readable_id tracking
SET @next_id = 1;

-- ============================================================================
-- LOWER EGYPT (NILE DELTA & CANAL REGION)
-- ============================================================================

-- 1. Cairo Governorate (القاهرة)
-- Capital: Cairo | Population center of Egypt
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
VALUES (UUID(), 'Cairo', @next_id := 1, 
ST_GeomFromText('POLYGON((31.1500 30.1500, 31.4500 30.1500, 31.4500 29.9500, 31.1500 29.9500, 31.1500 30.1500))', 4326), 
1, 0, 0, NOW(), NOW());

-- 2. Giza Governorate (الجيزة)
-- Capital: Giza | Home of the Pyramids
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
VALUES (UUID(), 'Giza', @next_id := @next_id + 1, 
ST_GeomFromText('POLYGON((30.7000 30.2000, 31.3000 30.2000, 31.3000 29.2000, 30.0000 29.2000, 30.0000 30.0000, 30.7000 30.2000))', 4326), 
1, 0, 0, NOW(), NOW());

-- 3. Alexandria Governorate (الإسكندرية)
-- Capital: Alexandria | Second largest city, Mediterranean coast
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
VALUES (UUID(), 'Alexandria', @next_id := @next_id + 1, 
ST_GeomFromText('POLYGON((29.7000 31.3500, 30.1500 31.3500, 30.1500 31.0500, 29.7000 31.0500, 29.7000 31.3500))', 4326), 
1, 0, 0, NOW(), NOW());

-- 4. Qalyubia Governorate (القليوبية)
-- Capital: Banha | North of Cairo
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
VALUES (UUID(), 'Qalyubia', @next_id := @next_id + 1, 
ST_GeomFromText('POLYGON((31.0500 30.4500, 31.4500 30.4500, 31.4500 30.1000, 31.0500 30.1000, 31.0500 30.4500))', 4326), 
1, 0, 0, NOW(), NOW());

-- 5. Dakahlia Governorate (الدقهلية)
-- Capital: Mansoura | Eastern Delta
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
VALUES (UUID(), 'Dakahlia', @next_id := @next_id + 1, 
ST_GeomFromText('POLYGON((31.2000 31.6000, 31.7500 31.6000, 31.7500 30.9000, 31.2000 30.9000, 31.2000 31.6000))', 4326), 
1, 0, 0, NOW(), NOW());

-- 6. Sharqia Governorate (الشرقية)
-- Capital: Zagazig | Eastern Delta
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
VALUES (UUID(), 'Sharqia', @next_id := @next_id + 1, 
ST_GeomFromText('POLYGON((31.3000 30.9500, 32.0000 30.9500, 32.0000 30.3000, 31.3000 30.3000, 31.3000 30.9500))', 4326), 
1, 0, 0, NOW(), NOW());

-- 7. Gharbia Governorate (الغربية)
-- Capital: Tanta | Central Delta
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
VALUES (UUID(), 'Gharbia', @next_id := @next_id + 1, 
ST_GeomFromText('POLYGON((30.7500 31.1500, 31.2500 31.1500, 31.2500 30.7000, 30.7500 30.7000, 30.7500 31.1500))', 4326), 
1, 0, 0, NOW(), NOW());

-- 8. Monufia Governorate (المنوفية)
-- Capital: Shibin El Kom | Central Delta
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
VALUES (UUID(), 'Monufia', @next_id := @next_id + 1, 
ST_GeomFromText('POLYGON((30.8000 30.7500, 31.2000 30.7500, 31.2000 30.3500, 30.8000 30.3500, 30.8000 30.7500))', 4326), 
1, 0, 0, NOW(), NOW());

-- 9. Beheira Governorate (البحيرة)
-- Capital: Damanhur | Western Delta
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
VALUES (UUID(), 'Beheira', @next_id := @next_id + 1, 
ST_GeomFromText('POLYGON((30.0000 31.2500, 30.8000 31.2500, 30.8000 30.4000, 30.0000 30.4000, 30.0000 31.2500))', 4326), 
1, 0, 0, NOW(), NOW());

-- 10. Kafr El Sheikh Governorate (كفر الشيخ)
-- Capital: Kafr El Sheikh | Northern Delta
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
VALUES (UUID(), 'Kafr El Sheikh', @next_id := @next_id + 1, 
ST_GeomFromText('POLYGON((30.5000 31.6000, 31.3000 31.6000, 31.3000 31.0500, 30.5000 31.0500, 30.5000 31.6000))', 4326), 
1, 0, 0, NOW(), NOW());

-- 11. Damietta Governorate (دمياط)
-- Capital: Damietta | Mediterranean coast, Nile mouth
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
VALUES (UUID(), 'Damietta', @next_id := @next_id + 1, 
ST_GeomFromText('POLYGON((31.6000 31.6500, 32.0000 31.6500, 32.0000 31.3000, 31.6000 31.3000, 31.6000 31.6500))', 4326), 
1, 0, 0, NOW(), NOW());

-- ============================================================================
-- SUEZ CANAL REGION
-- ============================================================================

-- 12. Port Said Governorate (بورسعيد)
-- Capital: Port Said | Northern entrance of Suez Canal
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
VALUES (UUID(), 'Port Said', @next_id := @next_id + 1, 
ST_GeomFromText('POLYGON((32.2000 31.4000, 32.6000 31.4000, 32.6000 31.1500, 32.2000 31.1500, 32.2000 31.4000))', 4326), 
1, 0, 0, NOW(), NOW());

-- 13. Ismailia Governorate (الإسماعيلية)
-- Capital: Ismailia | Central Suez Canal
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
VALUES (UUID(), 'Ismailia', @next_id := @next_id + 1, 
ST_GeomFromText('POLYGON((31.8000 30.8500, 32.6000 30.8500, 32.6000 30.3000, 31.8000 30.3000, 31.8000 30.8500))', 4326), 
1, 0, 0, NOW(), NOW());

-- 14. Suez Governorate (السويس)
-- Capital: Suez | Southern entrance of Suez Canal
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
VALUES (UUID(), 'Suez', @next_id := @next_id + 1, 
ST_GeomFromText('POLYGON((32.3000 30.1500, 32.7000 30.1500, 32.7000 29.8500, 32.3000 29.8500, 32.3000 30.1500))', 4326), 
1, 0, 0, NOW(), NOW());

-- ============================================================================
-- SINAI PENINSULA
-- ============================================================================

-- 15. North Sinai Governorate (شمال سيناء)
-- Capital: Arish | Northern Sinai Peninsula
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
VALUES (UUID(), 'North Sinai', @next_id := @next_id + 1, 
ST_GeomFromText('POLYGON((32.5000 31.3000, 34.9000 31.3000, 34.9000 30.0000, 32.5000 30.0000, 32.5000 31.3000))', 4326), 
1, 0, 0, NOW(), NOW());

-- 16. South Sinai Governorate (جنوب سيناء)
-- Capital: El Tor | Southern Sinai, includes Sharm El Sheikh
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
VALUES (UUID(), 'South Sinai', @next_id := @next_id + 1, 
ST_GeomFromText('POLYGON((32.5000 30.0000, 34.9000 30.0000, 34.9000 27.7000, 33.5000 27.7000, 32.5000 29.0000, 32.5000 30.0000))', 4326), 
1, 0, 0, NOW(), NOW());

-- ============================================================================
-- RED SEA COAST
-- ============================================================================

-- 17. Red Sea Governorate (البحر الأحمر)
-- Capital: Hurghada | Includes Hurghada, Safaga, Marsa Alam
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
VALUES (UUID(), 'Red Sea', @next_id := @next_id + 1, 
ST_GeomFromText('POLYGON((32.5000 29.0000, 33.5000 27.7000, 35.8000 23.5000, 35.0000 22.0000, 33.0000 22.0000, 32.0000 24.5000, 32.5000 29.0000))', 4326), 
1, 0, 0, NOW(), NOW());

-- ============================================================================
-- UPPER EGYPT (NILE VALLEY)
-- ============================================================================

-- 18. Fayoum Governorate (الفيوم)
-- Capital: Fayoum | Oasis west of the Nile
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
VALUES (UUID(), 'Fayoum', @next_id := @next_id + 1, 
ST_GeomFromText('POLYGON((30.3000 29.7000, 31.1000 29.7000, 31.1000 29.0000, 30.3000 29.0000, 30.3000 29.7000))', 4326), 
1, 0, 0, NOW(), NOW());

-- 19. Beni Suef Governorate (بني سويف)
-- Capital: Beni Suef | Upper Egypt
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
VALUES (UUID(), 'Beni Suef', @next_id := @next_id + 1, 
ST_GeomFromText('POLYGON((30.6000 29.4000, 31.4000 29.4000, 31.4000 28.8000, 30.6000 28.8000, 30.6000 29.4000))', 4326), 
1, 0, 0, NOW(), NOW());

-- 20. Minya Governorate (المنيا)
-- Capital: Minya | Upper Egypt
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
VALUES (UUID(), 'Minya', @next_id := @next_id + 1, 
ST_GeomFromText('POLYGON((30.4000 28.8000, 31.5000 28.8000, 31.5000 27.7000, 30.4000 27.7000, 30.4000 28.8000))', 4326), 
1, 0, 0, NOW(), NOW());

-- 21. Asyut Governorate (أسيوط)
-- Capital: Asyut | Upper Egypt
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
VALUES (UUID(), 'Asyut', @next_id := @next_id + 1, 
ST_GeomFromText('POLYGON((30.7000 27.7000, 31.6000 27.7000, 31.6000 26.8000, 30.7000 26.8000, 30.7000 27.7000))', 4326), 
1, 0, 0, NOW(), NOW());

-- 22. Sohag Governorate (سوهاج)
-- Capital: Sohag | Upper Egypt
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
VALUES (UUID(), 'Sohag', @next_id := @next_id + 1, 
ST_GeomFromText('POLYGON((31.3000 26.8000, 32.2000 26.8000, 32.2000 26.1000, 31.3000 26.1000, 31.3000 26.8000))', 4326), 
1, 0, 0, NOW(), NOW());

-- 23. Qena Governorate (قنا)
-- Capital: Qena | Upper Egypt
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
VALUES (UUID(), 'Qena', @next_id := @next_id + 1, 
ST_GeomFromText('POLYGON((32.2000 26.3000, 33.1000 26.3000, 33.1000 25.7000, 32.2000 25.7000, 32.2000 26.3000))', 4326), 
1, 0, 0, NOW(), NOW());

-- 24. Luxor Governorate (الأقصر)
-- Capital: Luxor | Ancient Thebes, major tourist destination
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
VALUES (UUID(), 'Luxor', @next_id := @next_id + 1, 
ST_GeomFromText('POLYGON((32.5000 25.8500, 32.8500 25.8500, 32.8500 25.5500, 32.5000 25.5500, 32.5000 25.8500))', 4326), 
1, 0, 0, NOW(), NOW());

-- 25. Aswan Governorate (أسوان)
-- Capital: Aswan | Southernmost governorate on the Nile
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
VALUES (UUID(), 'Aswan', @next_id := @next_id + 1, 
ST_GeomFromText('POLYGON((32.5000 25.0000, 33.5000 25.0000, 33.5000 22.0000, 31.5000 22.0000, 31.5000 24.0000, 32.5000 25.0000))', 4326), 
1, 0, 0, NOW(), NOW());

-- ============================================================================
-- WESTERN DESERT
-- ============================================================================

-- 26. New Valley Governorate (الوادي الجديد)
-- Capital: Kharga | Largest governorate, Western Desert oases
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
VALUES (UUID(), 'New Valley', @next_id := @next_id + 1, 
ST_GeomFromText('POLYGON((25.0000 30.0000, 30.5000 30.0000, 31.5000 24.0000, 31.5000 22.0000, 25.0000 22.0000, 25.0000 30.0000))', 4326), 
1, 0, 0, NOW(), NOW());

-- 27. Matrouh Governorate (مطروح)
-- Capital: Marsa Matrouh | Northwestern coast, includes Siwa Oasis
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) 
VALUES (UUID(), 'Matrouh', @next_id := @next_id + 1, 
ST_GeomFromText('POLYGON((25.0000 31.6000, 30.0000 31.6000, 30.0000 30.4000, 25.0000 30.0000, 25.0000 31.6000))', 4326), 
1, 0, 0, NOW(), NOW());

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================

-- List all zones
SELECT 
    readable_id,
    name,
    is_active,
    ST_AsText(coordinates) as coordinates_wkt,
    created_at
FROM zones 
WHERE deleted_at IS NULL
ORDER BY readable_id;

-- Total zone count (should be 27)
SELECT COUNT(*) as total_governorates FROM zones WHERE deleted_at IS NULL;

-- Test: Check if Cairo center (30.0444°N, 31.2357°E) falls within Cairo zone
-- Note: In WKT, format is (longitude latitude) = (31.2357 30.0444)
SELECT 
    name,
    ST_Contains(
        coordinates, 
        ST_GeomFromText('POINT(31.2357 30.0444)', 4326)
    ) as contains_cairo_center
FROM zones 
WHERE name = 'Cairo' AND deleted_at IS NULL;

-- Test: Check if Pyramids area (29.9792°N, 31.1342°E) falls within Giza zone
SELECT 
    name,
    ST_Contains(
        coordinates, 
        ST_GeomFromText('POINT(31.1342 29.9792)', 4326)
    ) as contains_pyramids
FROM zones 
WHERE name = 'Giza' AND deleted_at IS NULL;

-- Test: Check if Alexandria center (31.2001°N, 29.9187°E) falls within Alexandria zone
SELECT 
    name,
    ST_Contains(
        coordinates, 
        ST_GeomFromText('POINT(29.9187 31.2001)', 4326)
    ) as contains_alex_center
FROM zones 
WHERE name = 'Alexandria' AND deleted_at IS NULL;

-- ============================================================================
-- SUMMARY OF ALL 27 EGYPTIAN GOVERNORATES:
-- ============================================================================
-- Lower Egypt (Nile Delta):
--   1. Cairo, 2. Giza, 3. Alexandria, 4. Qalyubia, 5. Dakahlia, 
--   6. Sharqia, 7. Gharbia, 8. Monufia, 9. Beheira, 10. Kafr El Sheikh, 11. Damietta
-- Canal Region:
--   12. Port Said, 13. Ismailia, 14. Suez
-- Sinai:
--   15. North Sinai, 16. South Sinai
-- Red Sea Coast:
--   17. Red Sea
-- Upper Egypt (Nile Valley):
--   18. Fayoum, 19. Beni Suef, 20. Minya, 21. Asyut, 22. Sohag, 
--   23. Qena, 24. Luxor, 25. Aswan
-- Western Desert:
--   26. New Valley, 27. Matrouh
-- ============================================================================












