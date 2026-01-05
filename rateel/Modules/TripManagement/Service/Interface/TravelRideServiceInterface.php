<?php

namespace Modules\TripManagement\Service\Interface;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Modules\TripManagement\Entities\TripRequest;

interface TravelRideServiceInterface
{
    /**
     * Create a new travel ride request
     */
    public function createTravelRequest(Request $request, array $pickupCoordinates): TripRequest;

    /**
     * Get available VIP drivers for travel request
     */
    public function getAvailableVipDrivers(float $lat, float $lng, float $radiusKm = 30): Collection;

    /**
     * Dispatch travel request to VIP drivers
     */
    public function dispatchTravelRequest(string $tripId): bool;

    /**
     * Handle travel request timeout (no driver accepted)
     */
    public function handleTravelTimeout(string $tripId): void;

    /**
     * Get pending travel requests for admin
     */
    public function getPendingTravelRequests(): Collection;

    /**
     * Manually assign a VIP driver to a travel request (admin action)
     */
    public function assignDriverToTravel(string $tripId, string $driverId): bool;

    /**
     * Cancel a travel request
     */
    public function cancelTravelRequest(string $tripId, ?string $reason = null): bool;

    /**
     * Calculate fixed price for travel trip
     */
    public function calculateTravelPrice(
        float $distance,
        string $vehicleCategoryId,
        string $zoneId
    ): float;
}
