#!/bin/bash

# Quick Referral System cURL Tests
# Uses domain from .env or defaults to smartline-it.com

# Get domain from .env
if [ -f ".env" ]; then
    APP_URL=$(grep "^APP_URL=" .env | cut -d '=' -f2 | tr -d '"' | tr -d "'" | xargs)
    BASE_URL="${APP_URL}/api"
else
    BASE_URL="https://smartline-it.com/api"
fi

echo "=========================================="
echo "Referral System cURL Tests"
echo "Domain: $BASE_URL"
echo "=========================================="
echo ""

# Replace these with actual tokens
CUSTOMER_TOKEN="YOUR_CUSTOMER_TOKEN_HERE"
DRIVER_TOKEN="YOUR_DRIVER_TOKEN_HERE"

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${YELLOW}1. Validate Referral Code (Public - No Auth)${NC}"
curl -X GET "$BASE_URL/customer/referral/validate-code?code=TEST123" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' || echo "Response received"
echo ""

echo -e "${YELLOW}2. Get My Referral Code (Customer)${NC}"
curl -X GET "$BASE_URL/customer/referral/my-code" \
  -H "Authorization: Bearer $CUSTOMER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' || echo "Response received"
echo ""

echo -e "${YELLOW}3. Generate Invite (Customer)${NC}"
curl -X POST "$BASE_URL/customer/referral/generate-invite" \
  -H "Authorization: Bearer $CUSTOMER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"channel":"whatsapp","platform":"android"}' \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' || echo "Response received"
echo ""

echo -e "${YELLOW}4. Get Referral Stats (Customer)${NC}"
curl -X GET "$BASE_URL/customer/referral/stats" \
  -H "Authorization: Bearer $CUSTOMER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' || echo "Response received"
echo ""

echo -e "${YELLOW}5. Get Referral History (Customer)${NC}"
curl -X GET "$BASE_URL/customer/referral/history?limit=10&offset=1" \
  -H "Authorization: Bearer $CUSTOMER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' || echo "Response received"
echo ""

echo -e "${YELLOW}6. Get Rewards History (Customer)${NC}"
curl -X GET "$BASE_URL/customer/referral/rewards?limit=10&offset=1" \
  -H "Authorization: Bearer $CUSTOMER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' || echo "Response received"
echo ""

echo -e "${YELLOW}7. Get Leaderboard (Customer)${NC}"
curl -X GET "$BASE_URL/customer/referral/leaderboard?period=month&limit=10" \
  -H "Authorization: Bearer $CUSTOMER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' || echo "Response received"
echo ""

echo -e "${YELLOW}8. Get Driver Referral Details${NC}"
curl -X GET "$BASE_URL/driver/referral-details" \
  -H "Authorization: Bearer $DRIVER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' || echo "Response received"
echo ""

echo -e "${GREEN}=========================================="
echo "All tests completed!"
echo "==========================================${NC}"
