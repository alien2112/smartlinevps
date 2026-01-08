# Driver Destination Preferences API

## 📍 Overview

Drivers can set up to **3 preferred destinations** and enable filtering to receive only trips heading towards those destinations.

**Base URL:** `https://smartline-it.com`

---

## 🎯 Features

- ✅ Set up to **3 destination preferences**
- ✅ Each destination has a **radius** (1-15 km)
- ✅ **Enable/disable** filtering
- ✅ Only receive trips going to your preferred destinations
- ✅ **Update** or **delete** destinations anytime

---

## 📋 Endpoints Summary

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/driver/destination-preferences` | Get all destinations |
| POST | `/api/driver/destination-preferences` | Add new destination (max 3) |
| PUT | `/api/driver/destination-preferences/{id}` | Update destination |
| DELETE | `/api/driver/destination-preferences/{id}` | Delete destination |
| PATCH | `/api/driver/destination-preferences/toggle-filter` | Toggle filter on/off |
| PATCH | `/api/driver/destination-preferences/set-filter` | Set filter status |
| PATCH | `/api/driver/destination-preferences/set-radius` | Update default radius |

---

## 🔐 Authentication

All endpoints require authentication:
```
Authorization: Bearer {driver_access_token}
```

---

## 1️⃣ Get Destination Preferences

### Endpoint
```
GET /api/driver/destination-preferences
```

### Description
Retrieve all destination preferences and filter settings.

### Request
```bash
curl -X GET "https://smartline-it.com/api/driver/destination-preferences" \
  -H "Authorization: Bearer {your_access_token}"
```

### Success Response (200)
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "filter_enabled": true,
    "default_radius_km": 5,
    "destinations": [
      {
        "id": 1,
        "latitude": 31.2001,
        "longitude": 29.9187,
        "address": "Alexandria, Egypt",
        "radius_km": 5,
        "created_at": "2026-01-08T10:30:00.000000Z"
      },
      {
        "id": 2,
        "latitude": 30.0444,
        "longitude": 31.2357,
        "address": "Cairo, Egypt",
        "radius_km": 10,
        "created_at": "2026-01-08T11:00:00.000000Z"
      }
    ],
    "count": 2,
    "max_destinations": 3
  }
}
```

---

## 2️⃣ Add New Destination

### Endpoint
```
POST /api/driver/destination-preferences
```

### Description
Add a new destination preference (maximum 3 destinations).

### Request Parameters

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `latitude` | number | ✅ Yes | Latitude (-90 to 90) |
| `longitude` | number | ✅ Yes | Longitude (-180 to 180) |
| `address` | string | ❌ No | Human-readable address (max 500 chars) |
| `radius_km` | number | ❌ No | Radius in km (1-15, default: 5) |

### Request Example
```bash
curl -X POST "https://smartline-it.com/api/driver/destination-preferences" \
  -H "Authorization: Bearer {your_access_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "latitude": 31.2001,
    "longitude": 29.9187,
    "address": "Alexandria, Egypt",
    "radius_km": 5
  }'
```

### Success Response (200)
```json
{
  "response_code": "default_store_200",
  "message": "Successfully stored",
  "data": {
    "filter_enabled": true,
    "default_radius_km": 5,
    "destinations": [
      {
        "id": 1,
        "latitude": 31.2001,
        "longitude": 29.9187,
        "address": "Alexandria, Egypt",
        "radius_km": 5,
        "created_at": "2026-01-08T10:30:00.000000Z"
      }
    ],
    "count": 1,
    "max_destinations": 3
  }
}
```

### Error Response - Max Destinations (400)
```json
{
  "response_code": "default_400",
  "message": "Maximum 3 destination preferences allowed"
}
```

---

## 3️⃣ Update Destination

### Endpoint
```
PUT /api/driver/destination-preferences/{id}
```

### Description
Update an existing destination preference.

### URL Parameters
- `id` (integer) - Destination preference ID

### Request Parameters
Same as Add New Destination

### Request Example
```bash
curl -X PUT "https://smartline-it.com/api/driver/destination-preferences/1" \
  -H "Authorization: Bearer {your_access_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "latitude": 31.2100,
    "longitude": 29.9200,
    "address": "Alexandria Downtown, Egypt",
    "radius_km": 8
  }'
```

### Success Response (200)
```json
{
  "response_code": "default_update_200",
  "message": "Successfully updated",
  "data": {
    "filter_enabled": true,
    "default_radius_km": 5,
    "destinations": [...],
    "count": 1,
    "max_destinations": 3
  }
}
```

### Error Response - Not Found (404)
```json
{
  "response_code": "default_404",
  "message": "Destination preference not found"
}
```

---

## 4️⃣ Delete Destination

### Endpoint
```
DELETE /api/driver/destination-preferences/{id}
```

### Description
Remove a destination preference.

### URL Parameters
- `id` (integer) - Destination preference ID

### Request Example
```bash
curl -X DELETE "https://smartline-it.com/api/driver/destination-preferences/1" \
  -H "Authorization: Bearer {your_access_token}"
```

### Success Response (200)
```json
{
  "response_code": "default_delete_200",
  "message": "Successfully deleted",
  "data": {
    "filter_enabled": true,
    "default_radius_km": 5,
    "destinations": [],
    "count": 0,
    "max_destinations": 3
  }
}
```

---

## 5️⃣ Toggle Destination Filter

### Endpoint
```
PATCH /api/driver/destination-preferences/toggle-filter
```

### Description
Toggle destination filtering on/off. If enabled and destinations are set, driver will only receive trips going to those destinations.

### Request Example
```bash
curl -X PATCH "https://smartline-it.com/api/driver/destination-preferences/toggle-filter" \
  -H "Authorization: Bearer {your_access_token}"
```

### Success Response (200)
```json
{
  "response_code": "default_status_update_200",
  "message": "Destination filter enabled",
  "data": {
    "filter_enabled": true,
    "default_radius_km": 5,
    "destinations": [...],
    "count": 2,
    "max_destinations": 3
  }
}
```

---

## 6️⃣ Set Filter Status

### Endpoint
```
PATCH /api/driver/destination-preferences/set-filter
```

### Description
Explicitly set destination filter to enabled or disabled.

### Request Parameters

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `enabled` | boolean | ✅ Yes | `true` to enable, `false` to disable |

### Request Example
```bash
curl -X PATCH "https://smartline-it.com/api/driver/destination-preferences/set-filter" \
  -H "Authorization: Bearer {your_access_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "enabled": true
  }'
```

### Success Response (200)
```json
{
  "response_code": "default_status_update_200",
  "message": "Destination filter enabled",
  "data": {
    "filter_enabled": true,
    "default_radius_km": 5,
    "destinations": [...],
    "count": 2,
    "max_destinations": 3
  }
}
```

---

## 7️⃣ Set Default Radius

### Endpoint
```
PATCH /api/driver/destination-preferences/set-radius
```

### Description
Update the default radius for destination preferences.

### Request Parameters

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `radius_km` | number | ✅ Yes | Radius in km (1-15) |

### Request Example
```bash
curl -X PATCH "https://smartline-it.com/api/driver/destination-preferences/set-radius" \
  -H "Authorization: Bearer {your_access_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "radius_km": 10
  }'
```

### Success Response (200)
```json
{
  "response_code": "default_update_200",
  "message": "Default radius updated to 10 km",
  "data": {
    "filter_enabled": true,
    "default_radius_km": 10,
    "destinations": [...],
    "count": 2,
    "max_destinations": 3
  }
}
```

---

## 🔄 Common Workflows

### Workflow 1: Set Up First Destination
```bash
# 1. Add destination
curl -X POST "https://smartline-it.com/api/driver/destination-preferences" \
  -H "Authorization: Bearer {token}" \
  -d '{"latitude": 31.2001, "longitude": 29.9187, "address": "Alexandria", "radius_km": 5}'

# 2. Enable filter
curl -X PATCH "https://smartline-it.com/api/driver/destination-preferences/set-filter" \
  -H "Authorization: Bearer {token}" \
  -d '{"enabled": true}'
```

### Workflow 2: Add Multiple Destinations
```bash
# Add destination 1
curl -X POST "..." -d '{"latitude": 31.2001, "longitude": 29.9187, "address": "Alexandria"}'

# Add destination 2
curl -X POST "..." -d '{"latitude": 30.0444, "longitude": 31.2357, "address": "Cairo"}'

# Add destination 3
curl -X POST "..." -d '{"latitude": 30.5852, "longitude": 31.5048, "address": "Zagazig"}'
```

### Workflow 3: Update Existing Destination
```bash
# Get all destinations
curl -X GET "https://smartline-it.com/api/driver/destination-preferences" \
  -H "Authorization: Bearer {token}"

# Update destination with ID 1
curl -X PUT "https://smartline-it.com/api/driver/destination-preferences/1" \
  -H "Authorization: Bearer {token}" \
  -d '{"latitude": 31.2100, "longitude": 29.9200, "address": "Updated Address", "radius_km": 8}'
```

### Workflow 4: Temporarily Disable Filter
```bash
# Disable filter (keep destinations)
curl -X PATCH "https://smartline-it.com/api/driver/destination-preferences/set-filter" \
  -H "Authorization: Bearer {token}" \
  -d '{"enabled": false}'

# Later, re-enable
curl -X PATCH "https://smartline-it.com/api/driver/destination-preferences/set-filter" \
  -H "Authorization: Bearer {token}" \
  -d '{"enabled": true}'
```

---

## 💡 Use Cases

### Use Case 1: Going Home After Work
**Scenario:** Driver wants trips heading home

```bash
# Set home as destination
curl -X POST "https://smartline-it.com/api/driver/destination-preferences" \
  -H "Authorization: Bearer {token}" \
  -d '{
    "latitude": 31.2001,
    "longitude": 29.9187,
    "address": "Home - Alexandria",
    "radius_km": 3
  }'

# Enable filter
curl -X PATCH ".../set-filter" -d '{"enabled": true}'
```

### Use Case 2: Multiple Regular Destinations
**Scenario:** Driver goes between home, work, and school

```bash
# Home
POST .../destination-preferences {"latitude": 31.2001, "longitude": 29.9187, "address": "Home"}

# Work
POST .../destination-preferences {"latitude": 30.0444, "longitude": 31.2357, "address": "Work"}

# School (to pick up kids)
POST .../destination-preferences {"latitude": 30.5852, "longitude": 31.5048, "address": "School"}
```

### Use Case 3: Weekend vs Weekday
**Scenario:** Different destinations on different days

```bash
# Enable on Friday afternoon (going to weekend house)
PATCH .../set-filter {"enabled": true}

# Disable on Monday morning (accept all trips)
PATCH .../set-filter {"enabled": false}
```

---

## ⚠️ Important Notes

### Limits & Restrictions:
- ✅ **Maximum 3 destinations** per driver
- ✅ **Radius range**: 1-15 km
- ✅ **Latitude range**: -90 to 90
- ✅ **Longitude range**: -180 to 180
- ✅ **Address max length**: 500 characters

### How Filtering Works:
1. Driver enables destination filter
2. Trip request comes in
3. System checks trip **destination** coordinates
4. If destination is within radius of any driver's preferred destinations → driver receives trip
5. If destination is outside all radiuses → driver does NOT receive trip

### Filter Behavior:
- 🔵 **Filter enabled + destinations set** → Receive only matching trips
- 🔵 **Filter enabled + no destinations** → Receive all trips (filter has no effect)
- 🔵 **Filter disabled** → Receive all trips regardless of destinations

---

## 🧪 Testing

### Complete Test Flow:
```bash
TOKEN="your-driver-token"
BASE_URL="https://smartline-it.com/api/driver/destination-preferences"

# 1. Get current preferences
curl -X GET "$BASE_URL" -H "Authorization: Bearer $TOKEN"

# 2. Add first destination
curl -X POST "$BASE_URL" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"latitude": 31.2001, "longitude": 29.9187, "address": "Alexandria", "radius_km": 5}'

# 3. Add second destination
curl -X POST "$BASE_URL" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"latitude": 30.0444, "longitude": 31.2357, "address": "Cairo", "radius_km": 10}'

# 4. Enable filter
curl -X PATCH "$BASE_URL/set-filter" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"enabled": true}'

# 5. Update first destination
curl -X PUT "$BASE_URL/1" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"latitude": 31.2100, "longitude": 29.9200, "address": "Alexandria Downtown", "radius_km": 8}'

# 6. Toggle filter
curl -X PATCH "$BASE_URL/toggle-filter" \
  -H "Authorization: Bearer $TOKEN"

# 7. Delete first destination
curl -X DELETE "$BASE_URL/1" \
  -H "Authorization: Bearer $TOKEN"

# 8. Check final state
curl -X GET "$BASE_URL" -H "Authorization: Bearer $TOKEN"
```

---

## 📱 Mobile App Integration

### React Native Example:

```javascript
// Add destination
const addDestination = async (latitude, longitude, address, radius_km = 5) => {
  try {
    const response = await fetch(
      'https://smartline-it.com/api/driver/destination-preferences',
      {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${accessToken}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          latitude,
          longitude,
          address,
          radius_km,
        }),
      }
    );
    
    const result = await response.json();
    console.log('Destination added:', result);
    return result;
  } catch (error) {
    console.error('Error adding destination:', error);
  }
};

// Toggle filter
const toggleFilter = async () => {
  const response = await fetch(
    'https://smartline-it.com/api/driver/destination-preferences/toggle-filter',
    {
      method: 'PATCH',
      headers: {
        'Authorization': `Bearer ${accessToken}`,
      },
    }
  );
  return await response.json();
};
```

---

## 📚 Related Documentation

- Driver API Documentation
- Trip Management API
- Location Tracking API

---

**Created:** 2026-01-08  
**API Version:** v1  
**Status:** ✅ Production Ready  
**Max Destinations:** 3  
**Radius Range:** 1-15 km
