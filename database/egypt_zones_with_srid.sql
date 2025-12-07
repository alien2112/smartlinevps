-- Egypt Zones with SRID 4326
-- This script creates zones for major Egyptian cities with proper spatial data
-- All coordinates use SRID 4326 (WGS 84 - GPS standard)

USE drivemond_db;

-- First, let's clear existing zones (optional - comment out if you want to keep existing zones)
-- DELETE FROM zones WHERE deleted_at IS NULL;

-- Get the next readable_id
SET @next_id = (SELECT COALESCE(MAX(readable_id), 0) + 1 FROM zones);

-- 1. Greater Cairo Zone (covers Cairo, Giza, and surrounding areas)
INSERT INTO zones (
    id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at
) VALUES (
    UUID(),
    'Greater Cairo',
    @next_id,
    ST_GeomFromText('POLYGON((
        31.0500 30.2000,
        31.5000 30.2000,
        31.5000 29.8000,
        31.0500 29.8000,
        31.0500 30.2000
    ))', 4326),
    1, 0, 0, NOW(), NOW()
);
SET @next_id = @next_id + 1;

-- 2. Alexandria Zone
INSERT INTO zones (
    id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at
) VALUES (
    UUID(),
    'Alexandria',
    @next_id,
    ST_GeomFromText('POLYGON((
        29.7500 31.3500,
        30.1000 31.3500,
        30.1000 31.1000,
        29.7500 31.1000,
        29.7500 31.3500
    ))', 4326),
    1, 0, 0, NOW(), NOW()
);
SET @next_id = @next_id + 1;

-- 3. Luxor Zone
INSERT INTO zones (
    id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at
) VALUES (
    UUID(),
    'Luxor',
    @next_id,
    ST_GeomFromText('POLYGON((
        32.5500 25.8000,
        32.7500 25.8000,
        32.7500 25.6000,
        32.5500 25.6000,
        32.5500 25.8000
    ))', 4326),
    1, 0, 0, NOW(), NOW()
);
SET @next_id = @next_id + 1;

-- 4. Aswan Zone
INSERT INTO zones (
    id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at
) VALUES (
    UUID(),
    'Aswan',
    @next_id,
    ST_GeomFromText('POLYGON((
        32.8000 24.1500,
        33.0000 24.1500,
        33.0000 23.9500,
        32.8000 23.9500,
        32.8000 24.1500
    ))', 4326),
    1, 0, 0, NOW(), NOW()
);
SET @next_id = @next_id + 1;

-- 5. Sharm El Sheikh Zone
INSERT INTO zones (
    id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at
) VALUES (
    UUID(),
    'Sharm El Sheikh',
    @next_id,
    ST_GeomFromText('POLYGON((
        34.2000 28.0500,
        34.4500 28.0500,
        34.4500 27.8500,
        34.2000 27.8500,
        34.2000 28.0500
    ))', 4326),
    1, 0, 0, NOW(), NOW()
);
SET @next_id = @next_id + 1;

-- 6. Hurghada Zone
INSERT INTO zones (
    id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at
) VALUES (
    UUID(),
    'Hurghada',
    @next_id,
    ST_GeomFromText('POLYGON((
        33.7000 27.3500,
        33.9500 27.3500,
        33.9500 27.1500,
        33.7000 27.1500,
        33.7000 27.3500
    ))', 4326),
    1, 0, 0, NOW(), NOW()
);
SET @next_id = @next_id + 1;

-- 7. Port Said Zone
INSERT INTO zones (
    id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at
) VALUES (
    UUID(),
    'Port Said',
    @next_id,
    ST_GeomFromText('POLYGON((
        32.2500 31.3500,
        32.4000 31.3500,
        32.4000 31.2000,
        32.2500 31.2000,
        32.2500 31.3500
    ))', 4326),
    1, 0, 0, NOW(), NOW()
);
SET @next_id = @next_id + 1;

-- 8. Suez Zone
INSERT INTO zones (
    id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at
) VALUES (
    UUID(),
    'Suez',
    @next_id,
    ST_GeomFromText('POLYGON((
        32.4500 30.0500,
        32.6500 30.0500,
        32.6500 29.9000,
        32.4500 29.9000,
        32.4500 30.0500
    ))', 4326),
    1, 0, 0, NOW(), NOW()
);
SET @next_id = @next_id + 1;

-- 9. Ismailia Zone
INSERT INTO zones (
    id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at
) VALUES (
    UUID(),
    'Ismailia',
    @next_id,
    ST_GeomFromText('POLYGON((
        32.2000 30.7000,
        32.4000 30.7000,
        32.4000 30.5000,
        32.2000 30.5000,
        32.2000 30.7000
    ))', 4326),
    1, 0, 0, NOW(), NOW()
);
SET @next_id = @next_id + 1;

-- 10. Mansoura Zone (Dakahlia)
INSERT INTO zones (
    id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at
) VALUES (
    UUID(),
    'Mansoura',
    @next_id,
    ST_GeomFromText('POLYGON((
        31.3000 31.1500,
        31.5000 31.1500,
        31.5000 30.9500,
        31.3000 30.9500,
        31.3000 31.1500
    ))', 4326),
    1, 0, 0, NOW(), NOW()
);
SET @next_id = @next_id + 1;

-- 11. Tanta Zone (Gharbia)
INSERT INTO zones (
    id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at
) VALUES (
    UUID(),
    'Tanta',
    @next_id,
    ST_GeomFromText('POLYGON((
        30.9000 30.9000,
        31.1000 30.9000,
        31.1000 30.7000,
        30.9000 30.7000,
        30.9000 30.9000
    ))', 4326),
    1, 0, 0, NOW(), NOW()
);
SET @next_id = @next_id + 1;

-- 12. Zagazig Zone (Sharqia)
INSERT INTO zones (
    id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at
) VALUES (
    UUID(),
    'Zagazig',
    @next_id,
    ST_GeomFromText('POLYGON((
        31.4500 30.6500,
        31.6500 30.6500,
        31.6500 30.4500,
        31.4500 30.4500,
        31.4500 30.6500
    ))', 4326),
    1, 0, 0, NOW(), NOW()
);
SET @next_id = @next_id + 1;

-- 13. Asyut Zone
INSERT INTO zones (
    id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at
) VALUES (
    UUID(),
    'Asyut',
    @next_id,
    ST_GeomFromText('POLYGON((
        31.0500 27.2500,
        31.2500 27.2500,
        31.2500 27.0500,
        31.0500 27.0500,
        31.0500 27.2500
    ))', 4326),
    1, 0, 0, NOW(), NOW()
);
SET @next_id = @next_id + 1;

-- 14. Sohag Zone
INSERT INTO zones (
    id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at
) VALUES (
    UUID(),
    'Sohag',
    @next_id,
    ST_GeomFromText('POLYGON((
        31.6000 26.6500,
        31.8000 26.6500,
        31.8000 26.4500,
        31.6000 26.4500,
        31.6000 26.6500
    ))', 4326),
    1, 0, 0, NOW(), NOW()
);
SET @next_id = @next_id + 1;

-- 15. Fayoum Zone
INSERT INTO zones (
    id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at
) VALUES (
    UUID(),
    'Fayoum',
    @next_id,
    ST_GeomFromText('POLYGON((
        30.7000 29.4500,
        30.9500 29.4500,
        30.9500 29.2500,
        30.7000 29.2500,
        30.7000 29.4500
    ))', 4326),
    1, 0, 0, NOW(), NOW()
);
SET @next_id = @next_id + 1;

-- 16. Damanhur Zone (Beheira)
INSERT INTO zones (
    id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at
) VALUES (
    UUID(),
    'Damanhur',
    @next_id,
    ST_GeomFromText('POLYGON((
        30.3500 31.1500,
        30.5500 31.1500,
        30.5500 30.9500,
        30.3500 30.9500,
        30.3500 31.1500
    ))', 4326),
    1, 0, 0, NOW(), NOW()
);
SET @next_id = @next_id + 1;

-- 17. Beni Suef Zone
INSERT INTO zones (
    id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at
) VALUES (
    UUID(),
    'Beni Suef',
    @next_id,
    ST_GeomFromText('POLYGON((
        31.0000 29.1500,
        31.2000 29.1500,
        31.2000 28.9500,
        31.0000 28.9500,
        31.0000 29.1500
    ))', 4326),
    1, 0, 0, NOW(), NOW()
);
SET @next_id = @next_id + 1;

-- 18. Minya Zone
INSERT INTO zones (
    id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at
) VALUES (
    UUID(),
    'Minya',
    @next_id,
    ST_GeomFromText('POLYGON((
        30.7000 28.2000,
        30.9000 28.2000,
        30.9000 28.0000,
        30.7000 28.0000,
        30.7000 28.2000
    ))', 4326),
    1, 0, 0, NOW(), NOW()
);
SET @next_id = @next_id + 1;

-- 19. Qena Zone
INSERT INTO zones (
    id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at
) VALUES (
    UUID(),
    'Qena',
    @next_id,
    ST_GeomFromText('POLYGON((
        32.6500 26.2500,
        32.8500 26.2500,
        32.8500 26.0500,
        32.6500 26.0500,
        32.6500 26.2500
    ))', 4326),
    1, 0, 0, NOW(), NOW()
);
SET @next_id = @next_id + 1;

-- 20. New Capital Zone (New Administrative Capital)
INSERT INTO zones (
    id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at
) VALUES (
    UUID(),
    'New Administrative Capital',
    @next_id,
    ST_GeomFromText('POLYGON((
        31.7000 30.1000,
        31.9500 30.1000,
        31.9500 29.9000,
        31.7000 29.9000,
        31.7000 30.1000
    ))', 4326),
    1, 0, 0, NOW(), NOW()
);

-- Verify all zones were created
SELECT 
    id,
    name,
    readable_id,
    is_active,
    ST_SRID(coordinates) as srid,
    ST_AsText(coordinates) as coordinates_wkt
FROM zones 
WHERE deleted_at IS NULL
ORDER BY readable_id;

-- Test if Cairo coordinates (30.0444, 31.2357) fall within Greater Cairo zone
SELECT 
    name,
    ST_Contains(
        coordinates, 
        ST_GeomFromText('POINT(31.2357 30.0444)', 4326)
    ) as contains_cairo_center
FROM zones 
WHERE name = 'Greater Cairo' AND deleted_at IS NULL;

-- Show total count
SELECT COUNT(*) as total_zones FROM zones WHERE deleted_at IS NULL;

