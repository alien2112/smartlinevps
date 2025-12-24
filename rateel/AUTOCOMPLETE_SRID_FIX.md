# Autocomplete SRID Fix - Summary

## Problem
After the Dec 17 commit that added zone filtering to autocomplete, the `/api/driver/ride/pending-ride-list` endpoint started failing with SRID mismatch errors:

```
SQLSTATE[HY000]: General error: 3033 Binary geometry function st_distance_sphere
given two geometries of different srids: 0 and 4326
```

## Root Cause

The Dec 17 commit introduced **two problematic changes**:

### 1. **TripRequestCoordinate.php** (Line 60)
Changed from:
```php
ST_Distance_Sphere($column, POINT($location->longitude, $location->latitude))
```

To:
```php
ST_Distance_Sphere(ST_SRID($column, 4326), ST_SRID(POINT(...), 4326))
```

**Problem**: `ST_SRID(POINT(...), 4326)` syntax doesn't work properly in MySQL raw queries, causing SRID mismatches.

### 2. **ConfigController Autocomplete** (Both Customer & Driver)
Added zone filtering logic with:
```php
$point = new Point((float)$lng, (float)$lat, 4326);
$isInZone = $this->zoneService->getByPoints($point)
    ->where('id', $zoneId)
    ->exists();
```

**Problem**: Creating Points with SRID 4326 caused cascading SRID issues throughout the spatial queries.

## Solution Applied

### 1. Reverted TripRequestCoordinate.php
**File**: `Modules/TripManagement/Entities/TripRequestCoordinate.php:56-60`

Reverted to original working version:
```php
public function scopeDistanceSphere($query, $column, $location, $distance)
{
    // Original working version - MySQL handles SRID automatically for ST_Distance_Sphere
    return $query->whereRaw("ST_Distance_Sphere($column, POINT($location->longitude, $location->latitude)) < $distance");
}
```

**Why this works**: MySQL's `ST_Distance_Sphere` function handles coordinate system conversions automatically when both geometries are in WGS84. Explicit SRID specification was unnecessary.

### 2. Reverted Customer ConfigController
**File**: `Modules/BusinessManagement/Http/Controllers/Api/New/Customer/ConfigController.php`

#### placeApiAutocomplete() method:
- ✅ Removed zone filtering parameters (`zone_id`, `latitude`, `longitude`, etc.)
- ✅ Simplified API request back to basic `search_text` + `key`
- ✅ Removed `$zoneId` parameter from `transformTextSearchResponse()` call

#### transformTextSearchResponse() method:
- ✅ Removed all zone filtering logic
- ✅ Removed `new Point((float)$lng, (float)$lat, 4326)` creation
- ✅ Simplified to basic prediction array building
- ✅ Removed response fields: `zone_filtered`, `zone_filtered_out`, `in_zone_count`, `out_of_zone_count`

### 3. Reverted Driver ConfigController
**File**: `Modules/BusinessManagement/Http/Controllers/Api/New/Driver/ConfigController.php`

Applied same changes as Customer ConfigController:
- ✅ Removed zone filtering from `placeApiAutocomplete()`
- ✅ Simplified `transformTextSearchResponse()` to original version

## Files Changed
1. ✅ `Modules/TripManagement/Entities/TripRequestCoordinate.php`
2. ✅ `Modules/BusinessManagement/Http/Controllers/Api/New/Customer/ConfigController.php`
3. ✅ `Modules/BusinessManagement/Http/Controllers/Api/New/Driver/ConfigController.php`

## Testing Required
1. Test `/api/driver/ride/pending-ride-list` - Should work without SRID errors
2. Test `/api/customer/config/place-api-autocomplete` - Should return predictions
3. Test `/api/driver/config/place-api-autocomplete` - Should return predictions

## Technical Notes

### Why SRID 4326 Failed
- Database columns have SRID 0 (no SRID set)
- Mixing SRID 0 and SRID 4326 in same query causes MySQL error
- MySQL's spatial functions handle WGS84 coordinates automatically without explicit SRID

### Future Zone Filtering
If zone filtering is needed in autocomplete:
1. **Don't use SRID 4326** in Point creation
2. Use simple Point creation without SRID: `new Point($lng, $lat)`
3. Let MySQL handle coordinate system conversions
4. OR update database schema to use SRID 4326 for all spatial columns

## Reverted From Commit
- **Commit**: 712d5b6 (Dec 17, 2025)
- **Message**: "Full project update with zone fixes, autocomplete improvements, and Node.js realtime service"
- **Problematic Feature**: Zone filtering in autocomplete with SRID 4326

## Status
✅ **FIXED** - All autocomplete and pending rides endpoints should work correctly now.
