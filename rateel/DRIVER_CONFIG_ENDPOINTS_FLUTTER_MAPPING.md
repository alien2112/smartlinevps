# Flutter Driver App - Config Endpoints Mapping

## Overview
The `/api/driver/config` endpoint has been split into smaller endpoints to reduce initial load time from ~15KB to ~2.5KB (83% reduction).

---

## Endpoints & Usage

### 1. `/api/driver/config/core` (~2.5 KB)
**When:** App startup (SplashScreen) - REQUIRED, blocks until loaded

| Data | Used By |
|------|---------|
| `maintenance_mode` | `splash_screen.dart`, `maintainance_screen.dart` |
| `logo`, `business_name` | App branding everywhere |
| `app_minimum_version_*` | `app_version_warning_screen.dart` |
| `image_base_url.*` | All image widgets |
| `currency_*` | All price/earning displays |
| `websocket_*` | Pusher/real-time connection |
| `fuel_types` | Vehicle fuel type selection |

---

### 2. `/api/driver/config/auth` (~300 bytes)
**When:** Before login/registration screens

| Data | Used By |
|------|---------|
| `verification` | Phone verification requirement |
| `sms_verification`, `email_verification` | Verification type |
| `facebook_login`, `google_login` | Social login buttons |
| `firebase_otp_verification` | OTP screen |
| `otp_resend_time` | OTP countdown timer |
| `self_registration` | Enable/disable driver signup |
| `referral_earning_status` | Referral input on signup |

---

### 3. `/api/driver/config/trip` (~400 bytes)
**When:** When driver goes online or accepts a trip

| Data | Used By |
|------|---------|
| `bid_on_fare` | Fare bidding UI |
| `required_pin_to_start_trip` | Trip start OTP input |
| `add_intermediate_points` | Multi-stop support |
| `driver_completion_radius` | Trip completion detection |
| `otp_confirmation_for_trip` | Customer OTP verification |
| `review_status` | Rating UI after trip |
| `level_status` | Driver level feature toggle |
| `conversion_status`, `conversion_rate` | Points earning |
| `chatting_setup_status` | In-app chat feature |
| `driver_question_answer_status` | Customer Q&A feature |

---

### 4. `/api/driver/config/loyalty` (~100 bytes)
**When:** My Level screen, Wallet, after trip completion

| Data | Used By |
|------|---------|
| `level_status` | Enable/disable levels feature |
| `conversion_status` | Enable/disable points earning |
| `conversion_rate` | Points per EGP earned |

**Files:**
- `features/my_level/` - Driver level screen
- `features/wallet/` - Points balance
- Trip completion - Points earned display

---

### 5. `/api/driver/config/safety` (~350 bytes)
**When:** Safety screen or during active trip

| Data | Used By |
|------|---------|
| `safety_feature_status` | Feature toggle |
| `safety_feature_emergency_govt_number` | Emergency call button |
| `safety_feature_minimum_trip_delay_time` | Delay detection |
| `after_trip_completed_safety_feature_*` | Post-trip safety check |

---

### 6. `/api/driver/config/parcel` (~250 bytes)
**When:** Driver accepts parcel delivery

| Data | Used By |
|------|---------|
| `parcel_return_time_fee_status` | Return fee enabled |
| `return_time_for_driver` | Return time limit |
| `return_time_type_for_driver` | Time unit (day/hour) |
| `return_fee_for_driver_time_exceed` | Fee amount |
| `maximum_parcel_request_accept_limit_*` | Accept limit |
| `parcel_weight_unit` | Weight display (kg/lb) |

---

### 7. `/api/driver/config/contact` (~200 bytes)
**When:** Support/contact/help screen

| Data | Used By |
|------|---------|
| `business_address` | Office location |
| `business_contact_phone` | Contact number |
| `business_contact_email` | Contact email |
| `business_support_phone` | Support hotline |
| `business_support_email` | Support email |

---

### 8. `/api/driver/pages/{page}` (on-demand)
**When:** User opens legal page

Pages: `about_us`, `privacy_and_policy`, `terms_and_conditions`, `legal`, `refund_policy`

---

## Loading Strategy

```
App Launch
    └── /config/core ✅ (REQUIRED)

On-Demand (Lazy Load):
    ├── /config/auth      → Before login/signup
    ├── /config/trip      → When going online
    ├── /config/loyalty   → My Level / Wallet screens
    ├── /config/safety    → Safety screen
    ├── /config/parcel    → Parcel delivery feature
    └── /config/contact   → Support screen
```

---

## Flutter Implementation Notes

### Current State
Driver app uses `/api/driver/configuration` (legacy endpoint ~15KB)

### Migration Path

**Option 1: Quick Migration (Minimal Changes)**
```dart
// In app_constants.dart
static const String configUri = '/api/driver/config/core';
```
Update `ConfigModel` to parse only core fields.

**Option 2: Full Lazy Loading (Recommended)**
Create separate services:
- `AuthConfigService` → `/config/auth`
- `TripConfigService` → `/config/trip`
- `LoyaltyConfigService` → `/config/loyalty`
- etc.

### Where to Call Each Endpoint

| Endpoint | Flutter Location |
|----------|-----------------|
| `/config/core` | `splash_screen.dart` on app launch |
| `/config/auth` | `sign_in_screen.dart`, `sign_up_screen.dart` |
| `/config/trip` | `home_screen.dart` when going online |
| `/config/loyalty` | `my_level_screen.dart`, `wallet_screen.dart` |
| `/config/safety` | `safety_screen.dart` |
| `/config/parcel` | `parcel_screen.dart` |
| `/config/contact` | `support_screen.dart`, `contact_screen.dart` |

---

## Backend Implementation

All endpoints are cached for 5 minutes with proper cache headers:
```php
->header('Cache-Control', 'public, max-age=300')
->header('X-Cache-TTL', '300')
```

Legacy endpoint `/api/driver/configuration` still works for backward compatibility.

---

## Comparison: Customer vs Driver Config

| Endpoint | Customer | Driver |
|----------|----------|--------|
| `/config/core` | ✅ | ✅ |
| `/config/auth` | ✅ | ✅ |
| `/config/trip` | ✅ | ✅ |
| `/config/safety` | ✅ | ✅ |
| `/config/parcel` | ✅ | ✅ |
| `/config/contact` | ✅ | ✅ |
| `/config/loyalty` | ✅ | ✅ |
| `/config/external` | ✅ (Mart) | ❌ (N/A) |
