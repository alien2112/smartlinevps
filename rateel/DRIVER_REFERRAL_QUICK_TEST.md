# Driver Referral System - Quick cURL Tests

## Base URL
```
https://smartline-it.com/api
```

## Quick Test Commands

### 1. Get Driver Referral Details
```bash
curl -X GET "https://smartline-it.com/api/driver/referral-details" \
  -H "Authorization: Bearer YOUR_DRIVER_TOKEN" \
  -H "Content-Type: application/json"
```

### 2. Get Driver Referral Earnings History
```bash
curl -X GET "https://smartline-it.com/api/driver/transaction/referral-earning-list?limit=10&offset=1" \
  -H "Authorization: Bearer YOUR_DRIVER_TOKEN" \
  -H "Content-Type: application/json"
```

---

## Complete Test Flow

### Step 1: Login as Driver
```bash
curl -X POST "https://smartline-it.com/api/driver/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "+201012345678",
    "password": "your_password"
  }'
```

**Extract token from response:**
```bash
TOKEN=$(curl -s -X POST "https://smartline-it.com/api/driver/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"phone":"+201012345678","password":"your_password"}' \
  | jq -r '.token // .data.token // empty')

echo "Token: $TOKEN"
```

### Step 2: Test Referral Endpoints
```bash
# Get referral details
curl -X GET "https://smartline-it.com/api/driver/referral-details" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" | jq '.'

# Get referral earnings
curl -X GET "https://smartline-it.com/api/driver/transaction/referral-earning-list?limit=10&offset=1" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" | jq '.'
```

---

## One-Liner Tests

### Get Referral Code
```bash
curl -s "https://smartline-it.com/api/driver/referral-details" \
  -H "Authorization: Bearer YOUR_TOKEN" | jq '.content.referral_code'
```

### Get Earnings List
```bash
curl -s "https://smartline-it.com/api/driver/transaction/referral-earning-list?limit=5&offset=1" \
  -H "Authorization: Bearer YOUR_TOKEN" | jq '.content'
```

---

## Expected Responses

### Referral Details Response
```json
{
  "response_code": "default_200",
  "data": {
    "referral_code": "DRV-ABC123",
    "share_code_earning": 50.00,
    "use_code_earning": 25.00
  }
}
```

### Referral Earnings Response
```json
{
  "response_code": "default_200",
  "data": [
    {
      "id": "uuid",
      "amount": 50.00,
      "type": "referral_earning",
      "created_at": "2026-01-01T10:00:00Z"
    }
  ],
  "limit": 10,
  "offset": 1
}
```

---

## Test Script Usage

```bash
# Set token and run
export DRIVER_TOKEN="your_driver_token_here"
./test_driver_referral_simple.sh

# Or inline
DRIVER_TOKEN="your_token" ./test_driver_referral_simple.sh
```
