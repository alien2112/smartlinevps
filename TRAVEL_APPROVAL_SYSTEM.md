# Travel Approval System - Enterprise-Grade Implementation

## Overview

This document describes the **enterprise-grade Travel Approval System** implemented for the Rateel ride-hailing application. The system ensures that Travel mode is **permission-based**, not self-activated, following the same model used by Uber Intercity, Careem Travel, and Bolt Long Trip.

## Business Rules

### Category Hierarchy
```
Budget → Pro → VIP → Travel (requires approval)
```

### Travel Activation Flow
```
VIP Driver → Request Travel → Admin Approval → Travel Enabled
```

A driver selecting **"Travel"** does NOT become travel-enabled instantly. They submit an **application** that must be approved by an admin.

---

## Database Schema

### New Fields Added to `driver_details` Table

| Column | Type | Description |
|--------|------|-------------|
| `travel_status` | ENUM('none', 'requested', 'approved', 'rejected') | Travel privilege status |
| `travel_requested_at` | DATETIME | When driver submitted travel application |
| `travel_approved_at` | DATETIME | When admin approved travel privilege |
| `travel_rejected_at` | DATETIME | When admin rejected travel application |
| `travel_processed_by` | BIGINT | Admin user ID who approved/rejected |
| `travel_rejection_reason` | VARCHAR(255) | Reason for rejection, shown to driver |

### Index
- `idx_travel_approval` on (`travel_status`, `travel_approved_at`)

---

## API Endpoints

### Driver App APIs

#### 1. Select Vehicle with Travel Request
```
POST /api/driver/select-vehicle
```

**Request:**
```json
{
  "category_id": "uuid-vip-category",
  "request_travel": true
}
```

**Response (travel requested):**
```json
{
  "response_code": "travel_requested_201",
  "message": "Travel request submitted successfully. Pending admin approval.",
  "data": {
    "category_id": "uuid",
    "category_name": "VIP",
    "travel_status": "requested",
    "travel_enabled": false,
    "travel_requested_at": "2025-12-30T20:30:00Z",
    "can_request_travel": false
  }
}
```

#### 2. Get Travel Status
```
GET /api/driver/travel-status
```

**Response:**
```json
{
  "vehicle_category_id": "uuid",
  "vehicle_category_name": "VIP",
  "vehicle_category_level": 3,
  "is_vip": true,
  "travel_status": "requested",
  "travel_enabled": false,
  "travel_requested_at": "2025-12-30T20:30:00Z",
  "can_request_travel": false
}
```

#### 3. Request Travel Privilege (Standalone)
```
POST /api/driver/request-travel
```

#### 4. Cancel Travel Request
```
POST /api/driver/cancel-travel-request
```

---

## Admin Panel

### Routes
| Method | URL | Description |
|--------|-----|-------------|
| GET | `/admin/driver/travel-approval` | List travel requests |
| POST | `/admin/driver/travel-approval/approve/{id}` | Approve travel request |
| POST | `/admin/driver/travel-approval/reject/{id}` | Reject travel request |
| POST | `/admin/driver/travel-approval/revoke/{id}` | Revoke approved privilege |
| POST | `/admin/driver/travel-approval/bulk-approve` | Bulk approve |
| GET | `/admin/driver/travel-approval/statistics` | Dashboard stats |

### Admin View Features
- Statistics cards (Pending, Approved, Rejected counts)
- Filter tabs by status
- Table with driver info, vehicle, category, status, request date
- Action buttons: Approve, Reject (with reason), Revoke (with reason)
- Modals for rejection/revocation reasons
- Push notifications sent on status change

---

## Dispatch Logic (Critical)

### Travel Orders ONLY Go To:
```php
vehicle_type = VIP AND travel_status = 'approved'
```

### Never Dispatched To:
- `travel_status = 'requested'` (pending approval)
- `travel_status = 'rejected'` (denied)
- `travel_status = 'none'` (never requested)

### Updated in `TravelRideService.php`:
```php
// CRITICAL: Only travel-approved drivers
->where('travel_status', 'approved')
```

---

## Security & Fraud Prevention

| Risk | Prevention |
|------|-----------|
| Unqualified travel drivers | ✅ Admin must verify and approve |
| Fraud | ✅ Approval process with audit trail |
| Bad vehicles | ✅ Admin can review vehicle before approval |
| Customer complaints | ✅ Only vetted drivers receive travel bookings |
| VIP abuse | ✅ Travel is separate from VIP category |

---

## Files Modified/Created

### New Files
1. `database/migrations/2025_12_30_000001_add_travel_approval_to_driver_details.php`
2. `Modules/UserManagement/Http/Controllers/Web/New/Admin/Driver/TravelApprovalController.php`
3. `Modules/UserManagement/Resources/views/admin/driver/travel-approval/index.blade.php`

### Modified Files
1. `Modules/UserManagement/Entities/DriverDetail.php` - Added travel status fields and methods
2. `Modules/UserManagement/Http/Controllers/Api/New/Driver/DriverController.php` - Added API endpoints
3. `Modules/UserManagement/Routes/api.php` - Added API routes
4. `Modules/UserManagement/Routes/web.php` - Added admin routes
5. `Modules/TripManagement/Service/TravelRideService.php` - Updated dispatch logic

---

## Flutter Integration Guide

### 1. Vehicle Selection Screen
When user selects VIP category, show option to request travel:

```dart
// API Call
final response = await http.post(
  Uri.parse('$baseUrl/api/driver/select-vehicle'),
  headers: {'Authorization': 'Bearer $token'},
  body: {
    'category_id': selectedCategoryId,
    'request_travel': requestTravel, // true if travel checkbox selected
  },
);
```

### 2. Show Travel Status in Profile
```dart
// API Call
final response = await http.get(
  Uri.parse('$baseUrl/api/driver/travel-status'),
  headers: {'Authorization': 'Bearer $token'},
);

// Response handling
final data = jsonDecode(response.body);
if (data['travel_status'] == 'requested') {
  // Show "Travel pending approval" badge
} else if (data['travel_enabled'] == true) {
  // Show "Travel enabled" badge
}
```

### 3. Handle Push Notifications
Register handlers for:
- `travel_approved` - Show success message
- `travel_rejected` - Show rejection reason
- `travel_revoked` - Show revocation reason

---

## Run Migration

```bash
php artisan migrate --path=database/migrations/2025_12_30_000001_add_travel_approval_to_driver_details.php
```

---

## Next Steps (Optional Enhancements)

1. **Admin Approval UI in Sidebar** - Add menu item in admin sidebar
2. **DB indexes for dispatch** - Already added `idx_travel_approval`
3. **Travel pricing locked at booking** - Already implemented in TravelRideService
4. **Abuse prevention system** - VIP abuse tracking already in place
5. **Email notifications** - Can add to complement push notifications

---

*This implementation follows enterprise ride-hailing best practices from Uber, Careem, and Bolt.*
