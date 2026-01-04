#!/bin/bash

# Driver Referral System Test Script
# This script demonstrates how the driver referral system works

BASE_URL="${BASE_URL:-http://localhost:8000}"
API_BASE="${API_BASE:-$BASE_URL/api}"

echo "=========================================="
echo "Driver Referral System Test"
echo "=========================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Step 1: Get an existing driver's referral code (Referrer)
echo -e "${BLUE}Step 1: Getting Referrer Driver's Code${NC}"
echo "----------------------------------------"
REFERRER_PHONE="+201234567890"  # Change this to an existing driver phone
REFERRER_CODE=$(curl -s -X POST "$API_BASE/driver/auth/register-info" \
  -H "Content-Type: application/json" \
  -d "{\"phone\": \"$REFERRER_PHONE\"}" | jq -r '.data.ref_code // empty')

if [ -z "$REFERRER_CODE" ]; then
  echo -e "${YELLOW}Note: Using sample referral code from database${NC}"
  # Get from database directly
  REFERRER_CODE="WZZQGJKLUI"  # Sample code from earlier
fi

echo -e "${GREEN}Referrer Code: $REFERRER_CODE${NC}"
echo ""

# Step 2: Register a new driver WITH referral code (Referee)
echo -e "${BLUE}Step 2: Registering New Driver WITH Referral Code${NC}"
echo "----------------------------------------"
NEW_DRIVER_PHONE="+201234567891"  # New unique phone number
TIMESTAMP=$(date +%s)
NEW_DRIVER_EMAIL="driver${TIMESTAMP}@test.com"

echo "Registering driver with phone: $NEW_DRIVER_PHONE"
echo "Using referral code: $REFERRER_CODE"
echo ""

REGISTER_RESPONSE=$(curl -s -X POST "$API_BASE/driver/auth/registration" \
  -H "Content-Type: application/json" \
  -d "{
    \"first_name\": \"Ahmed\",
    \"last_name\": \"Test\",
    \"phone\": \"$NEW_DRIVER_PHONE\",
    \"email\": \"$NEW_DRIVER_EMAIL\",
    \"password\": \"password123\",
    \"password_confirmation\": \"password123\",
    \"gender\": \"male\",
    \"referral_code\": \"$REFERRER_CODE\",
    \"service\": \"taxi\"
  }")

echo "Registration Response:"
echo "$REGISTER_RESPONSE" | jq '.'
echo ""

# Check if registration was successful
SUCCESS=$(echo "$REGISTER_RESPONSE" | jq -r '.response_code // "error"')
if [ "$SUCCESS" != "default" ]; then
  echo -e "${RED}Registration may have failed. Check response above.${NC}"
  echo ""
fi

# Step 3: Check referral tracking in database
echo -e "${BLUE}Step 3: Checking Referral Tracking${NC}"
echo "----------------------------------------"
echo "Checking database for referral records..."
echo ""

# Use tinker to check referral data
php artisan tinker --execute="
use Modules\UserManagement\Entities\User;
use Illuminate\Support\Facades\DB;

echo '=== REFERRAL SYSTEM STATUS ===' . PHP_EOL . PHP_EOL;

// Check referrer
\$referrer = User::where('ref_code', '$REFERRER_CODE')->where('user_type', 'driver')->first();
if (\$referrer) {
    echo 'Referrer Driver:' . PHP_EOL;
    echo '  ID: ' . \$referrer->id . PHP_EOL;
    echo '  Name: ' . (\$referrer->first_name ?? 'N/A') . ' ' . (\$referrer->last_name ?? '') . PHP_EOL;
    echo '  Phone: ' . \$referrer->phone . PHP_EOL;
    echo '  Ref Code: ' . \$referrer->ref_code . PHP_EOL;
    echo '  Referral Count: ' . (\$referrer->referral_count ?? 0) . PHP_EOL;
    echo '  Successful Referrals: ' . (\$referrer->successful_referrals ?? 0) . PHP_EOL;
    echo PHP_EOL;
}

// Check referee (new driver)
\$referee = User::where('phone', '$NEW_DRIVER_PHONE')->where('user_type', 'driver')->first();
if (\$referee) {
    echo 'Referee Driver (New):' . PHP_EOL;
    echo '  ID: ' . \$referee->id . PHP_EOL;
    echo '  Name: ' . (\$referee->first_name ?? 'N/A') . ' ' . (\$referee->last_name ?? '') . PHP_EOL;
    echo '  Phone: ' . \$referee->phone . PHP_EOL;
    echo '  Ref Code: ' . \$referee->ref_code . PHP_EOL;
    echo '  Referred By: ' . (\$referee->referred_by ?? 'NULL') . PHP_EOL;
    echo PHP_EOL;
    
    // Check if referred_by matches referrer
    if (\$referee->referred_by && \$referrer && \$referee->referred_by === \$referrer->id) {
        echo '  ✓ Referral link confirmed!' . PHP_EOL;
    } else {
        echo '  ✗ Referral link NOT found' . PHP_EOL;
    }
    echo PHP_EOL;
}

// Check referral_invites table (if exists)
if (DB::getSchemaBuilder()->hasTable('referral_invites')) {
    echo 'Referral Invites:' . PHP_EOL;
    \$invites = DB::table('referral_invites')
        ->where('referrer_id', \$referrer->id ?? '')
        ->orWhere('referee_id', \$referee->id ?? '')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();
    
    if (\$invites->count() > 0) {
        foreach (\$invites as \$invite) {
            echo '  Invite ID: ' . \$invite->id . PHP_EOL;
            echo '    Referrer: ' . \$invite->referrer_id . PHP_EOL;
            echo '    Referee: ' . (\$invite->referee_id ?? 'NULL') . PHP_EOL;
            echo '    Code: ' . \$invite->invite_code . PHP_EOL;
            echo '    Status: ' . \$invite->status . PHP_EOL;
            echo '    Signed Up: ' . (\$invite->signup_at ?? 'NULL') . PHP_EOL;
            echo PHP_EOL;
        }
    } else {
        echo '  No invites found' . PHP_EOL;
        echo PHP_EOL;
    }
}

// Check referral_drivers table (legacy system)
if (DB::getSchemaBuilder()->hasTable('referral_drivers')) {
    echo 'Legacy Referral Drivers:' . PHP_EOL;
    \$legacy = DB::table('referral_drivers')
        ->where('referrer_id', \$referrer->id ?? '')
        ->orWhere('referee_id', \$referee->id ?? '')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();
    
    if (\$legacy->count() > 0) {
        foreach (\$legacy as \$ref) {
            echo '  Referrer: ' . \$ref->referrer_id . ' -> Referee: ' . \$ref->referee_id . PHP_EOL;
        }
    } else {
        echo '  No legacy referrals found' . PHP_EOL;
    }
    echo PHP_EOL;
}

echo '=== END ===' . PHP_EOL;
"

echo ""
echo -e "${GREEN}Test completed!${NC}"
echo ""
echo "=========================================="
echo "How the System Works:"
echo "=========================================="
echo ""
echo "1. REFERRER (Existing Driver):"
echo "   - Has a unique ref_code (e.g., $REFERRER_CODE)"
echo "   - Shares this code with potential new drivers"
echo ""
echo "2. REFEREE (New Driver):"
echo "   - Registers with referral_code in request"
echo "   - System validates the code exists"
echo "   - Links referee to referrer via 'referred_by' field"
echo ""
echo "3. TRACKING:"
echo "   - New system: referral_invites table tracks the funnel"
echo "   - Legacy system: referral_drivers table (backward compatibility)"
echo "   - User table: 'referred_by' field links to referrer"
echo ""
echo "4. REWARDS:"
echo "   - Based on referral_settings configuration"
echo "   - Can trigger on: signup, first_ride, three_rides, deposit"
echo "   - Points issued to both referrer and referee"
echo ""
echo "5. FRAUD PREVENTION:"
echo "   - Self-referral blocked"
echo "   - Same device/IP checks"
echo "   - Velocity limits (max per day/total)"
echo ""
