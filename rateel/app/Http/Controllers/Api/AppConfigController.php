<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Version;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AppConfigController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'nodejs_realtime_url' => config('services.nodejs_realtime.url', 'http://72.62.29.3:3000'),
            'api_url' => config('app.url'),
        ]);
    }

    /**
     * Get software version (converted from closure for route caching)
     */
    public function version(): JsonResponse
    {
        $version = Version::where('is_active', 1)->latest('id')->first();
        return response()->json(responseFormatter(DEFAULT_200, [
            'software_version' => $version ? $version->version : env('SOFTWARE_VERSION')
        ]));
    }

    /**
     * Get authenticated user (converted from closure for route caching)
     */
    public function currentUser(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    /**
     * Internal settings API for Node.js realtime service (converted from closure for route caching)
     */
    public function internalSettings(): JsonResponse
    {
        $settings = app(SettingsService::class)->getAsKeyValueArray();
        return response()->json([
            'success' => true,
            'settings' => $settings,
            'version' => Cache::get('app_settings:version', 1),
        ]);
    }
}
