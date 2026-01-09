# Flutter User App - Config Endpoints Mapping

## Overview
The `/api/customer/config` endpoint has been split into smaller endpoints to reduce initial load time from ~15KB to ~2.8KB (80% reduction).

---

## Endpoints & Usage

### 1. `/api/customer/config/core` (~2.8 KB)
**When:** App startup (SplashScreen) - REQUIRED, blocks until loaded

| Data | Used By |
|------|---------|
| `maintenance_mode` | `splash_screen.dart`, `maintainance_screen.dart` |
| `logo`, `business_name` | App branding everywhere |
| `app_minimum_version_*` | `app_version_warning_screen.dart` |
| `image_base_url.*` | All image widgets |
| `currency_*` | All price displays |
| `websocket_*` | Pusher/real-time connection |
| `ai_chatbot_enable` | Chatbot feature toggle |

---

### 2. `/api/customer/config/auth` (223 bytes)
**When:** Before login/registration screens

| Data | Used By |
|------|---------|
| `facebook_login`, `google_login` | Social login buttons |
| `firebase_otp_verification` | OTP screen |
| `otp_resend_time` | OTP countdown timer |
| `referral_earning_status` | Referral input on signup |

---

### 3. `/api/customer/config/trip` (435 bytes)
**When:** When user opens ride booking

| Data | Used By |
|------|---------|
| `bid_on_fare` | Fare negotiation UI |
| `add_intermediate_points` | Multi-stop toggle |
| `payment_gateways` | Payment method selection |
| `otp_confirmation_for_trip` | Trip OTP verification |
| `review_status` | Rating UI after trip |

---

### 4. `/api/customer/config/loyalty` (NEW - split from trip)
**When:** My Level screen, Wallet, after trip completion

| Data | Used By |
|------|---------|
| `level_status` | Enable/disable levels feature |
| `conversion_status` | Enable/disable points earning |
| `conversion_rate` | Points per EGP spent |

**Files:**
- `features/my_level/` - Customer level screen
- `features/wallet/` - Points balance
- Trip completion - Points earned display

---

### 5. `/api/customer/config/safety` (349 bytes)
**When:** Safety screen or during active trip

| Data | Used By |
|------|---------|
| `safety_feature_status` | Feature toggle |
| `safety_feature_emergency_govt_number` | Emergency call button |
| `after_trip_completed_safety_feature_*` | Post-trip safety check |

---

### 6. `/api/customer/config/parcel` (190 bytes)
**When:** User opens parcel/delivery feature

| Data | Used By |
|------|---------|
| `maximum_parcel_weight_*` | Weight validation |
| `parcel_refund_*` | Refund eligibility |

---

### 7. `/api/customer/config/contact` (201 bytes)
**When:** Support/contact/help screen

| Data | Used By |
|------|---------|
| `business_support_phone` | Call support button |
| `business_support_email` | Email support |

---

### 8. `/api/customer/config/external` (173 bytes)
**When:** Only if mart integration enabled

| Data | Used By |
|------|---------|
| `external_system` | Show/hide mart button |
| `mart_*` | Mart app links |

---

### 9. `/api/customer/pages/{page}` (on-demand)
**When:** User opens legal page

Pages: `about_us`, `privacy_and_policy`, `terms_and_conditions`, `legal`

---

## Loading Strategy

```
App Launch
    └── /config/core ✅ (REQUIRED)

On-Demand (Lazy Load):
    ├── /config/auth      → Before login/signup
    ├── /config/trip      → Before booking
    ├── /config/loyalty   → My Level / Wallet screens
    ├── /config/safety    → Safety screen
    ├── /config/parcel    → Parcel feature
    ├── /config/contact   → Support screen
    └── /config/external  → If mart enabled
```

---

## Implementation Notes

1. **Cache responses** - Config rarely changes, cache for session
2. **Parallel loading** - Load auth+trip together if user is logged in
3. **Error handling** - Graceful fallback if secondary endpoints fail
