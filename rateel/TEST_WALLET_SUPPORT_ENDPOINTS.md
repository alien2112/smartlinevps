# Test Guide for Wallet & Support Endpoints
**Date:** January 10, 2026

---

## ✅ Implementation Complete

All fixes from `FIXES_PLAN_2026-01-10.md` have been implemented:

### 1. Cash Payment Commission Handling ✅
- Modified `cashTransaction()` method to deduct commission from `wallet_balance`
- Wallet can now go negative
- Added notification when wallet goes negative
- Updated driver wallet API with negative balance indicators

### 2. Driver Support Endpoints ✅
- Created `SupportTicket` entity
- Created `SupportController` with 5 endpoints
- Registered routes under `/api/driver/support`
- Added FAQ with negative wallet explanation

---

## 🧪 Testing the Endpoints

### Prerequisites
1. Get a valid driver access token
2. Base URL: `https://smartline-it.com`
3. Use Postman or curl for testing

---

## Test 1: Driver Wallet Balance (With Negative Indicators)

### Endpoint
```
GET /api/driver/wallet/balance
```

### Headers
```
Authorization: Bearer {driver_access_token}
Accept: application/json
```

### Expected Response
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "receivable_balance": 150.00,
    "payable_balance": 0.00,
    "pending_balance": 0.00,
    "received_balance": 500.00,
    "total_withdrawn": 200.00,
    "wallet_balance": -25.99,
    "referral_earn": 0.00,
    "withdrawable_amount": 150.00,
    "is_negative": true,
    "amount_owed": 25.99,
    "formatted_wallet_balance": "-25.99 ج.م",
    "formatted_amount_owed": "25.99 ج.م",
    "currency": "EGP",
    "formatted_receivable": "150.00 ج.م",
    "formatted_payable": "0.00 ج.م",
    "formatted_withdrawable": "150.00 ج.م"
  }
}
```

### Key Fields to Check
- `is_negative`: `true` when wallet_balance < 0
- `amount_owed`: Absolute value of negative balance
- `formatted_amount_owed`: Formatted amount owed (null if positive)

---

## Test 2: Support App Info

### Endpoint
```
GET /api/driver/support/app-info
```

### Headers
```
Authorization: Bearer {driver_access_token}
Accept: application/json
```

### Expected Response
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "app_name": "Smartline",
    "app_version": "1.0.0",
    "api_version": "2.0",
    "minimum_supported_version": "1.0.0",
    "latest_version": "1.0.0",
    "force_update_required": false,
    "support_email": "support@smartline-it.com",
    "support_phone": "+20 xxx xxx xxxx",
    "support_whatsapp": null,
    "working_hours": "9:00 AM - 9:00 PM",
    "emergency_number": "911",
    "help_center_url": null
  }
}
```

---

## Test 3: Support FAQ

### Endpoint
```
GET /api/driver/support/faq
```

### Headers
```
Authorization: Bearer {driver_access_token}
Accept: application/json
```

### Expected Response
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "faqs": [
      {
        "id": 1,
        "question": "كيف أسحب أرباحي؟",
        "question_en": "How do I withdraw my earnings?",
        "answer": "يمكنك سحب أرباحك من خلال قائمة المحفظة > طلب سحب",
        "answer_en": "You can withdraw your earnings from Wallet menu > Request Withdrawal",
        "category": "payment"
      },
      {
        "id": 6,
        "question": "لماذا رصيد محفظتي سالب؟",
        "question_en": "Why is my wallet balance negative?",
        "answer": "عند استلام نقدية من العملاء، يتم خصم العمولة من محفظتك. يرجى شحن المحفظة لتسوية الرصيد السالب.",
        "answer_en": "When you receive cash from customers, the commission is deducted from your wallet. Please top-up your wallet to clear the negative balance.",
        "category": "payment"
      }
    ],
    "categories": [
      {"id": "all", "name": "الكل", "name_en": "All"},
      {"id": "payment", "name": "الدفع", "name_en": "Payment"},
      {"id": "trip", "name": "الرحلات", "name_en": "Trips"},
      {"id": "account", "name": "الحساب", "name_en": "Account"},
      {"id": "other", "name": "أخرى", "name_en": "Other"}
    ]
  }
}
```

---

## Test 4: Create Support Ticket

### Endpoint
```
POST /api/driver/support/ticket
```

### Headers
```
Authorization: Bearer {driver_access_token}
Accept: application/json
Content-Type: application/json
```

### Request Body
```json
{
  "subject": "Negative wallet balance issue",
  "message": "My wallet balance is negative after completing cash trips. How do I fix this?",
  "category": "payment",
  "priority": "normal"
}
```

### Expected Response
```json
{
  "response_code": "default_store_200",
  "message": "Successfully created",
  "data": {
    "ticket_id": "9c8f7e6d-5b4a-3c2d-1e0f-9a8b7c6d5e4f",
    "status": "open",
    "message": "Your support ticket has been submitted. We will respond shortly."
  }
}
```

---

## Test 5: Get Support Tickets

### Endpoint
```
GET /api/driver/support/tickets?limit=10&status=open
```

### Headers
```
Authorization: Bearer {driver_access_token}
Accept: application/json
```

### Expected Response
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "tickets": [
      {
        "id": "9c8f7e6d-5b4a-3c2d-1e0f-9a8b7c6d5e4f",
        "subject": "Negative wallet balance issue",
        "message": "My wallet balance is negative after completing cash trips. How do I fix this?",
        "category": "payment",
        "priority": "normal",
        "status": "open",
        "admin_response": null,
        "responded_at": null,
        "created_at": "2026-01-10T12:30:00+00:00",
        "trip_id": null
      }
    ],
    "pagination": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 10,
      "total": 1
    }
  }
}
```

---

## Test 6: Get Single Ticket

### Endpoint
```
GET /api/driver/support/ticket/{ticket_id}
```

### Headers
```
Authorization: Bearer {driver_access_token}
Accept: application/json
```

### Expected Response
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "id": "9c8f7e6d-5b4a-3c2d-1e0f-9a8b7c6d5e4f",
    "subject": "Negative wallet balance issue",
    "message": "My wallet balance is negative after completing cash trips. How do I fix this?",
    "category": "payment",
    "priority": "normal",
    "status": "open",
    "admin_response": null,
    "responded_at": null,
    "created_at": "2026-01-10T12:30:00+00:00",
    "trip": null
  }
}
```

---

## 🔄 Testing Cash Payment Commission Deduction

### Scenario: Complete a cash trip

1. **Before Trip:**
   - Driver wallet balance: 10.00 EGP
   - Trip fare: 100.00 EGP
   - Commission: 30.00 EGP

2. **Customer pays cash** → Driver receives 100 EGP physically

3. **After Trip (System processes):**
   - Driver wallet balance: **-20.00 EGP** (10 - 30 = -20)
   - Driver received_balance: +70.00 EGP (their earning)
   - Admin received_balance: +30.00 EGP (commission)

4. **Check wallet:**
```bash
GET /api/driver/wallet/balance
```

Expected:
```json
{
  "wallet_balance": -20.00,
  "is_negative": true,
  "amount_owed": 20.00,
  "formatted_wallet_balance": "-20.00 ج.م",
  "formatted_amount_owed": "20.00 ج.م"
}
```

5. **Driver receives notification:**
```
Title: "Commission Deducted"
Message: "Commission of 30.00 EGP has been deducted. Your wallet balance is now -20.00 EGP. Please top-up to clear the negative balance."
```

---

## 📱 Flutter Integration

### Wallet Screen Updates

```dart
// Check if wallet is negative
if (walletData['is_negative'] == true) {
  // Show warning banner
  Container(
    color: Colors.red[100],
    padding: EdgeInsets.all(16),
    child: Row(
      children: [
        Icon(Icons.warning, color: Colors.red),
        SizedBox(width: 8),
        Expanded(
          child: Text(
            'You owe ${walletData['formatted_amount_owed']}. Please top-up your wallet.',
            style: TextStyle(color: Colors.red[900]),
          ),
        ),
      ],
    ),
  );
}

// Display wallet balance with color
Text(
  walletData['formatted_wallet_balance'],
  style: TextStyle(
    fontSize: 32,
    fontWeight: FontWeight.bold,
    color: walletData['is_negative'] ? Colors.red : Colors.green,
  ),
);
```

### Support Screen

```dart
// Support Info Screen
class SupportScreen extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Support')),
      body: ListView(
        children: [
          // App Info Section
          FutureBuilder(
            future: api.getSupportAppInfo(),
            builder: (context, snapshot) {
              if (snapshot.hasData) {
                final info = snapshot.data;
                return Column(
                  children: [
                    ListTile(
                      leading: Icon(Icons.email),
                      title: Text('Email Support'),
                      subtitle: Text(info['support_email']),
                      onTap: () => launchUrl('mailto:${info['support_email']}'),
                    ),
                    ListTile(
                      leading: Icon(Icons.phone),
                      title: Text('Phone Support'),
                      subtitle: Text(info['support_phone']),
                      onTap: () => launchUrl('tel:${info['support_phone']}'),
                    ),
                    ListTile(
                      leading: Icon(Icons.help),
                      title: Text('FAQ'),
                      onTap: () => Navigator.push(context, FAQScreen()),
                    ),
                    ListTile(
                      leading: Icon(Icons.support_agent),
                      title: Text('Submit Ticket'),
                      onTap: () => Navigator.push(context, CreateTicketScreen()),
                    ),
                  ],
                );
              }
              return CircularProgressIndicator();
            },
          ),
        ],
      ),
    );
  }
}
```

---

## 🗄️ Database Changes

### Migration 1: Wallet Balance Index
```sql
-- Already allows negative values (DECIMAL type)
-- Just adds index for performance
ALTER TABLE user_accounts 
ADD INDEX idx_wallet_balance_negative (wallet_balance);
```

### Migration 2: Support Tickets Table
```sql
CREATE TABLE support_tickets (
  id CHAR(36) PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  user_type VARCHAR(255) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  category VARCHAR(255) NULL,
  priority VARCHAR(255) DEFAULT 'normal',
  status VARCHAR(255) DEFAULT 'open',
  trip_id CHAR(36) NULL,
  admin_response TEXT NULL,
  responded_at TIMESTAMP NULL,
  responded_by CHAR(36) NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  
  INDEX idx_user_status (user_id, status),
  INDEX idx_user_type_status (user_type, status),
  INDEX idx_created_at (created_at),
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (trip_id) REFERENCES trip_requests(id) ON DELETE SET NULL,
  FOREIGN KEY (responded_by) REFERENCES users(id) ON DELETE SET NULL
);
```

---

## 🔍 Transaction Records

### Cash Trip Commission Transaction
```sql
-- Driver wallet debit (commission deducted)
INSERT INTO transactions (
  attribute, attribute_id, debit, balance, user_id, account
) VALUES (
  'cash_trip_commission_deducted', 'trip-uuid', 30.00, -20.00, 'driver-uuid', 'wallet_balance'
);

-- Driver earning credit
INSERT INTO transactions (
  attribute, attribute_id, credit, balance, user_id, account, trx_ref_id
) VALUES (
  'driver_earning', 'trip-uuid', 70.00, 570.00, 'driver-uuid', 'received_balance', 'previous-txn-id'
);

-- Admin commission received
INSERT INTO transactions (
  attribute, attribute_id, credit, balance, user_id, account, trx_ref_id
) VALUES (
  'admin_commission', 'trip-uuid', 30.00, 1030.00, 'admin-uuid', 'received_balance', 'driver-txn-id'
);
```

---

## ✅ Verification Checklist

- [ ] Wallet balance API returns `is_negative` and `amount_owed`
- [ ] Support app-info endpoint returns contact details
- [ ] Support FAQ endpoint returns 6 questions including negative wallet FAQ
- [ ] Can create support ticket successfully
- [ ] Can list support tickets with pagination
- [ ] Can get single ticket details
- [ ] Cash trip deducts commission from wallet_balance
- [ ] Wallet can go negative
- [ ] Notification sent when wallet goes negative
- [ ] Transaction records created correctly

---

## 📞 Next Steps

1. **Run migrations** (when vendor folder is available):
   ```bash
   php artisan migrate
   ```

2. **Clear caches**:
   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan cache:clear
   ```

3. **Test with real driver token**

4. **Update Flutter app** to use new endpoints

5. **Admin panel** needs update to view/respond to support tickets (separate task)

---

**Status:** ✅ Implementation Complete - Ready for Testing
