# Primary Vehicle Change Approval System

## Overview

When drivers want to switch their primary (active) vehicle to a different vehicle, they must submit a request that requires admin approval. This prevents unauthorized or accidental vehicle switches during active rides or trips.

---

## 🔄 How It Works

### Driver Flow:
1. Driver has multiple approved vehicles
2. Driver requests to set a different vehicle as primary via API
3. Request is marked as **pending** (`has_pending_primary_request = true`)
4. Driver's current primary vehicle remains active
5. Admin reviews and approves/rejects the request
6. If approved, vehicles are switched
7. If rejected, request is cleared and nothing changes

### Admin Flow:
1. View driver's profile at: `https://smartline-it.com/admin/driver/approvals/{driver_id}`
2. See vehicles with pending primary change requests (yellow warning badge)
3. Click **Approve Primary Change** or **Reject Primary Change**
4. Changes take effect immediately upon approval

---

## 📋 API Endpoint

### Request Primary Vehicle Change

**Endpoint:** `POST /api/driver/vehicle/set-primary/{vehicle_id}`

**Authentication:** Required (Bearer token)

**Request:**
```bash
curl -X POST "https://smartline-it.com/api/driver/vehicle/set-primary/a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d" \
  -H "Authorization: Bearer {your_access_token}"
```

**Success Response (200):**
```json
{
  "response_code": "vehicle_primary_pending",
  "message": "Primary vehicle change request submitted. Waiting for admin approval."
}
```

**Error Responses:**

- **Vehicle Not Approved (403):**
  ```json
  {
    "response_code": "vehicle_not_approved",
    "message": "Only approved vehicles can be set as primary"
  }
  ```

- **Vehicle Already Primary (400):**
  ```json
  {
    "response_code": "vehicle_already_primary",
    "message": "This vehicle is already your primary vehicle"
  }
  ```

- **Vehicle Not Found (404):**
  ```json
  {
    "response_code": "default_404",
    "message": "Vehicle not found"
  }
  ```

---

## 🎯 Business Rules

### ✅ Requirements:
- Vehicle must be **approved** (`vehicle_request_status = 'approved'`)
- Vehicle must belong to the requesting driver
- Vehicle cannot already be the primary vehicle
- Only one pending primary request per driver at a time

### 🚫 Restrictions:
- Cannot set pending vehicles as primary
- Cannot set rejected vehicles as primary
- Cannot have multiple pending primary requests
- Current primary vehicle stays active until approval

---

## 🔍 Database Schema

### New Column: `has_pending_primary_request`
- **Type:** `boolean`
- **Default:** `false`
- **Location:** `vehicles` table
- **Purpose:** Track which vehicle has a pending primary change request

### Migration:
```php
Schema::table('vehicles', function (Blueprint $table) {
    $table->boolean('has_pending_primary_request')->default(false)->after('is_primary');
});
```

---

## 🛠️ Admin Actions

### Approve Primary Vehicle Change

**Route:** `POST /admin/driver/approvals/vehicle/approve-primary/{driverId}/{vehicleId}`

**What Happens:**
1. Current primary vehicle → `is_primary = false`
2. Requested vehicle → `is_primary = true`
3. Requested vehicle → `has_pending_primary_request = false`
4. Activity logged for audit trail

### Reject Primary Vehicle Change

**Route:** `POST /admin/driver/approvals/vehicle/reject-primary/{driverId}/{vehicleId}`

**What Happens:**
1. Requested vehicle → `has_pending_primary_request = false`
2. Current primary vehicle stays as primary
3. No other changes
4. Activity logged for audit trail

---

## 📊 Driver App Display

When listing vehicles via `GET /api/driver/vehicle/list`, the response includes:

```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "vehicles": [
      {
        "id": "f8a2b3c4-d5e6-4f7a-8b9c-0d1e2f3a4b5c",
        "brand": "Toyota",
        "model": "Camry",
        "is_primary": true,
        "has_pending_primary_request": false,
        "vehicle_request_status": "approved"
      },
      {
        "id": "a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d",
        "brand": "Honda",
        "model": "Civic",
        "is_primary": false,
        "has_pending_primary_request": true,
        "vehicle_request_status": "approved"
      }
    ]
  }
}
```

### UI Indicators:
- ✅ **Green Badge:** Current primary vehicle
- ⏳ **Yellow Badge:** Pending primary change request
- 🔄 **Text:** "Waiting for admin approval"

---

## 🎨 Admin UI

In the driver approval page (`show.blade.php`):

```html
@if($vehicle->has_pending_primary_request)
    <div class="alert alert-warning mt-3 mb-0">
        <i class="bi bi-exclamation-triangle"></i> 
        Driver requested to set this vehicle as primary
    </div>
    <div class="d-flex gap-2 mt-2">
        <form action="{{ route('admin.driver.approvals.vehicle.approve-primary', [$driver->id, $vehicle->id]) }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-primary w-100 btn-sm">
                <i class="bi bi-check-circle"></i> Approve Primary Change
            </button>
        </form>
        <form action="{{ route('admin.driver.approvals.vehicle.reject-primary', [$driver->id, $vehicle->id]) }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-secondary w-100 btn-sm">
                <i class="bi bi-x-circle"></i> Reject Primary Change
            </button>
        </form>
    </div>
@endif
```

---

## 🧪 Testing

### Test Scenario 1: Request Primary Vehicle Change
```bash
# 1. Get driver's vehicles
curl -X GET "https://smartline-it.com/api/driver/vehicle/list" \
  -H "Authorization: Bearer {token}"

# 2. Request to set vehicle as primary
curl -X POST "https://smartline-it.com/api/driver/vehicle/set-primary/{vehicle_id}" \
  -H "Authorization: Bearer {token}"

# 3. Verify pending status
curl -X GET "https://smartline-it.com/api/driver/vehicle/list" \
  -H "Authorization: Bearer {token}"
# Should show has_pending_primary_request: true

# 4. Admin approves via web interface

# 5. Verify primary changed
curl -X GET "https://smartline-it.com/api/driver/vehicle/list" \
  -H "Authorization: Bearer {token}"
# Should show is_primary: true, has_pending_primary_request: false
```

### Test Scenario 2: Multiple Requests
```bash
# Request vehicle A as primary
curl -X POST "https://smartline-it.com/api/driver/vehicle/set-primary/{vehicle_a_id}" \
  -H "Authorization: Bearer {token}"

# Request vehicle B as primary (clears vehicle A request)
curl -X POST "https://smartline-it.com/api/driver/vehicle/set-primary/{vehicle_b_id}" \
  -H "Authorization: Bearer {token}"

# Verify only vehicle B has pending request
curl -X GET "https://smartline-it.com/api/driver/vehicle/list" \
  -H "Authorization: Bearer {token}"
```

---

## 🔒 Security Considerations

### ✅ Implemented:
- Driver can only request their own vehicles
- Vehicle ownership verified before accepting request
- Only approved vehicles can be requested
- Admin authentication required for approval
- Activity logging for audit trail

### 🛡️ Race Condition Prevention:
- Only one pending primary request per driver
- Previous requests automatically cleared
- Database transactions for atomic updates

---

## 📝 Code Changes Summary

### Files Modified:
1. **Migration:** `database/migrations/2026_01_08_185144_add_pending_primary_to_vehicles_table.php`
   - Added `has_pending_primary_request` column

2. **Model:** `Modules/VehicleManagement/Entities/Vehicle.php`
   - Added field to fillable and casts

3. **Controller:** `Modules/VehicleManagement/Http/Controllers/Api/New/Driver/VehicleController.php`
   - Modified `setPrimary()` to require approval
   - Updated `index()` to include pending status

4. **Service:** `Modules/VehicleManagement/Service/VehicleService.php`
   - Added `approvePrimaryVehicleChange()`
   - Added `denyPrimaryVehicleChange()`

5. **Interface:** `Modules/VehicleManagement/Service/Interface/VehicleServiceInterface.php`
   - Added method signatures

6. **Admin Controller:** `Modules/UserManagement/Http/Controllers/Web/New/Admin/Driver/DriverApprovalController.php`
   - Added `approvePrimaryVehicleChange()`
   - Added `rejectPrimaryVehicleChange()`

7. **Routes:** `Modules/UserManagement/Routes/web.php`
   - Added approval/rejection routes

8. **View:** `Modules/UserManagement/Resources/views/admin/driver/approvals/show.blade.php`
   - Added UI for pending primary requests

---

## 🚀 Deployment Checklist

- [x] Database migration created and run
- [x] Model updated with new field
- [x] API endpoint modified to require approval
- [x] Service methods implemented
- [x] Admin approval endpoints created
- [x] Routes configured
- [x] Admin UI updated
- [x] Documentation created

---

## 📚 Related Documentation

- [Secondary Vehicle Implementation](SECONDARY_VEHICLE_IMPLEMENTATION.md)
- [Driver Onboarding API V2](API_V2_DRIVER_ONBOARDING_FULL_SPEC.md)
- [Vehicle Approval Quick Reference](VEHICLE_APPROVAL_QUICK_REFERENCE.md)

---

**Last Updated:** 2026-01-08  
**Feature Status:** ✅ Production Ready  
**Admin URL:** https://smartline-it.com/admin/driver/approvals/{driver_id}
