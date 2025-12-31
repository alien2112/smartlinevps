<?php

use Illuminate\Support\Facades\Route;
use Modules\AuthManagement\Http\Controllers\Api\New\AuthController;
use Modules\BusinessManagement\Http\Controllers\Api\Driver\ConfigController as DriverConfigController;
use Modules\UserManagement\Http\Controllers\Api\AppNotificationController;
use Modules\UserManagement\Http\Controllers\Api\Customer\AddressController;
use Modules\UserManagement\Http\Controllers\Api\Customer\CustomerController;
use Modules\UserManagement\Http\Controllers\Api\Customer\LoyaltyPointController;
use Modules\UserManagement\Http\Controllers\Api\Driver\TimeTrackController;
use Modules\UserManagement\Http\Controllers\Api\New\Customer\WalletTransferController;
use Modules\UserManagement\Http\Controllers\Api\New\Customer\WalletController;
use Modules\UserManagement\Http\Controllers\Api\New\Driver\WithdrawController;
use Modules\UserManagement\Http\Controllers\Api\New\Driver\WithdrawMethodInfoController;
use Modules\UserManagement\Http\Controllers\Api\UserController;

Route::group(['prefix' => 'customer'], function () {
    Route::group(['middleware' => ['auth:api', 'maintenance_mode']], function () {
        Route::group(['prefix' => 'loyalty-points'], function () {
            Route::get('list', [LoyaltyPointController::class, 'index']);
            Route::post('convert', [LoyaltyPointController::class, 'convert']);
        });
        Route::group(['prefix' => 'level'], function () {
            Route::get('/', [\Modules\UserManagement\Http\Controllers\Api\New\Customer\CustomerLevelController::class, 'getCustomerLevelWithTrip']);
        });
        Route::get('info', [CustomerController::class, 'profileInfo']);
        Route::group(['prefix' => 'update'], function () {
            Route::put('fcm-token', [AuthController::class, 'updateFcmToken']); //for customer and driver use AuthController
            Route::put('profile', [CustomerController::class, 'updateProfile']);
        });
        Route::get('notification-list', [AppNotificationController::class, 'index']);
        Route::controller(\Modules\UserManagement\Http\Controllers\Api\New\Customer\CustomerController::class)->group(function () {
            Route::post('get-data', 'getCustomer');
            Route::post('external-update-data', 'externalUpdateCustomer')->withoutMiddleware('auth:api');
            Route::post('applied-coupon', 'applyCoupon');
            Route::post('change-language', 'changeLanguage');
            Route::get('referral-details', 'referralDetails');
        });

        Route::group(['prefix' => 'address'], function () {
            Route::controller(\Modules\UserManagement\Http\Controllers\Api\New\Customer\AddressController::class)->group(function () {
                Route::get('all-address', 'getAddresses');
                Route::post('add', 'store');
                Route::get('edit/{id}', 'edit');
                Route::put('update', 'update');
                Route::delete('delete', 'destroy');
            });
        });

        Route::group(['prefix' => 'wallet'], function () {
            // Wallet balance and top-up
            Route::controller(WalletController::class)->group(function () {
                Route::get('balance', 'getBalance');
                Route::post('add-fund', 'addFund');
                Route::get('transactions', 'transactionHistory');
            });
            // Wallet transfers
            Route::controller(WalletTransferController::class)->group(function () {
                Route::post('transfer-drivemond-to-mart', 'transferDrivemondToMartWallet');
                Route::post('transfer-drivemond-from-mart', 'transferDrivemondFromMartWallet')->withoutMiddleware('auth:api');
            });
        });
    });

});

Route::group(['prefix' => 'driver'], function () {

    Route::group(['middleware' => ['auth:api', 'maintenance_mode']], function () {
        Route::get('time-tracking', [TimeTrackController::class, 'store']);
        Route::post('update-online-status', [TimeTrackController::class, 'onlineStatus']);
        Route::group(['prefix' => 'update'], function () {
            Route::put('fcm-token', [AuthController::class, 'updateFcmToken']); //for customer and driver use AuthController
        });
        Route::get('notification-list', [AppNotificationController::class, 'index']);
        Route::group(['prefix' => 'activity'], function () {
            Route::controller(\Modules\UserManagement\Http\Controllers\Api\Driver\ActivityController::class)->group(function () {
                Route::get('leaderboard', 'leaderboard');
                Route::get('daily-income', 'dailyIncome');
            });
        });
        //new controller
        Route::controller(\Modules\UserManagement\Http\Controllers\Api\New\Driver\DriverController::class)->group(function () {
            Route::get('my-activity', 'myActivity');
            Route::post('change-language', 'changeLanguage');
            Route::get('info', 'profileInfo');
            Route::get('income-statement', 'incomeStatement');
            Route::put('update/profile', 'updateProfile');
            Route::get('referral-details', 'referralDetails');
            
            // Travel Approval System - Enterprise-grade
            Route::post('select-vehicle', 'selectVehicle');           // Select category + optional travel request
            Route::get('travel-status', 'travelStatus');              // Get current travel approval status
            Route::post('request-travel', 'requestTravel');           // Request travel privilege (standalone)
            Route::post('cancel-travel-request', 'cancelTravelRequest'); // Cancel pending travel request
        });
        //new controller
        Route::group(['prefix' => 'level'], function () {
            Route::controller(\Modules\UserManagement\Http\Controllers\Api\New\Driver\DriverLevelController::class)->group(function () {
                Route::get('/', 'getDriverLevelWithTrip');
            });
        });
        //new controller
        Route::group(['prefix' => 'loyalty-points'], function () {
            Route::controller(\Modules\UserManagement\Http\Controllers\Api\New\Driver\LoyaltyPointController::class)->group(function () {
                Route::get('list', 'index');
                Route::post('convert', 'convert');
            });
        });
        //new controller
        Route::group(['prefix' => 'withdraw'], function () {
            Route::controller(WithdrawController::class)->group(function () {
                Route::get('methods', 'methods');
                Route::post('request', 'create');
                Route::get('pending-request', 'getPendingWithdrawRequests');
                Route::get('settled-request', 'getSettledWithdrawRequests');
            });
        });
        //new controller
        Route::group(['prefix' => 'withdraw-method-info'], function () {
            Route::controller(WithdrawMethodInfoController::class)->group(function () {
                Route::get('list', 'index');
                Route::post('create', 'create');
                Route::get('edit/{id}', 'edit');
                Route::post('update/{id}', 'update');
                Route::post('delete/{id}', 'destroy');
            });
        });

        // KYC Verification routes
        Route::group(['prefix' => 'verification', 'middleware' => 'throttle:10,1'], function () {
            Route::controller(\Modules\UserManagement\Http\Controllers\Api\New\Driver\VerificationController::class)->group(function () {
                Route::post('session', 'createSession');
                Route::post('session/{id}/upload', 'uploadMedia');
                Route::post('session/{id}/submit', 'submitSession');
                Route::get('status', 'getStatus');
            });
        });
    });


});

Route::post('/user/store-live-location', [UserController::class, 'storeLastLocation']);
Route::post('/user/get-live-location', [UserController::class, 'getLastLocation']);

