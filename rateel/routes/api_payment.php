<?php

use App\Http\Controllers\Api\Payment\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Payment API Routes
|--------------------------------------------------------------------------
*/

// Webhook endpoints (no authentication required)
Route::prefix('payment/webhook')->name('api.payment.webhook.')->group(function () {
    Route::post('kashier', [WebhookController::class, 'kashier'])->name('kashier');
});
