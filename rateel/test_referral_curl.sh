#!/bin/bash

# Referral System cURL Tests
# Domain: https://smartline-it.com

BASE_URL="https://smartline-it.com/api"

# ============================================
# REPLACE THESE WITH YOUR ACTUAL TOKENS
# ============================================
CUSTOMER_TOKEN="YOUR_CUSTOMER_TOKEN_HERE"
DRIVER_TOKEN="YOUR_DRIVER_TOKEN_HERE"

echo "=========================================="
echo "Referral System cURL Tests"
echo "Domain: $BASE_URL"
echo "=========================================="
echo ""

# ============================================
# 1. VALIDATE REFERRAL CODE (Public)
# ============================================
echo "1. Validate Referral Code (Public)"
echo "-----------------------------------"
curl -X GET "$BASE_URL/customer/referral/validate-code?code=TEST123" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\n\nHTTP Status: %{http_code}\n"
echo ""

# ============================================
# 2. GET MY REFERRAL CODE (Customer)
# ============================================
echo "2. Get My Referral Code (Customer)"
echo "-----------------------------------"
curl -X GET "$BASE_URL/customer/referral/my-code" \
  -H "Authorization: Bearer $CUSTOMER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\n\nHTTP Status: %{http_code}\n"
echo ""

# ============================================
# 3. GENERATE INVITE (Customer)
# ============================================
echo "3. Generate Invite (Customer)"
echo "-----------------------------------"
curl -X POST "$BASE_URL/customer/referral/generate-invite" \
  -H "Authorization: Bearer $CUSTOMER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"channel":"whatsapp","platform":"android"}' \
  -w "\n\nHTTP Status: %{http_code}\n"
echo ""

# ============================================
# 4. GET REFERRAL STATS (Customer)
# ============================================
echo "4. Get Referral Stats (Customer)"
echo "-----------------------------------"
curl -X GET "$BASE_URL/customer/referral/stats" \
  -H "Authorization: Bearer $CUSTOMER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\n\nHTTP Status: %{http_code}\n"
echo ""

# ============================================
# 5. GET REFERRAL HISTORY (Customer)
# ============================================
echo "5. Get Referral History (Customer)"
echo "-----------------------------------"
curl -X GET "$BASE_URL/customer/referral/history?limit=10&offset=1" \
  -H "Authorization: Bearer $CUSTOMER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\n\nHTTP Status: %{http_code}\n"
echo ""

# ============================================
# 6. GET REWARDS HISTORY (Customer)
# ============================================
echo "6. Get Rewards History (Customer)"
echo "-----------------------------------"
curl -X GET "$BASE_URL/customer/referral/rewards?limit=10&offset=1" \
  -H "Authorization: Bearer $CUSTOMER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\n\nHTTP Status: %{http_code}\n"
echo ""

# ============================================
# 7. GET LEADERBOARD (Customer)
# ============================================
echo "7. Get Leaderboard (Customer)"
echo "-----------------------------------"
curl -X GET "$BASE_URL/customer/referral/leaderboard?period=month&limit=10" \
  -H "Authorization: Bearer $CUSTOMER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\n\nHTTP Status: %{http_code}\n"
echo ""

# ============================================
# 8. GET DRIVER REFERRAL DETAILS
# ============================================
echo "8. Get Driver Referral Details"
echo "-----------------------------------"
curl -X GET "$BASE_URL/driver/referral-details" \
  -H "Authorization: Bearer $DRIVER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\n\nHTTP Status: %{http_code}\n"
echo ""

echo "=========================================="
echo "All tests completed!"
echo "=========================================="
