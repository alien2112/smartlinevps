<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/version', function () {
    $version = \App\Models\Version::where('is_active', 1)->latest('id')->first();
    return response()->json(responseFormatter(DEFAULT_200, ['software_version' => $version ? $version->version : env('SOFTWARE_VERSION')]));
});

// Internal settings API for Node.js realtime service
Route::get('/internal/settings', function () {
    $settings = app(\App\Services\SettingsService::class)->getAsKeyValueArray();
    return response()->json([
        'success' => true,
        'settings' => $settings,
        'version' => \Cache::get('app_settings:version', 1),
    ]);
})->middleware('throttle:60,1');
