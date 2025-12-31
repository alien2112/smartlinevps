<?php

use App\Http\Controllers\DemoController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ParcelTrackingController;
use App\Http\Controllers\PaymentRecordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
| NOTE: All routes must use controller methods (no closures) to enable
| route caching with `php artisan route:cache` in production.
|
*/

// Media serving route - serves images from /root/new/
Route::get('/media/{path}', [MediaController::class, 'serve'])
    ->where('path', '.*')
    ->name('media.serve');

// Test/Debug routes (converted from closures for route caching)
Route::get('/sender', [DemoController::class, 'sender'])->name('sender');
Route::get('/test-connection', [DemoController::class, 'testConnection'])->name('test-connection');
Route::get('trigger', [DemoController::class, 'trigger'])->name('trigger');
Route::get('test', [DemoController::class, 'testNotification'])->name('test');

Route::controller(LandingPageController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/contact-us', 'contactUs')->name('contact-us');
    Route::get('/about-us', 'aboutUs')->name('about-us');
    Route::get('/privacy', 'privacy')->name('privacy');
    Route::get('/terms', 'terms')->name('terms');
});

Route::get('track-parcel/{id}', [ParcelTrackingController::class, 'trackingParcel'])->name('track-parcel');

Route::get('add-payment-request', [PaymentRecordController::class, 'index']);

Route::get('payment-success', [PaymentRecordController::class, 'success'])->name('payment-success');
Route::get('payment-fail', [PaymentRecordController::class, 'fail'])->name('payment-fail');
Route::get('payment-cancel', [PaymentRecordController::class, 'cancel'])->name('payment-cancel');
Route::get('/update-data-test', [DemoController::class, 'demo'])->name('demo');

// Firebase Configuration Routes
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/firebase-config', [\App\Http\Controllers\Admin\FirebaseConfigController::class, 'index'])->name('firebase-config.index');
    Route::post('/firebase-config', [\App\Http\Controllers\Admin\FirebaseConfigController::class, 'store'])->name('firebase-config.store');
    Route::get('/firebase-config/test', [\App\Http\Controllers\Admin\FirebaseConfigController::class, 'test'])->name('firebase-config.test');
});
Route::get('sms-test', [DemoController::class, 'smsGatewayTest'])->name('sms-test');
Route::get('firebase-gen', [DemoController::class, 'firebaseMessageConfigFileGen'])->name('firebase-gen');
