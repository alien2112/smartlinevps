# Vehicle Image Upload Guide

**Issue:** Vehicle images showing as `null` in API response
**Cause:** Driver didn't upload images when creating the vehicle

---

## Current Status

### API Response (Before Fix):
```json
{
  "images": {
    "car_front": null,
    "car_back": null
  }
}
```

### API Response (After Adding Images):
```json
{
  "images": {
    "car_front": "https://smartline-it.com/media/vehicle/document/2026-01-09-39fba110e4279.webp",
    "car_back": "https://smartline-it.com/media/vehicle/document/2026-01-09-98828dd4d0589.webp"
  }
}
```

---

## How Vehicle Images Work

### Storage Location:
Images are stored in the `documents` JSON field in the `vehicles` table.

### Format:
```json
[
  "/root/new/vehicle/document/2026-01-09-39fba110e4279.webp",
  "/root/new/vehicle/document/2026-01-09-98828dd4d0589.webp"
]
```

### Image Mapping:
- **First image** (index 0) = `car_front`
- **Second image** (index 1) = `car_back`
- **Third image** (index 2) = `car_left` (optional)
- **Fourth image** (index 3) = `car_right` (optional)

---

## How to Upload Vehicle Images

### Method 1: Via Mobile App (Recommended)

The driver should use the mobile app to upload images when creating/updating a vehicle. The app should:

1. Take/select photos for:
   - Car front view (required)
   - Car back view (required)
   - Car left side (optional)
   - Car right side (optional)

2. Upload via API endpoint (example):
```bash
POST /api/driver/vehicle/store-as-primary
POST /api/driver/vehicle/{id}/update
```

With multipart form data:
```
car_front: (image file)
car_back: (image file)
car_left: (image file)
car_right: (image file)
```

---

### Method 2: Direct Database Update (For Testing)

```php
<?php
// Example: Add images to vehicle
$vehicleId = 'f592d439-378e-4fd0-9e88-845094091e54';

$images = [
    '/root/new/vehicle/document/2026-01-09-front.webp',
    '/root/new/vehicle/document/2026-01-09-back.webp',
];

DB::table('vehicles')
    ->where('id', $vehicleId)
    ->update(['documents' => json_encode($images)]);
```

---

### Method 3: Manual File Upload + DB Update

**Step 1: Upload image files to server**
```bash
# Via SCP
scp car_front.jpg root@smartline-it.com:/root/new/vehicle/document/

# Or create directory if it doesn't exist
mkdir -p /root/new/vehicle/document/
```

**Step 2: Rename with proper format**
```bash
cd /root/new/vehicle/document/
mv car_front.jpg 2026-01-09-abc123.webp
mv car_back.jpg 2026-01-09-def456.webp
```

**Step 3: Update database**
```sql
UPDATE vehicles 
SET documents = JSON_ARRAY(
  '/root/new/vehicle/document/2026-01-09-abc123.webp',
  '/root/new/vehicle/document/2026-01-09-def456.webp'
)
WHERE id = 'f592d439-378e-4fd0-9e88-845094091e54';
```

---

## Image Requirements

### File Format:
- **Preferred:** WebP (.webp)
- **Supported:** JPEG (.jpg), PNG (.png)

### File Size:
- **Maximum:** 5MB per image
- **Recommended:** 500KB - 1MB

### Dimensions:
- **Minimum:** 800x600 pixels
- **Recommended:** 1920x1080 pixels

### Naming Convention:
```
{YYYY-MM-DD}-{random_hash}.webp
```

Example:
```
2026-01-09-39fba110e4279.webp
```

---

## Image URL Format

The API automatically converts stored paths to accessible URLs:

**Stored in DB:**
```
/root/new/vehicle/document/2026-01-09-39fba110e4279.webp
```

**Returned in API:**
```
https://smartline-it.com/media/vehicle/document/2026-01-09-39fba110e4279.webp
```

---

## API Response Structure

### GET /api/driver/vehicle/list

```json
{
  "response_code": "default_200",
  "data": {
    "vehicles": [
      {
        "id": "f592d439-378e-4fd0-9e88-845094091e54",
        "licence_plate_number": "ا ب ج-5747",
        "images": {
          "car_front": "https://smartline-it.com/media/vehicle/document/2026-01-09-39fba110e4279.webp",
          "car_back": "https://smartline-it.com/media/vehicle/document/2026-01-09-98828dd4d0589.webp"
        },
        "vehicle_request_status": "pending",
        "has_pending_primary_request": true
      }
    ]
  }
}
```

---

## Testing Vehicle Images

### Check Current Status:
```bash
php artisan tinker --execute="
\$vehicle = DB::table('vehicles')
  ->where('id', 'f592d439-378e-4fd0-9e88-845094091e54')
  ->first();
echo 'Documents: ' . \$vehicle->documents . PHP_EOL;
"
```

### Add Test Images:
```bash
php artisan tinker --execute="
DB::table('vehicles')
  ->where('id', 'f592d439-378e-4fd0-9e88-845094091e54')
  ->update([
    'documents' => json_encode([
      '/root/new/vehicle/document/2026-01-09-test1.webp',
      '/root/new/vehicle/document/2026-01-09-test2.webp'
    ])
  ]);
echo 'Images added!' . PHP_EOL;
"
```

### Test via API:
```bash
curl -X GET "https://smartline-it.com/api/driver/vehicle/list" \
  -H "Authorization: Bearer {token}" | jq '.data.vehicles[].images'
```

---

## Common Issues & Solutions

### Issue 1: Images showing as null
**Cause:** `documents` field is empty `[]`
**Solution:** Upload images or add paths to documents field

### Issue 2: Images not accessible (404)
**Cause:** Image files don't exist on server
**Solution:** Upload actual image files to `/root/new/vehicle/document/`

### Issue 3: Wrong image format
**Cause:** Incorrect path format in database
**Solution:** Use format `/root/new/vehicle/document/YYYY-MM-DD-hash.webp`

### Issue 4: Permissions error
**Cause:** Image files not readable by web server
**Solution:** `chmod 644 /root/new/vehicle/document/*.webp`

---

## For Both Vehicles (Current Driver)

### Vehicle 1: 5747 (Primary - Approved)
```sql
UPDATE vehicles 
SET documents = JSON_ARRAY(
  '/root/new/vehicle/document/2026-01-09-vehicle1-front.webp',
  '/root/new/vehicle/document/2026-01-09-vehicle1-back.webp'
)
WHERE id = '4e34ffdf-91dd-43db-87b3-d89eb2b7f2ef';
```

### Vehicle 2: ا ب ج-5747 (Pending Approval)
```sql
UPDATE vehicles 
SET documents = JSON_ARRAY(
  '/root/new/vehicle/document/2026-01-09-39fba110e4279.webp',
  '/root/new/vehicle/document/2026-01-09-98828dd4d0589.webp'
)
WHERE id = 'f592d439-378e-4fd0-9e88-845094091e54';
```

---

## Summary

✅ **What We Did:**
- Added placeholder image paths to the new vehicle
- Images now show in API with proper URLs

⚠️ **What's Needed:**
- Upload actual image files to the server
- Or have driver upload images via mobile app

📝 **For Production:**
- Implement proper image upload in mobile app
- Add validation for image format and size
- Store images in proper directory structure

---

**Generated:** 2026-01-09  
**Driver:** +201208673028  
**Vehicle:** f592d439-378e-4fd0-9e88-845094091e54
