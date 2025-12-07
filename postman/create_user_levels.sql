-- Create Default User Levels for Customer and Driver
-- Run this SQL script if you get "Create a level first" error during registration

-- Customer Level (Bronze - Starting Level)
INSERT INTO user_levels (
    id, 
    sequence, 
    name, 
    reward_type, 
    reward_amount, 
    targeted_ride, 
    targeted_ride_point, 
    targeted_amount, 
    targeted_amount_point, 
    targeted_cancel, 
    targeted_cancel_point, 
    targeted_review, 
    targeted_review_point, 
    user_type, 
    is_active, 
    created_at, 
    updated_at
) VALUES (
    UUID(),
    1,
    'Bronze',
    'percentage',
    0,
    0,
    0,
    0,
    0,
    0,
    0,
    0,
    0,
    'customer',
    1,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Driver Level (Bronze - Starting Level)
INSERT INTO user_levels (
    id, 
    sequence, 
    name, 
    reward_type, 
    reward_amount, 
    targeted_ride, 
    targeted_ride_point, 
    targeted_amount, 
    targeted_amount_point, 
    targeted_cancel, 
    targeted_cancel_point, 
    targeted_review, 
    targeted_review_point, 
    user_type, 
    is_active, 
    created_at, 
    updated_at
) VALUES (
    UUID(),
    1,
    'Bronze',
    'percentage',
    0,
    0,
    0,
    0,
    0,
    0,
    0,
    0,
    0,
    'driver',
    1,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Verify levels were created
SELECT id, name, sequence, user_type, is_active FROM user_levels WHERE sequence = 1;

