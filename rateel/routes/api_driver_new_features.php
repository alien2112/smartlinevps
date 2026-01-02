<?php

use Illuminate\Support\Facades\Route;
use Modules\UserManagement\Http\Controllers\Api\New\Driver\NotificationController;
use Modules\UserManagement\Http\Controllers\Api\New\Driver\SupportController;

/*
|--------------------------------------------------------------------------
| Driver API Routes - New Features (2026)
|--------------------------------------------------------------------------
|
| All new driver features implemented:
| - Notifications system
| - Support & Help (FAQ, Tickets, Feedback, Issue Reports)
| - Content Pages
| - Account Management
| - Privacy Settings
| - Emergency Contacts
|
*/

Route::group(['prefix' => 'driver/auth', 'middleware' => ['auth:api']], function () {

    // ============================================
    // NOTIFICATIONS
    // ============================================
    Route::controller(NotificationController::class)->prefix('notifications')->group(function () {
        Route::get('/', 'index'); // Get all notifications
        Route::get('/unread-count', 'unreadCount'); // Get unread count
        Route::post('/{id}/read', 'markAsRead'); // Mark as read
        Route::post('/{id}/unread', 'markAsUnread'); // Mark as unread
        Route::post('/read-all', 'markAllAsRead'); // Mark all as read
        Route::delete('/{id}', 'destroy'); // Delete notification
        Route::post('/clear-read', 'clearRead'); // Clear all read notifications

        // Notification Settings
        Route::get('/settings', 'getSettings'); // Get settings
        Route::put('/settings', 'updateSettings'); // Update settings
    });

    // ============================================
    // SUPPORT & HELP
    // ============================================
    Route::controller(SupportController::class)->prefix('support')->group(function () {
        // FAQs
        Route::get('/faqs', 'faqs'); // Get FAQs
        Route::post('/faqs/{id}/feedback', 'faqFeedback'); // Mark FAQ as helpful/not

        // Support Tickets
        Route::get('/tickets', 'tickets'); // Get all tickets
        Route::post('/tickets', 'createTicket'); // Create new ticket
        Route::get('/tickets/{id}', 'ticketDetails'); // Get ticket details
        Route::post('/tickets/{id}/reply', 'replyToTicket'); // Reply to ticket
        Route::post('/tickets/{id}/rate', 'rateTicket'); // Rate support

        // Feedback
        Route::post('/feedback', 'submitFeedback'); // Submit feedback

        // Issue Reports
        Route::post('/report-issue', 'reportIssue'); // Report an issue

        // App Info
        Route::get('/app-info', 'appInfo'); // Get app version info
    });

    // ============================================
    // CONTENT PAGES
    // ============================================
    Route::get('/pages/{slug}', [\App\Http\Controllers\Api\ContentPageController::class, 'show']);
    Route::get('/pages', [\App\Http\Controllers\Api\ContentPageController::class, 'index']);

    // ============================================
    // ACCOUNT MANAGEMENT
    // ============================================
    Route::prefix('account')->group(function () {
        // Privacy Settings
        Route::get('/privacy-settings', [\App\Http\Controllers\Api\Driver\AccountController::class, 'getPrivacySettings']);
        Route::put('/privacy-settings', [\App\Http\Controllers\Api\Driver\AccountController::class, 'updatePrivacySettings']);

        // Emergency Contacts
        Route::get('/emergency-contacts', [\App\Http\Controllers\Api\Driver\AccountController::class, 'getEmergencyContacts']);
        Route::post('/emergency-contacts', [\App\Http\Controllers\Api\Driver\AccountController::class, 'createEmergencyContact']);
        Route::put('/emergency-contacts/{id}', [\App\Http\Controllers\Api\Driver\AccountController::class, 'updateEmergencyContact']);
        Route::delete('/emergency-contacts/{id}', [\App\Http\Controllers\Api\Driver\AccountController::class, 'deleteEmergencyContact']);
        Route::post('/emergency-contacts/{id}/set-primary', [\App\Http\Controllers\Api\Driver\AccountController::class, 'setPrimaryContact']);

        // Phone Change
        Route::post('/change-phone/request', [\App\Http\Controllers\Api\Driver\AccountController::class, 'requestPhoneChange']);
        Route::post('/change-phone/verify-old', [\App\Http\Controllers\Api\Driver\AccountController::class, 'verifyOldPhone']);
        Route::post('/change-phone/verify-new', [\App\Http\Controllers\Api\Driver\AccountController::class, 'verifyNewPhone']);

        // Account Deletion
        Route::post('/delete-request', [\App\Http\Controllers\Api\Driver\AccountController::class, 'requestAccountDeletion']);
        Route::post('/delete-cancel', [\App\Http\Controllers\Api\Driver\AccountController::class, 'cancelDeletionRequest']);
        Route::get('/delete-status', [\App\Http\Controllers\Api\Driver\AccountController::class, 'deletionStatus']);
    });

    // ============================================
    // DASHBOARD & ACTIVITY
    // ============================================
    Route::prefix('dashboard')->group(function () {
        Route::get('/widgets', [\App\Http\Controllers\Api\Driver\DashboardController::class, 'widgets']);
        Route::get('/recent-activity', [\App\Http\Controllers\Api\Driver\DashboardController::class, 'recentActivity']);
        Route::get('/promotional-banners', [\App\Http\Controllers\Api\Driver\DashboardController::class, 'promotionalBanners']);
    });

    // ============================================
    // TRIP REPORTS
    // ============================================
    Route::prefix('reports')->group(function () {
        Route::get('/weekly', [\App\Http\Controllers\Api\Driver\ReportController::class, 'weeklyReport']);
        Route::get('/monthly', [\App\Http\Controllers\Api\Driver\ReportController::class, 'monthlyReport']);
        Route::post('/export', [\App\Http\Controllers\Api\Driver\ReportController::class, 'exportReport']);
    });

    // ============================================
    // VEHICLE MANAGEMENT
    // ============================================
    Route::prefix('vehicle')->group(function () {
        Route::get('/insurance-status', [\App\Http\Controllers\Api\Driver\VehicleController::class, 'insuranceStatus']);
        Route::post('/insurance-update', [\App\Http\Controllers\Api\Driver\VehicleController::class, 'updateInsurance']);
        Route::get('/inspection-status', [\App\Http\Controllers\Api\Driver\VehicleController::class, 'inspectionStatus']);
        Route::post('/inspection-update', [\App\Http\Controllers\Api\Driver\VehicleController::class, 'updateInspection']);
        Route::get('/reminders', [\App\Http\Controllers\Api\Driver\VehicleController::class, 'getReminders']);
    });

    // ============================================
    // DOCUMENTS
    // ============================================
    Route::prefix('documents')->group(function () {
        Route::get('/expiry-status', [\App\Http\Controllers\Api\Driver\DocumentController::class, 'expiryStatus']);
        Route::post('/{id}/update-expiry', [\App\Http\Controllers\Api\Driver\DocumentController::class, 'updateExpiry']);
    });

    // ============================================
    // GAMIFICATION
    // ============================================
    Route::prefix('gamification')->group(function () {
        Route::get('/achievements', [\App\Http\Controllers\Api\Driver\GamificationController::class, 'achievements']);
        Route::get('/badges', [\App\Http\Controllers\Api\Driver\GamificationController::class, 'badges']);
        Route::get('/progress', [\App\Http\Controllers\Api\Driver\GamificationController::class, 'progress']);
    });

    // ============================================
    // PROMOTIONS & OFFERS
    // ============================================
    Route::prefix('promotions')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Driver\PromotionController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\Api\Driver\PromotionController::class, 'show']);
        Route::post('/{id}/claim', [\App\Http\Controllers\Api\Driver\PromotionController::class, 'claim']);
    });

    // ============================================
    // READINESS CHECK
    // ============================================
    // Comprehensive driver readiness check: validates account, GPS, vehicle,
    // documents, connectivity, active trips, and overall ready status
    Route::get('/readiness-check', [\App\Http\Controllers\Api\Driver\ReadinessController::class, 'check']);
});
