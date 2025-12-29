<?php

namespace App\Observers;

use App\Services\AdminDashboardCacheService;
use Modules\ZoneManagement\Entities\Zone;

class ZoneObserver
{
    /**
     * Handle the Zone "created" event.
     */
    public function created(Zone $zone): void
    {
        AdminDashboardCacheService::clearZones();
        AdminDashboardCacheService::clearStatistics();
    }

    /**
     * Handle the Zone "updated" event.
     */
    public function updated(Zone $zone): void
    {
        AdminDashboardCacheService::clearZones();
        AdminDashboardCacheService::clearStatistics();
    }

    /**
     * Handle the Zone "deleted" event.
     */
    public function deleted(Zone $zone): void
    {
        AdminDashboardCacheService::clearZones();
        AdminDashboardCacheService::clearStatistics();
    }

    /**
     * Handle the Zone "restored" event.
     */
    public function restored(Zone $zone): void
    {
        AdminDashboardCacheService::clearZones();
        AdminDashboardCacheService::clearStatistics();
    }

    /**
     * Handle the Zone "force deleted" event.
     */
    public function forceDeleted(Zone $zone): void
    {
        AdminDashboardCacheService::clearZones();
        AdminDashboardCacheService::clearStatistics();
    }
}
