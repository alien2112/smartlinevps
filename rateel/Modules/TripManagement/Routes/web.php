<?php

use Illuminate\Support\Facades\Route;
use Modules\TripManagement\Http\Controllers\Web\New\RefundController;
use Modules\TripManagement\Http\Controllers\Web\New\SafetyAlertController;
use Modules\TripManagement\Http\Controllers\Web\TripController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => 'admin'], function () {
    Route::group(['prefix' => 'trip', 'as' => 'trip.'], function () {
        Route::controller(\Modules\TripManagement\Http\Controllers\Web\New\TripController::class)->group(function () {
            Route::get('list/{type}', 'tripList')->name('index');
            Route::get('details/{id}', 'show')->name('show');
            Route::delete('delete/{id}', 'destroy')->name('delete');
            Route::get('export', 'export')->name('export');
        });
        Route::controller(TripController::class)->group(function () {
            Route::get('invoice/{id}', 'invoice')->name('invoice');
            Route::get('log', 'log')->name('log');
            Route::get('trashed', 'trashed')->name('trashed');
            Route::get('restore/{id}', 'restore')->name('restore');
        });

        Route::group(['prefix' => 'refund', 'as' => 'refund.'], function () {
            Route::controller(RefundController::class)->group(function () {
                Route::get('list/{type}', 'parcelRefundList')->name('index');
                Route::get('details/{id}', 'show')->name('show');
                Route::post('approved/{id}', 'storeApproved')->name('approved');
                Route::post('denied/{id}', 'storeDenied')->name('denied');
                Route::post('store/{id}', 'store')->name('store');
                Route::get('export', 'export')->name('export');
            });
        });
    });

    Route::group(['prefix' => 'safety-alert', 'as' => 'safety-alert.'], function () {
        Route::controller(SafetyAlertController::class)->group(function () {
            Route::get('list/{type}', 'index')->name('index');
            Route::get('export/{type}', 'export')->name('export');
            Route::put('mark-as-solved/{id}', 'markAsSolved')->name('mark-as-solved');
            Route::put('ajax-mark-as-solved/{id}', 'ajaxMarkAsSolved')->name('ajax-mark-as-solved');
        });
    });

    // Lost & Found Management Routes
    Route::group(['prefix' => 'lost-items', 'as' => 'lost-items.'], function () {
        Route::controller(\Modules\TripManagement\Http\Controllers\Web\New\LostItemController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{id}', 'show')->name('show');
            Route::patch('/{id}/status', 'updateStatus')->name('update-status');
            Route::get('/export/download', 'export')->name('export');
        });
    });

});
