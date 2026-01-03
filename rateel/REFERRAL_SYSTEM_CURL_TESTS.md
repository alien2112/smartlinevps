# Referral System - Complete cURL Test Commands

## Base URL
```
https://smartline-it.com/api
```

## Prerequisites
1. Get a customer token by logging in as a customer
2. Get a driver token by logging in as a driver
3. Replace `YOUR_CUSTOMER_TOKEN` and `YOUR_DRIVER_TOKEN` in the commands below

---

## Customer Referral Endpoints

### 1. Validate Referral Code (Public - No Auth Required)
```bash
curl -X GET "https://smartline-it.com/api/customer/referral/validate-code?code=TEST123" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json"
```

**Expected Response:**
```json
{
  "response_code": "default_200",
  "data": {
    "valid": true,
    "code": "TEST123",
    "message": "Valid referral code"
  }
}
```

---

### 2. Get My Referral Code
```bash
curl -X GET "https://smartline-it.com/api/customer/referral/my-code" \
  -H "Authorization: Bearer YOUR_CUSTOMER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json"
```

**Expected Response:**
```json
{
  "response_code": "default_200",
  "data": {
    "ref_code": "ahmed-a1b2",
    "shareable_link": "https://smartline-it.com/invite/ahmed-a1b2",
    "qr_data": "https://smartline-it.com/invite/ahmed-a1b2",
    "is_active": true,
    "referrer_points": 100,
    "referee_points": 50,
    "reward_trigger": "signup",
    "message": "Invite friends and earn 100 points when they sign up! They get 50 points too."
  }
}
```

---

### 3. Generate Tracked Invite
```bash
curl -X POST "https://smartline-it.com/api/customer/referral/generate-invite" \
  -H "Authorization: Bearer YOUR_CUSTOMER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "channel": "whatsapp",
    "platform": "android"
  }'
```

**Request Body Options:**
- `channel`: `link`, `code`, `qr`, `sms`, `whatsapp`, `copy`
- `platform`: `ios`, `android`, `web` (optional)

**Expected Response:**
```json
{
  "response_code": "default_200",
  "data": {
    "invite_token": "inv_abc123xyz",
    "invite_code": "ahmed-a1b2",
    "shareable_link": "https://smartline-it.com/invite/ahmed-a1b2?token=inv_abc123xyz",
    "channel": "whatsapp"
  }
}
```

---

### 4. Get Referral Statistics
```bash
curl -X GET "https://smartline-it.com/api/customer/referral/stats" \
  -H "Authorization: Bearer YOUR_CUSTOMER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json"
```

**Expected Response:**
```json
{
  "response_code": "default_200",
  "data": {
    "total_invites": 15,
    "successful_invites": 8,
    "pending_invites": 5,
    "expired_invites": 2,
    "total_points_earned": 800,
    "total_rewards": 8,
    "conversion_rate": 53.33
  }
}
```

---

### 5. Get Referral History
```bash
curl -X GET "https://smartline-it.com/api/customer/referral/history?limit=10&offset=1" \
  -H "Authorization: Bearer YOUR_CUSTOMER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json"
```

**Query Parameters:**
- `limit`: Number of results per page (default: 20)
- `offset`: Page number (default: 1)

**Expected Response:**
```json
{
  "response_code": "default_200",
  "data": {
    "invites": [
      {
        "id": "inv_uuid",
        "status": "rewarded",
        "channel": "whatsapp",
        "sent_at": "2026-01-01T10:00:00Z",
        "signup_at": "2026-01-01T11:00:00Z",
        "first_ride_at": "2026-01-02T09:00:00Z",
        "reward_at": "2026-01-02T09:05:00Z",
        "referee": {
          "id": "user_uuid",
          "name": "John",
          "image": "https://..."
        },
        "is_converted": true
      }
    ],
    "total": 15
  },
  "limit": 10,
  "offset": 1
}
```

---

### 6. Get Rewards History
```bash
curl -X GET "https://smartline-it.com/api/customer/referral/rewards?limit=10&offset=1" \
  -H "Authorization: Bearer YOUR_CUSTOMER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json"
```

**Expected Response:**
```json
{
  "response_code": "default_200",
  "data": {
    "rewards": [
      {
        "id": "reward_uuid",
        "points": 100,
        "trigger_type": "signup",
        "paid_at": "2026-01-01T11:05:00Z",
        "referee": {
          "id": "user_uuid",
          "name": "John",
          "image": "https://..."
        }
      }
    ],
    "total": 8,
    "total_points": 800
  },
  "limit": 10,
  "offset": 1
}
```

---

### 7. Get Referral Leaderboard
```bash
curl -X GET "https://smartline-it.com/api/customer/referral/leaderboard?period=month&limit=10" \
  -H "Authorization: Bearer YOUR_CUSTOMER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json"
```

**Query Parameters:**
- `period`: `week`, `month`, `all` (default: `month`)
- `limit`: Number of top referrers (default: 10)

**Expected Response:**
```json
{
  "response_code": "default_200",
  "data": {
    "leaderboard": [
      {
        "rank": 1,
        "user_id": "user_uuid",
        "name": "Ahmed",
        "total_conversions": 25,
        "total_points": 2500
      }
    ],
    "my_rank": 5,
    "my_conversions": 8,
    "period": "month"
  }
}
```

---

## Driver Referral Endpoints

### 8. Get Driver Referral Details
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
  "data": {
    "referral_code": "DRV-ABC123",
    "share_code_earning": 50.00,
    "use_code_earning": 25.00
  }
}
```

---

## Test Signup with Referral Code

### 9. Customer Registration with Referral Code
```bash
curl -X POST "https://smartline-it.com/api/customer/auth/registration" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "first_name": "Test",
    "last_name": "User",
    "phone": "+201012345678",
    "password": "Test123456!",
    "password_confirmation": "Test123456!",
    "referral_code": "ahmed-a1b2"
  }'
```

**Note:** Replace `ahmed-a1b2` with an actual referral code from step 2.

---

## Complete Test Sequence

### Step 1: Get Customer Token
```bash
# Login as customer first
curl -X POST "https://smartline-it.com/api/customer/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "+201012345678",
    "password": "password123"
  }'
```

### Step 2: Get Referral Code
```bash
curl -X GET "https://smartline-it.com/api/customer/referral/my-code" \
  -H "Authorization: Bearer YOUR_CUSTOMER_TOKEN"
```

### Step 3: Test All Endpoints
Run all the commands above in sequence.

---

## Quick Test Script

Save this as `test_referral.sh`:

```bash
#!/bin/bash

BASE_URL="https://smartline-it.com/api"
CUSTOMER_TOKEN="YOUR_CUSTOMER_TOKEN"
DRIVER_TOKEN="YOUR_DRIVER_TOKEN"

echo "1. Validate Code"
curl -s "$BASE_URL/customer/referral/validate-code?code=TEST" | jq '.'

echo -e "\n2. Get My Code"
curl -s -H "Authorization: Bearer $CUSTOMER_TOKEN" \
  "$BASE_URL/customer/referral/my-code" | jq '.'

echo -e "\n3. Generate Invite"
curl -s -X POST -H "Authorization: Bearer $CUSTOMER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"channel":"whatsapp"}' \
  "$BASE_URL/customer/referral/generate-invite" | jq '.'

echo -e "\n4. Get Stats"
curl -s -H "Authorization: Bearer $CUSTOMER_TOKEN" \
  "$BASE_URL/customer/referral/stats" | jq '.'

echo -e "\n5. Get History"
curl -s -H "Authorization: Bearer $CUSTOMER_TOKEN" \
  "$BASE_URL/customer/referral/history?limit=5" | jq '.'

echo -e "\n6. Get Rewards"
curl -s -H "Authorization: Bearer $CUSTOMER_TOKEN" \
  "$BASE_URL/customer/referral/rewards?limit=5" | jq '.'

echo -e "\n7. Get Leaderboard"
curl -s -H "Authorization: Bearer $CUSTOMER_TOKEN" \
  "$BASE_URL/customer/referral/leaderboard?period=month" | jq '.'

echo -e "\n8. Driver Referral Details"
curl -s -H "Authorization: Bearer $DRIVER_TOKEN" \
  "$BASE_URL/driver/referral-details" | jq '.'
```

---

## Testing Tips

1. **Get Tokens First:**
   - Login as customer to get customer token
   - Login as driver to get driver token

2. **Test Public Endpoint:**
   - Start with validate-code (no auth needed)

3. **Test with Real Data:**
   - Use actual referral codes from your database
   - Test with real user accounts

4. **Check Responses:**
   - All endpoints should return `response_code: "default_200"` on success
   - Check for proper error handling on invalid inputs

5. **Verify Rewards:**
   - After signup with referral code, check rewards are issued
   - Verify points are added to both referrer and referee

---

**Last Updated:** 2026-01-03
