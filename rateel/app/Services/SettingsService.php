<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class SettingsService
{
    private const CACHE_KEY = 'app_settings:all';
    private const VERSION_KEY = 'app_settings:version';
    private const CACHE_TTL = 3600; // 1 hour

    private ?array $settings = null;

    /**
     * Get a setting value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->getAllSettings();

        if (!isset($settings[$key])) {
            return $default;
        }

        return $settings[$key]['typed_value'] ?? $default;
    }

    /**
     * Get raw setting record
     */
    public function getRaw(string $key): ?array
    {
        $settings = $this->getAllSettings();
        return $settings[$key] ?? null;
    }

    /**
     * Set a setting value
     */
    public function set(string $key, mixed $value, ?string $adminId = null): bool
    {
        try {
            $setting = AppSetting::where('key', $key)->first();

            if (!$setting) {
                Log::warning('Attempted to set non-existent setting', ['key' => $key]);
                return false;
            }

            // Validate the value
            $errors = $setting->validateValue($value);
            if (!empty($errors)) {
                Log::warning('Setting validation failed', ['key' => $key, 'errors' => $errors]);
                return false;
            }

            // Convert value to string for storage
            $stringValue = is_array($value) || is_object($value)
                ? json_encode($value)
                : (string) $value;

            $setting->update([
                'value' => $stringValue,
                'updated_by_admin_id' => $adminId,
            ]);

            // Invalidate cache
            $this->invalidateCache();

            // Publish to Redis for Node.js consumers
            $this->publishSettingsUpdate($key, $value);

            Log::info('Setting updated', ['key' => $key, 'admin_id' => $adminId]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to set setting', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Set multiple settings at once
     */
    public function setMany(array $settings, ?string $adminId = null): array
    {
        $results = [];

        foreach ($settings as $key => $value) {
            $results[$key] = $this->set($key, $value, $adminId);
        }

        return $results;
    }

    /**
     * Get all settings for a group
     */
    public function getGroup(string $group): array
    {
        $settings = $this->getAllSettings();

        return array_filter($settings, function ($setting) use ($group) {
            return ($setting['group'] ?? '') === $group;
        });
    }

    /**
     * Get all available groups
     */
    public function getGroups(): array
    {
        return ['tracking', 'dispatch', 'travel', 'map'];
    }

    /**
     * Reset a setting to its default value
     */
    public function resetToDefault(string $key, ?string $adminId = null): bool
    {
        $setting = AppSetting::where('key', $key)->first();

        if (!$setting) {
            return false;
        }

        return $this->set($key, $setting->default_value, $adminId);
    }

    /**
     * Reset all settings in a group to defaults
     */
    public function resetGroupToDefaults(string $group, ?string $adminId = null): array
    {
        $settings = AppSetting::where('group', $group)->get();
        $results = [];

        foreach ($settings as $setting) {
            $results[$setting->key] = $this->set($setting->key, $setting->default_value, $adminId);
        }

        return $results;
    }

    /**
     * Get all settings (cached)
     */
    public function getAllSettings(): array
    {
        // In-memory cache for current request
        if ($this->settings !== null) {
            return $this->settings;
        }

        try {
            $version = $this->getCacheVersion();
            $cacheKey = self::CACHE_KEY . ':v' . $version;

            $this->settings = Cache::remember($cacheKey, self::CACHE_TTL, function () {
                return $this->loadSettingsFromDb();
            });

            return $this->settings;
        } catch (\Exception $e) {
            Log::error('Failed to load settings from cache', ['error' => $e->getMessage()]);
            return $this->loadSettingsFromDb();
        }
    }

    /**
     * Load settings from database
     */
    private function loadSettingsFromDb(): array
    {
        $settings = [];

        AppSetting::all()->each(function ($setting) use (&$settings) {
            $settings[$setting->key] = [
                'key' => $setting->key,
                'value' => $setting->value,
                'typed_value' => $setting->typed_value,
                'type' => $setting->type,
                'group' => $setting->group,
                'label' => $setting->label,
                'description' => $setting->description,
                'validation_rules' => $setting->validation_rules,
                'default_value' => $setting->default_value,
                'typed_default_value' => $setting->typed_default_value,
                'updated_at' => $setting->updated_at?->toISOString(),
            ];
        });

        // Also store in Redis for Node.js consumption
        $this->storeInRedis($settings);

        return $settings;
    }

    /**
     * Store settings in Redis for Node.js consumption
     */
    private function storeInRedis(array $settings): void
    {
        try {
            // Store as JSON for easy Node.js consumption
            Redis::set('app:settings', json_encode($settings));
            Redis::set('app:settings:version', $this->getCacheVersion());

            // Also store individual settings for quick access
            foreach ($settings as $key => $setting) {
                Redis::hset('app:settings:values', $key, json_encode($setting['typed_value']));
            }
        } catch (\Exception $e) {
            Log::warning('Failed to store settings in Redis', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get current cache version
     */
    private function getCacheVersion(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    /**
     * Invalidate cache by incrementing version
     */
    public function invalidateCache(): void
    {
        $newVersion = $this->getCacheVersion() + 1;
        Cache::forever(self::VERSION_KEY, $newVersion);

        // Clear in-memory cache
        $this->settings = null;

        // Update Redis version for Node.js
        try {
            Redis::set('app:settings:version', $newVersion);
            Redis::publish('settings:invalidated', json_encode([
                'version' => $newVersion,
                'timestamp' => now()->toISOString(),
            ]));
        } catch (\Exception $e) {
            Log::warning('Failed to invalidate Redis settings cache', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Publish setting update to Redis for realtime consumers
     */
    private function publishSettingsUpdate(string $key, mixed $value): void
    {
        try {
            Redis::publish('settings:updated', json_encode([
                'key' => $key,
                'value' => $value,
                'timestamp' => now()->toISOString(),
            ]));
        } catch (\Exception $e) {
            Log::warning('Failed to publish setting update', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get settings as flat key-value array (for Node.js)
     */
    public function getAsKeyValueArray(): array
    {
        $settings = $this->getAllSettings();
        $result = [];

        foreach ($settings as $key => $setting) {
            $result[$key] = $setting['typed_value'];
        }

        return $result;
    }

    /**
     * Calculate expected API calls based on current settings
     */
    public function calculateExpectedLoad(): array
    {
        $updateInterval = $this->get('tracking.update_interval_seconds', 3);
        $batchSize = $this->get('tracking.batch_size', 10);

        // Assuming 1000 active drivers
        $activeDrivers = 1000;
        $updatesPerMinutePerDriver = 60 / max(1, $updateInterval);
        $totalUpdatesPerMinute = $activeDrivers * $updatesPerMinutePerDriver;
        $redisOperationsPerMinute = $totalUpdatesPerMinute / max(1, $batchSize);

        return [
            'updates_per_minute_per_driver' => $updatesPerMinutePerDriver,
            'total_updates_per_minute' => $totalUpdatesPerMinute,
            'redis_operations_per_minute' => $redisOperationsPerMinute,
            'assumed_active_drivers' => $activeDrivers,
        ];
    }
}
