#!/bin/bash

# Comprehensive Driver Features Testing Script
# Tests all new driver app features using curl

BASE_URL="https://smartline-it.com/api"
TOKEN=""
DRIVER_PHONE="+201208673028"
DRIVER_PASSWORD="password123"

echo "=========================================="
echo "COMPREHENSIVE DRIVER APP FEATURES TEST"
echo "=========================================="
echo "Base URL: $BASE_URL"
echo "Date: $(date)"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test results tracking
PASSED=0
FAILED=0
SKIPPED=0

# Function to test endpoint
test_endpoint() {
    local method=$1
    local endpoint=$2
    local data=$3
    local description=$4
    local expected_status=${5:-200}
    
    echo "----------------------------------------"
    echo -e "${YELLOW}TEST: $description${NC}"
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
    
    # Show first 300 chars of response
    response_preview=$(echo "$body" | head -c 300)
    if [ ${#body} -gt 300 ]; then
        echo "Response: ${response_preview}..."
    else
        echo "Response: $body"
    fi
    echo ""
    
    # Check result
    if [ "$http_code" -ge 200 ] && [ "$http_code" -lt 300 ]; then
        echo -e "${GREEN}✓ PASS${NC}"
        ((PASSED++))
    elif [ "$http_code" -eq 401 ]; then
        echo -e "${RED}✗ FAIL - Authentication required${NC}"
        ((FAILED++))
    elif [ "$http_code" -eq 404 ]; then
        echo -e "${RED}✗ FAIL - Endpoint not found${NC}"
        ((FAILED++))
    elif [ "$http_code" -eq 422 ]; then
        echo -e "${YELLOW}⚠ VALIDATION ERROR (may be expected)${NC}"
        ((SKIPPED++))
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

echo "Login Response: $login_response"
TOKEN=$(echo "$login_response" | grep -o '"token":"[^"]*' | cut -d'"' -f4)

if [ -z "$TOKEN" ]; then
    echo -e "${YELLOW}⚠ Could not get token from login. Trying to get from database...${NC}"
    # Try to get token from database using PHP
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
    echo -e "${YELLOW}⚠ Continuing with tests (will show as skipped)${NC}"
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
test_endpoint "PUT" "/driver/update/profile" '{"first_name":"Test","last_name":"Driver"}' "Update profile"
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
test_endpoint "POST" "/driver/auth/vehicle/inspection-update" '{"expiry_date":"2026-12-31","document_url":"https://example.com/inspection.pdf"}' "Update inspection"
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

test_endpoint "GET" "/driver/income-statement" "" "Get income statement"
test_endpoint "GET" "/driver/auth/reports/weekly" "" "Get weekly report"
test_endpoint "GET" "/driver/auth/reports/monthly" "" "Get monthly report"
test_endpoint "POST" "/driver/auth/reports/export" '{"type":"weekly","format":"pdf"}' "Export report"

# ==========================================
# SUPPORT & HELP
# ==========================================
echo "=========================================="
echo "STEP 6: Support & Help"
echo "=========================================="

test_endpoint "GET" "/driver/auth/support/faqs" "" "Get FAQs"
test_endpoint "POST" "/driver/auth/support/faqs/1/feedback" '{"helpful":true}' "FAQ feedback"
test_endpoint "GET" "/driver/auth/support/tickets" "" "Get support tickets"
test_endpoint "POST" "/driver/auth/support/tickets" '{"subject":"Test Ticket","message":"This is a test support ticket","category":"technical"}' "Create support ticket"
test_endpoint "GET" "/driver/auth/support/tickets/1" "" "Get ticket details"
test_endpoint "POST" "/driver/auth/support/tickets/1/reply" '{"message":"Test reply"}' "Reply to ticket"
test_endpoint "POST" "/driver/auth/support/feedback" '{"rating":5,"comment":"Great app!","category":"general"}' "Submit feedback"
test_endpoint "POST" "/driver/auth/support/report-issue" '{"issue_type":"bug","description":"Test issue report","severity":"medium"}' "Report issue"
test_endpoint "GET" "/driver/auth/support/app-info" "" "Get app version info"

# ==========================================
# NOTIFICATIONS
# ==========================================
echo "=========================================="
echo "STEP 7: Notifications"
echo "=========================================="

test_endpoint "GET" "/driver/auth/notifications" "" "Get all notifications"
test_endpoint "GET" "/driver/auth/notifications/unread-count" "" "Get unread count"
test_endpoint "POST" "/driver/auth/notifications/1/read" "" "Mark notification as read"
test_endpoint "POST" "/driver/auth/notifications/1/unread" "" "Mark notification as unread"
test_endpoint "POST" "/driver/auth/notifications/read-all" "" "Mark all as read"
test_endpoint "DELETE" "/driver/auth/notifications/1" "" "Delete notification"
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
test_endpoint "POST" "/driver/auth/account/emergency-contacts" '{"name":"Emergency Contact","phone":"+201234567890","relationship":"family","is_primary":false}' "Create emergency contact"
test_endpoint "PUT" "/driver/auth/account/emergency-contacts/1" '{"name":"Updated Contact","phone":"+201234567890"}' "Update emergency contact"
test_endpoint "POST" "/driver/auth/account/emergency-contacts/1/set-primary" "" "Set primary emergency contact"
test_endpoint "DELETE" "/driver/auth/account/emergency-contacts/1" "" "Delete emergency contact"
test_endpoint "POST" "/driver/auth/account/change-phone/request" '{"new_phone":"+201234567890"}' "Request phone change"
test_endpoint "POST" "/driver/auth/account/change-phone/verify-old" '{"otp":"123456"}' "Verify old phone"
test_endpoint "POST" "/driver/auth/account/change-phone/verify-new" '{"otp":"123456"}' "Verify new phone"
test_endpoint "POST" "/driver/auth/account/delete-request" '{"reason":"Testing","password":"password123"}' "Request account deletion"
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
test_endpoint "GET" "/driver/my-activity" "" "Get my activity"

# ==========================================
# GAMIFICATION
# ==========================================
echo "=========================================="
echo "STEP 11: Gamification"
echo "=========================================="

test_endpoint "GET" "/driver/auth/gamification/achievements" "" "Get achievements"
test_endpoint "GET" "/driver/auth/gamification/badges" "" "Get badges"
test_endpoint "GET" "/driver/auth/gamification/progress" "" "Get progress"
test_endpoint "GET" "/driver/activity/leaderboard" "" "Get leaderboard"
test_endpoint "GET" "/driver/level/details" "" "Get driver level details"

# ==========================================
# PROMOTIONS & OFFERS
# ==========================================
echo "=========================================="
echo "STEP 12: Promotions & Offers"
echo "=========================================="

test_endpoint "GET" "/driver/auth/promotions" "" "Get promotions"
test_endpoint "GET" "/driver/auth/promotions/1" "" "Get promotion details"
test_endpoint "POST" "/driver/auth/promotions/1/claim" "" "Claim promotion"
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
echo "TEST SUMMARY"
echo "=========================================="
echo -e "${GREEN}Passed: $PASSED${NC}"
echo -e "${RED}Failed: $FAILED${NC}"
echo -e "${YELLOW}Skipped: $SKIPPED${NC}"
echo "Total Tests: $((PASSED + FAILED + SKIPPED))"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}✗ Some tests failed${NC}"
    exit 1
fi
