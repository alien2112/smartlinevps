<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LocationConfigService;
use Illuminate\Http\Request;

class LocationConfigController extends Controller
{
    public function __construct(
        private LocationConfigService $configService
    ) {}

    /**
     * Get current active configuration
     */
    public function getConfig()
    {
        $config = $this->configService->getActiveConfig();

        return response()->json([
            'status' => 'success',
            'data' => [
                'config' => $config,
                'preset' => $this->configService->getCurrentPreset(),
                'refresh_interval' => config('tracking.config_refresh_interval'),
            ],
        ]);
    }

    /**
     * Set active preset
     */
    public function setPreset(string $preset)
    {
        $success = $this->configService->setActivePreset($preset);

        if (!$success) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid preset',
            ], 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => "Preset '{$preset}' activated",
            'data' => $this->configService->getActiveConfig(),
        ]);
    }

    /**
     * Save custom configuration
     */
    public function saveCustom(Request $request)
    {
        $validated = $request->validate([
            'config' => 'required|array',
            'config.idle' => 'required|array',
            'config.searching' => 'required|array',
            'config.on_trip' => 'required|array',
        ]);

        $success = $this->configService->saveConfig($validated['config']);

        if (!$success) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid configuration',
            ], 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Configuration saved',
            'data' => $this->configService->getActiveConfig(),
        ]);
    }

    /**
     * Get all available presets
     */
    public function getPresets()
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->configService->getPresets(),
        ]);
    }

    /**
     * Get configuration statistics
     */
    public function getStats()
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->configService->getStats(),
        ]);
    }

    /**
     * Reset to default configuration
     */
    public function reset()
    {
        $this->configService->resetToDefault();

        return response()->json([
            'status' => 'success',
            'message' => 'Configuration reset to default',
            'data' => $this->configService->getActiveConfig(),
        ]);
    }
}
