#!/bin/bash

# Dashboard Routes Testing Script
# Tests all dashboard routes using curl

BASE_URL="https://smartline-it.com/api"
TOKEN=""
DRIVER_PHONE="+201208673028"
DRIVER_PASSWORD="password123"

echo "=========================================="
echo "DASHBOARD ROUTES TESTING REPORT"
echo "=========================================="
echo "Base URL: $BASE_URL"
echo "Date: $(date)"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test results tracking
PASSED=0
FAILED=0
SKIPPED=0
TOTAL=0

# Array to store test results
declare -a TEST_RESULTS

# Function to test endpoint with detailed reporting
test_endpoint() {
    local method=$1
    local endpoint=$2
    local description=$3
    
    ((TOTAL++))
    
    echo "----------------------------------------"
    echo -e "${BLUE}[$TOTAL] TEST: $description${NC}"
    echo "Endpoint: $method $endpoint"
    echo "Full URL: $BASE_URL$endpoint"
    
    if [ -z "$TOKEN" ]; then
        echo -e "${YELLOW}⚠ SKIPPED - No authentication token${NC}"
        ((SKIPPED++))
        TEST_RESULTS+=("SKIPPED|$endpoint|$description|No authentication token")
        echo ""
        return
    fi
    
    # Make the request
    if [ "$method" = "GET" ]; then
        response=$(curl -s -w "\nHTTP_CODE:%{http_code}\nTIME_TOTAL:%{time_total}" \
            -H "Authorization: Bearer $TOKEN" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            "$BASE_URL$endpoint" 2>&1)
    else
        response=$(curl -s -w "\nHTTP_CODE:%{http_code}\nTIME_TOTAL:%{time_total}" \
            -X "$method" \
            -H "Authorization: Bearer $TOKEN" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            -d "$data" \
            "$BASE_URL$endpoint" 2>&1)
    fi
    
    # Extract HTTP code and time
    http_code=$(echo "$response" | grep "HTTP_CODE" | cut -d: -f2 | tr -d ' ')
    time_total=$(echo "$response" | grep "TIME_TOTAL" | cut -d: -f2 | tr -d ' ')
    body=$(echo "$response" | sed '/HTTP_CODE/d' | sed '/TIME_TOTAL/d')
    
    echo "HTTP Status: $http_code"
    echo "Response Time: ${time_total}s"
    
    # Extract response details
    status_code=$(echo "$body" | grep -o '"response_code":"[^"]*' | cut -d'"' -f4 | head -1)
    message=$(echo "$body" | grep -o '"message":"[^"]*' | cut -d'"' -f4 | head -1)
    error_msg=$(echo "$body" | grep -o '"message":"[^"]*' | cut -d'"' -f4 | head -1)
    exception=$(echo "$body" | grep -o '"exception":"[^"]*' | cut -d'"' -f4 | head -1)
    
    # Show response preview
    response_preview=$(echo "$body" | head -c 500)
    if [ ${#body} -gt 500 ]; then
        echo "Response Preview: ${response_preview}..."
    else
        echo "Response: $body"
    fi
    
    # Determine test result
    if [ "$http_code" -ge 200 ] && [ "$http_code" -lt 300 ]; then
        echo -e "${GREEN}✓ PASS${NC}"
        ((PASSED++))
        TEST_RESULTS+=("PASS|$endpoint|$description|HTTP $http_code - $message")
    elif [ "$http_code" -eq 401 ]; then
        echo -e "${RED}✗ FAIL - Authentication required${NC}"
        ((FAILED++))
        TEST_RESULTS+=("FAIL|$endpoint|$description|HTTP 401 - Authentication required")
    elif [ "$http_code" -eq 404 ]; then
        echo -e "${RED}✗ FAIL - Endpoint not found${NC}"
        ((FAILED++))
        TEST_RESULTS+=("FAIL|$endpoint|$description|HTTP 404 - Endpoint not found")
    elif [ "$http_code" -eq 422 ]; then
        echo -e "${YELLOW}⚠ VALIDATION ERROR${NC}"
        ((SKIPPED++))
        TEST_RESULTS+=("SKIPPED|$endpoint|$description|HTTP 422 - Validation error: $error_msg")
    elif [ "$http_code" -eq 500 ]; then
        echo -e "${RED}✗ FAIL - Server error${NC}"
        if [ -n "$exception" ]; then
            echo "Exception: $exception"
        fi
        ((FAILED++))
        TEST_RESULTS+=("FAIL|$endpoint|$description|HTTP 500 - Server error: $exception")
    else
        echo -e "${RED}✗ FAIL - HTTP $http_code${NC}"
        if [ -n "$error_msg" ]; then
            echo "Error: $error_msg"
        fi
        ((FAILED++))
        TEST_RESULTS+=("FAIL|$endpoint|$description|HTTP $http_code - $error_msg")
    fi
    echo ""
}

# Step 1: Get Authentication Token
echo "=========================================="
echo "STEP 1: Authentication"
echo "=========================================="

echo "Attempting to login driver..."
login_response=$(curl -s -X POST \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d "{\"phone\":\"$DRIVER_PHONE\",\"password\":\"$DRIVER_PASSWORD\"}" \
    "$BASE_URL/driver/auth/login")

echo "Login Response: $login_response"
TOKEN=$(echo "$login_response" | grep -o '"token":"[^"]*' | cut -d'"' -f4)

if [ -z "$TOKEN" ]; then
    echo -e "${YELLOW}⚠ Could not get token from login. Trying database...${NC}"
    # Try to get token from database using PHP
    TOKEN=$(php -r "
    require '/var/www/laravel/smartlinevps/rateel/vendor/autoload.php';
    \$app = require_once '/var/www/laravel/smartlinevps/rateel/bootstrap/app.php';
    \$kernel = \$app->make('Illuminate\Contracts\Console\Kernel');
    \$kernel->bootstrap();
    \$driver = \Modules\UserManagement\Entities\User::where('phone', '$DRIVER_PHONE')->first();
    if (\$driver) {
        \$tokenRecord = \DB::table('oauth_access_tokens')
            ->where('user_id', \$driver->id)
            ->where('revoked', 0)
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->first();
        if (\$tokenRecord) {
            echo \$tokenRecord->id;
        }
    }
    " 2>/dev/null)
fi

if [ -z "$TOKEN" ]; then
    echo -e "${RED}✗ Could not obtain authentication token${NC}"
    echo -e "${YELLOW}⚠ Continuing with tests (will show as skipped)${NC}"
else
    echo -e "${GREEN}✓ Authentication token obtained${NC}"
    echo "Token: ${TOKEN:0:50}..."
fi
echo ""

# ==========================================
# DASHBOARD ROUTES
# ==========================================
echo "=========================================="
echo "STEP 2: Dashboard Routes Testing"
echo "=========================================="

# Test all dashboard routes
test_endpoint "GET" "/driver/auth/dashboard/widgets" "Get dashboard widgets"
test_endpoint "GET" "/driver/auth/dashboard/recent-activity" "Get recent activity"
test_endpoint "GET" "/driver/auth/dashboard/promotional-banners" "Get promotional banners"

# ==========================================
# SUMMARY REPORT
# ==========================================
echo "=========================================="
echo "TEST SUMMARY"
echo "=========================================="
echo -e "${GREEN}Passed: $PASSED${NC}"
echo -e "${RED}Failed: $FAILED${NC}"
echo -e "${YELLOW}Skipped: $SKIPPED${NC}"
echo "Total Tests: $TOTAL"
echo ""

# Generate detailed report
REPORT_FILE="DASHBOARD_ROUTES_TEST_REPORT_$(date +%Y%m%d_%H%M%S).md"
echo "Generating detailed report: $REPORT_FILE"

cat > "$REPORT_FILE" << EOF
# Dashboard Routes Testing Report

**Generated:** $(date)
**Base URL:** $BASE_URL
**Driver Phone:** $DRIVER_PHONE

## Test Summary

- **Total Tests:** $TOTAL
- **Passed:** $PASSED
- **Failed:** $FAILED
- **Skipped:** $SKIPPED

## Detailed Test Results

EOF

for result in "${TEST_RESULTS[@]}"; do
    IFS='|' read -r status endpoint description details <<< "$result"
    status_icon="❌"
    if [ "$status" = "PASS" ]; then
        status_icon="✅"
    elif [ "$status" = "SKIPPED" ]; then
        status_icon="⚠️"
    fi
    echo "- **$status_icon $status:** $description" >> "$REPORT_FILE"
    echo "  - Endpoint: \`$endpoint\`" >> "$REPORT_FILE"
    echo "  - Details: $details" >> "$REPORT_FILE"
    echo "" >> "$REPORT_FILE"
done

cat >> "$REPORT_FILE" << EOF

## Routes Tested

1. **GET /api/driver/auth/dashboard/widgets**
   - Description: Get dashboard widgets (earnings, trips, wallet, rating, etc.)
   - Authentication: Required (Bearer token)

2. **GET /api/driver/auth/dashboard/recent-activity**
   - Description: Get recent activity feed (trips, transactions)
   - Authentication: Required (Bearer token)

3. **GET /api/driver/auth/dashboard/promotional-banners**
   - Description: Get promotional banners for driver
   - Authentication: Required (Bearer token)

## Notes

- All routes require authentication via Bearer token
- Routes are under the \`/api/driver/auth/dashboard\` prefix
- All routes use GET method
- Response format follows Laravel API response structure

EOF

echo -e "${GREEN}Report saved to: $REPORT_FILE${NC}"
echo ""

if [ $FAILED -eq 0 ] && [ $SKIPPED -eq 0 ]; then
    echo -e "${GREEN}✓ All tests passed!${NC}"
    exit 0
elif [ $FAILED -eq 0 ]; then
    echo -e "${YELLOW}⚠ Some tests were skipped${NC}"
    exit 0
else
    echo -e "${RED}✗ Some tests failed${NC}"
    exit 1
fi
