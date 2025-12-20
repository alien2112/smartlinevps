<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Image Upload Configuration
    |--------------------------------------------------------------------------
    |
    | SmartLine image upload settings. All image uploads across the application
    | will use these settings for validation and processing.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Maximum File Size (in kilobytes)
    |--------------------------------------------------------------------------
    |
    | Maximum allowed file size for image uploads in KB.
    | 500 = 500KB, 1024 = 1MB, 5120 = 5MB
    |
    */
    'max_size' => env('IMAGE_MAX_SIZE', 500), // 500KB

    /*
    |--------------------------------------------------------------------------
    | Allowed MIME Types
    |--------------------------------------------------------------------------
    |
    | List of allowed image file extensions for upload.
    |
    */
    'allowed_mimes' => ['jpeg', 'jpg', 'png', 'gif', 'webp'],

    /*
    |--------------------------------------------------------------------------
    | PNG Only MIME Types (for icons/logos)
    |--------------------------------------------------------------------------
    |
    | Some uploads (like category icons, brand logos) require PNG only.
    |
    */
    'png_only_mimes' => ['png'],

    /*
    |--------------------------------------------------------------------------
    | Profile Image Settings
    |--------------------------------------------------------------------------
    */
    'profile' => [
        'max_size' => env('PROFILE_IMAGE_MAX_SIZE', 500), // 500KB
        'mimes' => ['jpeg', 'jpg', 'png', 'gif', 'webp'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Identity Document Settings
    |--------------------------------------------------------------------------
    */
    'identity' => [
        'max_size' => env('IDENTITY_IMAGE_MAX_SIZE', 500), // 500KB
        'mimes' => ['jpeg', 'jpg', 'png', 'webp', 'pdf'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Banner/Promotional Image Settings
    |--------------------------------------------------------------------------
    */
    'banner' => [
        'max_size' => env('BANNER_IMAGE_MAX_SIZE', 500), // 500KB
        'mimes' => ['jpeg', 'jpg', 'png', 'webp'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Vehicle Documents Settings
    |--------------------------------------------------------------------------
    */
    'vehicle_documents' => [
        'max_size' => env('VEHICLE_DOC_MAX_SIZE', 500), // 500KB
        'mimes' => ['jpeg', 'jpg', 'png', 'pdf', 'webp'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Icon/Logo Settings (PNG only)
    |--------------------------------------------------------------------------
    */
    'icon' => [
        'max_size' => env('ICON_IMAGE_MAX_SIZE', 500), // 500KB
        'mimes' => ['png'],
    ],
];
