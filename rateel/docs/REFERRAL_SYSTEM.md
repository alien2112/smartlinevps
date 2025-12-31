# Customer Referral System - Complete Documentation

## Overview

The referral system allows customers to invite friends and earn loyalty points when those friends complete specific actions (signup, first ride, etc.).

## ✅ Fixes Applied

### Issue: Referral system only tracked code creation and WhatsApp sharing

**Root Cause:** The new points-based referral system (`referral_invites`, `referral_rewards`) was not integrated with the signup flow.

**Fix Applied:**
1. Modified `AuthController::processReferral()` to call `ReferralService::linkSignup()`
2. This links new user signups to referral invites
3. Rewards are now automatically processed based on the configured trigger (signup, first_ride, etc.)

---

## Architecture

### Two Referral Systems

The codebase has TWO referral systems that now work together:

| System | Tables | Purpose |
|--------|--------|---------|
| **OLD** (Legacy) | `referral_customers`, `referral_drivers`, `referral_earning_settings` | Wallet-based rewards |
| **NEW** (Current) | `referral_settings`, `referral_invites`, `referral_rewards` | Points-based rewards with funnel tracking |

Both systems are triggered during signup to maintain backward compatibility.

---

## Database Schema

### referral_settings
Configurable settings for the referral program.

| Column | Type | Description |
|--------|------|-------------|
| referrer_points | integer | Points given to referrer (default: 100) |
| referee_points | integer | Points given to new user (default: 50) |
| reward_trigger | enum | When to issue reward: `signup`, `first_ride`, `three_rides`, `deposit` |
| min_ride_fare | decimal | Minimum fare for ride to count |
| required_rides | integer | Number of rides required (for `three_rides` trigger) |
| max_referrals_per_day | integer | Daily limit per referrer |
| max_referrals_total | integer | Total lifetime limit per referrer |
| invite_expiry_days | integer | Days before invite expires |
| is_active | boolean | Enable/disable referral program |

### referral_invites
Tracks the entire referral funnel.

| Column | Type | Description |
|--------|------|-------------|
| referrer_id | uuid | User who shared the code |
| referee_id | uuid | User who signed up (filled after signup) |
| invite_code | string | The referral code used |
| invite_channel | enum | How it was shared: `link`, `code`, `qr`, `sms`, `whatsapp`, `copy` |
| invite_token | string | Unique tracking token |
| sent_at | timestamp | When invite was created |
| opened_at | timestamp | When link was clicked |
| installed_at | timestamp | When app was installed |
| signup_at | timestamp | When user signed up |
| first_ride_at | timestamp | When first ride completed |
| reward_at | timestamp | When reward was issued |
| status | enum | Current status (see below) |

**Status Values:**
- `sent` - Invite created
- `opened` - Link clicked
- `installed` - App installed
- `signed_up` - User registered
- `converted` - Trigger condition met
- `rewarded` - Points issued
- `expired` - Invite expired
- `fraud_blocked` - Blocked for fraud

### referral_rewards
Tracks rewards issued.

| Column | Type | Description |
|--------|------|-------------|
| referral_invite_id | uuid | Related invite |
| referrer_id | uuid | Who gets referrer reward |
| referrer_points | integer | Points issued to referrer |
| referrer_status | enum | `eligible`, `paid`, `cancelled` |
| referee_id | uuid | Who gets referee reward |
| referee_points | integer | Points issued to referee |
| referee_status | enum | `eligible`, `paid`, `cancelled` |
| trigger_type | enum | What triggered the reward |
| trigger_trip_id | uuid | Trip that triggered reward (if applicable) |

---

## How It Works

### 1. Referral Code Generation

Every user gets a unique referral code in format: `name-xxxx` (e.g., `ahmed-a1b2`).

```
GET /api/customer/referral/my-code
```

### 2. Sharing via WhatsApp/SMS

When user shares, we create a tracked invite:

```
POST /api/customer/referral/generate-invite
{
  "channel": "whatsapp",
  "platform": "android"
}
```

### 3. New User Signup with Code

During registration, user provides the referral code:

```
POST /api/customer/auth/registration
{
  "first_name": "John",
  "phone": "+201234567890",
  "password": "password123",
  "referral_code": "ahmed-a1b2"  // <-- Referral code
}
```

### 4. Linking Signup (Automatic)

The system automatically calls `ReferralService::linkSignup()` which:
- Validates the referral code
- Checks for fraud (same device, same IP, etc.)
- Links the new user to the referrer
- If trigger is `signup`, issues reward immediately

### 5. Trip Completion Reward

If trigger is `first_ride` or `three_rides`:
- `TripRequestObserver` monitors trip completions
- When customer completes qualifying trip, `ReferralService::processRideCompletion()` is called
- Points are issued to both referrer and referee

---

## API Endpoints

### Customer Endpoints

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/api/customer/referral/my-code` | Get my referral code and settings | ✅ |
| POST | `/api/customer/referral/generate-invite` | Create tracked invite | ✅ |
| GET | `/api/customer/referral/stats` | Get my referral stats | ✅ |
| GET | `/api/customer/referral/history` | Get invite history | ✅ |
| GET | `/api/customer/referral/rewards` | Get rewards history | ✅ |
| GET | `/api/customer/referral/leaderboard` | Get top referrers | ✅ |
| GET | `/api/customer/referral/validate-code` | Validate a code (before signup) | ❌ |

### Example Responses

**GET /api/customer/referral/my-code**
```json
{
  "response_code": "default_200",
  "data": {
    "ref_code": "ahmed-a1b2",
    "shareable_link": "https://smartline-it.com/invite/ahmed-a1b2",
    "qr_data": "https://smartline-it.com/invite/ahmed-a1b2",
    "is_active": true,
    "referrer_points": 100,
    "referee_points": 50,
    "reward_trigger": "first_ride",
    "message": "Invite friends and earn 100 points when they complete their first ride! They get 50 points too."
  }
}
```

**GET /api/customer/referral/stats**
```json
{
  "response_code": "default_200",
  "data": {
    "ref_code": "ahmed-a1b2",
    "invites_sent": 15,
    "invites_opened": 8,
    "signups": 5,
    "conversions": 3,
    "total_points_earned": 300,
    "referral_count": 5,
    "successful_referrals": 3
  }
}
```

---

## Admin Dashboard

### URL: `/admin/referral`

### Pages

1. **Overview** (`/admin/referral`) - Analytics dashboard
2. **Settings** (`/admin/referral/settings`) - Configure referral program
3. **Referrals** (`/admin/referral/referrals`) - All invites with funnel status
4. **Rewards** (`/admin/referral/rewards`) - All rewards issued
5. **Leaderboard** (`/admin/referral/leaderboard`) - Top referrers
6. **Fraud Logs** (`/admin/referral/fraud-logs`) - Blocked attempts

### Configurable Settings

- **Referrer Points**: Points given to the person who shared
- **Referee Points**: Points given to the new user
- **Reward Trigger**: When to issue rewards
  - `signup` - Immediately on registration
  - `first_ride` - After first completed ride
  - `three_rides` - After 3 completed rides
  - `deposit` - After first wallet deposit
- **Min Ride Fare**: Minimum fare for ride to count
- **Daily Limit**: Max referrals per day per user
- **Total Limit**: Max lifetime referrals per user
- **Invite Expiry**: Days before invite expires
- **Fraud Prevention**: Block same device, same IP, require phone verification

---

## Fraud Prevention

The system includes multiple fraud detection mechanisms:

| Check | Description | Configurable |
|-------|-------------|--------------|
| Self-Referral | User can't use own code | Always on |
| Same Device | Block if device fingerprint matches | ✅ |
| Same IP | Block if signup IP matches referrer | ✅ |
| Velocity Limit | Daily/total referral limits | ✅ |
| Expired Invite | Block if invite is too old | ✅ |
| Low Fare Ride | Block if ride fare is too low | ✅ |

All blocked attempts are logged in `referral_fraud_logs`.

---

## Setup Instructions

### 1. Run Migrations

```bash
php artisan migrate
```

This creates the referral tables and adds required columns to users table.

### 2. Set Up Referral System

```bash
php artisan referral:setup --generate-codes
```

This will:
- Check all tables exist
- Create default settings if missing
- Generate referral codes for all users

### 3. Configure Settings

Go to Admin Dashboard → Referral → Settings

Configure:
- Points amounts
- Reward trigger
- Fraud prevention settings
- Enable/disable the program

---

## Flutter Integration

### Get Referral Code

```dart
final response = await api.get('/customer/referral/my-code');
final refCode = response.data['ref_code'];
final shareLink = response.data['shareable_link'];
```

### Share via WhatsApp

```dart
// First, generate tracked invite
final invite = await api.post('/customer/referral/generate-invite', {
  'channel': 'whatsapp',
  'platform': Platform.isAndroid ? 'android' : 'ios',
});

// Then share
Share.share(
  'Join SmartLine using my code ${invite.data['invite_code']} and get 50 points! ${invite.data['shareable_link']}'
);
```

### Register with Referral Code

```dart
await api.post('/customer/auth/registration', {
  'first_name': 'John',
  'last_name': 'Doe',
  'phone': '+201234567890',
  'password': 'password123',
  'referral_code': 'ahmed-a1b2',  // Optional
});
```

### Show Stats

```dart
final stats = await api.get('/customer/referral/stats');
// Display invites_sent, conversions, total_points_earned
```

### Show Leaderboard

```dart
final leaderboard = await api.get('/customer/referral/leaderboard?period=month');
// Display top referrers with my_rank
```

---

## Reward Flow

### Trigger: signup

```
User A shares code → User B signs up with code → Points issued immediately
```

### Trigger: first_ride

```
User A shares code → User B signs up → User B completes first ride → Points issued
```

### Trigger: three_rides

```
User A shares code → User B signs up → User B completes 3 rides → Points issued
```

---

## Troubleshooting

### Referral not tracking

1. Check if `referral_settings.is_active = true`
2. Verify referral code exists in users table
3. Check fraud logs for blocked attempts
4. Verify TripRequestObserver is registered

### Points not issued

1. Check reward trigger setting
2. For `first_ride`, verify trip is `completed` and `paid`
3. Check minimum fare requirement
4. Check referrer hasn't hit daily/total limit

### Debug with logs

```bash
tail -f storage/logs/laravel.log | grep -i referral
```

---

## Files Changed

1. `Modules/AuthManagement/Http/Controllers/Api/New/AuthController.php`
   - Added `ReferralService` dependency
   - Updated `processReferral()` to call new system

2. `Modules/UserManagement/Entities/ReferralSetting.php`
   - Auto-creates default settings if missing

3. `Modules/UserManagement/Console/SetupReferralSystem.php`
   - New artisan command for setup

4. `app/Observers/TripRequestObserver.php`
   - Already processes referral rewards on trip completion

---

## Summary

✅ **Fixed**: Referral codes are now properly tracked through the entire funnel  
✅ **Fixed**: Rewards are issued based on configured triggers  
✅ **Fixed**: Dashboard shows accurate referral data  
✅ **Fixed**: Flutter app can access all referral endpoints  

The referral system is now fully functional!
