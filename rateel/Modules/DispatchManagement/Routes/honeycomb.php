<?php

use Illuminate\Support\Facades\Route;
use Modules\DispatchManagement\Http\Controllers\Api\Admin\HoneycombController as AdminHoneycombApiController;
use Modules\DispatchManagement\Http\Controllers\Api\Driver\HoneycombController as DriverHoneycombApiController;
use Modules\DispatchManagement\Http\Controllers\Web\HoneycombAdminController;

/*
|--------------------------------------------------------------------------
| Honeycomb API Routes
|--------------------------------------------------------------------------
|
| Hexagonal grid-based dispatch system routes.
| Provides admin management and driver-facing hotspot/heatmap APIs.
|
*/

// ============================================================
// ADMIN API ROUTES - Honeycomb Management
// ============================================================
Route::middleware(['auth:api', 'user_type:admin'])->prefix('admin/dispatch/honeycomb')->group(function () {
    
    // Settings management
    Route::get('/settings', [AdminHoneycombApiController::class, 'getSettings']);
    Route::put('/settings', [AdminHoneycombApiController::class, 'updateSettings']);
    
    // Quick toggles
    Route::post('/toggle', [AdminHoneycombApiController::class, 'toggle']);
    
    // Heatmap & analytics
    Route::get('/heatmap', [AdminHoneycombApiController::class, 'getHeatmap']);
    Route::get('/analytics', [AdminHoneycombApiController::class, 'getAnalytics']);
    
    // Zone listing
    Route::get('/zones', [AdminHoneycombApiController::class, 'listZones']);
    
    // Preview/testing
    Route::get('/preview', [AdminHoneycombApiController::class, 'preview']);
});


// ============================================================
// DRIVER API ROUTES - Hotspots & Cell Stats
// ============================================================
Route::middleware(['auth:api', 'user_type:driver'])->prefix('driver/honeycomb')->group(function () {
    
    // Hotspots (high-demand areas)
    Route::get('/hotspots', [DriverHoneycombApiController::class, 'getHotspots']);
    
    // Current cell stats
    Route::get('/cell', [DriverHoneycombApiController::class, 'getCellStats']);
    
    // Heatmap visualization (simplified for mobile)
    Route::get('/heatmap', [DriverHoneycombApiController::class, 'getHeatmap']);
    
    // Surge check
    Route::get('/surge', [DriverHoneycombApiController::class, 'getSurge']);
});
