# Place ID Fix for GeoLink API Integration

## Problem

The `place-api-details` endpoint was failing with a 400 error:

```json
{
    "response_code": "default_400",
    "message": "Invalid or missing information",
    "errors": {
        "message": "Failed to fetch place details from map service",
        "status_code": 400,
        "debug": {
            "placeid": "6935bef60a59a",
            "response": "{\"error\":\"query is a required parameter\",\"success\":false}\n"
        }
    }
}
```

## Root Cause

**GeoLink's `/api/v2/geocode` endpoint does NOT support the `place_id` parameter** like Google Maps does. The error shows:

```json
"error": "query is a required parameter"
```

The endpoint was trying to call:

```
GET https://geolink-eg.com/api/v2/geocode?place_id={id}&key={key}
```

But GeoLink's geocode endpoint expects a **`query` parameter** (text search), not a `place_id`.

## Solution Implemented

### 1. Enhanced Autocomplete Response

Modified the `placeApiAutocomplete` method to include coordinates AND search query in the response, creating smart `place_id` values:

- If GeoLink provides a `place_id`, use it
- If no `place_id` but coordinates exist, encode coordinates + metadata as base64 JSON:
  ```php
  base64_encode(json_encode([
      'lat' => $lat,
      'lng' => $lng,
      'name' => $name,
      'address' => $address
  ]))
  ```
- If no coordinates, encode search query:
  ```php
  base64_encode(json_encode(['query' => $name ?: $address]))
  ```
- Include `geometry.location` in each prediction for direct coordinate access

**Files Updated:**
- `Modules/BusinessManagement/Http/Controllers/Api/New/Customer/ConfigController.php`
- `Modules/BusinessManagement/Http/Controllers/Api/New/Driver/ConfigController.php`
- `rateel/Modules/BusinessManagement/Http/Controllers/Api/New/Customer/ConfigController.php`
- `rateel/Modules/BusinessManagement/Http/Controllers/Api/New/Driver/ConfigController.php`

### 2. Smart Place Details Lookup

Modified the `placeApiDetails` method to intelligently route to the correct GeoLink endpoint:

1. **Decode the `place_id`** to check if it contains encoded data
2. **Case 1 - Coordinates found**: Use GeoLink's `/api/v2/reverse_geocode` endpoint
3. **Case 2 - Query found**: Use GeoLink's `/api/v2/text_search` endpoint
4. **Case 3 - Direct place_id**: Use `/api/v2/geocode` with `query` parameter (not `place_id`)
5. **Better error logging**: Log detailed information about which endpoint was used and any failures

### 3. Enhanced Error Handling

- Added timeout (30 seconds) to API calls
- Added detailed error logging with request parameters
- Added debug information in error responses
- Preserve original `place_id` in transformed response

## How It Works Now

### Flow 1: Autocomplete → Place Details (with encoded coordinates)

```
1. User searches: "Cairo Tower"
   GET /api/customer/config/place-api-autocomplete?search_text=Cairo Tower

2. Response includes:
   {
     "predictions": [{
       "place_id": "eyJsYXQiOjMwLjA0NiwibG5nIjozMS4yMzU3LCJuYW1lIjoiQ2Fpcm8gVG93ZXIiLCJhZGRyZXNzIjoiQ2Fpcm8sIEVneXB0In0=",
       "description": "Cairo, Egypt",
       "structured_formatting": {
         "main_text": "Cairo Tower",
         "secondary_text": "Cairo, Egypt"
       },
       "geometry": {
         "location": { "lat": 30.046, "lng": 31.2357 }
       }
     }]
   }

3. User requests details with encoded place_id:
   GET /api/customer/config/place-api-details?placeid=eyJsYXQi...

4. Backend decodes place_id → extracts coordinates → calls:
   GET https://geolink-eg.com/api/v2/reverse_geocode?latitude=30.046&longitude=31.2357&key={key}

5. Returns full place details with coordinates
```

### Flow 2: Autocomplete → Place Details (with query fallback)

```
1. If GeoLink text_search returns results WITHOUT coordinates:
   place_id = base64_encode(json_encode(['query' => 'Cairo Tower']))

2. User requests details:
   GET /api/customer/config/place-api-details?placeid={encoded_query}

3. Backend decodes → finds 'query' → calls:
   GET https://geolink-eg.com/api/v2/text_search?query=Cairo Tower&key={key}

4. Takes first result and returns place details
```

### Flow 3: Direct place_id (if GeoLink provides one)

```
1. If GeoLink returns a real place_id in text_search response
2. Backend uses it with /api/v2/geocode?query={place_id}&key={key}
3. Note: Uses 'query' parameter, not 'place_id'
```

## Benefits

✅ **Backward Compatible**: Works with any `place_id` format  
✅ **Future Proof**: Will work if GeoLink adds native `place_id` support  
✅ **Better Debugging**: Detailed error logs and debug info in responses  
✅ **Coordinates Available**: Autocomplete response now includes lat/lng directly  
✅ **No Breaking Changes**: Existing API contract maintained

## Testing

### Test the autocomplete endpoint:

```bash
GET {{base_url}}/api/customer/config/place-api-autocomplete?search_text=Cairo
```

Expected response includes `geometry.location` with coordinates.

### Test the place details endpoint:

```bash
# Use a place_id from the autocomplete response
GET {{base_url}}/api/customer/config/place-api-details?placeid={place_id_from_autocomplete}
```

Expected: Full place details with coordinates.

### Test with manual coordinates:

```bash
# Create a base64 encoded place_id manually
# echo -n '{"lat":30.0444,"lng":31.2357}' | base64
# Result: eyJsYXQiOjMwLjA0NDQsImxuZyI6MzEuMjM1N30=

GET {{base_url}}/api/customer/config/place-api-details?placeid=eyJsYXQiOjMwLjA0NDQsImxuZyI6MzEuMjM1N30=
```

## Code Changes Summary

### transformTextSearchResponse() - Enhanced

```php
// OLD: Simple place_id assignment with uniqid() fallback
'place_id' => $result['place_id'] ?? ($result['id'] ?? uniqid())

// NEW: Smart place_id with coordinate OR query encoding
$lat = $result['lat'] ?? $result['latitude'] ?? null;
$lng = $result['lng'] ?? $result['longitude'] ?? null;
$placeId = $result['place_id'] ?? $result['id'] ?? null;
$name = $result['name'] ?? '';
$address = $result['formatted_address'] ?? $result['address'] ?? '';

if (!$placeId) {
    if ($lat && $lng) {
        // Encode coordinates + metadata
        $placeId = base64_encode(json_encode([
            'lat' => $lat,
            'lng' => $lng,
            'name' => $name,
            'address' => $address
        ]));
    } else {
        // Encode query for text search fallback
        $placeId = base64_encode(json_encode([
            'query' => $name ?: $address
        ]));
    }
}

// Also includes geometry in response
'geometry' => [
    'location' => ['lat' => $lat, 'lng' => $lng]
]
```

### placeApiDetails() - Smart Routing (3 Cases)

```php
// Decode place_id to check for encoded data
$decodedData = null;
$decoded = base64_decode($placeId, true);
if ($decoded !== false) {
    $jsonData = json_decode($decoded, true);
    if (is_array($jsonData)) {
        $decodedData = $jsonData;
    }
}

// Route to appropriate endpoint based on decoded data
if ($decodedData && isset($decodedData['lat']) && isset($decodedData['lng'])) {
    // Case 1: Coordinates - use reverse geocode
    $response = Http::timeout(30)->get(MAP_API_BASE_URI . '/api/v2/reverse_geocode', [
        'latitude' => $decodedData['lat'],
        'longitude' => $decodedData['lng'],
        'key' => $mapKey
    ]);
} elseif ($decodedData && isset($decodedData['query'])) {
    // Case 2: Query - use text search
    $response = Http::timeout(30)->get(MAP_API_BASE_URI . '/api/v2/text_search', [
        'query' => $decodedData['query'],
        'key' => $mapKey
    ]);
} else {
    // Case 3: Direct place_id - use geocode with 'query' parameter
    $response = Http::timeout(30)->get(MAP_API_BASE_URI . '/api/v2/geocode', [
        'query' => $placeId,  // IMPORTANT: Use 'query' not 'place_id'
        'key' => $mapKey
    ]);
}
```

### transformPlaceDetailsResponse() - Handle Multiple Response Types

```php
// Handle array of results (from text_search) - take first result
if (is_array($data) && isset($data[0]) && is_array($data[0])) {
    $data = $data[0];
}
```

## Migration Notes

- **No database changes required**
- **No frontend changes required** (API contract unchanged)
- **Existing place_id values** will be handled gracefully
- **Logging enhanced** for better debugging

## Related Documentation

- See `GEOLINK_MIGRATION_GUIDE.md` for overall GeoLink migration details
- See `postman/API_ENDPOINTS_SUMMARY.md` for API testing examples

