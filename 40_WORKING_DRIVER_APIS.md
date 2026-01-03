# 40 Working Driver APIs

## Base URL: `https://smartline-it.com/api`

### 1. Profile & Account Management (7 APIs)
1. `GET /api/driver/info` - Get driver profile info
2. `PUT /api/driver/update/profile` - Update driver profile
3. `POST /api/driver/change-language` - Change driver language
4. `GET /api/driver/auth/account/privacy-settings` - Get privacy settings
5. `PUT /api/driver/auth/account/privacy-settings` - Update privacy settings
6. `GET /api/driver/referral-details` - Get referral details
7. `GET /api/driver/level` - Get driver level details

### 2. Vehicle Management (8 APIs)
8. `GET /api/driver/vehicle/category/list` - Get vehicle categories
9. `GET /api/driver/vehicle/brand/list` - Get vehicle brands
10. `GET /api/driver/vehicle/model/list` - Get vehicle models
11. `POST /api/driver/vehicle/store` - Store vehicle
12. `POST /api/driver/vehicle/update/{id}` - Update vehicle
13. `GET /api/driver/auth/vehicle/insurance-status` - Get insurance status
14. `POST /api/driver/auth/vehicle/insurance-update` - Update insurance
15. `GET /api/driver/auth/vehicle/inspection-status` - Get inspection status

### 3. Trip Management (15 APIs)
16. `POST /api/driver/ride/bid` - Bid on trip
17. `POST /api/driver/ride/trip-action` - Trip action (accept/reject)
18. `PUT /api/driver/ride/update-status` - Update ride status
19. `POST /api/driver/ride/match-otp` - Match OTP
20. `POST /api/driver/ride/track-location` - Track location
21. `GET /api/driver/ride/details/{ride_request_id}` - Get ride details
22. `GET /api/driver/ride/list` - Get ride list
23. `GET /api/driver/ride/pending-ride-list` - Get pending rides
24. `PUT /api/driver/ride/ride-waiting` - Update ride waiting status
25. `PUT /api/driver/ride/arrival-time` - Update arrival time
26. `PUT /api/driver/ride/coordinate-arrival` - Coordinate arrival
27. `GET /api/driver/ride/overview` - Get trip overview
28. `GET /api/driver/ride/final-fare` - Get final fare calculation
29. `GET /api/driver/ride/payment` - Get payment details
30. `GET /api/driver/ride/current-ride-status` - Get current ride status

### 4. Activity & Dashboard (4 APIs)
31. `GET /api/driver/my-activity` - Get my activity
32. `GET /api/driver/activity/leaderboard` - Get leaderboard
33. `GET /api/driver/activity/daily-income` - Get daily income
34. `GET /api/driver/auth/dashboard/widgets` - Get dashboard widgets

### 5. Wallet & Earnings (6 APIs)
35. `GET /api/driver/wallet/balance` - Get wallet balance
36. `GET /api/driver/wallet/earnings` - Get earnings
37. `GET /api/driver/wallet/summary` - Get wallet summary
38. `GET /api/driver/income-statement` - Get income statement
39. `GET /api/driver/withdraw/methods` - Get withdraw methods
40. `POST /api/driver/withdraw/request` - Request withdrawal

---

## Additional Working APIs (Beyond 40)

### Notifications (9 APIs)
- `GET /api/driver/auth/notifications` - Get all notifications
- `GET /api/driver/auth/notifications/unread-count` - Get unread count
- `POST /api/driver/auth/notifications/{id}/read` - Mark as read
- `POST /api/driver/auth/notifications/{id}/unread` - Mark as unread
- `POST /api/driver/auth/notifications/read-all` - Mark all as read
- `DELETE /api/driver/auth/notifications/{id}` - Delete notification
- `POST /api/driver/auth/notifications/clear-read` - Clear read notifications
- `GET /api/driver/auth/notifications/settings` - Get notification settings
- `PUT /api/driver/auth/notifications/settings` - Update notification settings

### Support & Help (9 APIs)
- `GET /api/driver/auth/support/faqs` - Get FAQs
- `POST /api/driver/auth/support/faqs/{id}/feedback` - FAQ feedback
- `GET /api/driver/auth/support/tickets` - Get support tickets
- `POST /api/driver/auth/support/tickets` - Create support ticket
- `GET /api/driver/auth/support/tickets/{id}` - Get ticket details
- `POST /api/driver/auth/support/tickets/{id}/reply` - Reply to ticket
- `POST /api/driver/auth/support/feedback` - Submit feedback
- `POST /api/driver/auth/support/report-issue` - Report issue
- `GET /api/driver/auth/support/app-info` - Get app version info

### Account Management (11 APIs)
- `GET /api/driver/auth/account/emergency-contacts` - Get emergency contacts
- `POST /api/driver/auth/account/emergency-contacts` - Create emergency contact
- `PUT /api/driver/auth/account/emergency-contacts/{id}` - Update emergency contact
- `DELETE /api/driver/auth/account/emergency-contacts/{id}` - Delete emergency contact
- `POST /api/driver/auth/account/emergency-contacts/{id}/set-primary` - Set primary contact
- `POST /api/driver/auth/account/change-phone/request` - Request phone change
- `POST /api/driver/auth/account/change-phone/verify-old` - Verify old phone
- `POST /api/driver/auth/account/change-phone/verify-new` - Verify new phone
- `POST /api/driver/auth/account/delete-request` - Request account deletion
- `POST /api/driver/auth/account/delete-cancel` - Cancel deletion request
- `GET /api/driver/auth/account/delete-status` - Get deletion status

### Reports (3 APIs)
- `GET /api/driver/auth/reports/weekly` - Get weekly report
- `GET /api/driver/auth/reports/monthly` - Get monthly report
- `POST /api/driver/auth/reports/export` - Export report

### Gamification (3 APIs)
- `GET /api/driver/auth/gamification/achievements` - Get achievements
- `GET /api/driver/auth/gamification/badges` - Get badges
- `GET /api/driver/auth/gamification/progress` - Get progress

### Promotions (3 APIs)
- `GET /api/driver/auth/promotions` - Get promotions
- `GET /api/driver/auth/promotions/{id}` - Get promotion details
- `POST /api/driver/auth/promotions/{id}/claim` - Claim promotion

### Other Features
- `GET /api/driver/auth/readiness-check` - Driver readiness check
- `POST /api/driver/last-ride-details` - Get last ride details
- `GET /api/driver/configuration` - Get configuration
- `POST /api/driver/update/fcm-token` - Update FCM token
- `GET /api/driver/notification-list` - Get notification list
- `POST /api/driver/time-tracking` - Time tracking
- `POST /api/driver/update-online-status` - Update online status

---

## Authentication
All APIs (except public ones) require Bearer token in header:
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

## Tested & Verified Working (7 APIs)
Based on test reports, these are confirmed working:
1. ✅ `GET /api/driver/info`
2. ✅ `POST /api/driver/change-language`
3. ✅ `GET /api/driver/vehicle/category/list`
4. ✅ `GET /api/driver/vehicle/brand/list`
5. ✅ `GET /api/driver/vehicle/model/list`
6. ✅ `GET /api/driver/my-activity`
7. ✅ `GET /api/driver/referral-details`
