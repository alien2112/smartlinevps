<?php

/**
 * Driver Onboarding Configuration
 *
 * This configuration file controls all aspects of the driver onboarding process
 * including rate limiting, OTP settings, document requirements, and session management.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | OTP Settings
    |--------------------------------------------------------------------------
    */
    'otp' => [
        // OTP validity duration in minutes
        'ttl_minutes' => env('DRIVER_OTP_TTL_MINUTES', 5),

        // OTP length
        'length' => env('DRIVER_OTP_LENGTH', 4),

        // Maximum verification attempts before lockout
        'max_verify_attempts' => env('DRIVER_OTP_MAX_ATTEMPTS', 5),

        // Lockout duration after max attempts (minutes)
        'verify_lockout_minutes' => env('DRIVER_OTP_LOCKOUT_MINUTES', 30),

        // Cooldown between resends (seconds) - 5 minutes = 300 seconds
        'resend_cooldown_seconds' => env('DRIVER_OTP_RESEND_COOLDOWN', 300),

        // Maximum resends per session
        'max_resends_per_session' => env('DRIVER_OTP_MAX_RESENDS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting - Per Phone Number
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'phone' => [
            // Maximum OTP sends per phone per hour
            'max_per_hour' => env('DRIVER_RATE_PHONE_HOURLY', 5),

            // Maximum OTP sends per phone per day
            'max_per_day' => env('DRIVER_RATE_PHONE_DAILY', 10),

            // Lockout duration when limit exceeded (minutes)
            'lockout_minutes' => env('DRIVER_RATE_PHONE_LOCKOUT', 60),
        ],

        'ip' => [
            // Maximum OTP sends per IP per hour
            'max_per_hour' => env('DRIVER_RATE_IP_HOURLY', 20),

            // Maximum OTP sends per IP per day
            'max_per_day' => env('DRIVER_RATE_IP_DAILY', 50),
        ],

        'device' => [
            // Maximum OTP sends per device per hour
            'max_per_hour' => env('DRIVER_RATE_DEVICE_HOURLY', 10),
        ],

        'global' => [
            // Maximum total OTP sends per minute (DoS protection)
            'max_per_minute' => env('DRIVER_RATE_GLOBAL_MINUTE', 100),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Settings
    |--------------------------------------------------------------------------
    */
    'session' => [
        // Onboarding session TTL (hours) - how long before session expires
        'ttl_hours' => env('DRIVER_SESSION_TTL_HOURS', 24),

        // Onboarding token TTL (hours) - how long the JWT/token is valid
        'token_ttl_hours' => env('DRIVER_TOKEN_TTL_HOURS', 48),

        // Driver token TTL (days) - for approved drivers
        'driver_token_ttl_days' => env('DRIVER_FULL_TOKEN_TTL_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Document Settings
    |--------------------------------------------------------------------------
    */
    'documents' => [
        // Required document types for driver approval
        'required' => [
            'national_id' => [
                'label' => 'National ID (Front & Back)',
                'max_size_mb' => 5,
                'allowed_mimes' => ['image/jpeg', 'image/png', 'application/pdf'],
                'required' => true,
            ],
            'driving_license' => [
                'label' => 'Driving License',
                'max_size_mb' => 5,
                'allowed_mimes' => ['image/jpeg', 'image/png', 'application/pdf'],
                'required' => true,
            ],
            'vehicle_registration' => [
                'label' => 'Vehicle Registration',
                'max_size_mb' => 5,
                'allowed_mimes' => ['image/jpeg', 'image/png', 'application/pdf'],
                'required' => true,
            ],
            'vehicle_photo' => [
                'label' => 'Vehicle Photo',
                'max_size_mb' => 10,
                'allowed_mimes' => ['image/jpeg', 'image/png'],
                'required' => true,
            ],
            'profile_photo' => [
                'label' => 'Profile Photo',
                'max_size_mb' => 5,
                'allowed_mimes' => ['image/jpeg', 'image/png'],
                'required' => true,
            ],
            'criminal_record' => [
                'label' => 'Criminal Record Certificate',
                'max_size_mb' => 5,
                'allowed_mimes' => ['image/jpeg', 'image/png', 'application/pdf'],
                'required' => false, // Optional based on region
            ],
        ],

        // Maximum uploads per document type (for re-uploads after rejection)
        'max_uploads_per_type' => env('DRIVER_DOC_MAX_UPLOADS', 5),

        // Storage disk
        'storage_disk' => env('DRIVER_DOC_DISK', 'public'),

        // Storage path
        'storage_path' => 'driver-documents',
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Requirements
    |--------------------------------------------------------------------------
    */
    'password' => [
        'min_length' => 8,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_number' => true,
        'require_special' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Profile Requirements
    |--------------------------------------------------------------------------
    */
    'profile' => [
        'required_fields' => [
            'first_name',
            'last_name',
            'national_id',
            'city_id',
            'date_of_birth',
        ],
        'optional_fields' => [
            'email',
            'gender',
            'first_name_ar',
            'last_name_ar',
        ],
        'min_age' => 21,
        'max_age' => 65,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        // Send SMS on approval
        'sms_on_approval' => env('DRIVER_NOTIFY_SMS_APPROVAL', true),

        // Send email on approval
        'email_on_approval' => env('DRIVER_NOTIFY_EMAIL_APPROVAL', true),

        // Send push on status change
        'push_on_status_change' => env('DRIVER_NOTIFY_PUSH_STATUS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Deprecation Settings
    |--------------------------------------------------------------------------
    */
    'deprecation' => [
        // Date when v1 endpoints will be removed (ISO 8601)
        'v1_sunset_date' => env('DRIVER_V1_SUNSET_DATE', '2026-04-01'),

        // Whether to log deprecated endpoint usage
        'log_deprecated_usage' => env('DRIVER_LOG_DEPRECATED', true),
    ],

];
