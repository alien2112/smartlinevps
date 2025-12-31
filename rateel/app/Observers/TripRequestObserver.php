<?php

namespace App\Observers;

use Modules\TripManagement\Entities\TripRequest;
use App\Services\AdminDashboardCacheService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Issue #16 FIX: Trip Request Observer with async cache clearing
 *
 * Cache clearing is deferred to after the response is sent to avoid
 * blocking API responses. This is especially important for high-traffic
 * trip update operations.
 */
class TripRequestObserver
{
    /**
     * Handle the TripRequest "created" event.
     */
    public function created(TripRequest $tripRequest): void
    {
        $this->clearDashboardCacheAsync($tripRequest, 'created');
    }

    /**
     * Handle the TripRequest "updated" event.
     */
    public function updated(TripRequest $tripRequest): void
    {
        // Only clear cache if status-related fields changed (performance optimization)
        if ($tripRequest->isDirty(['current_status', 'paid_fare', 'driver_id', 'payment_status'])) {
            $this->clearDashboardCacheAsync($tripRequest, 'updated');
        }
    }

    /**
     * Handle the TripRequest "deleted" event.
     */
    public function deleted(TripRequest $tripRequest): void
    {
        $this->clearDashboardCacheAsync($tripRequest, 'deleted');
    }

    /**
     * Handle the TripRequest "restored" event.
     */
    public function restored(TripRequest $tripRequest): void
    {
        $this->clearDashboardCacheAsync($tripRequest, 'restored');
    }

    /**
     * Handle the TripRequest "force deleted" event.
     */
    public function forceDeleted(TripRequest $tripRequest): void
    {
        $this->clearDashboardCacheAsync($tripRequest, 'forceDeleted');
    }

    /**
     * Issue #16 FIX: Clear caches asynchronously after response
     *
     * Using dispatch()->afterResponse() ensures cache clearing doesn't
     * block the API response while still running within the same request.
     */
    private function clearDashboardCacheAsync(TripRequest $tripRequest, string $event): void
    {
        // Use closure dispatch with afterResponse for deferred execution
        dispatch(function () use ($tripRequest, $event) {
            try {
                AdminDashboardCacheService::clearTripMetrics();
                AdminDashboardCacheService::clearRecentTrips();
                AdminDashboardCacheService::clearLeaderBoards();
                AdminDashboardCacheService::clearStatistics();

                Log::debug('Trip observer cache cleared', [
                    'trip_id' => $tripRequest->id,
                    'event' => $event
                ]);
            } catch (\Exception $e) {
                Log::warning('Trip observer cache clear failed', [
                    'trip_id' => $tripRequest->id,
                    'event' => $event,
                    'error' => $e->getMessage()
                ]);
            }
        })->afterResponse();
    }
}
