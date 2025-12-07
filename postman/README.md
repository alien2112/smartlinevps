# Rateel API Postman Collection

Complete Postman collection for testing all Rateel ride-sharing application API endpoints.

## Files

| File | Description |
|------|-------------|
| `Rateel_API_Collection.postman_collection.json` | Main collection with all API endpoints |
| `Rateel_API_Environment.postman_environment.json` | Environment variables for testing |

## How to Import

### Step 1: Import Collection
1. Open Postman
2. Click **Import** button (top left)
3. Select `Rateel_API_Collection.postman_collection.json`
4. Click **Import**

### Step 2: Import Environment
1. In Postman, click **Environments** (left sidebar)
2. Click **Import**
3. Select `Rateel_API_Environment.postman_environment.json`
4. Click **Import**
5. Select **Rateel API Environment** from the dropdown (top right)

## API Modules Included

### 1. Auth Management
- **Customer Auth**: Register, Login, Social Login, OTP Login, Forget/Reset Password
- **Driver Auth**: Register, Login, OTP, Forget/Reset Password
- **User**: Logout, Delete Account, Change Password

### 2. Business Management
- **Configuration**: App configurations, pages
- **Customer Config**: Zone, Place Autocomplete, Distance API, Geocode, Payment Methods
- **Driver Config**: Zone, Place Autocomplete, Distance API, Geocode
- **Location**: Save user location

### 3. User Management
- **Customer**: Profile, Update, Notifications, Loyalty Points, Level, Referrals
- **Customer Address**: CRUD operations for saved addresses
- **Driver**: Profile, Online Status, Activity, Income, Withdraw
- **Live Location**: Store and get live location

### 4. Trip Management
- **Customer Rides**: Create ride, Get fare estimate, Ride list, Bidding, Track, Pay
- **Driver Rides**: Pending rides, Bid, Accept, Update status, Track
- **Safety Alerts**: Store and manage safety alerts

### 5. Chatting Management
- **Customer Chat**: Find channel, Create channel, Send/Get messages
- **Driver Chat**: Chat with customers and admin

### 6. Vehicle Management
- **Customer**: View vehicle categories
- **Driver**: Store/Update vehicle, View brands/models

### 7. Parcel Management
- **Customer**: Create parcel, Track, List parcels

### 8. Promotion Management
- **Customer**: Banners, Coupons, Discounts
- **Driver**: Banners

### 9. Transaction Management
- **Customer**: Transaction history, Referral earnings
- **Driver**: Transactions, Payable, Cash collect, Wallet

### 10. Review Module
- **Customer**: Submit and view reviews
- **Driver**: Submit and view reviews

### 11. Zone Management
- **Driver**: Get zone list

### 12. Gateways
- Payment configuration

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `base_url` | Laravel server URL | `http://127.0.0.1:8000` |
| `access_token` | Bearer token for authenticated requests | - |
| `customer_token` | Customer's auth token | - |
| `driver_token` | Driver's auth token | - |
| `latitude` | Default latitude (Cairo) | `30.0444` |
| `longitude` | Default longitude (Cairo) | `31.2357` |
| `origin_lat` | Origin latitude for routes | `30.0444` |
| `origin_lng` | Origin longitude for routes | `31.2357` |
| `destination_lat` | Destination latitude | `31.2001` |
| `destination_lng` | Destination longitude | `29.9187` |
| `trip_request_id` | Current trip request ID | - |
| `zone_id` | Zone ID for requests | `1` |
| `place_id` | Place ID from geocode | - |
| `channel_id` | Chat channel ID | - |
| `address_id` | User address ID | - |
| `vehicle_id` | Vehicle ID | - |
| `user_id` | User ID | - |
| `geolink_api_key` | GeoLink API key | `4a3eb528...` |

## ⚠️ Important: User Levels Required

**Before registering users, you MUST create user levels first!**

If you get the error `"Create a level first"` during registration, run this SQL script:

```sql
-- Run: postman/create_user_levels.sql
-- Or execute via MySQL:
mysql -u root -D u304228525_ddd < postman/create_user_levels.sql
```

Or create levels via the admin panel: **User Management > Customer Level** or **Driver Level**

**User Levels** are tiered systems (Bronze, Silver, Gold, etc.) that determine:
- Rewards and benefits
- Targeted ride/amount requirements
- Loyalty point calculations

## Authentication

Most endpoints require authentication. The collection uses **Bearer Token** authentication.

### How to Authenticate:

1. **Register + Auto-Login**:
   - Run `Auth Management > Customer > Register` or `Driver > Register`
   - **Note**: Registration doesn't return a token by default
   - The Postman collection will **automatically login** after successful registration
   - Token is saved to `access_token` variable

2. **Manual Login**:
   - Run `Auth Management > Customer > Login` or `Driver > Login`
   - Token is automatically saved to `access_token` variable

3. **Switch Between Users**:
   - Set `access_token` to `{{customer_token}}` for customer requests
   - Set `access_token` to `{{driver_token}}` for driver requests

## Testing Workflow

### Test Customer Flow:
1. Register/Login as customer
2. Get configuration
3. Search for places (place-api-autocomplete)
4. Get estimated fare
5. Create ride request
6. Track ride status
7. Complete payment
8. Submit review

### Test Driver Flow:
1. Register/Login as driver
2. Get configuration
3. View pending ride list
4. Accept/Bid on ride
5. Update ride status (picked up, ongoing, completed)
6. View income statement
7. Request withdraw

## Base URLs

- **Local Development**: `http://127.0.0.1:8000`
- **Production**: Update `base_url` in environment

## Troubleshooting

### "Credential does not match" (default_401) Error

If you get this error on customer/driver endpoints:

**Most Common Issues:**

1. **Not logged in or token not set**:
   - ⚠️ **CRITICAL**: You MUST login first before testing any protected endpoints!
   - Go to `Auth Management > Customer > Login` (or `Driver > Login`)
   - Check the response - it should include `data.token` in the response
   - The token should be automatically saved to `customer_token` or `driver_token`
   - Verify in Postman: Click the eye icon (top right) → Check `customer_token` or `driver_token` has a value

2. **Wrong token variable**:
   - Customer rides use `{{customer_token}}`
   - Driver rides use `{{driver_token}}`
   - The login script saves tokens to separate variables for safety
   - Make sure you're using the correct token type!

3. **Token format**:
   - Postman automatically adds "Bearer " prefix
   - Authorization header should be: `Bearer <your-token-here>`
   - Collection/folder-level auth should handle this automatically

4. **User account issues**:
   - User must be active: `is_active = 1` in database
   - Check: `SELECT id, email, phone, is_active FROM users WHERE email = 'your@email.com'`
   - If inactive, activate via admin panel or database

5. **Token expired or invalid**:
   - Log out and log in again to get a fresh token
   - Tokens can expire or be revoked

6. **Quick Fix Steps**:
   ```
   1. Login as Customer → Saves token to {{customer_token}}
   2. Test Customer Rides endpoints (they use {{customer_token}})
   3. Login as Driver → Saves token to {{driver_token}}  
   4. Test Driver Rides endpoints (they use {{driver_token}})
   ```

## Notes

- All authenticated endpoints require the `access_token` to be set
- Coordinates default to Cairo, Egypt - update for your location
- Login endpoints automatically save tokens to environment variables
- Use the environment dropdown (top right) to switch environments
- **Always login first before testing protected endpoints!**

