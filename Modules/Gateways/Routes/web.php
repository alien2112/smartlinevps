<?php

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

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Modules\Gateways\Http\Controllers\KashierController;

/*
 * SmartLine Payment Gateway Routes
 * Only Kashier payment gateway is enabled
 */

Route::group(['prefix' => 'payment'], function () {
    
    // KASHIER - Primary Payment Gateway
    Route::group(['prefix' => 'kashier', 'as' => 'kashier.'], function () {
        Route::any('pay', [KashierController::class, 'pay'])->name('pay');
        Route::any('callback', [KashierController::class, 'callback'])
            ->name('callback')
            ->withoutMiddleware([VerifyCsrfToken::class]);
        Route::post('webhook', [KashierController::class, 'webhook'])
            ->name('webhook')
            ->withoutMiddleware([VerifyCsrfToken::class]);
    });
});
