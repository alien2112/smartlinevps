<?php

namespace Modules\TripManagement\Service;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeoLinkService
{
    /**
     * Get routes from GeoLink v2 API
     *
     * @param array $originCoordinates [latitude, longitude]
     * @param array $destinationCoordinates [latitude, longitude]
     * @param array $intermediateCoordinates Array of waypoints [[lat, lng], [lat, lng]]
     * @param bool $alternatives Request alternative routes
     * @return array|null
     */
    public function getRoutes(
        array $originCoordinates,
        array $destinationCoordinates,
        array $intermediateCoordinates = [],
        bool $alternatives = true
    ): ?array {
        try {
            $apiKey = $this->getApiKey();

            if (empty($apiKey)) {
                Log::error('GeoLink API key not configured');
                return null;
            }

            // Build request parameters
            $params = [
                'origin_latitude' => $originCoordinates[0],
                'origin_longitude' => $originCoordinates[1],
                'destination_latitude' => $destinationCoordinates[0],
                'destination_longitude' => $destinationCoordinates[1],
                'key' => $apiKey,
                'alternatives' => $alternatives ? 'true' : 'false'
            ];

            // Add waypoints if provided
            if (!empty($intermediateCoordinates)) {
                $waypoints = [];
                foreach ($intermediateCoordinates as $coord) {
                    if (isset($coord[0], $coord[1]) && !is_null($coord[0])) {
                        $waypoints[] = $coord[0] . ',' . $coord[1];
                    }
                }
                if (!empty($waypoints)) {
                    $params['waypoints'] = implode('|', $waypoints);
                }
            }

            // Make API request
            $response = Http::timeout(30)->get(MAP_API_BASE_URI . '/api/v2/directions', $params);

            if (!$response->successful()) {
                Log::error('GeoLink API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $result = $response->json();

            // Check if we have valid routes
            if (!isset($result['data']['routes']) || empty($result['data']['routes'])) {
                Log::warning('GeoLink API returned no routes', ['response' => $result]);
                return null;
            }

            return $this->formatRoutes($result['data']['routes']);

        } catch (\Exception $e) {
            Log::error('GeoLink API exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Get the shortest route by duration from multiple routes
     *
     * @param array $routes Array of routes from getRoutes()
     * @return array|null
     */
    public function getShortestRouteByDuration(array $routes): ?array
    {
        if (empty($routes)) {
            return null;
        }

        usort($routes, function($a, $b) {
            return ($a['duration_sec'] ?? PHP_INT_MAX) <=> ($b['duration_sec'] ?? PHP_INT_MAX);
        });

        return $routes[0];
    }

    /**
     * Format routes from GeoLink API response to app format
     *
     * @param array $geoLinkRoutes
     * @return array
     */
    private function formatRoutes(array $geoLinkRoutes): array
    {
        $formattedRoutes = [];

        foreach ($geoLinkRoutes as $index => $route) {
            $distance = $route['distance'] ?? 0; // meters
            $duration = $route['duration'] ?? 0; // seconds
            $polyline = $route['polyline'] ?? '';

            $formattedRoutes[] = [
                'route_index' => $index,
                'distance' => (double) number_format($distance / 1000, 2), // Convert to km
                'distance_text' => number_format($distance / 1000, 2) . ' km',
                'duration' => number_format($duration / 60, 2) . ' min',
                'duration_sec' => (int) $duration,
                'status' => 'OK',
                'encoded_polyline' => $polyline,
                'legs' => $route['legs'] ?? [],
            ];
        }

        return $formattedRoutes;
    }

    /**
     * Get API key from business configuration
     *
     * @return string
     */
    private function getApiKey(): string
    {
        return businessConfig(GOOGLE_MAP_API)?->value['map_api_key_server'] ?? '';
    }

    /**
     * Decode polyline to array of coordinates
     *
     * @param string $encoded
     * @return array Array of [lat, lng] coordinates
     */
    public function decodePolyline(string $encoded): array
    {
        $len = strlen($encoded);
        $index = 0;
        $points = [];
        $lat = 0;
        $lng = 0;

        while ($index < $len) {
            $b = 0;
            $shift = 0;
            $result = 0;

            do {
                $b = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b >= 0x20);

            $dlat = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $lat += $dlat;

            $shift = 0;
            $result = 0;

            do {
                $b = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b >= 0x20);

            $dlng = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $lng += $dlng;

            $points[] = [$lat * 1e-5, $lng * 1e-5];
        }

        return $points;
    }
}
