#!/bin/bash

BASE_URL="https://smartline-it.com/api"
PHONE="+2011767463164"
PASSWORD="password123"

echo "==================================="
echo "LOGGING IN TO GET TOKEN"
echo "==================================="

# Login to get token
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/v2/driver/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"phone\":\"$PHONE\",\"password\":\"$PASSWORD\"}")

echo "$LOGIN_RESPONSE" | jq '.' 2>/dev/null || echo "$LOGIN_RESPONSE"

TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.data.token // .token // empty' 2>/dev/null)

if [ -z "$TOKEN" ] || [ "$TOKEN" = "null" ]; then
  echo "Failed to get token. Exiting."
  exit 1
fi

echo ""
echo "✅ Token obtained: ${TOKEN:0:30}..."
echo ""
sleep 2

echo "==================================="
echo "TEST 1: Request Phone Change"
echo "==================================="
curl -s -X POST "$BASE_URL/driver/auth/account/change-phone/request" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"new_phone":"+201111111111"}' | jq '.' 2>/dev/null || echo "Failed to parse JSON"
echo ""
sleep 3

echo "==================================="
echo "TEST 2: Verify Old Phone"
echo "==================================="
curl -s -X POST "$BASE_URL/driver/auth/account/change-phone/verify-old" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"otp":"123456"}' | jq '.' 2>/dev/null || echo "Failed to parse JSON"
echo ""
sleep 3

echo "==================================="
echo "TEST 3: Verify New Phone"
echo "==================================="
curl -s -X POST "$BASE_URL/driver/auth/account/change-phone/verify-new" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"otp":"123456"}' | jq '.' 2>/dev/null || echo "Failed to parse JSON"
echo ""
sleep 3

echo "==================================="
echo "TEST 4: Request Account Deletion"
echo "==================================="
curl -s -X POST "$BASE_URL/driver/auth/account/delete-request" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"reason":"testing","password":"password123"}' | jq '.' 2>/dev/null || echo "Failed to parse JSON"
echo ""
sleep 3

echo "==================================="
echo "TEST 5: Get Weekly Report"
echo "==================================="
curl -s -X GET "$BASE_URL/driver/auth/reports/weekly" \
  -H "Authorization: Bearer $TOKEN" | jq '.' 2>/dev/null || echo "Failed to parse JSON"
echo ""

echo "==================================="
echo "ALL FAILED TESTS COMPLETED"
echo "==================================="

