<?php

namespace Modules\TripManagement\Service;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Modules\TripManagement\Entities\TripRequest;
use Modules\TripManagement\Entities\TripRequestCoordinate;

class DynamicReroutingService
{
    private GeoLinkService $geoLinkService;
    private RouteDeviationService $deviationService;

    /**
     * Cache key prefix for last known route
     */
    private const ROUTE_CACHE_PREFIX = 'trip_route_';

    /**
     * Minimum time between reroute requests (seconds)
     */
    private const REROUTE_COOLDOWN = 30;

    public function __construct()
    {
        $this->geoLinkService = new GeoLinkService();
        $this->deviationService = new RouteDeviationService();
    }

    /**
     * Check if driver needs rerouting and return new route if needed
     *
     * @param int $tripRequestId
     * @param Point $currentPosition Driver's current GPS position
     * @param float|null $deviationThreshold Distance threshold in meters
     * @return array|null New route if rerouting needed, null otherwise
     */
    public function checkAndReroute(
        int $tripRequestId,
        Point $currentPosition,
        ?float $deviationThreshold = null
    ): ?array {
        try {
            // Load trip request with coordinates
            $tripRequest = TripRequest::with('coordinate')->find($tripRequestId);

            if (!$tripRequest) {
                throw new \Exception("Trip request not found: {$tripRequestId}");
            }

            // Validate trip is in progress
            if (!in_array($tripRequest->current_status, [ACCEPTED, ONGOING])) {
                Log::info('Trip not in active status, skipping reroute check', [
                    'trip_id' => $tripRequestId,
                    'status' => $tripRequest->current_status
                ]);
                return null;
            }

            // Check cooldown to prevent too frequent rerouting
            if ($this->isInRerouteCooldown($tripRequestId)) {
                Log::debug('Reroute cooldown active, skipping check', ['trip_id' => $tripRequestId]);
                return null;
            }

            // Get current route polyline from cache or database
            $currentRoute = $this->getCurrentRoute($tripRequest);

            if (empty($currentRoute)) {
                Log::warning('No current route found, cannot check deviation', ['trip_id' => $tripRequestId]);
                return null;
            }

            // Check if driver has deviated from route
            $isDeviated = $this->deviationService->isDeviatedFromRoute(
                $currentPosition,
                $currentRoute,
                $deviationThreshold
            );

            if (!$isDeviated) {
                Log::debug('Driver on route, no rerouting needed', ['trip_id' => $tripRequestId]);
                return null;
            }

            Log::info('Route deviation detected, requesting new route', [
                'trip_id' => $tripRequestId,
                'current_position' => [
                    'lat' => $currentPosition->latitude,
                    'lng' => $currentPosition->longitude
                ]
            ]);

            // Request new route from current position
            $newRoute = $this->requestOptimizedRoute($tripRequest, $currentPosition);

            if ($newRoute) {
                // Set cooldown
                $this->setRerouteCooldown($tripRequestId);

                // Cache the new route
                $this->cacheRoute($tripRequestId, $newRoute['polyline']);

                return $newRoute;
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Error in dynamic rerouting', [
                'trip_id' => $tripRequestId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Request new optimized route from current position to destination
     *
     * @param TripRequest $tripRequest
     * @param Point $currentPosition
     * @return array|null
     */
    public function requestOptimizedRoute(
        TripRequest $tripRequest,
        Point $currentPosition
    ): ?array {
        try {
            $coordinate = $tripRequest->coordinate;

            if (!$coordinate) {
                throw new \Exception('Trip coordinates not found');
            }

            // Determine destination based on trip status
            $destination = $this->getNextDestination($tripRequest, $coordinate);

            if (!$destination) {
                Log::warning('Could not determine next destination', ['trip_id' => $tripRequest->id]);
                return null;
            }

            // Build waypoints for remaining stops
            $waypoints = $this->getRemainingWaypoints($tripRequest, $coordinate);

            // Request routes from GeoLink API
            $routes = $this->geoLinkService->getRoutes(
                [$currentPosition->latitude, $currentPosition->longitude],
                [$destination->latitude, $destination->longitude],
                $waypoints,
                true // Request alternatives
            );

            if (empty($routes)) {
                Log::error('GeoLink API returned no routes', ['trip_id' => $tripRequest->id]);
                return null;
            }

            // Filter and select shortest route by duration
            $shortestRoute = $this->geoLinkService->getShortestRouteByDuration($routes);

            if (!$shortestRoute) {
                Log::error('Could not determine shortest route', ['trip_id' => $tripRequest->id]);
                return null;
            }

            Log::info('New optimized route selected', [
                'trip_id' => $tripRequest->id,
                'duration' => $shortestRoute['duration'],
                'distance' => $shortestRoute['distance'],
                'alternatives_count' => count($routes)
            ]);

            // Decode polyline for deviation checking
            $polylineCoordinates = $this->geoLinkService->decodePolyline(
                $shortestRoute['encoded_polyline']
            );

            return [
                'route' => $shortestRoute,
                'polyline' => $polylineCoordinates,
                'alternatives' => $routes,
                'rerouted_at' => now()->toIso8601String(),
            ];

        } catch (\Exception $e) {
            Log::error('Error requesting optimized route', [
                'trip_id' => $tripRequest->id,
                'message' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get the next destination based on trip status
     *
     * @param TripRequest $tripRequest
     * @param TripRequestCoordinate $coordinate
     * @return Point|null
     */
    private function getNextDestination(
        TripRequest $tripRequest,
        TripRequestCoordinate $coordinate
    ): ?Point {
        // If trip is accepted but not started, go to pickup
        if ($tripRequest->current_status === ACCEPTED) {
            return $coordinate->pickup_coordinates;
        }

        // If trip is ongoing, check intermediate points first
        if ($tripRequest->current_status === ONGOING) {
            // Check intermediate waypoint 1
            if ($coordinate->int_coordinate_1 && !$coordinate->is_reached_1) {
                return $coordinate->int_coordinate_1;
            }

            // Check intermediate waypoint 2
            if ($coordinate->int_coordinate_2 && !$coordinate->is_reached_2) {
                return $coordinate->int_coordinate_2;
            }

            // Final destination
            return $coordinate->destination_coordinates;
        }

        return null;
    }

    /**
     * Get remaining waypoints that haven't been reached yet
     *
     * @param TripRequest $tripRequest
     * @param TripRequestCoordinate $coordinate
     * @return array
     */
    private function getRemainingWaypoints(
        TripRequest $tripRequest,
        TripRequestCoordinate $coordinate
    ): array {
        $waypoints = [];

        if ($tripRequest->current_status === ONGOING) {
            // Add unvisited intermediate waypoints
            if ($coordinate->int_coordinate_1 && !$coordinate->is_reached_1) {
                $waypoints[] = [
                    $coordinate->int_coordinate_1->latitude,
                    $coordinate->int_coordinate_1->longitude
                ];
            }

            if ($coordinate->int_coordinate_2 && !$coordinate->is_reached_2) {
                $waypoints[] = [
                    $coordinate->int_coordinate_2->latitude,
                    $coordinate->int_coordinate_2->longitude
                ];
            }
        }

        return $waypoints;
    }

    /**
     * Get current route polyline from cache or encoded polyline
     *
     * @param TripRequest $tripRequest
     * @return array Array of [lat, lng] coordinates
     */
    private function getCurrentRoute(TripRequest $tripRequest): array
    {
        // Try to get from cache first
        $cached = Cache::get(self::ROUTE_CACHE_PREFIX . $tripRequest->id);
        if ($cached) {
            return $cached;
        }

        // Decode from stored polyline
        if ($tripRequest->encoded_polyline) {
            $route = $this->geoLinkService->decodePolyline($tripRequest->encoded_polyline);
            $this->cacheRoute($tripRequest->id, $route);
            return $route;
        }

        return [];
    }

    /**
     * Cache route polyline for quick access
     *
     * @param int $tripRequestId
     * @param array $polyline
     * @return void
     */
    private function cacheRoute(int $tripRequestId, array $polyline): void
    {
        Cache::put(
            self::ROUTE_CACHE_PREFIX . $tripRequestId,
            $polyline,
            now()->addHours(24)
        );
    }

    /**
     * Check if trip is in reroute cooldown period
     *
     * @param int $tripRequestId
     * @return bool
     */
    private function isInRerouteCooldown(int $tripRequestId): bool
    {
        return Cache::has('reroute_cooldown_' . $tripRequestId);
    }

    /**
     * Set reroute cooldown for trip
     *
     * @param int $tripRequestId
     * @return void
     */
    private function setRerouteCooldown(int $tripRequestId): void
    {
        Cache::put(
            'reroute_cooldown_' . $tripRequestId,
            true,
            now()->addSeconds(self::REROUTE_COOLDOWN)
        );
    }

    /**
     * Clear route cache for a trip
     *
     * @param int $tripRequestId
     * @return void
     */
    public function clearRouteCache(int $tripRequestId): void
    {
        Cache::forget(self::ROUTE_CACHE_PREFIX . $tripRequestId);
        Cache::forget('reroute_cooldown_' . $tripRequestId);
    }
}
