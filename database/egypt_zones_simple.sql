-- Egypt Zones with SRID 4326
-- Single-line format for compatibility

USE drivemond_db;

-- 1. Greater Cairo Zone (covers Cairo, Giza, and surrounding areas)
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) VALUES (UUID(), 'Greater Cairo', 1, ST_GeomFromText('POLYGON((31.0500 30.2000, 31.5000 30.2000, 31.5000 29.8000, 31.0500 29.8000, 31.0500 30.2000))', 4326), 1, 0, 0, NOW(), NOW());

-- 2. Alexandria Zone
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) VALUES (UUID(), 'Alexandria', 2, ST_GeomFromText('POLYGON((29.7500 31.3500, 30.1000 31.3500, 30.1000 31.1000, 29.7500 31.1000, 29.7500 31.3500))', 4326), 1, 0, 0, NOW(), NOW());

-- 3. Luxor Zone
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) VALUES (UUID(), 'Luxor', 3, ST_GeomFromText('POLYGON((32.5500 25.8000, 32.7500 25.8000, 32.7500 25.6000, 32.5500 25.6000, 32.5500 25.8000))', 4326), 1, 0, 0, NOW(), NOW());

-- 4. Aswan Zone
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) VALUES (UUID(), 'Aswan', 4, ST_GeomFromText('POLYGON((32.8000 24.1500, 33.0000 24.1500, 33.0000 23.9500, 32.8000 23.9500, 32.8000 24.1500))', 4326), 1, 0, 0, NOW(), NOW());

-- 5. Sharm El Sheikh Zone
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) VALUES (UUID(), 'Sharm El Sheikh', 5, ST_GeomFromText('POLYGON((34.2000 28.0500, 34.4500 28.0500, 34.4500 27.8500, 34.2000 27.8500, 34.2000 28.0500))', 4326), 1, 0, 0, NOW(), NOW());

-- 6. Hurghada Zone
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) VALUES (UUID(), 'Hurghada', 6, ST_GeomFromText('POLYGON((33.7000 27.3500, 33.9500 27.3500, 33.9500 27.1500, 33.7000 27.1500, 33.7000 27.3500))', 4326), 1, 0, 0, NOW(), NOW());

-- 7. Port Said Zone
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) VALUES (UUID(), 'Port Said', 7, ST_GeomFromText('POLYGON((32.2500 31.3500, 32.4000 31.3500, 32.4000 31.2000, 32.2500 31.2000, 32.2500 31.3500))', 4326), 1, 0, 0, NOW(), NOW());

-- 8. Suez Zone
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) VALUES (UUID(), 'Suez', 8, ST_GeomFromText('POLYGON((32.4500 30.0500, 32.6500 30.0500, 32.6500 29.9000, 32.4500 29.9000, 32.4500 30.0500))', 4326), 1, 0, 0, NOW(), NOW());

-- 9. Ismailia Zone
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) VALUES (UUID(), 'Ismailia', 9, ST_GeomFromText('POLYGON((32.2000 30.7000, 32.4000 30.7000, 32.4000 30.5000, 32.2000 30.5000, 32.2000 30.7000))', 4326), 1, 0, 0, NOW(), NOW());

-- 10. Mansoura Zone (Dakahlia)
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) VALUES (UUID(), 'Mansoura', 10, ST_GeomFromText('POLYGON((31.3000 31.1500, 31.5000 31.1500, 31.5000 30.9500, 31.3000 30.9500, 31.3000 31.1500))', 4326), 1, 0, 0, NOW(), NOW());

-- 11. Tanta Zone (Gharbia)
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) VALUES (UUID(), 'Tanta', 11, ST_GeomFromText('POLYGON((30.9000 30.9000, 31.1000 30.9000, 31.1000 30.7000, 30.9000 30.7000, 30.9000 30.9000))', 4326), 1, 0, 0, NOW(), NOW());

-- 12. Zagazig Zone (Sharqia)
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) VALUES (UUID(), 'Zagazig', 12, ST_GeomFromText('POLYGON((31.4500 30.6500, 31.6500 30.6500, 31.6500 30.4500, 31.4500 30.4500, 31.4500 30.6500))', 4326), 1, 0, 0, NOW(), NOW());

-- 13. Asyut Zone
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) VALUES (UUID(), 'Asyut', 13, ST_GeomFromText('POLYGON((31.0500 27.2500, 31.2500 27.2500, 31.2500 27.0500, 31.0500 27.0500, 31.0500 27.2500))', 4326), 1, 0, 0, NOW(), NOW());

-- 14. Sohag Zone
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) VALUES (UUID(), 'Sohag', 14, ST_GeomFromText('POLYGON((31.6000 26.6500, 31.8000 26.6500, 31.8000 26.4500, 31.6000 26.4500, 31.6000 26.6500))', 4326), 1, 0, 0, NOW(), NOW());

-- 15. Fayoum Zone
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) VALUES (UUID(), 'Fayoum', 15, ST_GeomFromText('POLYGON((30.7000 29.4500, 30.9500 29.4500, 30.9500 29.2500, 30.7000 29.2500, 30.7000 29.4500))', 4326), 1, 0, 0, NOW(), NOW());

-- 16. Damanhur Zone (Beheira)
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) VALUES (UUID(), 'Damanhur', 16, ST_GeomFromText('POLYGON((30.3500 31.1500, 30.5500 31.1500, 30.5500 30.9500, 30.3500 30.9500, 30.3500 31.1500))', 4326), 1, 0, 0, NOW(), NOW());

-- 17. Beni Suef Zone
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) VALUES (UUID(), 'Beni Suef', 17, ST_GeomFromText('POLYGON((31.0000 29.1500, 31.2000 29.1500, 31.2000 28.9500, 31.0000 28.9500, 31.0000 29.1500))', 4326), 1, 0, 0, NOW(), NOW());

-- 18. Minya Zone
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) VALUES (UUID(), 'Minya', 18, ST_GeomFromText('POLYGON((30.7000 28.2000, 30.9000 28.2000, 30.9000 28.0000, 30.7000 28.0000, 30.7000 28.2000))', 4326), 1, 0, 0, NOW(), NOW());

-- 19. Qena Zone
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) VALUES (UUID(), 'Qena', 19, ST_GeomFromText('POLYGON((32.6500 26.2500, 32.8500 26.2500, 32.8500 26.0500, 32.6500 26.0500, 32.6500 26.2500))', 4326), 1, 0, 0, NOW(), NOW());

-- 20. New Capital Zone (New Administrative Capital)
INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at) VALUES (UUID(), 'New Administrative Capital', 20, ST_GeomFromText('POLYGON((31.7000 30.1000, 31.9500 30.1000, 31.9500 29.9000, 31.7000 29.9000, 31.7000 30.1000))', 4326), 1, 0, 0, NOW(), NOW());

