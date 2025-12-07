# API Endpoints Summary

This document lists all the location and map-related API endpoints available in the Rateel API Collection.

## Customer Config APIs

All customer endpoints are prefixed with: `{{base_url}}/api/customer/config/`

### 1. Get Zone ID
**Endpoint:** `GET /api/customer/config/get-zone-id`

**Description:** Get the zone ID for a given latitude and longitude.

**Parameters:**
- `lat` (required) - Latitude
- `lng` (required) - Longitude

**Example:**
```
{{base_url}}/api/customer/config/get-zone-id?lat={{latitude}}&lng={{longitude}}
```

**Postman Variable Examples:**
- `latitude`: `30.0444`
- `longitude`: `31.2357`

---

### 2. Geocode API (Reverse Geocode)
**Endpoint:** `GET /api/customer/config/geocode-api`

**Description:** Convert coordinates to address (reverse geocoding).

**Parameters:**
- `lat` (required) - Latitude
- `lng` (required) - Longitude
- `key` (optional) - GeoLink API key for testing. If not provided, uses the key configured in admin settings.

**Example:**
```
{{base_url}}/api/customer/config/geocode-api?lat={{latitude}}&lng={{longitude}}
```

**Example with manual API key:**
```
{{base_url}}/api/customer/config/geocode-api?lat={{latitude}}&lng={{longitude}}&key={{geolink_api_key}}
```

**Response includes `_debug` field for troubleshooting:**
```json
{
  "data": {
    "results": [...],
    "status": "OK",
    "_debug": {
      "geolink_response": {...},
      "http_status": 200,
      "has_data_key": true,
      "data_structure": [...]
    }
  }
}
```

---

### 3. Place API Autocomplete (Search Location)
**Endpoint:** `GET /api/customer/config/place-api-autocomplete`

**Description:** Search for places and get autocomplete suggestions.

**Parameters:**
- `search_text` (required) - Search query text

**Example:**
```
{{base_url}}/api/customer/config/place-api-autocomplete?search_text=Cairo, Egypt
```

---

### 4. Place API Details
**Endpoint:** `GET /api/customer/config/place-api-details`

**Description:** Get detailed information about a specific place.

**Parameters:**
- `placeid` (required) - Place ID from autocomplete results

**Example:**
```
{{base_url}}/api/customer/config/place-api-details?placeid={{place_id}}
```

**Postman Variable Example:**
- `place_id`: (obtained from place-api-autocomplete response)

---

### 5. Distance API
**Endpoint:** `GET /api/customer/config/distance_api`

**Description:** Calculate distance and duration between two points.

**Parameters:**
- `origin_lat` (required) - Origin latitude
- `origin_lng` (required) - Origin longitude
- `destination_lat` (required) - Destination latitude
- `destination_lng` (required) - Destination longitude
- `mode` (required) - Travel mode (e.g., "driving")

**Example:**
```
{{base_url}}/api/customer/config/distance_api?origin_lat={{origin_lat}}&origin_lng={{origin_lng}}&destination_lat={{destination_lat}}&destination_lng={{destination_lng}}&mode=driving
```

**Postman Variable Examples:**
- `origin_lat`: `30.0444`
- `origin_lng`: `31.2357`
- `destination_lat`: `30.0626`
- `destination_lng`: `31.2497`

---

### 6. Get Routes
**Endpoint:** `POST /api/customer/config/get-routes`

**Description:** Get detailed route information with polylines.

**Parameters (form-data):**
- `origin_lat` (required)
- `origin_lng` (required)
- `destination_lat` (required)
- `destination_lng` (required)

**Example:**
```
POST {{base_url}}/api/customer/config/get-routes
Content-Type: multipart/form-data

origin_lat={{origin_lat}}
origin_lng={{origin_lng}}
destination_lat={{destination_lat}}
destination_lng={{destination_lng}}
```

---

## Driver Config APIs

All driver endpoints are prefixed with: `{{base_url}}/api/driver/config/`

The driver endpoints mirror the customer endpoints with the same parameters:

1. `GET /api/driver/config/get-zone-id`
2. `GET /api/driver/config/geocode-api`
3. `GET /api/driver/config/place-api-autocomplete`
4. `GET /api/driver/config/place-api-details`
5. `GET /api/driver/config/distance_api`

---

## Postman Environment Variables

Make sure you have these variables set in your Postman environment:

### Required Variables:
- `base_url` - Your API base URL (e.g., `http://localhost:8000` or `https://your-domain.com`)
- `latitude` - Test latitude (e.g., `30.0444` for Cairo)
- `longitude` - Test longitude (e.g., `31.2357` for Cairo)

### Optional Variables:
- `geolink_api_key` - GeoLink API key for manual testing (e.g., `4a3eb528-befa-4300-860d-9442ae141310`)
- `place_id` - Place ID from autocomplete results
- `origin_lat` - Origin latitude for distance/route calculations
- `origin_lng` - Origin longitude for distance/route calculations
- `destination_lat` - Destination latitude for distance/route calculations
- `destination_lng` - Destination longitude for distance/route calculations

### Cairo Test Coordinates:
```json
{
  "latitude": "30.0444",
  "longitude": "31.2357",
  "origin_lat": "30.0444",
  "origin_lng": "31.2357",
  "destination_lat": "30.0626",
  "destination_lng": "31.2497"
}
```

---

## Testing Tips

1. **Test Geocode API First:** Start with the geocode API to verify your GeoLink integration is working:
   ```
   GET {{base_url}}/api/customer/config/geocode-api?lat=30.0444&lng=31.2357
   ```
   Check the `_debug` field in the response to see the raw GeoLink API response.

2. **Check Logs:** If you get `ZERO_RESULTS`, check your Laravel logs at `storage/logs/laravel.log` for detailed error messages.

3. **Verify API Key:** Make sure your GeoLink API key is configured in:
   - Admin Panel → Business Settings → Third Party → Map API → `map_api_key_server`

4. **Test Zone Coverage:** Use the `get-zone-id` endpoint to verify your coordinates fall within a defined zone.

5. **Sequential Testing:** Test in this order:
   - Get Zone ID
   - Geocode API (reverse geocode)
   - Place API Autocomplete
   - Place API Details
   - Distance API
   - Get Routes

---

## Common Issues

### Issue: `ZERO_RESULTS` Response
**Possible Causes:**
- API key not configured
- Invalid coordinates
- Coordinates outside service area
- GeoLink API service issue

**Solution:**
- Check `_debug` field in response
- Verify API key in admin panel
- Check Laravel logs
- Test with known good coordinates (Cairo: 30.0444, 31.2357)

### Issue: 404 Not Found
**Possible Causes:**
- Wrong endpoint URL
- Typo in endpoint path

**Solution:**
- Verify endpoint path matches this document
- Check for `distance_api` (underscore) not `distance-api` (hyphen)

### Issue: 400 Bad Request
**Possible Causes:**
- Missing required parameters
- Invalid parameter format

**Solution:**
- Check all required parameters are provided
- Verify parameter names match exactly

---

## GeoLink API Integration

These endpoints use **GeoLink API v2** (https://geolink-eg.com) instead of Google Maps.

**API Base URI:** `https://geolink-eg.com`

**Endpoints Used:**
- `/api/v2/reverse_geocode` - For geocode-api
- `/api/v2/text_search` - For place-api-autocomplete
- `/api/v2/geocode` - For place-api-details
- `/api/v2/directions` - For distance_api and get-routes

For more information, see `GEOLINK_MIGRATION_GUIDE.md`.

---

**Last Updated:** December 7, 2025

