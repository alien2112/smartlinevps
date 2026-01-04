# Driver Referral System - Complete Test Guide

## Overview

The driver referral system allows existing drivers to refer new drivers and earn rewards when those new drivers complete specific actions (signup, first ride, etc.).

## System Architecture

### Two Referral Systems (Working Together)

1. **NEW System** (Points-based):
   - Tables: `referral_settings`, `referral_invites`, `referral_rewards`
   - Tracks complete funnel: sent → opened → installed → signed_up → converted → rewarded
   - Configurable reward triggers

2. **LEGACY System** (Wallet-based):
   - Tables: `referral_drivers`, `referral_earning_settings`
   - Backward compatibility
   - Wallet-based rewards

### Database Tables

- **users**: `ref_code`, `referred_by`, `referral_count`, `successful_referrals`
- **referral_invites**: Complete tracking of referral funnel
- **referral_rewards**: Points issued to referrer and referee
- **referral_drivers**: Legacy referral tracking

---

## How It Works

### 1. Referrer (Existing Driver)
- Every driver gets a unique `ref_code` (8 characters, e.g., `WZZQGJKLUI`)
- Driver shares this code with potential new drivers

### 2. Referee (New Driver)
- Registers with `referral_code` in the registration request
- System validates the code exists and belongs to a driver
- Links referee to referrer via `referred_by` field

### 3. Tracking & Rewards
- System creates entry in `referral_invites` table
- Updates `users.referred_by` field
- Rewards issued based on `referral_settings.reward_trigger`:
  - `signup`: Immediately on registration
  - `first_ride`: When referee completes first trip
  - `three_rides`: When referee completes 3 trips
  - `deposit`: When referee makes a deposit

### 4. Fraud Prevention
- Self-referral blocked
- Same device/IP checks
- Velocity limits (max referrals per day/total)

---

## API Endpoints

### Driver Registration (with referral code)

**Endpoint:** `POST /api/driver/auth/registration`

**Request Body:**
```json
{
  "first_name": "Ahmed",
  "last_name": "Mohamed",
  "phone": "+201234567890",
  "email": "ahmed@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "gender": "male",
  "referral_code": "WZZQGJKLUI",  // ← Referral code from existing driver
  "service": "taxi"
}
```

**Response:**
```json
{
  "response_code": "default",
  "message": "Registration successful",
  "data": {
    "id": "driver-uuid",
    "ref_code": "NEWDRIVER1",
    "referred_by": "referrer-uuid"
  }
}
```

---

## Test Commands (cURL)

### Step 1: Get Referrer Driver's Code

```bash
# Option 1: Get from existing driver (if you know their phone)
curl -X POST "http://localhost:8000/api/driver/auth/register-info" \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "+201234567890"
  }'

# Option 2: Query database directly
php artisan tinker --execute="
use Modules\UserManagement\Entities\User;
\$driver = User::where('user_type', 'driver')->whereNotNull('ref_code')->first();
echo 'Ref Code: ' . \$driver->ref_code . PHP_EOL;
echo 'Driver ID: ' . \$driver->id . PHP_EOL;
"
```

### Step 2: Register New Driver WITH Referral Code

```bash
REFERRER_CODE="WZZQGJKLUI"  # Replace with actual code
NEW_PHONE="+201234567891"   # Unique phone number

curl -X POST "http://localhost:8000/api/driver/auth/registration" \
  -H "Content-Type: application/json" \
  -d "{
    \"first_name\": \"Ahmed\",
    \"last_name\": \"Test\",
    \"phone\": \"$NEW_PHONE\",
    \"email\": \"ahmed$(date +%s)@test.com\",
    \"password\": \"password123\",
    \"password_confirmation\": \"password123\",
    \"gender\": \"male\",
    \"referral_code\": \"$REFERRER_CODE\",
    \"service\": \"taxi\"
  }"
```

### Step 3: Check Referral Tracking

```bash
php artisan tinker --execute="
use Modules\UserManagement\Entities\User;
use Illuminate\Support\Facades\DB;

// Check referrer
\$referrer = User::where('ref_code', 'WZZQGJKLUI')->where('user_type', 'driver')->first();
if (\$referrer) {
    echo 'Referrer:' . PHP_EOL;
    echo '  ID: ' . \$referrer->id . PHP_EOL;
    echo '  Ref Code: ' . \$referrer->ref_code . PHP_EOL;
    echo '  Referral Count: ' . (\$referrer->referral_count ?? 0) . PHP_EOL;
    echo '  Successful Referrals: ' . (\$referrer->successful_referrals ?? 0) . PHP_EOL;
}

// Check referee
\$referee = User::where('phone', '+201234567891')->where('user_type', 'driver')->first();
if (\$referee) {
    echo PHP_EOL . 'Referee (New Driver):' . PHP_EOL;
    echo '  ID: ' . \$referee->id . PHP_EOL;
    echo '  Ref Code: ' . \$referee->ref_code . PHP_EOL;
    echo '  Referred By: ' . (\$referee->referred_by ?? 'NULL') . PHP_EOL;
    
    if (\$referee->referred_by && \$referrer && \$referee->referred_by === \$referrer->id) {
        echo '  ✓ Referral link confirmed!' . PHP_EOL;
    }
}

// Check referral_invites
if (DB::getSchemaBuilder()->hasTable('referral_invites')) {
    \$invites = DB::table('referral_invites')
        ->where('referrer_id', \$referrer->id ?? '')
        ->orWhere('referee_id', \$referee->id ?? '')
        ->orderBy('created_at', 'desc')
        ->get();
    
    echo PHP_EOL . 'Referral Invites: ' . \$invites->count() . PHP_EOL;
    foreach (\$invites as \$invite) {
        echo '  - Invite ID: ' . \$invite->id . PHP_EOL;
        echo '    Status: ' . \$invite->status . PHP_EOL;
        echo '    Signup At: ' . (\$invite->signup_at ?? 'NULL') . PHP_EOL;
    }
}
"
```

### Step 4: Check Referral Rewards

```bash
php artisan tinker --execute="
use Illuminate\Support\Facades\DB;

if (DB::getSchemaBuilder()->hasTable('referral_rewards')) {
    \$rewards = DB::table('referral_rewards')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();
    
    echo 'Recent Referral Rewards:' . PHP_EOL;
    foreach (\$rewards as \$reward) {
        echo '  - Reward ID: ' . \$reward->id . PHP_EOL;
        echo '    Referrer Points: ' . \$reward->referrer_points . PHP_EOL;
        echo '    Referee Points: ' . \$reward->referee_points . PHP_EOL;
        echo '    Trigger: ' . \$reward->trigger_type . PHP_EOL;
    }
}
"
```

---

## Complete Test Script

Run the automated test script:

```bash
cd /var/www/laravel/smartlinevps
./test_driver_referral_system.sh
```

Or manually:

```bash
bash test_driver_referral_system.sh
```

---

## System Flow Diagram

```
┌─────────────────┐
│ Existing Driver │
│  (Referrer)     │
│  ref_code: ABC  │
└────────┬────────┘
         │
         │ Shares code
         │
         ▼
┌─────────────────┐
│  New Driver     │
│  Registration   │
│  referral_code: │
│      ABC        │
└────────┬────────┘
         │
         │ System validates
         │
         ▼
┌─────────────────┐
│  Validation     │
│  - Code exists? │
│  - Fraud check  │
│  - Limits OK?   │
└────────┬────────┘
         │
         │ Success
         │
         ▼
┌─────────────────┐
│  Link Referral  │
│  - referred_by   │
│  - referral_invites│
│  - referral_drivers│
└────────┬────────┘
         │
         │ Based on trigger
         │
         ▼
┌─────────────────┐
│  Issue Rewards  │
│  - Referrer pts │
│  - Referee pts  │
│  - referral_rewards│
└─────────────────┘
```

---

## Key Points

1. **Referral Code Format**: 8 uppercase characters (e.g., `WZZQGJKLUI`)
2. **Code Generation**: Automatic when driver is created
3. **Validation**: Code must belong to an active driver
4. **Linking**: Happens during registration via `referral_code` field
5. **Tracking**: Multiple tables track referrals for redundancy
6. **Rewards**: Configurable triggers (signup, first_ride, etc.)
7. **Fraud Prevention**: Multiple checks prevent abuse

---

## Troubleshooting

### Referral code not working?
- Check if code exists: `User::where('ref_code', 'CODE')->first()`
- Verify user_type is 'driver'
- Check if driver is active

### Referral not tracked?
- Check `users.referred_by` field
- Check `referral_invites` table
- Check `referral_drivers` table (legacy)
- Verify referral_settings.is_active = true

### Rewards not issued?
- Check `referral_settings.reward_trigger`
- Verify trigger condition met (signup, first_ride, etc.)
- Check `referral_rewards` table

---

## Database Queries

### Find all referrals for a driver:
```sql
SELECT * FROM referral_invites 
WHERE referrer_id = 'driver-uuid'
ORDER BY created_at DESC;
```

### Find who referred a driver:
```sql
SELECT u.*, r.first_name as referrer_name, r.ref_code as referrer_code
FROM users u
LEFT JOIN users r ON u.referred_by = r.id
WHERE u.id = 'driver-uuid';
```

### Count referrals:
```sql
SELECT 
  COUNT(*) as total_referrals,
  COUNT(CASE WHEN status = 'rewarded' THEN 1 END) as successful
FROM referral_invites
WHERE referrer_id = 'driver-uuid';
```
