# Quick Start Guide - Fix "Credential does not match" Error

## The Problem

You're getting this error when testing Customer Rides endpoints:
```json
{
    "response_code": "default_401",
    "message": "Credential does not match"
}
```

## The Solution (3 Steps)

### Step 1: Login to Get Token

**Customer Login:**
1. Open: `Auth Management > Customer > Login`
2. Verify the body has:
   - `phone_or_email`: `1234567890`
   - `password`: `password123`
3. Click **Send**
4. Check response - should see `"response_code": "auth_login_200"`
5. Token is automatically saved to `{{customer_token}}`

**Driver Login:**
1. Open: `Auth Management > Driver > Login`
2. Verify the body has:
   - `phone_or_email`: `0987654321`
   - `password`: `password123`
3. Click **Send**
4. Token is automatically saved to `{{driver_token}}`

### Step 2: Verify Token is Saved

1. Click the **eye icon** (👁️) in the top right corner of Postman
2. Look for these variables:
   - `customer_token` → Should have a long string starting with "eyJ..."
   - `access_token` → Should match the customer_token
3. If empty, login failed - check the login response for errors

### Step 3: Test with Manual Request

Use the **🔧 Test Authentication (Manual)** request:

1. Open: `Trip Management > Customer Rides > 🔧 Test Authentication (Manual)`
2. Go to **Authorization** tab
3. Replace `PASTE_YOUR_TOKEN_HERE` with your actual token from login response
4. Go to **Headers** tab
5. Get a zone UUID from database:
   ```sql
   SELECT id FROM zones WHERE is_active = 1 LIMIT 1;
   ```
6. Replace `PASTE_ZONE_UUID_HERE` with the actual UUID
7. Click **Send**

**Expected Results:**
- ✅ **200 OK** → Authentication works!
- ❌ **401 Credential does not match** → Token is wrong/missing
- ❌ **404 Zone not found** → Zone ID is invalid

## Understanding the Setup

### Folder-Level Authentication

The collection uses **folder-level auth** (not request-level):

```
Trip Management
└── Customer Rides (Auth: Bearer {{customer_token}})
    ├── Drivers Near Me (Inherits auth)
    ├── Get Estimated Fare (Inherits auth)
    └── Create Ride Request (Inherits auth)
```

All requests in "Customer Rides" automatically use `{{customer_token}}`.

### How Token Auto-Save Works

When you run the Login request, this test script runs:

```javascript
var jsonData = pm.response.json();
if (jsonData.data && jsonData.data.token) {
    pm.environment.set('customer_token', jsonData.data.token);
    pm.environment.set('access_token', jsonData.data.token);
}
```

It extracts the token from the response and saves it to your environment.

## Complete Testing Workflow

### For Customer Endpoints:

```
1. Customer Login
   POST /api/customer/auth/login
   ↓ (saves customer_token)

2. Test Authentication (Manual)
   GET /api/customer/drivers-near-me
   ↓ (verify token works)

3. Get Estimated Fare
   POST /api/customer/ride/get-estimated-fare
   ↓ (uses customer_token automatically)

4. Create Ride Request
   POST /api/customer/ride/create
   ↓ (uses customer_token automatically)
```

### For Driver Endpoints:

```
1. Driver Login
   POST /api/driver/auth/login
   ↓ (saves driver_token)

2. Pending Rides
   GET /api/driver/ride/pending-ride-list
   ↓ (uses driver_token automatically)

3. Bid on Ride
   POST /api/driver/ride/bid
   ↓ (uses driver_token automatically)
```

## Debugging Checklist

- [ ] Ran login request successfully?
- [ ] Token visible in environment (eye icon)?
- [ ] Token starts with "eyJ..."?
- [ ] Using correct endpoint (customer vs driver)?
- [ ] Request Auth set to "Inherit auth from parent"?
- [ ] Folder Auth set to Bearer with correct token variable?
- [ ] Zone ID is a valid UUID (not "1")?

## Common Mistakes

### ❌ Mistake 1: Not Logging In First
```
You: *Opens "Drivers Near Me" and clicks Send*
API: "Credential does not match"
```
**Fix:** Login first!

### ❌ Mistake 2: Using Wrong Token
```
You: *Uses customer_token for driver endpoint*
API: "Credential does not match"
```
**Fix:** Customer endpoints need customer token, driver endpoints need driver token.

### ❌ Mistake 3: Zone ID is "1"
```
You: *Sends request with zone_id = "1"*
API: "Zone not found"
```
**Fix:** Zones use UUIDs. Get real UUID from database.

### ❌ Mistake 4: Request Auth Override
```
You: *Sets request Auth to "No Auth"*
API: "Credential does not match"
```
**Fix:** Set request Auth to "Inherit auth from parent".

## Need More Help?

1. **Check Postman Console:**
   - View → Show Postman Console
   - See the actual request being sent
   - Look for Authorization header

2. **Read Full Documentation:**
   - `AUTHENTICATION_SETUP.md` - Detailed auth guide
   - `README.md` - Complete collection documentation

3. **Test Credentials:**
   - Customer: `1234567890` / `password123`
   - Driver: `0987654321` / `password123`

## Quick Commands

### Create Test Users (if not exists):
```bash
php artisan db:seed --class=TestUserSeeder
```

### Create User Levels (if not exists):
```bash
php artisan db:seed --class=UserLevelSeeder
```

### Get Zone UUID:
```sql
SELECT id, name FROM zones WHERE is_active = 1 LIMIT 1;
```

### Check User Exists:
```sql
SELECT id, phone, user_type, is_active FROM users WHERE phone = '1234567890';
```

