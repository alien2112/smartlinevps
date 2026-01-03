#!/bin/bash

# Driver Referral System - cURL Test Script
# Tests all driver referral endpoints

# Get domain from .env
if [ -f ".env" ]; then
    APP_URL=$(grep "^APP_URL=" .env | cut -d '=' -f2 | tr -d '"' | tr -d "'" | xargs)
    BASE_URL="${APP_URL}/api"
else
    BASE_URL="https://smartline-it.com/api"
fi

echo "=========================================="
echo "Driver Referral System cURL Tests"
echo "Domain: $BASE_URL"
echo "=========================================="
echo ""

# Replace with actual driver token
DRIVER_TOKEN="${DRIVER_TOKEN:-YOUR_DRIVER_TOKEN_HERE}"

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# ============================================
# TEST 1: Get Driver Referral Details
# ============================================
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}1. Get Driver Referral Details${NC}"
echo -e "${BLUE}GET /api/driver/referral-details${NC}"
echo -e "${BLUE}========================================${NC}"

response=$(curl -s -w "\n%{http_code}" -X GET \
  -H "Authorization: Bearer $DRIVER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  "$BASE_URL/driver/referral-details")

http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | sed '$d')

echo -e "${YELLOW}HTTP Status: $http_code${NC}"
echo -e "${YELLOW}Response:${NC}"
echo "$body" | jq '.' 2>/dev/null || echo "$body"

if [ "$http_code" -ge 200 ] && [ "$http_code" -lt 300 ]; then
    echo -e "${GREEN}✓ Request successful${NC}"
    
    # Extract referral code from response
    REFERRAL_CODE=$(echo "$body" | jq -r '.content.referral_code // empty' 2>/dev/null)
    if [ -n "$REFERRAL_CODE" ] && [ "$REFERRAL_CODE" != "null" ]; then
        echo -e "${GREEN}Referral Code: $REFERRAL_CODE${NC}"
        export REFERRAL_CODE
    fi
else
    echo -e "${RED}✗ Request failed${NC}"
fi

echo ""

# ============================================
# TEST 2: Get Driver Referral Earnings List
# ============================================
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}2. Get Driver Referral Earnings List${NC}"
echo -e "${BLUE}GET /api/driver/transaction/referral-earning-list${NC}"
echo -e "${BLUE}========================================${NC}"

response=$(curl -s -w "\n%{http_code}" -X GET \
  -H "Authorization: Bearer $DRIVER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  "$BASE_URL/driver/transaction/referral-earning-list?limit=10&offset=1")

http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | sed '$d')

echo -e "${YELLOW}HTTP Status: $http_code${NC}"
echo -e "${YELLOW}Response:${NC}"
echo "$body" | jq '.' 2>/dev/null || echo "$body"

if [ "$http_code" -ge 200 ] && [ "$http_code" -lt 300 ]; then
    echo -e "${GREEN}✓ Request successful${NC}"
else
    echo -e "${RED}✗ Request failed${NC}"
fi

echo ""

# ============================================
# TEST 3: Test Driver Registration with Referral Code
# ============================================
if [ -n "$REFERRAL_CODE" ] && [ "$REFERRAL_CODE" != "null" ]; then
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}3. Test Driver Registration with Referral Code${NC}"
    echo -e "${BLUE}POST /api/driver/auth/start${NC}"
    echo -e "${BLUE}========================================${NC}"
    echo -e "${YELLOW}Using referral code: $REFERRAL_CODE${NC}"
    echo ""
    echo -e "${YELLOW}Example request (replace phone with test number):${NC}"
    echo "curl -X POST \"$BASE_URL/driver/auth/start\" \\"
    echo "  -H \"Content-Type: application/json\" \\"
    echo "  -d '{\"phone\": \"+201012345678\", \"referral_code\": \"$REFERRAL_CODE\"}'"
    echo ""
fi

# ============================================
# SUMMARY
# ============================================
echo -e "${GREEN}=========================================="
echo "Driver Referral Tests Completed!"
echo "==========================================${NC}"
echo ""
echo -e "${YELLOW}Next Steps:${NC}"
echo "1. Replace DRIVER_TOKEN with actual token from driver login"
echo "2. Test with real driver account"
echo "3. Verify referral code is returned correctly"
echo "4. Check referral earnings are displayed"
