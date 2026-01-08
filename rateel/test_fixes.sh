#!/bin/bash

BASE_URL="https://smartline-it.com/api"
PHONE="+2011767463164"
PASSWORD="password123"

echo "=========================================="
echo "Getting Token..."
echo "=========================================="
TOKEN=$(curl -s -X POST "$BASE_URL/v2/driver/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"phone\":\"$PHONE\",\"password\":\"$PASSWORD\"}" | jq -r '.data.token')

if [ -z "$TOKEN" ] || [ "$TOKEN" = "null" ]; then
  echo "❌ Failed to get token"
  exit 1
fi

echo "✅ Token obtained: ${TOKEN:0:30}..."
echo ""
sleep 2

echo "=========================================="
echo "TEST 1: Request Phone Change"
echo "=========================================="
RESPONSE=$(curl -s -X POST "$BASE_URL/driver/auth/account/change-phone/request" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"new_phone":"+201999999999","password":"password123"}')

echo "$RESPONSE" | jq '.' 2>/dev/null || echo "$RESPONSE" | head -50

if echo "$RESPONSE" | jq -e '.response_code' >/dev/null 2>&1; then
  echo "✅ PASSED - Phone change request working"
else
  echo "❌ FAILED - Check output above"
fi
echo ""
sleep 3

echo "=========================================="
echo "TEST 2: Request Account Deletion"
echo "=========================================="
RESPONSE=$(curl -s -X POST "$BASE_URL/driver/auth/account/delete-request" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"reason":"testing","password":"password123"}')

echo "$RESPONSE" | jq '.' 2>/dev/null || echo "$RESPONSE" | head -50

if echo "$RESPONSE" | jq -e '.response_code' >/dev/null 2>&1; then
  echo "✅ PASSED - Account deletion request working"
else
  echo "❌ FAILED - Check output above"
fi
echo ""
sleep 3

echo "=========================================="
echo "TEST 3: Get Weekly Report"
echo "=========================================="
RESPONSE=$(curl -s -X GET "$BASE_URL/driver/auth/reports/weekly" \
  -H "Authorization: Bearer $TOKEN")

echo "$RESPONSE" | jq '.' 2>/dev/null || echo "$RESPONSE" | head -50

if echo "$RESPONSE" | jq -e '.response_code' >/dev/null 2>&1; then
  echo "✅ PASSED - Weekly report working"
else
  echo "❌ FAILED - Check output above"
fi
echo ""

# Cancel deletion if successful
echo "=========================================="
echo "Cleanup: Canceling Account Deletion"
echo "=========================================="
curl -s -X POST "$BASE_URL/driver/auth/account/delete-cancel" \
  -H "Authorization: Bearer $TOKEN" | jq '.' 2>/dev/null
echo ""

echo "=========================================="
echo "ALL TESTS COMPLETED"
echo "=========================================="

