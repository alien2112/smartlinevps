#!/bin/bash

# Corrected Driver Features Testing Script
# Tests all new driver app features using curl with proper error handling

BASE_URL="https://smartline-it.com/api"
TOKEN=""
DRIVER_PHONE="+201208673028"
DRIVER_PASSWORD="password123"

echo "=========================================="
echo "DRIVER APP FEATURES TEST - CORRECTED"
echo "=========================================="
echo "Base URL: $BASE_URL"
echo "Date: $(date)"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

PASSED=0
FAILED=0
SKIPPED=0
TOTAL=0

# Function to test endpoint with detailed error reporting
test_endpoint() {
    local method=$1
    local endpoint=$2
    local data=$3
    local description=$4
    
    ((TOTAL++))
    
    echo "----------------------------------------"
    echo -e "${BLUE}[$TOTAL] TEST: $description${NC}"
    echo "Endpoint: $method $endpoint"
    
    if [ -z "$TOKEN" ]; then
        echo -e "${YELLOW}⚠ SKIPPED - No authentication token${NC}"
        ((SKIPPED++))
        echo ""
        return
    fi
    
    if [ "$method" = "GET" ]; then
        response=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
            -H "Authorization: Bearer $TOKEN" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            "$BASE_URL$endpoint" 2>&1)
    else
        response=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
            -X "$method" \
            -H "Authorization: Bearer $TOKEN" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            -d "$data" \
            "$BASE_URL$endpoint" 2>&1)
    fi
    
    http_code=$(echo "$response" | grep "HTTP_CODE" | cut -d: -f2)
    body=$(echo "$response" | sed '/HTTP_CODE/d')
    
    echo "HTTP Status: $http_code"
    
    # Extract error message if any
    error_msg=$(echo "$body" | grep -o '"message":"[^"]*' | cut -d'"' -f4 | head -1)
    exception=$(echo "$body" | grep -o '"exception":"[^"]*' | cut -d'"' -f4 | head -1)
    file=$(echo "$body" | grep -o '"file":"[^"]*' | cut -d'"' -f4 | head -1)
    
    # Show response preview
    response_preview=$(echo "$body" | head -c 500)
    if [ ${#body} -gt 500 ]; then
        echo "Response: ${response_preview}..."
    else
        echo "Response: $body"
    fi
    
    # Detailed error analysis
    if [ "$http_code" -ge 200 ] && [ "$http_code" -lt 300 ]; then
        echo -e "${GREEN}✓ PASS${NC}"
        ((PASSED++))
    elif [ "$http_code" -eq 401 ]; then
        echo -e "${RED}✗ FAIL - Authentication required (401)${NC}"
        echo "Reason: Token may be invalid or expired"
        ((FAILED++))
    elif [ "$http_code" -eq 403 ]; then
        echo -e "${RED}✗ FAIL - Forbidden (403)${NC}"
        echo "Reason: Insufficient permissions or missing required fields"
        if [ ! -z "$error_msg" ]; then
            echo "Error: $error_msg"
        fi
        ((FAILED++))
    elif [ "$http_code" -eq 404 ]; then
        echo -e "${RED}✗ FAIL - Not Found (404)${NC}"
        echo "Reason: Endpoint does not exist or route not registered"
        ((FAILED++))
    elif [ "$http_code" -eq 422 ]; then
        echo -e "${YELLOW}⚠ VALIDATION ERROR (422)${NC}"
        echo "Reason: Request validation failed"
        if [ ! -z "$error_msg" ]; then
            echo "Error: $error_msg"
        fi
        ((SKIPPED++))
    elif [ "$http_code" -eq 500 ]; then
        echo -e "${RED}✗ FAIL - Server Error (500)${NC}"
        if [ ! -z "$exception" ]; then
            echo "Exception: $exception"
        fi
        if [ ! -z "$file" ]; then
            echo "File: $(basename $file)"
        fi
        if [ ! -z "$error_msg" ]; then
            echo "Error: $error_msg"
        fi
        ((FAILED++))
    else
        echo -e "${RED}✗ FAIL - HTTP $http_code${NC}"
        ((FAILED++))
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

TOKEN=$(echo "$login_response" | grep -o '"token":"[^"]*' | cut -d'"' -f4)

if [ -z "$TOKEN" ]; then
    echo -e "${YELLOW}⚠ Could not get token from login. Trying database...${NC}"
    TOKEN=$(php -r "
    require '/var/www/laravel/smartlinevps/rateel/vendor/autoload.php';
    \$app = require_once '/var/www/laravel/smartlinevps/rateel/bootstrap/app.php';
    \$kernel = \$app->make('Illuminate\Contracts\Console\Kernel');
    \$kernel->bootstrap();
    \$driver = \Modules\UserManagement\Entities\User::where('phone', '$DRIVER_PHONE')->first();
    if (\$driver) {
        \$token = \DB::table('oauth_access_tokens')
            ->where('user_id', \$driver->id)
            ->where('revoked', 0)
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->value('id');
        echo \$token ? \$token : '';
    }
    " 2>/dev/null)
fi

if [ -z "$TOKEN" ]; then
    echo -e "${RED}✗ Could not obtain authentication token${NC}"
    exit 1
else
    echo -e "${GREEN}✓ Authentication token obtained${NC}"
    echo "Token: ${TOKEN:0:50}..."
fi
echo ""

# ==========================================
# PROFILE & SETTINGS
# ==========================================
echo "=========================================="
echo "STEP 2: Profile & Settings"
echo "=========================================="

test_endpoint "GET" "/driver/info" "" "Get driver profile info"
test_endpoint "PUT" "/driver/update/profile" '{"first_name":"Test","last_name":"Driver","service":"ride"}' "Update profile"
test_endpoint "POST" "/driver/change-language" '{"language":"en"}' "Change language"
test_endpoint "GET" "/driver/auth/account/privacy-settings" "" "Get privacy settings"
test_endpoint "PUT" "/driver/auth/account/privacy-settings" '{"profile_visibility":"public","location_sharing":true}' "Update privacy settings"

# ==========================================
# VEHICLE MANAGEMENT
# ==========================================
echo "=========================================="
echo "STEP 3: Vehicle Management"
echo "=========================================="

test_endpoint "GET" "/driver/vehicle/category/list" "" "Get vehicle categories"
test_endpoint "GET" "/driver/vehicle/brand/list" "" "Get vehicle brands"
test_endpoint "GET" "/driver/vehicle/model/list" "" "Get vehicle models"
test_endpoint "GET" "/driver/auth/vehicle/insurance-status" "" "Get insurance status"
test_endpoint "POST" "/driver/auth/vehicle/insurance-update" '{"expiry_date":"2026-12-31","document_url":"https://example.com/insurance.pdf"}' "Update insurance"
test_endpoint "GET" "/driver/auth/vehicle/inspection-status" "" "Get inspection status"
test_endpoint "POST" "/driver/auth/vehicle/inspection-update" '{"inspection_date":"2025-12-01","next_due_date":"2026-12-31","certificate_number":"INSP123456"}' "Update inspection"
test_endpoint "GET" "/driver/auth/vehicle/reminders" "" "Get vehicle reminders"

# ==========================================
# DOCUMENTS MANAGEMENT
# ==========================================
echo "=========================================="
echo "STEP 4: Documents Management"
echo "=========================================="

test_endpoint "GET" "/driver/auth/documents/expiry-status" "" "Get document expiry status"

# ==========================================
# EARNINGS & REPORTS
# ==========================================
echo "=========================================="
echo "STEP 5: Earnings & Reports"
echo "=========================================="

test_endpoint "GET" "/driver/income-statement?limit=10&offset=0" "" "Get income statement"
test_endpoint "GET" "/driver/auth/reports/weekly" "" "Get weekly report"
test_endpoint "GET" "/driver/auth/reports/monthly" "" "Get monthly report"
test_endpoint "POST" "/driver/auth/reports/export" '{"format":"pdf","start_date":"2025-12-01","end_date":"2025-12-31","include_details":false}' "Export report"

# ==========================================
# SUPPORT & HELP
# ==========================================
echo "=========================================="
echo "STEP 6: Support & Help"
echo "=========================================="

test_endpoint "GET" "/driver/auth/support/faqs" "" "Get FAQs"
test_endpoint "POST" "/driver/auth/support/faqs/a0bdf0cf-e9bd-476c-888f-d040e0d5c644/feedback" '{"helpful":true}' "FAQ feedback"
test_endpoint "GET" "/driver/auth/support/tickets" "" "Get support tickets"
test_endpoint "POST" "/driver/auth/support/tickets" '{"subject":"Test Ticket","description":"This is a test support ticket description with enough text","category":"technical"}' "Create support ticket"
test_endpoint "GET" "/driver/auth/support/tickets/a0bdf21d-06c0-4486-a024-0ef051afb1e7" "" "Get ticket details"
test_endpoint "POST" "/driver/auth/support/tickets/a0bdf21d-06c0-4486-a024-0ef051afb1e7/reply" '{"message":"Test reply"}' "Reply to ticket"
test_endpoint "POST" "/driver/auth/support/feedback" '{"type":"general_feedback","subject":"App Feedback","message":"This is a detailed feedback message with enough content","rating":5}' "Submit feedback"
test_endpoint "POST" "/driver/auth/support/report-issue" '{"issue_type":"app_malfunction","description":"This is a detailed issue report with enough description text","severity":"medium"}' "Report issue"
test_endpoint "GET" "/driver/auth/support/app-info" "" "Get app version info"

# ==========================================
# NOTIFICATIONS
# ==========================================
echo "=========================================="
echo "STEP 7: Notifications"
echo "=========================================="

test_endpoint "GET" "/driver/auth/notifications" "" "Get all notifications"
test_endpoint "GET" "/driver/auth/notifications/unread-count" "" "Get unread count"
test_endpoint "POST" "/driver/auth/notifications/a0bdf21d-0149-4a97-9014-8cbb26b57ed8/read" "" "Mark notification as read"
test_endpoint "POST" "/driver/auth/notifications/a0bdf21d-0149-4a97-9014-8cbb26b57ed8/unread" "" "Mark notification as unread"
test_endpoint "POST" "/driver/auth/notifications/read-all" "" "Mark all as read"
test_endpoint "DELETE" "/driver/auth/notifications/a0bdf21d-0149-4a97-9014-8cbb26b57ed8" "" "Delete notification"
test_endpoint "POST" "/driver/auth/notifications/clear-read" "" "Clear read notifications"
test_endpoint "GET" "/driver/auth/notifications/settings" "" "Get notification settings"
test_endpoint "PUT" "/driver/auth/notifications/settings" '{"ride_notifications":true,"promotion_notifications":true,"system_notifications":true}' "Update notification settings"

# ==========================================
# CONTENT PAGES
# ==========================================
echo "=========================================="
echo "STEP 8: Content Pages"
echo "=========================================="

test_endpoint "GET" "/driver/auth/pages" "" "Get all pages"
test_endpoint "GET" "/driver/auth/pages/terms" "" "Get terms & conditions"
test_endpoint "GET" "/driver/auth/pages/privacy" "" "Get privacy policy"
test_endpoint "GET" "/driver/auth/pages/about" "" "Get about page"
test_endpoint "GET" "/driver/auth/pages/help" "" "Get help page"

# ==========================================
# ACCOUNT MANAGEMENT
# ==========================================
echo "=========================================="
echo "STEP 9: Account Management"
echo "=========================================="

test_endpoint "GET" "/driver/auth/account/emergency-contacts" "" "Get emergency contacts"
test_endpoint "POST" "/driver/auth/account/emergency-contacts" '{"name":"Emergency Contact","phone":"+201234567890","relationship":"friend","is_primary":false}' "Create emergency contact"
test_endpoint "PUT" "/driver/auth/account/emergency-contacts/fa05fefb-450e-4d1d-9cbf-17a666997b7b" '{"name":"Updated Contact","phone":"+201234567890"}' "Update emergency contact"
test_endpoint "POST" "/driver/auth/account/emergency-contacts/fa05fefb-450e-4d1d-9cbf-17a666997b7b/set-primary" "" "Set primary emergency contact"
test_endpoint "DELETE" "/driver/auth/account/emergency-contacts/fa05fefb-450e-4d1d-9cbf-17a666997b7b" "" "Delete emergency contact"
test_endpoint "POST" "/driver/auth/account/change-phone/request" '{"new_phone":"+209999999999","password":"password123"}' "Request phone change"
test_endpoint "POST" "/driver/auth/account/change-phone/verify-old" '{"otp":"123456"}' "Verify old phone"
test_endpoint "POST" "/driver/auth/account/change-phone/verify-new" '{"otp":"123456"}' "Verify new phone"
test_endpoint "POST" "/driver/auth/account/delete-request" '{"reason":"temporary_break","password":"password123"}' "Request account deletion"
test_endpoint "POST" "/driver/auth/account/delete-cancel" "" "Cancel deletion request"
test_endpoint "GET" "/driver/auth/account/delete-status" "" "Get account deletion status"

# ==========================================
# DASHBOARD & ACTIVITY
# ==========================================
echo "=========================================="
echo "STEP 10: Dashboard & Activity"
echo "=========================================="

test_endpoint "GET" "/driver/auth/dashboard/widgets" "" "Get dashboard widgets"
test_endpoint "GET" "/driver/auth/dashboard/recent-activity" "" "Get recent activity"
test_endpoint "GET" "/driver/auth/dashboard/promotional-banners" "" "Get promotional banners"
test_endpoint "GET" "/driver/my-activity?limit=10&offset=0" "" "Get my activity"

# ==========================================
# GAMIFICATION
# ==========================================
echo "=========================================="
echo "STEP 11: Gamification"
echo "=========================================="

test_endpoint "GET" "/driver/auth/gamification/achievements" "" "Get achievements"
test_endpoint "GET" "/driver/auth/gamification/badges" "" "Get badges"
test_endpoint "GET" "/driver/auth/gamification/progress" "" "Get progress"
test_endpoint "GET" "/driver/activity/leaderboard?filter=today&limit=10&offset=0" "" "Get leaderboard"
test_endpoint "GET" "/driver/level" "" "Get driver level details"

# ==========================================
# PROMOTIONS & OFFERS
# ==========================================
echo "=========================================="
echo "STEP 12: Promotions & Offers"
echo "=========================================="

test_endpoint "GET" "/driver/auth/promotions" "" "Get promotions"
test_endpoint "GET" "/driver/auth/promotions/5a05a1d8-bf77-4685-9eca-042f9da667aa" "" "Get promotion details"
test_endpoint "POST" "/driver/auth/promotions/5a05a1d8-bf77-4685-9eca-042f9da667aa/claim" "" "Claim promotion"
test_endpoint "GET" "/driver/referral-details" "" "Get referral details"

# ==========================================
# READINESS CHECK
# ==========================================
echo "=========================================="
echo "STEP 13: Readiness Check"
echo "=========================================="

test_endpoint "GET" "/driver/auth/readiness-check" "" "Driver readiness check"

# ==========================================
# SUMMARY
# ==========================================
echo "=========================================="
echo "FINAL TEST SUMMARY"
echo "=========================================="
echo -e "${GREEN}Passed: $PASSED${NC}"
echo -e "${RED}Failed: $FAILED${NC}"
echo -e "${YELLOW}Skipped: $SKIPPED${NC}"
echo "Total Tests: $TOTAL"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}✗ Some tests failed${NC}"
    exit 1
fi
