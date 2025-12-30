# Driver Authentication & Onboarding API - Flutter Integration Guide

## Overview

This document describes the **Uber-style unified driver authentication and onboarding flow**. There is **NO separate login or register** - both use the same flow, and the backend determines what the driver should see based on their current state.

## Key Principle

> **Driver enters phone → Backend returns `next_step` → Flutter shows that screen**

Token is only issued when `onboarding_step = approved`.

---

## API Endpoints

Base URL: `{{base_url}}/api/driver/auth`

### 1. Start (Phone Screen)

**Endpoint:** `POST /api/driver/auth/start`

This is the **single entry point** for both new and existing drivers.

**Request:**
```json
{
  "phone": "+201012345678"
}
```

**Response (Success):**
```json
{
  "status": "success",
  "message": "OTP sent successfully",
  "data": {
    "next_step": "otp",
    "phone": "+201012345678",
    "is_new_driver": true
  }
}
```

**What it does:**
- Finds or creates driver by phone
- Sends OTP via SMS
- Returns `next_step: "otp"`

---

### 2. Verify OTP

**Endpoint:** `POST /api/driver/auth/verify-otp`

**Request:**
```json
{
  "phone": "+201012345678",
  "otp": "123456"
}
```

**Response (New Driver):**
```json
{
  "status": "success",
  "message": "OTP verified successfully",
  "data": {
    "next_step": "password"
  }
}
```

**Response (Existing Driver - Approved):**
```json
{
  "status": "success",
  "message": "OTP verified successfully",
  "data": {
    "next_step": "approved",
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOi...",
    "driver": {
      "id": "uuid",
      "first_name": "Ahmed",
      "last_name": "Mohamed",
      ...
    }
  }
}
```

**Backend Logic:**
```
If password not set → password
Else if register not done → register_info
Else if vehicle not selected → vehicle_type
Else if docs not done → documents
Else if not approved → pending_approval
Else → approved (with token)
```

---

### 3. Set Password

**Endpoint:** `POST /api/driver/auth/set-password`

**Request:**
```json
{
  "phone": "+201012345678",
  "password": "securePassword123",
  "password_confirmation": "securePassword123"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Password set successfully",
  "data": {
    "next_step": "register_info"
  }
}
```

---

### 4. Registration Info

**Endpoint:** `POST /api/driver/auth/register-info`

**Request:**
```json
{
  "phone": "+201012345678",
  "first_name_ar": "أحمد",
  "last_name_ar": "محمد",
  "national_id": "29901011234567",
  "city_id": 1
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Registration info saved successfully",
  "data": {
    "next_step": "vehicle_type"
  }
}
```

---

### 5. Vehicle Type Selection

**Endpoint:** `POST /api/driver/auth/vehicle-type`

**Request:**
```json
{
  "phone": "+201012345678",
  "vehicle_type": "car",
  "travel_enabled": false
}
```

**Vehicle Types:** `car`, `taxi`, `scooter`

**Response:**
```json
{
  "status": "success",
  "message": "Vehicle type selected successfully",
  "data": {
    "next_step": "documents",
    "vehicle_type": "car",
    "required_documents": ["id_front", "id_back", "license", "car_photo", "selfie"]
  }
}
```

---

### 6. Document Upload

**Endpoint:** `POST /api/driver/auth/upload/{type}`

Upload one document at a time. Call multiple times for different document types.

**Document Types:**
- `id_front` - National ID (Front)
- `id_back` - National ID (Back)
- `license` - Driving License
- `car_photo` - Vehicle Photo
- `selfie` - Driver Selfie

**Request (multipart/form-data):**
```
phone: +201012345678
document: [FILE]
```

**Response (More documents needed):**
```json
{
  "status": "success",
  "message": "Document uploaded successfully",
  "data": {
    "next_step": "documents",
    "document_type": "id_front",
    "document_id": "uuid",
    "uploaded_documents": ["id_front"],
    "missing_documents": ["id_back", "license", "car_photo", "selfie"]
  }
}
```

**Response (All documents uploaded):**
```json
{
  "status": "success",
  "message": "All documents uploaded. Your application is pending approval.",
  "data": {
    "next_step": "pending_approval",
    "document_type": "selfie",
    "document_id": "uuid"
  }
}
```

---

### 7. Get Status (MOST IMPORTANT)

**Endpoint:** `GET /api/driver/auth/status?phone=+201012345678`

**Call this on every app open** to determine where to resume.

**Response:**
```json
{
  "status": "success",
  "message": "Driver status retrieved",
  "data": {
    "next_step": "documents",
    "is_registered": true,
    "is_approved": false,
    "uploaded_documents": ["id_front", "id_back"],
    "missing_documents": ["license", "car_photo", "selfie"],
    "vehicle_type": "car"
  }
}
```

**Phone Not Registered:**
```json
{
  "status": "success",
  "message": "Phone number not registered",
  "data": {
    "next_step": "phone",
    "is_registered": false
  }
}
```

---

### 8. Login (Approved Drivers Only)

**Endpoint:** `POST /api/driver/auth/login`

This only works when `onboarding_step = approved`.

**Request:**
```json
{
  "phone": "+201012345678",
  "password": "securePassword123"
}
```

**Response (Success):**
```json
{
  "status": "success",
  "message": "Login successful",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOi...",
    "driver": {
      "id": "uuid",
      "first_name": "Ahmed",
      ...
    }
  }
}
```

**Response (Not Approved Yet):**
```json
{
  "status": "error",
  "message": "Account not approved yet",
  "data": {
    "next_step": "pending_approval",
    "is_approved": false
  }
}
```

---

### 9. Resend OTP

**Endpoint:** `POST /api/driver/auth/resend-otp`

**Request:**
```json
{
  "phone": "+201012345678"
}
```

---

## Flutter Implementation Guide

### Step 1: App Initialization

```dart
class AuthService {
  Future<void> checkDriverStatus() async {
    final phone = await getStoredPhone();
    if (phone == null) {
      // Show phone entry screen
      navigateTo(PhoneScreen());
      return;
    }
    
    final response = await api.get('/driver/auth/status?phone=$phone');
    final nextStep = response['data']['next_step'];
    
    navigateToStep(nextStep);
  }
  
  void navigateToStep(String step) {
    switch (step) {
      case 'phone':
        navigateTo(PhoneScreen());
        break;
      case 'otp':
        navigateTo(OtpScreen());
        break;
      case 'password':
        navigateTo(PasswordScreen());
        break;
      case 'register_info':
        navigateTo(RegistrationScreen());
        break;
      case 'vehicle_type':
        navigateTo(VehicleTypeScreen());
        break;
      case 'documents':
        navigateTo(DocumentsScreen());
        break;
      case 'pending_approval':
        navigateTo(PendingApprovalScreen());
        break;
      case 'approved':
        navigateTo(DashboardScreen());
        break;
    }
  }
}
```

### Step 2: Phone Entry

```dart
class PhoneScreen extends StatelessWidget {
  void submitPhone(String phone) async {
    final response = await api.post('/driver/auth/start', {
      'phone': phone,
    });
    
    if (response['status'] == 'success') {
      // Store phone for later use
      await storePhone(phone);
      
      // Navigate to OTP
      navigateTo(OtpScreen(phone: phone));
    }
  }
}
```

### Step 3: OTP Verification

```dart
class OtpScreen extends StatelessWidget {
  final String phone;
  
  void verifyOtp(String otp) async {
    final response = await api.post('/driver/auth/verify-otp', {
      'phone': phone,
      'otp': otp,
    });
    
    if (response['status'] == 'success') {
      final nextStep = response['data']['next_step'];
      
      // If approved, store token
      if (nextStep == 'approved') {
        await storeToken(response['data']['token']);
      }
      
      navigateToStep(nextStep);
    }
  }
}
```

### Step 4: Document Upload Screen

```dart
class DocumentsScreen extends StatelessWidget {
  List<String> uploadedDocs = [];
  List<String> missingDocs = [];
  
  @override
  void initState() {
    loadStatus();
  }
  
  void loadStatus() async {
    final response = await api.get('/driver/auth/status?phone=$phone');
    uploadedDocs = response['data']['uploaded_documents'];
    missingDocs = response['data']['missing_documents'];
  }
  
  void uploadDocument(String type, File file) async {
    final formData = FormData.fromMap({
      'phone': phone,
      'document': await MultipartFile.fromFile(file.path),
    });
    
    final response = await api.post('/driver/auth/upload/$type', formData);
    
    if (response['status'] == 'success') {
      final nextStep = response['data']['next_step'];
      
      if (nextStep == 'pending_approval') {
        navigateTo(PendingApprovalScreen());
      } else {
        // Update lists and show next document
        uploadedDocs = response['data']['uploaded_documents'];
        missingDocs = response['data']['missing_documents'];
      }
    }
  }
}
```

---

## Onboarding Steps Overview

| Step | Screen | API Endpoint | Next Step |
|------|--------|--------------|-----------|
| 1 | Phone Entry | `POST /start` | otp |
| 2 | OTP Verification | `POST /verify-otp` | password/register_info/vehicle_type/documents/pending_approval/approved |
| 3 | Create Password | `POST /set-password` | register_info |
| 4 | Registration Info | `POST /register-info` | vehicle_type |
| 5 | Vehicle Type | `POST /vehicle-type` | documents |
| 6 | Document Upload | `POST /upload/{type}` | documents/pending_approval |
| 7 | Pending Approval | (polling status) | approved |
| 8 | Dashboard | - | - |

---

## Error Handling

All errors follow this format:

```json
{
  "status": "error",
  "message": "Human readable message",
  "errors": {
    "field_name": ["Validation error message"]
  },
  "data": {
    "next_step": "correct_step"
  }
}
```

**Common HTTP Status Codes:**
- `200` - Success
- `400` - Bad Request (validation, wrong step)
- `401` - Invalid credentials
- `403` - Not approved / deactivated
- `404` - Driver not found
- `422` - Validation failed
- `500` - Server error

---

## Security Notes

1. **No token until approved** - Drivers cannot access protected routes until fully approved
2. **OTP required for every session** - Password alone is not enough without OTP
3. **Phone must be verified** - Each step validates that previous steps are completed
4. **Rate limiting** - OTP resend is limited to prevent abuse

---

## Pending Approval Screen

While waiting for admin approval:
- Show a "waiting" animation
- Poll `/status` every 30-60 seconds
- Show estimated wait time (if configured)
- Allow driver to contact support

When approved, `next_step` will change to `approved` with a token.

---

## Admin Approval (Backend)

Admins approve drivers through:
- Web admin panel: `/admin/driver/approvals`
- API: `POST /api/admin/drivers/{id}/approve`

Driver receives push notification when approved.
