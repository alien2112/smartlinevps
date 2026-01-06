# KYC Liveness Verification - Quick Reference

## 🚀 Base URL
```
https://smartline-it.com/api/driver
```

## 🔑 Authentication
```http
Authorization: Bearer {your_token}
```

---

## 📋 4-Step Integration

### 1️⃣ Create Session
```bash
POST /verification/session
```
**Returns:** `session_id`

### 2️⃣ Upload Selfie
```bash
POST /verification/session/{session_id}/upload
Content-Type: multipart/form-data

kind=selfie
file=@selfie.jpg
```

### 3️⃣ Upload ID Card
```bash
POST /verification/session/{session_id}/upload
Content-Type: multipart/form-data

kind=id_front
file=@id_card.jpg
```

### 4️⃣ Submit & Poll
```bash
# Submit
POST /verification/session/{session_id}/submit

# Check Status (poll every 3 seconds)
GET /verification/status
```

---

## 📊 Response Status Values

| Status | Meaning |
|--------|---------|
| `unverified` | Ready for uploads |
| `pending` | Processing... |
| `verified` | ✅ Approved |
| `rejected` | ❌ Denied |
| `manual_review` | 👤 Admin review needed |

---

## ✅ Image Requirements

### Selfie
- ✅ Clear face, front-facing
- ✅ Good lighting, no glare
- ✅ 640x480 minimum
- ✅ JPG, PNG, WebP
- ✅ Max 10MB

### ID Card
- ✅ Full card visible
- ✅ All text readable
- ✅ Egyptian National ID
- ✅ No shadows/glare
- ✅ Max 10MB

---

## 🧪 Test It Now

```bash
cd /var/www/laravel/smartlinevps/rateel
./test_kyc_with_images.sh
```

**Test Driver:**
- Phone: `+20107711921`
- Password: `Test123456!`

---

## 📱 One-Liner cURL Test

```bash
# Login
TOKEN=$(curl -s -X POST "https://smartline-it.com/api/driver/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"phone":"+20107711921","password":"Test123456!"}' | jq -r '.token')

# Create Session
SESSION=$(curl -s -X POST "https://smartline-it.com/api/driver/verification/session" \
  -H "Authorization: Bearer $TOKEN" | jq -r '.data.session_id')

# Upload Selfie
curl -X POST "https://smartline-it.com/api/driver/verification/session/$SESSION/upload" \
  -H "Authorization: Bearer $TOKEN" \
  -F "kind=selfie" -F "file=@selfie.jpg"

# Upload ID
curl -X POST "https://smartline-it.com/api/driver/verification/session/$SESSION/upload" \
  -H "Authorization: Bearer $TOKEN" \
  -F "kind=id_front" -F "file=@id.jpg"

# Submit
curl -X POST "https://smartline-it.com/api/driver/verification/session/$SESSION/submit" \
  -H "Authorization: Bearer $TOKEN"

# Check Status
curl -X GET "https://smartline-it.com/api/driver/verification/status" \
  -H "Authorization: Bearer $TOKEN" | jq '.'
```

---

## 📚 Full Documentation

- **Integration Guide:** `/var/www/laravel/smartlinevps/KYC_INTEGRATION_GUIDE.md`
- **Test Results:** `/var/www/laravel/smartlinevps/KYC_LIVENESS_TEST_RESULTS.md`
- **Flutter API:** `/var/www/laravel/smartlinevps/rateel/docs/KYC_FLUTTER_API.md`

---

## ⚡ Processing Time
- **Upload:** 1-5 seconds
- **Verification:** 1-60 seconds
- **Total:** 5-90 seconds

---

## 🔒 Rate Limit
**10 requests/minute** per driver

---

## 📞 Support
Check service: `curl https://smartline-it.com:8100/health`

---

**Ready to integrate!** 🎉
