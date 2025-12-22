# Payment Endpoints - Quick Reference Guide

## Overview
This guide covers the payment endpoints you're using, their purpose, and how to test them.

---

## 1. Get Available Payment Methods

**Endpoint:**
```
GET /api/customer/config/get-payment-methods
```

**Purpose:** Returns all active payment gateways configured in the system.

**Authentication:** Required (Bearer token)

**Response:**
```json
[
  {
    "gateway": "kashier",
    "gateway_title": "Kashier",
    "gateway_image": "kashier.png"
  },
  {
    "gateway": "stripe",
    "gateway_title": "Stripe",
    "gateway_image": "stripe.png"
  }
]
```

**Test Command:**
```bash
curl -X GET 'https://192.168.8.158:8080/api/customer/config/get-payment-methods' \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Accept: application/json'
```

**Notes:**
- Only returns gateways where `is_active = 1` in `payment_settings` table
- Kashier must be configured in database to appear in this list

---

## 2. Initiate Digital Payment (Kashier)

**Endpoint:**
```
GET /api/customer/ride/digital-payment
```

**Purpose:** Creates a payment request and redirects user to Kashier payment page.

**Authentication:** ✅ **NOW REQUIRED** (Fixed - was allowing unauthenticated requests)

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| trip_request_id | UUID | Yes | The trip/ride ID to pay for |
| payment_method | string | Yes | Payment gateway (e.g., "kashier") |
| tips | numeric | No | Tip amount (optional, default: 0) |

**Example URL:**
```
https://192.168.8.158:8080/api/customer/ride/digital-payment?trip_request_id=cf8c0637-395c-4f43-bc73-027d477ffaf4&payment_method=kashier&tips=10
```

**Test Command:**
```bash
curl -X GET 'https://192.168.8.158:8080/api/customer/ride/digital-payment' \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Accept: application/json' \
  -d 'trip_request_id=cf8c0637-395c-4f43-bc73-027d477ffaf4' \
  -d 'payment_method=kashier' \
  -d 'tips=10'
```

**What Happens:**
1. ✅ Validates authentication token
2. ✅ Validates trip exists and is unpaid
3. ✅ Updates trip with tips amount
4. ✅ Creates payment request in database
5. ✅ Generates secure signature using secret key
6. ✅ Redirects to Kashier payment page

**Response:**
```
HTTP 302 Redirect to:
https://payments.kashier.io/?merchantId=MID-xxx&amount=100&currency=EGP&orderId=123456&hash=xxx...
```

---

## 3. Wallet/Cash Payment

**Endpoint:**
```
GET /api/customer/ride/payment
```

**Purpose:** Process payment using wallet balance or mark as cash payment.

**Authentication:** Required (Bearer token)

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| trip_request_id | UUID | Yes | The trip/ride ID to pay for |
| payment_method | string | Yes | "wallet" or "cash" |
| tips | numeric | No | Tip amount (only for wallet) |

**Example:**
```bash
# Wallet payment with tips
curl -X GET 'https://192.168.8.158:8080/api/customer/ride/payment' \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -d 'trip_request_id=cf8c0637-395c-4f43-bc73-027d477ffaf4' \
  -d 'payment_method=wallet' \
  -d 'tips=5'

# Cash payment (no tips)
curl -X GET 'https://192.168.8.158:8080/api/customer/ride/payment' \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -d 'trip_request_id=cf8c0637-395c-4f43-bc73-027d477ffaf4' \
  -d 'payment_method=cash'
```

**Response:**
```json
{
  "response_code": "default_update_200",
  "message": "Payment successful"
}
```

**What Was Fixed:**
- ✅ Tips no longer overwritten to 0
- ✅ Database transactions wrapped in try-catch
- ✅ Proper rollback on errors

---

## 4. Submit Ride Review

**Endpoint:**
```
POST /api/customer/ride/submit-review
```

**Purpose:** Submit rating and feedback after completing a trip.

**Authentication:** Required (Bearer token)

**Body:**
```json
{
  "ride_request_id": "cf8c0637-395c-4f43-bc73-027d477ffaf4",
  "rating": 5,
  "feedback": "Great driver, smooth ride!"
}
```

**Example:**
```bash
curl -X POST 'https://192.168.8.158:8080/api/customer/ride/submit-review' \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
    "ride_request_id": "cf8c0637-395c-4f43-bc73-027d477ffaf4",
    "rating": 5,
    "feedback": "Excellent service"
  }'
```

---

## Payment Flow Diagram

```
┌──────────────┐
│   Customer   │
└──────┬───────┘
       │
       │ 1. GET /api/customer/config/get-payment-methods
       │
       ▼
┌──────────────────────────────────────┐
│ Returns: [kashier, stripe, paypal]   │
└──────┬───────────────────────────────┘
       │
       │ 2. GET /api/customer/ride/digital-payment
       │    ?trip_request_id=xxx&payment_method=kashier&tips=10
       │
       ▼
┌──────────────────────────────────────┐
│ ✓ Authenticate user                  │
│ ✓ Validate trip                      │
│ ✓ Save tips to database              │
│ ✓ Create payment_request             │
│ ✓ Generate signature (secret_key)    │
└──────┬───────────────────────────────┘
       │
       │ 3. REDIRECT to Kashier
       │
       ▼
┌──────────────────────────────────────┐
│   Kashier Payment Page               │
│   (Customer enters card details)     │
└──────┬───────────────────────────────┘
       │
       │ 4. Payment processed
       │
       ▼
┌──────────────────────────────────────┐
│ Kashier sends callback               │
│ GET /payment/kashier/callback        │
│   ?paymentStatus=SUCCESS             │
│   &transactionId=KSH123456           │
│   &orderId=123456                    │
│   &signature=xxx                     │
└──────┬───────────────────────────────┘
       │
       ▼
┌──────────────────────────────────────┐
│ ✓ Verify signature (secret_key)     │
│ ✓ Verify order ID matches            │
│ ✓ Update payment_request.is_paid    │
│ ✓ Store transaction_id               │
│ ✓ Update trip_request.payment_status│
│ ✓ Call hook function                 │
└──────┬───────────────────────────────┘
       │
       │ 5. Redirect to success page
       │
       ▼
┌──────────────────────────────────────┐
│   Payment Complete!                  │
│   Customer can now submit review     │
└──────────────────────────────────────┘
```

---

## Setup Instructions

### 1. Configure Kashier

Run the setup script:
```bash
php setup_kashier_payment.php
```

You'll need:
- **Merchant ID**: From Kashier dashboard (MID-xxxxx-xxx)
- **Public Key**: From Kashier account settings
- **Secret Key (API Key)**: From Kashier account settings ⚠️ IMPORTANT
- **Currency**: EGP (or your currency)
- **Mode**: test or live

### 2. Test Configuration

Run the test script:
```bash
php test_payment_flow.php
```

This verifies:
- ✅ Kashier is configured
- ✅ Routes are registered
- ✅ Database tables exist
- ✅ Signature generation works
- ✅ Authentication is required

### 3. Verify in Postman

Import the endpoint into Postman:
```
GET https://192.168.8.158:8080/api/customer/config/get-payment-methods
Headers:
  Authorization: Bearer YOUR_TOKEN
  Accept: application/json
```

You should see "kashier" in the response.

---

## Common Issues & Solutions

### Issue: "kashier" not in payment methods list

**Solution:**
```sql
-- Check if Kashier is configured
SELECT * FROM payment_settings WHERE key_name = 'kashier';

-- If not found, run setup script
php setup_kashier_payment.php

-- If found but inactive, activate it
UPDATE payment_settings SET is_active = 1 WHERE key_name = 'kashier';
```

### Issue: Payment fails with signature error

**Problem:** Using `public_key` instead of `secret_key`

**Solution:** ✅ Already fixed in code. Verify configuration:
```php
// Check in database
SELECT
  key_name,
  mode,
  JSON_EXTRACT(live_values, '$.secret_key') as secret_key,
  is_active
FROM payment_settings
WHERE key_name = 'kashier';
```

### Issue: Tips are lost during payment

**Solution:** ✅ Already fixed. The bug where `$trip->tips = 0` was removed.

### Issue: Unauthenticated payment requests allowed

**Solution:** ✅ Already fixed. Authentication middleware now required on digital-payment route.

---

## Monitoring & Debugging

### Check Payment Logs
```bash
# Real-time monitoring
tail -f storage/logs/laravel.log | grep -i payment

# Kashier-specific logs
tail -f storage/logs/laravel.log | grep -i kashier
```

### Check Payment Requests in Database
```sql
-- Recent payment requests
SELECT
  id,
  payment_method,
  payment_amount,
  is_paid,
  transaction_id,
  created_at
FROM payment_requests
WHERE payment_method = 'kashier'
ORDER BY created_at DESC
LIMIT 10;
```

### Check Trip Payment Status
```sql
-- Recent trips with Kashier payments
SELECT
  id,
  payment_method,
  payment_status,
  paid_fare,
  created_at
FROM trip_requests
WHERE payment_method = 'kashier'
ORDER BY created_at DESC
LIMIT 10;
```

---

## Testing Checklist

- [ ] Kashier appears in `/api/customer/config/get-payment-methods`
- [ ] Can initiate payment with `/api/customer/ride/digital-payment`
- [ ] Redirects to Kashier payment page with correct parameters
- [ ] Payment page shows correct amount (fare + tips)
- [ ] After payment, callback is received at `/payment/kashier/callback`
- [ ] Signature verification passes
- [ ] Trip status updates to "paid"
- [ ] Transaction ID is stored in `payment_requests.transaction_id`
- [ ] Tips are preserved correctly
- [ ] Can submit review after payment

---

## Security Notes

✅ **Fixed Issues:**
1. Authentication now required on digital payment endpoint
2. Signature generated using `secret_key` (not `public_key`)
3. Callback signature verified using `secret_key`
4. Order ID verification added
5. Transaction ID captured from Kashier
6. Database transactions wrapped in try-catch with rollback

⚠️ **Recommendations:**
1. Use HTTPS in production (not HTTP)
2. Store sensitive keys in `.env` file, not database
3. Add rate limiting on payment endpoints
4. Implement payment timeout handling
5. Add webhook verification tokens
6. Enable detailed logging for payment failures

---

## Support & Documentation

- **Kashier API Docs**: https://developers.kashier.io/
- **Kashier Merchant Dashboard**: https://merchant.kashier.io/
- **Payment Fixes Summary**: See `PAYMENT_FIXES_SUMMARY.md`
- **Test Scripts**:
  - `setup_kashier_payment.php` - Configure Kashier
  - `test_payment_flow.php` - Test payment system

---

## Quick Reference

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/api/customer/config/get-payment-methods` | GET | ✅ | List payment gateways |
| `/api/customer/ride/digital-payment` | GET | ✅ | Initiate Kashier payment |
| `/api/customer/ride/payment` | GET | ✅ | Wallet/cash payment |
| `/payment/kashier/pay` | GET | ❌ | Kashier payment page |
| `/payment/kashier/callback` | GET | ❌ | Kashier callback handler |
| `/api/customer/ride/submit-review` | POST | ✅ | Submit ride review |

✅ = Authentication Required
❌ = Public Endpoint

---

**Last Updated:** 2025-12-17
**Status:** ✅ All critical payment bugs fixed
