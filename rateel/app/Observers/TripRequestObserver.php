<?php

namespace App\Observers;

use Modules\TripManagement\Entities\TripRequest;
use App\Services\AdminDashboardCacheService;

class TripRequestObserver
{
    /**
     * Handle the TripRequest "created" event.
     */
    public function created(TripRequest $tripRequest): void
    {
        $this->clearDashboardCache();
    }

    /**
     * Handle the TripRequest "updated" event.
     */
    public function updated(TripRequest $tripRequest): void
    {
        $this->clearDashboardCache();
    }

    /**
     * Handle the TripRequest "deleted" event.
     */
    public function deleted(TripRequest $tripRequest): void
    {
        $this->clearDashboardCache();
    }

    /**
     * Handle the TripRequest "restored" event.
     */
    public function restored(TripRequest $tripRequest): void
    {
        $this->clearDashboardCache();
    }

    /**
     * Handle the TripRequest "force deleted" event.
     */
    public function forceDeleted(TripRequest $tripRequest): void
    {
        $this->clearDashboardCache();
    }

    /**
     * Clear relevant dashboard caches
     */
    private function clearDashboardCache(): void
    {
        AdminDashboardCacheService::clearTripMetrics();
        AdminDashboardCacheService::clearRecentTrips();
        AdminDashboardCacheService::clearLeaderBoards();
        AdminDashboardCacheService::clearStatistics();
    }
}
