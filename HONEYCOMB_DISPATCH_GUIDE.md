# Honeycomb Dispatch System

## Overview

This document describes the **Honeycomb Dispatch System** - a hexagonal grid-based approach for optimizing ride-hailing dispatch, similar to DiDi and Uber's H3 implementation.

### What is "Honeycomb Mode"?

Honeycomb mode divides the city into **hexagonal cells** (like a honeycomb) and uses these cells for:

1. **Faster Dispatch** - Only search drivers in the pickup cell + neighboring cells
2. **Supply/Demand Heatmaps** - Visualize driver availability vs ride requests
3. **Driver Hotspots** - Show drivers where demand is high
4. **Cell-based Surge Pricing** - Optional dynamic pricing per cell

### Why Hexagons?

- Hex grids approximate circles better than squares
- Uniform neighbor distances (all 6 neighbors are equidistant)
- Used by Uber's H3 and DiDi's dispatch systems

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        ADMIN PANEL                               │
│  • Enable/disable honeycomb per zone                             │
│  • Configure H3 resolution, search depth                         │
│  • View heatmaps and analytics                                   │
└───────────────────────────┬──────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                      LARAVEL BACKEND                             │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ HoneycombService                                          │   │
│  │ • latLngToH3() - Convert coordinates to cell index        │   │
│  │ • getCandidateDrivers() - Cell + kRing search             │   │
│  │ • getHeatmap() - Supply/demand visualization              │   │
│  │ • getSurgeMultiplier() - Cell-based pricing               │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                   │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ Admin API                                                 │   │
│  │ • GET  /admin/dispatch/honeycomb/settings                 │   │
│  │ • PUT  /admin/dispatch/honeycomb/settings                 │   │
│  │ • GET  /admin/dispatch/honeycomb/heatmap                  │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                   │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ Driver API                                                │   │
│  │ • GET  /driver/honeycomb/hotspots                         │   │
│  │ • GET  /driver/honeycomb/cell                             │   │
│  └──────────────────────────────────────────────────────────┘   │
└───────────────────────────┬──────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                         REDIS                                    │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ Real-time Cell Data                                       │   │
│  │ • hc:drivers:{zone}:{h3} → SET of driverIds               │   │
│  │ • hc:supply:{zone}:{h3} → HASH {total, budget, pro, vip}  │   │
│  │ • hc:demand:{zone}:{h3}:{window} → HASH {total, ...}      │   │
│  │ • hc:driver:cell:{driverId} → current cell index          │   │
│  │ • hc:settings:{zone} → cached settings JSON               │   │
│  └──────────────────────────────────────────────────────────┘   │
└───────────────────────────┬──────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                    NODE.JS REALTIME SERVICE                      │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ HoneycombService.js                                       │   │
│  │ • updateDriverCell() - Track driver cell changes          │   │
│  │ • getCandidateDrivers() - Fast dispatch candidate filter  │   │
│  │ • Subscribes to config updates via pub/sub                │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

---

## Database Schema

### `dispatch_honeycomb_settings`

Per-zone honeycomb configuration:

| Column | Type | Description |
|--------|------|-------------|
| `id` | UUID | Primary key |
| `zone_id` | UUID | Zone reference (nullable for global) |
| `enabled` | BOOL | Master toggle |
| `dispatch_enabled` | BOOL | Use honeycomb for dispatch |
| `heatmap_enabled` | BOOL | Enable heatmap visualization |
| `hotspots_enabled` | BOOL | Show hotspots to drivers |
| `surge_enabled` | BOOL | Cell-based surge pricing |
| `h3_resolution` | INT | 7=~5km, 8=~1.5km, 9=~500m |
| `search_depth_k` | INT | Number of hex rings to search |
| `surge_threshold` | DECIMAL | Imbalance ratio for surge |
| `surge_cap` | DECIMAL | Maximum surge multiplier |

### `honeycomb_cell_metrics`

Historical cell data for analytics:

| Column | Type | Description |
|--------|------|-------------|
| `h3_index` | VARCHAR | Cell identifier |
| `window_start` | DATETIME | Time window start |
| `supply_total` | INT | Available drivers |
| `demand_total` | INT | Ride requests |
| `imbalance_score` | DECIMAL | demand/supply ratio |

---

## Redis Key Design

```
# Driver location by cell (SET)
hc:drivers:{zoneId}:{h3Index}
  → Set of driverIds currently in this cell

# Supply counters (HASH)  
hc:supply:{zoneId}:{h3Index}
  → {total: "5", budget: "2", pro: "2", vip: "1"}

# Demand counters per time window (HASH)
hc:demand:{zoneId}:{h3Index}:{windowTimestamp}
  → {total: "3", budget: "2", pro: "1", vip: "0"}

# Driver's current cell (STRING with TTL)
hc:driver:cell:{driverId}
  → "r8_1a2b3c_4d5e6f"

# Cached settings (STRING with TTL)
hc:settings:{zoneId}
  → JSON settings object
```

---

## API Reference

### Admin Endpoints

#### Get Settings
```http
GET /api/admin/dispatch/honeycomb/settings?zone_id={uuid}
```

Response:
```json
{
  "success": true,
  "data": {
    "settings": {
      "id": "...",
      "zone_id": "...",
      "enabled": true,
      "dispatch_enabled": true,
      "h3_resolution": 8,
      "search_depth_k": 1
    },
    "resolutions": {
      "7": {"name": "City", "area_km2": 5.16},
      "8": {"name": "Neighborhood", "area_km2": 0.74},
      "9": {"name": "Block", "area_km2": 0.11}
    }
  }
}
```

#### Update Settings
```http
PUT /api/admin/dispatch/honeycomb/settings
Content-Type: application/json

{
  "zone_id": "...",
  "enabled": true,
  "dispatch_enabled": true,
  "heatmap_enabled": true,
  "h3_resolution": 8,
  "search_depth_k": 1,
  "surge_enabled": false,
  "surge_threshold": 1.5,
  "surge_cap": 2.0
}
```

#### Get Heatmap
```http
GET /api/admin/dispatch/honeycomb/heatmap?zone_id={uuid}&window=5m
```

Response:
```json
{
  "success": true,
  "data": {
    "cells": [
      {
        "h3_index": "r8_1a2b3c_4d5e6f",
        "center": {"lat": 30.044, "lng": 31.235},
        "supply": 5,
        "demand": 8,
        "imbalance": 1.6,
        "intensity": 0.32,
        "surge_multiplier": 1.1
      }
    ],
    "total_cells": 45,
    "total_supply": 120,
    "total_demand": 85,
    "hotspot_count": 12
  }
}
```

#### Quick Toggle
```http
POST /api/admin/dispatch/honeycomb/toggle
Content-Type: application/json

{
  "zone_id": "...",
  "feature": "dispatch_enabled",
  "value": true
}
```

### Driver Endpoints

#### Get Hotspots
```http
GET /api/driver/honeycomb/hotspots?zone_id={uuid}&lat=30.044&lng=31.235
```

Response:
```json
{
  "success": true,
  "data": {
    "hotspots": [
      {
        "h3_index": "r8_1a2b3c_4d5e6f",
        "center": {"lat": 30.044, "lng": 31.235},
        "demand": 8,
        "supply": 2,
        "incentive": 15.00,
        "distance_km": 1.2
      }
    ]
  }
}
```

#### Get Cell Stats
```http
GET /api/driver/honeycomb/cell?lat=30.044&lng=31.235&zone_id={uuid}
```

Response:
```json
{
  "success": true,
  "data": {
    "enabled": true,
    "current_cell": "r8_1a2b3c_4d5e6f",
    "supply": 3,
    "demand": 5,
    "imbalance": 1.67,
    "is_hotspot": true,
    "nearby_hotspots": [...],
    "suggested_direction": {
      "bearing": 45,
      "distance_km": 0.8,
      "incentive": 10.00
    }
  }
}
```

---

## Integration Guide

### 1. Run Database Migration

```bash
cd rateel
php artisan migrate
```

### 2. Register Service Provider

Add to `config/app.php`:
```php
'providers' => [
    // ...
    Modules\DispatchManagement\Providers\DispatchManagementServiceProvider::class,
],
```

Or update `modules_statuses.json`:
```json
{
    "DispatchManagement": true
}
```

### 3. Use in TripRequestService

Option A: Use the trait directly:

```php
use Modules\TripManagement\Traits\HoneycombDispatchTrait;

class TripRequestService extends BaseService
{
    use HoneycombDispatchTrait;
    
    public function makeRideRequest($request, $pickupCoordinates): mixed
    {
        // ... existing code ...
        
        // Replace findNearestDriver with honeycomb version
        $find_drivers = $this->findNearestDriverWithHoneycomb(
            latitude: $pickupCoordinates[0],
            longitude: $pickupCoordinates[1],
            zoneId: $request->header('zoneId'),
            radius: $search_radius,
            vehicleCategoryId: $request->vehicle_category_id,
            femaleOnly: $femaleOnly
        );
        
        // ... rest of the code ...
    }
}
```

Option B: Direct service usage:

```php
$honeycombService = app(\App\Services\HoneycombService::class);

// Check if honeycomb is enabled
$settings = $honeycombService->getSettings($zoneId);

if ($settings && $settings['dispatch_enabled']) {
    // Get candidates from cells
    $candidateIds = $honeycombService->getCandidateDrivers(
        $pickupLat, $pickupLng, $zoneId
    );
    
    // Query only these candidates
    $drivers = User::whereIn('id', $candidateIds)->get();
} else {
    // Fallback to regular search
    $drivers = $this->findNearestDriver(...);
}
```

### 4. Node.js Integration

Update `realtime-service/src/server.js`:

```javascript
const HoneycombService = require('./services/HoneycombService');

// Initialize
const honeycombService = new HoneycombService(redisClient, settingsManager);

// When driver location updates
socket.on('driver:location', async (data) => {
    // ... existing location update ...
    
    // Update honeycomb cell
    await honeycombService.updateDriverCell(
        userId,
        data.latitude,
        data.longitude,
        data.zoneId,
        driverCategory
    );
});

// When dispatching a ride
const candidates = await honeycombService.getCandidateDrivers(
    pickupLat, pickupLng, zoneId
);

if (candidates.enabled && candidates.drivers.length > 0) {
    // Filter nearby drivers by honeycomb candidates
    nearbyDrivers = nearbyDrivers.filter(d => 
        candidates.drivers.includes(d.driverId)
    );
}
```

---

## H3 Resolution Guide

| Resolution | Hex Edge | Hex Area | Use Case |
|------------|----------|----------|----------|
| 7 | ~2.6 km | ~5.16 km² | City-level, sparse areas |
| **8** | ~0.98 km | ~0.74 km² | **Recommended for dispatch** |
| 9 | ~0.37 km | ~0.11 km² | Dense urban, walking distance |

**Recommendation**: Start with resolution **8** for most cities.

---

## Rollout Plan

### Phase 1: Heatmap Only (Current)
- Enable `heatmap_enabled`
- No dispatch changes
- Validate data accuracy

### Phase 2: Dispatch Filtering
- Enable `dispatch_enabled`
- Monitor acceptance rates
- Compare performance metrics

### Phase 3: Driver Hotspots
- Enable `hotspots_enabled`
- Show demand heatmap in driver app
- Add suggested repositioning

### Phase 4: Surge Pricing (Optional)
- Enable `surge_enabled`
- Configure thresholds and caps
- Monitor pricing fairness

---

## Performance Expectations

| Metric | Without Honeycomb | With Honeycomb |
|--------|-------------------|----------------|
| Driver search time | 200-500ms | 20-50ms |
| Candidates scanned | 100+ drivers | 10-20 drivers |
| Redis calls | 1-3 | 5-10 (but simpler) |
| Memory per zone | N/A | ~1MB per 1000 cells |

---

## Files Created

```
rateel/
├── database/migrations/
│   └── 2025_12_28_000001_create_honeycomb_system_tables.php
├── app/Services/
│   └── HoneycombService.php
├── Modules/DispatchManagement/
│   ├── Entities/
│   │   ├── HoneycombSetting.php
│   │   └── HoneycombCellMetric.php
│   ├── Http/Controllers/Api/
│   │   ├── Admin/HoneycombController.php
│   │   └── Driver/HoneycombController.php
│   ├── Providers/
│   │   └── DispatchManagementServiceProvider.php
│   └── Routes/
│       └── honeycomb.php
├── Modules/TripManagement/Traits/
│   └── HoneycombDispatchTrait.php

realtime-service/src/services/
└── HoneycombService.js
```

---

## Next Steps

1. **Run migration**: `php artisan migrate`
2. **Register service provider**
3. **Test in staging** with heatmap only
4. **Enable dispatch** in one zone
5. **Monitor metrics** and adjust parameters
6. **Rollout to all zones**

---

## Troubleshooting

### Honeycomb not affecting dispatch
- Check `enabled` AND `dispatch_enabled` are both `true`
- Verify Redis connectivity
- Check settings cache: `redis-cli GET hc:settings:{zoneId}`

### Empty heatmap
- Ensure drivers are updating locations
- Check `hc:drivers:{zoneId}:*` keys exist
- Lower `min_drivers_to_color_cell` temporarily

### Settings not updating
- Clear cache: Call `HoneycombService::clearSettingsCache($zoneId)`
- Check Redis pub/sub for `dispatch.config.updated` channel
- Verify Node.js is subscribed to config updates

---

## References

- [Uber H3 Blog Post](https://www.uber.com/blog/h3/)
- [H3 Documentation](https://h3geo.org/)
- [DiDi Dispatch Research](https://www.didiglobal.com/research)
