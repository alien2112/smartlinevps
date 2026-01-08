# Vehicle Approval & Images Issues - FIXED

**Driver:** +201208673028 (سلمي سامي)  
**Vehicle ID:** f592d439-378e-4fd0-9e88-845094091e54  
**Date:** 2026-01-09

---

## Issues Found & Fixed

### ✅ Issue 1: Vehicle Not Showing in Admin Approvals (FIXED)

**Problem:**  
The vehicle change request wasn't appearing in the admin approval system because `has_pending_primary_request` was set to `0` instead of `1`.

**Root Cause:**  
When the driver submitted the vehicle, the flag `has_pending_primary_request` wasn't properly set.

**Fix Applied:**
```sql
UPDATE vehicles 
SET has_pending_primary_request = 1 
WHERE id = 'f592d439-378e-4fd0-9e88-845094091e54';
```

**Result:** ✅ Vehicle now shows `"has_pending_primary_request": true` in the API

---

### ❌ Issue 2: Car Images Not Showing (NOT UPLOADED)

**Problem:**  
Both vehicles show `null` for car images:
```json
"images": {
  "car_front": null,
  "car_back": null
}
```

**Root Cause:**  
The driver didn't upload images when creating the vehicle. The `documents` field is empty: `[]`

**Image Storage Format:**
Images are stored in the `documents` JSON field as an array of paths:
```json
[
  "/root/new/vehicle/document/2025-07-01-6863bab38bd5b.webp",
  "/root/new/vehicle/document/2025-07-01-6863bab38d4b3.webp"
]
```

**Solution:**  
Driver needs to upload car images. Images should be added to the `documents` array.

---

## Database Status (After Fix)

### Vehicle: f592d439-378e-4fd0-9e88-845094091e54

```json
{
  "id": "f592d439-378e-4fd0-9e88-845094091e54",
  "licence_plate_number": "ا ب ج-5747",
  "brand": "رينو (Renault)",
  "model": "داستر 2025",
  "category": "سياره التوفير",
  "is_primary": false,
  "vehicle_request_status": "pending",
  "has_pending_primary_request": true,  ← FIXED (was false)
  "documents": [],  ← EMPTY (needs images)
  "images": {
    "car_front": null,
    "car_back": null
  }
}
```

---

## Admin Approval Routes

The system has these routes for vehicle approval:

1. **Approve Individual Vehicle:**
   ```
   POST /admin/drivers/vehicle/approve/{driverId}/{vehicleId}
   Route Name: vehicle.approve
   ```

2. **Approve Primary Vehicle Change:**
   ```
   POST /admin/drivers/vehicle/approve-primary/{driverId}/{vehicleId}
   Route Name: vehicle.approve-primary
   ```

### Usage:
```bash
# Approve primary vehicle change
curl -X POST "https://smartline-it.com/admin/drivers/vehicle/approve-primary/000302ca-4065-463a-9e3f-4e281eba7fb0/f592d439-378e-4fd0-9e88-845094091e54" \
  -H "Cookie: your-admin-session-cookie"
```

---

## Why Vehicle Doesn't Appear in Driver Approvals Page

**URL:** `https://smartline-it.com/admin/driver/approvals?status=pending_approval`

**What This Page Shows:**  
- New drivers with `onboarding_state = 'pending_approval'`
- NOT vehicle change requests

**What You Need:**  
A separate **Vehicle Approvals** page or section that queries:
```sql
SELECT * FROM vehicles 
WHERE vehicle_request_status = 'pending' 
  AND has_pending_primary_request = 1;
```

---

## How to Add Vehicle Images

### Option 1: API Upload (Recommended)
```bash
curl -X POST "https://smartline-it.com/api/driver/vehicle/{vehicleId}/upload-images" \
  -H "Authorization: Bearer {driver_token}" \
  -F "car_front=@/path/to/front.jpg" \
  -F "car_back=@/path/to/back.jpg" \
  -F "car_left=@/path/to/left.jpg" \
  -F "car_right=@/path/to/right.jpg"
```

### Option 2: Direct Database Insert
```sql
UPDATE vehicles 
SET documents = JSON_ARRAY(
  '/path/to/car_front.jpg',
  '/path/to/car_back.jpg'
)
WHERE id = 'f592d439-378e-4fd0-9e88-845094091e54';
```

### Option 3: Manual File Upload + DB Update
1. Upload images to: `/var/www/laravel/smartlinevps/rateel/storage/app/public/vehicle/`
2. Update documents field with the paths

---

## Image Format Requirements

Based on existing vehicles:

**Storage Path:** `/root/new/vehicle/document/`  
**Format:** `YYYY-MM-DD-{hash}.webp`  
**Example:** `/root/new/vehicle/document/2025-07-01-6863bab38bd5b.webp`

**Minimum Images Required:**
- `car_front` (front view)
- `car_back` (back view)

**Optional Images:**
- `car_left` (left side)
- `car_right` (right side)

---

## API Response Format

### GET /api/driver/vehicle/list

Returns vehicles with image structure:
```json
{
  "vehicles": [
    {
      "id": "...",
      "licence_plate_number": "...",
      "images": {
        "car_front": "url_or_null",
        "car_back": "url_or_null"
      },
      "has_pending_primary_request": true/false,
      "vehicle_request_status": "pending|approved|rejected"
    }
  ]
}
```

---

## Transmission Values

Valid values for the `transmission` field:
1. `automatic` - Automatic transmission
2. `manual` or `مانيوال` - Manual transmission
3. `amt` - Automated Manual Transmission
4. `cvt` - Continuously Variable Transmission (if supported)

---

## Summary

### ✅ Fixed:
- `has_pending_primary_request` flag now correctly set to `1`
- Vehicle now properly marked as pending approval

### ❌ Still Needs:
- Driver must upload car images (front & back minimum)
- Images will then appear in the `documents` JSON array
- Admin panel needs a dedicated "Vehicle Approvals" section

### Next Steps:
1. **Ask driver to upload images** via the mobile app
2. **Admin can approve** using the route: `/admin/drivers/vehicle/approve-primary/...`
3. **Consider adding** a "Pending Vehicle Changes" section in admin panel

---

**Generated:** 2026-01-09  
**Status:** Partial Fix - Flag corrected, images need upload
