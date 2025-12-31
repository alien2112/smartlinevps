<?php

return [

    /*
     |--------------------------------------------------------------------------
     | Debugbar Settings
     |--------------------------------------------------------------------------
     |
     | Debugbar is enabled by default, when debug is enabled in app.php.
     | You can override the value by setting enable to true or false instead of null.
     |
     */

    'enabled' => env('DEBUGBAR_ENABLED', false),

    /*
     |--------------------------------------------------------------------------
     | Storage settings
     |--------------------------------------------------------------------------
     |
     | The following options configure the storage for debugbar.
     |
     */

    'storage' => [
        'enabled'    => true,
        'driver'     => 'file', // redis, file, pdo, custom
        'path'       => storage_path('debugbar'), // For file driver
        'connection' => null,   // Leave null for default connection (Redis/PDO)
    ],

    /*
     |--------------------------------------------------------------------------
     | Vendors
     |--------------------------------------------------------------------------
     |
     | Vendor files are included by default, but can be set to false.
     | This can also be set per vendor. (default: true)
     |
     */

    'include_vendors' => true,

    /*
     |--------------------------------------------------------------------------
     | Capture Ajax Requests
     |--------------------------------------------------------------------------
     |
     | The Debugbar can capture Ajax requests and display them.
     | You might want to disable this, if it slows down your application.
     |
     */

    'capture_ajax' => false,
    'add_ajax_timing' => false,

    /*
     |--------------------------------------------------------------------------
     | Error Handler
     |--------------------------------------------------------------------------
     |
     | When enabled, the Debugbar shows deprecated warnings for Symfony components
     | in the Messages tab.
     |
     */

    'error_handler' => false,

    /*
     |--------------------------------------------------------------------------
     | Clockwork integration
     |--------------------------------------------------------------------------
     |
     | The Debugbar can emulate the Clockwork browser, so you can use either
     | Chrome extension, or the Debugbar.
     |
     */

    'clockwork' => false,

    /*
     |--------------------------------------------------------------------------
     | DataCollectors
     |--------------------------------------------------------------------------
     |
     | Enable/disable DataCollectors
     |
     */

    'collectors' => [
        'phpinfo'         => true,
        'messages'        => true,
        'time'            => true,
        'memory'          => true,
        'exceptions'      => true,
        'log'             => true,
        'db'              => true,
        'views'           => true,
        'route'           => true,
        'auth'            => false,
        'gate'            => true,
        'session'        => true,
        'symfony_request' => true,
        'mail'            => true,
        'laravel'         => false,
        'events'          => false,
        'default_request' => false,
        'logs'            => false,
        'files'           => false,
        'config'          => false,
        'cache'           => false,
        'models'          => true,
        'livewire'        => false,
    ],

    /*
     |--------------------------------------------------------------------------
     | Options
     |--------------------------------------------------------------------------
     |
     | Configure some DataCollectors
     |
     */

    'options' => [
        'auth' => [
            'show_name' => true,
        ],
        'db' => [
            'with_params'       => true,
            'backtrace'         => true,
            'backtrace_exclude_paths' => [],
            'timeline'          => false,
            'explain'            => [
                'enabled' => false,
                'types'   => ['SELECT'],
            ],
            'hints'             => false,
            'show_copy'         => false,
        ],
        'mail' => [
            'full_log' => false,
        ],
        'views' => [
            'timeline' => false,
            'data' => false,
        ],
        'route' => [
            'label' => true,
        ],
        'logs' => [
            'file' => null,
        ],
        'cache' => [
            'values' => true,
        ],
    ],

    /*
     |--------------------------------------------------------------------------
     | Inject Debugbar in Response
     |--------------------------------------------------------------------------
     |
     | Usually, the debugbar is injected just before </body>, by listening to the
     | Response after the App is done. If you disable this, you have to add them
     | in your template yourself. See http://phpdebugbar.com/docs/rendering.html
     |
     */

    'inject' => true,

    /*
     |--------------------------------------------------------------------------
     | Debugbar Route Prefix
     |--------------------------------------------------------------------------
     |
     | Sometimes you want to set route prefix to be used by debugbar to load
     | its resources. Usually the need comes from misconfigured web server or
     | from trying to overcome bugs like this: http://trac.nginx.org/nginx/ticket/97
     |
     */

    'route_prefix' => '_debugbar',

    /*
     |--------------------------------------------------------------------------
     | Debugbar route domain
     |--------------------------------------------------------------------------
     |
     | By default Debugbar route served from the same domain that request served.
     * To override default domain, specify it as a non-empty value.
     */

    'route_domain' => null,

    /*
     |--------------------------------------------------------------------------
     | Debugbar theme
     |--------------------------------------------------------------------------
     |
     | Switches between light and dark theme. If set to auto it will respect system
     | preferences
     | Supported values: auto, light, dark
     */

    'theme' => env('DEBUGBAR_THEME', 'auto'),

    /*
     |--------------------------------------------------------------------------
     | Backtrace stack limit
     |--------------------------------------------------------------------------
     |
     | By default, the Debugbar limits the number of frames returned by the
     * debug_backtrace() function. If you need larger stack traces, you can
     * increase this number. Setting it to 0 will return all stack frames.
     */

    'debug_backtrace_limit' => 50,
];

