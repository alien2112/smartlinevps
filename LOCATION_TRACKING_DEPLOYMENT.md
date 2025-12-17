# Location Tracking System - Backend Deployment Guide

## Overview

This guide helps you deploy the **sparse + event-triggered location tracking system** for driver rides.

---

## Files Created

### 1. **Migrations**
- `database/migrations/2025_12_16_135756_create_trip_route_points_table.php`
- `database/migrations/2025_12_16_140636_add_tracking_columns_to_trip_requests_table.php`

### 2. **Models**
- `Modules/TripManagement/Entities/TripRoutePoint.php`

### 3. **Controllers**
- `Modules/TripManagement/Http/Controllers/Api/Driver/LocationController.php`

### 4. **Events**
- `app/Events/DriverLocationUpdated.php`

### 5. **Configuration**
- `config/tracking.php`

### 6. **Routes**
- Updated: `Modules/TripManagement/Routes/api.php`

### 7. **Documentation**
- `FRONTEND_LOCATION_TRACKING_SPEC.md` - Complete frontend implementation guide

---

## Deployment Steps

### Step 1: Run Migrations

Run the new migrations to add tracking tables and columns:

```bash
php artisan migrate
```

This will:
- Create `trip_route_points` table for storing route history
- Add tracking columns to `trip_requests` table:
  - `last_latitude`, `last_longitude`
  - `last_location_timestamp`
  - `current_speed`
  - `total_distance`, `total_duration`
  - `anomaly_count`, `last_anomaly_at`

### Step 2: Configure Environment Variables (Optional)

Add these to your `.env` file to customize thresholds:

```env
# Location Tracking Configuration

# Frontend triggers (guidelines)
TRACKING_MIN_DISTANCE=50
TRACKING_MIN_TIME=15

# Sanity checks
TRACKING_MAX_SPEED=200
TRACKING_MAX_SPEED_MS=55.5
TRACKING_MAX_JUMP=1000
TRACKING_MAX_JUMP_TIME=15
TRACKING_MAX_IDLE=10
TRACKING_MAX_FUTURE_OFFSET=60

# Storage
TRACKING_STORE_ROUTE=true
TRACKING_ROUTE_RETENTION=90
TRACKING_STORE_EVENTS_ONLY=false

# Notifications
TRACKING_NOTIFY_INTERVAL=10
TRACKING_USE_WEBSOCKET=true
TRACKING_USE_PUSH=false

# Anomaly handling
TRACKING_MAX_ANOMALIES=5
TRACKING_REJECT_ANOMALIES=false
TRACKING_NOTIFY_ADMIN_ANOMALY=false
```

**Note:** If you don't add these, the default values from `config/tracking.php` will be used.

### Step 3: Clear Cache

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### Step 4: Test the API Endpoints

#### Test Location Update

```bash
curl -X POST http://localhost:8000/api/driver/ride/location \
  -H "Authorization: Bearer YOUR_DRIVER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "ride_id": "valid-ride-uuid",
    "latitude": 30.0444,
    "longitude": 31.2357,
    "speed": 15.5,
    "heading": 180.0,
    "accuracy": 10.0,
    "timestamp": 1700000000,
    "event_type": "START"
  }'
```

Expected response:
```json
{
  "response_code": "default_200",
  "message": "Location updated successfully",
  "content": null,
  "errors": []
}
```

#### Test Location History

```bash
curl -X GET "http://localhost:8000/api/driver/ride/location/history?ride_id=valid-ride-uuid" \
  -H "Authorization: Bearer YOUR_DRIVER_TOKEN"
```

---

## API Routes

The following routes were added:

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/driver/ride/location` | Store driver location update |
| GET | `/api/driver/ride/location/history` | Get location history for a ride |

Both routes require authentication (`auth:api` middleware).

---

## Database Schema

### `trip_route_points` Table

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `trip_request_id` | uuid | Reference to trip_requests |
| `latitude` | decimal(10,8) | GPS latitude |
| `longitude` | decimal(11,8) | GPS longitude |
| `speed` | decimal(5,2) | Speed in m/s |
| `heading` | decimal(5,2) | Direction in degrees |
| `accuracy` | decimal(8,2) | GPS accuracy in meters |
| `timestamp` | bigint | Unix timestamp from device |
| `event_type` | enum | START, PICKUP, DROPOFF, SOS, IDLE, NORMAL |
| `created_at` | timestamp | Record creation time |
| `updated_at` | timestamp | Record update time |

**Indexes:**
- `trip_request_id`
- `timestamp`
- `(trip_request_id, timestamp)` - Composite

### `trip_requests` Table (New Columns)

| Column | Type | Description |
|--------|------|-------------|
| `last_latitude` | decimal(10,8) | Last known latitude |
| `last_longitude` | decimal(11,8) | Last known longitude |
| `last_location_timestamp` | bigint | Unix timestamp of last update |
| `current_speed` | decimal(5,2) | Current speed in m/s |
| `total_distance` | int | Accumulated distance in meters |
| `total_duration` | int | Accumulated duration in seconds |
| `anomaly_count` | int | Number of suspicious updates |
| `last_anomaly_at` | timestamp | Timestamp of last anomaly |

---

## How It Works

### 1. **Location Update Flow**

```
Driver App → POST /location → LocationController::store()
                                   ↓
                           Validate Request
                                   ↓
                           Check Active Ride
                                   ↓
                           Sanity Checks
                                   ↓
                           Update Metrics
                                   ↓
                           Store Route Point
                                   ↓
                           Handle Events
                                   ↓
                           Notify Rider
                                   ↓
                           Return Success
```

### 2. **Sanity Checks**

The backend performs these checks on every location update:

- **Future Timestamp:** Timestamp cannot be more than 60s in the future
- **Excessive Speed:** Speed cannot exceed 200 km/h (~55.5 m/s)
- **Teleportation:** Cannot move >1000m in <15 seconds
- **Impossible Speed:** Derived speed from distance/time must be reasonable

If anomalies are detected:
- Logged with details
- Anomaly counter incremented
- Update is accepted (unless `TRACKING_REJECT_ANOMALIES=true`)

### 3. **Distance & Duration Accumulation**

Every location update:
1. Calculates distance from last known position using **Haversine formula**
2. Adds to `total_distance` (in meters)
3. Calculates time difference from last update
4. Adds to `total_duration` (in seconds)

### 4. **Event Handling**

Special handling for event types:

- **START:** Updates ride status to STARTED
- **PICKUP:** Updates ride status to ARRIVED
- **DROPOFF:** Logs completion (doesn't auto-complete)
- **SOS:** Logs critical alert
- **IDLE:** Logs idle period

### 5. **Real-time Notifications**

When configured (`TRACKING_USE_WEBSOCKET=true`):
- Fires `DriverLocationUpdated` event
- Broadcasts to rider via WebSocket
- Throttled to prevent overwhelming (default: every 10s)

---

## Configuration Options

Edit `config/tracking.php` or use environment variables:

### Frontend Triggers
- `min_distance_meters`: Minimum distance before update (default: 50m)
- `min_time_seconds`: Minimum time before update (default: 15s)

### Sanity Checks
- `max_speed_ms`: Maximum reasonable speed in m/s (default: 55.5 = 200 km/h)
- `max_jump_meters`: Maximum distance jump allowed (default: 1000m)
- `max_jump_time_seconds`: Time window for jump detection (default: 15s)

### Storage
- `store_route_points`: Whether to save route points (default: true)
- `store_events_only`: Only save event-type updates (default: false)
- `route_retention_days`: How long to keep route history (default: 90 days)

### Notifications
- `notify_rider_interval`: Seconds between rider notifications (default: 10)
- `use_websocket`: Enable WebSocket broadcasts (default: true)

### Anomaly Handling
- `max_anomalies_before_flag`: Anomaly count before flagging (default: 5)
- `reject_anomalous_updates`: Reject updates with anomalies (default: false)

---

## Monitoring & Debugging

### View Route Points for a Ride

```sql
SELECT
  latitude,
  longitude,
  speed,
  FROM_UNIXTIME(timestamp) as time,
  event_type
FROM trip_route_points
WHERE trip_request_id = 'ride-uuid'
ORDER BY timestamp ASC;
```

### Check Rides with Anomalies

```sql
SELECT
  id,
  anomaly_count,
  last_anomaly_at,
  total_distance,
  total_duration
FROM trip_requests
WHERE anomaly_count > 0
ORDER BY anomaly_count DESC;
```

### View Application Logs

```bash
tail -f storage/logs/laravel.log | grep "Location anomaly"
```

---

## Performance Considerations

### Database Indexing

The migrations include optimized indexes:
- Fast lookups by `trip_request_id`
- Efficient time-based queries
- Composite index for route queries

### Storage Management

Route points can accumulate quickly. Consider:

1. **Auto-cleanup old records:**

```php
// In a scheduled task (app/Console/Kernel.php)
$schedule->call(function () {
    $cutoff = now()->subDays(config('tracking.route_retention_days', 90));
    TripRoutePoint::where('created_at', '<', $cutoff)->delete();
})->daily();
```

2. **Archive to cold storage:**

Export old route points to S3/cold storage before deletion.

### Throttling

Rider notifications are throttled using cache:
- Key: `rider_notified_{ride_id}`
- TTL: `notify_rider_interval` seconds
- Prevents overwhelming the rider app

---

## Security Considerations

### 1. Authentication
- All endpoints require `auth:api` middleware
- Driver can only update their own active rides

### 2. Validation
- All inputs are validated
- GPS coordinates checked for valid ranges
- Timestamp validated against future/past limits

### 3. Anomaly Detection
- Prevents GPS spoofing
- Detects teleportation attempts
- Flags impossible speeds

### 4. Rate Limiting
- Consider adding rate limiting to prevent abuse
- Suggested: 100 requests per minute per driver

```php
// In routes/api.php
Route::middleware(['throttle:100,1'])->group(function () {
    // Location routes
});
```

---

## Troubleshooting

### Issue: Migrations fail

**Cause:** Foreign key constraint or table already exists

**Solution:**
```bash
php artisan migrate:rollback --step=1
php artisan migrate
```

### Issue: Routes not found

**Cause:** Route cache not cleared

**Solution:**
```bash
php artisan route:clear
php artisan cache:clear
```

### Issue: Events not broadcasting

**Cause:** Broadcasting not configured

**Solution:**
1. Configure Pusher/Redis in `.env`
2. Set `BROADCAST_DRIVER=pusher` or `BROADCAST_DRIVER=redis`
3. Run queue worker: `php artisan queue:work`

---

## Next Steps

1. ✅ Run migrations
2. ✅ Test API endpoints
3. ✅ Share `FRONTEND_LOCATION_TRACKING_SPEC.md` with mobile team
4. Configure WebSocket/Pusher for real-time updates
5. Set up monitoring/alerts for anomalies
6. Configure scheduled cleanup for old route points

---

## Support

For questions or issues:
- Backend implementation: `Modules/TripManagement/Http/Controllers/Api/Driver/LocationController.php`
- Configuration: `config/tracking.php`
- Frontend guide: `FRONTEND_LOCATION_TRACKING_SPEC.md`
