<?php

use Illuminate\Support\Facades\Route;


Route::controller(\Modules\BusinessManagement\Http\Controllers\Api\New\ConfigurationController::class)->group(function () {
    Route::get('/configurations', 'getConfiguration');
    Route::get('/get-external-configurations', 'getExternalConfiguration');
    Route::post('/store-configurations', 'updateConfiguration');
});

Route::group(['prefix' => 'location', 'middleware' => ['auth:api', 'maintenance_mode']], function () {
    Route::controller(\Modules\BusinessManagement\Http\Controllers\Api\New\Customer\ConfigController::class)->group(function () {
        Route::post('save', 'userLastLocation');
    });
});

#new route
Route::group(['prefix' => 'customer'], function () {
    Route::controller(\Modules\BusinessManagement\Http\Controllers\Api\New\Customer\ConfigController::class)->group(function () {
        // Legacy full configuration (backward compatibility)
        Route::get('configuration', 'configuration');
        Route::get('pages/{page_name}', 'pages');

        Route::group(['prefix' => 'config'], function () {
            // OPTIMIZED: Smaller, focused configuration endpoints
            // Use these instead of /configuration for better performance
            Route::get('core', 'coreConfig');           // Essential startup data (~2KB)
            Route::get('auth', 'authConfig');           // Authentication settings
            Route::get('trip', 'tripConfig');           // Trip/ride settings + payment gateways
            Route::get('safety', 'safetyConfig');       // Safety features
            Route::get('parcel', 'parcelConfig');       // Parcel settings
            Route::get('external', 'externalConfig');   // External/Mart integration
            Route::get('contact', 'contactConfig');     // Business contact info
            Route::get('loyalty', 'loyaltyConfig');     // Loyalty points/levels settings

            // Existing endpoints
            Route::get('get-zone-id', 'getZone');
            Route::get('place-api-autocomplete', 'placeApiAutocomplete');
            Route::get('distance_api', 'distanceApi');
            Route::get('place-api-details', 'placeApiDetails');
            Route::get('geocode-api', 'geocodeApi');
            Route::post('get-routes', 'getRoutes');
            Route::get('get-payment-methods', 'getPaymentMethods');
            Route::get('cancellation-reason-list', 'cancellationReasonList');
            Route::get('parcel-cancellation-reason-list', 'parcelCancellationReasonList');
            Route::get('parcel-refund-reason-list', 'parcelRefundReasonList');
            Route::get('other-emergency-contact-list', 'otherEmergencyContactList');
            Route::get('safety-alert-reason-list', 'safetyAlertReasonList');
            Route::get('safety-precaution-list', 'safetyPrecautionList');
        });
    });
});

Route::group(['prefix' => 'driver'], function () {
    Route::controller(\Modules\BusinessManagement\Http\Controllers\Api\New\Driver\ConfigController::class)->group(function () {
        // Legacy full configuration (backward compatibility)
        Route::get('configuration', 'configuration');

        Route::group(['prefix' => 'config'], function () {
            // OPTIMIZED: Smaller, focused configuration endpoints
            // Use these instead of /configuration for better performance
            Route::get('core', 'coreConfig');           // Essential startup data (~2KB)
            Route::get('auth', 'authConfig');           // Authentication settings
            Route::get('trip', 'tripConfig');           // Trip/ride settings
            Route::get('safety', 'safetyConfig');       // Safety features
            Route::get('parcel', 'parcelConfig');       // Parcel settings
            Route::get('contact', 'contactConfig');     // Business contact info
            Route::get('loyalty', 'loyaltyConfig');     // Loyalty points/levels settings

            // Utility endpoints
            Route::get('get-zone-id', 'getZone');
            Route::get('place-api-autocomplete', 'placeApiAutocomplete');
            Route::get('distance_api', 'distanceApi');
            Route::get('place-api-details', 'placeApiDetails');
            Route::get('geocode-api', 'geocodeApi');
            Route::get('cancellation-reason-list', 'cancellationReasonList');
            Route::get('parcel-cancellation-reason-list', 'parcelCancellationReasonList');
            Route::get('predefined-question-answer-list', 'predefinedQuestionAnswerList');
            Route::get('other-emergency-contact-list', 'otherEmergencyContactList');
            Route::get('safety-alert-reason-list', 'safetyAlertReasonList');
            Route::get('safety-precaution-list', 'safetyPrecautionList');
        });
        Route::group(['middleware' => ['auth:api', 'maintenance_mode']], function () {
            Route::post('get-routes', 'getRoutes');
        });
    });
});
