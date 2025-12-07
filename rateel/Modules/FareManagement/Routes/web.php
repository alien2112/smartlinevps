<?php

use Illuminate\Support\Facades\Route;
use Modules\FareManagement\Http\Controllers\Web\New\Admin\ParcelFareController;
use Modules\FareManagement\Http\Controllers\Web\New\Admin\TripFareController;


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
    Route::group(['prefix' => 'fare', 'as' => 'fare.'], function () {
        Route::group(['prefix' => 'parcel', 'as' => 'parcel.'], function () {
            Route::controller(ParcelFareController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('create/{zone_id}', 'create')->name('create');
                Route::post('store', 'store')->name('store');
            });
        });

        Route::group(['prefix' => 'trip', 'as' => 'trip.'], function () {
            Route::controller(TripFareController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('create/{zone_id}', 'create')->name('create');
                Route::post('store', 'store')->name('store');
            });
        });
    });
});
