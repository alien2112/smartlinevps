<?php

use Illuminate\Support\Facades\Route;
use Modules\CouponManagement\Http\Controllers\Web\Admin\CouponWebController;
use Modules\CouponManagement\Http\Controllers\Web\Admin\OfferWebController;

/*
|--------------------------------------------------------------------------
| Coupon Management Web Routes (Admin)
|--------------------------------------------------------------------------
*/

Route::group([
    'prefix' => 'admin/coupon-management',
    'as' => 'admin.coupon-management.',
    'middleware' => ['web', 'admin']
], function () {
    // Coupon CRUD
    Route::controller(CouponWebController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{id}', 'show')->name('show');
        Route::get('/{id}/edit', 'edit')->name('edit');
        Route::put('/{id}', 'update')->name('update');
        Route::delete('/{id}', 'destroy')->name('destroy');
        
        // Quick actions
        Route::post('/{id}/toggle-status', 'toggleStatus')->name('toggle-status');
        Route::post('/{id}/broadcast', 'broadcast')->name('broadcast');
        
        // Statistics
        Route::get('/{id}/stats', 'stats')->name('stats');
        
        // Target users management
        Route::get('/{id}/users', 'targetUsers')->name('target-users');
        Route::post('/{id}/users', 'addTargetUsers')->name('add-target-users');
        Route::delete('/{id}/users/{userId}', 'removeTargetUser')->name('remove-target-user');
    });
});

/*
|--------------------------------------------------------------------------
| Offer Management Web Routes (Admin)
|--------------------------------------------------------------------------
*/

Route::group([
    'prefix' => 'admin/offer-management',
    'as' => 'admin.offer-management.',
    'middleware' => ['web', 'admin']
], function () {
    Route::controller(OfferWebController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{id}', 'show')->name('show');
        Route::get('/{id}/edit', 'edit')->name('edit');
        Route::put('/{id}', 'update')->name('update');
        Route::delete('/{id}', 'destroy')->name('destroy');
        
        // Quick actions
        Route::post('/{id}/toggle-status', 'toggleStatus')->name('toggle-status');
        
        // Statistics
        Route::get('/{id}/stats', 'stats')->name('stats');
    });
});
