#!/bin/bash

# Customer Referral System Test Script
# Base URL
BASE_URL="https://smartline-it.com/api"

echo "=========================================="
echo "Customer Referral System Test"
echo "=========================================="
echo ""

# Test 1: Validate Referral Code (Public - No Auth)
echo "1. Testing: Validate Referral Code (Public)"
echo "-------------------------------------------"
REF_CODE="O7PSQIVVZU"
curl -X GET "${BASE_URL}/customer/referral/validate-code?code=${REF_CODE}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' || echo "Response received"
echo ""
echo ""

# Test 2: Get Customer Token (Login)
echo "2. Testing: Customer Login to get token"
echo "-------------------------------------------"
# Using a test customer phone - you may need to adjust this
CUSTOMER_PHONE="+201010006782"
CUSTOMER_PASSWORD="123456"  # Default password - adjust if needed

LOGIN_RESPONSE=$(curl -X POST "${BASE_URL}/customer/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{
    \"phone\": \"${CUSTOMER_PHONE}\",
    \"password\": \"${CUSTOMER_PASSWORD}\"
  }" \
  -s)

echo "$LOGIN_RESPONSE" | jq '.' || echo "$LOGIN_RESPONSE"
echo ""

# Extract token from response
TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.data.token // .token // empty' 2>/dev/null)

if [ -z "$TOKEN" ] || [ "$TOKEN" = "null" ] || [ "$TOKEN" = "" ]; then
    echo "⚠️  Could not get token. Trying alternative method..."
    # Try alternative response format
    TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.token // empty' 2>/dev/null)
fi

if [ -z "$TOKEN" ] || [ "$TOKEN" = "null" ] || [ "$TOKEN" = "" ]; then
    echo "❌ Failed to get authentication token. Please check credentials."
    echo "Response was: $LOGIN_RESPONSE"
    exit 1
fi

echo "✅ Token obtained: ${TOKEN:0:20}..."
echo ""
echo ""

# Test 3: Get My Referral Code
echo "3. Testing: Get My Referral Code"
echo "-------------------------------------------"
curl -X GET "${BASE_URL}/customer/referral/my-code" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' || echo "Response received"
echo ""
echo ""

# Test 4: Get Referral Stats
echo "4. Testing: Get Referral Stats"
echo "-------------------------------------------"
curl -X GET "${BASE_URL}/customer/referral/stats" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' || echo "Response received"
echo ""
echo ""

# Test 5: Get Referral History
echo "5. Testing: Get Referral History"
echo "-------------------------------------------"
curl -X GET "${BASE_URL}/customer/referral/history" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' || echo "Response received"
echo ""
echo ""

# Test 6: Get Referral Rewards
echo "6. Testing: Get Referral Rewards"
echo "-------------------------------------------"
curl -X GET "${BASE_URL}/customer/referral/rewards" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' || echo "Response received"
echo ""
echo ""

# Test 7: Generate Invite
echo "7. Testing: Generate Invite"
echo "-------------------------------------------"
curl -X POST "${BASE_URL}/customer/referral/generate-invite" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "channel": "whatsapp",
    "platform": "android"
  }' \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' || echo "Response received"
echo ""
echo ""

# Test 8: Get Leaderboard
echo "8. Testing: Get Leaderboard"
echo "-------------------------------------------"
curl -X GET "${BASE_URL}/customer/referral/leaderboard" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' || echo "Response received"
echo ""
echo ""

echo "=========================================="
echo "Test Complete"
echo "=========================================="
