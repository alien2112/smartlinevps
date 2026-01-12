# Safety Alert API Endpoints Test Report

## Endpoints Overview

### 1. `/api/driver/config/safety-alert-reason-list`
**Purpose:** Get list of safety alert reasons for drivers (displayed in bottom sheet)

**Method:** GET

**Authentication:** Not required (public config endpoint)

**Response Example:**
```json
{
  "response_code": "default_200",
  "message": "Success",
  "content": [
    {"reason": "أشعر بالخطر"},
    {"reason": "سلوك مشبوه من الراكب"},
    {"reason": "تهديد أو تحرش"},
    {"reason": "حادث أو عطل في السيارة"},
    {"reason": "موقف طارئ آخر"}
  ]
}
```

**Implementation Status:** ✅ Working
- Controller: `Modules/BusinessManagement/Http/Controllers/Api/New/Driver/ConfigController.php:1231`
- Service: Uses `SafetyAlertReasonService`
- Database: `safety_alert_reasons` table (10 reasons seeded - 5 for driver, 5 for customer)
- Feature enabled via: `business_settings.safety_alert_reasons_status = 1`

---

### 2. `/api/driver/config/other-emergency-contact-list`
**Purpose:** Get emergency contact numbers (police, ambulance, etc.)

**Method:** GET

**Authentication:** Not required (public config endpoint)

**Response Example:**
```json
{
  "response_code": "default_200",
  "message": "Success",
  "content": [
    {
      "title": "الشرطه",
      "number": "+201039675685425"
    }
  ]
}
```

**Implementation Status:** ✅ Working
- Controller: `Modules/BusinessManagement/Http/Controllers/Api/New/Driver/ConfigController.php:1221`
- Configuration: `business_settings.emergency_other_numbers_for_call`
- Feature enabled via: `business_settings.emergency_number_for_call_status = 1`

---

### 3. `/api/driver/safety-alert/store`
**Purpose:** Create safety alert when driver presses "Send" button

**Method:** POST

**Authentication:** Required (`auth:api` + `driver.approved` middleware)

**Request Body:**
```json
{
  "trip_request_id": "uuid-of-current-trip",
  "lat": 30.0444,
  "lng": 31.2357,
  "reason": "أشعر بالخطر",
  "comment": "Optional comment"
}
```

**Validation Rules:**
- `trip_request_id`: required, uuid
- `lat`: required
- `lng`: required
- `reason`: optional
- `comment`: optional

**Response (Success - 200):**
```json
{
  "response_code": "safety_alert_store_200",
  "message": "Safety alert stored successfully",
  "content": {
    "id": "uuid",
    "trip_request_id": "uuid",
    "sent_by": "driver-uuid",
    "reason": "أشعر بالخطر",
    "comment": null,
    "alert_location": {"lat": 30.0444, "lng": 31.2357},
    "number_of_alert": 1,
    "status": "pending",
    "trip": {...}
  }
}
```

**Response (Already Exists - 403):**
```json
{
  "response_code": "safety_alert_already_exist_400",
  "message": "Safety alert already exists for this trip"
}
```

**Implementation Status:** ✅ Working
- Controller: `Modules/TripManagement/Http/Controllers/Api/New/Driver/SafetyAlertController.php:25`
- Service: `SafetyAlertService`
- Database: `safety_alerts` table
- **Notifications:** 
  - Sends Firebase topic notification to `admin_safety_alert_notification`
  - Admin panel receives real-time alert
- **Business Logic:**
  - One safety alert per trip per user
  - Driver can resend alert (increments `number_of_alert`)
  - Driver can mark as solved
  - Driver can undo (delete) alert

---

## Additional Endpoints

### `/api/driver/safety-alert/resend/{trip_request_id}`
**Method:** PUT
**Purpose:** Resend safety alert (increases number_of_alert counter)

### `/api/driver/safety-alert/mark-as-solved/{trip_request_id}`
**Method:** PUT
**Purpose:** Driver marks their safety alert as resolved

### `/api/driver/safety-alert/show/{trip_request_id}`
**Method:** GET
**Purpose:** Get safety alert details for specific trip

### `/api/driver/safety-alert/undo/{trip_request_id}`
**Method:** DELETE
**Purpose:** Delete/undo safety alert

---

## Database Schema

### `safety_alert_reasons` Table
```sql
- id (char 36)
- reason (varchar 255) - Alert reason text
- reason_for_whom (varchar 255) - 'driver' or 'customer'
- is_active (tinyint 1)
- created_at, updated_at
```

### `safety_alerts` Table
```sql
- id (char 36)
- trip_request_id (char 36)
- sent_by (char 36) - User who sent alert
- reason (longtext) - Selected reason
- comment (text) - Optional comment
- alert_location (text) - JSON with lat/lng
- resolved_location (text) - JSON with lat/lng when resolved
- number_of_alert (int) - How many times resent
- resolved_by (char 36) - Who resolved it
- trip_status_when_make_alert (varchar 255)
- status (varchar 255) - 'pending' or 'resolved'
- created_at, updated_at
```

---

## Configuration Settings

All settings in `business_settings` table:

| Setting Key | Value | Purpose |
|------------|-------|---------|
| `safety_feature_status` | `"1"` | Enable/disable entire safety feature |
| `safety_alert_reasons_status` | `"1"` | Enable/disable safety alert reasons |
| `emergency_number_for_call_status` | `"1"` | Enable/disable emergency contacts |
| `emergency_govt_number_type` | `"phone"` | Type of government number |
| `emergency_govt_number_for_call` | `"+201068321456"` | Government emergency number |
| `emergency_other_numbers_for_call` | `[{...}]` | Array of emergency contacts |

---

## Testing Commands

### Test Endpoint 1: Safety Alert Reasons
```bash
curl -X GET "https://smartline-it.com/api/driver/config/safety-alert-reason-list" \
  -H "Accept: application/json"
```

### Test Endpoint 2: Emergency Contacts
```bash
curl -X GET "https://smartline-it.com/api/driver/config/other-emergency-contact-list" \
  -H "Accept: application/json"
```

### Test Endpoint 3: Store Safety Alert
```bash
curl -X POST "https://smartline-it.com/api/driver/safety-alert/store" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_DRIVER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "trip_request_id": "CURRENT_TRIP_UUID",
    "lat": 30.0444,
    "lng": 31.2357,
    "reason": "أشعر بالخطر",
    "comment": "test alert"
  }'
```

---

## Summary

✅ **All 3 endpoints are implemented and working**
✅ **Database tables created and seeded with default data**
✅ **Feature flags configured and enabled**
✅ **Real-time notifications to admin via Firebase**
✅ **Complete CRUD operations for safety alerts**

**Seeded Data:**
- 5 safety alert reasons for drivers (Arabic)
- 5 safety alert reasons for customers (Arabic)
- 1 emergency contact (الشرطه)
- All safety features enabled

**Routes:**
- Customer routes: `/api/customer/config/*` and `/api/customer/safety-alert/*`
- Driver routes: `/api/driver/config/*` and `/api/driver/safety-alert/*`

