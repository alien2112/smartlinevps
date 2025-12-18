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
