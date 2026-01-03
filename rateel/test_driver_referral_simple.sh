#!/bin/bash

# Simple Driver Referral Test Script
# Usage: DRIVER_TOKEN="your_token" ./test_driver_referral_simple.sh

BASE_URL="https://smartline-it.com/api"
DRIVER_TOKEN="${DRIVER_TOKEN:-}"

if [ -z "$DRIVER_TOKEN" ]; then
    echo "Error: DRIVER_TOKEN is required"
    echo "Usage: DRIVER_TOKEN='your_token' $0"
    exit 1
fi

echo "=========================================="
echo "Testing Driver Referral System"
echo "Domain: $BASE_URL"
echo "=========================================="
echo ""

# Test 1: Get Referral Details
echo "1. GET /api/driver/referral-details"
echo "-----------------------------------"
curl -s -X GET "$BASE_URL/driver/referral-details" \
  -H "Authorization: Bearer $DRIVER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" | jq '.' || echo "Response received"
echo ""
echo ""

# Test 2: Get Referral Earnings History
echo "2. GET /api/driver/transaction/referral-earning-list"
echo "-----------------------------------------------------"
curl -s -X GET "$BASE_URL/driver/transaction/referral-earning-list?limit=10&offset=1" \
  -H "Authorization: Bearer $DRIVER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" | jq '.' || echo "Response received"
echo ""

echo "=========================================="
echo "Tests completed!"
echo "=========================================="
