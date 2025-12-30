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
        
        // Step 6: Document upload (id_front, id_back, license, car_photo, selfie)
        Route::post('upload/{type}', 'uploadDocument')->name('driver.onboarding.upload');
        
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

