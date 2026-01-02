<?php

use App\Http\Controllers\Api\AppConfigController;
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
| NOTE: All routes must use controller methods (no closures) to enable
| route caching with `php artisan route:cache` in production.
|
*/

// Auth routes (converted from closures for route caching)
Route::middleware('auth:sanctum')->get('/user', [AppConfigController::class, 'currentUser']);

// Version API (converted from closure for route caching)
Route::get('/version', [AppConfigController::class, 'version']);

// Internal settings API for Node.js realtime service (converted from closure for route caching)
Route::get('/internal/settings', [AppConfigController::class, 'internalSettings'])
    ->middleware('throttle:60,1');

// Issue #31 FIX: Health check endpoints for load balancer and monitoring
Route::get('/health', [AppConfigController::class, 'health']);
Route::get('/health/detailed', [AppConfigController::class, 'healthDetailed']);

// Driver App New Features (2026) - Notifications, Support, Account Management, etc.
require __DIR__.'/api_driver_new_features.php';
