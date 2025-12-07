# Google Maps to GeoLink API Migration Guide

## Overview
This document outlines the changes made to migrate from Google Maps API to **GeoLink API v2** (https://geolink-eg.com).

## Completed Changes

### 1. Backend API Changes

#### ✅ Constants Updated
- **File**: `app\Lib\Constant.php` and `rateel\app\Lib\Constant.php`
- **Changed**: `MAP_API_BASE_URI` from Google Maps to GeoLink
```php
// Old: const MAP_API_BASE_URI = 'https://maps.googleapis.com/maps/api';
// New: const MAP_API_BASE_URI = 'https://geolink-eg.com';
```

#### ✅ Reverse Geocoding API (geocode-api)
**Files Updated:**
- `Modules\BusinessManagement\Http\Controllers\Api\New\Customer\ConfigController.php`
- `Modules\BusinessManagement\Http\Controllers\Api\New\Driver\ConfigController.php`
- `Modules\TripManagement\Service\SafetyAlertService.php`
- All corresponding files in `rateel\` directory

**Changes:**
```php
// OLD Google Maps Format:
$response = Http::get(MAP_API_BASE_URI . '/geocode/json?latlng=' . $lat . ',' . $lng . '&key=' . $apiKey);
$address = $response->json()['results'][0]['formatted_address'];

// NEW GeoLink v2 Format:
$response = Http::get(MAP_API_BASE_URI . '/api/v2/reverse_geocode', [
    'latitude' => $lat,
    'longitude' => $lng,
    'key' => $apiKey
]);
$address = $response->json()['data']['formatted_address'];
```

#### ✅ Place Autocomplete API (place-api-autocomplete / searchLocationUri)
**Changes:**
```php
// OLD Google: /place/autocomplete/json?input={text}&key={key}
// NEW GeoLink v2: /api/v2/text_search?query={text}&key={key}
```

#### ✅ Place Details API (place-api-details / placeApiDetails)
**Changes:**
```php
// OLD Google: /place/details/json?placeid={id}&key={key}
// NEW GeoLink v2: /api/v2/geocode?place_id={id}&key={key}
```

#### ✅ Distance/Directions API (distance-api / getDistanceFromLatLng)
**Changes:**
```php
// OLD Google Distance Matrix API:
// /distancematrix/json?origins=lat,lng&destinations=lat,lng&travelmode=driving&key=...

// NEW GeoLink v2 Directions API:
// /api/v2/directions?origin_latitude=lat&origin_longitude=lng&destination_latitude=lat&destination_longitude=lng&key=...
```

**Response Format Changes:**
- Google: `result['rows'][0]['elements'][0]['distance']['value']`
- GeoLink: `result['data']['distance']` (transformed to Google format)

### 2. Frontend Map Changes

#### ✅ Library Changed
**From**: Google Maps JavaScript API
**To**: Leaflet.js + Geoapify Tiles

**Files Updated:**
- `Modules\AdminModule\Resources\views\partials\dashboard\map.blade.php`
- `rateel\Modules\AdminModule\Resources\views\partials\dashboard\map.blade.php`

**New Libraries Added:**
```html
<!-- Leaflet CSS and JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Geoapify Address Search Plugin -->
<script src="https://unpkg.com/@geoapify/leaflet-address-search-plugin@^1/dist/L.Control.GeoapifyAddressSearch.min.js"></script>
<link rel="stylesheet" href="https://unpkg.com/@geoapify/leaflet-address-search-plugin@^1/dist/L.Control.GeoapifyAddressSearch.min.css" />
```

## Files That Still Need Manual Updates

The following map view files use Google Maps and need to be converted to Leaflet + Geoapify:

### Zone Management Views
1. ✅ `Modules\ZoneManagement\Resources\views\admin\zone\index.blade.php` - **CONVERTED**
2. ✅ `Modules\ZoneManagement\Resources\views\admin\zone\edit.blade.php` - **TO DO**
3. ✅ `rateel\Modules\ZoneManagement\Resources\views\admin\zone\index.blade.php` - **CONVERTED**
4. ✅ `rateel\Modules\ZoneManagement\Resources\views\admin\zone\edit.blade.php` - **TO DO**

### Admin Module Views
5. ✅ `Modules\AdminModule\Resources\views\heat-map.blade.php`
6. ✅ `Modules\AdminModule\Resources\views\heat-map-compare.blade.php`
7. ✅ `Modules\AdminModule\Resources\views\fleet-map.blade.php`
8. ✅ `rateel\Modules\AdminModule\Resources\views\heat-map.blade.php`
9. ✅ `rateel\Modules\AdminModule\Resources\views\heat-map-compare.blade.php`
10. ✅ `rateel\Modules\AdminModule\Resources\views\fleet-map.blade.php`

### Trip Management Views
11. ✅ `Modules\TripManagement\Resources\views\admin\trip\partials\_trip-details-status.blade.php` - **CONVERTED**
12. ✅ `Modules\TripManagement\Resources\views\admin\refund\partials\_trip-details-status.blade.php` - **CONVERTED**
13. ✅ `rateel\Modules\TripManagement\Resources\views\admin\trip\partials\_trip-details-status.blade.php` - **CONVERTED**
14. ✅ `rateel\Modules\TripManagement\Resources\views\admin\refund\partials\_trip-details-status.blade.php` - **CONVERTED**

## Conversion Pattern for Frontend Maps

### Google Maps → Leaflet Equivalents

```javascript
// Map Initialization
// OLD:
map = new google.maps.Map(document.getElementById("map"), {
    center: {lat: 23.757989, lng: 90.360587},
    zoom: 13
});

// NEW:
map = L.map('map').setView([23.757989, 90.360587], 13);
L.tileLayer('https://maps.geoapify.com/v1/tile/osm-bright/{z}/{x}/{y}.png?apiKey=YOUR_API_KEY', {
    attribution: '© Geoapify | © OpenStreetMap',
    maxZoom: 20
}).addTo(map);

// Markers
// OLD:
marker = new google.maps.Marker({
    position: {lat: lat, lng: lng},
    map: map
});

// NEW:
marker = L.marker([lat, lng]).addTo(map);

// Polygons
// OLD:
polygon = new google.maps.Polygon({
    paths: coordinates,
    strokeColor: "#FF0000",
    fillColor: "#FF0000"
});
polygon.setMap(map);

// NEW:
polygon = L.polygon(coordinates, {
    color: '#FF0000',
    fillColor: '#FF0000'
}).addTo(map);

// Polylines
// OLD:
polyline = new google.maps.Polyline({
    path: coordinates,
    strokeColor: "#FF0000"
});

// NEW:
polyline = L.polyline(coordinates, {
    color: '#FF0000'
}).addTo(map);
```

## Configuration Updates Needed

### 1. Admin Panel Settings
Update your Google Map API configuration in the admin panel to use your GeoLink API key:
- Navigate to: Business Settings → Third Party → Map API
- Update API key to: `4a3eb528-befa-4300-860d-9442ae141310`

**Note**: See `UPDATE_GEOLINK_API_KEY.md` for detailed instructions on updating the API key using multiple methods.

### 2. Environment Variables
If you have any environment variables for Google Maps, update them:
```env
# OLD
GOOGLE_MAP_API_KEY=your_old_google_key

# NEW (if using env variables)
GEOAPIFY_API_KEY=4a3eb528-befa-4300-860d-9442ae141310
```

## API Response Format Changes

### Geocoding Response
```javascript
// Google Maps Response:
{
    "results": [{
        "formatted_address": "123 Main St, City, Country",
        "geometry": {
            "location": {"lat": 23.757989, "lng": 90.360587}
        }
    }]
}

// Geoapify Response:
{
    "features": [{
        "properties": {
            "formatted": "123 Main St, City, Country",
            "lat": 23.757989,
            "lon": 90.360587
        }
    }]
}
```

### Routing Response
```javascript
// Google Maps Response:
{
    "routes": [{
        "legs": [{
            "distance": {"value": 5000},  // meters
            "duration": {"value": 600}     // seconds
        }]
    }]
}

// Geoapify Response:
{
    "features": [{
        "properties": {
            "distance": 5000,    // meters
            "time": 600          // seconds
        }
    }]
}
```

## Testing Checklist

- [ ] Test geocoding (address to coordinates)
- [ ] Test reverse geocoding (coordinates to address)
- [ ] Test place autocomplete search
- [ ] Test routing/directions
- [ ] Test map display with zones
- [ ] Test marker placement
- [ ] Test polygon drawing
- [ ] Test fleet tracking map
- [ ] Test heat map visualization
- [ ] Test trip details map view

## Important Notes

1. **API Limits**: Check your Geoapify API plan limits at https://www.geoapify.com/
2. **Coordinate Order**: 
   - Google Maps uses `{lat, lng}` or `(lat, lng)`
   - Leaflet uses `[lat, lng]` (array format)
   - Geoapify API uses `lat,lon` in URLs

3. **HTTPS Required**: Geoapify requires HTTPS for production use

4. **Browser Compatibility**: Leaflet.js supports all modern browsers (IE 11+)

## Support Resources

- **Geoapify Documentation**: https://www.geoapify.com/docs/
- **Leaflet Documentation**: https://leafletjs.com/reference.html
- **Geoapify Geocoding**: https://www.geoapify.com/geocoding-api
- **Geoapify Routing**: https://www.geoapify.com/routing-api
- **Geoapify Maps**: https://www.geoapify.com/maps-api

## Rollback Plan

If you need to rollback to Google Maps:
1. Restore the `MAP_API_BASE_URI` constant in `app\Lib\Constant.php`
2. Restore the original API call formats in controllers
3. Replace Leaflet scripts with Google Maps scripts in blade files
4. Update the API key back to Google Maps key

---

**Migration Date**: December 3, 2025 (Updated to v2: December 7, 2025)
**Current API Key**: 4a3eb528-befa-4300-860d-9442ae141310
**Service**: GeoLink API v2 (https://geolink-eg.com)

**To update the API key**, see `UPDATE_GEOLINK_API_KEY.md` for instructions.

