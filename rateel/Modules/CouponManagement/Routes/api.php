<?php

use Illuminate\Support\Facades\Route;
use Modules\CouponManagement\Http\Controllers\Api\Admin\CouponAdminController;
use Modules\CouponManagement\Http\Controllers\Api\Customer\CouponController;
use Modules\CouponManagement\Http\Controllers\Api\Customer\DeviceController;

/*
|--------------------------------------------------------------------------
| Coupon Management API Routes
|--------------------------------------------------------------------------
|
| Customer Routes (require auth:api middleware)
| Admin Routes (require auth:api + admin middleware)
|
*/

// Customer Routes
Route::prefix('api/v1')->middleware(['auth:api'])->group(function () {
    // Coupon validation and listing
    Route::prefix('coupons')->group(function () {
        Route::post('/validate', [CouponController::class, 'validate']);
        Route::get('/available', [CouponController::class, 'available']);
    });

    // Device token management
    Route::prefix('devices')->group(function () {
        Route::post('/register', [DeviceController::class, 'register']);
        Route::post('/unregister', [DeviceController::class, 'unregister']);
        Route::get('/', [DeviceController::class, 'list']);
    });
});

// Admin Routes
Route::prefix('admin/coupons')->middleware(['auth:api', 'admin'])->group(function () {
    Route::get('/', [CouponAdminController::class, 'index']);
    Route::post('/', [CouponAdminController::class, 'store']);
    Route::get('/{coupon}', [CouponAdminController::class, 'show']);
    Route::put('/{coupon}', [CouponAdminController::class, 'update']);
    Route::delete('/{coupon}', [CouponAdminController::class, 'destroy']);

    Route::post('/{coupon}/assign-users', [CouponAdminController::class, 'assignUsers']);
    Route::post('/{coupon}/broadcast', [CouponAdminController::class, 'broadcast']);
    Route::post('/{coupon}/deactivate', [CouponAdminController::class, 'deactivate']);
    Route::get('/{coupon}/stats', [CouponAdminController::class, 'stats']);
});
