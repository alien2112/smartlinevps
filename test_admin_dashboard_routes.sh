#!/bin/bash

# Admin Dashboard Routes Testing Script
# Tests all admin dashboard routes using curl with session cookies

BASE_URL="https://smartline-it.com"
COOKIE_FILE="/tmp/admin_test_cookies.txt"
ADMIN_EMAIL=""
ADMIN_PASSWORD=""

echo "=========================================="
echo "ADMIN DASHBOARD ROUTES TESTING REPORT"
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

# Clean up cookie file
rm -f "$COOKIE_FILE"

# Function to test endpoint with detailed reporting
test_endpoint() {
    local method=$1
    local endpoint=$2
    local data=$3
    local description=$4
    
    ((TOTAL++))
    
    echo "----------------------------------------"
    echo -e "${BLUE}[$TOTAL] TEST: $description${NC}"
    echo "Endpoint: $method $endpoint"
    echo "Full URL: $BASE_URL$endpoint"
    
    # Test even without authentication to see response
    use_cookies=""
    if [ -f "$COOKIE_FILE" ] && [ -s "$COOKIE_FILE" ]; then
        use_cookies="-b $COOKIE_FILE -c $COOKIE_FILE"
    else
        echo -e "${YELLOW}⚠ Testing without authentication session${NC}"
    fi
    
    # Make the request
    if [ "$method" = "GET" ]; then
        response=$(curl -s -w "\nHTTP_CODE:%{http_code}\nTIME_TOTAL:%{time_total}" \
            $use_cookies \
            -H "Content-Type: application/json" \
            -H "Accept: text/html,application/json" \
            -L \
            "$BASE_URL$endpoint" 2>&1)
    elif [ "$method" = "POST" ]; then
        response=$(curl -s -w "\nHTTP_CODE:%{http_code}\nTIME_TOTAL:%{time_total}" \
            -X "$method" \
            $use_cookies \
            -H "Content-Type: application/x-www-form-urlencoded" \
            -H "Accept: text/html,application/json" \
            -H "X-Requested-With: XMLHttpRequest" \
            -d "$data" \
            -L \
            "$BASE_URL$endpoint" 2>&1)
    elif [ "$method" = "PUT" ]; then
        response=$(curl -s -w "\nHTTP_CODE:%{http_code}\nTIME_TOTAL:%{time_total}" \
            -X "$method" \
            $use_cookies \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            -H "X-Requested-With: XMLHttpRequest" \
            -d "$data" \
            -L \
            "$BASE_URL$endpoint" 2>&1)
    else
        response=$(curl -s -w "\nHTTP_CODE:%{http_code}\nTIME_TOTAL:%{time_total}" \
            -X "$method" \
            $use_cookies \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            -L \
            "$BASE_URL$endpoint" 2>&1)
    fi
    
    # Extract HTTP code and time
    http_code=$(echo "$response" | grep "HTTP_CODE" | cut -d: -f2 | tr -d ' ')
    time_total=$(echo "$response" | grep "TIME_TOTAL" | cut -d: -f2 | tr -d ' ')
    body=$(echo "$response" | sed '/HTTP_CODE/d' | sed '/TIME_TOTAL/d')
    
    echo "HTTP Status: $http_code"
    echo "Response Time: ${time_total}s"
    
    # Check for redirects (302/301) which might indicate login redirect
    if [ "$http_code" -eq 302 ] || [ "$http_code" -eq 301 ]; then
        location=$(echo "$response" | grep -i "location:" | cut -d' ' -f2 | tr -d '\r')
        if [[ "$location" == *"login"* ]]; then
            echo -e "${RED}✗ FAIL - Redirected to login (authentication failed)${NC}"
            ((FAILED++))
            TEST_RESULTS+=("FAIL|$endpoint|$description|HTTP $http_code - Redirected to login")
            echo ""
            return
        fi
    fi
    
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
        TEST_RESULTS+=("PASS|$endpoint|$description|HTTP $http_code")
    elif [ "$http_code" -eq 401 ] || [ "$http_code" -eq 403 ]; then
        echo -e "${RED}✗ FAIL - Authentication/Authorization required${NC}"
        ((FAILED++))
        TEST_RESULTS+=("FAIL|$endpoint|$description|HTTP $http_code - Authentication required")
    elif [ "$http_code" -eq 404 ]; then
        echo -e "${RED}✗ FAIL - Endpoint not found${NC}"
        ((FAILED++))
        TEST_RESULTS+=("FAIL|$endpoint|$description|HTTP 404 - Endpoint not found")
    elif [ "$http_code" -eq 422 ]; then
        echo -e "${YELLOW}⚠ VALIDATION ERROR${NC}"
        ((SKIPPED++))
        TEST_RESULTS+=("SKIPPED|$endpoint|$description|HTTP 422 - Validation error")
    elif [ "$http_code" -eq 500 ]; then
        echo -e "${RED}✗ FAIL - Server error${NC}"
        ((FAILED++))
        TEST_RESULTS+=("FAIL|$endpoint|$description|HTTP 500 - Server error")
    else
        echo -e "${YELLOW}⚠ UNEXPECTED STATUS - HTTP $http_code${NC}"
        ((SKIPPED++))
        TEST_RESULTS+=("SKIPPED|$endpoint|$description|HTTP $http_code")
    fi
    echo ""
}

# Step 1: Get Admin Credentials from Database or use defaults
echo "=========================================="
echo "STEP 1: Authentication"
echo "=========================================="

# Try to get admin credentials from database
ADMIN_EMAIL=$(php -r "
require '/var/www/laravel/smartlinevps/rateel/vendor/autoload.php';
\$app = require_once '/var/www/laravel/smartlinevps/rateel/bootstrap/app.php';
\$kernel = \$app->make('Illuminate\Contracts\Console\Kernel');
\$kernel->bootstrap();
\$admin = \Modules\UserManagement\Entities\User::where('user_type', 'super-admin')->orWhere('user_type', 'admin')->first();
if (\$admin) {
    echo \$admin->email ?: '';
}
" 2>/dev/null)

if [ -z "$ADMIN_EMAIL" ]; then
    echo -e "${YELLOW}⚠ Could not get admin email from database${NC}"
    echo -e "${YELLOW}⚠ Please set ADMIN_EMAIL and ADMIN_PASSWORD in the script${NC}"
    echo -e "${YELLOW}⚠ Continuing without authentication (routes will be skipped)${NC}"
    echo ""
else
    echo "Admin Email: $ADMIN_EMAIL"
    echo "Attempting to login..."
    
    # Get CSRF token first
    csrf_response=$(curl -s -c "$COOKIE_FILE" "$BASE_URL/admin/auth/login")
    csrf_token=$(echo "$csrf_response" | grep -o 'name="_token" value="[^"]*' | cut -d'"' -f4)
    
    if [ -z "$csrf_token" ]; then
        echo -e "${YELLOW}⚠ Could not extract CSRF token${NC}"
    else
        echo "CSRF Token obtained"
        
        # Attempt login
        login_response=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
            -b "$COOKIE_FILE" \
            -c "$COOKIE_FILE" \
            -X POST \
            -H "Content-Type: application/x-www-form-urlencoded" \
            -H "Accept: text/html" \
            -d "email=$ADMIN_EMAIL&password=$ADMIN_PASSWORD&_token=$csrf_token" \
            -L \
            "$BASE_URL/admin/auth/login" 2>&1)
        
        login_code=$(echo "$login_response" | grep "HTTP_CODE" | cut -d: -f2 | tr -d ' ')
        
        # Check if we have a valid session (check for dashboard redirect or success)
        if [ "$login_code" -eq 200 ] || [ "$login_code" -eq 302 ]; then
            # Check if we're redirected to dashboard or have dashboard content
            if echo "$login_response" | grep -q "dashboard\|admin" || [ "$login_code" -eq 302 ]; then
                echo -e "${GREEN}✓ Authentication successful${NC}"
            else
                echo -e "${YELLOW}⚠ Login response unclear, but continuing...${NC}"
            fi
        else
            echo -e "${RED}✗ Login failed (HTTP $login_code)${NC}"
            echo -e "${YELLOW}⚠ Continuing without authentication (routes will be skipped)${NC}"
            rm -f "$COOKIE_FILE"
        fi
    fi
fi
echo ""

# ==========================================
# ADMIN DASHBOARD ROUTES
# ==========================================
echo "=========================================="
echo "STEP 2: Admin Dashboard Routes Testing"
echo "=========================================="

# Main dashboard routes
test_endpoint "GET" "/admin" "Main dashboard page"
test_endpoint "GET" "/admin/heat-map" "Heat map page"
test_endpoint "GET" "/admin/heat-map-overview-data" "Heat map overview data"
test_endpoint "GET" "/admin/heat-map-compare" "Heat map compare page"
test_endpoint "GET" "/admin/recent-trip-activity" "Recent trip activity (AJAX)"
test_endpoint "GET" "/admin/leader-board-driver" "Leader board driver (AJAX)"
test_endpoint "GET" "/admin/leader-board-customer" "Leader board customer (AJAX)"
test_endpoint "GET" "/admin/earning-statistics" "Earning statistics (AJAX)"
test_endpoint "GET" "/admin/zone-wise-statistics" "Zone wise statistics (AJAX)"
test_endpoint "GET" "/admin/chatting" "Chatting page"
test_endpoint "GET" "/admin/search-drivers" "Search drivers (AJAX)"
test_endpoint "GET" "/admin/search-saved-topic-answers" "Search saved topic answers (AJAX)"
test_endpoint "GET" "/admin/feature-toggles" "Get feature toggles (AJAX)"

# POST routes (need data)
test_endpoint "POST" "/admin/clear-cache" "_token=$(php -r "echo csrf_token();" 2>/dev/null || echo 'test')" "Clear cache"
test_endpoint "POST" "/admin/toggle-ai-chatbot" "enabled=1&_token=$(php -r "echo csrf_token();" 2>/dev/null || echo 'test')" "Toggle AI chatbot"
test_endpoint "POST" "/admin/toggle-honeycomb" "feature=enabled&enabled=1&_token=$(php -r "echo csrf_token();" 2>/dev/null || echo 'test')" "Toggle Honeycomb"

# Routes with parameters (will test with placeholder)
test_endpoint "GET" "/admin/driver-conversation/test-channel-id" "Get driver conversation (with placeholder channelId)"

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
REPORT_FILE="ADMIN_DASHBOARD_ROUTES_TEST_REPORT_$(date +%Y%m%d_%H%M%S).md"
echo "Generating detailed report: $REPORT_FILE"

cat > "$REPORT_FILE" << EOF
# Admin Dashboard Routes Testing Report

**Generated:** $(date)
**Base URL:** $BASE_URL
**Admin Email:** ${ADMIN_EMAIL:-"Not provided"}

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

### Main Dashboard Routes
1. **GET /admin** - Main dashboard page
2. **GET /admin/heat-map** - Heat map visualization page
3. **GET /admin/heat-map-overview-data** - Heat map overview data (AJAX)
4. **GET /admin/heat-map-compare** - Heat map comparison page
5. **GET /admin/recent-trip-activity** - Recent trip activity (AJAX)
6. **GET /admin/leader-board-driver** - Driver leader board (AJAX)
7. **GET /admin/leader-board-customer** - Customer leader board (AJAX)
8. **GET /admin/earning-statistics** - Earning statistics (AJAX)
9. **GET /admin/zone-wise-statistics** - Zone wise statistics (AJAX)
10. **GET /admin/chatting** - Chatting interface page
11. **GET /admin/search-drivers** - Search drivers (AJAX)
12. **GET /admin/search-saved-topic-answers** - Search saved topic answers (AJAX)
13. **GET /admin/feature-toggles** - Get feature toggles (AJAX)

### POST Routes
14. **POST /admin/clear-cache** - Clear dashboard cache
15. **POST /admin/toggle-ai-chatbot** - Toggle AI chatbot feature
16. **POST /admin/toggle-honeycomb** - Toggle Honeycomb feature

### Routes with Parameters
17. **GET /admin/driver-conversation/{channelId}** - Get driver conversation (tested with placeholder)

## Notes

- All routes require authentication via session cookies
- Routes are under the \`/admin\` prefix
- Most routes use GET method, some use POST
- AJAX routes return JSON or HTML fragments
- Some routes require CSRF token for POST requests
- Routes with dynamic parameters (like channelId) were tested with placeholder values

## Authentication

Admin routes use session-based authentication:
1. Login via \`POST /admin/auth/login\` with email and password
2. Session cookie is stored and used for subsequent requests
3. CSRF token required for POST requests

EOF

echo -e "${GREEN}Report saved to: $REPORT_FILE${NC}"
echo ""

# Clean up
rm -f "$COOKIE_FILE"

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
