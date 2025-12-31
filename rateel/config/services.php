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
    | Rateel OTP Service
    |--------------------------------------------------------------------------
    |
    | External OTP verification service for customer signup.
    | API Documentation: https://documenter.getpostman.com/view/9924527/2sB2x6nsUP
    |
    */

    'rateel_otp' => [
        'enabled' => env('RATEEL_OTP_ENABLED', true),
        'base_url' => env('RATEEL_OTP_BASE_URL', 'https://otp.rateel.app/api'),
        'api_token' => env('RATEEL_OTP_API_TOKEN', 'p9ORLwInOCRgjK6BS8DFw5s8yiITUhtEQxAHmD4HudAv1mcgW7WhIvhtiz1I'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Node.js Realtime Service
    |--------------------------------------------------------------------------
    */

    'nodejs_realtime' => [
        'url' => env('NODEJS_REALTIME_URL', 'http://localhost:3000'),
    ],

];
