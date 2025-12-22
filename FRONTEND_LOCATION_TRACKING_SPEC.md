# Driver Location Tracking - Frontend Implementation Guide

## Table of Contents
1. [Overview](#overview)
2. [API Endpoints](#api-endpoints)
3. [Location Update Logic](#location-update-logic)
4. [Event Types](#event-types)
5. [Implementation Examples](#implementation-examples)
6. [Error Handling](#error-handling)
7. [Best Practices](#best-practices)
8. [Testing](#testing)

---

## Overview

This document describes how to implement **sparse + event-triggered location tracking** in the driver mobile app. The system is designed to:

- **Save battery** by reducing frequent GPS updates
- **Reduce network usage** by sending updates only when needed
- **Maintain accuracy** through event-triggered updates
- **Ensure reliability** with retry mechanisms

---

## API Endpoints

### 1. Send Location Update

**Endpoint:** `POST /api/driver/ride/location`

**Authentication:** Required (Bearer token)

**Request Body:**
```json
{
  "ride_id": "uuid-string",
  "latitude": 30.0444,
  "longitude": 31.2357,
  "speed": 15.5,
  "heading": 180.0,
  "accuracy": 10.0,
  "timestamp": 1700000000,
  "event_type": "START"
}
```

**Field Descriptions:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `ride_id` | string (UUID) | ✅ Yes | ID of the active ride |
| `latitude` | float | ✅ Yes | Latitude (-90 to 90) |
| `longitude` | float | ✅ Yes | Longitude (-180 to 180) |
| `speed` | float | ❌ No | Speed in meters/second |
| `heading` | float | ❌ No | Direction in degrees (0-360) |
| `accuracy` | float | ❌ No | GPS accuracy in meters |
| `timestamp` | integer | ✅ Yes | Unix timestamp (seconds) |
| `event_type` | string | ❌ No | See [Event Types](#event-types) |

**Success Response (200 OK):**
```json
{
  "response_code": "default_200",
  "message": "Location updated successfully",
  "content": null,
  "errors": []
}
```

**Error Responses:**

**400 Bad Request** - Invalid data or anomaly detected:
```json
{
  "response_code": "default_400",
  "message": "Location update rejected due to anomalies: teleport, excessive_speed",
  "content": null,
  "errors": [
    {
      "field": "latitude",
      "message": "Validation error message"
    }
  ]
}
```

**404 Not Found** - No active ride found:
```json
{
  "response_code": "trip_request_404",
  "message": "No active ride found or you are not the driver for this ride",
  "content": null,
  "errors": []
}
```

### 2. Get Location History

**Endpoint:** `GET /api/driver/ride/location/history?ride_id={uuid}`

**Authentication:** Required (Bearer token)

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `ride_id` | string (UUID) | ✅ Yes | ID of the ride |

**Success Response (200 OK):**
```json
{
  "response_code": "default_200",
  "message": "success",
  "content": {
    "ride_id": "uuid-string",
    "total_distance": 5420,
    "total_duration": 720,
    "route_points": [
      {
        "latitude": 30.0444,
        "longitude": 31.2357,
        "speed": 15.5,
        "timestamp": 1700000000,
        "event_type": "START"
      },
      {
        "latitude": 30.0450,
        "longitude": 31.2360,
        "speed": 18.2,
        "timestamp": 1700000015,
        "event_type": "NORMAL"
      }
    ]
  },
  "errors": []
}
```

---

## Location Update Logic

### Update Triggers

The app should send a location update when **ANY** of the following conditions are met:

#### 1. **Distance-Based Trigger**
- Driver has moved **≥ 50 meters** since last update

```javascript
const MIN_DISTANCE_METERS = 50;

if (distanceFromLastUpdate >= MIN_DISTANCE_METERS) {
  sendLocationUpdate();
}
```

#### 2. **Time-Based Trigger**
- **≥ 15 seconds** have passed since last update

```javascript
const MIN_TIME_SECONDS = 15;

if (currentTime - lastUpdateTime >= MIN_TIME_SECONDS) {
  sendLocationUpdate();
}
```

#### 3. **Event-Based Triggers**
- **Critical events bypass all other rules** and send immediately
- See [Event Types](#event-types) for full list

```javascript
// Critical events send immediately
if (eventType) {
  sendLocationUpdate(location, eventType);
}
```

### Flow Diagram

```
┌─────────────────┐
│  GPS Update     │
└────────┬────────┘
         │
         ▼
┌────────────────────┐
│  Check Triggers    │
│  • Distance ≥ 50m? │
│  • Time ≥ 15s?     │
│  • Event?          │
└────────┬───────────┘
         │
    ┌────┴────┐
    │         │
    ▼         ▼
   YES       NO
    │         │
    │         └──► Skip (wait for next GPS update)
    │
    ▼
┌────────────────────┐
│  Send to API       │
│  POST /location    │
└────────┬───────────┘
         │
    ┌────┴────┐
    │         │
    ▼         ▼
  SUCCESS   FAIL
    │         │
    │         └──► Retry with exponential backoff
    │
    ▼
┌────────────────────┐
│  Update last sent  │
│  location & time   │
└────────────────────┘
```

---

## Event Types

Send these event types immediately, bypassing all distance/time rules:

| Event Type | When to Send | Description |
|------------|--------------|-------------|
| `START` | Trip starts | Driver taps "Start Trip" button |
| `PICKUP` | Driver arrives at pickup | Near pickup location (< 50m) |
| `DROPOFF` | Driver arrives at destination | Near dropoff location (< 50m) |
| `SOS` | Emergency | SOS/Panic button pressed |
| `IDLE` | Driver stopped for 2+ minutes | Stopped in traffic, bathroom break, etc. |

**Do NOT send `event_type` for normal updates** - the backend will mark them as `NORMAL` automatically.

---

## Implementation Examples

### React Native Example

```javascript
import Geolocation from '@react-native-community/geolocation';
import axios from 'axios';

class LocationTracker {
  constructor(apiBaseUrl, authToken) {
    this.apiBaseUrl = apiBaseUrl;
    this.authToken = authToken;
    this.lastSentLocation = null;
    this.lastSentTime = null;
    this.minDistanceMeters = 50;
    this.minTimeSeconds = 15;
    this.watchId = null;
    this.currentRideId = null;
  }

  /**
   * Start tracking for a ride
   */
  startTracking(rideId) {
    this.currentRideId = rideId;

    // Watch position changes
    this.watchId = Geolocation.watchPosition(
      (position) => {
        this.handleLocationUpdate(position);
      },
      (error) => {
        console.error('GPS error:', error);
      },
      {
        enableHighAccuracy: true,
        distanceFilter: 10, // Get GPS updates every 10m
        interval: 5000, // Check every 5 seconds
        fastestInterval: 2000,
      }
    );
  }

  /**
   * Stop tracking
   */
  stopTracking() {
    if (this.watchId !== null) {
      Geolocation.clearWatch(this.watchId);
      this.watchId = null;
    }
    this.currentRideId = null;
    this.lastSentLocation = null;
    this.lastSentTime = null;
  }

  /**
   * Handle GPS position update
   */
  async handleLocationUpdate(position) {
    if (!this.currentRideId) return;

    const currentLocation = {
      latitude: position.coords.latitude,
      longitude: position.coords.longitude,
      speed: position.coords.speed || 0,
      heading: position.coords.heading || null,
      accuracy: position.coords.accuracy || null,
      timestamp: Math.floor(position.timestamp / 1000), // Convert to seconds
    };

    // Check if we should send this update
    if (this.shouldSendUpdate(currentLocation)) {
      await this.sendLocation(currentLocation);
    }
  }

  /**
   * Check if update should be sent
   */
  shouldSendUpdate(currentLocation) {
    // First update always sends
    if (!this.lastSentLocation || !this.lastSentTime) {
      return true;
    }

    // Check distance trigger
    const distance = this.calculateDistance(
      this.lastSentLocation.latitude,
      this.lastSentLocation.longitude,
      currentLocation.latitude,
      currentLocation.longitude
    );

    if (distance >= this.minDistanceMeters) {
      return true;
    }

    // Check time trigger
    const now = Math.floor(Date.now() / 1000);
    if (now - this.lastSentTime >= this.minTimeSeconds) {
      return true;
    }

    return false;
  }

  /**
   * Send location to API
   */
  async sendLocation(location, eventType = null) {
    const payload = {
      ride_id: this.currentRideId,
      latitude: location.latitude,
      longitude: location.longitude,
      speed: location.speed,
      heading: location.heading,
      accuracy: location.accuracy,
      timestamp: location.timestamp,
    };

    if (eventType) {
      payload.event_type = eventType;
    }

    try {
      const response = await axios.post(
        `${this.apiBaseUrl}/api/driver/ride/location`,
        payload,
        {
          headers: {
            Authorization: `Bearer ${this.authToken}`,
            'Content-Type': 'application/json',
          },
          timeout: 10000, // 10 second timeout
        }
      );

      if (response.status === 200) {
        // Update last sent values
        this.lastSentLocation = location;
        this.lastSentTime = location.timestamp;
        console.log('Location sent successfully');
      }
    } catch (error) {
      console.error('Failed to send location:', error);

      // Retry logic
      await this.retryWithBackoff(location, eventType);
    }
  }

  /**
   * Retry with exponential backoff
   */
  async retryWithBackoff(location, eventType, attempt = 1, maxAttempts = 3) {
    if (attempt > maxAttempts) {
      console.error('Max retry attempts reached');
      // Store in local queue for later sync
      await this.queueForLaterSync(location, eventType);
      return;
    }

    const delay = Math.pow(2, attempt) * 1000; // 2s, 4s, 8s
    console.log(`Retrying in ${delay}ms (attempt ${attempt}/${maxAttempts})`);

    await new Promise(resolve => setTimeout(resolve, delay));

    try {
      await this.sendLocation(location, eventType);
    } catch (error) {
      await this.retryWithBackoff(location, eventType, attempt + 1, maxAttempts);
    }
  }

  /**
   * Queue failed updates for later sync
   */
  async queueForLaterSync(location, eventType) {
    // Implement using AsyncStorage or similar
    console.log('Queuing location for later sync:', location);
    // TODO: Store in AsyncStorage
  }

  /**
   * Send event-based location (bypasses triggers)
   */
  async sendEvent(eventType) {
    return new Promise((resolve, reject) => {
      Geolocation.getCurrentPosition(
        async (position) => {
          const currentLocation = {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            speed: position.coords.speed || 0,
            heading: position.coords.heading || null,
            accuracy: position.coords.accuracy || null,
            timestamp: Math.floor(position.timestamp / 1000),
          };

          await this.sendLocation(currentLocation, eventType);
          resolve();
        },
        (error) => {
          console.error('Failed to get current position:', error);
          reject(error);
        },
        {
          enableHighAccuracy: true,
          timeout: 10000,
          maximumAge: 0,
        }
      );
    });
  }

  /**
   * Calculate distance between two GPS coordinates (Haversine formula)
   */
  calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371000; // Earth radius in meters
    const dLat = this.toRad(lat2 - lat1);
    const dLon = this.toRad(lon2 - lon1);
    const a =
      Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos(this.toRad(lat1)) *
        Math.cos(this.toRad(lat2)) *
        Math.sin(dLon / 2) *
        Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
  }

  toRad(degrees) {
    return degrees * (Math.PI / 180);
  }
}

export default LocationTracker;
```

### Usage Example

```javascript
import LocationTracker from './LocationTracker';

// Initialize tracker
const tracker = new LocationTracker(
  'https://api.yourapp.com',
  userAuthToken
);

// When driver accepts a ride
function onRideAccepted(rideId) {
  tracker.startTracking(rideId);
}

// When driver starts trip
async function onStartTrip() {
  await tracker.sendEvent('START');
}

// When driver arrives at pickup
async function onArrivedAtPickup() {
  await tracker.sendEvent('PICKUP');
}

// When driver completes trip
async function onTripCompleted() {
  await tracker.sendEvent('DROPOFF');
  tracker.stopTracking();
}

// SOS button
async function onSOSPressed() {
  await tracker.sendEvent('SOS');
}
```

---

## Error Handling

### Common Errors and Solutions

#### 1. **400 Bad Request - Validation Error**

**Cause:** Invalid data sent to API

**Solution:**
```javascript
// Validate before sending
if (latitude < -90 || latitude > 90) {
  console.error('Invalid latitude');
  return;
}

if (longitude < -180 || longitude > 180) {
  console.error('Invalid longitude');
  return;
}
```

#### 2. **404 Not Found - No Active Ride**

**Cause:** Sending location without an active ride, or wrong ride ID

**Solution:**
```javascript
// Only track when ride is active
if (!currentRideId) {
  console.warn('No active ride, skipping location update');
  return;
}
```

#### 3. **Network Timeout**

**Cause:** Poor network connection

**Solution:**
```javascript
// Implement retry with exponential backoff
// Queue failed updates for later sync
```

#### 4. **GPS Unavailable**

**Cause:** GPS permission denied or device issue

**Solution:**
```javascript
// Request permissions
import { PermissionsAndroid, Platform } from 'react-native';

async function requestLocationPermission() {
  if (Platform.OS === 'android') {
    const granted = await PermissionsAndroid.request(
      PermissionsAndroid.PERMISSIONS.ACCESS_FINE_LOCATION
    );
    return granted === PermissionsAndroid.RESULTS.GRANTED;
  }
  return true; // iOS handles via Info.plist
}
```

---

## Best Practices

### 1. **Battery Optimization**

- Use `distanceFilter` to reduce GPS polling
- Stop tracking when app is backgrounded (if ride is not active)
- Use lower accuracy for non-critical updates

```javascript
// High accuracy for events, normal for regular updates
const gpsOptions = {
  enableHighAccuracy: isEventUpdate ? true : false,
  distanceFilter: 10,
};
```

### 2. **Network Optimization**

- Batch updates if multiple are queued
- Compress payloads if possible
- Use HTTP/2 or WebSocket for real-time updates

### 3. **Offline Handling**

- Queue updates when offline
- Sync when connection restored
- Store in persistent storage (AsyncStorage)

```javascript
// Check network status
import NetInfo from '@react-native-community/netinfo';

NetInfo.addEventListener(state => {
  if (state.isConnected) {
    syncQueuedUpdates();
  }
});
```

### 4. **User Privacy**

- Only track during active rides
- Stop tracking immediately when ride ends
- Don't store location data locally unnecessarily

---

## Testing

### Test Cases

#### 1. **Distance Trigger Test**

```javascript
// Simulate moving 60 meters
const location1 = { latitude: 30.0444, longitude: 31.2357 };
const location2 = { latitude: 30.0450, longitude: 31.2357 }; // ~67m north

// Should trigger update
```

#### 2. **Time Trigger Test**

```javascript
// Send update, wait 16 seconds, send again
// Should trigger even if location hasn't moved much
```

#### 3. **Event Trigger Test**

```javascript
// Send START event
await tracker.sendEvent('START');

// Should send immediately regardless of distance/time
```

#### 4. **Retry Logic Test**

```javascript
// Disconnect network
// Send update
// Should retry 3 times, then queue

// Reconnect network
// Should sync queued updates
```

#### 5. **Anomaly Test**

```javascript
// Send location: Cairo (30.04, 31.23)
// Immediately send: Alexandria (31.20, 29.92) [~200km]
// Backend should flag as anomaly (teleport)
```

### Test Endpoints

Use the backend's history endpoint to verify updates were received:

```bash
GET /api/driver/ride/location/history?ride_id=xyz
```

---

## Configuration

### Recommended Values

```javascript
const CONFIG = {
  // Trigger thresholds
  MIN_DISTANCE_METERS: 50,
  MIN_TIME_SECONDS: 15,

  // GPS settings
  GPS_ACCURACY: true, // High accuracy
  GPS_DISTANCE_FILTER: 10, // Get updates every 10m
  GPS_INTERVAL: 5000, // 5 seconds

  // Network settings
  API_TIMEOUT: 10000, // 10 seconds
  MAX_RETRY_ATTEMPTS: 3,
  RETRY_BACKOFF_BASE: 2, // 2s, 4s, 8s

  // Queue settings
  MAX_QUEUE_SIZE: 100, // Max queued updates
  QUEUE_SYNC_INTERVAL: 60000, // Sync every 60s
};
```

### Environment-Specific Overrides

```javascript
// Development: More frequent updates for testing
const DEV_CONFIG = {
  MIN_DISTANCE_METERS: 25,
  MIN_TIME_SECONDS: 10,
};

// Production: Battery-optimized
const PROD_CONFIG = {
  MIN_DISTANCE_METERS: 50,
  MIN_TIME_SECONDS: 15,
};
```

---

## Real-Time Updates (WebSocket/Pusher)

### Listening for Location Updates (Rider App)

If you want the **rider app** to receive real-time location updates:

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Initialize Laravel Echo (assuming you use Pusher)
const echo = new Echo({
  broadcaster: 'pusher',
  key: 'your-pusher-key',
  cluster: 'your-cluster',
  encrypted: true,
});

// Listen for driver location updates
echo.private(`customer-ride.${userId}`)
  .listen('driver-location-updated', (data) => {
    console.log('Driver location:', data);

    // Update map
    updateDriverMarker({
      lat: data.latitude,
      lng: data.longitude,
      heading: data.heading,
      eta: data.eta_minutes,
    });
  });
```

---

## Troubleshooting

### Issue: Location not sending

**Check:**
1. Is `currentRideId` set?
2. Are triggers being met?
3. Is GPS working?
4. Is network available?
5. Check console logs for errors

### Issue: Too many updates

**Check:**
1. Are distance/time thresholds too low?
2. Is GPS accuracy causing jitter?
3. Increase `MIN_DISTANCE_METERS` to 75-100m

### Issue: Anomaly detected errors

**Check:**
1. Is GPS accuracy poor?
2. Is device time synchronized?
3. Are you testing with mock locations?

---

## Summary

**Key Points:**
- Send location every **50m** OR **15s** OR **on events**
- Events bypass all rules and send immediately
- Implement retry with exponential backoff
- Queue failed updates for later sync
- Stop tracking when ride ends

**Next Steps:**
1. Implement the `LocationTracker` class
2. Integrate with your ride management system
3. Test all trigger conditions
4. Monitor battery usage
5. Deploy and monitor

---

**Questions?**
Contact backend team or refer to the backend implementation at:
- Controller: `Modules/TripManagement/Http/Controllers/Api/Driver/LocationController.php`
- Config: `config/tracking.php`
