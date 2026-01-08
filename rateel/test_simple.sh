#!/bin/bash

BASE_URL="https://smartline-it.com/api"
PHONE="+2011767463164"
PASSWORD="password123"

# Login
TOKEN=$(curl -s -X POST "$BASE_URL/v2/driver/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"phone\":\"$PHONE\",\"password\":\"$PASSWORD\"}" | jq -r '.data.token')

echo "Token: ${TOKEN:0:30}..."
echo ""

# Test 4: Account Deletion Request
echo "=========================================="
echo "TEST: Account Deletion Request"
echo "=========================================="
curl -s -X POST "$BASE_URL/driver/auth/account/delete-request" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"reason":"testing","password":"password123"}'
echo ""
echo ""

# Test 5: Weekly Report
echo "=========================================="
echo "TEST: Weekly Report"
echo "=========================================="
curl -s -X GET "$BASE_URL/driver/auth/reports/weekly" \
  -H "Authorization: Bearer $TOKEN"
echo ""
echo ""

# Test: Cancel Deletion
echo "=========================================="
echo "TEST: Cancel Account Deletion"
echo "=========================================="
curl -s -X POST "$BASE_URL/driver/auth/account/delete-cancel" \
  -H "Authorization: Bearer $TOKEN"
echo ""
echo ""

# Test: Deletion Status
echo "=========================================="
echo "TEST: Account Deletion Status"
echo "=========================================="
curl -s -X GET "$BASE_URL/driver/auth/account/delete-status" \
  -H "Authorization: Bearer $TOKEN"
echo ""

