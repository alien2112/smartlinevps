<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Verification Thresholds
    |--------------------------------------------------------------------------
    |
    | These thresholds determine automatic decision making for KYC verification.
    | Scores are 0-100. Adjust based on your risk tolerance.
    |
    */
    'thresholds' => [
        'auto_approve' => [
            'face_match' => 85.0,      // Minimum face match score for auto-approval
            'doc_auth' => 75.0,        // Minimum document authenticity score
            'liveness_quality' => true, // Must pass liveness quality checks
        ],
        'manual_review' => [
            'face_match' => 60.0,      // Below auto, above this goes to review
            'doc_auth' => 50.0,        // Below auto, above this goes to review
        ],
        // Below manual_review thresholds → auto reject
    ],

    /*
    |--------------------------------------------------------------------------
    | FastAPI Verification Service
    |--------------------------------------------------------------------------
    |
    | Configuration for the Python FastAPI microservice that handles
    | ID OCR, face matching, and image quality checks.
    |
    */
    'fastapi' => [
        'base_url' => env('FASTAPI_VERIFICATION_URL', 'http://localhost:8100'),
        'api_key' => env('FASTAPI_VERIFICATION_KEY', ''),
        'timeout' => 120, // seconds
        'retry_times' => 3,
        'retry_sleep' => 1000, // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Where verification media (selfies, IDs, videos) are stored.
    | Use 'local' for MVP, switch to 's3' or 'r2' for production.
    |
    */
    'storage' => [
        'disk' => env('VERIFICATION_STORAGE_DISK', 'local'),
        'path_prefix' => 'verification',
        'signed_url_expiry' => 15, // minutes
        'max_file_size' => 10 * 1024 * 1024, // 10MB
        'allowed_image_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        'allowed_video_mimes' => ['video/mp4', 'video/webm', 'video/quicktime'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Configuration
    |--------------------------------------------------------------------------
    */
    'session' => [
        'expiry_minutes' => 30, // Session expires after this time if not submitted
        'max_active_sessions' => 1, // Max concurrent sessions per user
    ],

    /*
    |--------------------------------------------------------------------------
    | Required Media
    |--------------------------------------------------------------------------
    |
    | Minimum required media for a verification session to be submitted.
    |
    */
    'required_media' => [
        'driver_kyc' => ['selfie', 'id_front'], // Minimum for drivers
        'customer_kyc' => ['selfie'], // Minimum for customers (future)
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'notify_on_verified' => true,
        'notify_on_rejected' => true,
        'notify_on_manual_review' => false,
    ],
];
