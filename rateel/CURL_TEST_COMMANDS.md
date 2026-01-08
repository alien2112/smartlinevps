# Quick CURL Test Commands - 40 Driver APIs

## Setup
```bash
BASE_URL="https://smartline-it.com/api"
PHONE="+2011767463164"
PASSWORD="password123"

# Get Token
TOKEN=$(curl -s -X POST "$BASE_URL/v2/driver/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"phone\":\"$PHONE\",\"password\":\"$PASSWORD\"}" | jq -r '.data.token')

echo "Token: $TOKEN"
```

---

## 1. NOTIFICATIONS (9 APIs)

```bash
# Get all notifications
curl -X GET "$BASE_URL/driver/auth/notifications" \
  -H "Authorization: Bearer $TOKEN"

# Get unread count
curl -X GET "$BASE_URL/driver/auth/notifications/unread-count" \
  -H "Authorization: Bearer $TOKEN"

# Mark as read
curl -X POST "$BASE_URL/driver/auth/notifications/1/read" \
  -H "Authorization: Bearer $TOKEN"

# Mark as unread
curl -X POST "$BASE_URL/driver/auth/notifications/1/unread" \
  -H "Authorization: Bearer $TOKEN"

# Mark all as read
curl -X POST "$BASE_URL/driver/auth/notifications/read-all" \
  -H "Authorization: Bearer $TOKEN"

# Delete notification
curl -X DELETE "$BASE_URL/driver/auth/notifications/1" \
  -H "Authorization: Bearer $TOKEN"

# Clear read notifications
curl -X POST "$BASE_URL/driver/auth/notifications/clear-read" \
  -H "Authorization: Bearer $TOKEN"

# Get settings
curl -X GET "$BASE_URL/driver/auth/notifications/settings" \
  -H "Authorization: Bearer $TOKEN"

# Update settings
curl -X PUT "$BASE_URL/driver/auth/notifications/settings" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"push_enabled":true,"email_enabled":false}'
```

---

## 2. SUPPORT & HELP (10 APIs)

```bash
# Get FAQs
curl -X GET "$BASE_URL/driver/auth/support/faqs" \
  -H "Authorization: Bearer $TOKEN"

# FAQ feedback
curl -X POST "$BASE_URL/driver/auth/support/faqs/1/feedback" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"helpful":true}'

# Get tickets
curl -X GET "$BASE_URL/driver/auth/support/tickets" \
  -H "Authorization: Bearer $TOKEN"

# Create ticket
curl -X POST "$BASE_URL/driver/auth/support/tickets" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"subject":"Issue","message":"Description","category":"technical"}'

# Get ticket details
curl -X GET "$BASE_URL/driver/auth/support/tickets/1" \
  -H "Authorization: Bearer $TOKEN"

# Reply to ticket
curl -X POST "$BASE_URL/driver/auth/support/tickets/1/reply" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"message":"Thank you"}'

# Rate support
curl -X POST "$BASE_URL/driver/auth/support/tickets/1/rate" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"rating":5,"comment":"Excellent"}'

# Submit feedback
curl -X POST "$BASE_URL/driver/auth/support/feedback" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"type":"general","message":"Great app"}'

# Report issue
curl -X POST "$BASE_URL/driver/auth/support/report-issue" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"issue_type":"bug","description":"Bug report"}'

# Get app info
curl -X GET "$BASE_URL/driver/auth/support/app-info" \
  -H "Authorization: Bearer $TOKEN"
```

---

## 3. CONTENT PAGES (5 APIs)

```bash
# Get all pages
curl -X GET "$BASE_URL/driver/auth/pages" \
  -H "Authorization: Bearer $TOKEN"

# Terms & conditions
curl -X GET "$BASE_URL/driver/auth/pages/terms" \
  -H "Authorization: Bearer $TOKEN"

# Privacy policy
curl -X GET "$BASE_URL/driver/auth/pages/privacy" \
  -H "Authorization: Bearer $TOKEN"

# About page
curl -X GET "$BASE_URL/driver/auth/pages/about" \
  -H "Authorization: Bearer $TOKEN"

# Help page
curl -X GET "$BASE_URL/driver/auth/pages/help" \
  -H "Authorization: Bearer $TOKEN"
```

---

## 4. PRIVACY SETTINGS (2 APIs)

```bash
# Get privacy settings
curl -X GET "$BASE_URL/driver/auth/account/privacy-settings" \
  -H "Authorization: Bearer $TOKEN"

# Update privacy settings
curl -X PUT "$BASE_URL/driver/auth/account/privacy-settings" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"location_sharing":true,"activity_status":true}'
```

---

## 5. EMERGENCY CONTACTS (5 APIs)

```bash
# Get emergency contacts
curl -X GET "$BASE_URL/driver/auth/account/emergency-contacts" \
  -H "Authorization: Bearer $TOKEN"

# Create emergency contact
curl -X POST "$BASE_URL/driver/auth/account/emergency-contacts" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"John Doe","phone":"+201234567890","relationship":"friend"}'

# Update emergency contact
curl -X PUT "$BASE_URL/driver/auth/account/emergency-contacts/1" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Jane Doe","phone":"+201234567891"}'

# Delete emergency contact
curl -X DELETE "$BASE_URL/driver/auth/account/emergency-contacts/1" \
  -H "Authorization: Bearer $TOKEN"

# Set primary contact
curl -X POST "$BASE_URL/driver/auth/account/emergency-contacts/1/set-primary" \
  -H "Authorization: Bearer $TOKEN"
```

---

## 6. DASHBOARD (3 APIs)

```bash
# Get dashboard widgets
curl -X GET "$BASE_URL/driver/auth/dashboard/widgets" \
  -H "Authorization: Bearer $TOKEN"

# Get recent activity
curl -X GET "$BASE_URL/driver/auth/dashboard/recent-activity" \
  -H "Authorization: Bearer $TOKEN"

# Get promotional banners
curl -X GET "$BASE_URL/driver/auth/dashboard/promotional-banners" \
  -H "Authorization: Bearer $TOKEN"
```

---

## 7. GAMIFICATION (3 APIs)

```bash
# Get achievements
curl -X GET "$BASE_URL/driver/auth/gamification/achievements" \
  -H "Authorization: Bearer $TOKEN"

# Get badges
curl -X GET "$BASE_URL/driver/auth/gamification/badges" \
  -H "Authorization: Bearer $TOKEN"

# Get progress
curl -X GET "$BASE_URL/driver/auth/gamification/progress" \
  -H "Authorization: Bearer $TOKEN"
```

---

## 8. PROMOTIONS (3 APIs)

```bash
# Get promotions
curl -X GET "$BASE_URL/driver/auth/promotions" \
  -H "Authorization: Bearer $TOKEN"

# Get promotion details
curl -X GET "$BASE_URL/driver/auth/promotions/1" \
  -H "Authorization: Bearer $TOKEN"

# Claim promotion
curl -X POST "$BASE_URL/driver/auth/promotions/1/claim" \
  -H "Authorization: Bearer $TOKEN"
```

---

## 9. READINESS CHECK (1 API)

```bash
# Driver readiness check
curl -X GET "$BASE_URL/driver/auth/readiness-check" \
  -H "Authorization: Bearer $TOKEN"
```

---

## 10. VEHICLE MANAGEMENT (5 APIs)

```bash
# Insurance status
curl -X GET "$BASE_URL/driver/auth/vehicle/insurance-status" \
  -H "Authorization: Bearer $TOKEN"

# Update insurance
curl -X POST "$BASE_URL/driver/auth/vehicle/insurance-update" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"policy_number":"POL123","expiry_date":"2026-12-31"}'

# Inspection status
curl -X GET "$BASE_URL/driver/auth/vehicle/inspection-status" \
  -H "Authorization: Bearer $TOKEN"

# Update inspection
curl -X POST "$BASE_URL/driver/auth/vehicle/inspection-update" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"inspection_date":"2026-01-08","next_due":"2026-07-08"}'

# Get reminders
curl -X GET "$BASE_URL/driver/auth/vehicle/reminders" \
  -H "Authorization: Bearer $TOKEN"
```

---

## 11. DOCUMENTS (2 APIs)

```bash
# Document expiry status
curl -X GET "$BASE_URL/driver/auth/documents/expiry-status" \
  -H "Authorization: Bearer $TOKEN"

# Update document expiry
curl -X POST "$BASE_URL/driver/auth/documents/1/update-expiry" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"expiry_date":"2027-01-08"}'
```

---

## 12. REPORTS (3 APIs)

```bash
# Weekly report
curl -X GET "$BASE_URL/driver/auth/reports/weekly" \
  -H "Authorization: Bearer $TOKEN"

# Monthly report
curl -X GET "$BASE_URL/driver/auth/reports/monthly" \
  -H "Authorization: Bearer $TOKEN"

# Export report
curl -X POST "$BASE_URL/driver/auth/reports/export" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"type":"monthly","format":"pdf"}'
```

---

## Quick Test All Script

```bash
#!/bin/bash
BASE_URL="https://smartline-it.com/api"
TOKEN=$(curl -s -X POST "$BASE_URL/v2/driver/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"phone":"+2011767463164","password":"password123"}' | jq -r '.data.token')

# Test notifications
curl -s "$BASE_URL/driver/auth/notifications" -H "Authorization: Bearer $TOKEN" | jq '.'

# Test dashboard
curl -s "$BASE_URL/driver/auth/dashboard/widgets" -H "Authorization: Bearer $TOKEN" | jq '.'

# Test readiness
curl -s "$BASE_URL/driver/auth/readiness-check" -H "Authorization: Bearer $TOKEN" | jq '.'
```

---

**Test Results:** 52/57 APIs working (91.2% success rate)  
**Full Report:** See `TEST_RESULTS_40_NEW_APIS.md`
