<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cached Route Service
 * 
 * Wraps the route API calls with caching to reduce external API calls
 * and improve response times for fare estimation.
 */
class CachedRouteService
{
    private const DEFAULT_TIMEOUT = 15;
    private const MAX_TIMEOUT = 30;

    /**
     * Get routes with caching
     *
     * @param array $originCoordinates [lat, lng]
     * @param array $destinationCoordinates [lat, lng]
     * @param array $intermediateCoordinates
     * @param array $drivingMode
     * @return array
     */
    public function getRoutes(
        array $originCoordinates,
        array $destinationCoordinates,
        array $intermediateCoordinates = [],
        array $drivingMode = ["DRIVE"]
    ): array {
        // Check cache first
        $cachedRoute = PerformanceCache::getRoute(
            $originCoordinates,
            $destinationCoordinates,
            $intermediateCoordinates
        );
        
        if ($cachedRoute !== null) {
            Log::debug('CachedRouteService: Cache hit for route');
            return $cachedRoute;
        }
        
        // Fetch from API
        $route = $this->fetchRouteFromApi(
            $originCoordinates,
            $destinationCoordinates,
            $intermediateCoordinates,
            $drivingMode
        );
        
        // Cache successful responses
        if (is_array($route) && isset($route[0]['status']) && $route[0]['status'] === 'OK') {
            PerformanceCache::setRoute(
                $originCoordinates,
                $destinationCoordinates,
                $intermediateCoordinates,
                $route
            );
        }
        
        return $route;
    }

    /**
     * Fetch route from external API
     */
    private function fetchRouteFromApi(
        array $originCoordinates,
        array $destinationCoordinates,
        array $intermediateCoordinates,
        array $drivingMode
    ): array {
        $apiKey = businessConfig(GOOGLE_MAP_API)?->value['map_api_key_server'] ?? '';
        
        if (empty($apiKey)) {
            $errorMessage = 'GeoLink API key not configured';
            return [
                0 => ['status' => 'ERROR', 'error_detail' => $errorMessage],
                1 => ['status' => 'ERROR', 'error_detail' => $errorMessage]
            ];
        }

        // Build GeoLink API parameters
        $params = [
            'origin_latitude' => $originCoordinates[0],
            'origin_longitude' => $originCoordinates[1],
            'destination_latitude' => $destinationCoordinates[0],
            'destination_longitude' => $destinationCoordinates[1],
            'key' => $apiKey,
            'alternatives' => 'true'
        ];
        
        // Add waypoints if provided
        if (!empty($intermediateCoordinates) && !is_null($intermediateCoordinates[0][0] ?? null)) {
            $waypoints = [];
            foreach ($intermediateCoordinates as $wp) {
                if (isset($wp[0], $wp[1]) && !is_null($wp[0])) {
                    $waypoints[] = $wp[0] . ',' . $wp[1];
                }
            }
            if (!empty($waypoints)) {
                $params['waypoints'] = implode('|', $waypoints);
            }
        }

        try {
            $response = Http::timeout(self::DEFAULT_TIMEOUT)
                ->retry(2, 500)
                ->get(MAP_API_BASE_URI . '/api/v2/directions', $params);
            
            if ($response->successful()) {
                return $this->parseRouteResponse($response->json());
            } else {
                $errorMessage = 'GeoLink API request failed with status: ' . $response->status();
                if ($response->json()) {
                    $errorMessage = $response->json()['message'] ?? $errorMessage;
                }
                
                Log::warning('CachedRouteService: API request failed', [
                    'status' => $response->status(),
                    'error' => $errorMessage
                ]);
                
                return [
                    0 => ['status' => 'ERROR', 'error_detail' => $errorMessage],
                    1 => ['status' => 'ERROR', 'error_detail' => $errorMessage]
                ];
            }
        } catch (\Exception $e) {
            Log::error('CachedRouteService: Exception during API call', [
                'error' => $e->getMessage()
            ]);
            
            return [
                0 => ['status' => 'ERROR', 'error_detail' => 'Route API unavailable: ' . $e->getMessage()],
                1 => ['status' => 'ERROR', 'error_detail' => 'Route API unavailable: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Parse route API response
     */
    private function parseRouteResponse(array $result): array
    {
        $routes = $this->extractGeoLinkRoutes($result);
        
        if (empty($routes)) {
            $errorMessage = $result['message'] ?? 'No routes found between the specified locations';
            return [
                0 => ['status' => 'ERROR', 'error_detail' => $errorMessage],
                1 => ['status' => 'ERROR', 'error_detail' => $errorMessage]
            ];
        }

        $route = $routes[0];

        $distanceMeters = 0;
        if (isset($route['distance']['meters'])) {
            $distanceMeters = (float) $route['distance']['meters'];
        } elseif (isset($route['distance'])) {
            $distanceMeters = (float) $route['distance'];
        }

        $durationSeconds = 0;
        if (isset($route['duration']['seconds'])) {
            $durationSeconds = (float) $route['duration']['seconds'];
        } elseif (isset($route['duration'])) {
            $durationSeconds = (float) $route['duration'];
        }

        $encodedPolyline = $route['polyline'] ?? ($route['overview_polyline'] ?? '');
        if (empty($encodedPolyline) && isset($route['waypoints']) && is_array($route['waypoints'])) {
            $encodedPolyline = $this->encodePolyline($route['waypoints']);
        }

        $durationInTraffic = $durationSeconds;
        $convert_to_bike = 1.2;

        $responses = [];
        
        $responses[0] = [
            'distance' => (double) str_replace(',', '', number_format(($distanceMeters ?? 0) / 1000, 2)),
            'distance_text' => number_format(($distanceMeters ?? 0) / 1000, 2) . ' km',
            'duration' => number_format((($durationSeconds / 60) / $convert_to_bike), 2) . ' min',
            'duration_sec' => (int) ($durationSeconds / $convert_to_bike),
            'duration_in_traffic' => number_format((($durationInTraffic / 60) / $convert_to_bike), 2) . ' min',
            'duration_in_traffic_sec' => (int) ($durationInTraffic / $convert_to_bike),
            'status' => "OK",
            'drive_mode' => 'TWO_WHEELER',
            'encoded_polyline' => $encodedPolyline,
        ];

        $responses[1] = [
            'distance' => (double) str_replace(',', '', number_format(($distanceMeters ?? 0) / 1000, 2)),
            'distance_text' => number_format(($distanceMeters ?? 0) / 1000, 2) . ' km',
            'duration' => number_format(($durationSeconds / 60), 2) . ' min',
            'duration_sec' => (int) $durationSeconds,
            'duration_in_traffic' => number_format(($durationInTraffic / 60), 2) . ' min',
            'duration_in_traffic_sec' => (int) $durationInTraffic,
            'status' => "OK",
            'drive_mode' => 'DRIVE',
            'encoded_polyline' => $encodedPolyline,
        ];

        return $responses;
    }

    /**
     * Extract routes from GeoLink API response
     */
    private function extractGeoLinkRoutes(array $result): array
    {
        if (!is_array($result)) {
            return [];
        }

        if (isset($result['data']['routes']) && is_array($result['data']['routes'])) {
            return $result['data']['routes'];
        }
        if (isset($result['routes']) && is_array($result['routes'])) {
            return $result['routes'];
        }
        if (isset($result['data']) && is_array($result['data'])) {
            return $result['data'];
        }

        return [];
    }

    /**
     * Encode polyline from waypoints
     */
    private function encodePolyline(array $points): string
    {
        $result = '';
        $prevLat = 0;
        $prevLng = 0;

        $encodeSigned = function (int $value) use (&$result) {
            $value = ($value < 0) ? ~($value << 1) : ($value << 1);
            while ($value >= 0x20) {
                $result .= chr((0x20 | ($value & 0x1f)) + 63);
                $value >>= 5;
            }
            $result .= chr($value + 63);
        };

        foreach ($points as $point) {
            if (!is_array($point) || count($point) < 2) {
                continue;
            }
            $lat = (int) round(((float) $point[0]) * 1e5);
            $lng = (int) round(((float) $point[1]) * 1e5);

            $encodeSigned($lat - $prevLat);
            $encodeSigned($lng - $prevLng);

            $prevLat = $lat;
            $prevLng = $lng;
        }

        return $result;
    }

    /**
     * Calculate straight-line distance between two points (Haversine)
     * Used as a fallback when API is unavailable
     *
     * @param array $origin [lat, lng]
     * @param array $destination [lat, lng]
     * @return float Distance in kilometers
     */
    public function calculateStraightLineDistance(array $origin, array $destination): float
    {
        $earthRadius = 6371; // km
        
        $latFrom = deg2rad($origin[0]);
        $lonFrom = deg2rad($origin[1]);
        $latTo = deg2rad($destination[0]);
        $lonTo = deg2rad($destination[1]);
        
        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;
        
        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)
        ));
        
        return $angle * $earthRadius;
    }
}
