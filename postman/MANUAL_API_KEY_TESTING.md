# Manual API Key Testing Guide

This guide explains how to manually test the Geocode API with different API keys in Postman.

## Overview

The Geocode API endpoint now supports an optional `key` parameter that allows you to override the API key configured in the admin panel. This is useful for:
- Testing different API keys
- Debugging API key issues
- Testing without configuring the admin panel

## How to Use

### Method 1: Using Postman Variables (Recommended)

1. **Set up the environment variable:**
   - In Postman, go to your environment settings
   - Add a new variable: `geolink_api_key`
   - Set the value to your GeoLink API key (e.g., `4a3eb528-befa-4300-860d-9442ae141310`)

2. **Use the endpoint:**
   ```
   GET {{base_url}}/api/customer/config/geocode-api?lat={{latitude}}&lng={{longitude}}&key={{geolink_api_key}}
   ```

3. **Test:**
   - The `key` parameter is already included in the Postman collection
   - Simply set the `geolink_api_key` variable and run the request

### Method 2: Direct API Key in URL

1. **Replace the variable with your actual API key:**
   ```
   GET {{base_url}}/api/customer/config/geocode-api?lat=30.0444&lng=31.2357&key=4a3eb528-befa-4300-860d-9442ae141310
   ```

2. **Test:**
   - This method is quick for one-off tests
   - Not recommended for sharing collections

### Method 3: Disable the Key Parameter

If you want to use the API key from the admin panel settings:

1. **In Postman, uncheck the `key` parameter:**
   - Open the request
   - Go to the "Params" tab
   - Uncheck the checkbox next to the `key` parameter
   - The URL will become: `{{base_url}}/api/customer/config/geocode-api?lat={{latitude}}&lng={{longitude}}`

2. **Test:**
   - The API will use the key configured in: Admin Panel → Business Settings → Third Party → Map API

## Testing Different Scenarios

### Scenario 1: Test with Valid API Key

```
GET {{base_url}}/api/customer/config/geocode-api?lat=30.0444&lng=31.2357&key=YOUR_VALID_KEY
```

**Expected Response:**
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "results": [
      {
        "formatted_address": "Cairo, Egypt",
        "geometry": {
          "location": {
            "lat": 30.0444,
            "lng": 31.2357
          }
        },
        "address_components": [...],
        "place_id": "..."
      }
    ],
    "status": "OK",
    "_debug": {
      "geolink_response": {...},
      "http_status": 200,
      "has_data_key": true
    }
  }
}
```

### Scenario 2: Test with Invalid API Key

```
GET {{base_url}}/api/customer/config/geocode-api?lat=30.0444&lng=31.2357&key=INVALID_KEY
```

**Expected Response:**
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "results": [],
    "status": "ZERO_RESULTS",
    "_debug": {
      "geolink_response": {
        "error": "Invalid API key"
      },
      "http_status": 401
    }
  }
}
```

### Scenario 3: Test without API Key

```
GET {{base_url}}/api/customer/config/geocode-api?lat=30.0444&lng=31.2357
```

**Expected Response (if not configured in admin):**
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "results": [],
    "status": "ZERO_RESULTS",
    "_debug": {
      "error": "API key not provided"
    }
  }
}
```

## Postman Environment Setup

Create a Postman environment with these variables:

```json
{
  "name": "Rateel Testing",
  "values": [
    {
      "key": "base_url",
      "value": "http://localhost:8000",
      "enabled": true
    },
    {
      "key": "latitude",
      "value": "30.0444",
      "enabled": true
    },
    {
      "key": "longitude",
      "value": "31.2357",
      "enabled": true
    },
    {
      "key": "geolink_api_key",
      "value": "4a3eb528-befa-4300-860d-9442ae141310",
      "enabled": true
    },
    {
      "key": "origin_lat",
      "value": "30.0444",
      "enabled": true
    },
    {
      "key": "origin_lng",
      "value": "31.2357",
      "enabled": true
    },
    {
      "key": "destination_lat",
      "value": "30.0626",
      "enabled": true
    },
    {
      "key": "destination_lng",
      "value": "31.2497",
      "enabled": true
    }
  ]
}
```

## Debugging Tips

### Check the _debug Field

The response includes a `_debug` field with detailed information:

```json
"_debug": {
  "geolink_response": {...},      // Raw response from GeoLink API
  "http_status": 200,              // HTTP status code
  "has_data_key": true,            // Whether 'data' key exists in response
  "data_structure": [...]          // Keys in the data object
}
```

### Common Issues and Solutions

#### Issue: "API key not provided"
**Solution:** Add the `key` parameter to your request or configure it in the admin panel.

#### Issue: "Invalid API key" or 401 status
**Solution:** Verify your API key is correct. The current GeoLink key is: `4a3eb528-befa-4300-860d-9442ae141310`

#### Issue: ZERO_RESULTS with valid key
**Possible causes:**
- Coordinates are outside the service area
- GeoLink API service issue
- Network connectivity problem

**Solution:** 
- Check the `_debug.geolink_response` for error messages
- Test with known good coordinates (Cairo: 30.0444, 31.2357)
- Check Laravel logs at `storage/logs/laravel.log`

### Check Laravel Logs

The API logs detailed information to help with debugging:

```bash
# View recent logs
tail -f storage/logs/laravel.log

# Search for geocode logs
grep "GeoLink reverse geocode" storage/logs/laravel.log
```

Log entries include:
- HTTP status code
- Full API response
- Request parameters
- API URL used

## API Key Priority

The API uses this priority order for the API key:

1. **Request parameter** (`key` in URL) - Highest priority
2. **Admin panel setting** (Business Settings → Third Party → Map API)
3. **None** - Returns error

This allows you to:
- Override the configured key for testing
- Use different keys for different requests
- Test without modifying admin settings

## Security Note

⚠️ **Important:** When sharing Postman collections or screenshots:
- Remove or mask API keys
- Use environment variables instead of hardcoded keys
- Don't commit API keys to version control

## Example Postman Request

Here's a complete example request in Postman:

**Request:**
```
GET {{base_url}}/api/customer/config/geocode-api
```

**Params:**
| Key | Value | Description |
|-----|-------|-------------|
| lat | {{latitude}} | Latitude coordinate |
| lng | {{longitude}} | Longitude coordinate |
| key | {{geolink_api_key}} | Optional: GeoLink API key |

**Headers:**
| Key | Value |
|-----|-------|
| Accept | application/json |

**Environment Variables:**
| Variable | Value |
|----------|-------|
| base_url | http://localhost:8000 |
| latitude | 30.0444 |
| longitude | 31.2357 |
| geolink_api_key | 4a3eb528-befa-4300-860d-9442ae141310 |

---

**Last Updated:** December 7, 2025

