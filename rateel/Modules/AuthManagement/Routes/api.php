<?php

use Illuminate\Support\Facades\Route;

Route::controller(\Modules\AuthManagement\Http\Controllers\Api\New\AuthController::class)->group(function () {
    Route::group(['prefix' => 'customer'], function () {
        Route::group(['prefix' => 'auth'], function () {
            Route::post('registration', 'register')->name('customer-registration');
            Route::post('login', 'login')->name('customer-login');
            Route::post('social-login', 'customerSocialLogin');
            //login
            Route::post('otp-login', 'otpLogin');
            Route::post('check', 'userExistOrNotChecking');
            // reset or forget password
            Route::post('forget-password', 'forgetPassword');
            Route::post('reset-password', 'resetPassword');
            Route::post('otp-verification', 'otpVerification');
            Route::post('firebase-otp-verification', 'firebaseOtpVerification');
            //send otp for otp login or reset
            Route::post('send-otp', 'sendOtp');
            Route::post('external-registration', 'customerRegistrationFromMart');
            Route::post('external-login', 'customerLoginFromMart');

        });
    });

    //driver routes (LEGACY - kept for backward compatibility)
    Route::group(['prefix' => 'driver'], function () {
        Route::group(['prefix' => 'auth'], function () {
            Route::post('registration', 'register')->name('driver-registration');
            Route::post('login', 'login')->name('driver-login');
            Route::post('otp-login', 'otpLogin');
            Route::post('send-otp', 'sendOtp');
            Route::post('check', 'userExistOrNotChecking');
            Route::post('forget-password', 'forgetPassword');
            Route::post('reset-password', 'resetPassword');
            Route::post('otp-verification', 'otpVerification');
            Route::post('firebase-otp-verification', 'firebaseOtpVerification');
        });
    });
});

// ============================================
// NEW UNIFIED DRIVER ONBOARDING FLOW (UBER-STYLE)
// ============================================
// This replaces the separate login/register flow with a single entry point.
// The backend determines what the driver should see based on their onboarding state.
// 
// Flow: Phone → OTP → Password → Registration Info → Vehicle Type → Documents → Pending Approval → Dashboard
//
// Key principle: Driver enters phone, API returns next_step, Flutter shows that screen.
// ============================================

// V2 Driver Onboarding API
Route::group(['prefix' => 'v2/driver/onboarding'], function () {
    Route::controller(\Modules\UserManagement\Http\Controllers\Api\New\Driver\V2\DriverOnboardingV2Controller::class)->group(function () {
        // Step 1: Start - Enter phone number
        Route::post('start', 'start')->name('driver.onboarding.v2.start');

        // Step 2: Verify OTP
        Route::post('verify-otp', 'verifyOtp')->name('driver.onboarding.v2.verify-otp');

        // Step 3: Set password
        Route::post('set-password', 'setPassword')->name('driver.onboarding.v2.set-password');

        // Step 4: Registration info
        Route::post('register-info', 'registerInfo')->name('driver.onboarding.v2.register-info');

        // Step 5: Vehicle type selection
        Route::post('vehicle-type', 'vehicleType')->name('driver.onboarding.v2.vehicle-type');

        // Step 6: Document uploads
        Route::post('upload/id', 'uploadId')->name('driver.onboarding.v2.upload.id');
        Route::post('upload/license', 'uploadLicense')->name('driver.onboarding.v2.upload.license');
        Route::post('upload/car_photo', 'uploadCarPhoto')->name('driver.onboarding.v2.upload.car_photo');
        Route::post('upload/selfie', 'uploadSelfie')->name('driver.onboarding.v2.upload.selfie');
    });
});

// V1 Driver Onboarding API (Backward Compatibility)
Route::group(['prefix' => 'driver/auth'], function () {
    Route::controller(\Modules\UserManagement\Http\Controllers\Api\New\Driver\DriverOnboardingController::class)->group(function () {
        
        // Step 1: Start - Enter phone number (finds or creates driver, sends OTP)
        Route::post('start', 'start')->name('driver.onboarding.start');
        
        // Step 2: Verify OTP (determines next step based on driver state)
        Route::post('verify-otp', 'verifyOtp')->name('driver.onboarding.verify-otp');
        
        // Step 3: Set password (only for new drivers or password reset)
        Route::post('set-password', 'setPassword')->name('driver.onboarding.set-password');
        
        // Step 4: Registration info (first_name_ar, last_name_ar, national_id, city_id)
        Route::post('register-info', 'registerInfo')->name('driver.onboarding.register-info');
        
        // Step 5: Vehicle type selection (car, taxi, scooter + travel_enabled)
        Route::post('vehicle-type', 'vehicleType')->name('driver.onboarding.vehicle-type');

        // Step 6: Document upload (multi-file uploads)
        Route::post('upload/id', 'uploadId')->name('driver.onboarding.upload.id');
        Route::post('upload/license', 'uploadLicense')->name('driver.onboarding.upload.license');
        Route::post('upload/car_photo', 'uploadCarPhoto')->name('driver.onboarding.upload.car_photo');
        Route::post('upload/selfie', 'uploadSelfie')->name('driver.onboarding.upload.selfie');

        // Document retrieval (GET method - combined uploaded + missing)
        Route::get('documents', 'getDocuments')->name('driver.onboarding.documents');

        // Document update (re-upload documents)
        Route::put('update/id', 'updateId')->name('driver.onboarding.update.id');
        Route::put('update/license', 'updateLicense')->name('driver.onboarding.update.license');
        Route::put('update/car_photo', 'updateCarPhoto')->name('driver.onboarding.update.car_photo');
        Route::put('update/selfie', 'updateSelfie')->name('driver.onboarding.update.selfie');

        // Step 7: Skip/Complete KYC verification
        Route::post('skip-kyc', 'skipKycVerification')->name('driver.onboarding.skip-kyc');

        // Resume/Status endpoint - MOST IMPORTANT - Flutter calls this on app open
        Route::get('status', 'status')->name('driver.onboarding.status');
        
        // Login - Only works for approved drivers (onboarding_step = approved)
        Route::post('login', 'login')->name('driver.onboarding.login');
        
        // Resend OTP
        Route::post('resend-otp', 'resendOtp')->name('driver.onboarding.resend-otp');
    });
});

Route::controller(\Modules\AuthManagement\Http\Controllers\Api\New\AuthController::class)->group(function () {
    Route::group(['prefix' => 'user', 'middleware' => ['auth:api', 'maintenance_mode']], function () {
        Route::post('logout', 'logout')->name('logout');
        Route::post('delete', 'delete')->name('delete');
        Route::post('change-password', 'changePassword');
    });
});

