<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminModule\Http\Controllers\Web\New\Admin\ActivityLogController;
use Modules\AdminModule\Http\Controllers\Web\New\Admin\DashboardController;
use Modules\AdminModule\Http\Controllers\Web\New\Admin\FirebaseSubscribeController;
use Modules\AdminModule\Http\Controllers\Web\New\Admin\ReportController;
use Modules\AdminModule\Http\Controllers\Web\New\Admin\SettingController;
use Modules\AdminModule\Http\Controllers\Web\New\Admin\SharedController;
use Modules\AdminModule\Http\Controllers\Web\New\Admin\FleetMapViewController;
use Modules\AdminModule\Http\Controllers\Web\New\Admin\TripTrackingController;
use Modules\AdminModule\Http\Controllers\Web\New\Admin\NotificationController;
use Modules\AdminModule\Http\Controllers\Web\Admin\AiChatbotController;

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
    Route::controller(FirebaseSubscribeController::class)->group(function () {
        Route::post('/subscribe-topic', 'subscribeToTopic')->name('subscribe-topic');
    });
    Route::controller(FleetMapViewController::class)->group(function(){
        Route::get('fleet-map/{type}', 'fleetMap')->name('fleet-map');
        Route::get('fleet-map-driver-list/{type}', 'fleetMapDriverList')->name('fleet-map-driver-list');
        Route::get('fleet-map-driver-details/{id}', 'fleetMapDriverDetails')->name('fleet-map-driver-details');
        Route::get('fleet-map-view-single-driver/{id}', 'fleetMapViewSingleDriver')->name('fleet-map-view-single-driver');
        Route::get('fleet-map-customer-list/{type}', 'fleetMapCustomerList')->name('fleet-map-customer-list');
        Route::get('fleet-map-customer-details/{id}', 'fleetMapCustomerDetails')->name('fleet-map-customer-details');
        Route::get('fleet-map-view-single-customer/{id}', 'fleetMapViewSingleCustomer')->name('fleet-map-view-single-customer');
        Route::get('fleet-map-view-using-ajax', 'fleetMapViewUsingAjax')->name('fleet-map-view-using-ajax');
        Route::get('fleet-map-safety-alert-icon-in-map', 'fleetMapSafetyAlertIconInMap')->name('fleet-map-safety-alert-icon-in-map');
        Route::get('fleet-map-zone-message', 'fleetMapZoneMessage')->name('fleet-map-zone-message');
    });

    // Trip Tracking Routes
    Route::controller(TripTrackingController::class)->group(function() {
        Route::get('trip-tracking', 'index')->name('trip-tracking');
        Route::get('trip-tracking-data', 'tripTrackingData')->name('trip-tracking-data');
    });

    Route::controller(DashboardController::class)->group(function () {
        Route::get('/', 'index')->name('dashboard');
        Route::get('heat-map', 'heatMap')->name('heat-map');
        Route::get('heat-map-overview-data', 'heatMapOverview')->name('heat-map-overview-data');
        Route::get('heat-map-compare', 'heatMapCompare')->name('heat-map-compare');
        Route::get('recent-trip-activity', 'recentTripActivity')->name('recent-trip-activity');
        Route::get('leader-board-driver', 'leaderBoardDriver')->name('leader-board-driver');
        Route::get('leader-board-customer', 'leaderBoardCustomer')->name('leader-board-customer');
        Route::get('earning-statistics', 'adminEarningStatistics')->name('earning-statistics');
        Route::get('zone-wise-statistics', 'zoneWiseStatistics')->name('zone-wise-statistics');
        Route::post('clear-cache', 'clearCache')->name('clear-cache');
        Route::get('chatting', 'chatting')->name('chatting');
        Route::get('driver-conversation/{channelId}', 'getDriverConversation')->name('driver-conversation');
        Route::post('send-message-to-driver', 'sendMessageToDriver')->name('send-message-to-driver');
        Route::get('search-drivers', 'searchDriversList')->name('search-drivers');
        Route::get('search-saved-topic-answers', 'searchSavedTopicAnswer')->name('search-saved-topic-answers');
        Route::put('create-channel-with-admin', 'createChannelWithAdmin')->name('create-channel-with-admin');

        // Feature toggles
        Route::get('feature-toggles', 'getFeatureToggles')->name('feature-toggles');
        Route::post('toggle-ai-chatbot', 'toggleAiChatbot')->name('toggle-ai-chatbot');
        Route::post('toggle-honeycomb', 'toggleHoneycomb')->name('toggle-honeycomb');
    });
    Route::controller(ActivityLogController::class)->group(function () {
        Route::get('log', 'log')->name('log');
    });
    Route::controller(SettingController::class)->group(function () {
        Route::get('settings', 'index')->name('settings');
        Route::post('update-profile/{id}', 'update')->name('update-profile');
    });
    Route::controller(SharedController::class)->group(function () {
        Route::get('seen-notification', 'seenNotification')->name('seen-notification');
        Route::get('get-notifications', 'getNotifications')->name('get-notifications');
        Route::get('get-safety-alert', 'getSafetyAlert')->name('get-safety-alert');
    });
    Route::controller(NotificationController::class)->group(function () {
        Route::get('add-new', 'index')->name('add-new');
        Route::post('store', 'store')->name('store');
        Route::get('edit/{id}', 'edit')->name('edit');
        Route::post('update/{id}', 'update')->name('update');
        Route::post('status', 'status')->name('status');
        Route::post('resend-notification', 'resendNotification')->name('resend-notification');
        Route::post('delete', 'delete')->name('delete');
        Route::get('push', 'push_notification')->name('push');
        Route::post('update-push-notification', 'update_push_notification')->name('update-push-notification');
    });

    Route::controller(AiChatbotController::class)->group(function () {
        Route::get('chatbot/config', 'index')->name('chatbot.index');
        Route::post('chatbot/update', 'update')->name('chatbot.update');
        Route::get('chatbot/logs', 'logs')->name('chatbot.logs');
        Route::post('chatbot/clear-history', 'clearUserHistory')->name('chatbot.clear-history');
        Route::get('chatbot/health', 'health')->name('chatbot.health');
    });

    // App Settings (Tracking, Dispatch, Travel, Map)
    Route::group(['prefix' => 'app-settings', 'as' => 'app-settings.'], function () {
        Route::controller(\App\Http\Controllers\Admin\SettingsController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/update', 'update')->name('update');
            Route::post('/update-single', 'updateSingle')->name('update-single');
            Route::post('/reset', 'resetToDefault')->name('reset');
            Route::post('/reset-group', 'resetGroupToDefaults')->name('reset-group');
            Route::get('/load-preview', 'loadPreview')->name('load-preview');
            Route::get('/group/{group}', 'getGroupSettings')->name('group');
        });
    });
});
Route::controller(SharedController::class)->group(function () {
    Route::get('lang/{locale}', 'lang')->name('lang');
});

