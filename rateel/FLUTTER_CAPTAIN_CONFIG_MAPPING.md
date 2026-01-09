# Flutter Driver App - Config Endpoints & Screen Mapping

This document provides the exact mapping of the 8 new configuration endpoints to the specific Screens, Controllers, and Service files in the Flutter Driver App (`smartline-captin`).

---

## 🏗️ 1. Core Config
**Endpoint:** `/api/driver/config/core`  
**Purpose:** Essential global settings (Maintenance, Version, Currency, Websocket) loaded before the app starts.

| component | Path |
|-----------|------|
| **Screen** | `lib/features/splash/screens/splash_screen.dart` |
| **Controller** | `lib/features/splash/controllers/splash_controller.dart` (to be created/updated) |
| **Service** | `lib/features/splash/domain/services/splash_service.dart` |
| **Model** | `lib/features/splash/domain/models/config_model.dart` |

---

## 🔐 2. Auth Config
**Endpoint:** `/api/driver/config/auth`  
**Purpose:** Login/Registration settings, Social logins, OTP settings.

| component | Path |
|-----------|------|
| **Screen** | `lib/features/auth/screens/sign_in_screen.dart` |
| **Controller** | `lib/features/auth/controllers/auth_controller.dart` |
| **Service** | `lib/features/auth/domain/services/auth_service.dart` |
| **Trigger** | `initState()` of `SignInScreen` |

---

## 🚗 3. Trip Config
**Endpoint:** `/api/driver/config/trip`  
**Purpose:** Trip-related logic (Bidding, OTP start, Radius, Chat).

| component | Path |
|-----------|------|
| **Screen** | `lib/features/dashboard/screens/dashboard_screen.dart` (When going online) |
| **Controller** | `lib/features/ride/controllers/ride_controller.dart` |
| **Service** | `lib/features/ride/domain/services/ride_service.dart` |
| **Trigger** | When driver toggles "Online" switch |

---

## 💎 4. Loyalty Config
**Endpoint:** `/api/driver/config/loyalty`  
**Purpose:** Points conversion rates, Level system status.

| component | Path |
|-----------|------|
| **Screen** | `lib/features/wallet/screens/wallet_screen.dart` |
| **Controller** | `lib/features/wallet/controllers/wallet_controller.dart` |
| **Service** | `lib/features/wallet/domain/services/wallet_service.dart` |
| **Trigger** | `initState()` of `WalletScreen` |

---

## 🛡️ 5. Safety Config
**Endpoint:** `/api/driver/config/safety`  
**Purpose:** Emergency numbers, Safety features toggle.

| component | Path |
|-----------|------|
| **Screen** | `lib/features/safety_setup/screens/safety_setup_screen.dart` |
| **Controller** | `lib/features/safety_setup/controllers/safety_alert_controller.dart` |
| **Service** | `lib/features/safety_setup/domain/services/safety_alert_service.dart` |
| **Trigger** | `initState()` of `SafetySetupScreen` |

---

## 📦 6. Parcel Config
**Endpoint:** `/api/driver/config/parcel`  
**Purpose:** Parcel delivery rules, return fees, max weight.

| component | Path |
|-----------|------|
| **Screen** | `lib/features/home/screens/ongoing_parcel_list_screen.dart` |
| **Controller** | `lib/features/ride/controllers/ride_controller.dart` |
| **Trigger** | When accessing Parcel tab or accepting a parcel ride |

---

## 📞 7. Contact Config
**Endpoint:** `/api/driver/config/contact`  
**Purpose:** Support phone, email, address.

| component | Path |
|-----------|------|
| **Screen** | `lib/features/help_and_support/screens/help_and_support_screen.dart` |
| **Controller** | `lib/features/help_and_support/controllers/help_and_support_controller.dart` |
| **Service** | `lib/features/help_and_support/domain/services/help_and_support_service.dart` |
| **Trigger** | `initState()` of `HelpAndSupportScreen` |

---

## 📄 8. Content Pages
**Endpoint:** `/api/driver/pages` (or `/pages/{slug}`)  
**Purpose:** Dynamic legal pages (Privacy, Terms, About).

| component | Path |
|-----------|------|
| **Screen** | `lib/features/html/screens/policy_viewer_screen.dart` |
| **Controller** | `lib/features/html/controllers/html_controller.dart` |
| **Trigger** | `initState()` of `PolicyViewerScreen` (fetch specific slug) |

