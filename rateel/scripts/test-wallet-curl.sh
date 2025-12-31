#!/bin/bash

# ============================================================================
# Customer Wallet API Testing Script (curl)
# ============================================================================
# This script tests all customer wallet functionalities using curl
# 
# Prerequisites:
# - Customer must be registered and have valid credentials
# - Server must be running at the specified BASE_URL
#
# Usage:
#   bash test-wallet-curl.sh
# ============================================================================

# Configuration
BASE_URL="https://smartline-it.com/api"
PHONE="+201234567890"
PASSWORD="12345678"
TOKEN=""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# ============================================================================
# Helper Functions
# ============================================================================

print_header() {
    echo -e "\n${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}\n"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_info() {
    echo -e "${YELLOW}ℹ️  $1${NC}"
}

# ============================================================================
# Step 1: Customer Login
# ============================================================================

print_header "Step 1: Customer Login"

print_info "Logging in with phone: $PHONE"

LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/customer/auth/login" \
  -H "Content-Type: application/json" \
  -d "{
    \"phone\": \"$PHONE\",
    \"password\": \"$PASSWORD\"
  }")

echo "Response: $LOGIN_RESPONSE"

# Extract token from response (using grep and sed)
TOKEN=$(echo "$LOGIN_RESPONSE" | grep -o '"token":"[^"]*' | sed 's/"token":"//')

if [ -z "$TOKEN" ]; then
    print_error "Login failed! Could not extract token."
    echo "Response: $LOGIN_RESPONSE"
    exit 1
fi

print_success "Login successful!"
print_info "Token: ${TOKEN:0:20}..."

# ============================================================================
# Step 2: View Wallet Balance
# ============================================================================

print_header "Step 2: View Wallet Balance"

BALANCE_RESPONSE=$(curl -s -X GET "$BASE_URL/customer/wallet/balance" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json")

echo "Response: $BALANCE_RESPONSE"

# Extract balance
BALANCE=$(echo "$BALANCE_RESPONSE" | grep -o '"wallet_balance":[0-9.]*' | sed 's/"wallet_balance"://')

if [ -n "$BALANCE" ]; then
    print_success "Current wallet balance: $BALANCE EGP"
else
    print_error "Failed to retrieve wallet balance"
fi

# ============================================================================
# Step 3: Add Money to Wallet (Kashier Payment Gateway)
# ============================================================================

print_header "Step 3: Add Money to Wallet (Kashier)"

print_info "Requesting payment URL to add 100 EGP to wallet..."

ADD_FUND_RESPONSE=$(curl -s -X POST "$BASE_URL/customer/wallet/add-fund" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{
    \"amount\": 100,
    \"payment_method\": \"kashier\",
    \"redirect_url\": \"https://smartline-it.com/payment/callback\"
  }")

echo "Response: $ADD_FUND_RESPONSE"

# Extract payment URL
PAYMENT_URL=$(echo "$ADD_FUND_RESPONSE" | grep -o '"payment_url":"[^"]*' | sed 's/"payment_url":"//' | sed 's/\\//g')

if [ -n "$PAYMENT_URL" ]; then
    print_success "Payment URL generated successfully!"
    print_info "Payment URL: $PAYMENT_URL"
    print_info "⚠️  Note: Customer needs to complete payment at this URL"
    print_info "⚠️  Only Kashier payment gateway is allowed for adding funds"
else
    print_error "Failed to generate payment URL"
fi

# ============================================================================
# Step 4: View Transaction History
# ============================================================================

print_header "Step 4: View Transaction History"

TRANSACTIONS_RESPONSE=$(curl -s -X GET "$BASE_URL/customer/wallet/transactions?limit=10&offset=1&type=all" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json")

echo "Response: $TRANSACTIONS_RESPONSE"

# Count transactions
TRANSACTION_COUNT=$(echo "$TRANSACTIONS_RESPONSE" | grep -o '"total":[0-9]*' | sed 's/"total"://')

if [ -n "$TRANSACTION_COUNT" ]; then
    print_success "Retrieved transaction history: $TRANSACTION_COUNT total transactions"
else
    print_error "Failed to retrieve transaction history"
fi

# ============================================================================
# Step 5: Test Insufficient Balance Scenario
# ============================================================================

print_header "Step 5: Test Insufficient Balance for Trip Payment"

print_info "This test requires an active trip. Skipping automated test."
print_info "To test manually:"
echo ""
echo "curl -X POST \"$BASE_URL/trip/payment\" \\"
echo "  -H \"Authorization: Bearer $TOKEN\" \\"
echo "  -H \"Content-Type: application/json\" \\"
echo "  -d '{"
echo "    \"trip_request_id\": \"YOUR_TRIP_ID\","
echo "    \"payment_method\": \"wallet\","
echo "    \"tips\": 0"
echo "  }'"
echo ""
print_info "Expected: If balance < trip_fare, returns 403 with 'insufficient_fund_403'"

# ============================================================================
# Summary
# ============================================================================

print_header "Test Summary"

echo -e "Feature                      | Status | Endpoint"
echo -e "---------------------------- | ------ | --------"
echo -e "✅ Customer Login            | TESTED | POST /customer/auth/login"
echo -e "✅ View Wallet Balance       | TESTED | GET /customer/wallet/balance"
echo -e "✅ Add Money (Kashier Only)  | TESTED | POST /customer/wallet/add-fund"
echo -e "✅ Transaction History       | TESTED | GET /customer/wallet/transactions"
echo -e "⚠️  Pay Trip with Wallet     | MANUAL | POST /trip/payment"

print_info "Current Balance: $BALANCE EGP"
print_info "Total Transactions: $TRANSACTION_COUNT"

if [ -n "$PAYMENT_URL" ]; then
    echo ""
    print_info "💳 Complete payment at: $PAYMENT_URL"
fi

echo ""
print_success "Wallet API testing completed!"
