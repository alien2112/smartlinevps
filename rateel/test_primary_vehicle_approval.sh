#!/bin/bash

# Test Primary Vehicle Change Approval System
# This script tests the complete flow of requesting and approving primary vehicle changes

echo "=========================================="
echo "PRIMARY VEHICLE CHANGE APPROVAL TEST"
echo "=========================================="
echo ""

# Configuration
BASE_URL="https://smartline-it.com"
DRIVER_TOKEN=""  # Set your driver access token here
DRIVER_ID=""     # Set your driver ID here
VEHICLE_ID=""    # Set the vehicle ID to make primary here

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Test 1: List current vehicles
echo -e "${YELLOW}Test 1: List current vehicles${NC}"
echo "GET $BASE_URL/api/driver/vehicle/list"
curl -s -X GET "$BASE_URL/api/driver/vehicle/list" \
  -H "Authorization: Bearer $DRIVER_TOKEN" \
  -H "Accept: application/json" | jq '.'
echo ""

# Test 2: Request to set vehicle as primary
echo -e "${YELLOW}Test 2: Request primary vehicle change${NC}"
echo "POST $BASE_URL/api/driver/vehicle/set-primary/$VEHICLE_ID"
curl -s -X POST "$BASE_URL/api/driver/vehicle/set-primary/$VEHICLE_ID" \
  -H "Authorization: Bearer $DRIVER_TOKEN" \
  -H "Accept: application/json" | jq '.'
echo ""

# Test 3: Verify pending status
echo -e "${YELLOW}Test 3: Verify pending status${NC}"
echo "GET $BASE_URL/api/driver/vehicle/list"
curl -s -X GET "$BASE_URL/api/driver/vehicle/list" \
  -H "Authorization: Bearer $DRIVER_TOKEN" \
  -H "Accept: application/json" | jq '.data.vehicles[] | {id, brand, model, is_primary, has_pending_primary_request}'
echo ""

echo -e "${GREEN}=========================================="
echo "Tests completed!"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Go to: $BASE_URL/admin/driver/approvals/$DRIVER_ID"
echo "2. Look for the vehicle with pending primary request (yellow warning)"
echo "3. Click 'Approve Primary Change' or 'Reject Primary Change'"
echo "4. Run this script again to verify the change"
echo ""
