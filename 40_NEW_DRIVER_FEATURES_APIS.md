# 40 New Driver Features APIs (2026)

## Base URL: `https://smartline-it.com/api`
## All routes require: `Authorization: Bearer {token}`

---

## 1. NOTIFICATIONS (9 APIs)

1. `GET /api/driver/auth/notifications` - Get all notifications
2. `GET /api/driver/auth/notifications/unread-count` - Get unread count
3. `POST /api/driver/auth/notifications/{id}/read` - Mark notification as read
4. `POST /api/driver/auth/notifications/{id}/unread` - Mark notification as unread
5. `POST /api/driver/auth/notifications/read-all` - Mark all as read
6. `DELETE /api/driver/auth/notifications/{id}` - Delete notification
7. `POST /api/driver/auth/notifications/clear-read` - Clear read notifications
8. `GET /api/driver/auth/notifications/settings` - Get notification settings
9. `PUT /api/driver/auth/notifications/settings` - Update notification settings

---

## 2. SUPPORT & HELP (9 APIs)

10. `GET /api/driver/auth/support/faqs` - Get FAQs
11. `POST /api/driver/auth/support/faqs/{id}/feedback` - FAQ feedback
12. `GET /api/driver/auth/support/tickets` - Get support tickets
13. `POST /api/driver/auth/support/tickets` - Create support ticket
14. `GET /api/driver/auth/support/tickets/{id}` - Get ticket details
15. `POST /api/driver/auth/support/tickets/{id}/reply` - Reply to ticket
16. `POST /api/driver/auth/support/tickets/{id}/rate` - Rate support
17. `POST /api/driver/auth/support/feedback` - Submit feedback
18. `POST /api/driver/auth/support/report-issue` - Report issue
19. `GET /api/driver/auth/support/app-info` - Get app version info

---

## 3. CONTENT PAGES (5 APIs)

20. `GET /api/driver/auth/pages` - Get all pages
21. `GET /api/driver/auth/pages/terms` - Get terms & conditions
22. `GET /api/driver/auth/pages/privacy` - Get privacy policy
23. `GET /api/driver/auth/pages/about` - Get about page
24. `GET /api/driver/auth/pages/help` - Get help page

---

## 4. ACCOUNT MANAGEMENT - PRIVACY SETTINGS (2 APIs)

25. `GET /api/driver/auth/account/privacy-settings` - Get privacy settings
26. `PUT /api/driver/auth/account/privacy-settings` - Update privacy settings

---

## 5. ACCOUNT MANAGEMENT - EMERGENCY CONTACTS (5 APIs)

27. `GET /api/driver/auth/account/emergency-contacts` - Get emergency contacts
28. `POST /api/driver/auth/account/emergency-contacts` - Create emergency contact
29. `PUT /api/driver/auth/account/emergency-contacts/{id}` - Update emergency contact
30. `DELETE /api/driver/auth/account/emergency-contacts/{id}` - Delete emergency contact
31. `POST /api/driver/auth/account/emergency-contacts/{id}/set-primary` - Set primary contact

---

## 6. ACCOUNT MANAGEMENT - PHONE CHANGE (3 APIs)

32. `POST /api/driver/auth/account/change-phone/request` - Request phone change
33. `POST /api/driver/auth/account/change-phone/verify-old` - Verify old phone
34. `POST /api/driver/auth/account/change-phone/verify-new` - Verify new phone

---

## 7. ACCOUNT MANAGEMENT - ACCOUNT DELETION (3 APIs)

35. `POST /api/driver/auth/account/delete-request` - Request account deletion
36. `POST /api/driver/auth/account/delete-cancel` - Cancel deletion request
37. `GET /api/driver/auth/account/delete-status` - Get deletion status

---

## 8. DASHBOARD & ACTIVITY (3 APIs)

38. `GET /api/driver/auth/dashboard/widgets` - Get dashboard widgets
39. `GET /api/driver/auth/dashboard/recent-activity` - Get recent activity
40. `GET /api/driver/auth/dashboard/promotional-banners` - Get promotional banners

---

## Additional New Features (Beyond 40)

### TRIP REPORTS (3 APIs)
- `GET /api/driver/auth/reports/weekly` - Get weekly report
- `GET /api/driver/auth/reports/monthly` - Get monthly report
- `POST /api/driver/auth/reports/export` - Export report

### VEHICLE MANAGEMENT (5 APIs)
- `GET /api/driver/auth/vehicle/insurance-status` - Get insurance status
- `POST /api/driver/auth/vehicle/insurance-update` - Update insurance
- `GET /api/driver/auth/vehicle/inspection-status` - Get inspection status
- `POST /api/driver/auth/vehicle/inspection-update` - Update inspection
- `GET /api/driver/auth/vehicle/reminders` - Get vehicle reminders

### DOCUMENTS (2 APIs)
- `GET /api/driver/auth/documents/expiry-status` - Get document expiry status
- `POST /api/driver/auth/documents/{id}/update-expiry` - Update document expiry

### GAMIFICATION (3 APIs)
- `GET /api/driver/auth/gamification/achievements` - Get achievements
- `GET /api/driver/auth/gamification/badges` - Get badges
- `GET /api/driver/auth/gamification/progress` - Get progress

### PROMOTIONS & OFFERS (3 APIs)
- `GET /api/driver/auth/promotions` - Get promotions
- `GET /api/driver/auth/promotions/{id}` - Get promotion details
- `POST /api/driver/auth/promotions/{id}/claim` - Claim promotion

### READINESS CHECK (1 API)
- `GET /api/driver/auth/readiness-check` - Driver readiness check

---

## Summary

**Total New Features APIs: 40+**

### Categories:
- ✅ Notifications: 9 APIs
- ✅ Support & Help: 10 APIs
- ✅ Content Pages: 5 APIs
- ✅ Account Management: 13 APIs
- ✅ Dashboard: 3 APIs

**All APIs are under `/api/driver/auth/*` prefix and require authentication.**

---

## Test Status

Based on test reports:
- **7 APIs confirmed working** (from older features)
- **40+ new APIs** defined in `api_driver_new_features.php`
- Most new APIs need controller autoload fixes to work properly
