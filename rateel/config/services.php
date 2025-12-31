<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | BeOn OTP Service (SMS Gateway)
    |--------------------------------------------------------------------------
    |
    | BeOn V3 API for OTP/SMS messaging.
    | API Documentation: https://documenter.getpostman.com/view/9924527/2sB2x6nsUP
    |
    */

    'beon_otp' => [
        'enabled' => env('BEON_OTP_ENABLED', true),
        'base_url' => env('BEON_OTP_BASE_URL', 'https://v3.api.beon.chat/api/v3'),
        'api_token' => env('BEON_OTP_TOKEN', 'p9ORLwInOCRgjK6BS8DFw5s8yiITUhtEQxAHmD4HudAv1mcgW7WhIvhtiz1I'),
        'otp_length' => env('BEON_OTP_LENGTH', 6),
        'lang' => env('BEON_OTP_LANG', 'ar'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Node.js Realtime Service
    |--------------------------------------------------------------------------
    */

    'nodejs_realtime' => [
        'url' => env('NODEJS_REALTIME_URL', 'http://localhost:3000'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging (FCM)
    |--------------------------------------------------------------------------
    |
    | Configuration for Firebase Cloud Messaging HTTP v1 API.
    | You can provide either a path to the service account JSON file,
    | or individual credentials via environment variables.
    |
    */

    'firebase' => [
        // Option 1: Path to service account JSON file
        'credentials_path' => env('FIREBASE_CREDENTIALS_PATH'),

        // Option 2: Individual credentials (if not using JSON file)
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'private_key_id' => env('FIREBASE_PRIVATE_KEY_ID'),
        'private_key' => env('FIREBASE_PRIVATE_KEY'),
        'client_email' => env('FIREBASE_CLIENT_EMAIL'),
        'client_id' => env('FIREBASE_CLIENT_ID'),
    ],

];
