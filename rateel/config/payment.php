<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Configuration
    |--------------------------------------------------------------------------
    */

    'default_gateway' => env('PAYMENT_GATEWAY', 'kashier'),

    'gateways' => [
        'kashier' => [
            'base_url' => env('KASHIER_BASE_URL', 'https://api.kashier.io'), // Updated to correct API endpoint
            'merchant_id' => env('KASHIER_MERCHANT_ID', 'MID-36316-436'),
            'api_key' => env('KASHIER_API_KEY', 'd5d3dd58-50b2-4203-b397-3f83b3a93f24'),
            'secret_key' => env('KASHIER_SECRET_KEY'),
            'currency' => env('KASHIER_CURRENCY', 'EGP'),
            'timeout' => env('KASHIER_TIMEOUT', 30), // seconds
            'mode' => env('KASHIER_MODE', 'live'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeout Configuration
    |--------------------------------------------------------------------------
    */

    'timeout' => [
        'gateway_request' => env('PAYMENT_GATEWAY_TIMEOUT', 30), // seconds
        'max_processing_time' => env('PAYMENT_MAX_PROCESSING_TIME', 300), // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Reconciliation Configuration
    |--------------------------------------------------------------------------
    */

    'reconciliation' => [
        'enabled' => env('PAYMENT_RECONCILIATION_ENABLED', true),
        'max_attempts' => env('PAYMENT_RECONCILIATION_MAX_ATTEMPTS', 10),
        'backoff_strategy' => 'exponential', // linear, exponential
        'initial_delay' => 60, // seconds - first retry after 1 minute
        'max_delay' => 3600, // seconds - max 1 hour between retries
        'batch_size' => env('PAYMENT_RECONCILIATION_BATCH_SIZE', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    */

    'retry' => [
        'max_attempts' => env('PAYMENT_MAX_RETRY_ATTEMPTS', 3),
        'delay' => env('PAYMENT_RETRY_DELAY', 5), // seconds
        'multiplier' => 2, // Exponential backoff multiplier
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency Configuration
    |--------------------------------------------------------------------------
    */

    'idempotency' => [
        'enabled' => true,
        'ttl' => 86400, // 24 hours - how long to keep idempotency keys
    ],

    /*
    |--------------------------------------------------------------------------
    | Locking Configuration
    |--------------------------------------------------------------------------
    */

    'locking' => [
        'driver' => env('PAYMENT_LOCK_DRIVER', 'redis'), // redis, database
        'timeout' => env('PAYMENT_LOCK_TIMEOUT', 10), // seconds
        'retry_interval' => 100, // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | State Machine Configuration
    |--------------------------------------------------------------------------
    */

    'state_machine' => [
        'allowed_transitions' => [
            'created' => ['pending_gateway', 'cancelled'],
            'pending_gateway' => ['processing', 'paid', 'failed', 'unknown', 'cancelled'],
            'processing' => ['paid', 'failed', 'unknown'],
            'paid' => ['refunded'],
            'failed' => [],
            'unknown' => ['paid', 'failed', 'processing'],
            'refunded' => [],
            'cancelled' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */

    'webhook' => [
        'verify_signature' => env('PAYMENT_WEBHOOK_VERIFY_SIGNATURE', true),
        'tolerance' => 300, // seconds - timestamp tolerance
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring & Alerting
    |--------------------------------------------------------------------------
    */

    'monitoring' => [
        'alert_on_unknown_status' => true,
        'alert_threshold' => 5, // Alert after 5 unknown transactions
        'slack_webhook' => env('PAYMENT_SLACK_WEBHOOK'),
    ],
];
