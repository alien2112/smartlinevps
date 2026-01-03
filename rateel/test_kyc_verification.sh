#!/bin/bash

# KYC Verification System - Complete Test
# Creates a test driver, tests KYC verification endpoints, then cleans up

# Get domain from .env
if [ -f ".env" ]; then
    APP_URL=$(grep "^APP_URL=" .env | cut -d '=' -f2 | tr -d '"' | tr -d "'" | xargs)
    BASE_URL="${APP_URL}/api"
else
    BASE_URL="https://smartline-it.com/api"
fi

echo "=========================================="
echo "KYC Verification System - Complete Test"
echo "Domain: $BASE_URL"
echo "=========================================="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Generate unique test data
TEST_PHONE="+2010$(date +%s | tail -c 8)"
TEST_EMAIL="testdriver$(date +%s)@test.com"
TEST_DRIVER_ID=""
DRIVER_TOKEN=""
SESSION_ID=""

# ============================================
# STEP 1: Create Test Driver in Database
# ============================================
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Step 1: Creating Test Driver${NC}"
echo -e "${BLUE}========================================${NC}"

cd /var/www/laravel/smartlinevps/rateel

echo -e "${YELLOW}Creating driver with phone: $TEST_PHONE${NC}"
CREATE_RESULT=$(php create_test_driver.php "$TEST_PHONE" "$TEST_EMAIL" 2>&1)

if echo "$CREATE_RESULT" | grep -q '"success"'; then
    TEST_DRIVER_ID=$(echo "$CREATE_RESULT" | jq -r '.id' 2>/dev/null)
    echo -e "${GREEN}✓ Driver created successfully${NC}"
    echo -e "${GREEN}Driver ID: $TEST_DRIVER_ID${NC}"
    echo -e "${GREEN}Phone: $TEST_PHONE${NC}"
else
    echo -e "${RED}✗ Failed to create driver${NC}"
    echo "$CREATE_RESULT"
    exit 1
fi

echo ""

# ============================================
# STEP 2: Login as Test Driver
# ============================================
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Step 2: Logging in as Test Driver${NC}"
echo -e "${BLUE}========================================${NC}"

LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/driver/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{
    \"phone\": \"$TEST_PHONE\",
    \"password\": \"Test123456!\"
  }")

# Extract token
DRIVER_TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.token // .data.token // empty' 2>/dev/null)

if [ -z "$DRIVER_TOKEN" ] || [ "$DRIVER_TOKEN" = "null" ]; then
    echo -e "${RED}✗ Failed to get driver token${NC}"
    echo "$LOGIN_RESPONSE" | jq '.' 2>/dev/null || echo "$LOGIN_RESPONSE"
    echo "Cleaning up..."
    php delete_test_driver.php "$TEST_DRIVER_ID" 2>/dev/null
    exit 1
fi

echo -e "${GREEN}✓ Login successful${NC}"
echo -e "${GREEN}Token: ${DRIVER_TOKEN:0:20}...${NC}"
echo ""

# ============================================
# STEP 3: Test Account Verification Status
# ============================================
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Step 3: Testing Account Verification Status${NC}"
echo -e "${BLUE}========================================${NC}"

echo -e "${YELLOW}Test 1: GET /api/driver/auth/account/verification${NC}"
RESPONSE1=$(curl -s -w "\n%{http_code}" -X GET \
  -H "Authorization: Bearer $DRIVER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  "$BASE_URL/driver/auth/account/verification")

HTTP_CODE1=$(echo "$RESPONSE1" | tail -n1)
BODY1=$(echo "$RESPONSE1" | sed '$d')

echo -e "${YELLOW}HTTP Status: $HTTP_CODE1${NC}"
echo "$BODY1" | jq '.' 2>/dev/null || echo "$BODY1"

if [ "$HTTP_CODE1" -ge 200 ] && [ "$HTTP_CODE1" -lt 300 ]; then
    echo -e "${GREEN}✓ Test 1 passed${NC}"
    VERIFICATION_STATUS=$(echo "$BODY1" | jq -r '.content.verification.status // .data.verification.status // empty' 2>/dev/null)
    if [ -n "$VERIFICATION_STATUS" ]; then
        echo -e "${GREEN}Verification Status: $VERIFICATION_STATUS${NC}"
    fi
else
    echo -e "${RED}✗ Test 1 failed${NC}"
fi
echo ""

# ============================================
# STEP 4: Test KYC Verification Session
# ============================================
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Step 4: Testing KYC Verification Session${NC}"
echo -e "${BLUE}========================================${NC}"

# Test 2: Create/Get Verification Session
echo -e "${YELLOW}Test 2: POST /api/driver/verification/session${NC}"
RESPONSE2=$(curl -s -w "\n%{http_code}" -X POST \
  -H "Authorization: Bearer $DRIVER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  "$BASE_URL/driver/verification/session")

HTTP_CODE2=$(echo "$RESPONSE2" | tail -n1)
BODY2=$(echo "$RESPONSE2" | sed '$d')

echo -e "${YELLOW}HTTP Status: $HTTP_CODE2${NC}"
echo "$BODY2" | jq '.' 2>/dev/null || echo "$BODY2"

if [ "$HTTP_CODE2" -ge 200 ] && [ "$HTTP_CODE2" -lt 300 ]; then
    echo -e "${GREEN}✓ Test 2 passed${NC}"
    SESSION_ID=$(echo "$BODY2" | jq -r '.content.session_id // .data.session_id // empty' 2>/dev/null)
    if [ -n "$SESSION_ID" ] && [ "$SESSION_ID" != "null" ]; then
        echo -e "${GREEN}Session ID: $SESSION_ID${NC}"
    fi
else
    echo -e "${RED}✗ Test 2 failed${NC}"
fi
echo ""

# Test 3: Get Verification Status
echo -e "${YELLOW}Test 3: GET /api/driver/verification/status${NC}"
RESPONSE3=$(curl -s -w "\n%{http_code}" -X GET \
  -H "Authorization: Bearer $DRIVER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  "$BASE_URL/driver/verification/status")

HTTP_CODE3=$(echo "$RESPONSE3" | tail -n1)
BODY3=$(echo "$RESPONSE3" | sed '$d')

echo -e "${YELLOW}HTTP Status: $HTTP_CODE3${NC}"
echo "$BODY3" | jq '.' 2>/dev/null || echo "$BODY3"

if [ "$HTTP_CODE3" -ge 200 ] && [ "$HTTP_CODE3" -lt 300 ]; then
    echo -e "${GREEN}✓ Test 3 passed${NC}"
    KYC_STATUS=$(echo "$BODY3" | jq -r '.content.kyc_status // .data.kyc_status // empty' 2>/dev/null)
    if [ -n "$KYC_STATUS" ]; then
        echo -e "${GREEN}KYC Status: $KYC_STATUS${NC}"
    fi
else
    echo -e "${RED}✗ Test 3 failed${NC}"
fi
echo ""

# Test 4: Upload Media (if session exists)
if [ -n "$SESSION_ID" ] && [ "$SESSION_ID" != "null" ]; then
    echo -e "${YELLOW}Test 4: POST /api/driver/verification/session/{id}/upload${NC}"
    echo -e "${YELLOW}Note: This test requires actual file upload. Testing endpoint structure only.${NC}"
    
    # Test with invalid data to check endpoint structure
    RESPONSE4=$(curl -s -w "\n%{http_code}" -X POST \
      -H "Authorization: Bearer $DRIVER_TOKEN" \
      -H "Accept: application/json" \
      "$BASE_URL/driver/verification/session/$SESSION_ID/upload")
    
    HTTP_CODE4=$(echo "$RESPONSE4" | tail -n1)
    BODY4=$(echo "$RESPONSE4" | sed '$d')
    
    echo -e "${YELLOW}HTTP Status: $HTTP_CODE4${NC}"
    echo "$BODY4" | jq '.' 2>/dev/null || echo "$BODY4"
    
    # 400 is expected for missing file, which means endpoint exists
    if [ "$HTTP_CODE4" -eq 400 ] || [ "$HTTP_CODE4" -eq 200 ]; then
        echo -e "${GREEN}✓ Test 4 passed (endpoint exists)${NC}"
    else
        echo -e "${RED}✗ Test 4 failed${NC}"
    fi
    echo ""
fi

# ============================================
# STEP 5: Cleanup - Remove Test Driver
# ============================================
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Step 5: Cleaning Up Test Driver${NC}"
echo -e "${BLUE}========================================${NC}"

echo -e "${YELLOW}Deleting test driver: $TEST_DRIVER_ID${NC}"
CLEANUP_RESULT=$(php delete_test_driver.php "$TEST_DRIVER_ID" 2>&1)

if echo "$CLEANUP_RESULT" | grep -q '"success"'; then
    echo -e "${GREEN}✓ Test driver removed successfully${NC}"
else
    echo -e "${RED}✗ Failed to remove test driver${NC}"
    echo "$CLEANUP_RESULT"
    echo -e "${YELLOW}Please manually delete driver with ID: $TEST_DRIVER_ID${NC}"
fi

echo ""

# ============================================
# SUMMARY
# ============================================
echo -e "${GREEN}=========================================="
echo "Test Summary"
echo "==========================================${NC}"
echo -e "${YELLOW}Test Driver Created:${NC} $TEST_PHONE"
echo -e "${YELLOW}Driver ID:${NC} $TEST_DRIVER_ID"
echo -e "${YELLOW}Tests Completed:${NC}"
echo "  1. Account Verification Status - $([ "$HTTP_CODE1" -ge 200 ] && [ "$HTTP_CODE1" -lt 300 ] && echo "✓ PASS" || echo "✗ FAIL")"
echo "  2. Create KYC Session - $([ "$HTTP_CODE2" -ge 200 ] && [ "$HTTP_CODE2" -lt 300 ] && echo "✓ PASS" || echo "✗ FAIL")"
echo "  3. Get KYC Status - $([ "$HTTP_CODE3" -ge 200 ] && [ "$HTTP_CODE3" -lt 300 ] && echo "✓ PASS" || echo "✗ FAIL")"
if [ -n "$SESSION_ID" ] && [ "$SESSION_ID" != "null" ]; then
    echo "  4. Upload Media Endpoint - $([ "$HTTP_CODE4" -eq 400 ] || [ "$HTTP_CODE4" -eq 200 ] && echo "✓ PASS" || echo "✗ FAIL")"
fi
echo -e "${GREEN}Cleanup:${NC} $([ -n "$CLEANUP_RESULT" ] && echo "$CLEANUP_RESULT" | grep -q '"success"' && echo "✓ Driver removed" || echo "✗ Cleanup failed")"
echo ""
