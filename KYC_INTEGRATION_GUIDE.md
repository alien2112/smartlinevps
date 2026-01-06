# KYC Liveness Verification - Integration Guide

## Base URL
```
https://smartline-it.com/api/driver
```

---

## Authentication

All endpoints require **Bearer Token Authentication**:

```http
Authorization: Bearer {your_driver_token}
Content-Type: application/json
Accept: application/json
```

### Get Driver Token
First, login as a driver:

```bash
curl -X POST "https://smartline-it.com/api/driver/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "phone": "+201234567890",
    "password": "your_password"
  }'
```

**Response:**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "data": {
    "id": "driver-uuid",
    "name": "Driver Name",
    "phone": "+201234567890"
  }
}
```

---

## Complete KYC Flow (4 Steps)

### Step 1: Create Verification Session

**Endpoint:** `POST /api/driver/verification/session`

**Description:** Creates a new KYC verification session or returns existing active session.

**Request:**
```bash
curl -X POST "https://smartline-it.com/api/driver/verification/session" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json"
```

**Response (200 OK):**
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "session_id": "5f872b6b-c27c-47ad-a14b-743a558d18fa",
    "status": "unverified",
    "created_at": "2026-01-06T17:05:22+02:00",
    "existing_media": []
  }
}
```

**Status Values:**
- `unverified` - Session created, ready for uploads
- `pending` - Submitted, waiting for processing
- `processing` - Currently being verified
- `verified` - Successfully verified
- `rejected` - Verification failed
- `manual_review` - Needs admin review

---

### Step 2: Upload Selfie Image

**Endpoint:** `POST /api/driver/verification/session/{session_id}/upload`

**Description:** Upload driver's selfie photo for liveness detection.

**Request:**
```bash
curl -X POST "https://smartline-it.com/api/driver/verification/session/5f872b6b-c27c-47ad-a14b-743a558d18fa/upload" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  -F "kind=selfie" \
  -F "file=@/path/to/selfie.jpg"
```

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| kind | string | Yes | Must be: `selfie`, `id_front`, `id_back`, or `liveness_video` |
| file | file | Yes | Image file (JPG, PNG, WebP) - Max 10MB |

**Response (200 OK):**
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "media_id": 21,
    "kind": "selfie",
    "size": 6614,
    "uploaded_at": "2026-01-06T17:05:22+02:00"
  }
}
```

**Image Requirements:**
- **Format:** JPG, PNG, WebP
- **Size:** Maximum 10MB
- **Resolution:** Minimum 640x480 recommended
- **Quality:** Clear, well-lit, facing camera
- **Content:** Single face, front-facing, no glasses/mask

---

### Step 3: Upload ID Front Image

**Endpoint:** `POST /api/driver/verification/session/{session_id}/upload`

**Description:** Upload front side of Egyptian National ID card.

**Request:**
```bash
curl -X POST "https://smartline-it.com/api/driver/verification/session/5f872b6b-c27c-47ad-a14b-743a558d18fa/upload" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  -F "kind=id_front" \
  -F "file=@/path/to/id_front.jpg"
```

**Response (200 OK):**
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "media_id": 22,
    "kind": "id_front",
    "size": 6209,
    "uploaded_at": "2026-01-06T17:05:22+02:00"
  }
}
```

**ID Card Requirements:**
- **Format:** JPG, PNG, WebP
- **Size:** Maximum 10MB
- **Quality:** All text must be readable
- **Content:** Full ID card visible, no glare/shadows
- **Type:** Egyptian National ID card

**Optional:** Upload ID back side with `kind=id_back`

---

### Step 4: Submit for Verification

**Endpoint:** `POST /api/driver/verification/session/{session_id}/submit`

**Description:** Submit the session for KYC processing. Requires at least selfie and id_front uploaded.

**Request:**
```bash
curl -X POST "https://smartline-it.com/api/driver/verification/session/5f872b6b-c27c-47ad-a14b-743a558d18fa/submit" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json"
```

**Response (200 OK):**
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "message": "Verification submitted successfully",
    "session_id": "5f872b6b-c27c-47ad-a14b-743a558d18fa",
    "status": "pending"
  }
}
```

**Error Response (400 Bad Request):**
```json
{
  "response_code": "default_400",
  "errors": [
    {
      "message": "Missing required media. Required: selfie, id_front"
    }
  ]
}
```

---

## Check Verification Status

**Endpoint:** `GET /api/driver/verification/status`

**Description:** Get current verification status and results for the authenticated driver.

**Request:**
```bash
curl -X GET "https://smartline-it.com/api/driver/verification/status" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json"
```

**Response - Processing (200 OK):**
```json
{
  "response_code": "default_200",
  "data": {
    "has_session": true,
    "session_id": "5f872b6b-c27c-47ad-a14b-743a558d18fa",
    "status": "pending",
    "decision": "pending",
    "kyc_status": "pending",
    "created_at": "2026-01-06T17:05:22+02:00",
    "submitted_at": "2026-01-06T17:05:23+02:00",
    "processed_at": null,
    "existing_media": ["selfie", "id_front"]
  }
}
```

**Response - Approved (200 OK):**
```json
{
  "response_code": "default_200",
  "data": {
    "has_session": true,
    "session_id": "5f872b6b-c27c-47ad-a14b-743a558d18fa",
    "status": "verified",
    "decision": "approved",
    "kyc_status": "verified",
    "created_at": "2026-01-06T17:05:22+02:00",
    "submitted_at": "2026-01-06T17:05:23+02:00",
    "processed_at": "2026-01-06T17:05:24+02:00",
    "existing_media": ["selfie", "id_front"],
    "scores": {
      "liveness": 95.50,
      "face_match": 88.25,
      "doc_auth": 82.00
    },
    "extracted_fields": {
      "name": "محمد أحمد",
      "id_number": "29001011234567",
      "birth_date": "1990-01-01",
      "governorate": "Cairo",
      "gender": "Male"
    }
  }
}
```

**Response - Rejected (200 OK):**
```json
{
  "response_code": "default_200",
  "data": {
    "has_session": true,
    "session_id": "5f872b6b-c27c-47ad-a14b-743a558d18fa",
    "status": "rejected",
    "decision": "rejected",
    "kyc_status": "rejected",
    "created_at": "2026-01-06T17:05:22+02:00",
    "submitted_at": "2026-01-06T17:05:23+02:00",
    "processed_at": "2026-01-06T17:05:24+02:00",
    "existing_media": ["selfie", "id_front"],
    "scores": {
      "liveness": 45.00,
      "face_match": 55.00,
      "doc_auth": 60.00
    },
    "reason_codes": [
      {
        "code": "FACE_MISMATCH",
        "message": "Face in selfie doesn't match ID photo"
      },
      {
        "code": "LOW_DOC_AUTHENTICITY",
        "message": "Document authenticity score too low"
      }
    ]
  }
}
```

---

## Rejection Reason Codes

| Code | Description |
|------|-------------|
| `LOW_QUALITY_SELFIE` | Image is too blurry or low quality |
| `NO_FACE_DETECTED` | No face found in selfie image |
| `MULTIPLE_FACES` | More than one face detected |
| `FACE_MISMATCH` | Face in selfie doesn't match ID photo |
| `LOW_LIVENESS_SCORE` | Liveness detection failed (possible photo of photo) |
| `DOC_PROCESSING_FAILED` | Could not process ID document |
| `NO_DOC_DETECTED` | No document detected in image |
| `LOW_DOC_AUTHENTICITY` | Document authenticity score too low |
| `EXPIRED_ID` | ID card has expired |
| `UNDERAGE` | Driver is below minimum age requirement |

---

## Complete Example (Shell Script)

```bash
#!/bin/bash

# Configuration
BASE_URL="https://smartline-it.com/api"
PHONE="+201234567890"
PASSWORD="your_password"

# Step 1: Login
echo "Logging in..."
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/driver/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"phone\":\"$PHONE\",\"password\":\"$PASSWORD\"}")

TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.token')
echo "Token: $TOKEN"

# Step 2: Create Session
echo "Creating verification session..."
SESSION_RESPONSE=$(curl -s -X POST "$BASE_URL/driver/verification/session" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json")

SESSION_ID=$(echo "$SESSION_RESPONSE" | jq -r '.data.session_id')
echo "Session ID: $SESSION_ID"

# Step 3: Upload Selfie
echo "Uploading selfie..."
curl -s -X POST "$BASE_URL/driver/verification/session/$SESSION_ID/upload" \
  -H "Authorization: Bearer $TOKEN" \
  -F "kind=selfie" \
  -F "file=@selfie.jpg"

# Step 4: Upload ID Front
echo "Uploading ID card..."
curl -s -X POST "$BASE_URL/driver/verification/session/$SESSION_ID/upload" \
  -H "Authorization: Bearer $TOKEN" \
  -F "kind=id_front" \
  -F "file=@id_front.jpg"

# Step 5: Submit
echo "Submitting for verification..."
curl -s -X POST "$BASE_URL/driver/verification/session/$SESSION_ID/submit" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json"

# Step 6: Poll Status
echo "Checking status..."
for i in {1..20}; do
  STATUS_RESPONSE=$(curl -s -X GET "$BASE_URL/driver/verification/status" \
    -H "Authorization: Bearer $TOKEN")
  
  STATUS=$(echo "$STATUS_RESPONSE" | jq -r '.data.status')
  echo "Attempt $i: Status = $STATUS"
  
  if [ "$STATUS" = "verified" ] || [ "$STATUS" = "rejected" ]; then
    echo "Verification complete!"
    echo "$STATUS_RESPONSE" | jq '.'
    break
  fi
  
  sleep 3
done
```

---

## Flutter/Dart Example

```dart
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'dart:io';

class KycService {
  final String baseUrl = 'https://smartline-it.com/api/driver';
  String? token;

  // Step 1: Create Session
  Future<String> createSession() async {
    final response = await http.post(
      Uri.parse('$baseUrl/verification/session'),
      headers: {
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      return data['data']['session_id'];
    }
    throw Exception('Failed to create session');
  }

  // Step 2 & 3: Upload Media
  Future<void> uploadMedia(String sessionId, String kind, File file) async {
    var request = http.MultipartRequest(
      'POST',
      Uri.parse('$baseUrl/verification/session/$sessionId/upload'),
    );

    request.headers['Authorization'] = 'Bearer $token';
    request.headers['Accept'] = 'application/json';
    request.fields['kind'] = kind;
    request.files.add(await http.MultipartFile.fromPath('file', file.path));

    final response = await request.send();
    if (response.statusCode != 200) {
      throw Exception('Upload failed');
    }
  }

  // Step 4: Submit Session
  Future<void> submitSession(String sessionId) async {
    final response = await http.post(
      Uri.parse('$baseUrl/verification/session/$sessionId/submit'),
      headers: {
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    );

    if (response.statusCode != 200) {
      throw Exception('Submit failed');
    }
  }

  // Step 5: Get Status
  Future<Map<String, dynamic>> getStatus() async {
    final response = await http.get(
      Uri.parse('$baseUrl/verification/status'),
      headers: {
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      return data['data'];
    }
    throw Exception('Failed to get status');
  }

  // Complete Flow
  Future<void> completeKyc(File selfie, File idFront) async {
    // Create session
    String sessionId = await createSession();
    print('Session created: $sessionId');

    // Upload selfie
    await uploadMedia(sessionId, 'selfie', selfie);
    print('Selfie uploaded');

    // Upload ID
    await uploadMedia(sessionId, 'id_front', idFront);
    print('ID uploaded');

    // Submit
    await submitSession(sessionId);
    print('Submitted for verification');

    // Poll status
    for (int i = 0; i < 20; i++) {
      await Future.delayed(Duration(seconds: 3));
      var status = await getStatus();
      
      print('Status: ${status['status']}');
      
      if (status['status'] == 'verified' || status['status'] == 'rejected') {
        print('Verification complete!');
        print('Decision: ${status['decision']}');
        if (status['scores'] != null) {
          print('Scores: ${status['scores']}');
        }
        break;
      }
    }
  }
}
```

---

## Rate Limiting

All verification endpoints are rate-limited:
- **Limit:** 10 requests per minute per driver
- **Scope:** Per authenticated user

If exceeded, you'll receive:
```json
{
  "response_code": "default_429",
  "message": "Too many requests. Please try again later."
}
```

---

## Best Practices

### 1. Image Quality
- ✅ Use high-resolution images (minimum 640x480)
- ✅ Ensure good lighting, no glare
- ✅ Capture full ID card, all corners visible
- ✅ Face should be clear and front-facing
- ❌ Don't use screenshots or photos of photos

### 2. Polling
- ⏱️ Wait 2-3 seconds between status checks
- 🔄 Poll up to 20 times (1 minute total)
- ✅ Check for final states: `verified`, `rejected`, `manual_review`

### 3. Error Handling
- 🔄 Handle network errors gracefully
- ⚠️ Display clear error messages to users
- 🔁 Allow users to retry on failure

### 4. User Experience
- 📸 Provide camera guidelines before capture
- ℹ️ Show upload progress indicators
- ✅ Display verification results clearly
- 📧 Notify users when manual review is needed

---

## Processing Time

| Stage | Expected Time |
|-------|---------------|
| Session Creation | Instant |
| File Upload | 1-5 seconds |
| Verification Processing | 1-60 seconds |
| Total Flow | 5-90 seconds |

---

## Test Credentials

Use the test script to create a test driver:
```bash
cd /var/www/laravel/smartlinevps/rateel
./test_kyc_with_images.sh
```

Or use existing test driver:
- **Phone:** +20107711921
- **Password:** Test123456!

---

## Support

### Check Service Status
```bash
curl https://smartline-it.com:8100/health
```

### View Documentation
- Full API: `/var/www/laravel/smartlinevps/rateel/docs/KYC_FLUTTER_API.md`
- Test Results: `/var/www/laravel/smartlinevps/KYC_LIVENESS_TEST_RESULTS.md`

### Troubleshooting
1. **401 Unauthorized:** Invalid or expired token
2. **404 Not Found:** Invalid session ID
3. **400 Bad Request:** Missing required files or invalid format
4. **429 Too Many Requests:** Rate limit exceeded, wait 1 minute
5. **500 Internal Error:** Contact support

---

## Quick Start Checklist

- [ ] Get driver authentication token
- [ ] Create verification session
- [ ] Upload high-quality selfie image
- [ ] Upload clear ID card image
- [ ] Submit session for processing
- [ ] Poll status endpoint until complete
- [ ] Handle result (approved/rejected/manual_review)

---

**API Version:** 1.0  
**Last Updated:** 2026-01-06  
**Base URL:** https://smartline-it.com/api/driver
