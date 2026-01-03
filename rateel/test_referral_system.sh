#!/bin/bash

# Referral System - Complete cURL Test Script
# Tests all referral endpoints for both Customer and Driver

# ============================================
# CONFIGURATION
# ============================================
# Get domain from .env file or use default
if [ -f ".env" ]; then
    APP_URL=$(grep "^APP_URL=" .env | cut -d '=' -f2 | tr -d '"' | tr -d "'")
    if [ -n "$APP_URL" ]; then
        BASE_URL="${APP_URL}/api"
    else
        BASE_URL="https://smartline-it.com/api"
    fi
else
    BASE_URL="https://smartline-it.com/api"
fi

echo "Using BASE_URL: $BASE_URL"

# Test tokens (replace with actual tokens from login)
CUSTOMER_TOKEN="${CUSTOMER_TOKEN:-your_customer_token_here}"
DRIVER_TOKEN="${DRIVER_TOKEN:-your_driver_token_here}"

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# ============================================
# HELPER FUNCTIONS
# ============================================
print_header() {
    echo -e "\n${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}\n"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_info() {
    echo -e "${YELLOW}ℹ $1${NC}"
}

test_endpoint() {
    local method=$1
    local endpoint=$2
    local data=$3
    local token=$4
    local description=$5
    
    echo -e "\n${YELLOW}Testing: $description${NC}"
    echo -e "${YELLOW}Endpoint: $method $endpoint${NC}"
    
    if [ -n "$data" ]; then
        echo -e "${YELLOW}Data: $data${NC}"
    fi
    
    if [ "$method" = "GET" ]; then
        if [ -n "$token" ]; then
            response=$(curl -s -w "\n%{http_code}" -X GET \
                -H "Authorization: Bearer $token" \
                -H "Content-Type: application/json" \
                -H "Accept: application/json" \
                "$BASE_URL$endpoint")
        else
            response=$(curl -s -w "\n%{http_code}" -X GET \
                -H "Content-Type: application/json" \
                -H "Accept: application/json" \
                "$BASE_URL$endpoint")
        fi
    else
        if [ -n "$token" ]; then
            response=$(curl -s -w "\n%{http_code}" -X $method \
                -H "Authorization: Bearer $token" \
                -H "Content-Type: application/json" \
                -H "Accept: application/json" \
                -d "$data" \
                "$BASE_URL$endpoint")
        else
            response=$(curl -s -w "\n%{http_code}" -X $method \
                -H "Content-Type: application/json" \
                -H "Accept: application/json" \
                -d "$data" \
                "$BASE_URL$endpoint")
        fi
    fi
    
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | sed '$d')
    
    echo -e "${YELLOW}HTTP Status: $http_code${NC}"
    echo -e "${YELLOW}Response:${NC}"
    echo "$body" | jq '.' 2>/dev/null || echo "$body"
    
    if [ "$http_code" -ge 200 ] && [ "$http_code" -lt 300 ]; then
        print_success "Request successful"
        return 0
    else
        print_error "Request failed with status $http_code"
        return 1
    fi
}

# ============================================
# CUSTOMER REFERRAL TESTS
# ============================================
print_header "CUSTOMER REFERRAL SYSTEM TESTS"

# 1. Validate Referral Code (Public - No Auth)
print_header "1. Validate Referral Code (Public)"
test_endpoint "GET" "/customer/referral/validate-code?code=TEST123" "" "" "Validate a referral code before signup"

# 2. Get My Referral Code
print_header "2. Get My Referral Code"
test_endpoint "GET" "/customer/referral/my-code" "" "$CUSTOMER_TOKEN" "Get customer's referral code and shareable link"

# 3. Generate Invite
print_header "3. Generate Tracked Invite"
test_endpoint "POST" "/customer/referral/generate-invite" \
    '{"channel":"whatsapp","platform":"android"}' \
    "$CUSTOMER_TOKEN" \
    "Generate a tracked referral invite"

# 4. Get Referral Stats
print_header "4. Get Referral Statistics"
test_endpoint "GET" "/customer/referral/stats" "" "$CUSTOMER_TOKEN" "Get customer's referral statistics"

# 5. Get Referral History
print_header "5. Get Referral History"
test_endpoint "GET" "/customer/referral/history?limit=10&offset=1" "" "$CUSTOMER_TOKEN" "Get referral invite history"

# 6. Get Rewards History
print_header "6. Get Rewards History"
test_endpoint "GET" "/customer/referral/rewards?limit=10&offset=1" "" "$CUSTOMER_TOKEN" "Get referral rewards history"

# 7. Get Leaderboard
print_header "7. Get Referral Leaderboard"
test_endpoint "GET" "/customer/referral/leaderboard?period=month&limit=10" "" "$CUSTOMER_TOKEN" "Get top referrers leaderboard"

# ============================================
# DRIVER REFERRAL TESTS
# ============================================
print_header "DRIVER REFERRAL SYSTEM TESTS"

# 8. Get Driver Referral Details
print_header "8. Get Driver Referral Details"
test_endpoint "GET" "/driver/referral-details" "" "$DRIVER_TOKEN" "Get driver's referral code and earnings"

# ============================================
# TEST SIGNUP WITH REFERRAL CODE
# ============================================
print_header "TEST SIGNUP WITH REFERRAL CODE"

# First, get a valid referral code from a customer
print_info "Step 1: Get a referral code from an existing customer"
print_info "Then use that code in the signup below"

# Example signup with referral code
print_header "9. Customer Signup with Referral Code"
test_endpoint "POST" "/customer/auth/registration" \
    '{
        "first_name": "Test",
        "last_name": "User",
        "phone": "+201012345678",
        "password": "Test123456!",
        "password_confirmation": "Test123456!",
        "referral_code": "REPLACE_WITH_ACTUAL_CODE"
    }' \
    "" \
    "Register new customer with referral code"

# ============================================
# SUMMARY
# ============================================
print_header "TEST SUMMARY"
print_info "All referral system endpoints have been tested"
print_info "Review the responses above to verify functionality"
print_info ""
print_info "Next Steps:"
print_info "1. Replace CUSTOMER_TOKEN and DRIVER_TOKEN with actual tokens"
print_info "2. Test with real referral codes"
print_info "3. Verify rewards are issued correctly"
print_info "4. Check fraud detection is working"
