#!/bin/bash

# Driver Features Testing Script
# Tests all driver app features using curl

BASE_URL="http://127.0.0.1:8000/api"
TOKEN=""
DRIVER_ID=""

echo "=========================================="
echo "DRIVER APP FEATURES TESTING REPORT"
echo "=========================================="
echo "Base URL: $BASE_URL"
echo "Date: $(date)"
echo ""

# Function to test endpoint
test_endpoint() {
    local method=$1
    local endpoint=$2
    local data=$3
    local description=$4
    
    echo "----------------------------------------"
    echo "TEST: $description"
    echo "Endpoint: $method $endpoint"
    
    if [ "$method" = "GET" ]; then
        response=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
            -H "Authorization: Bearer $TOKEN" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            "$BASE_URL$endpoint")
    else
        response=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
            -X "$method" \
            -H "Authorization: Bearer $TOKEN" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            -d "$data" \
            "$BASE_URL$endpoint")
    fi
    
    http_code=$(echo "$response" | grep "HTTP_CODE" | cut -d: -f2)
    body=$(echo "$response" | sed '/HTTP_CODE/d')
    
    echo "HTTP Status: $http_code"
    echo "Response: $body" | head -c 500
    echo ""
    
    if [ "$http_code" -ge 200 ] && [ "$http_code" -lt 300 ]; then
        echo "✓ PASS"
    elif [ "$http_code" -eq 401 ]; then
        echo "⚠ AUTH REQUIRED (Need valid token)"
    elif [ "$http_code" -eq 404 ]; then
        echo "✗ NOT FOUND"
    else
        echo "✗ FAIL"
    fi
    echo ""
}

# First, try to get a token (if we have driver credentials)
echo "=========================================="
echo "STEP 1: Authentication"
echo "=========================================="
echo "Note: Need valid driver credentials to get token"
echo "Skipping authentication - using placeholder token"
echo ""

# Set a placeholder token (in real test, get from login)
TOKEN="PLACEHOLDER_TOKEN"

echo "=========================================="
echo "STEP 2: Profile & Settings"
echo "=========================================="

test_endpoint "GET" "/driver/info" "" "Get driver profile info"
test_endpoint "PUT" "/driver/update/profile" '{"first_name":"Test"}' "Update profile"
test_endpoint "POST" "/driver/change-language" '{"language":"en"}' "Change language"

echo "=========================================="
echo "STEP 3: Privacy Settings"
echo "=========================================="

test_endpoint "GET" "/driver/auth/account/privacy-settings" "" "Get privacy settings"
test_endpoint "PUT" "/driver/auth/account/privacy-settings" '{"profile_visibility":"public"}' "Update privacy settings"

echo "=========================================="
echo "STEP 4: Vehicle Management"
echo "=========================================="

test_endpoint "GET" "/driver/vehicle/category/list" "" "Get vehicle categories"
test_endpoint "GET" "/driver/vehicle/brand/list" "" "Get vehicle brands"
test_endpoint "GET" "/driver/vehicle/model/list" "" "Get vehicle models"
test_endpoint "GET" "/driver/auth/vehicle/insurance-status" "" "Get insurance status"
test_endpoint "GET" "/driver/auth/vehicle/inspection-status" "" "Get inspection status"
test_endpoint "GET" "/driver/auth/vehicle/reminders" "" "Get vehicle reminders"

echo "=========================================="
echo "STEP 5: Documents Management"
echo "=========================================="

test_endpoint "GET" "/driver/auth/documents/expiry-status" "" "Get document expiry status"

echo "=========================================="
echo "STEP 6: Earnings & Reports"
echo "=========================================="

test_endpoint "GET" "/driver/income-statement" "" "Get income statement"
test_endpoint "GET" "/driver/auth/reports/weekly" "" "Get weekly report"
test_endpoint "GET" "/driver/auth/reports/monthly" "" "Get monthly report"

echo "=========================================="
echo "STEP 7: Support & Help"
echo "=========================================="

test_endpoint "GET" "/driver/auth/support/faqs" "" "Get FAQs"
test_endpoint "GET" "/driver/auth/support/tickets" "" "Get support tickets"
test_endpoint "POST" "/driver/auth/support/tickets" '{"subject":"Test","message":"Test message"}' "Create support ticket"
test_endpoint "POST" "/driver/auth/support/feedback" '{"rating":5,"comment":"Great app"}' "Submit feedback"
test_endpoint "POST" "/driver/auth/support/report-issue" '{"issue_type":"bug","description":"Test issue"}' "Report issue"
test_endpoint "GET" "/driver/auth/support/app-info" "" "Get app info"

echo "=========================================="
echo "STEP 8: Notifications"
echo "=========================================="

test_endpoint "GET" "/driver/auth/notifications" "" "Get all notifications"
test_endpoint "GET" "/driver/auth/notifications/unread-count" "" "Get unread count"
test_endpoint "GET" "/driver/auth/notifications/settings" "" "Get notification settings"
test_endpoint "PUT" "/driver/auth/notifications/settings" '{"ride_notifications":true}' "Update notification settings"

echo "=========================================="
echo "STEP 9: Content Pages"
echo "=========================================="

test_endpoint "GET" "/driver/auth/pages" "" "Get all pages"
test_endpoint "GET" "/driver/auth/pages/terms" "" "Get terms & conditions"
test_endpoint "GET" "/driver/auth/pages/privacy" "" "Get privacy policy"
test_endpoint "GET" "/driver/auth/pages/about" "" "Get about page"

echo "=========================================="
echo "STEP 10: Account Management"
echo "=========================================="

test_endpoint "GET" "/driver/auth/account/emergency-contacts" "" "Get emergency contacts"
test_endpoint "POST" "/driver/auth/account/emergency-contacts" '{"name":"Emergency","phone":"+201234567890","relationship":"family"}' "Create emergency contact"
test_endpoint "POST" "/driver/auth/account/change-phone/request" '{"new_phone":"+201234567890"}' "Request phone change"
test_endpoint "GET" "/driver/auth/account/delete-status" "" "Get account deletion status"

echo "=========================================="
echo "STEP 11: Dashboard & Activity"
echo "=========================================="

test_endpoint "GET" "/driver/auth/dashboard/widgets" "" "Get dashboard widgets"
test_endpoint "GET" "/driver/auth/dashboard/recent-activity" "" "Get recent activity"
test_endpoint "GET" "/driver/auth/dashboard/promotional-banners" "" "Get promotional banners"
test_endpoint "GET" "/driver/my-activity" "" "Get my activity"

echo "=========================================="
echo "STEP 12: Gamification"
echo "=========================================="

test_endpoint "GET" "/driver/auth/gamification/achievements" "" "Get achievements"
test_endpoint "GET" "/driver/auth/gamification/badges" "" "Get badges"
test_endpoint "GET" "/driver/auth/gamification/progress" "" "Get progress"
test_endpoint "GET" "/driver/activity/leaderboard" "" "Get leaderboard"

echo "=========================================="
echo "STEP 13: Promotions & Offers"
echo "=========================================="

test_endpoint "GET" "/driver/auth/promotions" "" "Get promotions"
test_endpoint "GET" "/driver/referral-details" "" "Get referral details"

echo "=========================================="
echo "STEP 14: Readiness Check"
echo "=========================================="

test_endpoint "GET" "/driver/auth/readiness-check" "" "Driver readiness check"

echo "=========================================="
echo "TESTING COMPLETE"
echo "=========================================="
