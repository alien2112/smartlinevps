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
Route::middleware('auth:sanctum')->get('/user', [AppConfigController::class, 'user']);

// Version API (converted from closure for route caching)
Route::get('/version', [AppConfigController::class, 'version']);

// Internal settings API for Node.js realtime service (converted from closure for route caching)
Route::get('/internal/settings', [AppConfigController::class, 'internalSettings'])
    ->middleware('throttle:60,1');
