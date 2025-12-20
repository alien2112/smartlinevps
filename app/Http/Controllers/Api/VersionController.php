<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Version;
use Illuminate\Http\JsonResponse;

class VersionController extends Controller
{
    /**
     * Get current API version
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $version = Version::where('is_active', true)
            ->orderBy('id', 'desc')
            ->first();

        return response()->json([
            'status' => 'success',
            'data' => [
                'version' => $version?->version ?? 'v2',
                'description' => $version?->description ?? 'Current API version',
            ],
        ]);
    }

    /**
     * Get version string only
     *
     * @return JsonResponse
     */
    public function getVersion(): JsonResponse
    {
        return response()->json([
            'version' => Version::getCurrentVersion(),
        ]);
    }
}
