<?php

namespace App\Observers;

use App\Services\AdminDashboardCacheService;
use App\Services\ZoneCacheService;
use Modules\ZoneManagement\Entities\Zone;

class ZoneObserver
{
    /**
     * Handle the Zone "created" event.
     */
    public function created(Zone $zone): void
    {
        $this->clearCaches();
    }

    /**
     * Handle the Zone "updated" event.
     */
    public function updated(Zone $zone): void
    {
        $this->clearCaches();
    }

    /**
     * Handle the Zone "deleted" event.
     */
    public function deleted(Zone $zone): void
    {
        $this->clearCaches();
    }

    /**
     * Handle the Zone "restored" event.
     */
    public function restored(Zone $zone): void
    {
        $this->clearCaches();
    }

    /**
     * Handle the Zone "force deleted" event.
     */
    public function forceDeleted(Zone $zone): void
    {
        $this->clearCaches();
    }

    /**
     * Clear all zone-related caches
     * Issue #23 FIX: Include ZoneCacheService
     */
    private function clearCaches(): void
    {
        AdminDashboardCacheService::clearZones();
        AdminDashboardCacheService::clearStatistics();

        // Issue #23 FIX: Clear zone cache for API lookups
        app(ZoneCacheService::class)->clearCache();
    }
}
