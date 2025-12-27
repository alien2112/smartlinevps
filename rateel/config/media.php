<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CDN Domain
    |--------------------------------------------------------------------------
    |
    | The domain that serves media files via Cloudflare Worker.
    | Example: cdn.yoursite.com
    |
    */
    'cdn_domain' => env('MEDIA_CDN_DOMAIN', 'cdn.yoursite.com'),

    /*
    |--------------------------------------------------------------------------
    | Signing Secrets
    |--------------------------------------------------------------------------
    |
    | HMAC secrets for URL signing. Support multiple keys for rotation.
    | Secrets should be at least 32 characters.
    |
    */
    'signing_secrets' => [
        'v1' => env('MEDIA_SIGNING_SECRET_V1'),
        'v2' => env('MEDIA_SIGNING_SECRET_V2'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Current Key ID
    |--------------------------------------------------------------------------
    |
    | The key ID to use for signing new URLs. During rotation, the worker
    | will accept both old and new keys.
    |
    */
    'current_kid' => env('MEDIA_CURRENT_KID', 'v1'),

    /*
    |--------------------------------------------------------------------------
    | Default TTL
    |--------------------------------------------------------------------------
    |
    | Default URL expiration time in seconds.
    |
    */
    'default_ttl' => (int) env('MEDIA_DEFAULT_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | TTL by Category
    |--------------------------------------------------------------------------
    |
    | Category-specific TTL values in seconds.
    |
    */
    'ttl_by_category' => [
        'kyc' => 300,          // 5 minutes - sensitive identity documents
        'profile' => 900,       // 15 minutes - profile images
        'vehicle' => 600,       // 10 minutes - vehicle documents
        'document' => 600,      // 10 minutes - other documents
        'receipt' => 900,       // 15 minutes - trip receipts
        'evidence' => 300,      // 5 minutes - trip evidence
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed MIME Types by Category
    |--------------------------------------------------------------------------
    |
    | Restrict file types per category for security.
    |
    */
    'allowed_mime_types' => [
        'profile' => ['image/jpeg', 'image/png', 'image/webp'],
        'kyc' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
        'vehicle' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
        'document' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
        'receipt' => ['image/jpeg', 'image/png', 'application/pdf'],
        'evidence' => ['image/jpeg', 'image/png', 'image/webp', 'video/mp4'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Max File Sizes by Category (in bytes)
    |--------------------------------------------------------------------------
    |
    | Maximum allowed file sizes per category.
    |
    */
    'max_file_sizes' => [
        'profile' => 5 * 1024 * 1024,    // 5MB
        'kyc' => 10 * 1024 * 1024,       // 10MB
        'vehicle' => 10 * 1024 * 1024,   // 10MB
        'document' => 10 * 1024 * 1024,  // 10MB
        'receipt' => 5 * 1024 * 1024,    // 5MB
        'evidence' => 50 * 1024 * 1024,  // 50MB (for video)
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | The disk to use for secure media storage.
    | Use 'r2' for production, 'public' for local development.
    |
    */
    'disk' => env('MEDIA_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Max URL TTL
    |--------------------------------------------------------------------------
    |
    | Maximum allowed TTL for any signed URL (1 hour).
    | Worker should reject URLs with exp too far in the future.
    |
    */
    'max_ttl' => 3600,
];
