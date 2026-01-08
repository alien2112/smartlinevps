# Old Document System to New System Mapping

## Problem
Old drivers (before current upload system) have documents stored in the `users` table as JSON arrays, but the admin approval page only checks the `driver_documents` table. This causes "Not Uploaded" to show even when documents exist.

## Old System (users table columns)
- `identification_image` - JSON array of national ID images
- `driving_license` - JSON array of driving license images  
- `vehicle_license` - JSON array of vehicle registration images
- `car_front_image` - JSON array of car front images
- `car_back_image` - JSON array of car back images
- `profile_image` - String path to profile photo
- `other_documents` - JSON array of other documents

## New System (driver_documents table)
Document types stored in `type` column:
- `national_id` - National ID documents
- `id_front` - ID Front (old)
- `id_back` - ID Back (old)
- `driving_license` - Driving license
- `license_front` - License Front (old)
- `license_back` - License Back (old)
- `vehicle_registration` - Vehicle registration/license
- `vehicle_photo` - Vehicle photos
- `car_front` - Car Front (old)
- `car_back` - Car Back (old)
- `profile_photo` - Profile photo

## Mapping Strategy
Map old system fields to new system document types:

| Old Field (users table) | New Type(s) (driver_documents) | Check Logic |
|-------------------------|-------------------------------|-------------|
| `identification_image` | `national_id`, `id_front`, `id_back` | If JSON array has items OR identification_image IS NOT NULL |
| `driving_license` | `driving_license`, `license_front`, `license_back` | If JSON array has items |
| `vehicle_license` | `vehicle_registration` | If JSON array has items |
| `car_front_image` | `car_front`, `vehicle_photo` | If JSON array has items |
| `car_back_image` | `car_back`, `vehicle_photo` | If JSON array has items |
| `profile_image` | `profile_photo` | If NOT NULL |

## Proposed Solution

### Option 1: Show "Doc Uploaded" for old system documents (RECOMMENDED)
Modify the controller to check BOTH:
1. `driver_documents` table (new system)
2. `users` table JSON fields (old system)

If documents exist in EITHER location, show badge as "Doc Uploaded" instead of "Not Uploaded", but don't try to display images (since old system uses different storage format).

**Pros:**
- Simple implementation
- Clear indication that documents exist
- No need to migrate old data
- Works for both old and new drivers

**Cons:**
- Can't view/verify old document images from admin panel
- Manual verification needed if admin wants to see old documents

### Option 2: Migrate old documents to new system
Create a migration script to copy old documents to `driver_documents` table.

**Pros:**
- Unified system
- Can view all documents in admin panel

**Cons:**
- Complex migration
- Risk of data issues
- Need to handle different storage formats

## Recommended Implementation
Use **Option 1** - Check both systems and show "Doc Uploaded" badge.

### Code Changes Needed:

1. **Controller** (`DriverApprovalController.php` - `show()` method):
   - Add logic to check old system fields
   - Create collection of "legacy documents" with type and status
   - Merge with new system documents

2. **View** (`show.blade.php`):
   - Update badge to show "Doc Uploaded (Legacy)" for old system docs
   - Show message that document exists but can't be previewed
   - Only show verify/reject buttons for new system documents

### Example Check Logic:
```php
// Check if driver has old system documents
$legacyDocs = [];

// Check identification (National ID)
if (!empty($driver->identification_image) && $driver->identification_image != '[]') {
    $legacyDocs['national_id'] = true;
    $legacyDocs['id_front'] = true;
    $legacyDocs['id_back'] = true;
}

// Check driving license
if (!empty($driver->driving_license) && $driver->driving_license != '[]') {
    $legacyDocs['driving_license'] = true;
    $legacyDocs['license_front'] = true;
    $legacyDocs['license_back'] = true;
}

// Check vehicle license
if (!empty($driver->vehicle_license) && $driver->vehicle_license != '[]') {
    $legacyDocs['vehicle_registration'] = true;
}

// Check car images
if (!empty($driver->car_front_image) && $driver->car_front_image != '[]') {
    $legacyDocs['car_front'] = true;
    $legacyDocs['vehicle_photo'] = true;
}

if (!empty($driver->car_back_image) && $driver->car_back_image != '[]') {
    $legacyDocs['car_back'] = true;
}

// Check profile image
if (!empty($driver->profile_image)) {
    $legacyDocs['profile_photo'] = true;
}

// Pass to view
return view('...', compact('driver', 'documents', 'requiredDocs', 'legacyDocs'));
```

### View Update:
```blade
@if($isUploaded)
    <span class="badge bg-info">{{ $docList->count() }} file(s)</span>
@elseif(isset($legacyDocs[$docType]) && $legacyDocs[$docType])
    <span class="badge bg-success">Doc Uploaded</span>
@else
    <span class="badge bg-secondary">Not Uploaded</span>
@endif
```

## Example: Driver 000302ca-4065-463a-9e3f-4e281eba7fb0
Current state:
- `identification_image`: 9 images (JSON array)
- `profile_image`: Path string
- All other fields: NULL or empty

Should show:
- ✅ National ID: "Doc Uploaded"
- ✅ ID Front: "Doc Uploaded" 
- ✅ ID Back: "Doc Uploaded"
- ✅ Profile Photo: "Doc Uploaded"
- ❌ Driving License: "Not Uploaded"
- ❌ Vehicle Registration: "Not Uploaded"
- ❌ Car Front: "Not Uploaded"
- ❌ Car Back: "Not Uploaded"
