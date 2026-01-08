#!/bin/bash

BASE_URL="https://smartline-it.com/api"
PHONE="+2011767463164"
PASSWORD="password123"

echo "==================================="
echo "LOGGING IN TO GET TOKEN"
echo "==================================="

# Login to get token
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/v2/driver/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"phone\":\"$PHONE\",\"password\":\"$PASSWORD\"}")

echo "$LOGIN_RESPONSE" | jq '.' 2>/dev/null || echo "$LOGIN_RESPONSE"

TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.data.token // .token // empty' 2>/dev/null)

if [ -z "$TOKEN" ] || [ "$TOKEN" = "null" ]; then
  echo "Failed to get token. Exiting."
  exit 1
fi

echo ""
echo "✅ Token obtained: ${TOKEN:0:20}..."
echo ""

# Test counter
TOTAL=0
PASSED=0
FAILED=0

test_api() {
  local num=$1
  local method=$2
  local endpoint=$3
  local name=$4
  local data=$5
  
  TOTAL=$((TOTAL + 1))
  
  echo "==================================="
  echo "TEST $num: $name"
  echo "==================================="
  echo "Method: $method"
  echo "Endpoint: $endpoint"
  
  if [ -n "$data" ]; then
    RESPONSE=$(curl -s -X "$method" "$BASE_URL$endpoint" \
      -H "Authorization: Bearer $TOKEN" \
      -H "Content-Type: application/json" \
      -d "$data")
  else
    RESPONSE=$(curl -s -X "$method" "$BASE_URL$endpoint" \
      -H "Authorization: Bearer $TOKEN")
  fi
  
  echo "$RESPONSE" | jq '.' 2>/dev/null || echo "$RESPONSE"
  
  # Check if response contains success indicators
  if echo "$RESPONSE" | grep -q '"success":true\|"status":"success"\|"data":\|"message"'; then
    if ! echo "$RESPONSE" | grep -q '"success":false\|"error"\|"unauthorized"\|"unauthenticated"'; then
      echo "✅ PASSED"
      PASSED=$((PASSED + 1))
    else
      echo "❌ FAILED"
      FAILED=$((FAILED + 1))
    fi
  else
    echo "❌ FAILED"
    FAILED=$((FAILED + 1))
  fi
  
  echo ""
}

echo ""
echo "###################################"
echo "# 1. NOTIFICATIONS (9 APIs)"
echo "###################################"
echo ""

test_api 1 "GET" "/driver/auth/notifications" "Get all notifications"
test_api 2 "GET" "/driver/auth/notifications/unread-count" "Get unread count"
test_api 3 "POST" "/driver/auth/notifications/1/read" "Mark notification as read"
test_api 4 "POST" "/driver/auth/notifications/1/unread" "Mark notification as unread"
test_api 5 "POST" "/driver/auth/notifications/read-all" "Mark all as read"
test_api 6 "DELETE" "/driver/auth/notifications/1" "Delete notification"
test_api 7 "POST" "/driver/auth/notifications/clear-read" "Clear read notifications"
test_api 8 "GET" "/driver/auth/notifications/settings" "Get notification settings"
test_api 9 "PUT" "/driver/auth/notifications/settings" "Update notification settings" '{"push_enabled":true,"email_enabled":false}'

echo ""
echo "###################################"
echo "# 2. SUPPORT & HELP (10 APIs)"
echo "###################################"
echo ""

test_api 10 "GET" "/driver/auth/support/faqs" "Get FAQs"
test_api 11 "POST" "/driver/auth/support/faqs/1/feedback" "FAQ feedback" '{"helpful":true}'
test_api 12 "GET" "/driver/auth/support/tickets" "Get support tickets"
test_api 13 "POST" "/driver/auth/support/tickets" "Create support ticket" '{"subject":"Test Issue","message":"Testing support ticket","category":"technical"}'
test_api 14 "GET" "/driver/auth/support/tickets/1" "Get ticket details"
test_api 15 "POST" "/driver/auth/support/tickets/1/reply" "Reply to ticket" '{"message":"Test reply"}'
test_api 16 "POST" "/driver/auth/support/tickets/1/rate" "Rate support" '{"rating":5,"comment":"Excellent"}'
test_api 17 "POST" "/driver/auth/support/feedback" "Submit feedback" '{"type":"general","message":"Great app"}'
test_api 18 "POST" "/driver/auth/support/report-issue" "Report issue" '{"issue_type":"bug","description":"Test bug report"}'
test_api 19 "GET" "/driver/auth/support/app-info" "Get app info"

echo ""
echo "###################################"
echo "# 3. CONTENT PAGES (5 APIs)"
echo "###################################"
echo ""

test_api 20 "GET" "/driver/auth/pages" "Get all pages"
test_api 21 "GET" "/driver/auth/pages/terms" "Get terms & conditions"
test_api 22 "GET" "/driver/auth/pages/privacy" "Get privacy policy"
test_api 23 "GET" "/driver/auth/pages/about" "Get about page"
test_api 24 "GET" "/driver/auth/pages/help" "Get help page"

echo ""
echo "###################################"
echo "# 4. PRIVACY SETTINGS (2 APIs)"
echo "###################################"
echo ""

test_api 25 "GET" "/driver/auth/account/privacy-settings" "Get privacy settings"
test_api 26 "PUT" "/driver/auth/account/privacy-settings" "Update privacy settings" '{"location_sharing":true,"activity_status":true}'

echo ""
echo "###################################"
echo "# 5. EMERGENCY CONTACTS (5 APIs)"
echo "###################################"
echo ""

test_api 27 "GET" "/driver/auth/account/emergency-contacts" "Get emergency contacts"
test_api 28 "POST" "/driver/auth/account/emergency-contacts" "Create emergency contact" '{"name":"John Doe","phone":"+201234567890","relationship":"friend"}'
test_api 29 "PUT" "/driver/auth/account/emergency-contacts/1" "Update emergency contact" '{"name":"Jane Doe","phone":"+201234567891"}'
test_api 30 "DELETE" "/driver/auth/account/emergency-contacts/1" "Delete emergency contact"
test_api 31 "POST" "/driver/auth/account/emergency-contacts/1/set-primary" "Set primary contact"

echo ""
echo "###################################"
echo "# 6. PHONE CHANGE (3 APIs)"
echo "###################################"
echo ""

test_api 32 "POST" "/driver/auth/account/change-phone/request" "Request phone change" '{"new_phone":"+201111111111"}'
test_api 33 "POST" "/driver/auth/account/change-phone/verify-old" "Verify old phone" '{"otp":"123456"}'
test_api 34 "POST" "/driver/auth/account/change-phone/verify-new" "Verify new phone" '{"otp":"123456"}'

echo ""
echo "###################################"
echo "# 7. ACCOUNT DELETION (3 APIs)"
echo "###################################"
echo ""

test_api 35 "POST" "/driver/auth/account/delete-request" "Request account deletion" '{"reason":"testing","password":"password123"}'
test_api 36 "POST" "/driver/auth/account/delete-cancel" "Cancel deletion request"
test_api 37 "GET" "/driver/auth/account/delete-status" "Get deletion status"

echo ""
echo "###################################"
echo "# 8. DASHBOARD & ACTIVITY (3 APIs)"
echo "###################################"
echo ""

test_api 38 "GET" "/driver/auth/dashboard/widgets" "Get dashboard widgets"
test_api 39 "GET" "/driver/auth/dashboard/recent-activity" "Get recent activity"
test_api 40 "GET" "/driver/auth/dashboard/promotional-banners" "Get promotional banners"

echo ""
echo "###################################"
echo "# BONUS: TRIP REPORTS (3 APIs)"
echo "###################################"
echo ""

test_api 41 "GET" "/driver/auth/reports/weekly" "Get weekly report"
test_api 42 "GET" "/driver/auth/reports/monthly" "Get monthly report"
test_api 43 "POST" "/driver/auth/reports/export" "Export report" '{"type":"monthly","format":"pdf"}'

echo ""
echo "###################################"
echo "# BONUS: VEHICLE MANAGEMENT (5 APIs)"
echo "###################################"
echo ""

test_api 44 "GET" "/driver/auth/vehicle/insurance-status" "Get insurance status"
test_api 45 "POST" "/driver/auth/vehicle/insurance-update" "Update insurance" '{"policy_number":"POL123","expiry_date":"2026-12-31"}'
test_api 46 "GET" "/driver/auth/vehicle/inspection-status" "Get inspection status"
test_api 47 "POST" "/driver/auth/vehicle/inspection-update" "Update inspection" '{"inspection_date":"2026-01-08","next_due":"2026-07-08"}'
test_api 48 "GET" "/driver/auth/vehicle/reminders" "Get vehicle reminders"

echo ""
echo "###################################"
echo "# BONUS: DOCUMENTS (2 APIs)"
echo "###################################"
echo ""

test_api 49 "GET" "/driver/auth/documents/expiry-status" "Get document expiry status"
test_api 50 "POST" "/driver/auth/documents/1/update-expiry" "Update document expiry" '{"expiry_date":"2027-01-08"}'

echo ""
echo "###################################"
echo "# BONUS: GAMIFICATION (3 APIs)"
echo "###################################"
echo ""

test_api 51 "GET" "/driver/auth/gamification/achievements" "Get achievements"
test_api 52 "GET" "/driver/auth/gamification/badges" "Get badges"
test_api 53 "GET" "/driver/auth/gamification/progress" "Get progress"

echo ""
echo "###################################"
echo "# BONUS: PROMOTIONS & OFFERS (3 APIs)"
echo "###################################"
echo ""

test_api 54 "GET" "/driver/auth/promotions" "Get promotions"
test_api 55 "GET" "/driver/auth/promotions/1" "Get promotion details"
test_api 56 "POST" "/driver/auth/promotions/1/claim" "Claim promotion"

echo ""
echo "###################################"
echo "# BONUS: READINESS CHECK (1 API)"
echo "###################################"
echo ""

test_api 57 "GET" "/driver/auth/readiness-check" "Driver readiness check"

echo ""
echo "==================================="
echo "FINAL SUMMARY"
echo "==================================="
echo "Total Tests: $TOTAL"
echo "✅ Passed: $PASSED"
echo "❌ Failed: $FAILED"
echo "Success Rate: $(awk "BEGIN {printf \"%.1f\", ($PASSED/$TOTAL)*100}")%"
echo "==================================="

