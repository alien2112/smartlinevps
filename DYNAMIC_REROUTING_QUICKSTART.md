# Dynamic Rerouting - Quick Start Guide

## Installation Complete ✓

The dynamic rerouting feature has been successfully implemented in your Laravel application using **GeoLink v2 API**.

## What Was Created

### Services (4 files)
```
Modules/TripManagement/Service/
├── GeoLinkService.php              ✓ GeoLink v2 API integration
├── RouteDeviationService.php       ✓ Deviation detection (Haversine + geometry)
├── DynamicReroutingService.php     ✓ Main rerouting logic
└── GpsTrackingService.php          ✓ GPS monitoring integration
```

### Controller
```
Modules/TripManagement/Http/Controllers/Api/New/Driver/
└── DynamicReroutingController.php  ✓ 3 API endpoints
```

### API Routes
```
/api/v1/driver/rerouting/check-deviation       ✓ Auto-check for deviation
/api/v1/driver/rerouting/request-new-route     ✓ Manual reroute request
/api/v1/driver/rerouting/get-alternatives      ✓ Get all route options
```

## Quick Integration Steps

### Step 1: Mobile App Integration (15 minutes)

Add this to your driver app's GPS tracking code:

```javascript
// Example: React Native / JavaScript
class TripNavigation {
    startGPSMonitoring(tripId) {
        // Update GPS every 10-15 seconds during active trip
        this.gpsInterval = setInterval(() => {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.checkForRerouting(
                        tripId,
                        position.coords.latitude,
                        position.coords.longitude
                    );
                },
                (error) => console.error('GPS error:', error),
                { enableHighAccuracy: true }
            );
        }, 15000); // Every 15 seconds
    }

    async checkForRerouting(tripId, lat, lng) {
        try {
            const response = await fetch(
                `${API_BASE_URL}/api/v1/driver/rerouting/check-deviation`,
                {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${this.authToken}`,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        trip_request_id: tripId,
                        latitude: lat,
                        longitude: lng,
                        deviation_threshold: 100 // 100 meters
                    })
                }
            );

            const data = await response.json();

            if (data.data.reroute_needed) {
                // Show alert to driver
                this.showRerouteNotification();

                // Update map with new route
                this.updateRoute(data.data.route.encoded_polyline);

                // Update ETA
                this.updateETA(data.data.route.duration);
            }
        } catch (error) {
            console.error('Rerouting check failed:', error);
            // Continue with existing route
        }
    }

    showRerouteNotification() {
        Alert.alert(
            'New Route',
            'A faster route has been found. Your map has been updated.',
            [{ text: 'OK' }]
        );
    }

    stopGPSMonitoring() {
        if (this.gpsInterval) {
            clearInterval(this.gpsInterval);
        }
    }
}

// Usage
const navigation = new TripNavigation();
navigation.startGPSMonitoring(tripId);
```

### Step 2: Backend Event Integration (Optional)

If you want to trigger rerouting from backend events:

```php
// In your existing GPS tracking endpoint or event handler
use Modules\TripManagement\Service\GpsTrackingService;

class DriverLocationController extends Controller
{
    public function updateLocation(Request $request)
    {
        $driverId = auth()->id();
        $lat = $request->input('latitude');
        $lng = $request->input('longitude');

        // ... existing location update code ...

        // Check for automatic rerouting
        $gpsService = new GpsTrackingService();
        $rerouteResult = $gpsService->processGpsUpdate($driverId, $lat, $lng);

        if ($rerouteResult && $rerouteResult['rerouted']) {
            // Broadcast to driver via WebSocket/Pusher
            broadcast(new RouteUpdatedEvent(
                $driverId,
                $rerouteResult['new_route']
            ));
        }

        return response()->json(['status' => 'success']);
    }
}
```

### Step 3: Manual Reroute Button (Optional)

Add a "Find Better Route" button in your driver app:

```javascript
async requestNewRoute(tripId, currentLat, currentLng) {
    try {
        const response = await fetch(
            `${API_BASE_URL}/api/v1/driver/rerouting/request-new-route`,
            {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.authToken}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    trip_request_id: tripId,
                    latitude: currentLat,
                    longitude: currentLng
                })
            }
        );

        const data = await response.json();

        if (data.status === 'success') {
            // Show alternatives to driver
            this.showRouteAlternatives(data.data.alternatives);
        }
    } catch (error) {
        console.error('Failed to get new route:', error);
    }
}
```

## Testing Your Implementation

### Test 1: Basic Rerouting (5 minutes)

Using Postman or curl:

```bash
# 1. Get an active trip ID and driver token
TRIP_ID=123
TOKEN="your_driver_auth_token"

# 2. Simulate driver position far from route
curl -X POST http://your-domain.com/api/v1/driver/rerouting/check-deviation \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "trip_request_id": '${TRIP_ID}',
    "latitude": 30.0500,
    "longitude": 31.2400,
    "deviation_threshold": 100
  }'

# Expected: New route returned if deviated
```

### Test 2: Route Alternatives (2 minutes)

```bash
curl -X POST http://your-domain.com/api/v1/driver/rerouting/get-alternatives \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "trip_request_id": '${TRIP_ID}',
    "latitude": 30.0444,
    "longitude": 31.2357
  }'

# Expected: Multiple route options returned
```

### Test 3: Check Logs

```bash
# View rerouting events in logs
tail -f storage/logs/laravel.log | grep -i "rerouting\|deviation"
```

## Configuration

### Adjust Deviation Threshold

Default is 100 meters. Adjust based on your needs:

```javascript
// In mobile app
const DEVIATION_THRESHOLDS = {
    highway: 200,    // Less sensitive on highways
    urban: 100,      // Default for city driving
    residential: 50  // More sensitive in neighborhoods
};

// Pass appropriate threshold
deviation_threshold: DEVIATION_THRESHOLDS.urban
```

### Adjust GPS Update Frequency

```javascript
// More frequent in dense areas
const GPS_INTERVAL_URBAN = 10000;  // 10 seconds

// Less frequent on highways
const GPS_INTERVAL_HIGHWAY = 30000; // 30 seconds

setInterval(() => {
    checkForRerouting();
}, currentRoadType === 'highway' ? GPS_INTERVAL_HIGHWAY : GPS_INTERVAL_URBAN);
```

## How It Works

### 1. Continuous Monitoring
```
Driver App GPS Update (every 10-15 sec)
    ↓
Send to: /api/v1/driver/rerouting/check-deviation
    ↓
Calculate distance from current route polyline
    ↓
If distance > threshold → Request new routes from GeoLink v2
    ↓
Filter all routes, select shortest by duration
    ↓
Return optimized route to driver
```

### 2. Route Selection
```
GeoLink v2 API returns: [Route A, Route B, Route C]

Duration:
  Route A: 750 sec  ← SELECTED (shortest)
  Route B: 780 sec
  Route C: 820 sec

App displays: Route A (12.5 min, 5.23 km)
```

## Key Features

### ✓ Real-time Deviation Detection
- Uses Haversine formula for accurate distance calculation
- Checks distance to nearest point on route polyline
- Configurable threshold (default: 100m)

### ✓ Automatic Route Optimization
- Requests multiple alternatives from GeoLink v2
- Filters by duration (shortest ETA wins)
- Handles intermediate waypoints automatically

### ✓ Production-Ready Error Handling
- API failures: Falls back to current route
- GPS unavailable: Returns error to client
- Route missing: Logs warning, continues
- Unauthorized: Returns 403 Forbidden

### ✓ Performance Optimized
- 30-second cooldown between reroutes
- Route polyline caching (24 hours)
- Efficient database queries with eager loading

## Common Issues & Solutions

### Issue: "No routes returned"
**Solution**: Check GeoLink API key in Admin Panel
```
Admin Panel → Business Settings → Third Party → Map API
Ensure map_api_key_server is configured
```

### Issue: "Too many rerouting requests"
**Solution**: Cooldown is active (30 sec minimum between reroutes)
```
This is by design to prevent excessive API usage
Wait 30 seconds before next reroute attempt
```

### Issue: "Driver not deviating but reroute triggered"
**Solution**: Adjust deviation threshold
```javascript
// Increase threshold for less sensitive detection
deviation_threshold: 200  // 200 meters instead of 100
```

## API Response Reference

### Success (On Route)
```json
{
  "status": "success",
  "message": "Driver is on route",
  "data": {
    "reroute_needed": false
  }
}
```

### Success (Deviation Detected)
```json
{
  "status": "success",
  "message": "New optimized route provided",
  "data": {
    "reroute_needed": true,
    "route": {
      "distance": 5.23,
      "distance_text": "5.23 km",
      "duration": "12.50 min",
      "duration_sec": 750,
      "encoded_polyline": "..."
    },
    "alternatives_count": 3
  }
}
```

### Error (Validation Failed)
```json
{
  "status": "error",
  "message": "Validation failed",
  "errors": {
    "latitude": ["The latitude field is required."]
  }
}
```

## Next Steps

1. **Integrate into driver mobile app** (see Step 1 above)
2. **Test with real GPS data** during active trips
3. **Monitor logs** for rerouting events
4. **Adjust thresholds** based on real-world usage
5. **Add UI notifications** when new routes are provided

## Support & Documentation

- **Full Guide**: See `DYNAMIC_REROUTING_GUIDE.md`
- **GeoLink API**: https://geolink-eg.com/docs
- **Logs**: `storage/logs/laravel.log`

---

**Status**: ✅ Ready for Integration
**Version**: 1.0.0
**Last Updated**: December 7, 2025
