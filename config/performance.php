<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Performance Optimization Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control various performance optimizations throughout
    | the application. Adjust as needed for your infrastructure.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Cache TTLs (in seconds)
    |--------------------------------------------------------------------------
    */
    'cache' => [
        // Business configuration cache TTL
        'config_ttl' => env('PERF_CONFIG_CACHE_TTL', 3600),
        
        // Route API response cache TTL
        'route_ttl' => env('PERF_ROUTE_CACHE_TTL', 1800),
        
        // Zone data cache TTL
        'zone_ttl' => env('PERF_ZONE_CACHE_TTL', 300),
        
        // Driver location cache TTL (for aggregations)
        'driver_location_ttl' => env('PERF_DRIVER_LOCATION_TTL', 30),
        
        // Pending trips count cache TTL
        'pending_trips_ttl' => env('PERF_PENDING_TRIPS_TTL', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Spatial Query Settings
    |--------------------------------------------------------------------------
    */
    'spatial' => [
        // Enable spatial index usage (disable if indexes not migrated)
        'use_spatial_index' => env('PERF_USE_SPATIAL_INDEX', true),
        
        // Default search radius for driver queries (km)
        'default_radius_km' => env('PERF_DEFAULT_RADIUS_KM', 5),
        
        // Maximum drivers to return in nearby search
        'max_nearby_drivers' => env('PERF_MAX_NEARBY_DRIVERS', 100),
        
        // Coordinate rounding precision for cache keys (decimal places)
        // 4 = ~11m accuracy, 3 = ~111m accuracy
        'coordinate_precision' => env('PERF_COORD_PRECISION', 4),
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Optimization Settings
    |--------------------------------------------------------------------------
    */
    'query' => [
        // Maximum results for pending rides query
        'max_pending_rides' => env('PERF_MAX_PENDING_RIDES', 20),
        
        // Enable selective eager loading
        'selective_eager_loading' => env('PERF_SELECTIVE_EAGER_LOADING', true),
        
        // Use whereExists instead of whereHas for performance
        'use_where_exists' => env('PERF_USE_WHERE_EXISTS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcasting Settings
    |--------------------------------------------------------------------------
    */
    'broadcasting' => [
        // Queue name for broadcast jobs
        'queue' => env('PERF_BROADCAST_QUEUE', 'broadcasting'),
        
        // Enable async broadcasting (via queue)
        'async' => env('PERF_ASYNC_BROADCAST', true),
        
        // Batch size for multi-driver broadcasts
        'batch_size' => env('PERF_BROADCAST_BATCH_SIZE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limiting' => [
        // Driver location update throttle (seconds)
        'location_update_throttle' => env('PERF_LOCATION_THROTTLE', 3),
        
        // Pending rides polling throttle (seconds)
        'pending_rides_throttle' => env('PERF_PENDING_RIDES_THROTTLE', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Settings
    |--------------------------------------------------------------------------
    */
    'logging' => [
        // Enable debug logging in hot paths
        'debug_hot_paths' => env('PERF_LOG_HOT_PATHS', false),
        
        // Enable query logging (high overhead, use only for debugging)
        'query_log' => env('PERF_QUERY_LOG', false),
    ],
];
