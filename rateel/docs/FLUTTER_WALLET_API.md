# Smartline Wallet API - Flutter Integration Guide

## Base URL
```
https://smartline-it.com/api
```

## Authentication
All endpoints require Bearer token authentication:
```dart
headers: {
  'Authorization': 'Bearer $accessToken',
  'Accept': 'application/json',
  'Content-Type': 'application/json',
}
```

---

## Customer Wallet Endpoints

### 1. Get Wallet Balance
Returns the current wallet balance for the authenticated customer.

**Endpoint:** `GET /customer/wallet/balance`

**Response:**
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "wallet_balance": 100.00,
    "currency": "EGP",
    "formatted_balance": "EGP 100"
  }
}
```

**Flutter Code:**
```dart
Future<WalletBalance> getWalletBalance() async {
  final response = await http.get(
    Uri.parse('$baseUrl/customer/wallet/balance'),
    headers: _authHeaders(),
  );

  if (response.statusCode == 200) {
    final data = jsonDecode(response.body)['data'];
    return WalletBalance.fromJson(data);
  }
  throw Exception('Failed to get balance');
}

class WalletBalance {
  final double balance;
  final String currency;
  final String formattedBalance;

  WalletBalance.fromJson(Map<String, dynamic> json)
    : balance = (json['wallet_balance'] as num).toDouble(),
      currency = json['currency'],
      formattedBalance = json['formatted_balance'];
}
```

---

### 2. Add Funds to Wallet
Initiates a wallet top-up via payment gateway.

**Endpoint:** `POST /customer/wallet/add-fund`

**Request Body:**
```json
{
  "amount": 100.00,
  "payment_method": "kashier"
}
```

**Supported Payment Methods:**
- `kashier`
- `stripe`
- `paypal`
- `paymob_accept`
- `paystack`
- `flutterwave`

**Response:**
```json
{
  "response_code": "default_200",
  "message": "Redirect to payment gateway",
  "data": {
    "payment_url": "https://checkout.kashier.io/...",
    "message": "Redirect to payment gateway"
  }
}
```

**Flutter Code:**
```dart
Future<String> addFunds({
  required double amount,
  required String paymentMethod,
}) async {
  final response = await http.post(
    Uri.parse('$baseUrl/customer/wallet/add-fund'),
    headers: _authHeaders(),
    body: jsonEncode({
      'amount': amount,
      'payment_method': paymentMethod,
    }),
  );

  if (response.statusCode == 200) {
    final data = jsonDecode(response.body)['data'];
    return data['payment_url'];
  }
  throw Exception('Failed to initiate payment');
}

// Launch payment URL in WebView
void launchPayment(String paymentUrl) {
  // Use url_launcher or in-app WebView
  launchUrl(Uri.parse(paymentUrl));
}
```

---

### 3. Get Transaction History
Returns paginated wallet transaction history.

**Endpoint:** `GET /customer/wallet/transactions`

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| limit | int | No | 20 | Items per page (max 100) |
| offset | int | No | 1 | Page number |
| type | string | No | all | Filter: `all`, `credit`, `debit` |

**Response:**
```json
{
  "response_code": "default_200",
  "data": {
    "transactions": [
      {
        "id": "uuid-here",
        "type": "credit",
        "amount": 100.00,
        "formatted_amount": "EGP 100",
        "balance_after": 200.00,
        "formatted_balance": "EGP 200",
        "attribute": "wallet_top_up",
        "reference": "payment_123",
        "created_at": "2025-01-01T10:30:00Z"
      }
    ],
    "total": 25,
    "limit": 20,
    "offset": 1
  }
}
```

**Flutter Code:**
```dart
Future<TransactionHistory> getTransactionHistory({
  int limit = 20,
  int offset = 1,
  String type = 'all',
}) async {
  final response = await http.get(
    Uri.parse('$baseUrl/customer/wallet/transactions?limit=$limit&offset=$offset&type=$type'),
    headers: _authHeaders(),
  );

  if (response.statusCode == 200) {
    return TransactionHistory.fromJson(jsonDecode(response.body)['data']);
  }
  throw Exception('Failed to get transactions');
}

class WalletTransaction {
  final String id;
  final String type;
  final double amount;
  final String formattedAmount;
  final double balanceAfter;
  final String attribute;
  final String? reference;
  final DateTime createdAt;

  WalletTransaction.fromJson(Map<String, dynamic> json)
    : id = json['id'],
      type = json['type'],
      amount = (json['amount'] as num).toDouble(),
      formattedAmount = json['formatted_amount'],
      balanceAfter = (json['balance_after'] as num).toDouble(),
      attribute = json['attribute'],
      reference = json['reference'],
      createdAt = DateTime.parse(json['created_at']);
}

class TransactionHistory {
  final List<WalletTransaction> transactions;
  final int total;
  final int limit;
  final int offset;

  TransactionHistory.fromJson(Map<String, dynamic> json)
    : transactions = (json['transactions'] as List)
        .map((t) => WalletTransaction.fromJson(t))
        .toList(),
      total = json['total'],
      limit = json['limit'],
      offset = json['offset'];
}
```

---

## Coupon Endpoints

### 4. Get Available Coupons
Returns list of available coupons for the customer.

**Endpoint:** `GET /v1/coupons/available`

**Response:**
```json
{
  "response_code": "default_200",
  "data": [
    {
      "id": "uuid",
      "code": "SAVE20",
      "discount_type": "percentage",
      "discount_value": 20,
      "min_fare": 50,
      "max_discount": 100,
      "valid_until": "2025-12-31",
      "description": "20% off your next ride"
    }
  ]
}
```

**Flutter Code:**
```dart
Future<List<Coupon>> getAvailableCoupons() async {
  final response = await http.get(
    Uri.parse('$baseUrl/v1/coupons/available'),
    headers: _authHeaders(),
  );

  if (response.statusCode == 200) {
    final data = jsonDecode(response.body)['data'] as List;
    return data.map((c) => Coupon.fromJson(c)).toList();
  }
  throw Exception('Failed to get coupons');
}

class Coupon {
  final String id;
  final String code;
  final String discountType;
  final double discountValue;
  final double minFare;
  final double? maxDiscount;
  final DateTime validUntil;
  final String? description;

  Coupon.fromJson(Map<String, dynamic> json)
    : id = json['id'],
      code = json['code'],
      discountType = json['discount_type'],
      discountValue = (json['discount_value'] as num).toDouble(),
      minFare = (json['min_fare'] as num).toDouble(),
      maxDiscount = json['max_discount'] != null
          ? (json['max_discount'] as num).toDouble()
          : null,
      validUntil = DateTime.parse(json['valid_until']),
      description = json['description'];
}
```

---

### 5. Validate Coupon
Validates a coupon code and calculates discount.

**Endpoint:** `POST /v1/coupons/validate`

**Request Body:**
```json
{
  "code": "SAVE20",
  "fare": 100.00,
  "city_id": "zone-uuid",
  "service_type": "ride"
}
```

**Response (Success):**
```json
{
  "response_code": "default_200",
  "data": {
    "valid": true,
    "coupon": {
      "id": "uuid",
      "code": "SAVE20",
      "discount_type": "percentage",
      "discount_value": 20
    },
    "discount_amount": 20.00,
    "final_fare": 80.00
  }
}
```

**Response (Invalid):**
```json
{
  "response_code": "coupon_not_found_404",
  "message": "Coupon not found or expired"
}
```

**Flutter Code:**
```dart
Future<CouponValidation> validateCoupon({
  required String code,
  required double fare,
  required String cityId,
  String serviceType = 'ride',
}) async {
  final response = await http.post(
    Uri.parse('$baseUrl/v1/coupons/validate'),
    headers: _authHeaders(),
    body: jsonEncode({
      'code': code,
      'fare': fare,
      'city_id': cityId,
      'service_type': serviceType,
    }),
  );

  final data = jsonDecode(response.body);
  return CouponValidation.fromJson(data);
}

class CouponValidation {
  final bool valid;
  final Coupon? coupon;
  final double? discountAmount;
  final double? finalFare;
  final String? errorMessage;

  CouponValidation.fromJson(Map<String, dynamic> json)
    : valid = json['data']?['valid'] ?? false,
      coupon = json['data']?['coupon'] != null
          ? Coupon.fromJson(json['data']['coupon'])
          : null,
      discountAmount = json['data']?['discount_amount'] != null
          ? (json['data']['discount_amount'] as num).toDouble()
          : null,
      finalFare = json['data']?['final_fare'] != null
          ? (json['data']['final_fare'] as num).toDouble()
          : null,
      errorMessage = json['message'];
}
```

---

## Device Registration Endpoints

### 6. Register Device for Push Notifications
Registers the device FCM token for push notifications.

**Endpoint:** `POST /v1/devices/register`

**Request Body:**
```json
{
  "fcm_token": "firebase-token-here",
  "platform": "android",
  "device_id": "unique-device-id",
  "device_model": "Samsung Galaxy S21",
  "app_version": "1.0.0"
}
```

**Response:**
```json
{
  "response_code": "default_200",
  "message": "Device registered successfully"
}
```

**Flutter Code:**
```dart
Future<void> registerDevice({
  required String fcmToken,
  required String deviceId,
  String? deviceModel,
  String? appVersion,
}) async {
  final platform = Platform.isAndroid ? 'android' : 'ios';

  final response = await http.post(
    Uri.parse('$baseUrl/v1/devices/register'),
    headers: _authHeaders(),
    body: jsonEncode({
      'fcm_token': fcmToken,
      'platform': platform,
      'device_id': deviceId,
      'device_model': deviceModel ?? 'Unknown',
      'app_version': appVersion ?? '1.0.0',
    }),
  );

  if (response.statusCode != 200) {
    throw Exception('Failed to register device');
  }
}
```

---

### 7. Unregister Device
Removes the device from push notifications.

**Endpoint:** `POST /v1/devices/unregister`

**Request Body:**
```json
{
  "fcm_token": "firebase-token-here"
}
```

**Response:**
```json
{
  "response_code": "default_200",
  "message": "Device unregistered successfully"
}
```

**Flutter Code:**
```dart
Future<void> unregisterDevice(String fcmToken) async {
  final response = await http.post(
    Uri.parse('$baseUrl/v1/devices/unregister'),
    headers: _authHeaders(),
    body: jsonEncode({'fcm_token': fcmToken}),
  );

  if (response.statusCode != 200) {
    throw Exception('Failed to unregister device');
  }
}
```

---

## Driver Wallet Endpoints

### 8. Get Driver Earnings
Returns driver's earnings summary.

**Endpoint:** `GET /driver/my-activity`

**Response:**
```json
{
  "response_code": "default_200",
  "data": {
    "total_earning": 5000.00,
    "total_trips": 150,
    "receivable_balance": 4500.00,
    "payable_balance": 200.00,
    "pending_balance": 0,
    "total_withdrawn": 4000.00
  }
}
```

---

### 9. Get Withdraw Methods
Returns available withdrawal methods for drivers.

**Endpoint:** `GET /driver/withdraw/methods`

**Query Parameters:**
- `limit`: Items per page
- `offset`: Page number

**Response:**
```json
{
  "response_code": "default_200",
  "data": [
    {
      "id": "uuid",
      "method_name": "Bank Transfer",
      "method_fields": [
        {"input_name": "bank_name", "input_type": "text", "placeholder": "Bank Name"},
        {"input_name": "account_number", "input_type": "text", "placeholder": "Account Number"},
        {"input_name": "account_name", "input_type": "text", "placeholder": "Account Holder Name"}
      ]
    }
  ]
}
```

---

### 10. Create Withdraw Request
Creates a withdrawal request for the driver.

**Endpoint:** `POST /driver/withdraw/request`

**Request Body:**
```json
{
  "withdraw_method": "method-uuid",
  "amount": 500.00,
  "note": "Monthly withdrawal",
  "bank_name": "Cairo Bank",
  "account_number": "1234567890",
  "account_name": "Driver Name"
}
```

**Response:**
```json
{
  "response_code": "withdraw_request_200",
  "message": "Withdraw request submitted successfully"
}
```

---

## Complete Flutter Service Class

```dart
import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;

class WalletService {
  static const String baseUrl = 'https://smartline-it.com/api';
  final String accessToken;

  WalletService(this.accessToken);

  Map<String, String> get _headers => {
    'Authorization': 'Bearer $accessToken',
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  };

  // Get wallet balance
  Future<WalletBalance> getBalance() async {
    final response = await http.get(
      Uri.parse('$baseUrl/customer/wallet/balance'),
      headers: _headers,
    );
    _handleError(response);
    return WalletBalance.fromJson(jsonDecode(response.body)['data']);
  }

  // Add funds to wallet
  Future<String> addFunds({
    required double amount,
    required String paymentMethod,
  }) async {
    final response = await http.post(
      Uri.parse('$baseUrl/customer/wallet/add-fund'),
      headers: _headers,
      body: jsonEncode({
        'amount': amount,
        'payment_method': paymentMethod,
      }),
    );
    _handleError(response);
    return jsonDecode(response.body)['data']['payment_url'];
  }

  // Get transaction history
  Future<TransactionHistory> getTransactions({
    int limit = 20,
    int offset = 1,
    String type = 'all',
  }) async {
    final response = await http.get(
      Uri.parse('$baseUrl/customer/wallet/transactions?limit=$limit&offset=$offset&type=$type'),
      headers: _headers,
    );
    _handleError(response);
    return TransactionHistory.fromJson(jsonDecode(response.body)['data']);
  }

  // Get available coupons
  Future<List<Coupon>> getAvailableCoupons() async {
    final response = await http.get(
      Uri.parse('$baseUrl/v1/coupons/available'),
      headers: _headers,
    );
    _handleError(response);
    final data = jsonDecode(response.body)['data'] as List;
    return data.map((c) => Coupon.fromJson(c)).toList();
  }

  // Validate coupon
  Future<CouponValidation> validateCoupon({
    required String code,
    required double fare,
    required String cityId,
    String serviceType = 'ride',
  }) async {
    final response = await http.post(
      Uri.parse('$baseUrl/v1/coupons/validate'),
      headers: _headers,
      body: jsonEncode({
        'code': code,
        'fare': fare,
        'city_id': cityId,
        'service_type': serviceType,
      }),
    );
    return CouponValidation.fromJson(jsonDecode(response.body));
  }

  // Register device for notifications
  Future<void> registerDevice({
    required String fcmToken,
    required String deviceId,
    String? deviceModel,
    String? appVersion,
  }) async {
    final response = await http.post(
      Uri.parse('$baseUrl/v1/devices/register'),
      headers: _headers,
      body: jsonEncode({
        'fcm_token': fcmToken,
        'platform': Platform.isAndroid ? 'android' : 'ios',
        'device_id': deviceId,
        'device_model': deviceModel ?? 'Unknown',
        'app_version': appVersion ?? '1.0.0',
      }),
    );
    _handleError(response);
  }

  // Unregister device
  Future<void> unregisterDevice(String fcmToken) async {
    final response = await http.post(
      Uri.parse('$baseUrl/v1/devices/unregister'),
      headers: _headers,
      body: jsonEncode({'fcm_token': fcmToken}),
    );
    _handleError(response);
  }

  void _handleError(http.Response response) {
    if (response.statusCode != 200) {
      final body = jsonDecode(response.body);
      throw WalletException(
        body['message'] ?? 'Unknown error',
        response.statusCode,
      );
    }
  }
}

class WalletException implements Exception {
  final String message;
  final int statusCode;

  WalletException(this.message, this.statusCode);

  @override
  String toString() => 'WalletException: $message (Status: $statusCode)';
}
```

---

## Error Handling

All API responses follow this format:
```json
{
  "response_code": "error_code_here",
  "message": "Human readable message",
  "errors": []
}
```

Common error codes:
| Code | HTTP Status | Description |
|------|-------------|-------------|
| `default_200` | 200 | Success |
| `default_400` | 400 | Bad request / Validation error |
| `default_401` | 401 | Unauthorized |
| `default_403` | 403 | Forbidden |
| `default_404` | 404 | Not found |
| `insufficient_fund_403` | 403 | Insufficient wallet balance |
| `coupon_not_found_404` | 404 | Coupon not found |

---

## Payment Callback Handling

After payment completion, the user will be redirected to:
- **Success:** `smartline://wallet/callback?status=success&transaction_id=xxx`
- **Failed:** `smartline://wallet/callback?status=failed&reason=xxx`

**Flutter Deep Link Handler:**
```dart
void handleDeepLink(Uri uri) {
  if (uri.host == 'wallet' && uri.path == '/callback') {
    final status = uri.queryParameters['status'];
    final transactionId = uri.queryParameters['transaction_id'];

    if (status == 'success') {
      // Refresh wallet balance
      walletService.getBalance();
      showSuccessMessage('Payment successful!');
    } else {
      final reason = uri.queryParameters['reason'];
      showErrorMessage('Payment failed: $reason');
    }
  }
}
```

---

## Best Practices

1. **Always refresh balance** after any payment operation
2. **Cache transaction history** and use pagination
3. **Validate coupons** before showing final fare to user
4. **Handle network errors** gracefully with retry logic
5. **Store FCM token** and update on app launch

---

## Rate Limits

| Endpoint | Limit |
|----------|-------|
| Wallet balance | 60 requests/minute |
| Add funds | 10 requests/minute |
| Transactions | 30 requests/minute |
| Coupons | 30 requests/minute |

---

## Support

For API issues, contact: api-support@smartline-it.com
