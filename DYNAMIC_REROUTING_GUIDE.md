# Dynamic Rerouting Implementation Guide

## Overview
This document describes the dynamic rerouting feature that automatically detects when a driver deviates from the defined route and provides an optimized alternative using the **GeoLink v2 API**.

## Features

### Core Functionality
- **Real-time Route Deviation Detection**: Continuously monitors driver GPS position against the planned route
- **Automatic Rerouting**: Triggers new route requests when deviation exceeds threshold
- **Shortest Route Selection**: Automatically filters all returned routes and selects the one with the shortest ETA (duration)
- **Multiple Route Alternatives**: Provides alternative routes for driver selection
- **Waypoint Management**: Handles intermediate stops and updates routes accordingly
- **Error Handling**: Comprehensive error handling for API failures, GPS unavailability, and missing routes

### Technical Features
- **Cooldown Mechanism**: Prevents excessive API calls (30-second minimum between reroutes)
- **Route Caching**: Caches decoded polylines for fast deviation checking
- **Production-Ready**: Optimized, reusable, and fully logged
- **RESTful API**: Clean API endpoints for mobile apps

## Architecture

### Services Created

```
Modules/TripManagement/Service/
├── GeoLinkService.php              # GeoLink v2 API integration
├── RouteDeviationService.php       # Route deviation detection logic
├── DynamicReroutingService.php     # Main rerouting orchestration
└── GpsTrackingService.php          # GPS tracking integration
```

### Controller

```
Modules/TripManagement/Http/Controllers/Api/New/Driver/
└── DynamicReroutingController.php  # API endpoints
```

## API Endpoints

### 1. Check Deviation (Automatic)
```http
POST /api/v1/driver/rerouting/check-deviation
Authorization: Bearer {token}
Content-Type: application/json

{
  "trip_request_id": 123,
  "latitude": 30.0444,
  "longitude": 31.2357,
  "deviation_threshold": 100
}
```

**Response (No Deviation):**
```json
{
  "status": "success",
  "message": "Driver is on route",
  "data": {
    "reroute_needed": false,
    "current_position": {
      "latitude": 30.0444,
      "longitude": 31.2357
    }
  }
}
```

**Response (Deviation Detected):**
```json
{
  "status": "success",
  "message": "New optimized route provided",
  "data": {
    "reroute_needed": true,
    "route": {
      "route_index": 0,
      "distance": 5.23,
      "distance_text": "5.23 km",
      "duration": "12.50 min",
      "duration_sec": 750,
      "status": "OK",
      "encoded_polyline": "encoded_string_here",
      "legs": [...]
    },
    "alternatives_count": 3,
    "rerouted_at": "2025-12-07T10:30:00Z"
  }
}
```

### 2. Request New Route (Manual)
```http
POST /api/v1/driver/rerouting/request-new-route
Authorization: Bearer {token}
Content-Type: application/json

{
  "trip_request_id": 123,
  "latitude": 30.0444,
  "longitude": 31.2357
}
```

**Response:**
```json
{
  "status": "success",
  "message": "New route generated successfully",
  "data": {
    "route": {
      "distance": 5.23,
      "duration": "12.50 min",
      "duration_sec": 750,
      "encoded_polyline": "..."
    },
    "alternatives": [
      {
        "route_index": 0,
        "distance": 5.23,
        "duration_sec": 750
      },
      {
        "route_index": 1,
        "distance": 5.45,
        "duration_sec": 780
      }
    ],
    "rerouted_at": "2025-12-07T10:30:00Z"
  }
}
```

### 3. Get Route Alternatives
```http
POST /api/v1/driver/rerouting/get-alternatives
Authorization: Bearer {token}
Content-Type: application/json

{
  "trip_request_id": 123,
  "latitude": 30.0444,
  "longitude": 31.2357
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Route alternatives retrieved successfully",
  "data": {
    "recommended_route": {
      "distance": 5.23,
      "duration": "12.50 min",
      "duration_sec": 750
    },
    "all_routes": [...],
    "routes_count": 3
  }
}
```

## Usage Examples

### PHP Service Usage

#### Example 1: Check for Deviation
```php
use Modules\TripManagement\Service\DynamicReroutingService;
use MatanYadaev\EloquentSpatial\Objects\Point;

$reroutingService = new DynamicReroutingService();

$tripId = 123;
$currentPosition = new Point(30.0444, 31.2357);
$deviationThreshold = 100; // meters

$result = $reroutingService->checkAndReroute(
    $tripId,
    $currentPosition,
    $deviationThreshold
);

if ($result) {
    // Driver deviated - new route provided
    $newRoute = $result['route'];
    $alternatives = $result['alternatives'];

    echo "New route: " . $newRoute['duration'] . "\n";
    echo "Distance: " . $newRoute['distance'] . " km\n";
} else {
    // Driver is on route
    echo "Driver on route\n";
}
```

#### Example 2: GPS Tracking Integration
```php
use Modules\TripManagement\Service\GpsTrackingService;

$gpsService = new GpsTrackingService();

// Called whenever driver GPS updates
$result = $gpsService->processGpsUpdate(
    $driverId,
    $latitude,
    $longitude
);

if ($result && $result['rerouted']) {
    // Send notification to driver
    event(new DriverReroutedEvent($result));
}
```

#### Example 3: Manual Route Request
```php
use Modules\TripManagement\Service\DynamicReroutingService;
use Modules\TripManagement\Entities\TripRequest;
use MatanYadaev\EloquentSpatial\Objects\Point;

$reroutingService = new DynamicReroutingService();

$trip = TripRequest::with('coordinate')->find($tripId);
$currentPosition = new Point(30.0444, 31.2357);

$result = $reroutingService->requestOptimizedRoute(
    $trip,
    $currentPosition
);

if ($result) {
    // Display alternatives to driver
    foreach ($result['alternatives'] as $route) {
        echo "Route {$route['route_index']}: ";
        echo "{$route['duration']} ({$route['distance']} km)\n";
    }
}
```

### JavaScript/Mobile App Integration

#### Example: Continuous GPS Monitoring
```javascript
// In your driver app GPS tracking code
function onGPSUpdate(latitude, longitude) {
    const tripId = getCurrentTripId();

    // Check for route deviation every GPS update
    fetch('/api/v1/driver/rerouting/check-deviation', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${getAuthToken()}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            trip_request_id: tripId,
            latitude: latitude,
            longitude: longitude,
            deviation_threshold: 100 // 100 meters
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.data.reroute_needed) {
            // Show new route to driver
            updateMapWithNewRoute(data.data.route);
            showNotification('New route calculated');
        }
    })
    .catch(error => {
        console.error('Rerouting check failed:', error);
    });
}

// Call this every 10-15 seconds or on significant GPS updates
setInterval(() => {
    const position = getCurrentGPSPosition();
    onGPSUpdate(position.lat, position.lng);
}, 15000); // Every 15 seconds
```

## Configuration

### Deviation Threshold
The deviation threshold determines how far (in meters) the driver can be from the route before triggering rerouting:

- **Highway**: 200 meters (recommended)
- **Urban**: 100 meters (default)
- **Residential**: 50 meters

```php
// Configure based on road type
use Modules\TripManagement\Service\RouteDeviationService;

$deviationService = new RouteDeviationService();
$threshold = $deviationService->getThresholdByRoadType('highway'); // 200m
```

### Cooldown Period
Rerouting cooldown prevents excessive API calls:
- **Default**: 30 seconds between reroute requests
- **Configurable** in `DynamicReroutingService::REROUTE_COOLDOWN`

### GeoLink API Key
The system uses the existing GeoLink API key configured in business settings:
- **Location**: Admin Panel → Business Settings → Third Party → Map API
- **Key Name**: `map_api_key_server`

## How It Works

### 1. Route Deviation Detection

The system uses the **Haversine formula** and **point-to-line-segment distance** calculation:

1. **GPS Update**: Driver's app sends current position
2. **Polyline Decoding**: Current route is decoded into coordinate points
3. **Distance Calculation**: Minimum distance to any route segment is calculated
4. **Threshold Check**: If distance > threshold → deviation detected

### 2. Route Selection Algorithm

When deviation is detected:

1. **Request Multiple Routes**: GeoLink v2 returns alternative routes
2. **Filter by Duration**: All routes are sorted by `duration_sec` (shortest first)
3. **Select Winner**: Route with minimum duration is chosen
4. **Update Trip**: New route polyline is saved to database

### 3. Waypoint Handling

The system automatically manages intermediate waypoints:

1. **Ongoing Trip**: Routes through unvisited waypoints to destination
2. **Waypoint Reached**: Detected when driver within 50m of waypoint
3. **Auto-Reroute**: New route calculated to next destination

## Error Handling

### API Failures
```php
// GeoLink API failure
if ($routes === null) {
    Log::error('GeoLink API returned no routes');
    return null; // Fallback: keep current route
}
```

### GPS Not Available
```php
// Client should handle GPS errors
{
    "status": "error",
    "message": "GPS coordinates required"
}
```

### Route Missing
```php
// No encoded polyline in trip
if (empty($currentRoute)) {
    Log::warning('No current route found');
    return null; // Cannot check deviation
}
```

### Unauthorized Access
```php
// Driver doesn't own trip
if ($tripRequest->driver_id !== auth()->id()) {
    return response()->json([
        'status': 'error',
        'message': 'Unauthorized access to trip'
    ], 403);
}
```

## Performance Considerations

### Caching Strategy
- **Route Polylines**: Cached for 24 hours
- **Cooldown Status**: Cached for 30 seconds
- **Cache Keys**: `trip_route_{id}`, `reroute_cooldown_{id}`

### API Rate Limiting
- **Cooldown**: 30-second minimum between requests per trip
- **GeoLink API**: Respects API provider rate limits
- **Timeout**: 30-second HTTP timeout

### Database Optimization
- **Indexed Columns**: `trip_requests.id`, `driver_id`, `current_status`
- **Eager Loading**: `TripRequest::with('coordinate')` to reduce queries
- **Selective Updates**: Only `encoded_polyline` updated on reroute

## Testing

### Unit Test Example
```php
use Tests\TestCase;
use Modules\TripManagement\Service\RouteDeviationService;
use MatanYadaev\EloquentSpatial\Objects\Point;

class RouteDeviationTest extends TestCase
{
    public function test_detects_deviation_when_far_from_route()
    {
        $service = new RouteDeviationService();

        $currentPosition = new Point(30.0500, 31.2400);
        $routePolyline = [
            [30.0444, 31.2357],
            [30.0450, 31.2365],
            [30.0460, 31.2380]
        ];

        $isDeviated = $service->isDeviatedFromRoute(
            $currentPosition,
            $routePolyline,
            100 // 100m threshold
        );

        $this->assertTrue($isDeviated);
    }
}
```

### Manual Testing

1. **Start a trip** with known route
2. **Simulate GPS updates** far from route
3. **Call check-deviation endpoint**
4. **Verify** new route is returned
5. **Check logs** for proper error handling

## Logging

All rerouting events are logged:

```
[INFO] Route deviation detected, requesting new route
       trip_id: 123
       current_position: {lat: 30.0444, lng: 31.2357}

[INFO] New optimized route selected
       trip_id: 123
       duration: 12.50 min
       distance: 5.23 km
       alternatives_count: 3

[ERROR] GeoLink API request failed
        status: 500
        body: {...}
```

## Troubleshooting

### Issue: Rerouting not triggering
- **Check**: GPS updates are being sent
- **Check**: Trip status is `accepted` or `ongoing`
- **Check**: Cooldown period has expired
- **Check**: GeoLink API key is configured

### Issue: No routes returned
- **Check**: GeoLink API connectivity
- **Check**: Valid coordinates provided
- **Check**: API key is valid and not rate-limited

### Issue: Wrong route selected
- **Verify**: `duration_sec` field in API response
- **Check**: Route alternatives are being returned
- **Debug**: Log all routes before selection

## Migration from Existing System

If you have existing trips using old routing:

1. **Update `getRoutes()` helper** to use GeoLink v2 (already done in migration)
2. **Re-encode existing polylines** if needed
3. **Test with existing trip data** to ensure compatibility

## Future Enhancements

Potential improvements:
- **Traffic-aware routing**: Use real-time traffic data
- **Driver preferences**: Allow drivers to choose preferred routes
- **Route history**: Track all reroute events for analytics
- **Predictive rerouting**: Suggest reroutes before deviation
- **WebSocket notifications**: Real-time push to driver app

## Support

For issues or questions:
- **Logs**: Check `storage/logs/laravel.log`
- **API Docs**: See GeoLink v2 documentation at https://geolink-eg.com/docs
- **Code**: Review service files in `Modules/TripManagement/Service/`

---

**Last Updated**: December 7, 2025
**Version**: 1.0.0
**API**: GeoLink v2
