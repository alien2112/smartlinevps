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
    | - AI Chatbot: AI-powered customer support
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

    /*
    |--------------------------------------------------------------------------
    | AI Chatbot Service - Node.js Chatbot Server
    |--------------------------------------------------------------------------
    |
    | Configuration for the AI-powered chatbot service:
    | - Customer support automation
    | - Trip booking via chat
    | - Multi-language support (Arabic/English)
    |
    */
    'ai_chatbot' => [
        'url' => env('AI_CHATBOT_URL', 'http://localhost:3001'),
        'api_key' => env('AI_CHATBOT_API_KEY'),
        'timeout' => env('AI_CHATBOT_TIMEOUT', 30),
    ],

];
