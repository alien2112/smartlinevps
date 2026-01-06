#!/bin/bash

# KYC Liveness Verification Test with Actual Driver Images
# Tests the complete KYC flow using real driver images from storage

# Get domain from .env
if [ -f ".env" ]; then
    APP_URL=$(grep "^APP_URL=" .env | cut -d '=' -f2 | tr -d '"' | tr -d "'" | xargs)
    BASE_URL="${APP_URL}/api"
else
    BASE_URL="https://smartline-it.com/api"
fi

echo "=========================================="
echo "KYC Liveness Test with Actual Images"
echo "Domain: $BASE_URL"
echo "=========================================="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Test data paths
STORAGE_PATH="/var/www/laravel/smartlinevps/rateel/storage/app"
TEST_SELFIE_PATH="$STORAGE_PATH/test-kyc/test_selfie.jpg"
TEST_ID_PATH="$STORAGE_PATH/test-kyc/test_id.jpg"

# Check if test images exist
if [ ! -f "$TEST_SELFIE_PATH" ]; then
    echo -e "${RED}✗ Test selfie image not found: $TEST_SELFIE_PATH${NC}"
    echo -e "${YELLOW}Looking for alternative images...${NC}"
    
    # Try to find real driver verification images
    ALT_SELFIE=$(find "$STORAGE_PATH/verification" -name "*.jpg" -path "*/selfie/*" | head -1)
    ALT_ID=$(find "$STORAGE_PATH/verification" -name "*.jpg" -path "*/id_front/*" | head -1)
    
    if [ -n "$ALT_SELFIE" ] && [ -n "$ALT_ID" ]; then
        TEST_SELFIE_PATH="$ALT_SELFIE"
        TEST_ID_PATH="$ALT_ID"
        echo -e "${GREEN}✓ Found alternative images:${NC}"
        echo -e "  Selfie: $TEST_SELFIE_PATH"
        echo -e "  ID: $TEST_ID_PATH"
    else
        echo -e "${RED}✗ No images found. Please add test images to $STORAGE_PATH/test-kyc/${NC}"
        exit 1
    fi
fi

if [ ! -f "$TEST_ID_PATH" ]; then
    echo -e "${RED}✗ Test ID image not found: $TEST_ID_PATH${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Test images ready:${NC}"
echo -e "  Selfie: $TEST_SELFIE_PATH ($(du -h "$TEST_SELFIE_PATH" | cut -f1))"
echo -e "  ID: $TEST_ID_PATH ($(du -h "$TEST_ID_PATH" | cut -f1))"
echo ""

# Generate unique test data
TEST_PHONE="+2010$(date +%s | tail -c 8)"
TEST_EMAIL="testdriver$(date +%s)@test.com"
TEST_DRIVER_ID=""
DRIVER_TOKEN=""
SESSION_ID=""

# ============================================
# STEP 1: Create Test Driver
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

DRIVER_TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.token // .data.token // empty' 2>/dev/null)

if [ -z "$DRIVER_TOKEN" ] || [ "$DRIVER_TOKEN" = "null" ]; then
    echo -e "${RED}✗ Failed to get driver token${NC}"
    echo "$LOGIN_RESPONSE" | jq '.' 2>/dev/null || echo "$LOGIN_RESPONSE"
    php delete_test_driver.php "$TEST_DRIVER_ID" 2>/dev/null
    exit 1
fi

echo -e "${GREEN}✓ Login successful${NC}"
echo -e "${GREEN}Token: ${DRIVER_TOKEN:0:30}...${NC}"
echo ""

# ============================================
# STEP 3: Create KYC Verification Session
# ============================================
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Step 3: Creating KYC Verification Session${NC}"
echo -e "${BLUE}========================================${NC}"

SESSION_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST \
  -H "Authorization: Bearer $DRIVER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  "$BASE_URL/driver/verification/session")

HTTP_CODE=$(echo "$SESSION_RESPONSE" | tail -n1)
BODY=$(echo "$SESSION_RESPONSE" | sed '$d')

echo -e "${YELLOW}HTTP Status: $HTTP_CODE${NC}"
echo "$BODY" | jq '.' 2>/dev/null || echo "$BODY"

if [ "$HTTP_CODE" -ge 200 ] && [ "$HTTP_CODE" -lt 300 ]; then
    echo -e "${GREEN}✓ Session created successfully${NC}"
    SESSION_ID=$(echo "$BODY" | jq -r '.content.session_id // .data.session_id // empty' 2>/dev/null)
    echo -e "${GREEN}Session ID: $SESSION_ID${NC}"
else
    echo -e "${RED}✗ Failed to create session${NC}"
    php delete_test_driver.php "$TEST_DRIVER_ID" 2>/dev/null
    exit 1
fi

echo ""

# ============================================
# STEP 4: Upload Selfie Image
# ============================================
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Step 4: Uploading Selfie Image${NC}"
echo -e "${BLUE}========================================${NC}"

echo -e "${YELLOW}Uploading selfie from: $TEST_SELFIE_PATH${NC}"

UPLOAD_SELFIE_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST \
  -H "Authorization: Bearer $DRIVER_TOKEN" \
  -H "Accept: application/json" \
  -F "kind=selfie" \
  -F "file=@$TEST_SELFIE_PATH" \
  "$BASE_URL/driver/verification/session/$SESSION_ID/upload")

HTTP_CODE=$(echo "$UPLOAD_SELFIE_RESPONSE" | tail -n1)
BODY=$(echo "$UPLOAD_SELFIE_RESPONSE" | sed '$d')

echo -e "${YELLOW}HTTP Status: $HTTP_CODE${NC}"
echo "$BODY" | jq '.' 2>/dev/null || echo "$BODY"

if [ "$HTTP_CODE" -ge 200 ] && [ "$HTTP_CODE" -lt 300 ]; then
    echo -e "${GREEN}✓ Selfie uploaded successfully${NC}"
    MEDIA_ID=$(echo "$BODY" | jq -r '.content.media_id // .data.media_id // empty' 2>/dev/null)
    echo -e "${GREEN}Media ID: $MEDIA_ID${NC}"
else
    echo -e "${RED}✗ Failed to upload selfie${NC}"
fi

echo ""

# ============================================
# STEP 5: Upload ID Front Image
# ============================================
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Step 5: Uploading ID Front Image${NC}"
echo -e "${BLUE}========================================${NC}"

echo -e "${YELLOW}Uploading ID from: $TEST_ID_PATH${NC}"

UPLOAD_ID_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST \
  -H "Authorization: Bearer $DRIVER_TOKEN" \
  -H "Accept: application/json" \
  -F "kind=id_front" \
  -F "file=@$TEST_ID_PATH" \
  "$BASE_URL/driver/verification/session/$SESSION_ID/upload")

HTTP_CODE=$(echo "$UPLOAD_ID_RESPONSE" | tail -n1)
BODY=$(echo "$UPLOAD_ID_RESPONSE" | sed '$d')

echo -e "${YELLOW}HTTP Status: $HTTP_CODE${NC}"
echo "$BODY" | jq '.' 2>/dev/null || echo "$BODY"

if [ "$HTTP_CODE" -ge 200 ] && [ "$HTTP_CODE" -lt 300 ]; then
    echo -e "${GREEN}✓ ID front uploaded successfully${NC}"
    MEDIA_ID=$(echo "$BODY" | jq -r '.content.media_id // .data.media_id // empty' 2>/dev/null)
    echo -e "${GREEN}Media ID: $MEDIA_ID${NC}"
else
    echo -e "${RED}✗ Failed to upload ID front${NC}"
fi

echo ""

# ============================================
# STEP 6: Check Status Before Submission
# ============================================
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Step 6: Checking Status Before Submission${NC}"
echo -e "${BLUE}========================================${NC}"

STATUS_RESPONSE=$(curl -s -w "\n%{http_code}" -X GET \
  -H "Authorization: Bearer $DRIVER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  "$BASE_URL/driver/verification/status")

HTTP_CODE=$(echo "$STATUS_RESPONSE" | tail -n1)
BODY=$(echo "$STATUS_RESPONSE" | sed '$d')

echo -e "${YELLOW}HTTP Status: $HTTP_CODE${NC}"
echo "$BODY" | jq '.' 2>/dev/null || echo "$BODY"
echo ""

# ============================================
# STEP 7: Submit Session for Verification
# ============================================
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Step 7: Submitting Session for Verification${NC}"
echo -e "${BLUE}========================================${NC}"

echo -e "${YELLOW}Submitting session: $SESSION_ID${NC}"

SUBMIT_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST \
  -H "Authorization: Bearer $DRIVER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  "$BASE_URL/driver/verification/session/$SESSION_ID/submit")

HTTP_CODE=$(echo "$SUBMIT_RESPONSE" | tail -n1)
BODY=$(echo "$SUBMIT_RESPONSE" | sed '$d')

echo -e "${YELLOW}HTTP Status: $HTTP_CODE${NC}"
echo "$BODY" | jq '.' 2>/dev/null || echo "$BODY"

if [ "$HTTP_CODE" -ge 200 ] && [ "$HTTP_CODE" -lt 300 ]; then
    echo -e "${GREEN}✓ Session submitted successfully${NC}"
    echo -e "${GREEN}KYC verification is now processing...${NC}"
else
    echo -e "${RED}✗ Failed to submit session${NC}"
fi

echo ""

# ============================================
# STEP 8: Poll for Verification Results
# ============================================
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Step 8: Polling for Verification Results${NC}"
echo -e "${BLUE}========================================${NC}"

echo -e "${YELLOW}Waiting for KYC service to process...${NC}"
echo -e "${YELLOW}This may take 30-60 seconds...${NC}"
echo ""

MAX_ATTEMPTS=20
ATTEMPT=1
VERIFICATION_COMPLETE=false

while [ $ATTEMPT -le $MAX_ATTEMPTS ]; do
    echo -e "${YELLOW}Attempt $ATTEMPT/$MAX_ATTEMPTS${NC}"
    
    STATUS_RESPONSE=$(curl -s -X GET \
      -H "Authorization: Bearer $DRIVER_TOKEN" \
      -H "Content-Type: application/json" \
      -H "Accept: application/json" \
      "$BASE_URL/driver/verification/status")
    
    VERIFICATION_STATUS=$(echo "$STATUS_RESPONSE" | jq -r '.content.status // .data.status // empty' 2>/dev/null)
    DECISION=$(echo "$STATUS_RESPONSE" | jq -r '.content.decision // .data.decision // empty' 2>/dev/null)
    
    echo -e "  Status: ${YELLOW}$VERIFICATION_STATUS${NC}"
    
    if [ "$VERIFICATION_STATUS" = "verified" ] || [ "$VERIFICATION_STATUS" = "rejected" ] || [ "$VERIFICATION_STATUS" = "manual_review" ]; then
        VERIFICATION_COMPLETE=true
        echo ""
        echo -e "${GREEN}✓ Verification processing complete!${NC}"
        echo ""
        echo -e "${BLUE}=== VERIFICATION RESULTS ===${NC}"
        echo "$STATUS_RESPONSE" | jq '.content // .data' 2>/dev/null || echo "$STATUS_RESPONSE"
        echo ""
        
        # Display scores if available
        LIVENESS_SCORE=$(echo "$STATUS_RESPONSE" | jq -r '.content.scores.liveness // .data.scores.liveness // "N/A"' 2>/dev/null)
        FACE_MATCH_SCORE=$(echo "$STATUS_RESPONSE" | jq -r '.content.scores.face_match // .data.scores.face_match // "N/A"' 2>/dev/null)
        DOC_AUTH_SCORE=$(echo "$STATUS_RESPONSE" | jq -r '.content.scores.doc_auth // .data.scores.doc_auth // "N/A"' 2>/dev/null)
        
        if [ "$LIVENESS_SCORE" != "N/A" ]; then
            echo -e "${GREEN}=== VERIFICATION SCORES ===${NC}"
            echo -e "  Liveness Score: ${YELLOW}$LIVENESS_SCORE${NC}"
            echo -e "  Face Match Score: ${YELLOW}$FACE_MATCH_SCORE${NC}"
            echo -e "  Document Auth Score: ${YELLOW}$DOC_AUTH_SCORE${NC}"
            echo ""
        fi
        
        # Display decision
        if [ "$DECISION" = "approved" ]; then
            echo -e "${GREEN}✓ DECISION: APPROVED${NC}"
        elif [ "$DECISION" = "rejected" ]; then
            echo -e "${RED}✗ DECISION: REJECTED${NC}"
            
            # Show rejection reasons
            REASON_CODES=$(echo "$STATUS_RESPONSE" | jq -r '.content.reason_codes // .data.reason_codes // empty' 2>/dev/null)
            if [ -n "$REASON_CODES" ] && [ "$REASON_CODES" != "null" ]; then
                echo -e "${YELLOW}Rejection Reasons:${NC}"
                echo "$REASON_CODES" | jq -r '.[] | "  - [\(.code)] \(.message)"' 2>/dev/null
            fi
        else
            echo -e "${YELLOW}⚠ DECISION: $DECISION${NC}"
        fi
        
        break
    fi
    
    if [ $ATTEMPT -eq $MAX_ATTEMPTS ]; then
        echo -e "${YELLOW}⚠ Verification still processing after $MAX_ATTEMPTS attempts${NC}"
        echo -e "${YELLOW}Current status: $VERIFICATION_STATUS${NC}"
        echo -e "${YELLOW}You can check status later using: curl -H \"Authorization: Bearer \$TOKEN\" $BASE_URL/driver/verification/status${NC}"
    fi
    
    sleep 3
    ATTEMPT=$((ATTEMPT + 1))
done

echo ""

# ============================================
# STEP 9: Cleanup - Remove Test Driver
# ============================================
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Step 9: Cleanup${NC}"
echo -e "${BLUE}========================================${NC}"

read -p "Do you want to delete the test driver? (y/N) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}Deleting test driver: $TEST_DRIVER_ID${NC}"
    CLEANUP_RESULT=$(php delete_test_driver.php "$TEST_DRIVER_ID" 2>&1)
    
    if echo "$CLEANUP_RESULT" | grep -q '"success"'; then
        echo -e "${GREEN}✓ Test driver removed successfully${NC}"
    else
        echo -e "${RED}✗ Failed to remove test driver${NC}"
        echo "$CLEANUP_RESULT"
    fi
else
    echo -e "${YELLOW}⚠ Test driver kept: ID=$TEST_DRIVER_ID, Phone=$TEST_PHONE${NC}"
    echo -e "${YELLOW}You can login with: Phone=$TEST_PHONE, Password=Test123456!${NC}"
fi

echo ""

# ============================================
# SUMMARY
# ============================================
echo -e "${GREEN}=========================================="
echo "Test Completion Summary"
echo "==========================================${NC}"
echo -e "${YELLOW}Driver Details:${NC}"
echo "  Phone: $TEST_PHONE"
echo "  ID: $TEST_DRIVER_ID"
echo "  Session ID: $SESSION_ID"
echo ""
echo -e "${YELLOW}Images Used:${NC}"
echo "  Selfie: $(basename "$TEST_SELFIE_PATH")"
echo "  ID Front: $(basename "$TEST_ID_PATH")"
echo ""
echo -e "${YELLOW}Final Status:${NC}"
if [ "$VERIFICATION_COMPLETE" = true ]; then
    echo "  Verification: ${GREEN}Complete${NC}"
    echo "  Decision: ${YELLOW}$DECISION${NC}"
else
    echo "  Verification: ${YELLOW}Still Processing${NC}"
fi
echo ""
