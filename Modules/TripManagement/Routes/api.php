<?php

use Illuminate\Support\Facades\Route;
use Modules\TripManagement\Http\Controllers\Api\Customer\TripRequestController;
use Modules\TripManagement\Http\Controllers\Api\Driver\TripRequestController as DriverTripController;
use Modules\TripManagement\Http\Controllers\Api\New\Customer\ParcelRefundController;
use Modules\TripManagement\Http\Controllers\Api\New\Customer\SafetyAlertController;
use Modules\TripManagement\Http\Controllers\Api\PaymentController;

/**
 * CUSTOMER API LIST
 */
Route::group(['prefix' => 'customer', 'middleware' => ['auth:api', 'maintenance_mode']], function () {
    Route::get('drivers-near-me', [TripRequestController::class, 'driversNearMe']);
    Route::group(['prefix' => 'ride'], function () {
        Route::controller(TripRequestController::class)->group(function () {
            Route::post('get-estimated-fare', 'getEstimatedFare');
            Route::post('create', 'createRideRequest');
            Route::put('ignore-bidding', 'ignoreBidding');
            Route::get('bidding-list/{trip_request_id}', 'biddingList');
            Route::put('update-status/{trip_request_id}', 'rideStatusUpdate');
            Route::get('details/{trip_request_id}', 'rideDetails');
            Route::get('list', 'rideList');
            Route::get('final-fare', 'finalFareCalculation');
            Route::post('trip-action', 'requestAction');
            Route::get('ride-resume-status', 'rideResumeStatus');
            Route::put('arrival-time', 'arrivalTime');
            Route::put('coordinate-arrival', 'coordinateArrival');
            Route::put('apply-coupon', 'applyCoupon');
            Route::put('cancel-coupon', 'cancelCoupon');
            Route::get('ongoing-parcel-list', 'pendingParcelList');
            Route::get('unpaid-parcel-list', 'unpaidParcelRequest');
            Route::put('received-returning-parcel/{trip_request_id}', 'receivedReturningParcel');
        });
        Route::post('track-location', [DriverTripController::class, 'trackLocation']);
        Route::get('payment', [PaymentController::class, 'payment']);
        Route::get('digital-payment', [PaymentController::class, 'digitalPayment']);
    });
    Route::group(['prefix' => 'parcel'], function () {
        Route::controller(ParcelRefundController::class)->group(function () {
            Route::group(['prefix' => 'refund'], function () {
                Route::post('create', 'createParcelRefundRequest');
            });
        });
    });
    Route::group(['prefix' => 'safety-alert'], function () {
        Route::controller(SafetyAlertController::class)->group(function () {
            Route::post('store', 'storeSafetyAlert');
            Route::put('resend/{trip_request_id}', 'resendSafetyAlert');
            Route::put('mark-as-solved/{trip_request_id}', 'markAsSolvedSafetyAlert');
            Route::get('show/{trip_request_id}', 'showSafetyAlert');
            Route::delete('undo/{trip_request_id}', 'deleteSafetyAlert');
        });
    });
    // Lost & Found routes
    Route::group(['prefix' => 'lost-items'], function () {
        Route::controller(\Modules\TripManagement\Http\Controllers\Api\New\Customer\LostItemController::class)->group(function () {
            Route::post('/', 'store');
            Route::get('/', 'index');
            Route::get('/{id}', 'show');
        });
    });
});


/**
 * DRIVER API LIST
 */
Route::group(['prefix' => 'driver', 'middleware' => ['auth:api', 'maintenance_mode']], function () {
    Route::post('last-ride-details', [DriverTripController::class, 'lastRideDetails']);
    Route::group(['prefix' => 'ride', 'middleware' => ['auth:api', 'maintenance_mode']], function () {
        Route::controller(DriverTripController::class)->group(function () {
            Route::post('bid', 'bid');
            Route::post('trip-action', 'requestAction');
            Route::put('update-status', 'rideStatusUpdate');
            Route::post('match-otp', 'matchOtp');
            Route::post('track-location', 'trackLocation');
            Route::get('details/{ride_request_id}', 'rideDetails');
            Route::get('list', 'rideList');
            Route::get('pending-ride-list', 'pendingRideList');
            Route::put('ride-waiting', 'rideWaiting');
            Route::put('arrival-time', 'arrivalTime');
            Route::put('coordinate-arrival', 'coordinateArrival');
            Route::get('overview', 'tripOverView');
            Route::post('ignore-trip-notification', 'ignoreTripNotification');
            Route::get('ongoing-parcel-list', 'pendingParcelList');
            Route::get('unpaid-parcel-list', 'unpaidParcelRequest');
            Route::put('returned-parcel', 'returnedParcel');
            Route::put('resend-otp', 'resendOtp');
        });
        Route::get('final-fare', [TripRequestController::class, 'finalFareCalculation']);
        Route::get('payment', [PaymentController::class, 'payment']);

        Route::controller(\Modules\TripManagement\Http\Controllers\Api\New\Driver\TripRequestController::class)->group(function () {
            Route::get('current-ride-status', 'currentRideStatus');
        });

        // Location tracking routes
        Route::controller(\Modules\TripManagement\Http\Controllers\Api\Driver\LocationController::class)->group(function () {
            Route::post('location', 'store');
            Route::get('location/history', 'history');
        });
    });
    Route::get('pending-ride-list-test', [DriverTripController::class, 'test']);
    Route::group(['prefix' => 'safety-alert'], function () {
        Route::controller(\Modules\TripManagement\Http\Controllers\Api\New\Driver\SafetyAlertController::class)->group(function () {
            Route::post('store', 'storeSafetyAlert');
            Route::put('resend/{trip_request_id}', 'resendSafetyAlert');
            Route::put('mark-as-solved/{trip_request_id}', 'markAsSolvedSafetyAlert');
            Route::get('show/{trip_request_id}', 'showSafetyAlert');
            Route::delete('undo/{trip_request_id}', 'deleteSafetyAlert');
        });
    });

    // Dynamic Rerouting API
    Route::group(['prefix' => 'rerouting'], function () {
        Route::controller(\Modules\TripManagement\Http\Controllers\Api\New\Driver\DynamicReroutingController::class)->group(function () {
            Route::post('check-deviation', 'checkDeviation');
            Route::post('request-new-route', 'requestNewRoute');
            Route::post('get-alternatives', 'getRouteAlternatives');
        });
    });

    // Lost & Found routes for driver
    Route::group(['prefix' => 'lost-items'], function () {
        Route::controller(\Modules\TripManagement\Http\Controllers\Api\New\Driver\LostItemController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('/pending', 'pending');
            Route::get('/{id}', 'show');
            Route::patch('/{id}', 'update');
        });
    });
});

Route::post('ride/store-screenshot', [TripRequestController::class, 'storeScreenshot'])->middleware('auth:api');
