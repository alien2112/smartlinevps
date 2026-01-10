# Quick Reference - Wallet & Support Fixes
**Date:** January 10, 2026

---

## ✅ Implementation Complete

All fixes have been implemented to address the issues shown in the user's screenshots.

---

## 🎯 What Was Fixed

### 1. Negative Wallet Balance (-25.99 EGP shown in screenshot)
- **Problem:** DiDiPay screen showing -25.99 EGP but API didn't provide negative indicators
- **Solution:** Updated wallet API to return `is_negative` and `amount_owed` fields
- **Endpoint:** `GET /api/driver/wallet/balance`

### 2. Support Screen (No records shown in screenshot)
- **Problem:** No support endpoints existed for driver app
- **Solution:** Created 5 new support endpoints
- **Endpoints:** `/api/driver/support/*`

---

## 📡 New API Endpoints

### Wallet Balance (Updated)
```
GET /api/driver/wallet/balance
Authorization: Bearer {token}
```

**New Response Fields:**
```json
{
  "wallet_balance": -25.99,
  "is_negative": true,
  "amount_owed": 25.99,
  "formatted_wallet_balance": "-25.99 ج.م",
  "formatted_amount_owed": "25.99 ج.م"
}
```

### Support Endpoints (New)

#### 1. App Info
```
GET /api/driver/support/app-info
Authorization: Bearer {token}
```
Returns: support email, phone, WhatsApp, working hours

#### 2. FAQ
```
GET /api/driver/support/faq
Authorization: Bearer {token}
```
Returns: 6 FAQs including negative wallet explanation

#### 3. Create Ticket
```
POST /api/driver/support/ticket
Authorization: Bearer {token}
Content-Type: application/json

{
  "subject": "Issue title",
  "message": "Issue description",
  "category": "payment|trip|account|other",
  "priority": "low|normal|high|urgent"
}
```

#### 4. List Tickets
```
GET /api/driver/support/tickets?limit=10&status=open
Authorization: Bearer {token}
```

#### 5. Get Ticket
```
GET /api/driver/support/ticket/{id}
Authorization: Bearer {token}
```

---

## 🔄 Cash Payment Flow (Changed)

### Before
1. Customer pays 100 EGP cash
2. Commission (30 EGP) tracked in `payable_balance`
3. Admin must manually collect

### After
1. Customer pays 100 EGP cash
2. Commission (30 EGP) **deducted from wallet_balance** ❌
3. Wallet can go negative (e.g., -25.99 EGP)
4. Driver must top-up to clear debt

---

## 📁 Files Modified/Created

### Modified
1. `Modules/TransactionManagement/Traits/TransactionTrait.php`
   - Method: `cashTransaction()`
   - Change: Deduct commission from wallet_balance

2. `Modules/UserManagement/Http/Controllers/Api/New/Driver/DriverWalletController.php`
   - Method: `getBalance()`
   - Change: Added negative balance indicators

3. `Modules/UserManagement/Routes/api.php`
   - Added support routes

### Created
1. `database/migrations/2026_01_10_000001_allow_negative_driver_wallet_balance.php`
2. `database/migrations/2026_01_10_000002_create_support_tickets_table.php`
3. `Modules/UserManagement/Entities/SupportTicket.php`
4. `Modules/UserManagement/Http/Controllers/Api/New/Driver/SupportController.php`

---

## 🚀 Deployment Steps

```bash
# 1. Run migrations
php artisan migrate

# 2. Clear caches
php artisan config:clear
php artisan route:clear
php artisan cache:clear

# 3. Test endpoints
curl -X GET "https://smartline-it.com/api/driver/wallet/balance" \
  -H "Authorization: Bearer {token}"

curl -X GET "https://smartline-it.com/api/driver/support/app-info" \
  -H "Authorization: Bearer {token}"
```

---

## 📱 Flutter Updates Needed

### 1. Wallet Screen
```dart
// Show warning if negative
if (wallet['is_negative'] == true) {
  // Display red warning banner
  // Show "You owe {amount_owed}" message
  // Prominent top-up button
}
```

### 2. Support Screen
```dart
// Add navigation items:
// - Contact Support (email, phone, WhatsApp)
// - FAQ
// - Submit Ticket
// - My Tickets
```

---

## 📊 Database Schema

### support_tickets
```sql
id (UUID)
user_id (UUID) → users.id
user_type (driver/customer)
subject (VARCHAR)
message (TEXT)
category (payment/trip/account/other)
priority (low/normal/high/urgent)
status (open/in_progress/resolved/closed)
trip_id (UUID) → trip_requests.id (nullable)
admin_response (TEXT, nullable)
responded_at (TIMESTAMP, nullable)
responded_by (UUID) → users.id (nullable)
created_at, updated_at
```

---

## 🔍 Testing

### Test Wallet Balance
```bash
curl -X GET "https://smartline-it.com/api/driver/wallet/balance" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

Expected: `is_negative: true`, `amount_owed: 25.99`

### Test Support Info
```bash
curl -X GET "https://smartline-it.com/api/driver/support/app-info" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

Expected: support_email, support_phone, etc.

---

## ⚠️ Important Notes

1. **Wallet can go negative** - This is intentional for cash trip commissions
2. **Driver must top-up** - To clear negative balance
3. **Notification sent** - When wallet goes negative
4. **Admin panel update needed** - To view/respond to support tickets (separate task)

---

## 📞 Contact

For questions: support@smartline-it.com

---

**Status:** ✅ Ready for Deployment  
**All TODOs:** Completed ✅
