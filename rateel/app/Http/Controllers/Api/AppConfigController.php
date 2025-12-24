<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AppConfigController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'nodejs_realtime_url' => config('services.nodejs_realtime.url', 'http://72.62.29.3:3000'),
            'api_url' => config('app.url'),
        ]);
    }
}
