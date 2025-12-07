<?php

namespace Modules\TripManagement\Service;

use Illuminate\Support\Facades\Log;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Modules\TripManagement\Entities\TripRequest;
use Modules\UserManagement\Entities\UserLastLocation;

class GpsTrackingService
{
    private DynamicReroutingService $reroutingService;
    private RouteDeviationService $deviationService;

    /**
     * Minimum time between GPS position checks (seconds)
     */
    private const GPS_CHECK_INTERVAL = 10;

    public function __construct()
    {
        $this->reroutingService = new DynamicReroutingService();
        $this->deviationService = new RouteDeviationService();
    }

    /**
     * Process GPS update and check for rerouting if needed
     *
     * @param int $userId Driver user ID
     * @param float $latitude
     * @param float $longitude
     * @return array|null Rerouting data if triggered, null otherwise
     */
    public function processGpsUpdate(int $userId, float $latitude, float $longitude): ?array
    {
        try {
            // Get driver's active trip
            $activeTripId = $this->getActiveTrip($userId);

            if (!$activeTripId) {
                Log::debug('No active trip for driver', ['user_id' => $userId]);
                return null;
            }

            // Create point from GPS coordinates
            $currentPosition = new Point($latitude, $longitude);

            // Check for route deviation and reroute if needed
            $rerouteResult = $this->reroutingService->checkAndReroute(
                $activeTripId,
                $currentPosition
            );

            if ($rerouteResult) {
                Log::info('Automatic rerouting triggered via GPS tracking', [
                    'user_id' => $userId,
                    'trip_id' => $activeTripId,
                    'position' => ['lat' => $latitude, 'lng' => $longitude]
                ]);

                // Update trip with new route
                $this->updateTripRoute($activeTripId, $rerouteResult);

                return [
                    'rerouted' => true,
                    'trip_id' => $activeTripId,
                    'new_route' => $rerouteResult['route'],
                    'message' => 'Route deviation detected. New optimized route provided.'
                ];
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Error processing GPS update for rerouting', [
                'user_id' => $userId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Get active trip for driver
     *
     * @param int $driverId
     * @return int|null Trip request ID
     */
    private function getActiveTrip(int $driverId): ?int
    {
        $trip = TripRequest::where('driver_id', $driverId)
            ->whereIn('current_status', [ACCEPTED, ONGOING])
            ->orderBy('updated_at', 'desc')
            ->first();

        return $trip?->id;
    }

    /**
     * Update trip request with new route
     *
     * @param int $tripId
     * @param array $rerouteResult
     * @return void
     */
    private function updateTripRoute(int $tripId, array $rerouteResult): void
    {
        try {
            $trip = TripRequest::find($tripId);
            if ($trip) {
                $trip->encoded_polyline = $rerouteResult['route']['encoded_polyline'];
                $trip->save();

                Log::info('Trip route updated', [
                    'trip_id' => $tripId,
                    'new_duration' => $rerouteResult['route']['duration'],
                    'new_distance' => $rerouteResult['route']['distance']
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to update trip route', [
                'trip_id' => $tripId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get current deviation distance from route
     *
     * @param int $tripId
     * @param Point $currentPosition
     * @return float|null Distance in meters, null on error
     */
    public function getCurrentDeviation(int $tripId, Point $currentPosition): ?float
    {
        try {
            $trip = TripRequest::find($tripId);
            if (!$trip || !$trip->encoded_polyline) {
                return null;
            }

            $geoLinkService = new GeoLinkService();
            $routePolyline = $geoLinkService->decodePolyline($trip->encoded_polyline);

            return $this->deviationService->getMinimumDistanceToRoute(
                $currentPosition,
                $routePolyline
            );

        } catch (\Exception $e) {
            Log::error('Error calculating current deviation', [
                'trip_id' => $tripId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Monitor waypoint arrival and trigger route updates
     *
     * @param int $tripId
     * @param Point $currentPosition
     * @return bool True if waypoint reached
     */
    public function checkWaypointArrival(int $tripId, Point $currentPosition): bool
    {
        try {
            $trip = TripRequest::with('coordinate')->find($tripId);
            if (!$trip || !$trip->coordinate) {
                return false;
            }

            $coordinate = $trip->coordinate;

            // Check intermediate waypoint 1
            if ($coordinate->int_coordinate_1 && !$coordinate->is_reached_1) {
                $reached = $this->deviationService->hasReachedWaypoint(
                    $currentPosition,
                    $coordinate->int_coordinate_1,
                    50 // 50 meters threshold
                );

                if ($reached) {
                    $coordinate->is_reached_1 = true;
                    $coordinate->save();

                    Log::info('Driver reached intermediate waypoint 1', ['trip_id' => $tripId]);

                    // Request new route to next destination
                    $this->reroutingService->requestOptimizedRoute($trip, $currentPosition);

                    return true;
                }
            }

            // Check intermediate waypoint 2
            if ($coordinate->int_coordinate_2 && !$coordinate->is_reached_2) {
                $reached = $this->deviationService->hasReachedWaypoint(
                    $currentPosition,
                    $coordinate->int_coordinate_2,
                    50
                );

                if ($reached) {
                    $coordinate->is_reached_2 = true;
                    $coordinate->save();

                    Log::info('Driver reached intermediate waypoint 2', ['trip_id' => $tripId]);

                    // Request new route to next destination
                    $this->reroutingService->requestOptimizedRoute($trip, $currentPosition);

                    return true;
                }
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Error checking waypoint arrival', [
                'trip_id' => $tripId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
