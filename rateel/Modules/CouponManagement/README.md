# Coupon Management Module

Production-ready coupon/discount system for the ride-hailing app.

## Features

- **Coupon Types**: PERCENT, FIXED, FREE_RIDE_CAP
- **Constraints**: starts_at/ends_at, min_fare, global_limit, per_user_limit
- **Scope Rules**: city_ids, service_types (JSON)
- **Eligibility**: ALL, TARGETED (user list), SEGMENT (e.g., INACTIVE_30_DAYS)
- **Safe Redemption**: Validate → Reserve → Apply/Cancel
- **Concurrency Safe**: DB transactions + row locking + unique constraints
- **FCM Notifications**: Bulk sending with chunking, retry, invalid token handling

---

## Database Schema

```
coupons
├── id (UUID, PK)
├── code (VARCHAR, UNIQUE)
├── type (ENUM: PERCENT, FIXED, FREE_RIDE_CAP)
├── value, max_discount, min_fare
├── global_limit, per_user_limit, global_used_count
├── starts_at, ends_at
├── allowed_city_ids (JSON)
├── allowed_service_types (JSON)
├── eligibility_type (ENUM: ALL, TARGETED, SEGMENT)
├── segment_key
└── is_active

coupon_target_users
├── coupon_id (FK)
├── user_id (FK)
├── notified, notified_at
└── UNIQUE(coupon_id, user_id)

coupon_redemptions
├── coupon_id, user_id, ride_id
├── idempotency_key
├── status (ENUM: RESERVED, APPLIED, CANCELLED, EXPIRED)
├── estimated_fare, estimated_discount
├── final_fare, final_discount
├── UNIQUE(ride_id)
└── UNIQUE(user_id, idempotency_key)

user_devices
├── user_id (FK)
├── fcm_token (UNIQUE)
├── platform (ENUM: android, ios, web)
├── is_active, failure_count
└── deactivated_at, deactivation_reason
```

---

## API Endpoints

### Customer Endpoints

```
POST /api/v1/coupons/validate
GET  /api/v1/coupons/available
POST /api/v1/devices/register
POST /api/v1/devices/unregister
```

### Admin Endpoints

```
GET    /admin/coupons
POST   /admin/coupons
GET    /admin/coupons/{id}
PUT    /admin/coupons/{id}
DELETE /admin/coupons/{id}
POST   /admin/coupons/{id}/assign-users
POST   /admin/coupons/{id}/broadcast
GET    /admin/coupons/{id}/stats
POST   /admin/coupons/{id}/deactivate
```

---

## Integration with Ride Flow

### 1. Validate Coupon (Optional Preview)

```php
// Customer can preview discount before ride request
POST /api/v1/coupons/validate
{
    "code": "SAVE20",
    "fare": 25.50,
    "city_id": "city-uuid",
    "service_type": "ride"
}

Response:
{
    "valid": true,
    "discount_amount": 5.10,
    "coupon": {
        "code": "SAVE20",
        "type": "PERCENT",
        "value": 20
    },
    "meta": {
        "original_fare": 25.50,
        "discounted_fare": 20.40
    }
}
```

### 2. Reserve Coupon (On Ride Request)

Modify your existing ride request controller to reserve coupon:

```php
// In TripRequestController or RideController

use Modules\CouponManagement\Service\CouponService;

public function createRide(Request $request, CouponService $couponService)
{
    $user = auth('api')->user();

    // Create the ride first
    $ride = TripRequest::create([
        'customer_id' => $user->id,
        'pickup_coordinates' => $request->pickup,
        'dropoff_coordinates' => $request->dropoff,
        // ... other fields
    ]);

    // Reserve coupon if provided
    $couponResult = null;
    if ($request->has('coupon_code') && $request->input('coupon_code')) {
        $couponResult = $couponService->reserveCoupon(
            user: $user,
            code: $request->input('coupon_code'),
            rideId: $ride->id,
            idempotencyKey: $request->input('idempotency_key', $ride->id), // Use ride ID as fallback
            estimateContext: [
                'fare' => $request->input('estimated_fare'),
                'city_id' => $ride->zone_id,
                'service_type' => $ride->type,
            ]
        );

        if (!$couponResult['success']) {
            // Return error but don't fail ride creation
            // App can show coupon error to user
        }
    }

    return response()->json([
        'ride' => $ride,
        'coupon' => $couponResult,
    ]);
}
```

### 3. Apply Coupon (On Ride Completion)

```php
// In your ride completion logic

public function completeRide(TripRequest $ride, CouponService $couponService)
{
    // Complete the ride first
    $ride->update([
        'current_status' => 'completed',
        'paid_fare' => $calculatedFare,
    ]);

    // Apply coupon if reserved
    $discountResult = $couponService->applyCoupon($ride->customer, $ride->id);

    if ($discountResult['success']) {
        // Update invoice/fare with discount
        $finalAmount = $discountResult['discounted_fare'];

        // Update ride or invoice
        $ride->update([
            'discount_amount' => $discountResult['discount_amount'],
            'final_amount' => $finalAmount,
        ]);
    }

    return $ride;
}
```

### 4. Cancel Reservation (On Ride Cancel)

```php
// In your ride cancellation logic

public function cancelRide(TripRequest $ride, CouponService $couponService)
{
    // Cancel coupon reservation
    $couponService->cancelReservation($ride->id);

    // Cancel the ride
    $ride->update(['current_status' => 'cancelled']);
}
```

---

## Node.js Realtime Integration

**IMPORTANT**: The Node.js socket server should NOT calculate discounts. It only passes `coupon_code` to Laravel.

### Node.js Code

```javascript
// socket-server/handlers/rideHandler.js

async function handleRideRequest(socket, payload) {
    const { pickup, dropoff, service_type, coupon_code } = payload;

    // Forward to Laravel API
    const response = await axios.post(`${LARAVEL_API}/api/customer/rides/create`, {
        pickup,
        dropoff,
        service_type,
        coupon_code,  // Just pass it along
        idempotency_key: payload.request_id,
    }, {
        headers: {
            'Authorization': `Bearer ${socket.user.token}`,
        }
    });

    // Laravel returns ride with coupon reservation result
    const { ride, coupon } = response.data;

    // Emit result to customer
    socket.emit('ride_created', {
        ride_id: ride.id,
        status: ride.status,
        coupon_applied: coupon?.success ?? false,
        coupon_error: coupon?.error_message,
        estimated_discount: coupon?.redemption?.estimated_discount,
    });
}
```

### Flow Diagram

```
Customer App                Node.js                  Laravel
     |                         |                         |
     |-- ride_request -------->|                         |
     |   (with coupon_code)    |                         |
     |                         |-- POST /rides/create -->|
     |                         |   (coupon_code)         |
     |                         |                         |-- Reserve coupon
     |                         |                         |-- Create ride
     |                         |<-- Response ------------|
     |<-- ride_created --------|   (ride + coupon_result)|
     |                         |                         |
     |   ... ride in progress ..                         |
     |                         |                         |
     |                         |-- POST /rides/complete->|
     |                         |                         |-- Apply coupon
     |                         |                         |-- Calculate final fare
     |<-- ride_completed ------|<-- Response ------------|
     |   (final_fare, discount)|                         |
```

---

## Error Codes

| Code | Description |
|------|-------------|
| COUPON_NOT_FOUND | Coupon code does not exist |
| COUPON_INACTIVE | Coupon is deactivated |
| COUPON_EXPIRED | Coupon end date has passed |
| COUPON_NOT_STARTED | Coupon start date is in future |
| GLOBAL_LIMIT_REACHED | Coupon usage limit reached |
| USER_LIMIT_REACHED | User has used this coupon max times |
| NOT_ELIGIBLE | User not eligible for this coupon |
| NOT_IN_TARGET_LIST | Coupon is targeted, user not in list |
| SEGMENT_NOT_MATCHED | User doesn't match segment criteria |
| CITY_NOT_ALLOWED | Coupon not valid in this city |
| SERVICE_TYPE_NOT_ALLOWED | Coupon not valid for this service |
| MIN_FARE_NOT_MET | Fare below minimum requirement |
| RIDE_ALREADY_HAS_COUPON | Ride already has a coupon |
| RESERVATION_NOT_FOUND | No reservation for this ride |
| RESERVATION_EXPIRED | Reservation has expired |
| CONCURRENCY_CONFLICT | Race condition, retry |

---

## Scheduled Commands

Add to `app/Console/Kernel.php`:

```php
$schedule->command('coupons:expire-reservations')->everyFiveMinutes();
```

---

## Firebase Configuration

Add to `.env`:

```env
# Option 1: Service account JSON file
FIREBASE_CREDENTIALS_PATH=/path/to/firebase-service-account.json

# Option 2: Individual credentials
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_PRIVATE_KEY_ID=your-key-id
FIREBASE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n"
FIREBASE_CLIENT_EMAIL=firebase-adminsdk@your-project.iam.gserviceaccount.com
FIREBASE_CLIENT_ID=123456789
```

---

## Queue Configuration

The module uses these queues:

- `notifications` - Single user notifications
- `notifications-bulk` - Bulk notification jobs

Configure in `config/queue.php` or use separate workers:

```bash
php artisan queue:work --queue=notifications
php artisan queue:work --queue=notifications-bulk
```

---

## Example Payloads

### Create Coupon (Admin)

```json
POST /admin/coupons
{
    "code": "WELCOME50",
    "name": "Welcome Bonus",
    "description": "50% off your first ride",
    "type": "PERCENT",
    "value": 50,
    "max_discount": 20.00,
    "min_fare": 10.00,
    "global_limit": 1000,
    "per_user_limit": 1,
    "starts_at": "2025-01-01T00:00:00Z",
    "ends_at": "2025-12-31T23:59:59Z",
    "allowed_city_ids": null,
    "allowed_service_types": ["ride", "parcel"],
    "eligibility_type": "SEGMENT",
    "segment_key": "NEW_USER",
    "is_active": true
}
```

### Assign Users (Admin)

```json
POST /admin/coupons/{id}/assign-users
{
    "user_ids": ["uuid-1", "uuid-2", "uuid-3"],
    "notify": true,
    "message_template": "Use code {code} for {value}% off!"
}
```

### Broadcast (Admin)

```json
POST /admin/coupons/{id}/broadcast
{
    "target": "segment",
    "segment_key": "INACTIVE_30_DAYS",
    "message_template": "We miss you! Use {code} for a discount."
}
```
