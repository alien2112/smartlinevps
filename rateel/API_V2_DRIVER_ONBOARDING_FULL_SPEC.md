# Driver Onboarding API V2 - Complete Endpoint Specification

## Base URL
```
/api/v2/driver
```

## Authentication
- **Onboarding Token**: Required for protected endpoints (after OTP verification)
- **Header**: `Authorization: Bearer {onboarding_token}`
- **Token Scope**: `onboarding` (for onboarding endpoints) or `driver` (for approved drivers)

---

## 📋 Table of Contents

1. [Public Endpoints](#public-endpoints)
   - [Start Onboarding](#1-start-onboarding)
   - [Verify OTP](#2-verify-otp)
   - [Resend OTP](#3-resend-otp)
   - [Login](#4-login)
2. [Protected Endpoints](#protected-endpoints)
   - [Get Status](#5-get-status)
   - [Set Password](#6-set-password)
   - [Submit Profile](#7-submit-profile)
   - [Select Vehicle](#8-select-vehicle)
   - [Upload Document](#9-upload-document)
   - [Submit Application](#10-submit-application)

---

## Public Endpoints

### 1. Start Onboarding

**Endpoint:** `POST /api/v2/driver/onboarding/start`

**Description:** Start the onboarding process by submitting phone number. Sends OTP to the phone.

**Authentication:** None required

**Request Parameters:**

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| `phone` | string | ✅ Yes | min:10, max:20 | Phone number (e.g., "+201012345678" or "01012345678") |
| `device_id` | string | ❌ No | max:100 | Device fingerprint/identifier |

**Request Body Example:**
```json
{
  "phone": "+201012345678",
  "device_id": "abc123-device-fingerprint"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Verification code sent to your phone",
  "data": {
    "onboarding_id": "onb_7f8a9b2c3d4e5f6",
    "phone_masked": "+20101****678",
    "otp_expires_at": "2026-01-03T15:05:00Z",
    "otp_length": 6,
    "resend_available_at": "2026-01-03T15:01:00Z",
    "resends_remaining": 3,
    "next_step": "verify_otp",
    "onboarding_state": "otp_pending",
    "state_version": 1
  }
}
```

**Error Responses:**

**429 - Rate Limited:**
```json
{
  "success": false,
  "message": "This phone number is temporarily locked. Please try again later.",
  "error": {
    "code": "RATE_LIMITED",
    "reason": "phone_locked",
    "locked_until": "2026-01-03T16:00:00Z",
    "retry_after": 3600,
    "retry_after_at": "2026-01-03T16:00:00Z"
  }
}
```

**429 - Resend Cooldown:**
```json
{
  "success": false,
  "message": "Please wait before requesting a new code",
  "error": {
    "code": "RESEND_COOLDOWN",
    "retry_after": 45,
    "retry_after_at": "2026-01-03T15:01:45Z",
    "onboarding_id": "onb_7f8a9b2c3d4e5f6"
  }
}
```

**422 - Validation Error:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "phone": ["The phone field is required."]
  }
}
```

---

### 2. Verify OTP

**Endpoint:** `POST /api/v2/driver/onboarding/verify-otp`

**Description:** Verify OTP code and receive onboarding token.

**Authentication:** None required

**Request Parameters:**

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| `onboarding_id` | string | ✅ Yes | min:19, max:24 | Onboarding session ID from start endpoint |
| `otp` | string | ✅ Yes | size:6 | 6-digit OTP code |
| `device_id` | string | ❌ No | max:100 | Device fingerprint/identifier |

**Request Body Example:**
```json
{
  "onboarding_id": "onb_7f8a9b2c3d4e5f6",
  "otp": "123456",
  "device_id": "abc123-device-fingerprint"
}
```

**Success Response (200) - New Driver:**
```json
{
  "success": true,
  "message": "Phone verified successfully",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIs...",
    "token_type": "Bearer",
    "token_expires_at": "2026-01-04T15:00:00Z",
    "token_scope": "onboarding",
    "driver_id": "drv_a1b2c3d4e5f6",
    "next_step": "set_password",
    "onboarding_state": "otp_verified",
    "state_version": 2,
    "is_returning": false
  }
}
```

**Success Response (200) - Returning Driver:**
```json
{
  "success": true,
  "message": "Welcome back! Continue your registration.",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIs...",
    "token_type": "Bearer",
    "token_expires_at": "2026-01-04T15:00:00Z",
    "token_scope": "onboarding",
    "driver_id": "drv_a1b2c3d4e5f6",
    "next_step": "upload_documents",
    "onboarding_state": "vehicle_selected",
    "state_version": 5,
    "is_returning": true,
    "profile": {
      "first_name": "Ahmed",
      "phone_masked": "+20101****678"
    },
    "missing_documents": ["driving_license", "vehicle_registration"]
  }
}
```

**Error Responses:**

**400 - Invalid OTP:**
```json
{
  "success": false,
  "message": "Invalid code. Please try again.",
  "error": {
    "code": "INVALID_OTP",
    "attempts_remaining": 2
  }
}
```

**400 - OTP Expired:**
```json
{
  "success": false,
  "message": "Code expired. Please request a new one.",
  "error": {
    "code": "OTP_EXPIRED",
    "can_resend": true
  }
}
```

**429 - Too Many Attempts:**
```json
{
  "success": false,
  "message": "Too many failed attempts. Please request a new code.",
  "error": {
    "code": "VERIFY_LOCKED",
    "must_resend": true,
    "retry_after": 1800
  }
}
```

**401 - Session Not Found/Expired:**
```json
{
  "success": false,
  "message": "Session not found or expired. Please start over.",
  "error": {
    "code": "SESSION_NOT_FOUND"
  }
}
```

---

### 3. Resend OTP

**Endpoint:** `POST /api/v2/driver/onboarding/resend-otp`

**Description:** Resend OTP code for an existing session.

**Authentication:** None required

**Request Parameters:**

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| `onboarding_id` | string | ✅ Yes | min:19, max:24 | Onboarding session ID |
| `device_id` | string | ❌ No | max:100 | Device fingerprint/identifier |

**Request Body Example:**
```json
{
  "onboarding_id": "onb_7f8a9b2c3d4e5f6",
  "device_id": "abc123-device-fingerprint"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "New verification code sent",
  "data": {
    "onboarding_id": "onb_7f8a9b2c3d4e5f6",
    "phone_masked": "+20101****678",
    "otp_expires_at": "2026-01-03T15:10:00Z",
    "otp_length": 6,
    "resend_available_at": "2026-01-03T15:06:00Z",
    "resends_remaining": 2,
    "next_step": "verify_otp",
    "onboarding_state": "otp_pending",
    "state_version": 1
  }
}
```

**Error Responses:**

**429 - Resend Cooldown:**
```json
{
  "success": false,
  "message": "Please wait before requesting a new code",
  "error": {
    "code": "RESEND_COOLDOWN",
    "retry_after": 30,
    "retry_after_at": "2026-01-03T15:01:30Z"
  }
}
```

**400 - Max Resends:**
```json
{
  "success": false,
  "message": "Maximum resend attempts reached. Please start over.",
  "error": {
    "code": "MAX_RESENDS"
  }
}
```

---

### 4. Login

**Endpoint:** `POST /api/v2/driver/auth/login`

**Description:** Login for approved drivers or get onboarding token for pending drivers.

**Authentication:** None required

**Request Parameters:**

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| `phone` | string | ✅ Yes | min:10, max:20 | Phone number |
| `password` | string | ✅ Yes | - | Driver password |
| `device_id` | string | ❌ No | max:100 | Device fingerprint |
| `fcm_token` | string | ❌ No | max:500 | Firebase Cloud Messaging token |

**Request Body Example:**
```json
{
  "phone": "+201012345678",
  "password": "SecurePass123!",
  "device_id": "abc123-device-fingerprint",
  "fcm_token": "firebase-token-xyz"
}
```

**Success Response (200) - Approved Driver:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIs...",
    "token_type": "Bearer",
    "token_expires_at": "2026-02-02T15:00:00Z",
    "token_scope": "driver",
    "driver": {
      "id": "drv_a1b2c3d4e5f6",
      "first_name": "Ahmed",
      "last_name": "Hassan",
      "phone": "+201012345678",
      "email": "ahmed@example.com",
      "profile_image": "https://example.com/profile.jpg",
      "rating": 4.85,
      "is_online": false,
      "is_approved": true
    }
  }
}
```

**Success Response (200) - Pending Driver:**
```json
{
  "success": true,
  "message": "Your application is under review",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIs...",
    "token_type": "Bearer",
    "token_scope": "onboarding",
    "is_approved": false,
    "onboarding_state": "pending_approval",
    "next_step": "wait_for_approval"
  }
}
```

**Error Responses:**

**401 - Invalid Credentials:**
```json
{
  "success": false,
  "message": "Invalid credentials"
}
```

**422 - Validation Error:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "phone": ["The phone field is required."],
    "password": ["The password field is required."]
  }
}
```

---

## Protected Endpoints

All protected endpoints require:
- **Header**: `Authorization: Bearer {onboarding_token}`
- **Token Scope**: `onboarding` (for onboarding endpoints)

---

### 5. Get Status

**Endpoint:** `GET /api/v2/driver/onboarding/status`

**Description:** Get current onboarding status and progress.

**Authentication:** ✅ Required (Onboarding Token)

**Request Parameters:** None (uses token to identify driver)

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "driver_id": "drv_a1b2c3d4e5f6",
    "phone_masked": "+20101****678",
    "next_step": "upload_documents",
    "onboarding_state": "vehicle_selected",
    "state_version": 5,
    "progress_percentage": 70,
    "is_approved": false,
    "created_at": "2026-01-03T14:00:00Z",
    "profile": {
      "first_name": "Ahmed",
      "last_name": "Hassan",
      "email": "ahmed@example.com",
      "national_id_masked": "***********1234"
    },
    "vehicle": {
      "id": "veh_xyz123",
      "type": "sedan",
      "category_id": "cat_sedan",
      "brand": "Toyota",
      "model": "Camry",
      "licence_plate": "ABC-1234"
    },
    "documents": {
      "required": ["national_id", "driving_license", "vehicle_registration", "vehicle_photo", "profile_photo"],
      "uploaded": [
        {
          "type": "national_id",
          "status": "pending",
          "uploaded_at": "2026-01-03T15:00:00Z",
          "rejection_reason": null
        }
      ],
      "missing": ["driving_license", "vehicle_registration", "vehicle_photo", "profile_photo"],
      "rejected": []
    }
  }
}
```

**Error Responses:**

**401 - Unauthorized:**
```json
{
  "success": false,
  "message": "Unauthorized",
  "error": {
    "code": "UNAUTHORIZED"
  }
}
```

---

### 6. Set Password

**Endpoint:** `POST /api/v2/driver/onboarding/password`

**Description:** Set password for the driver account.

**Authentication:** ✅ Required (Onboarding Token)

**Request Parameters:**

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| `password` | string | ✅ Yes | min:8, regex patterns | Password (must match password_confirmation) |
| `password_confirmation` | string | ✅ Yes | - | Password confirmation |

**Password Requirements:**
- Minimum 8 characters
- At least one uppercase letter (A-Z)
- At least one lowercase letter (a-z)
- At least one number (0-9)
- Special characters optional (configurable)

**Request Body Example:**
```json
{
  "password": "SecurePass123!",
  "password_confirmation": "SecurePass123!"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Password set successfully",
  "data": {
    "next_step": "submit_profile",
    "onboarding_state": "password_set",
    "state_version": 3
  }
}
```

**Error Responses:**

**409 - Invalid State:**
```json
{
  "success": false,
  "message": "Please complete the previous step first",
  "error": {
    "code": "INVALID_STATE_TRANSITION",
    "current_state": "otp_pending",
    "expected_state": "otp_verified",
    "next_step": "verify_otp"
  }
}
```

**422 - Validation Error:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "password": [
      "The password field is required.",
      "The password must be at least 8 characters.",
      "The password must contain at least one uppercase letter.",
      "The password confirmation does not match."
    ]
  }
}
```

---

### 7. Submit Profile

**Endpoint:** `POST /api/v2/driver/onboarding/profile`

**Description:** Submit driver profile information.

**Authentication:** ✅ Required (Onboarding Token)

**Request Parameters:**

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| `first_name` | string | ✅ Yes | min:2, max:50 | First name |
| `last_name` | string | ✅ Yes | min:2, max:50 | Last name |
| `national_id` | string | ✅ Yes | min:10, max:20 | National ID number |
| `city_id` | string | ✅ Yes | exists:cities,id | City UUID |
| `email` | string | ❌ No | email, max:100 | Email address |
| `date_of_birth` | date | ❌ No | date, age:21-65 | Date of birth (YYYY-MM-DD) |
| `gender` | string | ❌ No | in:male,female | Gender |
| `first_name_ar` | string | ❌ No | min:2, max:50 | First name in Arabic |
| `last_name_ar` | string | ❌ No | min:2, max:50 | Last name in Arabic |

**Age Requirements:**
- Minimum age: 21 years
- Maximum age: 65 years

**Request Body Example:**
```json
{
  "first_name": "Ahmed",
  "last_name": "Hassan",
  "national_id": "12345678901234",
  "city_id": "city_uuid_123",
  "email": "ahmed@example.com",
  "date_of_birth": "1990-05-15",
  "gender": "male",
  "first_name_ar": "أحمد",
  "last_name_ar": "حسن"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Profile saved successfully",
  "data": {
    "next_step": "select_vehicle",
    "onboarding_state": "profile_complete",
    "state_version": 4
  }
}
```

**Error Responses:**

**409 - Invalid State:**
```json
{
  "success": false,
  "message": "Please complete the previous step first",
  "error": {
    "code": "INVALID_STATE_TRANSITION",
    "current_state": "otp_verified",
    "expected_state": "password_set",
    "next_step": "set_password"
  }
}
```

**422 - Validation Error:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "first_name": ["The first name field is required."],
    "date_of_birth": ["You must be at least 21 years old."]
  }
}
```

---

### 8. Select Vehicle

**Endpoint:** `POST /api/v2/driver/onboarding/vehicle`

**Description:** Select vehicle type and details.

**Authentication:** ✅ Required (Onboarding Token)

**Request Parameters:**

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| `vehicle_category_id` | string | ✅ Yes | exists:vehicle_categories,id | Vehicle category UUID (e.g., sedan, SUV) |
| `brand_id` | string | ✅ Yes | exists:vehicle_brands,id | Vehicle brand UUID |
| `model_id` | string | ✅ Yes | exists:vehicle_models,id | Vehicle model UUID |
| `year` | integer | ❌ No | min:1990, max:current+1 | Production year |
| `color` | string | ❌ No | max:30 | Vehicle color |
| `licence_plate` | string | ❌ No | max:20 | License plate number |

**Request Body Example:**
```json
{
  "vehicle_category_id": "cat_sedan_uuid",
  "brand_id": "brand_toyota_uuid",
  "model_id": "model_camry_uuid",
  "year": 2020,
  "color": "White",
  "licence_plate": "ABC-1234"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Vehicle information saved",
  "data": {
    "vehicle_id": "veh_xyz123",
    "next_step": "upload_documents",
    "onboarding_state": "vehicle_selected",
    "state_version": 5,
    "required_documents": {
      "national_id": {
        "type": "national_id",
        "label": "National ID (Front & Back)",
        "max_size_mb": 5,
        "allowed_mimes": ["image/jpeg", "image/png", "application/pdf"],
        "required": true
      },
      "driving_license": {
        "type": "driving_license",
        "label": "Driving License",
        "max_size_mb": 5,
        "allowed_mimes": ["image/jpeg", "image/png", "application/pdf"],
        "required": true
      },
      "vehicle_registration": {
        "type": "vehicle_registration",
        "label": "Vehicle Registration",
        "max_size_mb": 5,
        "allowed_mimes": ["image/jpeg", "image/png", "application/pdf"],
        "required": true
      },
      "vehicle_photo": {
        "type": "vehicle_photo",
        "label": "Vehicle Photo",
        "max_size_mb": 10,
        "allowed_mimes": ["image/jpeg", "image/png"],
        "required": true
      },
      "profile_photo": {
        "type": "profile_photo",
        "label": "Profile Photo",
        "max_size_mb": 5,
        "allowed_mimes": ["image/jpeg", "image/png"],
        "required": true
      }
    },
    "missing_documents": [
      "national_id",
      "driving_license",
      "vehicle_registration",
      "vehicle_photo",
      "profile_photo"
    ]
  }
}
```

**Error Responses:**

**409 - Invalid State:**
```json
{
  "success": false,
  "message": "Please complete the previous step first",
  "error": {
    "code": "INVALID_STATE_TRANSITION",
    "current_state": "password_set",
    "expected_state": "profile_complete",
    "next_step": "submit_profile"
  }
}
```

**422 - Validation Error:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "vehicle_category_id": ["The selected vehicle category id is invalid."],
    "year": ["The year must be between 1990 and 2027."]
  }
}
```

---

### 9. Upload Document

**Endpoint:** `POST /api/v2/driver/onboarding/documents/{type}`

**Description:** Upload a document file.

**Authentication:** ✅ Required (Onboarding Token)

**URL Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `type` | string | ✅ Yes | Document type (see allowed types below) |

**Allowed Document Types:**
- `national_id` - National ID (Front & Back)
- `driving_license` - Driving License
- `vehicle_registration` - Vehicle Registration
- `vehicle_photo` - Vehicle Photo
- `profile_photo` - Profile Photo
- `criminal_record` - Criminal Record Certificate (optional)

**Request Parameters (Multipart Form Data):**

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| `file` | file | ✅ Yes | file, max size varies, mimetypes | Document file |

**File Requirements by Type:**

| Type | Max Size | Allowed MIME Types |
|------|----------|-------------------|
| `national_id` | 5 MB | image/jpeg, image/png, application/pdf |
| `driving_license` | 5 MB | image/jpeg, image/png, application/pdf |
| `vehicle_registration` | 5 MB | image/jpeg, image/png, application/pdf |
| `vehicle_photo` | 10 MB | image/jpeg, image/png |
| `profile_photo` | 5 MB | image/jpeg, image/png |
| `criminal_record` | 5 MB | image/jpeg, image/png, application/pdf |

**Request Example (cURL):**
```bash
curl -X POST \
  https://api.example.com/api/v2/driver/onboarding/documents/driving_license \
  -H "Authorization: Bearer {onboarding_token}" \
  -F "file=@/path/to/license.jpg"
```

**Request Example (Form Data):**
```
POST /api/v2/driver/onboarding/documents/driving_license
Content-Type: multipart/form-data
Authorization: Bearer {onboarding_token}

file: [binary file data]
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Document uploaded successfully",
  "data": {
    "document": {
      "id": "doc_xyz123",
      "type": "driving_license",
      "label": "Driving License",
      "status": "pending",
      "uploaded_at": "2026-01-03T15:30:00Z"
    },
    "next_step": "upload_documents",
    "onboarding_state": "vehicle_selected",
    "state_version": 5,
    "missing_documents": [
      "national_id",
      "vehicle_registration",
      "vehicle_photo",
      "profile_photo"
    ],
    "all_documents_uploaded": false
  }
}
```

**Success Response (200) - All Documents Uploaded:**
```json
{
  "success": true,
  "message": "Document uploaded successfully",
  "data": {
    "document": {
      "id": "doc_xyz123",
      "type": "profile_photo",
      "label": "Profile Photo",
      "status": "pending",
      "uploaded_at": "2026-01-03T15:35:00Z"
    },
    "next_step": "submit_for_review",
    "onboarding_state": "documents_pending",
    "state_version": 6,
    "missing_documents": [],
    "all_documents_uploaded": true
  }
}
```

**Error Responses:**

**400 - Invalid Document Type:**
```json
{
  "success": false,
  "message": "Invalid document type",
  "error": {
    "code": "INVALID_DOCUMENT_TYPE",
    "provided": "random_type",
    "allowed": [
      "national_id",
      "driving_license",
      "vehicle_registration",
      "vehicle_photo",
      "profile_photo",
      "criminal_record"
    ]
  }
}
```

**400 - File Too Large:**
```json
{
  "success": false,
  "message": "File size exceeds maximum allowed",
  "error": {
    "code": "FILE_TOO_LARGE",
    "max_size_mb": 5,
    "provided_size_mb": 12.5
  }
}
```

**400 - Invalid File Type:**
```json
{
  "success": false,
  "message": "Invalid file type",
  "error": {
    "code": "INVALID_FILE_TYPE",
    "allowed_mimes": ["image/jpeg", "image/png", "application/pdf"],
    "provided_mime": "video/mp4"
  }
}
```

**400 - Max Uploads Reached:**
```json
{
  "success": false,
  "message": "Maximum upload attempts reached for this document type",
  "error": {
    "code": "MAX_UPLOADS_REACHED"
  }
}
```

**409 - Invalid State:**
```json
{
  "success": false,
  "message": "Please complete vehicle selection first",
  "error": {
    "code": "INVALID_STATE",
    "current_state": "profile_complete",
    "next_step": "select_vehicle"
  }
}
```

---

### 10. Submit Application

**Endpoint:** `POST /api/v2/driver/onboarding/submit`

**Description:** Submit the complete application for admin review.

**Authentication:** ✅ Required (Onboarding Token)

**Request Parameters:**

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| `terms_accepted` | boolean | ✅ Yes | accepted | Terms and conditions acceptance |
| `privacy_accepted` | boolean | ✅ Yes | accepted | Privacy policy acceptance |

**Request Body Example:**
```json
{
  "terms_accepted": true,
  "privacy_accepted": true
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Application submitted for review",
  "data": {
    "next_step": "wait_for_approval",
    "onboarding_state": "pending_approval",
    "state_version": 11,
    "estimated_review_time": "24-48 hours",
    "message": "Your application has been submitted for review"
  }
}
```

**Error Responses:**

**409 - Invalid State:**
```json
{
  "success": false,
  "message": "Please complete the previous step first",
  "error": {
    "code": "INVALID_STATE_TRANSITION",
    "current_state": "vehicle_selected",
    "expected_state": "documents_pending",
    "next_step": "upload_documents"
  }
}
```

**400 - Documents Incomplete:**
```json
{
  "success": false,
  "message": "Please upload all required documents",
  "error": {
    "code": "DOCUMENTS_INCOMPLETE",
    "missing_documents": [
      "driving_license",
      "vehicle_photo"
    ]
  }
}
```

**422 - Validation Error:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "terms_accepted": ["The terms accepted field must be accepted."],
    "privacy_accepted": ["The privacy accepted field must be accepted."]
  }
}
```

---

## 🔄 State Machine Flow

```
otp_pending → otp_verified → password_set → profile_complete 
→ vehicle_selected → documents_pending → pending_approval 
→ approved/rejected
```

**State Transitions:**
- Each state must be completed before moving to the next
- Invalid transitions return `409 Conflict` with current/expected state
- State version increments with each transition

---

## 📊 Common Response Fields

### Success Response Structure
```json
{
  "success": true,
  "message": "Success message",
  "data": {
    "next_step": "string",           // Next action for client
    "onboarding_state": "string",     // Current state
    "state_version": integer,          // State version (prevents stale updates)
    // ... endpoint-specific fields
  }
}
```

### Error Response Structure
```json
{
  "success": false,
  "message": "Error message",
  "error": {
    "code": "ERROR_CODE",
    // ... error-specific fields
  },
  "errors": {                         // Only for validation errors (422)
    "field_name": ["Error message"]
  }
}
```

---

## 🔐 Authentication Flow

1. **Start Onboarding** → Get `onboarding_id`
2. **Verify OTP** → Get `onboarding_token` (scope: `onboarding`)
3. **Use Token** → Access protected endpoints with `Authorization: Bearer {token}`
4. **After Approval** → Use `/login` to get `driver_token` (scope: `driver`)

---

## ⚠️ Rate Limiting

- **OTP Send**: 5 per hour per phone, 10 per day per phone
- **OTP Verify**: 5 attempts per session
- **Resend**: 60-second cooldown, max 3 resends per session
- **Global**: 100 OTP sends per minute (DoS protection)

---

## 📝 Notes

- All timestamps are in ISO 8601 format (UTC)
- Phone numbers are normalized to international format (+20...)
- OTP codes are 6 digits (configurable)
- Token expiry: 48 hours for onboarding tokens, 30 days for driver tokens
- File uploads use multipart/form-data
- All endpoints return JSON

---

**Last Updated:** 2026-01-03
**API Version:** 2.0
