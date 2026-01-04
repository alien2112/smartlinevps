<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V2\Driver\DriverOnboardingController;
use App\Http\Controllers\Api\V2\Driver\DriverAuthController;

/*
|--------------------------------------------------------------------------
| Driver Onboarding API Routes (V2)
|--------------------------------------------------------------------------
|
| Secure driver onboarding flow with:
| - Token-based authentication (no phone in query params)
| - State machine enforcement
| - Rate limiting
| - Consistent response format with next_step
|
*/

// Public endpoints (no auth required)
Route::prefix('v2/driver/onboarding')->group(function () {
    // Start onboarding - submit phone and receive OTP
    Route::post('start', [DriverOnboardingController::class, 'start']);

    // Verify OTP and receive onboarding token
    Route::post('verify-otp', [DriverOnboardingController::class, 'verifyOtp']);

    // Resend OTP
    Route::post('resend-otp', [DriverOnboardingController::class, 'resendOtp']);

    // Get available cities (zones) for profile selection
    Route::get('cities', [DriverOnboardingController::class, 'getCities']);
});

// Protected endpoints (require onboarding token)
Route::prefix('v2/driver/onboarding')->middleware(['auth:api', 'onboarding'])->group(function () {
    // Get current onboarding status
    Route::get('status', [DriverOnboardingController::class, 'status']);

    // Set password
    Route::post('password', [DriverOnboardingController::class, 'setPassword']);

    // Submit profile information
    Route::post('profile', [DriverOnboardingController::class, 'submitProfile']);

    // Select vehicle type
    Route::post('vehicle', [DriverOnboardingController::class, 'selectVehicle']);

    // Upload document
    Route::post('documents/{type}', [DriverOnboardingController::class, 'uploadDocument']);

    // Submit application for review
    Route::post('submit', [DriverOnboardingController::class, 'submit']);
});

// Driver authentication (public)
Route::prefix('v2/driver/auth')->group(function () {
    // Login for approved drivers
    Route::post('login', [DriverAuthController::class, 'login']);
});
