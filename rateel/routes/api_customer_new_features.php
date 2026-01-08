<?php

use Illuminate\Support\Facades\Route;
use Modules\UserManagement\Http\Controllers\Api\New\Customer\SupportController;

/*
|--------------------------------------------------------------------------
| Customer API Routes - New Features (2026)
|--------------------------------------------------------------------------
|
| Customer support and additional features
|
*/

Route::group(['prefix' => 'customer/auth', 'middleware' => ['auth:api']], function () {

    // ============================================
    // SUPPORT & HELP
    // ============================================
    Route::controller(SupportController::class)->prefix('support')->group(function () {
        // App Info
        Route::get('/app-info', 'appInfo'); // Get app version info
    });

});
