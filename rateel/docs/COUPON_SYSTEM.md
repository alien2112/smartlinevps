# Advanced Coupon Management System

## Overview

The new coupon system provides production-ready, enterprise-grade coupon and promotional offer management with the following features:

- **Multiple Coupon Types**: Percentage, Fixed Amount, and Free Ride
- **User Targeting**: All users, targeted users, or user segments
- **Atomic Reservations**: Race-condition safe coupon reservations
- **Push Notifications**: FCM-based coupon broadcasts
- **Analytics**: Detailed redemption statistics and daily breakdowns

## Quick Start

### 1. Run Migrations

```bash
php artisan migrate
```

### 2. Access Admin Panel

Navigate to: **Promotion Management → Advanced Coupons** in the admin sidebar.

### 3. Create Your First Coupon

1. Click "Create Coupon"
2. Enter code (e.g., `WELCOME20`)
3. Set discount type and value
4. Configure eligibility and limits
5. Set validity period
6. Save and activate

## API Endpoints

### Customer Endpoints (v1)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/coupons/available` | Get available coupons for user |
| POST | `/api/v1/coupons/validate` | Validate coupon before ride |

#### Validate Coupon Request

```json
{
  "code": "SAVE20",
  "fare": 50.00,
  "city_id": "zone-uuid",
  "service_type": "ride"
}
```

#### Response

```json
{
  "valid": true,
  "discount_amount": 10.00,
  "coupon": {
    "id": "coupon-uuid",
    "code": "SAVE20",
    "name": "20% Off",
    "type": "PERCENT",
    "value": 20,
    "max_discount": 10.00
  },
  "meta": {
    "original_fare": 50.00,
    "discounted_fare": 40.00
  }
}
```

### Device Registration

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/devices/register` | Register FCM token |
| POST | `/api/v1/devices/unregister` | Remove device token |

### Admin Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/coupons` | List all coupons |
| POST | `/admin/coupons` | Create coupon |
| GET | `/admin/coupons/{id}` | Get coupon details |
| PUT | `/admin/coupons/{id}` | Update coupon |
| DELETE | `/admin/coupons/{id}` | Delete coupon |
| POST | `/admin/coupons/{id}/assign-users` | Add targeted users |
| POST | `/admin/coupons/{id}/broadcast` | Send push notifications |
| GET | `/admin/coupons/{id}/stats` | Get statistics |

## Coupon Types

### PERCENT
- Discount is percentage of fare (0-100)
- Optional `max_discount` cap
- Example: 20% off up to $10 max

### FIXED
- Fixed amount discount
- Discount cannot exceed fare
- Example: $5 off

### FREE_RIDE_CAP
- 100% discount up to a cap
- Requires `max_discount` to limit exposure
- Example: Free ride up to $15

## Eligibility Types

### ALL
All active users can use the coupon.

### TARGETED
Only users in the target list can use.
- Add users via admin panel or API
- Supports notifications when added

### SEGMENT
Automatic targeting based on user behavior:

| Segment Key | Description |
|-------------|-------------|
| `NEW_USER` | Registered in last 7 days |
| `INACTIVE_30_DAYS` | No completed rides in 30 days |
| `HIGH_VALUE` | 10+ completed rides |

## Coupon Lifecycle

```
1. Customer validates coupon → VALIDATED
2. Ride requested with coupon → RESERVED (locked for 30 min)
3. Ride completed → APPLIED (discount deducted)
   or
   Ride cancelled → RELEASED (coupon available again)
   or
   Reservation timeout → EXPIRED (coupon available again)
```

## Environment Configuration

Add to `.env`:

```env
# Firebase Configuration (for push notifications)
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_CREDENTIALS_PATH=/path/to/service-account.json
# OR individual credentials:
FIREBASE_PRIVATE_KEY_ID=xxx
FIREBASE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----"
FIREBASE_CLIENT_EMAIL=firebase-adminsdk@xxx.iam.gserviceaccount.com
FIREBASE_CLIENT_ID=123456789
```

## Queue Configuration

The system uses these queues:
- `notifications` - Individual notifications
- `notifications-bulk` - Bulk broadcast jobs

Ensure workers are running:

```bash
php artisan queue:work --queue=notifications,notifications-bulk
```

## Scheduled Tasks

The scheduler automatically:
- Expires stale reservations every 5 minutes
- Releases locked coupons back to pool

Add to crontab:
```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

## Database Tables

| Table | Description |
|-------|-------------|
| `coupons` | Coupon definitions |
| `coupon_target_users` | Targeted user assignments |
| `coupon_redemptions` | Usage records |
| `user_devices` | FCM tokens for notifications |

## Security Features

### Atomic Reservations
- Database locking prevents race conditions
- Idempotency keys prevent duplicate reservations
- Global limits enforced atomically

### Fraud Prevention
- Per-user usage limits
- City/service type restrictions
- Date range validation
- Segment eligibility checks

## Admin Web Interface

Access at: `/admin/coupon-management`

Features:
- Dashboard with key metrics
- Create/Edit/Delete coupons
- Manage targeted users
- View redemption statistics
- Toggle coupon status
- Send broadcast notifications

## Troubleshooting

### Coupon Not Validating

1. Check `is_active` is true
2. Verify date range (starts_at/ends_at)
3. Check global limit not reached
4. Verify user eligibility (TARGETED/SEGMENT)
5. Check city and service type restrictions

### Notifications Not Sending

1. Verify Firebase credentials in .env
2. Check queue workers are running
3. Check `user_devices` table has active tokens
4. Review logs in `storage/logs/laravel.log`

### Missing Coupons in Flutter

1. Ensure `/api/v1/coupons/available` returns data
2. Check user is authenticated
3. Verify coupon eligibility for user

## Migration from Old System

The old coupon system in `PromotionManagement` module remains functional.
Both systems can operate simultaneously.

To migrate:
1. Use new system for new coupons
2. Let old coupons expire naturally
3. Eventually disable old system

## API Error Codes

| Code | Message |
|------|---------|
| `COUPON_NOT_FOUND` | Coupon code does not exist |
| `COUPON_INACTIVE` | Coupon is disabled |
| `COUPON_EXPIRED` | Past end date |
| `COUPON_NOT_STARTED` | Before start date |
| `GLOBAL_LIMIT_REACHED` | No more uses available |
| `USER_LIMIT_REACHED` | User exceeded their limit |
| `NOT_ELIGIBLE` | User not eligible |
| `NOT_IN_TARGET_LIST` | User not in targeted list |
| `SEGMENT_NOT_MATCHED` | User not in segment |
| `CITY_NOT_ALLOWED` | City restricted |
| `SERVICE_TYPE_NOT_ALLOWED` | Service type restricted |
| `MIN_FARE_NOT_MET` | Fare too low |

## Support

For issues, check:
1. Laravel logs: `storage/logs/laravel.log`
2. Queue failed jobs: `php artisan queue:failed`
3. Coupon expire logs: `storage/logs/coupon-expire.log`
