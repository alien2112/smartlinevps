#!/bin/bash

# Driver Referral System - Complete Test with Driver Creation
# Creates a test driver, tests referral endpoints, then cleans up

# Get domain from .env
if [ -f ".env" ]; then
    APP_URL=$(grep "^APP_URL=" .env | cut -d '=' -f2 | tr -d '"' | tr -d "'" | xargs)
    BASE_URL="${APP_URL}/api"
else
    BASE_URL="https://smartline-it.com/api"
fi

echo "=========================================="
echo "Driver Referral System - Complete Test"
echo "Domain: $BASE_URL"
echo "=========================================="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Generate unique test data
TEST_PHONE="+2010$(date +%s | tail -c 8)"
TEST_EMAIL="testdriver$(date +%s)@test.com"
TEST_DRIVER_ID=""

# ============================================
# STEP 1: Create Test Driver in Database
# ============================================
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Step 1: Creating Test Driver${NC}"
echo -e "${BLUE}========================================${NC}"

cd /var/www/laravel/smartlinevps/rateel

# Create driver using PHP artisan tinker
CREATE_SCRIPT=$(cat <<'PHPSCRIPT'
<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Modules\UserManagement\Entities\DriverDetail;
use Modules\UserManagement\Entities\UserAccount;
use Modules\UserManagement\Entities\UserLevel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$phone = $argv[1];
$email = $argv[2];

// Check if driver already exists
$existing = User::where('phone', $phone)->where('user_type', 'driver')->first();
if ($existing) {
    echo json_encode(['error' => 'Driver already exists', 'id' => $existing->id]);
    exit(1);
}

DB::beginTransaction();
try {
    // Get first driver level
    $driverLevel = UserLevel::where('user_type', 'driver')->first();
    
    // Generate referral code
    $refCode = 'TEST-' . strtoupper(Str::random(8));
    
    // Create test driver
    $driver = User::create([
        'id' => Str::uuid(),
        'user_level_id' => $driverLevel?->id,
        'first_name' => 'Test',
        'last_name' => 'Driver',
        'full_name' => 'Test Driver',
        'phone' => $phone,
        'email' => $email,
        'password' => Hash::make('Test123456!'),
        'user_type' => 'driver',
        'is_active' => true,
        'is_approved' => true,
        'ref_code' => $refCode,
        'onboarding_step' => 'approved',
        'onboarding_state' => 'approved',
        'is_phone_verified' => true,
        'phone_verified_at' => now(),
        'email_verified_at' => now(),
    ]);
    
    // Create driver details
    $driver->driverDetails()->create([
        'user_id' => $driver->id,
        'is_online' => false,
        'availability_status' => 'unavailable',
    ]);
    
    // Create user account
    $driver->userAccount()->create([
        'id' => Str::uuid(),
        'user_id' => $driver->id,
        'payable_balance' => 0,
        'receivable_balance' => 0,
        'received_balance' => 0,
        'pending_balance' => 0,
        'wallet_balance' => 0,
        'total_withdrawn' => 0,
    ]);
    
    DB::commit();
    echo json_encode(['success' => true, 'id' => $driver->id, 'ref_code' => $driver->ref_code, 'phone' => $driver->phone]);
} catch (\Exception $e) {
    DB::rollBack();
    echo json_encode(['error' => $e->getMessage()]);
    exit(1);
}
PHPSCRIPT
)

echo -e "${YELLOW}Creating driver with phone: $TEST_PHONE${NC}"
CREATE_RESULT=$(php create_test_driver.php "$TEST_PHONE" "$TEST_EMAIL" 2>&1)

if echo "$CREATE_RESULT" | grep -q '"success"'; then
    TEST_DRIVER_ID=$(echo "$CREATE_RESULT" | jq -r '.id' 2>/dev/null)
    REFERRAL_CODE=$(echo "$CREATE_RESULT" | jq -r '.ref_code' 2>/dev/null)
    echo -e "${GREEN}✓ Driver created successfully${NC}"
    echo -e "${GREEN}Driver ID: $TEST_DRIVER_ID${NC}"
    echo -e "${GREEN}Referral Code: $REFERRAL_CODE${NC}"
    echo -e "${GREEN}Phone: $TEST_PHONE${NC}"
else
    echo -e "${RED}✗ Failed to create driver${NC}"
    echo "$CREATE_RESULT"
    exit 1
fi

echo ""

# ============================================
# STEP 2: Login as Test Driver
# ============================================
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Step 2: Logging in as Test Driver${NC}"
echo -e "${BLUE}========================================${NC}"

LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/driver/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{
    \"phone\": \"$TEST_PHONE\",
    \"password\": \"Test123456!\"
  }")

echo -e "${YELLOW}Login Response:${NC}"
echo "$LOGIN_RESPONSE" | jq '.' 2>/dev/null || echo "$LOGIN_RESPONSE"

# Extract token
DRIVER_TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.token // .data.token // empty' 2>/dev/null)

if [ -z "$DRIVER_TOKEN" ] || [ "$DRIVER_TOKEN" = "null" ]; then
    echo -e "${RED}✗ Failed to get driver token${NC}"
    echo "Cleaning up..."
    php -r "require 'vendor/autoload.php'; \$app = require_once 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); \App\Models\User::where('id', '$TEST_DRIVER_ID')->delete();" 2>/dev/null
    exit 1
fi

echo -e "${GREEN}✓ Login successful${NC}"
echo -e "${GREEN}Token: ${DRIVER_TOKEN:0:20}...${NC}"
echo ""

# ============================================
# STEP 3: Test Referral Endpoints
# ============================================
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Step 3: Testing Referral Endpoints${NC}"
echo -e "${BLUE}========================================${NC}"

# Test 1: Get Referral Details
echo -e "${YELLOW}Test 1: GET /api/driver/referral-details${NC}"
RESPONSE1=$(curl -s -w "\n%{http_code}" -X GET \
  -H "Authorization: Bearer $DRIVER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  "$BASE_URL/driver/referral-details")

HTTP_CODE1=$(echo "$RESPONSE1" | tail -n1)
BODY1=$(echo "$RESPONSE1" | sed '$d')

echo -e "${YELLOW}HTTP Status: $HTTP_CODE1${NC}"
echo "$BODY1" | jq '.' 2>/dev/null || echo "$BODY1"

if [ "$HTTP_CODE1" -ge 200 ] && [ "$HTTP_CODE1" -lt 300 ]; then
    echo -e "${GREEN}✓ Test 1 passed${NC}"
    # Extract referral code from response
    RESPONSE_REF_CODE=$(echo "$BODY1" | jq -r '.content.referral_code // .data.referral_code // empty' 2>/dev/null)
    if [ -n "$RESPONSE_REF_CODE" ] && [ "$RESPONSE_REF_CODE" != "null" ]; then
        echo -e "${GREEN}Referral Code from API: $RESPONSE_REF_CODE${NC}"
    fi
else
    echo -e "${RED}✗ Test 1 failed${NC}"
fi
echo ""

# Test 2: Get Referral Earnings History
echo -e "${YELLOW}Test 2: GET /api/driver/transaction/referral-earning-list${NC}"
RESPONSE2=$(curl -s -w "\n%{http_code}" -X GET \
  -H "Authorization: Bearer $DRIVER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  "$BASE_URL/driver/transaction/referral-earning-list?limit=10&offset=1")

HTTP_CODE2=$(echo "$RESPONSE2" | tail -n1)
BODY2=$(echo "$RESPONSE2" | sed '$d')

echo -e "${YELLOW}HTTP Status: $HTTP_CODE2${NC}"
echo "$BODY2" | jq '.' 2>/dev/null || echo "$BODY2"

if [ "$HTTP_CODE2" -ge 200 ] && [ "$HTTP_CODE2" -lt 300 ]; then
    echo -e "${GREEN}✓ Test 2 passed${NC}"
else
    echo -e "${RED}✗ Test 2 failed${NC}"
fi
echo ""

# ============================================
# STEP 4: Cleanup - Remove Test Driver
# ============================================
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Step 4: Cleaning Up Test Driver${NC}"
echo -e "${BLUE}========================================${NC}"

CLEANUP_SCRIPT=$(cat <<'PHPSCRIPT'
<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

$driverId = $argv[1];

DB::beginTransaction();
try {
    $driver = User::find($driverId);
    if ($driver) {
        // Delete related records (soft deletes will handle most)
        // Force delete to clean up completely
        $driver->driverDetails()->delete();
        $driver->userAccount()->delete();
        $driver->forceDelete(); // Force delete to bypass soft deletes for test cleanup
        DB::commit();
        echo json_encode(['success' => true, 'message' => 'Driver deleted']);
    } else {
        DB::rollBack();
        echo json_encode(['success' => false, 'message' => 'Driver not found']);
    }
} catch (\Exception $e) {
    DB::rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
PHPSCRIPT
)

echo -e "${YELLOW}Deleting test driver: $TEST_DRIVER_ID${NC}"
CLEANUP_RESULT=$(php delete_test_driver.php "$TEST_DRIVER_ID" 2>&1)

if echo "$CLEANUP_RESULT" | grep -q '"success"'; then
    echo -e "${GREEN}✓ Test driver removed successfully${NC}"
else
    echo -e "${RED}✗ Failed to remove test driver${NC}"
    echo "$CLEANUP_RESULT"
    echo -e "${YELLOW}Please manually delete driver with ID: $TEST_DRIVER_ID${NC}"
fi

echo ""

# ============================================
# SUMMARY
# ============================================
echo -e "${GREEN}=========================================="
echo "Test Summary"
echo "==========================================${NC}"
echo -e "${YELLOW}Test Driver Created:${NC} $TEST_PHONE"
echo -e "${YELLOW}Driver ID:${NC} $TEST_DRIVER_ID"
echo -e "${YELLOW}Referral Code:${NC} $REFERRAL_CODE"
echo -e "${YELLOW}Tests Completed:${NC}"
echo "  1. Get Referral Details - $([ "$HTTP_CODE1" -ge 200 ] && [ "$HTTP_CODE1" -lt 300 ] && echo "✓ PASS" || echo "✗ FAIL")"
echo "  2. Get Referral Earnings - $([ "$HTTP_CODE2" -ge 200 ] && [ "$HTTP_CODE2" -lt 300 ] && echo "✓ PASS" || echo "✗ FAIL")"
echo -e "${GREEN}Cleanup:${NC} $([ -n "$CLEANUP_RESULT" ] && echo "$CLEANUP_RESULT" | grep -q '"success"' && echo "✓ Driver removed" || echo "✗ Cleanup failed")"
echo ""
