-- Test Zone for Cairo, Egypt
-- This creates a zone covering central Cairo area
-- Coordinates form a polygon around Cairo city center

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
    '[
        {"lat": 30.1, "lng": 31.1},
        {"lat": 30.1, "lng": 31.4},
        {"lat": 29.9, "lng": 31.4},
        {"lat": 29.9, "lng": 31.1}
    ]',
    1,
    NOW(),
    NOW()
);

-- Verify the zone was created
SELECT id, name, is_active, coordinates FROM zones WHERE name = 'Cairo Test Zone';

-- Test if your coordinates fall within this zone
-- Your test coordinates: lat=30.0444, lng=31.2357
-- These should now be within the zone boundaries


