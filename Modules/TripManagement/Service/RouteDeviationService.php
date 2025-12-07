<?php

namespace Modules\TripManagement\Service;

use Illuminate\Support\Facades\Log;
use MatanYadaev\EloquentSpatial\Objects\Point;

class RouteDeviationService
{
    /**
     * Default deviation threshold in meters
     */
    private const DEFAULT_THRESHOLD = 100; // 100 meters

    /**
     * Check if current position deviates from the route corridor
     *
     * @param Point $currentPosition Driver's current GPS position
     * @param array $routePolyline Array of [lat, lng] coordinates representing the route
     * @param float|null $thresholdMeters Maximum allowed distance from route (meters)
     * @return bool True if driver has deviated from route
     */
    public function isDeviatedFromRoute(
        Point $currentPosition,
        array $routePolyline,
        ?float $thresholdMeters = null
    ): bool {
        if (empty($routePolyline)) {
            Log::warning('Route polyline is empty, cannot check deviation');
            return false;
        }

        $threshold = $thresholdMeters ?? self::DEFAULT_THRESHOLD;
        $minDistance = $this->getMinimumDistanceToRoute($currentPosition, $routePolyline);

        return $minDistance > $threshold;
    }

    /**
     * Get the minimum distance from current position to any point on the route
     *
     * @param Point $currentPosition
     * @param array $routePolyline Array of [lat, lng] coordinates
     * @return float Distance in meters
     */
    public function getMinimumDistanceToRoute(Point $currentPosition, array $routePolyline): float
    {
        $minDistance = PHP_FLOAT_MAX;

        // Check distance to each segment of the route
        for ($i = 0; $i < count($routePolyline) - 1; $i++) {
            $segmentStart = $routePolyline[$i];
            $segmentEnd = $routePolyline[$i + 1];

            $distance = $this->distanceToLineSegment(
                $currentPosition,
                $segmentStart,
                $segmentEnd
            );

            $minDistance = min($minDistance, $distance);
        }

        return $minDistance;
    }

    /**
     * Calculate the shortest distance from a point to a line segment
     *
     * @param Point $point Current position
     * @param array $lineStart [lat, lng] of segment start
     * @param array $lineEnd [lat, lng] of segment end
     * @return float Distance in meters
     */
    private function distanceToLineSegment(Point $point, array $lineStart, array $lineEnd): float
    {
        $lat = $point->latitude;
        $lng = $point->longitude;

        $lat1 = $lineStart[0];
        $lng1 = $lineStart[1];
        $lat2 = $lineEnd[0];
        $lng2 = $lineEnd[1];

        // Calculate the perpendicular distance from point to line segment
        $A = $lat - $lat1;
        $B = $lng - $lng1;
        $C = $lat2 - $lat1;
        $D = $lng2 - $lng1;

        $dot = $A * $C + $B * $D;
        $lenSq = $C * $C + $D * $D;

        $param = -1;
        if ($lenSq != 0) {
            $param = $dot / $lenSq;
        }

        $xx = 0;
        $yy = 0;

        if ($param < 0) {
            // Closest to start point
            $xx = $lat1;
            $yy = $lng1;
        } elseif ($param > 1) {
            // Closest to end point
            $xx = $lat2;
            $yy = $lng2;
        } else {
            // Closest to some point on the segment
            $xx = $lat1 + $param * $C;
            $yy = $lng1 + $param * $D;
        }

        return $this->haversineDistance($lat, $lng, $xx, $yy);
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     *
     * @param float $lat1 Latitude of point 1
     * @param float $lng1 Longitude of point 1
     * @param float $lat2 Latitude of point 2
     * @param float $lng2 Longitude of point 2
     * @return float Distance in meters
     */
    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Check if driver has reached a waypoint
     *
     * @param Point $currentPosition
     * @param Point $waypoint
     * @param float $thresholdMeters
     * @return bool
     */
    public function hasReachedWaypoint(
        Point $currentPosition,
        Point $waypoint,
        float $thresholdMeters = 50
    ): bool {
        $distance = $this->haversineDistance(
            $currentPosition->latitude,
            $currentPosition->longitude,
            $waypoint->latitude,
            $waypoint->longitude
        );

        return $distance <= $thresholdMeters;
    }

    /**
     * Get suggested threshold based on road type or speed
     *
     * @param string $roadType 'highway', 'urban', 'residential'
     * @return float Threshold in meters
     */
    public function getThresholdByRoadType(string $roadType): float
    {
        return match($roadType) {
            'highway' => 200,
            'urban' => 100,
            'residential' => 50,
            default => self::DEFAULT_THRESHOLD
        };
    }
}
