<?php

use Illuminate\Support\Facades\Route;
use Modules\UserManagement\Http\Controllers\Api\New\Customer\SupportController;
use App\Http\Controllers\Api\AiChatController;

/*
|--------------------------------------------------------------------------
| Customer API Routes - New Features (2026)
|--------------------------------------------------------------------------
|
| Customer support and additional features
|
*/

Route::group(['prefix' => 'customer', 'middleware' => ['auth:api']], function () {

    // ============================================
    // SUPPORT & HELP
    // ============================================
    Route::controller(SupportController::class)->prefix('auth/support')->group(function () {
        // App Info
        Route::get('/app-info', 'appInfo'); // Get app version info
    });

    // ============================================
    // AI CHATBOT
    // ============================================
    Route::controller(AiChatController::class)->prefix('ai-chat')->group(function () {
        Route::post('/', 'customerChat');                    // Send message to chatbot
        Route::get('/history', 'getChatHistory');            // Get chat history
        Route::delete('/history', 'clearChatHistory');       // Clear chat history
    });

});

// Driver AI Chat Routes
Route::group(['prefix' => 'driver', 'middleware' => ['auth:api']], function () {
    Route::controller(AiChatController::class)->prefix('ai-chat')->group(function () {
        Route::post('/', 'driverChat');                      // Send message to chatbot
        Route::get('/history', 'getChatHistory');            // Get chat history
        Route::delete('/history', 'clearChatHistory');       // Clear chat history
    });
});
