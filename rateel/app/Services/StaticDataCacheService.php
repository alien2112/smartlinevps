<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\VehicleManagement\Entities\VehicleCategory;
use Modules\TripManagement\Entities\CancellationReason;
use Modules\ParcelManagement\Entities\ParcelCategory;
use Modules\ParcelManagement\Entities\ParcelWeight;

/**
 * Issue #27 FIX: Static Data Cache Service
 *
 * Caches frequently accessed static data that rarely changes:
 * - Vehicle categories
 * - Cancellation reasons
 * - Parcel categories
 * - Parcel weights
 * - Fare structures
 *
 * This dramatically reduces database queries for data that changes infrequently.
 */
class StaticDataCacheService
{
    // Cache TTLs (in seconds)
    private const TTL_VEHICLE_CATEGORIES = 3600; // 1 hour
    private const TTL_CANCELLATION_REASONS = 86400; // 24 hours
    private const TTL_PARCEL_CATEGORIES = 3600; // 1 hour
    private const TTL_PARCEL_WEIGHTS = 3600; // 1 hour
    private const TTL_BUSINESS_SETTINGS = 1800; // 30 minutes

    // Cache keys
    private const KEY_VEHICLE_CATEGORIES = 'static:vehicle_categories:active';
    private const KEY_CANCELLATION_REASONS = 'static:cancellation_reasons';
    private const KEY_PARCEL_CATEGORIES = 'static:parcel_categories:active';
    private const KEY_PARCEL_WEIGHTS = 'static:parcel_weights';
    private const KEY_BUSINESS_SETTINGS = 'static:business_settings';

    /**
     * Get all active vehicle categories
     */
    public static function getVehicleCategories()
    {
        return Cache::remember(self::KEY_VEHICLE_CATEGORIES, self::TTL_VEHICLE_CATEGORIES, function () {
            return VehicleCategory::where('is_active', 1)
                ->select(['id', 'name', 'type', 'image', 'short_desc', 'description'])
                ->orderBy('name')
                ->get();
        });
    }

    /**
     * Get a specific vehicle category by ID
     */
    public static function getVehicleCategoryById($id)
    {
        $categories = self::getVehicleCategories();
        return $categories->firstWhere('id', $id);
    }

    /**
     * Get all cancellation reasons
     */
    public static function getCancellationReasons(?string $userType = null)
    {
        $cacheKey = self::KEY_CANCELLATION_REASONS . ($userType ? ":{$userType}" : '');

        return Cache::remember($cacheKey, self::TTL_CANCELLATION_REASONS, function () use ($userType) {
            $query = CancellationReason::where('is_active', 1);

            if ($userType) {
                $query->where('user_type', $userType);
            }

            return $query->select(['id', 'title', 'cancellation_type', 'user_type'])
                ->orderBy('title')
                ->get();
        });
    }

    /**
     * Get all parcel categories
     */
    public static function getParcelCategories()
    {
        return Cache::remember(self::KEY_PARCEL_CATEGORIES, self::TTL_PARCEL_CATEGORIES, function () {
            return ParcelCategory::where('is_active', 1)
                ->select(['id', 'name', 'image', 'description'])
                ->orderBy('name')
                ->get();
        });
    }

    /**
     * Get all parcel weights
     */
    public static function getParcelWeights()
    {
        return Cache::remember(self::KEY_PARCEL_WEIGHTS, self::TTL_PARCEL_WEIGHTS, function () {
            return ParcelWeight::select(['id', 'min_weight', 'max_weight'])
                ->orderBy('min_weight')
                ->get();
        });
    }

    /**
     * Find parcel weight by actual weight value
     */
    public static function findParcelWeightByValue(float $weight)
    {
        $weights = self::getParcelWeights();

        foreach ($weights as $pw) {
            if ($weight >= $pw->min_weight && $weight <= $pw->max_weight) {
                return $pw;
            }
        }

        return null;
    }

    /**
     * Get cached business setting
     */
    public static function getBusinessSetting(string $key, $default = null)
    {
        $settings = Cache::remember(self::KEY_BUSINESS_SETTINGS, self::TTL_BUSINESS_SETTINGS, function () {
            return \Modules\BusinessManagement\Entities\BusinessSetting::all()
                ->keyBy('key_name');
        });

        return $settings->get($key)?->value ?? $default;
    }

    /**
     * Clear all static data caches
     */
    public static function clearAll(): void
    {
        Cache::forget(self::KEY_VEHICLE_CATEGORIES);
        Cache::forget(self::KEY_CANCELLATION_REASONS);
        Cache::forget(self::KEY_CANCELLATION_REASONS . ':customer');
        Cache::forget(self::KEY_CANCELLATION_REASONS . ':driver');
        Cache::forget(self::KEY_PARCEL_CATEGORIES);
        Cache::forget(self::KEY_PARCEL_WEIGHTS);
        Cache::forget(self::KEY_BUSINESS_SETTINGS);

        Log::info('Static data caches cleared');
    }

    /**
     * Clear specific cache
     */
    public static function clearVehicleCategories(): void
    {
        Cache::forget(self::KEY_VEHICLE_CATEGORIES);
    }

    public static function clearCancellationReasons(): void
    {
        Cache::forget(self::KEY_CANCELLATION_REASONS);
        Cache::forget(self::KEY_CANCELLATION_REASONS . ':customer');
        Cache::forget(self::KEY_CANCELLATION_REASONS . ':driver');
    }

    public static function clearParcelCategories(): void
    {
        Cache::forget(self::KEY_PARCEL_CATEGORIES);
    }

    public static function clearParcelWeights(): void
    {
        Cache::forget(self::KEY_PARCEL_WEIGHTS);
    }

    public static function clearBusinessSettings(): void
    {
        Cache::forget(self::KEY_BUSINESS_SETTINGS);
    }

    /**
     * Warm all caches
     */
    public static function warmAll(): void
    {
        self::getVehicleCategories();
        self::getCancellationReasons();
        self::getParcelCategories();
        self::getParcelWeights();

        Log::info('Static data caches warmed');
    }
}
