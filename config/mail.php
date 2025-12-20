<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send any email
    | messages sent by your application. Alternative mailers may be setup
    | and used as needed; however, this mailer will be used by default.
    |
    | Supported: "mailgun", "log", "array", "failover"
    |
    */

    'default' => env('MAIL_MAILER', 'mailgun'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | SmartLine Mail Configuration
    | 
    | Supported Mailers:
    | - Mailgun: Free tier available, best for small projects
    | - Log: For development/testing (logs emails to file)
    | - Array: For unit testing (stores in memory)
    | - Failover: Automatic fallback (mailgun → log)
    |
    */

    'mailers' => [
        
        /*
        |----------------------------------------------------------------------
        | Mailgun - Primary Email Provider
        |----------------------------------------------------------------------
        | 
        | ✅ Free tier: 5,000 emails/month for 3 months
        | ✅ Good deliverability
        | ✅ Easy setup
        | 📌 Best choice for production
        |
        | Required ENV variables:
        | - MAILGUN_DOMAIN=your-domain.mailgun.org
        | - MAILGUN_SECRET=your-api-key
        | - MAILGUN_ENDPOINT=api.mailgun.net (or api.eu.mailgun.net for EU)
        |
        */
        'mailgun' => [
            'transport' => 'mailgun',
        ],

        /*
        |----------------------------------------------------------------------
        | Log - Development/Testing
        |----------------------------------------------------------------------
        | 
        | Logs all emails to storage/logs instead of sending
        | Perfect for development and debugging
        |
        */
        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL', 'stack'),
        ],

        /*
        |----------------------------------------------------------------------
        | Array - Unit Testing
        |----------------------------------------------------------------------
        | 
        | Stores emails in memory for testing assertions
        | Use Mail::fake() in tests
        |
        */
        'array' => [
            'transport' => 'array',
        ],

        /*
        |----------------------------------------------------------------------
        | Failover - Production Fallback
        |----------------------------------------------------------------------
        | 
        | Automatically tries the next mailer if one fails
        | mailgun → log (ensures emails are at least logged if sending fails)
        |
        */
        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'mailgun',
                'log',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all e-mails sent by your application to be sent from
    | the same address. Here, you may specify a name and address that is
    | used globally for all e-mails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@smartline.com'),
        'name' => env('MAIL_FROM_NAME', 'SmartLine'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Markdown Mail Settings
    |--------------------------------------------------------------------------
    */

    'markdown' => [
        'theme' => 'default',

        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],

];
