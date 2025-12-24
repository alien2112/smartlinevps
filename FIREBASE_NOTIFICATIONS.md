# Firebase Push Notifications Guide

## Overview

This document describes the Firebase push notification system used throughout the application.

## Database Setup

### Running the Seeder

To populate all notification templates in the database:

```bash
php artisan db:seed --class=FirebasePushNotificationSeeder
```

This will create/update all notification entries in the `firebase_push_notifications` table.

### Manual Entry via Admin Panel

Notifications can also be managed through:
- Admin Panel → Settings → Firebase Push Notifications

## Notification Categories

### 1. Trip Request Notifications

| Key | Description | When Triggered |
|-----|-------------|----------------|
| `new_ride_request` | New ride available | When customer requests a ride |
| `new_parcel` | New parcel delivery | When customer requests parcel delivery |
| `trip_request_cancelled` | Trip cancelled before acceptance | When trip is cancelled in pending state |

### 2. Driver Acceptance Notifications

| Key | Description | When Triggered |
|-----|-------------|----------------|
| `driver_is_on_the_way` | Driver heading to pickup | When driver accepts trip |
| `driver_assigned` | Driver assigned to trip | When driver is matched |
| `ride_is_started` | Another driver accepted | Notify other drivers trip was taken |
| `driver_after_bid_trip_rejected` | Driver cancelled after bidding | Driver rejects after placing bid |

### 3. Trip Status Notifications

| Key | Description | When Triggered |
|-----|-------------|----------------|
| `trip_started` | Trip has started | Driver enters OTP, trip begins |
| `ride_accepted` | Driver accepted ride | Driver clicks accept |
| `ride_ongoing` | Ride is ongoing | Trip status changed to ongoing |
| `ride_completed` | Ride finished | Driver/customer completes trip |
| `ride_cancelled` | Ride cancelled | Trip cancelled by driver/customer |

### 4. Parcel Notifications

| Key | Description | When Triggered |
|-----|-------------|----------------|
| `parcel_cancelled` | Parcel delivery cancelled | Parcel trip cancelled |
| `parcel_returned` | Parcel returned to sender | Driver returns parcel |
| `parcel_returning_otp` | OTP for parcel return | Parcel return process initiated |

### 5. Payment Notifications

| Key | Description | When Triggered |
|-----|-------------|----------------|
| `payment_successful` | Payment completed | Payment processed successfully |
| `tips_from_customer` | Tips received | Customer adds tip to payment |

### 6. Bidding Notifications

| Key | Description | When Triggered |
|-----|-------------|----------------|
| `received_new_bid` | New bid received | Driver places bid on trip |
| `bid_accepted` | Your bid accepted | Customer accepts driver bid |
| `customer_bid_rejected` | Bid rejected | Customer rejects bid |
| `driver_cancel_ride_request` | Driver cancelled | Driver cancels after bidding |

### 7. Review Notifications

| Key | Description | When Triggered |
|-----|-------------|----------------|
| `review_from_driver` | Driver reviewed you | Driver submits review |
| `review_from_customer` | Customer reviewed you | Customer submits review |

### 8. Other Notifications

| Key | Description | When Triggered |
|-----|-------------|----------------|
| `referral_reward_received` | Referral reward earned | Referral bonus awarded |
| `vehicle_approved` | Vehicle approved | Admin approves driver vehicle |
| `coupon_applied` | Coupon applied | Discount coupon applied |
| `trip_paused` | Trip paused | Driver pauses trip |
| `trip_resumed` | Trip resumed | Driver resumes trip |
| `lost_item_no_response` | Lost item closed | No response on lost item |
| `trip_otp` | Trip OTP sent | OTP sent to customer |
| `otp_matched` | OTP verified | Customer OTP verified |

## Notification Variables

Some notifications support dynamic variables:

| Notification | Variables | Example |
|-------------|-----------|---------|
| `parcel_returning_otp` | `{otp}` | "OTP for return: 1234" |
| `referral_reward_received` | `{referralRewardAmount}` | "Reward: $10.00" |
| `trip_otp` | `{otp}` | "Your OTP: 5678" |

## Usage in Code

### Basic Usage

```php
$push = getNotification('ride_completed');

sendDeviceNotification(
    fcm_token: $user->fcm_token,
    title: translate($push['title']),
    description: translate($push['description']),
    status: $push['status'],
    ride_request_id: $tripId,
    type: $tripType,
    action: 'ride_completed',
    user_id: $userId
);
```

### With Variable Replacement

```php
$push = getNotification('referral_reward_received');

$description = translate(
    textVariableDataFormat(
        value: $push['description'],
        referralRewardAmount: getCurrencyFormat($amount)
    )
);
```

## Error Handling

### Missing Notifications

If a notification key is missing from the database:

1. **Log Warning**: A warning is logged with the missing key
2. **Safe Defaults**: Returns empty notification that won't be sent (status = false)
3. **No Crash**: Application continues normally

Example log output:
```
[WARNING] Firebase notification key not found in database
Key: ride_accepted
Trace: [caller stack trace]
```

### Resolution

1. Check logs: `storage/logs/laravel.log`
2. Run seeder: `php artisan db:seed --class=FirebasePushNotificationSeeder`
3. Or add manually via admin panel

## Database Schema

```sql
CREATE TABLE firebase_push_notifications (
    id BIGINT UNSIGNED PRIMARY KEY,
    name VARCHAR(255) UNIQUE,     -- Notification key
    value TEXT,                     -- Notification message
    status TINYINT(1),             -- 1 = active, 0 = inactive
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

## Testing Notifications

### Test Single Notification

```bash
php artisan tinker

# Test notification retrieval
$notif = getNotification('ride_completed');
dd($notif);
```

### Check Missing Notifications

```bash
# Monitor logs in real-time
tail -f storage/logs/laravel.log | grep "notification key not found"
```

## Maintenance

### Adding New Notifications

1. Add to seeder: `database/seeders/FirebasePushNotificationSeeder.php`
2. Run seeder: `php artisan db:seed --class=FirebasePushNotificationSeeder`
3. Update this documentation

### Updating Notification Messages

Either:
- Via Admin Panel: Settings → Firebase Push Notifications
- Or update seeder and re-run

## Troubleshooting

### Notifications Not Sending

1. **Check notification exists**:
   ```sql
   SELECT * FROM firebase_push_notifications WHERE name = 'your_key';
   ```

2. **Check status is active**:
   ```sql
   UPDATE firebase_push_notifications SET status = 1 WHERE name = 'your_key';
   ```

3. **Check logs**:
   ```bash
   grep "notification key not found" storage/logs/laravel.log
   ```

### Missing Keys After Deployment

Run the seeder on production:
```bash
php artisan db:seed --class=FirebasePushNotificationSeeder --force
```

## Best Practices

1. ✅ **Always check logs** after deployment for missing notification keys
2. ✅ **Run seeder** as part of deployment process
3. ✅ **Use descriptive keys** following pattern: `entity_action` (e.g., `ride_completed`)
4. ✅ **Keep messages concise** - mobile notifications have character limits
5. ✅ **Test notification flow** before deploying new notification types

## Related Files

- Helper Function: `app/Lib/Helpers.php` (getNotification function)
- Seeder: `database/seeders/FirebasePushNotificationSeeder.php`
- Model: `Modules/BusinessManagement/Entities/FirebasePushNotification.php`
