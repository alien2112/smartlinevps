# Offer Management System

## Overview

The Offer Management System provides **automatic discounts** that apply to customers without requiring a coupon code. Offers are applied automatically based on targeting rules (zone, customer level, service type).

This is similar to the old "Discount Setup" system but with production-ready features:
- Atomic operations with race-condition protection
- Priority-based offer selection
- Real-time analytics
- Modern admin dashboard

## Key Differences: Offers vs Coupons

| Feature | Offers | Coupons |
|---------|--------|---------|
| Requires Code | ❌ No | ✅ Yes |
| Applied Automatically | ✅ Yes | ❌ No |
| Targeting Options | Zone, Level, Category | All, Targeted, Segment |
| Priority System | ✅ Yes | ❌ No |
| Reservation Flow | ❌ No | ✅ Yes |

## Quick Start

### 1. Run Migrations

```bash
php artisan migrate
```

### 2. Access Admin Panel

Navigate to: **Promotion Management → Offer Management** in the admin sidebar.

### 3. Create Your First Offer

1. Click "Create Offer"
2. Set title and description
3. Configure discount (percentage/fixed/free ride)
4. Set targeting rules (zones, levels, categories)
5. Set validity period
6. Save and activate

## API Endpoints

### Customer Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/offers` | Get available offers for user |
| POST | `/api/v1/offers/best` | Get best offer for trip context |
| GET | `/api/v1/offers/{id}` | Get offer details |

#### Get Best Offer Request

```json
POST /api/v1/offers/best
{
  "zone_id": "uuid",
  "trip_type": "ride_request",
  "vehicle_category_id": "uuid",
  "fare": 50.00
}
```

#### Response

```json
{
  "has_offer": true,
  "offer": {
    "id": "uuid",
    "title": "20% Off All Rides",
    "discount_type": "percentage",
    "discount_amount": 20,
    "max_discount": 10.00
  },
  "discount_amount": 10.00,
  "original_fare": 50.00,
  "final_fare": 40.00
}
```

### Admin Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/offers` | List all offers |
| POST | `/admin/offers` | Create offer |
| GET | `/admin/offers/{id}` | Get details |
| PUT | `/admin/offers/{id}` | Update |
| DELETE | `/admin/offers/{id}` | Delete |
| POST | `/admin/offers/{id}/toggle` | Toggle status |
| GET | `/admin/offers/{id}/stats` | Statistics |
| GET | `/admin/offers/{id}/usages` | Usage history |

## Discount Types

### Percentage
- Discount is percentage of fare (0-100)
- Optional `max_discount` cap
- Example: 20% off up to 10 EGP max

### Fixed
- Fixed amount discount
- Discount cannot exceed fare
- Example: 5 EGP off

### Free Ride
- 100% discount
- Requires `max_discount` to limit exposure
- Example: Free ride up to 15 EGP

## Targeting Options

### Zone Targeting
- **All**: Available in all zones
- **Selected**: Only in selected zones

### Customer Level Targeting
- **All**: Available to all customer levels
- **Selected**: Only for selected levels (e.g., Gold, Platinum)

### Customer Targeting
- **All**: Available to all customers
- **Selected**: Only for specific customer IDs

### Service Type Targeting
- **All**: All services (ride + parcel)
- **Ride**: Only ride requests
- **Parcel**: Only parcel deliveries
- **Selected**: Specific vehicle categories

## Priority System

When multiple offers match a trip:
1. All applicable offers are collected
2. Discount is calculated for each
3. The offer with the **highest discount** is applied

Priority field (0-255) is used for tie-breaking and display order.

## Offer Lifecycle

```
1. Admin creates offer → Status: Scheduled (if future start date)
2. Start date reached → Status: Active
3. Customer requests trip → System checks applicable offers
4. Best offer selected → Discount applied to fare
5. Trip completed → Usage recorded, stats updated
6. End date reached → Status: Expired (auto-deactivated)
```

## Database Tables

| Table | Description |
|-------|-------------|
| `offers` | Offer definitions and settings |
| `offer_usages` | Usage records per user/trip |

## Admin Web Interface

Access at: `/admin/offer-management`

Features:
- Dashboard with key metrics
- Create/Edit/Delete offers
- Image upload support
- Zone/Level/Category targeting
- Real-time status toggling
- Usage statistics with charts

## Scheduled Tasks

```bash
# Deactivate expired offers (daily at midnight)
php artisan offers:deactivate-expired
```

Already configured in `app/Console/Kernel.php`.

## Integration with Trip Flow

The offer system integrates with trip estimation and completion:

### During Fare Estimation

```php
use Modules\CouponManagement\Service\OfferService;

$offerService = app(OfferService::class);
$result = $offerService->getBestOffer($user, [
    'zone_id' => $zoneId,
    'trip_type' => 'ride_request',
    'vehicle_category_id' => $categoryId,
    'fare' => $estimatedFare,
]);

if ($result) {
    $discountAmount = $result['discount_amount'];
    $offerId = $result['offer_id'];
}
```

### On Trip Completion

```php
$result = $offerService->applyOffer($user, $offerId, $tripId, $finalFare);

if ($result['success']) {
    // Discount applied
    $discountAmount = $result['discount_amount'];
}
```

### On Trip Cancellation

```php
$offerService->cancelOfferUsage($tripId);
```

## Migration from Old System

The old Discount Setup system (`PromotionManagement`) remains functional. Both systems can operate simultaneously.

### Migration Strategy

1. Create equivalent offers in new system
2. Set old discounts to inactive
3. Monitor new system for issues
4. Eventually remove old system

## Error Handling

The `applyOffer` method returns detailed error information:

```php
$result = $offerService->applyOffer(...);

if (!$result['success']) {
    $error = $result['error'];
    // Possible errors:
    // - "Offer not found"
    // - "Offer is inactive"
    // - "Offer has expired"
    // - "Offer limit reached"
    // - "User limit reached"
    // - "Minimum fare not met"
}
```

## Files Created

### New Files
- `Modules/CouponManagement/Database/Migrations/2025_01_02_000001_create_offers_table.php`
- `Modules/CouponManagement/Database/Migrations/2025_01_02_000002_create_offer_usages_table.php`
- `Modules/CouponManagement/Entities/Offer.php`
- `Modules/CouponManagement/Entities/OfferUsage.php`
- `Modules/CouponManagement/Service/OfferService.php`
- `Modules/CouponManagement/Http/Controllers/Api/Customer/OfferController.php`
- `Modules/CouponManagement/Http/Controllers/Api/Admin/OfferAdminController.php`
- `Modules/CouponManagement/Http/Controllers/Web/Admin/OfferWebController.php`
- `Modules/CouponManagement/Console/DeactivateExpiredOffersCommand.php`
- `Modules/CouponManagement/Resources/views/admin/offers/*.blade.php`

### Modified Files
- `Modules/CouponManagement/Routes/api.php` - Added offer routes
- `Modules/CouponManagement/Routes/web.php` - Added offer admin routes
- `Modules/CouponManagement/Providers/CouponManagementServiceProvider.php` - Registered service
- `Modules/AdminModule/Resources/views/partials/_sidebar.blade.php` - Added menu item
- `app/Console/Kernel.php` - Added scheduled command

## Summary

The new Offer Management System is **production-ready** with:
- ✅ Full CRUD operations
- ✅ Admin web dashboard
- ✅ Customer API
- ✅ Zone/Level/Category targeting
- ✅ Priority-based selection
- ✅ Atomic usage tracking
- ✅ Analytics and statistics
- ✅ Automatic expiration
- ✅ Comprehensive documentation
