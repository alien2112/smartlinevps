# Driver Referral System - Complete cURL Test Commands

## Base URL
```
https://smartline-it.com/api
```

## Prerequisites
1. Get a driver token by logging in as a driver
2. Replace `YOUR_DRIVER_TOKEN` in the commands below

---

## Driver Referral Endpoints

### 1. Get Driver Referral Details
**Endpoint:** `GET /api/driver/referral-details`

**Description:** Get driver's referral code and earnings information.

**Authentication:** ✅ Required (Driver Token)

**cURL Command:**
```bash
curl -X GET "https://smartline-it.com/api/driver/referral-details" \
  -H "Authorization: Bearer YOUR_DRIVER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json"
```

**Expected Response:**
```json
{
  "response_code": "default_200",
  "message": "Successfully retrieved",
  "data": {
    "referral_code": "DRV-ABC123",
    "share_code_earning": 50.00,
    "use_code_earning": 25.00
  }
}
```

**Response Fields:**
- `referral_code`: Driver's unique referral code
- `share_code_earning`: Amount earned when someone uses driver's code (shares it)
- `use_code_earning`: Amount earned when driver uses someone else's code

---

### 2. Get Driver Referral Earnings History
**Endpoint:** `GET /api/driver/transaction/referral-earning-list`

**Description:** Get list of referral earnings transactions for the driver.

**Authentication:** ✅ Required (Driver Token)

**Query Parameters:**
- `limit`: Number of results per page (optional, default: 10)
- `offset`: Page number (optional, default: 1)

**cURL Command:**
```bash
curl -X GET "https://smartline-it.com/api/driver/transaction/referral-earning-list?limit=10&offset=1" \
  -H "Authorization: Bearer YOUR_DRIVER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json"
```

**Expected Response:**
```json
{
  "response_code": "default_200",
  "message": "Successfully retrieved",
  "data": [
    {
      "id": "transaction_uuid",
      "amount": 50.00,
      "type": "referral_earning",
      "description": "Referral earning from user signup",
      "created_at": "2026-01-01T10:00:00Z",
      "status": "completed"
    }
  ],
  "limit": 10,
  "offset": 1
}
```

---

## Complete Test Sequence

### Step 1: Login as Driver
```bash
curl -X POST "https://smartline-it.com/api/driver/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "+201012345678",
    "password": "password123"
  }'
```

**Save the token from response:**
```json
{
  "token": "eyJhbGciOiJIUzI1NiIs...",
  "token_type": "Bearer"
}
```

### Step 2: Get Referral Details
```bash
# Replace YOUR_DRIVER_TOKEN with token from Step 1
curl -X GET "https://smartline-it.com/api/driver/referral-details" \
  -H "Authorization: Bearer YOUR_DRIVER_TOKEN" \
  -H "Content-Type: application/json"
```

### Step 3: Get Referral Earnings History
```bash
curl -X GET "https://smartline-it.com/api/driver/transaction/referral-earning-list?limit=20&offset=1" \
  -H "Authorization: Bearer YOUR_DRIVER_TOKEN" \
  -H "Content-Type: application/json"
```

---

## Quick Test Script

Save this as `test_driver_referral_quick.sh`:

```bash
#!/bin/bash

BASE_URL="https://smartline-it.com/api"
DRIVER_TOKEN="YOUR_DRIVER_TOKEN"

echo "=========================================="
echo "Driver Referral System Tests"
echo "=========================================="
echo ""

echo "1. Get Referral Details"
curl -s -X GET "$BASE_URL/driver/referral-details" \
  -H "Authorization: Bearer $DRIVER_TOKEN" \
  -H "Content-Type: application/json" | jq '.'

echo -e "\n2. Get Referral Earnings History"
curl -s -X GET "$BASE_URL/driver/transaction/referral-earning-list?limit=10&offset=1" \
  -H "Authorization: Bearer $DRIVER_TOKEN" \
  -H "Content-Type: application/json" | jq '.'
```

---

## Testing with Real Data

### Example: Full Test Flow

```bash
# 1. Login
TOKEN=$(curl -s -X POST "https://smartline-it.com/api/driver/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"phone":"+201012345678","password":"password123"}' \
  | jq -r '.token // .data.token // empty')

if [ -z "$TOKEN" ]; then
  echo "Login failed"
  exit 1
fi

echo "Token: $TOKEN"
echo ""

# 2. Get Referral Details
echo "Getting referral details..."
curl -s -X GET "https://smartline-it.com/api/driver/referral-details" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" | jq '.'

echo ""

# 3. Get Referral Earnings
echo "Getting referral earnings..."
curl -s -X GET "https://smartline-it.com/api/driver/transaction/referral-earning-list?limit=10&offset=1" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" | jq '.'
```

---

## Expected Behaviors

### Referral Details Endpoint
- ✅ Returns referral code if driver has one
- ✅ Returns earnings amounts (may be 0 if not configured)
- ✅ Returns 401 if not authenticated
- ✅ Returns 401 if user is not a driver

### Referral Earnings List Endpoint
- ✅ Returns paginated list of referral transactions
- ✅ Shows earnings from referrals
- ✅ Includes transaction details (amount, date, type)
- ✅ Returns empty array if no earnings yet

---

## Troubleshooting

### Issue: "Unauthenticated" Error
**Solution:** Make sure you're using a valid driver token from login.

### Issue: Empty Referral Code
**Solution:** Driver may not have a referral code yet. The system should generate one automatically.

### Issue: No Earnings in History
**Solution:** 
- Check if referral system is enabled in admin settings
- Verify driver has referred anyone
- Check if referrals have completed required actions (signup, first ride, etc.)

---

**Last Updated:** 2026-01-03
