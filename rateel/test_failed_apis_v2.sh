#!/bin/bash

BASE_URL="https://smartline-it.com/api"
PHONE="+2011767463164"
PASSWORD="password123"

echo "==================================="
echo "LOGGING IN"
echo "==================================="

LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/v2/driver/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"phone\":\"$PHONE\",\"password\":\"$PASSWORD\"}")

TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.data.token // .token // empty' 2>/dev/null)

if [ -z "$TOKEN" ] || [ "$TOKEN" = "null" ]; then
  echo "Failed to get token"
  exit 1
fi

echo "✅ Token obtained"
echo ""
sleep 2

echo "==================================="
echo "TEST 1: Request Phone Change (with password)"
echo "==================================="
curl -s -X POST "$BASE_URL/driver/auth/account/change-phone/request" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"new_phone":"+201111111111","password":"password123"}' | jq '.' 2>/dev/null || curl -s -X POST "$BASE_URL/driver/auth/account/change-phone/request" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"new_phone":"+201111111111","password":"password123"}'
echo ""
sleep 3

echo "==================================="
echo "TEST 2: Verify Old Phone"
echo "==================================="
RESPONSE=$(curl -s -X POST "$BASE_URL/driver/auth/account/change-phone/verify-old" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"otp":"123456"}')
echo "$RESPONSE" | jq '.' 2>/dev/null || echo "$RESPONSE"
echo ""
sleep 3

echo "==================================="
echo "TEST 3: Verify New Phone"
echo "==================================="
RESPONSE=$(curl -s -X POST "$BASE_URL/driver/auth/account/change-phone/verify-new" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"otp":"123456"}')
echo "$RESPONSE" | jq '.' 2>/dev/null || echo "$RESPONSE"
echo ""
sleep 3

echo "==================================="
echo "TEST 4: Request Account Deletion"
echo "==================================="
RESPONSE=$(curl -s -X POST "$BASE_URL/driver/auth/account/delete-request" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"reason":"testing","password":"password123"}')
echo "$RESPONSE" | jq '.' 2>/dev/null || echo "$RESPONSE"
echo ""
sleep 3

echo "==================================="
echo "TEST 5: Get Weekly Report"
echo "==================================="
RESPONSE=$(curl -s -X GET "$BASE_URL/driver/auth/reports/weekly" \
  -H "Authorization: Bearer $TOKEN")
echo "$RESPONSE" | jq '.' 2>/dev/null || echo "$RESPONSE"
echo ""

echo "==================================="
echo "TESTS COMPLETED"
echo "==================================="

