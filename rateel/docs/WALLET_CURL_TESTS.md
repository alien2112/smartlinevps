# Customer Wallet API - curl Test Guide

## ✅ Wallet Balance Check Implementation

**YES!** The wallet system has a complete balance check before allowing trip payments.

### Security Features:
- ✅ Checks if `wallet_balance < totalAmount` before payment
- ✅ Uses `lockForUpdate()` to prevent race conditions (double-spending)
- ✅ Includes tips in total calculation: `total = paid_fare + tips`
- ✅ Returns proper error response: `insufficient_fund_403` (HTTP 403)
- ✅ Uses database transactions with rollback on failure

### Code Location:
`Modules/TripManagement/Http/Controllers/Api/New/PaymentController.php` (lines 58-68)

---

## API Endpoints

| Feature | Method | Endpoint | Auth Required |
|---------|--------|----------|---------------|
| Login | POST | `/api/customer/auth/login` | No |
| View Balance | GET | `/api/customer/wallet/balance` | Yes |
| Add Funds | POST | `/api/customer/wallet/add-fund` | Yes |
| Transaction History | GET | `/api/customer/wallet/transactions` | Yes |
| Pay Trip | POST | `/api/trip/payment` | Yes |

---

## Test Instructions

### Step 1: Customer Login

```bash
curl.exe -X POST "https://smartline-it.com/api/customer/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"phone\": \"+201234567890\", \"password\": \"yourpassword\"}"
```

**Expected Response:**
```json
{
  "response_code": "default_200",
  "message": "Login successful",
  "data": {
    "token": "1|abc123...",
    "user": { ... }
  }
}
```

**Save the token** from the response for next steps.

---

### Step 2: View Wallet Balance

```bash
curl.exe -X GET "https://smartline-it.com/api/customer/wallet/balance" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"
```

**Expected Response:**
```json
{
  "response_code": "default_200",
  "data": {
    "wallet_balance": 150.50,
    "currency": "EGP",
    "formatted_balance": "150.50 EGP"
  }
}
```

---

### Step 3: Add Money to Wallet (Kashier Only)

⚠️ **IMPORTANT**: Only Kashier payment gateway is allowed for adding funds to wallet.

```bash
curl.exe -X POST "https://smartline-it.com/api/customer/wallet/add-fund" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{\"amount\": 100, \"payment_method\": \"kashier\", \"redirect_url\": \"https://smartline-it.com/payment/callback\"}"
```

**Expected Response:**
```json
{
  "response_code": "default_200",
  "message": "Redirect to payment gateway",
  "data": {
    "payment_url": "https://kashier.io/payment/..."
  }
}
```

**Supported Payment Methods for Add Funds:**
- ✅ `kashier` (Primary)
- ✅ `ssl_commerz`
- ✅ `stripe`
- ✅ `paypal`
- ✅ `razor_pay`
- ✅ `paystack`
- ✅ And 30+ other payment gateways

**Validation Rules:**
- Minimum amount: 10 EGP (configurable via `min_wallet_add_fund_amount`)
- Maximum amount: 50,000 EGP (configurable via `max_wallet_add_fund_amount`)

---

### Step 4: View Transaction History

```bash
curl.exe -X GET "https://smartline-it.com/api/customer/wallet/transactions?limit=10&offset=1&type=all" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"
```

**Query Parameters:**
- `limit` (optional): Number of transactions per page (1-100, default: 20)
- `offset` (optional): Page number (default: 1)
- `type` (optional): Filter by type (`all`, `credit`, `debit`, default: `all`)

**Expected Response:**
```json
{
  "response_code": "default_200",
  "data": {
    "transactions": [
      {
        "id": "uuid-123",
        "type": "credit",
        "amount": 100.00,
        "balance_after": 250.50,
        "description": "Wallet top-up",
        "created_at": "2026-01-01T10:30:00Z"
      },
      {
        "id": "uuid-124",
        "type": "debit",
        "amount": 50.00,
        "balance_after": 150.50,
        "description": "Trip payment",
        "created_at": "2026-01-01T11:00:00Z"
      }
    ],
    "total": 45,
    "current_page": 1,
    "per_page": 10
  }
}
```

---

### Step 5: Pay for Trip Using Wallet

```bash
curl.exe -X POST "https://smartline-it.com/api/trip/payment" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d "{\"trip_request_id\": \"YOUR_TRIP_ID\", \"payment_method\": \"wallet\", \"tips\": 10}"
```

**Request Body:**
- `trip_request_id` (required): The trip ID
- `payment_method` (required): Must be `wallet`
- `tips` (optional): Additional tip amount (only deducted if using wallet)

**Expected Response (Success):**
```json
{
  "response_code": "default_200",
  "message": "Payment successful",
  "data": {
    "trip_id": "...",
    "payment_status": "paid",
    "paid_fare": 60.00,
    "wallet_balance_after": 90.50
  }
}
```

**Expected Response (Insufficient Balance):**
```json
{
  "response_code": "insufficient_fund_403",
  "message": "Insufficient wallet balance"
}
```

**HTTP Status Code:** 403 Forbidden

---

## Test Scenarios

### Scenario 1: Successful Wallet Payment
1. Customer has balance: 150 EGP
2. Trip fare: 50 EGP
3. Tips: 10 EGP
4. Total: 60 EGP
5. ✅ Payment succeeds
6. New balance: 90 EGP

### Scenario 2: Insufficient Balance
1. Customer has balance: 40 EGP
2. Trip fare: 50 EGP
3. Tips: 10 EGP
4. Total: 60 EGP
5. ❌ Payment fails with `insufficient_fund_403`
6. Balance unchanged: 40 EGP

### Scenario 3: Race Condition Prevention
1. Customer has balance: 100 EGP
2. Two simultaneous payment requests for 60 EGP each
3. ✅ First request succeeds (balance: 40 EGP)
4. ❌ Second request fails with `insufficient_fund_403`
5. **Protected by `lockForUpdate()`**

---

## Security Implementation Details

### Balance Check Code
```php
// From PaymentController.php (lines 58-68)
$totalAmount = $trip->paid_fare + $tips;

if ($request->payment_method == 'wallet') {
    // Re-fetch with lock to prevent race conditions
    $customerAccount = \Modules\UserManagement\Entities\UserAccount::where('user_id', $trip->customer_id)
        ->lockForUpdate()
        ->first();
        
    if (!$customerAccount || $customerAccount->wallet_balance < $totalAmount) {
        DB::rollBack();
        return response()->json(responseFormatter(INSUFFICIENT_FUND_403), 403);
    }
}
```

### Key Security Features:
1. **Atomic Transaction**: Entire payment wrapped in `DB::beginTransaction()` / `DB::commit()`
2. **Pessimistic Locking**: `lockForUpdate()` prevents concurrent modifications
3. **Balance Verification**: Checks `wallet_balance < totalAmount` before deduction
4. **Rollback on Failure**: `DB::rollBack()` if insufficient funds
5. **TOCTOU Prevention**: Lock acquired before balance check (Time-of-check to Time-of-use)

---

## Common Issues & Solutions

### Issue 1: "Insufficient funds" but balance shows enough
**Cause:** Tips not included in displayed balance check  
**Solution:** Total = `trip_fare + tips`, ensure balance covers both

### Issue 2: Payment URL not generated
**Cause:** Invalid payment method or gateway not configured  
**Solution:** Verify payment gateway is enabled in admin settings

### Issue 3: Transaction not appearing in history
**Cause:** Payment still pending or failed  
**Solution:** Check payment status, only completed payments appear in history

---

## Production Checklist

- ✅ Balance check implemented with database lock
- ✅ Race condition prevention (pessimistic locking)
- ✅ Proper error responses (403 for insufficient funds)
- ✅ Transaction rollback on failure
- ✅ Tips included in total calculation
- ✅ Kashier payment gateway for add-funds
- ✅ Transaction history with pagination
- ✅ Minimum/Maximum amount validation
- ✅ Audit trail for all wallet operations

---

## Notes

1. **Add Funds**: Customer must complete payment at the payment gateway URL. The wallet balance is updated via webhook callback after successful payment.

2. **Payment Methods**: While 30+ payment gateways are supported for adding funds, trip payments support `wallet`, `cash`, and other configured methods.

3. **Concurrency**: The system uses pessimistic locking to prevent double-spending in high-concurrency scenarios.

4. **Idempotency**: Payment operations are idempotent - retrying the same payment won't charge twice.

5. **Webhooks**: Payment gateway callbacks are handled asynchronously to update wallet balance after successful payment.
