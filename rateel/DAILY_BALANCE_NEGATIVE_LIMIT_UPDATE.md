# Daily Balance Endpoint - Negative Balance Limit Update

**Date:** January 12, 2026  
**Updated Endpoint:** `GET /api/driver/wallet/daily-balance`

---

## 📝 Summary

Added negative balance limit information to the daily balance endpoint to match the structure available in the `/api/driver/wallet/balance` endpoint.

---

## 🔧 Changes Made

**File Updated:** `Modules/UserManagement/Http/Controllers/Api/New/Driver/DriverWalletController.php`  
**Method:** `dailyBalance()` (Lines 315-364)

### New Fields Added

```json
{
  "data": {
    "date": "2026-01-12",
    "day_name": "Monday",
    "is_today": true,
    "total_earnings": 500.00,
    "total_trips": 5,
    "net_earnings": -150.00,
    
    // ✨ NEW FIELDS ADDED ✨
    "wallet_balance": -60.00,
    "formatted_wallet_balance": "-60.00 EGP",
    "is_wallet_negative": true,
    "amount_owed": 60.00,
    "formatted_amount_owed": "60.00 EGP",
    
    "negative_balance_limit": {
      "max_limit": 72.00,
      "formatted_max_limit": "72.00 EGP",
      "used": 60.00,
      "formatted_used": "60.00 EGP",
      "remaining": 12.00,
      "formatted_remaining": "12.00 EGP",
      "percentage_used": 83.33,
      "is_near_limit": true,
      "warning_threshold": 54.00,
      "formatted_warning_threshold": "54.00 EGP"
    }
  }
}
```

---

## 📊 Field Descriptions

| Field | Type | Description |
|-------|------|-------------|
| `wallet_balance` | float | Current wallet balance (can be negative) |
| `formatted_wallet_balance` | string | Formatted wallet balance with currency |
| `is_wallet_negative` | boolean | Whether wallet balance is negative |
| `amount_owed` | float | Absolute value of negative balance (amount driver owes) |
| `formatted_amount_owed` | string | Formatted amount owed with currency (null if positive) |

### Negative Balance Limit Object

| Field | Type | Description |
|-------|------|-------------|
| `max_limit` | float | Maximum negative balance allowed (from `users.max_negative_balance`) |
| `formatted_max_limit` | string | Formatted max limit with currency |
| `used` | float | Amount of negative balance used (absolute value) |
| `formatted_used` | string | Formatted used amount with currency |
| `remaining` | float | Remaining negative balance available before account deactivation |
| `formatted_remaining` | string | Formatted remaining amount with currency |
| `percentage_used` | float | Percentage of limit used (0-100) |
| `is_near_limit` | boolean | `true` if used ≥ 75% of max limit (warning threshold) |
| `warning_threshold` | float | 75% of max limit (triggers warning) |
| `formatted_warning_threshold` | string | Formatted warning threshold with currency |

---

## 🎯 Business Logic

1. **Max Negative Balance:** Retrieved from `users.max_negative_balance` (default: 200.00)
2. **Warning Threshold:** 75% of max limit (e.g., 150.00 for 200.00 max)
3. **Is Near Limit:** `true` when used balance ≥ warning threshold
4. **Percentage Used:** Calculated as `(used / max_limit) × 100`
5. **Remaining:** Calculated as `max(0, max_limit - used)`

---

## 🧪 Test Cases

### Example 1: Driver with Negative Balance (Warning Zone)

**Input:**
- Wallet Balance: -60.00 EGP
- Max Negative Balance: 72.00 EGP

**Output:**
```json
{
  "wallet_balance": -60.00,
  "is_wallet_negative": true,
  "amount_owed": 60.00,
  "negative_balance_limit": {
    "max_limit": 72.00,
    "used": 60.00,
    "remaining": 12.00,
    "percentage_used": 83.33,
    "is_near_limit": true,
    "warning_threshold": 54.00
  }
}
```

### Example 2: Driver with Positive Balance

**Input:**
- Wallet Balance: 100.00 EGP
- Max Negative Balance: 200.00 EGP

**Output:**
```json
{
  "wallet_balance": 100.00,
  "is_wallet_negative": false,
  "amount_owed": 0.00,
  "formatted_amount_owed": null,
  "negative_balance_limit": {
    "max_limit": 200.00,
    "used": 0.00,
    "remaining": 200.00,
    "percentage_used": 0.00,
    "is_near_limit": false,
    "warning_threshold": 150.00
  }
}
```

### Example 3: Driver at Exact Warning Threshold (75%)

**Input:**
- Wallet Balance: -150.00 EGP
- Max Negative Balance: 200.00 EGP

**Output:**
```json
{
  "wallet_balance": -150.00,
  "is_wallet_negative": true,
  "amount_owed": 150.00,
  "negative_balance_limit": {
    "max_limit": 200.00,
    "used": 150.00,
    "remaining": 50.00,
    "percentage_used": 75.00,
    "is_near_limit": true,
    "warning_threshold": 150.00
  }
}
```

---

## 🔌 API Endpoints

### GET /api/driver/wallet/daily-balance

**Parameters:**
- `date` (optional): Date in format `Y-m-d` (default: today)
- `include_hourly` (optional): Include hourly breakdown (boolean)

**Example Request:**
```bash
curl -X GET "https://smartline-it.com/api/driver/wallet/daily-balance?date=2026-01-12" \
  -H "Authorization: Bearer YOUR_DRIVER_TOKEN" \
  -H "Accept: application/json"
```

**Example Response:**
```json
{
  "response_code": "default_200",
  "message": "Success",
  "data": {
    "date": "2026-01-12",
    "day_name": "Monday",
    "is_today": true,
    "total_earnings": 500.00,
    "formatted_earnings": "500.00 EGP",
    "total_trips": 10,
    "total_payable": 75.00,
    "formatted_payable": "75.00 EGP",
    "net_earnings": 425.00,
    "formatted_net": "425.00 EGP",
    "has_negative_balance": false,
    "negative_balance": null,
    "transaction_count": 15,
    "currency": "EGP",
    
    "wallet_balance": -60.00,
    "formatted_wallet_balance": "-60.00 EGP",
    "is_wallet_negative": true,
    "amount_owed": 60.00,
    "formatted_amount_owed": "60.00 EGP",
    
    "negative_balance_limit": {
      "max_limit": 72.00,
      "formatted_max_limit": "72.00 EGP",
      "used": 60.00,
      "formatted_used": "60.00 EGP",
      "remaining": 12.00,
      "formatted_remaining": "12.00 EGP",
      "percentage_used": 83.33,
      "is_near_limit": true,
      "warning_threshold": 54.00,
      "formatted_warning_threshold": "54.00 EGP"
    }
  }
}
```

---

## 🔗 Related Endpoints

| Endpoint | Description |
|----------|-------------|
| `GET /api/driver/wallet/balance` | Get current wallet balance (already has this structure) |
| `GET /api/driver/wallet/daily-balance` | Get daily balance (✅ NOW UPDATED) |
| `GET /api/driver/wallet/earnings` | Get earnings transaction history |
| `GET /api/driver/wallet/summary` | Get earnings summary by period |

---

## ⚠️ Important Notes

1. **Caching:** Daily balance is cached for 30 minutes. Clear cache if testing: 
   ```bash
   php artisan cache:forget "driver_daily_balance_{driver_id}_{date}_0"
   ```

2. **Real-time Updates:** The `wallet_balance` reflects the **current** wallet balance, not the historical balance at end of the specified date.

3. **Warning Notifications:** When wallet balance reaches 75% or 100% of limit, automatic notifications are sent via `UserAccountObserver`.

4. **Account Deactivation:** When balance reaches 100% of max limit, the driver account is automatically deactivated (`is_active = 0`).

---

## 📱 Flutter App Integration

The Flutter driver app can now use this endpoint to:
- Display daily earnings alongside current wallet status
- Show negative balance warnings in daily summaries
- Alert drivers when approaching limit while viewing daily performance
- Display progress bar showing how close to deactivation threshold

---

## ✅ Testing

Tested with driver phone: `+201208673028`
- Current wallet balance: -60.00 EGP
- Max negative limit: 72.00 EGP
- Percentage used: 83.33%
- Is near limit: ✅ YES (over 75%)

**Status:** ✅ Working as expected
