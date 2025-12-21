<?php

/**
 * API Rate Limiting Configuration
 *
 * Configurable rate limits for different API endpoint types.
 * All values can be adjusted via environment variables.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Rate Limit Presets
    |--------------------------------------------------------------------------
    |
    | Define rate limits for different types of API endpoints.
    | Each preset has 'max' (requests allowed) and 'decay' (window in seconds).
    |
    */
    
    'limits' => [
        // Critical trip actions - stricter limits
        'trip_accept' => [
            'max' => (int) env('RATE_LIMIT_TRIP_ACCEPT_MAX', 10),
            'decay' => (int) env('RATE_LIMIT_TRIP_ACCEPT_DECAY', 60),
        ],
        
        'trip_cancel' => [
            'max' => (int) env('RATE_LIMIT_TRIP_CANCEL_MAX', 5),
            'decay' => (int) env('RATE_LIMIT_TRIP_CANCEL_DECAY', 60),
        ],
        
        // Location updates - moderate limits
        'location_update' => [
            'max' => (int) env('RATE_LIMIT_LOCATION_UPDATE_MAX', 100),
            'decay' => (int) env('RATE_LIMIT_LOCATION_UPDATE_DECAY', 60),
        ],
        
        // Driver search/matching
        'driver_search' => [
            'max' => (int) env('RATE_LIMIT_DRIVER_SEARCH_MAX', 30),
            'decay' => (int) env('RATE_LIMIT_DRIVER_SEARCH_DECAY', 60),
        ],
        
        // General API - lenient limits
        'general' => [
            'max' => (int) env('RATE_LIMIT_GENERAL_MAX', 60),
            'decay' => (int) env('RATE_LIMIT_GENERAL_DECAY', 60),
        ],
        
        // Authentication endpoints - anti-brute-force
        'auth' => [
            'max' => (int) env('RATE_LIMIT_AUTH_MAX', 5),
            'decay' => (int) env('RATE_LIMIT_AUTH_DECAY', 60),
        ],
        
        // SMS/OTP sending - strict limits
        'sms' => [
            'max' => (int) env('RATE_LIMIT_SMS_MAX', 3),
            'decay' => (int) env('RATE_LIMIT_SMS_DECAY', 300), // 5 minutes
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Driver Matching Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for driver search and matching algorithm.
    |
    */
    
    'driver_matching' => [
        // Search radius in kilometers
        'search_radius_km' => (int) env('DRIVER_SEARCH_RADIUS_KM', 10),
        
        // Maximum drivers to notify for a ride
        'max_drivers_to_notify' => (int) env('MAX_DRIVERS_TO_NOTIFY', 10),
        
        // Timeout for driver to accept (seconds)
        'match_timeout_seconds' => (int) env('DRIVER_MATCH_TIMEOUT_SECONDS', 120),
        
        // Minimum rating required for driver
        'min_driver_rating' => (float) env('MIN_DRIVER_RATING', 3.5),
        
        // Maximum distance jump for suspicious activity (km)
        'max_distance_jump_km' => (float) env('MAX_DISTANCE_JUMP_KM', 5.0),
        
        // How long a driver can be idle before considered offline (minutes)
        'driver_idle_timeout_minutes' => (int) env('DRIVER_IDLE_TIMEOUT_MINUTES', 30),
        
        // Expand search radius after initial timeout (multiplier)
        'search_radius_expansion_multiplier' => (float) env('SEARCH_RADIUS_EXPANSION', 1.5),
        
        // Maximum expanded radius (km)
        'max_search_radius_km' => (int) env('MAX_DRIVER_SEARCH_RADIUS_KM', 25),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ride Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for ride management.
    |
    */
    
    'ride' => [
        // Grace period for late cancellation (minutes)
        'free_cancellation_minutes' => (int) env('FREE_CANCELLATION_MINUTES', 5),
        
        // Maximum ride request validity (minutes)
        'request_validity_minutes' => (int) env('RIDE_REQUEST_VALIDITY_MINUTES', 10),
        
        // Driver arrival timeout (minutes)
        'driver_arrival_timeout_minutes' => (int) env('DRIVER_ARRIVAL_TIMEOUT_MINUTES', 15),
        
        // Maximum waiting time for customer (minutes)
        'max_customer_wait_minutes' => (int) env('MAX_CUSTOMER_WAIT_MINUTES', 5),
    ],
];
