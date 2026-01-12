# Driver Wallet API - Complete Reference
**Date:** January 10, 2026  
**Base URL:** `https://smartline-it.com/api/driver`

---

## 🔐 Authentication

All endpoints require driver authentication:
```
Authorization: Bearer {driver_access_token}
Accept: application/json
```

---

## 📋 All Wallet Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wallet/balance` | Get wallet balance and earnings |
| GET | `/wallet/earnings` | Get transaction history |
| GET | `/wallet/summary` | Get earnings summary by period |
| GET | `/wallet/daily-balance` | Get daily earnings breakdown |
| POST | `/wallet/add-fund` | Add funds via payment gateway |

---

## 1️⃣ Get Wallet Balance

### Endpoint
```
GET /api/driver/wallet/balance
```

### Headers
```
Authorization: Bearer {token}
Accept: application/json
```

### Response (200 OK)
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "receivable_balance": 1500.50,
    "payable_balance": 0.00,
    "pending_balance": 0.00,
    "received_balance": 5000.00,
    "total_withdrawn": 3000.00,
    "wallet_balance": -25.99,
    "referral_earn": 150.00,
    "withdrawable_amount": 1500.50,
    "is_negative": true,
    "amount_owed": 25.99,
    "formatted_wallet_balance": "-25.99 ج.م",
    "formatted_amount_owed": "25.99 ج.م",
    "currency": "EGP",
    "formatted_receivable": "1,500.50 ج.م",
    "formatted_payable": "0.00 ج.م",
    "formatted_withdrawable": "1,500.50 ج.م"
  }
}
```

### Response Fields Explained

| Field | Type | Description |
|-------|------|-------------|
| `receivable_balance` | float | Earnings available for withdrawal |
| `payable_balance` | float | Amount owed to admin (not used in new system) |
| `pending_balance` | float | Withdrawal requests pending |
| `received_balance` | float | Total lifetime earnings received |
| `total_withdrawn` | float | Total amount withdrawn |
| `wallet_balance` | float | **Current wallet balance (can be negative)** |
| `referral_earn` | float | Earnings from referrals |
| `withdrawable_amount` | float | Amount available to withdraw now |
| `is_negative` | boolean | **True if wallet is negative** |
| `amount_owed` | float | **Absolute value when negative** |
| `formatted_wallet_balance` | string | Formatted balance with currency |
| `formatted_amount_owed` | string | Formatted amount owed (null if positive) |
| `currency` | string | Currency code (EGP) |
| `formatted_receivable` | string | Formatted receivable with currency |
| `formatted_payable` | string | Formatted payable with currency |
| `formatted_withdrawable` | string | Formatted withdrawable with currency |

### Example - Positive Balance
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "receivable_balance": 2500.00,
    "payable_balance": 0.00,
    "pending_balance": 0.00,
    "received_balance": 8000.00,
    "total_withdrawn": 5000.00,
    "wallet_balance": 150.00,
    "referral_earn": 200.00,
    "withdrawable_amount": 2500.00,
    "is_negative": false,
    "amount_owed": 0,
    "formatted_wallet_balance": "150.00 ج.م",
    "formatted_amount_owed": null,
    "currency": "EGP",
    "formatted_receivable": "2,500.00 ج.م",
    "formatted_payable": "0.00 ج.م",
    "formatted_withdrawable": "2,500.00 ج.م"
  }
}
```

### Example - Negative Balance (After Cash Trip Commission)
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "receivable_balance": 1200.00,
    "payable_balance": 0.00,
    "pending_balance": 0.00,
    "received_balance": 3500.00,
    "total_withdrawn": 2000.00,
    "wallet_balance": -25.99,
    "referral_earn": 50.00,
    "withdrawable_amount": 1200.00,
    "is_negative": true,
    "amount_owed": 25.99,
    "formatted_wallet_balance": "-25.99 ج.م",
    "formatted_amount_owed": "25.99 ج.م",
    "currency": "EGP",
    "formatted_receivable": "1,200.00 ج.م",
    "formatted_payable": "0.00 ج.م",
    "formatted_withdrawable": "1,200.00 ج.م"
  }
}
```

---

## 2️⃣ Get Earnings History

### Endpoint
```
GET /api/driver/wallet/earnings
```

### Query Parameters
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `limit` | integer | No | 20 | Items per page (1-100) |
| `offset` | integer | No | 1 | Page number |
| `type` | string | No | all | Filter: `all`, `earnings`, `payable`, `withdrawn` |

### Request Examples
```
GET /api/driver/wallet/earnings
GET /api/driver/wallet/earnings?limit=10&offset=1
GET /api/driver/wallet/earnings?type=earnings&limit=20
GET /api/driver/wallet/earnings?type=withdrawn
```

### Response (200 OK)
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "transactions": [
      {
        "id": "txn-uuid-1",
        "type": "debit",
        "amount": 30.00,
        "formatted_amount": "30.00 ج.م",
        "balance_after": -25.99,
        "formatted_balance": "-25.99 ج.م",
        "attribute": "cash_trip_commission_deducted",
        "account": "wallet_balance",
        "reference": "txn-uuid-ref",
        "created_at": "2026-01-10 14:30:00"
      },
      {
        "id": "txn-uuid-2",
        "type": "credit",
        "amount": 70.00,
        "formatted_amount": "70.00 ج.م",
        "balance_after": 1270.00,
        "formatted_balance": "1,270.00 ج.م",
        "attribute": "driver_earning",
        "account": "receivable_balance",
        "reference": "txn-uuid-1",
        "created_at": "2026-01-10 14:30:00"
      },
      {
        "id": "txn-uuid-3",
        "type": "credit",
        "amount": 100.00,
        "formatted_amount": "100.00 ج.م",
        "balance_after": 1200.00,
        "formatted_balance": "1,200.00 ج.م",
        "attribute": "driver_earning",
        "account": "receivable_balance",
        "reference": null,
        "created_at": "2026-01-10 12:15:00"
      },
      {
        "id": "txn-uuid-4",
        "type": "credit",
        "amount": 50.00,
        "formatted_amount": "50.00 ج.م",
        "balance_after": 150.00,
        "formatted_balance": "150.00 ج.م",
        "attribute": "wallet_add_fund",
        "account": "wallet_balance",
        "reference": null,
        "created_at": "2026-01-09 18:20:00"
      },
      {
        "id": "txn-uuid-5",
        "type": "debit",
        "amount": 500.00,
        "formatted_amount": "500.00 ج.م",
        "balance_after": 700.00,
        "formatted_balance": "700.00 ج.م",
        "attribute": "pending_withdrawn",
        "account": "pending_withdraw_balance",
        "reference": "withdraw-uuid",
        "created_at": "2026-01-08 10:00:00"
      }
    ],
    "total": 45,
    "limit": 20,
    "offset": 1
  }
}
```

### Transaction Types
| Type | Description |
|------|-------------|
| `credit` | Money added (earnings, top-up, referral) |
| `debit` | Money deducted (commission, withdrawal) |

### Transaction Attributes
| Attribute | Description |
|-----------|-------------|
| `driver_earning` | Trip earnings |
| `cash_trip_commission_deducted` | Commission deducted from wallet |
| `admin_commission` | Commission owed |
| `wallet_add_fund` | Wallet top-up |
| `pending_withdrawn` | Withdrawal request |
| `withdraw_request_accepted` | Withdrawal completed |
| `referral_earning` | Referral bonus |
| `point_conversion` | Loyalty points converted |

### Response - Filtered by Type (earnings)
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "transactions": [
      {
        "id": "txn-uuid-2",
        "type": "credit",
        "amount": 70.00,
        "formatted_amount": "70.00 ج.م",
        "balance_after": 1270.00,
        "formatted_balance": "1,270.00 ج.م",
        "attribute": "driver_earning",
        "account": "receivable_balance",
        "reference": "txn-uuid-1",
        "created_at": "2026-01-10 14:30:00"
      }
    ],
    "total": 25,
    "limit": 20,
    "offset": 1
  }
}
```

---

## 3️⃣ Get Earnings Summary

### Endpoint
```
GET /api/driver/wallet/summary
```

### Query Parameters
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `period` | string | No | month | Period: `today`, `week`, `month`, `year`, `all` |

### Request Examples
```
GET /api/driver/wallet/summary
GET /api/driver/wallet/summary?period=today
GET /api/driver/wallet/summary?period=week
GET /api/driver/wallet/summary?period=month
GET /api/driver/wallet/summary?period=year
GET /api/driver/wallet/summary?period=all
```

### Response (200 OK) - Today
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "period": "today",
    "total_earnings": 350.00,
    "total_deductions": 0.00,
    "net_earnings": 350.00,
    "total_payable": 105.00,
    "transaction_count": 5,
    "formatted_earnings": "350.00 ج.م",
    "formatted_net": "350.00 ج.م",
    "currency": "EGP"
  }
}
```

### Response (200 OK) - Week
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "period": "week",
    "total_earnings": 2450.00,
    "total_deductions": 0.00,
    "net_earnings": 2450.00,
    "total_payable": 735.00,
    "transaction_count": 35,
    "formatted_earnings": "2,450.00 ج.م",
    "formatted_net": "2,450.00 ج.م",
    "currency": "EGP"
  }
}
```

### Response (200 OK) - Month
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "period": "month",
    "total_earnings": 8750.00,
    "total_deductions": 0.00,
    "net_earnings": 8750.00,
    "total_payable": 2625.00,
    "transaction_count": 125,
    "formatted_earnings": "8,750.00 ج.م",
    "formatted_net": "8,750.00 ج.م",
    "currency": "EGP"
  }
}
```

### Response (200 OK) - Year
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "period": "year",
    "total_earnings": 105000.00,
    "total_deductions": 0.00,
    "net_earnings": 105000.00,
    "total_payable": 31500.00,
    "transaction_count": 1500,
    "formatted_earnings": "105,000.00 ج.م",
    "formatted_net": "105,000.00 ج.م",
    "currency": "EGP"
  }
}
```

### Summary Fields Explained
| Field | Description |
|-------|-------------|
| `period` | Time period for summary |
| `total_earnings` | Total credits to receivable_balance |
| `total_deductions` | Total debits from receivable_balance |
| `net_earnings` | total_earnings - total_deductions |
| `total_payable` | Commissions tracked in period |
| `transaction_count` | Number of transactions |
| `formatted_earnings` | Formatted earnings with currency |
| `formatted_net` | Formatted net earnings with currency |
| `currency` | Currency code |

---

## 4️⃣ Get Daily Balance

### Endpoint
```
GET /api/driver/wallet/daily-balance
```

### Query Parameters
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `date` | string | No | today | Date in format: YYYY-MM-DD |
| `include_hourly` | boolean | No | false | Include hourly breakdown |

### Request Examples
```
GET /api/driver/wallet/daily-balance
GET /api/driver/wallet/daily-balance?date=2026-01-10
GET /api/driver/wallet/daily-balance?date=2026-01-09&include_hourly=true
```

### Response (200 OK) - Without Hourly
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "date": "2026-01-10",
    "day_name": "Friday",
    "is_today": true,
    "total_earnings": 450.00,
    "formatted_earnings": "450.00 ج.م",
    "total_trips": 6,
    "total_payable": 135.00,
    "formatted_payable": "135.00 ج.م",
    "net_earnings": 315.00,
    "formatted_net": "315.00 ج.م",
    "transaction_count": 12,
    "currency": "EGP"
  }
}
```

### Response (200 OK) - With Hourly Breakdown
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "date": "2026-01-10",
    "day_name": "Friday",
    "is_today": true,
    "total_earnings": 450.00,
    "formatted_earnings": "450.00 ج.م",
    "total_trips": 6,
    "total_payable": 135.00,
    "formatted_payable": "135.00 ج.م",
    "net_earnings": 315.00,
    "formatted_net": "315.00 ج.م",
    "transaction_count": 12,
    "currency": "EGP",
    "hourly_breakdown": [
      {
        "hour": "08:00",
        "earnings": 75.00,
        "formatted_earnings": "75.00 ج.م",
        "trips": 1,
        "transactions": 2
      },
      {
        "hour": "10:00",
        "earnings": 120.00,
        "formatted_earnings": "120.00 ج.م",
        "trips": 2,
        "transactions": 4
      },
      {
        "hour": "14:00",
        "earnings": 90.00,
        "formatted_earnings": "90.00 ج.م",
        "trips": 1,
        "transactions": 2
      },
      {
        "hour": "16:00",
        "earnings": 165.00,
        "formatted_earnings": "165.00 ج.م",
        "trips": 2,
        "transactions": 4
      }
    ]
  }
}
```

### Daily Balance Fields
| Field | Description |
|-------|-------------|
| `date` | Date in YYYY-MM-DD format |
| `day_name` | Day of week (Monday, Tuesday, etc.) |
| `is_today` | True if selected date is today |
| `total_earnings` | Total earnings for the day |
| `formatted_earnings` | Formatted earnings |
| `total_trips` | Number of completed trips |
| `total_payable` | Commissions for the day |
| `formatted_payable` | Formatted payable |
| `net_earnings` | Earnings minus payable |
| `formatted_net` | Formatted net earnings |
| `transaction_count` | Number of transactions |
| `currency` | Currency code |
| `hourly_breakdown` | Array of hourly data (if requested) |

---

## 5️⃣ Add Funds to Wallet

### Endpoint
```
POST /api/driver/wallet/add-fund
```

### Headers
```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

### Request Body
```json
{
  "amount": 100.00,
  "payment_method": "kashier",
  "redirect_url": "smartline://driver/wallet/callback"
}
```

### Request Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `amount` | float | Yes | Amount to add (min: 10, max: 50000) |
| `payment_method` | string | Yes | Payment gateway (see list below) |
| `redirect_url` | string | No | Return URL after payment |

### Supported Payment Methods
- `kashier` (Primary for Egypt)
- `ssl_commerz`
- `stripe`
- `paypal`
- `razor_pay`
- `paystack`
- `senang_pay`
- `paymob_accept`
- `flutterwave`
- `paytm`
- `paytabs`
- `liqpay`
- `mercadopago`
- `bkash`
- `fatoorah`
- `xendit`
- `amazon_pay`
- `iyzi_pay`
- `hyper_pay`
- `foloosi`
- `ccavenue`
- `pvit`
- `moncash`
- `thawani`
- `tap`
- `viva_wallet`
- `hubtel`
- `maxicash`
- `esewa`
- `swish`
- `momo`
- `payfast`
- `worldpay`
- `sixcash`

### Response (200 OK)
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "payment_url": "https://test.kashier.io/payment/MIyOMxgXkobYR...",
    "amount": 100.00,
    "formatted_amount": "100.00 ج.م",
    "payment_method": "kashier",
    "message": "Redirect to payment gateway"
  }
}
```

### Error - Amount Too Low (400)
```json
{
  "response_code": "min_amount_error_400",
  "message": "Minimum amount to add is 10.00 ج.م"
}
```

### Error - Amount Too High (400)
```json
{
  "response_code": "max_amount_error_400",
  "message": "Maximum amount to add is 50,000.00 ج.م"
}
```

### Error - Validation Failed (400)
```json
{
  "response_code": "default_400",
  "message": "Validation failed",
  "errors": [
    {
      "error_code": "amount",
      "message": "The amount field is required."
    },
    {
      "error_code": "payment_method",
      "message": "The selected payment method is invalid."
    }
  ]
}
```

### Payment Flow
1. Driver calls `/wallet/add-fund` with amount and payment method
2. API returns `payment_url`
3. Driver redirected to payment gateway (Kashier, etc.)
4. Driver completes payment
5. Gateway redirects back to `redirect_url` or default
6. Webhook updates wallet balance
7. Driver's wallet balance updated

---

## 🧪 Testing Examples

### Test 1: Get Balance
```bash
curl -X GET "https://smartline-it.com/api/driver/wallet/balance" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Test 2: Get Earnings (Last 10)
```bash
curl -X GET "https://smartline-it.com/api/driver/wallet/earnings?limit=10" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Test 3: Get Monthly Summary
```bash
curl -X GET "https://smartline-it.com/api/driver/wallet/summary?period=month" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Test 4: Get Today's Balance with Hourly
```bash
curl -X GET "https://smartline-it.com/api/driver/wallet/daily-balance?include_hourly=true" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Test 5: Add 100 EGP
```bash
curl -X POST "https://smartline-it.com/api/driver/wallet/add-fund" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 100.00,
    "payment_method": "kashier"
  }'
```

---

## 📱 Flutter Integration Examples

### Get Balance
```dart
Future<Map<String, dynamic>> getWalletBalance() async {
  final response = await http.get(
    Uri.parse('https://smartline-it.com/api/driver/wallet/balance'),
    headers: {
      'Authorization': 'Bearer $token',
      'Accept': 'application/json',
    },
  );
  
  if (response.statusCode == 200) {
    return jsonDecode(response.body)['data'];
  }
  throw Exception('Failed to load balance');
}
```

### Add Funds
```dart
Future<String> addFunds(double amount, String method) async {
  final response = await http.post(
    Uri.parse('https://smartline-it.com/api/driver/wallet/add-fund'),
    headers: {
      'Authorization': 'Bearer $token',
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    },
    body: jsonEncode({
      'amount': amount,
      'payment_method': method,
      'redirect_url': 'smartline://driver/wallet/callback',
    }),
  );
  
  if (response.statusCode == 200) {
    final data = jsonDecode(response.body)['data'];
    return data['payment_url'];
  }
  throw Exception('Failed to add funds');
}
```

---

## ⚠️ Important Notes

1. **Negative Wallet Balance**: `wallet_balance` can be negative after cash trip commissions are deducted
2. **Withdrawable Amount**: Calculated as `receivable_balance - payable_balance` (minimum 0)
3. **Currency**: Default is EGP (Egyptian Pound)
4. **Formatted Amounts**: All amounts have formatted versions with currency symbol
5. **Caching**: Daily balance is cached for 30 minutes for performance
6. **Payment Limits**: Min 10 EGP, Max 50,000 EGP per top-up

---

## 📞 Support

For questions: support@smartline-it.com

---

**Last Updated:** January 10, 2026  
**Status:** ✅ Complete and Tested
