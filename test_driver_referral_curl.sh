#!/bin/bash

# Simple Driver Referral Test with cURL
# This demonstrates the referral system with actual API calls

BASE_URL="${BASE_URL:-http://localhost:8000}"
API_BASE="${API_BASE:-$BASE_URL/api}"

echo "=========================================="
echo "Driver Referral System - cURL Test"
echo "=========================================="
echo ""

# Step 1: Get a referrer code from database
echo "Step 1: Getting Referrer Code from Database..."
echo "----------------------------------------"
REFERRER_CODE=$(php artisan tinker --execute="
use Modules\UserManagement\Entities\User;
\$driver = User::where('user_type', 'driver')->whereNotNull('ref_code')->first();
if (\$driver) {
    echo \$driver->ref_code;
} else {
    echo 'NONE';
}
")

if [ "$REFERRER_CODE" = "NONE" ] || [ -z "$REFERRER_CODE" ]; then
  echo "❌ No driver with referral code found in database"
  echo "   Please create a driver first or use a known referral code"
  exit 1
fi

echo "✓ Found Referrer Code: $REFERRER_CODE"
echo ""

# Step 2: Register new driver with referral code
echo "Step 2: Registering New Driver WITH Referral Code"
echo "----------------------------------------"
TIMESTAMP=$(date +%s)
NEW_PHONE="+2012345678$TIMESTAMP"  # Unique phone
NEW_EMAIL="driver${TIMESTAMP}@test.com"

echo "New Driver Details:"
echo "  Phone: $NEW_PHONE"
echo "  Email: $NEW_EMAIL"
echo "  Referral Code: $REFERRER_CODE"
echo ""

REGISTER_RESPONSE=$(curl -s -X POST "$API_BASE/driver/auth/registration" \
  -H "Content-Type: application/json" \
  -d "{
    \"first_name\": \"Ahmed\",
    \"last_name\": \"Test\",
    \"phone\": \"$NEW_PHONE\",
    \"email\": \"$NEW_EMAIL\",
    \"password\": \"password123\",
    \"password_confirmation\": \"password123\",
    \"gender\": \"male\",
    \"referral_code\": \"$REFERRER_CODE\",
    \"service\": \"taxi\"
  }")

echo "Registration Response:"
echo "$REGISTER_RESPONSE" | jq '.' 2>/dev/null || echo "$REGISTER_RESPONSE"
echo ""

# Step 3: Verify referral was tracked
echo "Step 3: Verifying Referral Tracking"
echo "----------------------------------------"
php artisan tinker --execute="
use Modules\UserManagement\Entities\User;
use Illuminate\Support\Facades\DB;

echo '=== REFERRAL VERIFICATION ===' . PHP_EOL . PHP_EOL;

// Get referrer
\$referrer = User::where('ref_code', '$REFERRER_CODE')->where('user_type', 'driver')->first();
if (!\$referrer) {
    echo '❌ Referrer not found!' . PHP_EOL;
    exit;
}

echo 'Referrer (Existing Driver):' . PHP_EOL;
echo '  ID: ' . \$referrer->id . PHP_EOL;
echo '  Name: ' . (\$referrer->first_name ?? 'N/A') . ' ' . (\$referrer->last_name ?? '') . PHP_EOL;
echo '  Ref Code: ' . \$referrer->ref_code . PHP_EOL;
echo '  Referral Count: ' . (\$referrer->referral_count ?? 0) . PHP_EOL;
echo '  Successful Referrals: ' . (\$referrer->successful_referrals ?? 0) . PHP_EOL;
echo PHP_EOL;

// Get referee
\$referee = User::where('phone', '$NEW_PHONE')->where('user_type', 'driver')->first();
if (!\$referee) {
    echo '❌ New driver not found! Registration may have failed.' . PHP_EOL;
    exit;
}

echo 'Referee (New Driver):' . PHP_EOL;
echo '  ID: ' . \$referee->id . PHP_EOL;
echo '  Name: ' . (\$referee->first_name ?? 'N/A') . ' ' . (\$referee->last_name ?? '') . PHP_EOL;
echo '  Phone: ' . \$referee->phone . PHP_EOL;
echo '  Ref Code: ' . \$referee->ref_code . PHP_EOL;
echo '  Referred By: ' . (\$referee->referred_by ?? 'NULL') . PHP_EOL;
echo PHP_EOL;

// Verify link
if (\$referee->referred_by && \$referee->referred_by === \$referrer->id) {
    echo '✅ Referral link CONFIRMED!' . PHP_EOL;
    echo '   New driver is linked to referrer' . PHP_EOL;
} else {
    echo '❌ Referral link NOT found' . PHP_EOL;
    echo '   referred_by: ' . (\$referee->referred_by ?? 'NULL') . PHP_EOL;
    echo '   referrer_id: ' . \$referrer->id . PHP_EOL;
}
echo PHP_EOL;

// Check referral_invites
if (DB::getSchemaBuilder()->hasTable('referral_invites')) {
    \$invite = DB::table('referral_invites')
        ->where('referrer_id', \$referrer->id)
        ->where('referee_id', \$referee->id)
        ->orderBy('created_at', 'desc')
        ->first();
    
    if (\$invite) {
        echo 'Referral Invite Record:' . PHP_EOL;
        echo '  Invite ID: ' . \$invite->id . PHP_EOL;
        echo '  Status: ' . \$invite->status . PHP_EOL;
        echo '  Signup At: ' . (\$invite->signup_at ?? 'NULL') . PHP_EOL;
        echo '  Channel: ' . (\$invite->invite_channel ?? 'N/A') . PHP_EOL;
    } else {
        echo '⚠️  No referral_invites record found (may use legacy system)' . PHP_EOL;
    }
    echo PHP_EOL;
}

// Check legacy referral_drivers
if (DB::getSchemaBuilder()->hasTable('referral_drivers')) {
    \$legacy = DB::table('referral_drivers')
        ->where('referrer_id', \$referrer->id)
        ->where('referee_id', \$referee->id)
        ->first();
    
    if (\$legacy) {
        echo 'Legacy Referral Record:' . PHP_EOL;
        echo '  Found in referral_drivers table' . PHP_EOL;
    }
}
echo PHP_EOL;

echo '=== SUMMARY ===' . PHP_EOL;
echo 'System Status: ';
if (\$referee->referred_by && \$referee->referred_by === \$referrer->id) {
    echo '✅ WORKING' . PHP_EOL;
} else {
    echo '❌ NOT WORKING' . PHP_EOL;
}
"

echo ""
echo "=========================================="
echo "Test Complete!"
echo "=========================================="
