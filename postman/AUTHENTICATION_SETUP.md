# Postman Authentication Setup Guide

## Problem: "Credential does not match" (default_401)

This error occurs when:
1. You haven't logged in yet (no token)
2. The token has expired
3. The token isn't being sent with the request

## Step-by-Step Setup

### 1. Login First

Before testing any authenticated endpoints, you MUST login:

**For Customer Endpoints:**
```
POST {{base_url}}/api/customer/auth/login

Body (form-data):
- phone_or_email: 1234567890
- password: password123
```

**For Driver Endpoints:**
```
POST {{base_url}}/api/driver/auth/login

Body (form-data):
- phone_or_email: 0987654321
- password: password123
```

### 2. Token Auto-Save

The login requests have a test script that automatically saves the token:
- Customer token → `{{customer_token}}`
- Driver token → `{{driver_token}}`
- Both also save to → `{{access_token}}`

### 3. Verify Token is Saved

After login, check your Postman environment:
1. Click the eye icon (👁️) in the top right
2. Look for `customer_token` or `driver_token`
3. Should see a long string starting with "eyJ..."

### 4. Authentication Inheritance

The collection uses **folder-level authentication**:

- **Customer Rides** folder uses: `{{customer_token}}`
- **Driver Rides** folder uses: `{{driver_token}}`
- Individual requests inherit from their parent folder

### 5. Manual Token Testing

To manually test with a token:

1. Login and copy the token from the response
2. Go to your request
3. Click "Authorization" tab
4. Select "Bearer Token"
5. Paste the token
6. Send request

## Common Issues

### Issue 1: Token Not Sent

**Symptom:** "Credential does not match"

**Solution:**
1. Check the folder's Auth tab (not the request's Auth tab)
2. Ensure it's set to "Bearer Token"
3. Ensure the value is `{{customer_token}}` or `{{driver_token}}`
4. Make sure the request Auth is set to "Inherit auth from parent"

### Issue 2: Wrong Token for Endpoint

**Symptom:** "Credential does not match"

**Solution:**
- Customer endpoints need customer token
- Driver endpoints need driver token
- Don't mix them up!

### Issue 3: Token Expired

**Symptom:** "Credential does not match" after working previously

**Solution:**
- Login again to get a fresh token

### Issue 4: Zone ID Not Set

**Symptom:** "Zone not found" (zone_404)

**Solution:**
1. Get a zone UUID from database: `SELECT id FROM zones WHERE is_active = 1 LIMIT 1;`
2. Update environment variable `zone_id` with the UUID (not "1")

## Testing Workflow

### Correct Order:

1. **Login** → Get token
2. **Test Profile** → Verify token works
3. **Test Other Endpoints** → Use authenticated requests

### Example Test Flow:

```
1. Customer Login
   ↓ (token auto-saved)
2. Get Customer Profile
   ↓ (verify auth works)
3. Drivers Near Me
   ↓ (requires zoneId header + token)
4. Create Ride Request
   ↓ (requires token + zone_id)
```

## Environment Variables Needed

| Variable | Description | Example |
|----------|-------------|---------|
| `base_url` | API base URL | `http://127.0.0.1:8000` |
| `customer_token` | Customer auth token | Auto-set after login |
| `driver_token` | Driver auth token | Auto-set after login |
| `access_token` | Current active token | Auto-set after login |
| `zone_id` | Zone UUID | Get from database |
| `latitude` | Test latitude | `30.0444` |
| `longitude` | Test longitude | `31.2357` |

## Quick Debug Checklist

- [ ] Logged in successfully?
- [ ] Token visible in environment variables?
- [ ] Request Auth set to "Inherit auth from parent"?
- [ ] Folder Auth set to Bearer with correct token variable?
- [ ] Using correct endpoint (customer vs driver)?
- [ ] Zone ID is a valid UUID (not "1")?
- [ ] Required headers present (like `zoneId`)?

## Test Credentials

### Customer
- Phone: `1234567890`
- Password: `password123`

### Driver
- Phone: `0987654321`
- Password: `password123`

## Need Help?

1. Check the Console (View → Show Postman Console)
2. Look for the actual request being sent
3. Check if Authorization header is present
4. Verify the token format (should start with "eyJ...")


