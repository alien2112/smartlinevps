<?php

return [
    'name' => 'CouponManagement',

    /*
    |--------------------------------------------------------------------------
    | Coupon Reservation Settings
    |--------------------------------------------------------------------------
    */
    'reservation' => [
        // How long a coupon reservation is valid (in minutes)
        'expiry_minutes' => env('COUPON_RESERVATION_EXPIRY', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        // Maximum tokens per FCM request
        'max_tokens_per_request' => 500,

        // Chunk size for bulk operations
        'bulk_chunk_size' => 100,

        // Default notification channel
        'default_channel' => 'coupons',
    ],
];
