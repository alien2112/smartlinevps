# 40 New Driver Features APIs - Request & Response Details

**Base URL**: `https://smartline-it.com/api`
**Headers**: `Authorization: Bearer {token}`
**Rate Limits**:
- Default: 60 requests/minute
- Phone Change: 5 requests/15 minutes
- Account Deletion: 3 requests/hour

---

## 1. NOTIFICATIONS

### 1.1 Get All Notifications
**GET** `/api/driver/auth/notifications`

**Query Parameters:**
- `limit` (int, optional): Default 20
- `offset` (int, optional): Default 0
- `status` (string, optional): `all`, `read`, `unread`
- `category` (string, optional): `trips`, `earnings`, `promotions`, `system`, `documents`

**Response:**
```json
{
    "response_code": "default_200",
    "message": "successfully_loaded",
    "content": {
        "notifications": [
            {
                "id": "uuid",
                "type": "trip_request",
                "title": "New Trip Request",
                "message": "You have a new trip request from John",
                "data": { "trip_id": "123" },
                "action_type": "open_trip",
                "action_url": null,
                "is_read": false,
                "read_at": null,
                "priority": "high",
                "category": "trips",
                "created_at": "2024-01-08T10:00:00Z",
                "time_ago": "2 minutes ago"
            }
        ],
        "unread_count": 5,
        "total": 100,
        "limit": 20,
        "offset": 0
    }
}
```

### 1.2 Get Unread Count
**GET** `/api/driver/auth/notifications/unread-count`

**Response:**
```json
{
    "response_code": "default_200",
    "message": "successfully_loaded",
    "content": {
        "unread_count": 5
    }
}
```

### 1.3 Mark Notification as Read
**POST** `/api/driver/auth/notifications/{id}/read`

**Response:**
```json
{
    "response_code": "notification_marked_read_200",
    "message": "Notification marked as read"
}
```

### 1.4 Mark Notification as Unread
**POST** `/api/driver/auth/notifications/{id}/unread`

**Response:**
```json
{
    "response_code": "notification_marked_unread_200",
    "message": "Notification marked as unread"
}
```

### 1.5 Mark All as Read
**POST** `/api/driver/auth/notifications/read-all`

**Response:**
```json
{
    "response_code": "all_notifications_marked_read_200",
    "message": "All notifications marked as read"
}
```

### 1.6 Delete Notification
**DELETE** `/api/driver/auth/notifications/{id}`

**Response:**
```json
{
    "response_code": "notification_deleted_200",
    "message": "Notification deleted"
}
```

### 1.7 Clear Read Notifications
**POST** `/api/driver/auth/notifications/clear-read`

**Response:**
```json
{
    "response_code": "notifications_cleared_200",
    "message": "Read notifications cleared",
    "data": {
        "deleted_count": 15
    }
}
```

### 1.8 Get Notification Settings
**GET** `/api/driver/auth/notifications/settings`

**Response:**
```json
{
    "response_code": "default_200",
    "message": "successfully_loaded",
    "content": {
        "id": "uuid",
        "trip_requests_enabled": true,
        "trip_updates_enabled": true,
        "payment_notifications_enabled": true,
        "promotional_notifications_enabled": true,
        "system_notifications_enabled": true,
        "push_notifications_enabled": true,
        "quiet_hours_enabled": false,
        "quiet_hours_start": "22:00",
        "quiet_hours_end": "07:00"
    }
}
```

### 1.9 Update Notification Settings
**PUT** `/api/driver/auth/notifications/settings`

**Body:**
```json
{
    "trip_requests_enabled": true,
    "quiet_hours_enabled": true,
    "quiet_hours_start": "23:00",
    "quiet_hours_end": "06:00"
}
```

**Response:**
```json
{
    "response_code": "settings_updated_200",
    "message": "Notification settings updated",
    "data": { ...settings_object }
}
```

---

## 2. SUPPORT & HELP

### 2.1 Get FAQs
**GET** `/api/driver/auth/support/faqs`

**Query Parameters:**
- `category` (string, optional): `general`, `trips`, `payments`, `account`, `vehicle`
- `search` (string, optional)

**Response:**
```json
{
    "response_code": "default_200",
    "message": "successfully_loaded",
    "content": {
        "faqs": {
            "general": [
                {
                    "id": "uuid",
                    "question": "How do I start?",
                    "answer": "...",
                    "category": "general"
                }
            ]
        },
        "categories": {
            "general": "General",
            "trips": "Trips & Bookings"
        }
    }
}
```

### 2.2 FAQ Feedback
**POST** `/api/driver/auth/support/faqs/{id}/feedback`

**Body:**
```json
{
    "helpful": true
}
```

**Response:**
```json
{
    "response_code": "feedback_recorded_200",
    "message": "Thank you for your feedback"
}
```

### 2.3 Get Support Tickets
**GET** `/api/driver/auth/support/tickets`

**Query Parameters:**
- `status` (string, optional): `all`, `open`, `in_progress`, `resolved`, `closed`

**Response:**
```json
{
    "response_code": "default_200",
    "message": "successfully_loaded",
    "content": {
        "tickets": [
            {
                "id": "uuid",
                "ticket_number": "#1001",
                "subject": "Payment Issue",
                "status": "open",
                "last_message": "...",
                "has_unread_replies": true
            }
        ],
        "total": 5
    }
}
```

### 2.4 Create Support Ticket
**POST** `/api/driver/auth/support/tickets`

**Body:**
```json
{
    "subject": "App crashing",
    "description": "The app crashes when I open the map",
    "category": "technical",
    "priority": "high"
}
```

**Response:**
```json
{
    "response_code": "ticket_created_201",
    "message": "Support ticket created successfully",
    "data": { "ticket_number": "#1002" }
}
```

### 2.5 Get Ticket Details
**GET** `/api/driver/auth/support/tickets/{id}`

**Response:**
```json
{
    "response_code": "default_200",
    "message": "successfully_loaded",
    "content": {
        "ticket": { ...ticket_details },
        "messages": [
            {
                "id": "uuid",
                "message": "Hello...",
                "is_admin_reply": true,
                "sender_name": "Support Team",
                "attachments": []
            }
        ]
    }
}
```

### 2.6 Reply to Ticket
**POST** `/api/driver/auth/support/tickets/{id}/reply`

**Body (Multipart/Form-Data):**
- `message`: "Here is the screenshot"
- `attachments[]`: (File, optional)

**Response:**
```json
{
    "response_code": "reply_sent_200",
    "message": "Reply sent successfully"
}
```

### 2.7 Rate Support
**POST** `/api/driver/auth/support/tickets/{id}/rate`

**Body:**
```json
{
    "rating": 5,
    "comment": "Great help!"
}
```

**Response:**
```json
{
    "response_code": "rating_submitted_200",
    "message": "Thank you for rating our support"
}
```

### 2.8 Submit General Feedback
**POST** `/api/driver/auth/support/feedback`

**Body:**
```json
{
    "type": "feature_request",
    "subject": "Dark Mode",
    "message": "Please add dark mode",
    "rating": 5
}
```

**Response:**
```json
{
    "response_code": "feedback_submitted_201",
    "message": "Thank you for your feedback"
}
```

### 2.9 Report Issue
**POST** `/api/driver/auth/support/report-issue`

**Body:**
```json
{
    "issue_type": "app_malfunction",
    "description": "GPS not updating",
    "severity": "high",
    "trip_id": "optional-trip-id"
}
```

**Response:**
```json
{
    "response_code": "issue_reported_201",
    "message": "Issue reported successfully"
}
```

### 2.10 Get App Info
**GET** `/api/driver/auth/support/app-info`

**Response:**
```json
{
    "response_code": "default_200",
    "content": {
        "app_name": "SmartLine Driver",
        "app_version": "1.0.0",
        "support_email": "support@smartline.com",
        "support_phone": "+1234567890"
    }
}
```

---

## 3. CONTENT PAGES

### 3.1 Get All Pages
**GET** `/api/driver/auth/pages`

**Response:**
```json
{
    "response_code": "default_200",
    "content": {
        "pages": [
            {
                "slug": "terms-and-conditions",
                "title": "Terms & Conditions",
                "type": "terms"
            }
        ]
    }
}
```

### 3.2 Get Page Content
**GET** `/api/driver/auth/pages/{slug}`

**Response:**
```json
{
    "response_code": "default_200",
    "content": {
        "slug": "terms-and-conditions",
        "title": "Terms & Conditions",
        "content": "<h1>Terms...</h1>"
    }
}
```

---

## 4. ACCOUNT MANAGEMENT

### 4.1 Get Privacy Settings
**GET** `/api/driver/auth/account/privacy-settings`

**Response:**
```json
{
    "response_code": "default_200",
    "content": {
        "show_profile_photo": true,
        "show_phone_number": false,
        "show_in_leaderboard": true
    }
}
```

### 4.2 Update Privacy Settings
**PUT** `/api/driver/auth/account/privacy-settings`

**Body:**
```json
{
    "show_phone_number": true
}
```

**Response:**
```json
{
    "response_code": "settings_updated_200",
    "message": "Privacy settings updated successfully"
}
```

### 4.3 Get Emergency Contacts
**GET** `/api/driver/auth/account/emergency-contacts`

**Response:**
```json
{
    "response_code": "default_200",
    "content": {
        "contacts": [
            {
                "id": "uuid",
                "name": "Jane Doe",
                "relationship": "spouse",
                "phone": "+1234567890",
                "is_primary": true
            }
        ]
    }
}
```

### 4.4 Create Emergency Contact
**POST** `/api/driver/auth/account/emergency-contacts`

**Body:**
```json
{
    "name": "John Doe",
    "relationship": "parent",
    "phone": "+1987654321",
    "is_primary": false
}
```

**Response:**
```json
{
    "response_code": "contact_created_201",
    "message": "Emergency contact added successfully"
}
```

### 4.5 Update Emergency Contact
**PUT** `/api/driver/auth/account/emergency-contacts/{id}`

**Body:**
```json
{
    "phone": "+1122334455"
}
```

**Response:**
```json
{
    "response_code": "contact_updated_200",
    "message": "Emergency contact updated successfully"
}
```

### 4.6 Delete Emergency Contact
**DELETE** `/api/driver/auth/account/emergency-contacts/{id}`

**Response:**
```json
{
    "response_code": "contact_deleted_200",
    "message": "Emergency contact deleted successfully"
}
```

### 4.7 Set Primary Contact
**POST** `/api/driver/auth/account/emergency-contacts/{id}/set-primary`

**Response:**
```json
{
    "response_code": "primary_contact_set_200",
    "message": "Primary contact updated successfully"
}
```

### 4.8 Request Phone Change
**POST** `/api/driver/auth/account/change-phone/request`

**Body:**
```json
{
    "new_phone": "+1555555555",
    "password": "current_password"
}
```

**Response:**
```json
{
    "response_code": "otp_sent_200",
    "message": "OTP sent to your current phone number",
    "data": { "request_id": "uuid" }
}
```

### 4.9 Verify Old Phone
**POST** `/api/driver/auth/account/change-phone/verify-old`

**Body:**
```json
{
    "otp": "123456"
}
```

**Response:**
```json
{
    "response_code": "old_phone_verified_200",
    "message": "Old phone verified. OTP sent to new phone number."
}
```

### 4.10 Verify New Phone
**POST** `/api/driver/auth/account/change-phone/verify-new`

**Body:**
```json
{
    "otp": "654321"
}
```

**Response:**
```json
{
    "response_code": "phone_changed_200",
    "message": "Phone number changed successfully"
}
```

### 4.11 Request Account Deletion
**POST** `/api/driver/auth/account/delete-request`

**Body:**
```json
{
    "reason": "switching_service",
    "password": "current_password"
}
```

**Response:**
```json
{
    "response_code": "deletion_requested_200",
    "message": "Account deletion requested",
    "data": { "grace_period_days": 30 }
}
```

### 4.12 Cancel Deletion
**POST** `/api/driver/auth/account/delete-cancel`

**Response:**
```json
{
    "response_code": "deletion_cancelled_200",
    "message": "Account deletion request cancelled"
}
```

### 4.13 Get Deletion Status
**GET** `/api/driver/auth/account/delete-status`

**Response:**
```json
{
    "response_code": "default_200",
    "content": {
        "has_pending_request": true,
        "days_remaining": 29
    }
}
```

### 4.14 Get Verification Status
**GET** `/api/driver/auth/account/verification`

**Response:**
```json
{
    "response_code": "default_200",
    "content": {
        "overall_status": "verified",
        "account": { "is_active": true, "is_verified": true },
        "vehicle": { "status": "ready" },
        "documents": { "status": "ready" }
    }
}
```

---

## 5. DASHBOARD & ACTIVITY

### 5.1 Get Dashboard Widgets
**GET** `/api/driver/auth/dashboard/widgets`

**Response:**
```json
{
    "response_code": "default_200",
    "content": {
        "today": { "earnings": 150.50, "trips": 5 },
        "weekly": { "earnings": 850.00, "trips": 30 },
        "wallet": { "withdrawable_amount": 500.00 },
        "rating": { "average": 4.8 },
        "notifications": { "unread_count": 2 },
        "reminders": [
            {
                "type": "insurance_expiry",
                "title": "Insurance Expiring Soon",
                "days_remaining": 5
            }
        ]
    }
}
```

### 5.2 Get Recent Activity
**GET** `/api/driver/auth/dashboard/recent-activity`

**Response:**
```json
{
    "response_code": "default_200",
    "content": {
        "activities": [
            {
                "type": "trip",
                "title": "Trip Completed",
                "description": "Fare: $15.00",
                "timestamp": "2024-01-08T15:30:00Z"
            }
        ]
    }
}
```

### 5.3 Get Promotional Banners
**GET** `/api/driver/auth/dashboard/promotional-banners`

**Response:**
```json
{
    "response_code": "default_200",
    "content": {
        "banners": [
            {
                "id": "1",
                "image_url": "https://...",
                "action_type": "link",
                "action_url": "https://..."
            }
        ]
    }
}
```

---

## 6. TRIP REPORTS

### 6.1 Weekly Report
**GET** `/api/driver/auth/reports/weekly`

**Query Parameters:**
- `week_offset` (int): 0 for current, 1 for last week

**Response:**
```json
{
    "response_code": "default_200",
    "content": {
        "summary": {
            "total_trips": 45,
            "total_earnings": 1200.50,
            "completion_rate": 95.5
        },
        "daily_breakdown": [
            { "day_name": "Monday", "trips": 8, "earnings": 200.00 }
        ],
        "insights": {
            "peak_hours": [ { "hour": "08:00 - 09:00", "trips": 12 } ]
        }
    }
}
```

### 6.2 Monthly Report
**GET** `/api/driver/auth/reports/monthly`

**Query Parameters:**
- `month_offset` (int)

**Response:**
```json
{
    "response_code": "default_200",
    "content": {
        "summary": { "total_earnings": 4500.00 },
        "earnings_breakdown": { "net_earnings": 4000.00 },
        "weekly_breakdown": [],
        "comparison": {
            "growth": { "earnings_percentage": 10.5, "trend": "up" }
        }
    }
}
```

### 6.3 Export Report
**POST** `/api/driver/auth/reports/export`

**Body:**
```json
{
    "format": "pdf",
    "start_date": "2024-01-01",
    "end_date": "2024-01-31"
}
```

**Response:**
```json
{
    "response_code": "export_ready_200",
    "data": {
        "download_url": "https://...",
        "file_name": "report.pdf"
    }
}
```

---

## 7. VEHICLE MANAGEMENT

### 7.1 Insurance Status
**GET** `/api/driver/auth/vehicle/insurance-status`

**Response:**
```json
{
    "response_code": "default_200",
    "content": {
        "insurance": {
            "expiry_date": "2024-12-31",
            "status": "valid",
            "days_remaining": 300
        }
    }
}
```

### 7.2 Update Insurance
**POST** `/api/driver/auth/vehicle/insurance-update`

**Body:**
```json
{
    "expiry_date": "2025-12-31",
    "company": "SafeDrive",
    "policy_number": "POL123456"
}
```

**Response:**
```json
{
    "response_code": "insurance_updated_200"
}
```

### 7.3 Inspection Status
**GET** `/api/driver/auth/vehicle/inspection-status`

**Response:**
```json
{
    "response_code": "default_200",
    "content": {
        "inspection": {
            "status": "warning",
            "days_remaining": 15
        }
    }
}
```

### 7.4 Update Inspection
**POST** `/api/driver/auth/vehicle/inspection-update`

**Body:**
```json
{
    "inspection_date": "2024-01-08",
    "next_due_date": "2025-01-08"
}
```

**Response:**
```json
{
    "response_code": "inspection_updated_200"
}
```

### 7.5 Get Reminders
**GET** `/api/driver/auth/vehicle/reminders`

**Response:**
```json
{
    "response_code": "default_200",
    "content": {
        "reminders": [
            {
                "type": "inspection",
                "title": "Inspection Due Soon",
                "priority": "high"
            }
        ]
    }
}
```

---

## 8. DOCUMENTS

### 8.1 Expiry Status
**GET** `/api/driver/auth/documents/expiry-status`

**Response:**
```json
{
    "response_code": "default_200",
    "content": {
        "documents": {
            "expiring_soon": [],
            "expired": []
        },
        "alerts": { "has_expired": false }
    }
}
```

### 8.2 Update Expiry
**POST** `/api/driver/auth/documents/{id}/update-expiry`

**Body:**
```json
{
    "expiry_date": "2025-05-20"
}
```

**Response:**
```json
{
    "response_code": "expiry_updated_200"
}
```

---

## 9. GAMIFICATION

### 9.1 Get Achievements
**GET** `/api/driver/auth/gamification/achievements`

**Response:**
```json
{
    "response_code": "default_200",
    "content": {
        "achievements": {
            "trips": [
                {
                    "title": "First 100 Trips",
                    "is_unlocked": true,
                    "points": 500
                }
            ]
        },
        "summary": { "completion_percentage": 25.5 }
    }
}
```

### 9.2 Get Badges
**GET** `/api/driver/auth/gamification/badges`

**Response:**
```json
{
    "response_code": "default_200",
    "content": {
        "badges": {
            "legendary": [
                { "title": "Top Rated", "is_earned": true }
            ]
        }
    }
}
```

### 9.3 Get Progress
**GET** `/api/driver/auth/gamification/progress`

**Response:**
```json
{
    "response_code": "default_200",
    "content": {
        "level": { "current": 5, "progress_percentage": 80 },
        "points": { "total": 2500 }
    }
}
```

### 9.4 Check Achievements
**POST** `/api/driver/auth/gamification/check-achievements`

**Response:**
```json
{
    "response_code": "default_200",
    "content": {
        "newly_unlocked_achievements": []
    }
}
```

---

## 10. LEADERBOARD

### 10.1 Get Leaderboard
**GET** `/api/driver/auth/leaderboard`

**Query Parameters:**
- `type`: `weekly`, `monthly`, `all_time`
- `category`: `trips`, `earnings`, `rating`, `points`

**Response:**
```json
{
    "response_code": "default_200",
    "content": {
        "leaderboard": [
            { "rank": 1, "name": "John D.", "formatted_value": "150 trips" }
        ],
        "my_position": { "rank": 5, "value": 120 },
        "am_i_in_top": true
    }
}
```

### 10.2 My Rank
**GET** `/api/driver/auth/leaderboard/my-rank`

**Response:**
```json
{
    "response_code": "default_200",
    "content": {
        "rankings": {
            "weekly": { "trips": { "rank": 5 }, "earnings": { "rank": 8 } }
        },
        "summary": { "percentile": 95.5 }
    }
}
```

### 10.3 Nearby Drivers
**GET** `/api/driver/auth/leaderboard/nearby`

**Response:**
```json
{
    "response_code": "default_200",
    "content": {
        "above": [ { "rank": 4, "name": "Alice" } ],
        "my_position": { "rank": 5, "name": "Me" },
        "below": [ { "rank": 6, "name": "Bob" } ]
    }
}
```

---

## 11. PROMOTIONS

### 11.1 List Promotions
**GET** `/api/driver/auth/promotions`

**Response:**
```json
{
    "response_code": "default_200",
    "content": {
        "promotions": [
            {
                "id": "1",
                "title": "Weekend Bonus",
                "can_claim": true,
                "claims_remaining": 50
            }
        ]
    }
}
```

### 11.2 Promotion Details
**GET** `/api/driver/auth/promotions/{id}`

**Response:**
```json
{
    "response_code": "default_200",
    "content": {
        "id": "1",
        "title": "Weekend Bonus",
        "terms_conditions": "...",
        "can_claim": true
    }
}
```

### 11.3 Claim Promotion
**POST** `/api/driver/auth/promotions/{id}/claim`

**Response:**
```json
{
    "response_code": "promotion_claimed_200",
    "message": "Promotion claimed successfully"
}
```

---

## 12. READINESS CHECK

### 12.1 Comprehensive Readiness Check
**GET** `/api/driver/auth/readiness-check`

**Response:**
```json
{
    "response_code": "default_200",
    "content": {
        "ready_status": {
            "overall_status": "ready",
            "can_accept_trips": true,
            "blockers": [],
            "warnings": []
        },
        "account": { "status": "ready" },
        "driver": { "is_online": true },
        "vehicle": { "status": "ready" },
        "documents": { "status": "ready" }
    }
}
```
