<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Location Tracking Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for the driver location tracking system.
    | These values control when location updates are accepted, how anomalies
    | are detected, and how route data is stored.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Frontend Trigger Thresholds
    |--------------------------------------------------------------------------
    |
    | These values are guidelines for the mobile app to decide when to send
    | location updates. The backend doesn't enforce these strictly, but uses
    | them for anomaly detection.
    |
    */

    'min_distance_meters' => env('TRACKING_MIN_DISTANCE', 50),
    'min_time_seconds' => env('TRACKING_MIN_TIME', 15),

    /*
    |--------------------------------------------------------------------------
    | Sanity Check Thresholds
    |--------------------------------------------------------------------------
    |
    | These values are used by the backend to detect suspicious location
    | updates that might indicate GPS spoofing, fraud, or technical issues.
    |
    */

    // Maximum reasonable speed (in km/h) for a vehicle
    'max_speed_kmh' => env('TRACKING_MAX_SPEED', 200),

    // Maximum reasonable speed (in m/s) for a vehicle
    'max_speed_ms' => env('TRACKING_MAX_SPEED_MS', 55.5), // ~200 km/h

    // Maximum distance jump allowed between consecutive updates
    'max_jump_meters' => env('TRACKING_MAX_JUMP', 1000),

    // Maximum time difference for jump detection (seconds)
    'max_jump_time_seconds' => env('TRACKING_MAX_JUMP_TIME', 15),

    // Maximum idle time before considering it suspicious (minutes)
    'max_idle_minutes' => env('TRACKING_MAX_IDLE', 10),

    // Maximum time a timestamp can be in the future (seconds)
    'max_future_timestamp_offset' => env('TRACKING_MAX_FUTURE_OFFSET', 60),

    /*
    |--------------------------------------------------------------------------
    | Route Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Control whether and how long to store detailed route points.
    |
    */

    // Whether to store individual route points
    'store_route_points' => env('TRACKING_STORE_ROUTE', true),

    // How long to keep route points (days) - for dispute resolution
    'route_retention_days' => env('TRACKING_ROUTE_RETENTION', 90),

    // Only store route points for event types (saves storage)
    'store_events_only' => env('TRACKING_STORE_EVENTS_ONLY', false),

    /*
    |--------------------------------------------------------------------------
    | Real-time Notification Configuration
    |--------------------------------------------------------------------------
    |
    | Control how often to push location updates to riders.
    |
    */

    // Minimum interval between rider notifications (seconds)
    'notify_rider_interval' => env('TRACKING_NOTIFY_INTERVAL', 10),

    // Whether to use WebSocket for real-time updates
    'use_websocket' => env('TRACKING_USE_WEBSOCKET', true),

    // Whether to use push notifications (FCM/APNS)
    'use_push_notifications' => env('TRACKING_USE_PUSH', false),

    /*
    |--------------------------------------------------------------------------
    | Distance Calculation Configuration
    |--------------------------------------------------------------------------
    |
    | Method for calculating distances between GPS points.
    |
    */

    // Method: 'haversine' or 'vincenty' (haversine is faster, vincenty is more accurate)
    'distance_calculation_method' => env('TRACKING_DISTANCE_METHOD', 'haversine'),

    /*
    |--------------------------------------------------------------------------
    | Anomaly Handling
    |--------------------------------------------------------------------------
    |
    | Control how anomalies are handled.
    |
    */

    // Maximum number of anomalies before flagging for review
    'max_anomalies_before_flag' => env('TRACKING_MAX_ANOMALIES', 5),

    // Whether to reject updates with anomalies or just log them
    'reject_anomalous_updates' => env('TRACKING_REJECT_ANOMALIES', false),

    // Whether to notify admin of anomalies
    'notify_admin_on_anomaly' => env('TRACKING_NOTIFY_ADMIN_ANOMALY', false),
];
