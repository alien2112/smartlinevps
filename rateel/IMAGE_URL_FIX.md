# Image URL Fix - Summary

## Problem
URLs were being malformed like:
```
https://smartline-it.com/storage/app/public/vehicle/category//root/new/vehicle/category/2025-05-29-68379d7cda14b.webp
```

This happened because:
1. Database stores full paths: `/root/new/vehicle/category/file.webp`
2. API config was returning wrong base URLs: `asset('storage/app/public/vehicle/category')`
3. Mobile apps concatenated base URL + full path = malformed URL

## Correct URL Format
Images should be accessed via the media route:
```
https://smartline-it.com/media/vehicle/category/2025-05-29-68379d7cda14b.webp
```

## Changes Made

### 1. Fixed API Config Base URLs
**Files Modified:**
- `Modules/BusinessManagement/Http/Controllers/Api/New/Customer/ConfigController.php`
- `Modules/BusinessManagement/Http/Controllers/Api/New/Driver/ConfigController.php`

**Changed:**
```php
// BEFORE (Wrong - doesn't exist on server)
'vehicle_category' => asset('storage/app/public/vehicle/category')
// Returns: https://smartline-it.com/storage/app/public/vehicle/category

// AFTER (Correct - uses media route)
'vehicle_category' => url('media/vehicle/category')
// Returns: https://smartline-it.com/media/vehicle/category
```

**All fixed base URLs:**
- `profile_image_driver` → `url('media/driver/profile')`
- `profile_image_admin` → `url('media/employee/profile')`
- `banner` → `url('media/promotion/banner')`
- `vehicle_category` → `url('media/vehicle/category')`
- `vehicle_model` → `url('media/vehicle/model')`
- `vehicle_brand` → `url('media/vehicle/brand')`
- `profile_image` → `url('media/customer/profile')` / `url('media/driver/profile')`
- `identity_image` → `url('media/customer/identity')` / `url('media/driver/identity')`
- `documents` → `url('media/customer/document')` / `url('media/driver/document')`
- `level` → `url('media/customer/level')`
- `pages` → `url('media/business/pages')`
- `conversation` → `url('media/conversation')`
- `parcel` → `url('media/parcel/category')`
- `payment_method` → `url('media/payment_modules/gateway_image')`

### 2. Added Legacy Document Detection
**Files Modified:**
- `Modules/UserManagement/Http/Controllers/Web/New/Admin/Driver/DriverApprovalController.php`
- `Modules/UserManagement/Resources/views/admin/driver/approvals/show.blade.php`

**What it does:**
- Checks if driver has documents in OLD system (stored in `users` table JSON fields)
- Shows "Doc Uploaded" badge instead of "Not Uploaded" for legacy documents
- Displays message: "This document was uploaded using the legacy system and cannot be previewed here"

**Old System Fields Checked:**
- `identification_image` → Maps to: `national_id`, `id_front`, `id_back`
- `driving_license` → Maps to: `driving_license`, `license_front`, `license_back`
- `vehicle_license` → Maps to: `vehicle_registration`
- `car_front_image` → Maps to: `car_front`, `vehicle_photo`
- `car_back_image` → Maps to: `car_back`, `vehicle_photo`
- `profile_image` → Maps to: `profile_photo`

## How Media URLs Work

### Storage System
Files are stored in: `/root/new/CATEGORY/SUBCATEGORY/YYYY-MM-DD-HASH.webp`

Examples:
- `/root/new/vehicle/category/2025-05-29-68379d7cda14b.webp`
- `/root/new/driver/profile/2025-06-17-6850ffc69d94b.webp`
- `/root/new/vehicle/document/2026-01-09-39fba110e4279.webp`

### URL Conversion
The `getMediaUrl()` helper function converts paths:

```php
// Input: /root/new/vehicle/category/file.webp
// Output: https://smartline-it.com/media/vehicle/category/file.webp

// Input: vehicle/category/file.webp (relative)
// Output: https://smartline-it.com/media/vehicle/category/file.webp
```

### Nginx/Caddy Configuration
The `/media/` route is handled by the web server to serve files from `/root/new/`:

```nginx
location /media/ {
    alias /root/new/;
}
```

## Impact

### For Mobile Apps
After next app config fetch, apps will receive correct base URLs and images will load properly.

**Before:**
- Base URL: `https://smartline-it.com/storage/app/public/vehicle/category`
- File: `/root/new/vehicle/category/file.webp`
- Result: ❌ `https://smartline-it.com/storage/app/public/vehicle/category//root/new/vehicle/category/file.webp`

**After:**
- Base URL: `https://smartline-it.com/media/vehicle/category`
- File: `file.webp` (or `2025-05-29-68379d7cda14b.webp`)
- Result: ✅ `https://smartline-it.com/media/vehicle/category/file.webp`

### For Admin Panel
Old drivers who uploaded documents before the new system will now show:
- ✅ Green "Doc Uploaded" badge instead of ❌ "Not Uploaded"
- Info message explaining it's from legacy system
- Admin can verify documents exist even if not viewable in new interface

## Testing

### Test Image URLs
```bash
# Test vehicle category image
curl -I https://smartline-it.com/media/vehicle/category/2025-05-29-68379d7cda14b.webp
# Should return: 200 OK

# Test driver profile image
curl -I https://smartline-it.com/media/driver/profile/2025-06-17-6850ffc69d94b.webp
# Should return: 200 OK

# Test vehicle document
curl -I https://smartline-it.com/media/vehicle/document/2026-01-09-39fba110e4279.webp
# Should return: 200 OK
```

### Test API Config
```bash
# Get customer config
curl -H "Authorization: Bearer TOKEN" https://smartline-it.com/api/customer/config

# Check image_base_url.vehicle_category
# Should return: "https://smartline-it.com/media/vehicle/category"
```

### Test Legacy Documents
Visit driver approval page for old drivers:
```
https://smartline-it.com/admin/driver/approvals/000302ca-4065-463a-9e3f-4e281eba7fb0
```

Should show:
- National ID: ✅ "Doc Uploaded" (green badge)
- Profile Photo: ✅ "Doc Uploaded" (green badge)
- Driving License: ❌ "Not Uploaded" (if not uploaded)

## Notes

### Why Not Migrate Old Documents?
We chose to detect legacy documents rather than migrate them because:
1. **Simpler** - No complex data migration needed
2. **Safer** - No risk of data corruption
3. **Faster** - Immediate fix without downtime
4. **Sufficient** - Admin can still see status and verify externally if needed

### Helper Functions
The existing helper functions already handle URL conversion correctly:
- `getMediaUrl()` - Converts paths to proper media URLs
- `onErrorImage()` - Uses `getMediaUrl()` internally for path conversion

The issue was only in API config endpoints returning wrong base URLs.

## Related Documentation
- `OLD_DOCUMENT_SYSTEM_MAPPING.md` - Detailed mapping of old vs new document system
- `VEHICLE_IMAGE_UPLOAD_GUIDE.md` - Guide for vehicle image storage
- `STORAGE_CONFIGURATION.md` - Overall storage system documentation
