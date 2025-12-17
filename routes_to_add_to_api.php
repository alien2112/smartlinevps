<?php

/**
 * INTERNAL API ROUTES FOR NODE.JS REAL-TIME SERVICE
 *
 * Add these routes to routes/api.php
 *
 * These routes allow the Node.js service to:
 * 1. Assign drivers to rides (with database locking)
 * 2. Send events back to Laravel (no drivers, timeout, etc.)
 * 3. Health check Laravel API availability
 */

// Internal API routes (Node.js callbacks)
// These are protected by API key authentication in the controller
Route::prefix('internal')->group(function () {

    /**
     * POST /api/internal/ride/assign-driver
     *
     * Node.js calls this when a driver accepts a ride
     * This performs the database update with proper locking
     *
     * Request body:
     * {
     *   "ride_id": "uuid",
     *   "driver_id": "uuid"
     * }
     *
     * Response:
     * {
     *   "success": true,
     *   "ride": {...},
     *   "driver": {...},
     *   "estimated_arrival": 5
     * }
     */
    Route::post('ride/assign-driver', [
        \App\Http\Controllers\Api\Internal\RealtimeController::class,
        'assignDriver'
    ]);

    /**
     * POST /api/internal/events/{event}
     *
     * Node.js sends events back to Laravel
     *
     * Supported events:
     * - ride.no_drivers - No drivers available for ride
     * - ride.timeout - No driver accepted within timeout
     * - driver.disconnected - Driver lost connection during ride
     *
     * Request body:
     * {
     *   "ride_id": "uuid",
     *   "driver_id": "uuid" (optional, depends on event)
     * }
     */
    Route::post('events/{event}', [
        \App\Http\Controllers\Api\Internal\RealtimeController::class,
        'handleEvent'
    ]);

    /**
     * GET /api/internal/health
     *
     * Health check endpoint for Node.js to verify Laravel API is reachable
     *
     * Response:
     * {
     *   "status": "ok",
     *   "service": "laravel-api",
     *   "timestamp": "2025-12-17T..."
     * }
     */
    Route::get('health', [
        \App\Http\Controllers\Api\Internal\RealtimeController::class,
        'health'
    ]);
});

/**
 * SECURITY NOTES:
 *
 * 1. These routes are protected by API key authentication in the controller
 * 2. The API key is configured in .env as NODEJS_REALTIME_API_KEY
 * 3. Node.js must send the key in X-API-Key header
 * 4. These routes should NOT be accessible from public internet
 * 5. Use firewall rules to restrict access to Node.js server IP only
 *
 * Example nginx configuration:
 *
 * location /api/internal {
 *     allow 127.0.0.1;           # Localhost
 *     allow 10.0.0.0/8;          # Private network
 *     deny all;                   # Deny everyone else
 *     proxy_pass http://laravel;
 * }
 */
