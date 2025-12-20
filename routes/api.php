<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Version API Routes
|--------------------------------------------------------------------------
|
| Returns the current API version from the database
|
*/
Route::get('/version', [\App\Http\Controllers\Api\VersionController::class, 'index']);
Route::get('/v', [\App\Http\Controllers\Api\VersionController::class, 'getVersion']);

/*
|--------------------------------------------------------------------------
| Health Check & Monitoring Routes
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    try {
        $status = [
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'service' => 'SmartLine API',
            'version' => config('app.version', '1.0.0'),
        ];

        // Check database connection
        try {
            \DB::connection()->getPdo();
            $status['database'] = 'connected';
        } catch (\Exception $e) {
            $status['database'] = 'disconnected';
            $status['status'] = 'unhealthy';
        }

        // Check Redis connection
        try {
            \Illuminate\Support\Facades\Redis::ping();
            $status['redis'] = 'connected';
        } catch (\Exception $e) {
            $status['redis'] = 'disconnected';
            $status['status'] = 'degraded';
        }

        // Check storage is writable
        try {
            \Storage::disk('public')->put('health-check.txt', 'OK');
            \Storage::disk('public')->delete('health-check.txt');
            $status['storage'] = 'writable';
        } catch (\Exception $e) {
            $status['storage'] = 'read-only';
            $status['status'] = 'degraded';
        }

        $httpStatus = $status['status'] === 'healthy' ? 200 : 503;

        return response()->json($status, $httpStatus);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'unhealthy',
            'error' => 'Health check failed',
            'timestamp' => now()->toIso8601String(),
        ], 503);
    }
});

Route::get('/health/detailed', function () {
    try {
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'uptime' => null, // Can be calculated from deployment time
            'checks' => [],
        ];

        // Database check with query time
        $dbStart = microtime(true);
        try {
            \DB::select('SELECT 1');
            $dbTime = round((microtime(true) - $dbStart) * 1000, 2);
            $health['checks']['database'] = [
                'status' => 'up',
                'response_time_ms' => $dbTime,
            ];
        } catch (\Exception $e) {
            $health['checks']['database'] = [
                'status' => 'down',
                'error' => $e->getMessage(),
            ];
            $health['status'] = 'unhealthy';
        }

        // Redis check
        try {
            $redisStart = microtime(true);
            \Illuminate\Support\Facades\Redis::ping();
            $redisTime = round((microtime(true) - $redisStart) * 1000, 2);
            $health['checks']['redis'] = [
                'status' => 'up',
                'response_time_ms' => $redisTime,
            ];
        } catch (\Exception $e) {
            $health['checks']['redis'] = [
                'status' => 'down',
                'error' => $e->getMessage(),
            ];
        }

        // Storage check
        try {
            \Storage::disk('public')->put('health-check.txt', 'OK');
            \Storage::disk('public')->delete('health-check.txt');
            $health['checks']['storage'] = ['status' => 'up'];
        } catch (\Exception $e) {
            $health['checks']['storage'] = [
                'status' => 'down',
                'error' => $e->getMessage(),
            ];
        }

        // Queue check
        try {
            $queueConnection = config('queue.default');
            $health['checks']['queue'] = [
                'status' => 'configured',
                'driver' => $queueConnection,
            ];
        } catch (\Exception $e) {
            $health['checks']['queue'] = [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }

        // System info
        $health['system'] = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => app()->environment(),
        ];

        $httpStatus = $health['status'] === 'healthy' ? 200 : 503;

        return response()->json($health, $httpStatus);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'unhealthy',
            'error' => 'Detailed health check failed',
            'timestamp' => now()->toIso8601String(),
        ], 503);
    }
});

/*
|--------------------------------------------------------------------------
| Internal API Routes (Node.js Real-time Service)
|--------------------------------------------------------------------------
|
| These routes are for internal communication between Laravel and Node.js
| They are protected by API key authentication (X-API-Key header)
| NOT for public/frontend use
|
*/
Route::prefix('internal')->group(function () {
    /**
     * Node.js calls this when a driver accepts a ride
     * Assigns driver to ride with database locking
     */
    Route::post('ride/assign-driver', [
        \App\Http\Controllers\Api\Internal\RealtimeController::class,
        'assignDriver'
    ]);

    /**
     * Node.js sends events back to Laravel
     * Events: ride.no_drivers, ride.timeout, driver.disconnected
     */
    Route::post('events/{event}', [
        \App\Http\Controllers\Api\Internal\RealtimeController::class,
        'handleEvent'
    ]);

    /**
     * Health check for Node.js to verify Laravel is reachable
     */
    Route::get('health', [
        \App\Http\Controllers\Api\Internal\RealtimeController::class,
        'health'
    ]);
});

/*
|--------------------------------------------------------------------------
| Location Configuration Routes (Tunable Tracking)
|--------------------------------------------------------------------------
|
| Real-time location tracking configuration management
| Allows switching between presets without app deployment
| Used by driver apps to fetch current tracking parameters
|
*/
Route::prefix('config/location')->group(function () {
    /**
     * Get current active configuration
     * Public endpoint - no auth required for driver apps
     */
    Route::get('/', [
        \App\Http\Controllers\Api\LocationConfigController::class,
        'getConfig'
    ]);

    /**
     * Get all available presets
     */
    Route::get('/presets', [
        \App\Http\Controllers\Api\LocationConfigController::class,
        'getPresets'
    ]);

    /**
     * Admin-only endpoints for configuration management
     */
    Route::middleware('auth:sanctum')->group(function () {
        /**
         * Switch to a different preset
         */
        Route::post('/preset/{preset}', [
            \App\Http\Controllers\Api\LocationConfigController::class,
            'setPreset'
        ]);

        /**
         * Save custom configuration
         */
        Route::post('/custom', [
            \App\Http\Controllers\Api\LocationConfigController::class,
            'saveCustom'
        ]);

        /**
         * Get configuration statistics
         */
        Route::get('/stats', [
            \App\Http\Controllers\Api\LocationConfigController::class,
            'getStats'
        ]);

        /**
         * Reset to default configuration
         */
        Route::post('/reset', [
            \App\Http\Controllers\Api\LocationConfigController::class,
            'reset'
        ]);
    });
});
