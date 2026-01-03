<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Location Configuration Service
 *
 * Manages dynamic location update configuration in Redis.
 * Allows real-time updates without mobile app deployment.
 */
class LocationConfigService
{
    private string $redisKey;
    private array $config;

    public function __construct()
    {
        $this->redisKey = config('tracking.redis_config_key');
        $this->config = config('tracking');
    }

    /**
     * Get current active configuration
     *
     * @return array
     */
    public function getActiveConfig(): array
    {
        // Try Redis first
        $cachedConfig = Redis::get($this->redisKey);

        if ($cachedConfig) {
            return json_decode($cachedConfig, true);
        }

        // Fall back to config file
        $activePreset = config('tracking.active_preset', 'normal');
        $config = $this->getPresetConfig($activePreset);

        // Cache it
        $this->saveConfig($config);

        return $config;
    }

    /**
     * Get configuration for a specific preset
     *
     * @param string $preset
     * @return array
     */
    public function getPresetConfig(string $preset): array
    {
        $presets = config('tracking.presets');

        if (!isset($presets[$preset])) {
            Log::warning("Invalid preset requested: {$preset}, falling back to 'normal'");
            $preset = 'normal';
        }

        return $presets[$preset]['config'];
    }

    /**
     * Set active preset
     *
     * @param string $preset
     * @return bool
     */
    public function setActivePreset(string $preset): bool
    {
        $config = $this->getPresetConfig($preset);

        if (!$config) {
            return false;
        }

        // Save to Redis
        $saved = $this->saveConfig($config);

        if ($saved) {
            // Update active preset in cache
            Cache::forever('location:active_preset', $preset);

            // Log preset change
            Log::info("Location config preset changed to: {$preset}");

            // Publish notification to Node.js servers
            $this->publishConfigUpdate($preset, $config);
        }

        return $saved;
    }

    /**
     * Save custom configuration
     *
     * @param array $config
     * @return bool
     */
    public function saveConfig(array $config): bool
    {
        // Apply safety clamps
        $config = $this->applySafetyClamps($config);

        // Validate configuration
        if (!$this->validateConfig($config)) {
            Log::error('Invalid location configuration', ['config' => $config]);
            return false;
        }

        // Save to Redis (no expiry)
        $saved = Redis::set($this->redisKey, json_encode($config));

        if ($saved) {
            // Publish update notification
            $this->publishConfigUpdate('custom', $config);
        }

        return (bool) $saved;
    }

    /**
     * Apply safety clamps to configuration
     *
     * @param array $config
     * @return array
     */
    private function applySafetyClamps(array $config): array
    {
        $clamps = config('tracking.safety_clamps');

        foreach (['idle', 'searching', 'on_trip'] as $state) {
            if (!isset($config[$state])) {
                continue;
            }

            // Clamp interval
            if (isset($config[$state]['interval_sec'])) {
                $config[$state]['interval_sec'] = max(
                    $clamps['interval_sec']['min'],
                    min($clamps['interval_sec']['max'], $config[$state]['interval_sec'])
                );
            }

            // Clamp distance
            if (isset($config[$state]['distance_m'])) {
                $config[$state]['distance_m'] = max(
                    $clamps['distance_m']['min'],
                    min($clamps['distance_m']['max'], $config[$state]['distance_m'])
                );
            }

            // Clamp speed change
            if (isset($config[$state]['speed_change_pct'])) {
                $config[$state]['speed_change_pct'] = max(
                    $clamps['speed_change_pct']['min'],
                    min($clamps['speed_change_pct']['max'], $config[$state]['speed_change_pct'])
                );
            }

            // Clamp heading change
            if (isset($config[$state]['heading_change_deg'])) {
                $config[$state]['heading_change_deg'] = max(
                    $clamps['heading_change_deg']['min'],
                    min($clamps['heading_change_deg']['max'], $config[$state]['heading_change_deg'])
                );
            }
        }

        return $config;
    }

    /**
     * Validate configuration structure
     *
     * @param array $config
     * @return bool
     */
    private function validateConfig(array $config): bool
    {
        $requiredStates = ['idle', 'searching', 'on_trip'];

        foreach ($requiredStates as $state) {
            if (!isset($config[$state])) {
                return false;
            }

            if (!isset($config[$state]['interval_sec']) || !isset($config[$state]['distance_m'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Publish configuration update to Node.js servers
     *
     * @param string $preset
     * @param array $config
     * @return void
     */
    private function publishConfigUpdate(string $preset, array $config): void
    {
        $message = [
            'event' => 'config:location:updated',
            'preset' => $preset,
            'config' => $config,
            'timestamp' => now()->toIso8601String(),
        ];

        // Publish to Redis channel for Node.js servers
        Redis::publish('location:config:updates', json_encode($message));

        Log::info('Published location config update', ['preset' => $preset]);
    }

    /**
     * Get all available presets
     *
     * @return array
     */
    public function getPresets(): array
    {
        return config('tracking.presets');
    }

    /**
     * Get current preset name
     *
     * @return string
     */
    public function getCurrentPreset(): string
    {
        return Cache::get('location:active_preset', config('tracking.active_preset', 'normal'));
    }

    /**
     * Get configuration statistics
     *
     * @return array
     */
    public function getStats(): array
    {
        $config = $this->getActiveConfig();
        $currentPreset = $this->getCurrentPreset();

        return [
            'current_preset' => $currentPreset,
            'last_updated' => Cache::get('location:config:last_updated'),
            'config' => $config,
            'avg_update_intervals' => [
                'idle' => $config['idle']['interval_sec'] ?? 0,
                'searching' => $config['searching']['interval_sec'] ?? 0,
                'on_trip' => $config['on_trip']['interval_sec'] ?? 0,
            ],
            'estimated_updates_per_driver_per_min' => $this->estimateUpdatesPerDriver($config),
        ];
    }

    /**
     * Estimate average updates per driver per minute
     *
     * @param array $config
     * @return float
     */
    private function estimateUpdatesPerDriver(array $config): float
    {
        // Weighted average based on typical state distribution
        // Assumptions: 60% idle, 20% searching, 20% on trip
        $idle = 60 / ($config['idle']['interval_sec'] ?? 45);
        $searching = 60 / ($config['searching']['interval_sec'] ?? 12);
        $onTrip = 60 / ($config['on_trip']['interval_sec'] ?? 7);

        return round(($idle * 0.6) + ($searching * 0.2) + ($onTrip * 0.2), 2);
    }

    /**
     * Apply dynamic throttling based on server load
     *
     * @param float $cpuUsage CPU usage percentage (0-100)
     * @param float $redisLatency Redis latency in milliseconds
     * @return bool
     */
    public function applyDynamicThrottling(float $cpuUsage, float $redisLatency): bool
    {
        if (!config('tracking.dynamic_throttling.enabled', false)) {
            return false;
        }

        $currentConfig = $this->getActiveConfig();
        $modified = false;

        // Check CPU thresholds
        $cpuThresholds = config('tracking.dynamic_throttling.cpu_thresholds', []);
        ksort($cpuThresholds);

        foreach ($cpuThresholds as $threshold => $multipliers) {
            if ($cpuUsage >= $threshold) {
                $currentConfig = $this->applyMultipliers($currentConfig, $multipliers);
                $modified = true;
            }
        }

        // Check Redis latency thresholds
        $latencyThresholds = config('tracking.dynamic_throttling.redis_latency_thresholds_ms', []);
        ksort($latencyThresholds);

        foreach ($latencyThresholds as $threshold => $multipliers) {
            if ($redisLatency >= $threshold) {
                $currentConfig = $this->applyMultipliers($currentConfig, $multipliers);
                $modified = true;
            }
        }

        if ($modified) {
            // Save throttled configuration
            $this->saveConfig($currentConfig);

            Log::warning('Dynamic throttling applied', [
                'cpu_usage' => $cpuUsage,
                'redis_latency' => $redisLatency,
            ]);

            return true;
        }

        return false;
    }

    /**
     * Apply multipliers to configuration
     *
     * @param array $config
     * @param array $multipliers
     * @return array
     */
    private function applyMultipliers(array $config, array $multipliers): array
    {
        $intervalMult = $multipliers['interval_multiplier'] ?? 1.0;
        $distanceMult = $multipliers['distance_multiplier'] ?? 1.0;

        foreach (['idle', 'searching', 'on_trip'] as $state) {
            if (isset($config[$state]['interval_sec'])) {
                $config[$state]['interval_sec'] = round($config[$state]['interval_sec'] * $intervalMult);
            }

            if (isset($config[$state]['distance_m'])) {
                $config[$state]['distance_m'] = round($config[$state]['distance_m'] * $distanceMult);
            }
        }

        return $config;
    }

    /**
     * Reset to default configuration
     *
     * @return bool
     */
    public function resetToDefault(): bool
    {
        return $this->setActivePreset('normal');
    }
}
