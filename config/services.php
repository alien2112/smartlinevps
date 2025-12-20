<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | SmartLine External Services Configuration
    | 
    | Supported Services:
    | - Mailgun: Email delivery (primary)
    | - Realtime: Node.js WebSocket service
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Mailgun - Email Service
    |--------------------------------------------------------------------------
    |
    | ✅ Free tier: 5,000 emails/month for 3 months
    | ✅ Good deliverability
    | ✅ Easy setup
    |
    | Sign up at: https://www.mailgun.com/
    |
    */
    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        // For EU region, use: 'api.eu.mailgun.net'
    ],

    /*
    |--------------------------------------------------------------------------
    | Realtime Service - Node.js WebSocket Server
    |--------------------------------------------------------------------------
    |
    | Configuration for the Node.js realtime service that handles:
    | - Live trip tracking
    | - Driver/rider notifications
    | - Real-time location updates
    |
    */
    'realtime' => [
        'url' => env('NODEJS_REALTIME_URL', 'http://localhost:3000'),
        'api_key' => env('NODEJS_REALTIME_API_KEY'),
    ],

];
