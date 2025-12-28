<?php

use Illuminate\Support\Facades\Route;
use Modules\DispatchManagement\Http\Controllers\Web\HoneycombAdminController;

/*
|--------------------------------------------------------------------------
| Honeycomb Web Routes (Admin Panel)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'user_type:admin'])->prefix('admin/dispatch/honeycomb')->name('admin.dispatch.honeycomb.')->group(function () {
    
    // Settings page
    Route::get('/', [HoneycombAdminController::class, 'index'])->name('index');
    Route::post('/', [HoneycombAdminController::class, 'store'])->name('store');
    
    // Toggle via AJAX
    Route::post('/toggle', [HoneycombAdminController::class, 'toggle'])->name('toggle');
    
    // Heatmap visualization
    Route::get('/heatmap', [HoneycombAdminController::class, 'heatmap'])->name('heatmap');
    Route::get('/heatmap/data', [HoneycombAdminController::class, 'getHeatmapData'])->name('heatmap.data');
});
