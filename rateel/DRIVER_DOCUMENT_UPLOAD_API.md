# Driver Document Upload API Documentation

## Overview
This API provides endpoints for uploading and updating driver documents during the onboarding process. Documents are grouped logically so that front and back images can be uploaded together.

**Base URL:** `{{base_url}}/api/driver/auth`

---

## Upload Endpoints (POST)

### 1. Upload ID Documents
Upload both ID front and back together.

**Endpoint:** `POST /upload/id`

**Request Type:** `multipart/form-data`

**Request Body:**
```
phone: +201234567890 (required, string)
id_front: [file] (required, jpg/jpeg/png/pdf, max 5MB)
id_back: [file] (required, jpg/jpeg/png/pdf, max 5MB)
```

**Success Response (200):**
```json
{
  "status": "success",
  "message": "Documents uploaded successfully",
  "data": {
    "next_step": "documents",
    "uploaded_documents": [
      {
        "type": "id_front",
        "id": "uuid-here"
      },
      {
        "type": "id_back",
        "id": "uuid-here"
      }
    ],
    "all_uploaded_types": ["id_front", "id_back"],
    "missing_documents": ["license_front", "license_back", "car_front", "car_back", "selfie"]
  }
}
```

---

### 2. Upload License Documents
Upload both driver's license front and back together.

**Endpoint:** `POST /upload/license`

**Request Type:** `multipart/form-data`

**Request Body:**
```
phone: +201234567890 (required, string)
license_front: [file] (required, jpg/jpeg/png/pdf, max 5MB)
license_back: [file] (required, jpg/jpeg/png/pdf, max 5MB)
```

**Success Response (200):**
```json
{
  "status": "success",
  "message": "Documents uploaded successfully",
  "data": {
    "next_step": "documents",
    "uploaded_documents": [
      {
        "type": "license_front",
        "id": "uuid-here"
      },
      {
        "type": "license_back",
        "id": "uuid-here"
      }
    ],
    "all_uploaded_types": ["id_front", "id_back", "license_front", "license_back"],
    "missing_documents": ["car_front", "car_back", "selfie"]
  }
}
```

---

### 3. Upload Car Photos
Upload both car front and back photos together.

**Endpoint:** `POST /upload/car_photo`

**Request Type:** `multipart/form-data`

**Request Body:**
```
phone: +201234567890 (required, string)
car_front: [file] (required, jpg/jpeg/png/pdf, max 5MB)
car_back: [file] (required, jpg/jpeg/png/pdf, max 5MB)
```

**Success Response (200):**
```json
{
  "status": "success",
  "message": "Documents uploaded successfully",
  "data": {
    "next_step": "documents",
    "uploaded_documents": [
      {
        "type": "car_front",
        "id": "uuid-here"
      },
      {
        "type": "car_back",
        "id": "uuid-here"
      }
    ],
    "all_uploaded_types": ["id_front", "id_back", "license_front", "license_back", "car_front", "car_back"],
    "missing_documents": ["selfie"]
  }
}
```

**Note:** For motor_bike and scooter vehicle types, car photos are not required.

---

### 4. Upload Selfie
Upload driver's selfie photo.

**Endpoint:** `POST /upload/selfie`

**Request Type:** `multipart/form-data`

**Request Body:**
```
phone: +201234567890 (required, string)
selfie: [file] (required, jpg/jpeg/png/pdf, max 5MB)
```

**Success Response - All Documents Complete (200):**
```json
{
  "status": "success",
  "message": "All documents uploaded successfully. Your application is pending approval.",
  "data": {
    "next_step": "pending_approval",
    "uploaded_documents": [
      {
        "type": "selfie",
        "id": "uuid-here"
      }
    ],
    "requires_admin_approval": true
  }
}
```

---

## Update Endpoints (PUT)

### 1. Update ID Documents
Update one or both ID documents (front and/or back).

**Endpoint:** `PUT /update/id`

**Request Type:** `multipart/form-data`

**Request Body:**
```
phone: +201234567890 (required, string)
id_front: [file] (optional, jpg/jpeg/png/pdf, max 5MB)
id_back: [file] (optional, jpg/jpeg/png/pdf, max 5MB)
```

**Note:** At least one file (id_front or id_back) must be provided.

**Success Response (200):**
```json
{
  "status": "success",
  "message": "Documents updated successfully",
  "data": {
    "updated_documents": [
      {
        "type": "id_front",
        "id": "uuid-here"
      },
      {
        "type": "id_back",
        "id": "uuid-here"
      }
    ]
  }
}
```

**Error Response - Document Not Found (404):**
```json
{
  "status": "error",
  "message": "Document type 'id_front' not found. Please upload it first."
}
```

---

### 2. Update License Documents
Update one or both license documents (front and/or back).

**Endpoint:** `PUT /update/license`

**Request Type:** `multipart/form-data`

**Request Body:**
```
phone: +201234567890 (required, string)
license_front: [file] (optional, jpg/jpeg/png/pdf, max 5MB)
license_back: [file] (optional, jpg/jpeg/png/pdf, max 5MB)
```

**Note:** At least one file must be provided.

**Success Response (200):**
```json
{
  "status": "success",
  "message": "Documents updated successfully",
  "data": {
    "updated_documents": [
      {
        "type": "license_front",
        "id": "uuid-here"
      },
      {
        "type": "license_back",
        "id": "uuid-here"
      }
    ]
  }
}
```

---

### 3. Update Car Photos
Update one or both car photos (front and/or back).

**Endpoint:** `PUT /update/car_photo`

**Request Type:** `multipart/form-data`

**Request Body:**
```
phone: +201234567890 (required, string)
car_front: [file] (optional, jpg/jpeg/png/pdf, max 5MB)
car_back: [file] (optional, jpg/jpeg/png/pdf, max 5MB)
```

**Note:** At least one file must be provided.

**Success Response (200):**
```json
{
  "status": "success",
  "message": "Documents updated successfully",
  "data": {
    "updated_documents": [
      {
        "type": "car_front",
        "id": "uuid-here"
      },
      {
        "type": "car_back",
        "id": "uuid-here"
      }
    ]
  }
}
```

---

### 4. Update Selfie
Update the driver's selfie photo.

**Endpoint:** `PUT /update/selfie`

**Request Type:** `multipart/form-data`

**Request Body:**
```
phone: +201234567890 (required, string)
selfie: [file] (required, jpg/jpeg/png/pdf, max 5MB)
```

**Success Response (200):**
```json
{
  "status": "success",
  "message": "Documents updated successfully",
  "data": {
    "updated_documents": [
      {
        "type": "selfie",
        "id": "uuid-here"
      }
    ]
  }
}
```

---

## Common Error Responses

### Validation Error (422)
```json
{
  "status": "error",
  "message": "Validation failed",
  "errors": {
    "id_front": ["The id front field is required."],
    "phone": ["The phone field is required."]
  }
}
```

### Driver Not Found (404)
```json
{
  "status": "error",
  "message": "Driver not found"
}
```

### Vehicle Type Not Selected (400)
```json
{
  "status": "error",
  "message": "Please select vehicle type first",
  "data": {
    "next_step": "vehicle_type"
  }
}
```

### Server Error (500)
```json
{
  "status": "error",
  "message": "Failed to upload documents: [error message]"
}
```

---

## Important Notes

1. **File Requirements:**
   - Supported formats: JPG, JPEG, PNG, PDF
   - Maximum file size: 5MB per file
   - All files are stored in `storage/driver-documents/{driver_id}/`

2. **Upload vs Update:**
   - Use **upload** endpoints during initial onboarding
   - Use **update** endpoints to replace existing documents (e.g., if rejected)
   - Update endpoints require documents to exist first

3. **Document Verification:**
   - When documents are uploaded or updated, they are marked as unverified
   - Admin must verify documents before driver can be approved
   - Rejected documents can be updated using the update endpoints

4. **Vehicle Type Requirements:**
   - **Car/Taxi:** ID, License, Car Photos, Selfie (all required)
   - **Motor Bike:** ID, License, Selfie (car photos not required)
   - **Scooter:** ID, License, Selfie (car photos not required)

5. **Auto-approval:**
   - Some vehicle categories (Taxi, Scooter, Uncategorized) are auto-approved
   - Other categories require admin approval after document upload

6. **Phone Number Format:**
   - Phone numbers are automatically normalized
   - Numbers starting with '0' are assumed to be Egyptian (+20)
   - Always use international format: +201234567890

---

## Example: Complete Upload Flow

```bash
# 1. Upload ID
curl -X POST "http://your-domain.com/api/driver/auth/upload/id" \
  -F "phone=+201234567890" \
  -F "id_front=@/path/to/id_front.jpg" \
  -F "id_back=@/path/to/id_back.jpg"

# 2. Upload License
curl -X POST "http://your-domain.com/api/driver/auth/upload/license" \
  -F "phone=+201234567890" \
  -F "license_front=@/path/to/license_front.jpg" \
  -F "license_back=@/path/to/license_back.jpg"

# 3. Upload Car Photos
curl -X POST "http://your-domain.com/api/driver/auth/upload/car_photo" \
  -F "phone=+201234567890" \
  -F "car_front=@/path/to/car_front.jpg" \
  -F "car_back=@/path/to/car_back.jpg"

# 4. Upload Selfie (completes onboarding)
curl -X POST "http://your-domain.com/api/driver/auth/upload/selfie" \
  -F "phone=+201234567890" \
  -F "selfie=@/path/to/selfie.jpg"
```

## Example: Update Document

```bash
# Update only ID front (keep ID back as is)
curl -X PUT "http://your-domain.com/api/driver/auth/update/id" \
  -F "phone=+201234567890" \
  -F "id_front=@/path/to/new_id_front.jpg"

# Update both ID documents
curl -X PUT "http://your-domain.com/api/driver/auth/update/id" \
  -F "phone=+201234567890" \
  -F "id_front=@/path/to/new_id_front.jpg" \
  -F "id_back=@/path/to/new_id_back.jpg"
```

---

## Testing with Postman

### Upload ID Request
1. Method: POST
2. URL: `{{base_url}}/api/driver/auth/upload/id`
3. Body → form-data:
   - Key: `phone`, Value: `+201234567890`
   - Key: `id_front`, Type: File, Value: [Select file]
   - Key: `id_back`, Type: File, Value: [Select file]

### Update License Request
1. Method: PUT
2. URL: `{{base_url}}/api/driver/auth/update/license`
3. Body → form-data:
   - Key: `phone`, Value: `+201234567890`
   - Key: `license_front`, Type: File, Value: [Select file] (optional)
   - Key: `license_back`, Type: File, Value: [Select file] (optional)

---

## Migration Notes

If you're migrating from the old single-file upload endpoint:

**Old Endpoint:**
```
POST /api/driver/auth/upload/id_front
POST /api/driver/auth/upload/id_back
```

**New Endpoint:**
```
POST /api/driver/auth/upload/id (uploads both front and back together)
```

This reduces the number of API calls and provides a better user experience.
