# V2 Forgot Password Flow

## Overview
Complete forgot password implementation for the V2 driver authentication system with 3-step verification process.

---

## 🔐 **Security Features**

1. ✅ **OTP-based verification** - 4-digit code sent via SMS
2. ✅ **Rate limiting** - Max 3 attempts per OTP
3. ✅ **Time-based expiration** - OTP expires in 5 minutes, reset token in 15 minutes
4. ✅ **Secure tokens** - 64-character cryptographically secure reset tokens
5. ✅ **No user enumeration** - Same response whether user exists or not
6. ✅ **Audit logging** - All password reset attempts are logged

---

## 📋 **Complete Flow**

### Step 1: Request Password Reset
**Endpoint:** `POST /api/v2/driver/auth/forgot-password`

**Request:**
```json
{
  "phone": "01288037214"
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Verification code sent to your phone",
  "data": {
    "phone_masked": "+201****7214",
    "expires_in_seconds": 300
  }
}
```

**What Happens:**
1. System validates phone number
2. Checks if driver exists (silently)
3. Generates 4-digit OTP
4. Stores OTP in cache for 5 minutes
5. Sends SMS via BeOn service
6. Returns success (even if user doesn't exist - security)

---

### Step 2: Verify OTP
**Endpoint:** `POST /api/v2/driver/auth/verify-forgot-password-otp`

**Request:**
```json
{
  "phone": "01288037214",
  "otp": "1234"
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Verification successful",
  "data": {
    "reset_token": "a1b2c3d4e5f6...64-character-token",
    "expires_in_seconds": 900
  }
}
```

**Error Response (Wrong OTP):**
```json
{
  "success": false,
  "message": "Invalid verification code. Please try again.",
  "data": {
    "attempts_remaining": 2
  }
}
```

**What Happens:**
1. Validates OTP from cache
2. Checks attempt count (max 3)
3. If OTP correct: generates reset token
4. Stores reset token in cache for 15 minutes
5. Clears OTP from cache
6. Returns reset token

---

### Step 3: Reset Password
**Endpoint:** `POST /api/v2/driver/auth/reset-password`

**Request:**
```json
{
  "reset_token": "a1b2c3d4e5f6...64-character-token",
  "password": "newpassword123"
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Password reset successfully. You can now login with your new password."
}
```

**Error Response (Expired Token):**
```json
{
  "success": false,
  "message": "Reset token expired or invalid. Please start over."
}
```

**What Happens:**
1. Validates reset token from cache
2. Finds driver by phone number
3. Updates password (hashed with bcrypt)
4. Clears reset token from cache
5. Logs successful password reset
6. Returns success

---

## 🔄 **Complete Example Flow**

```bash
# Step 1: Request reset
curl -X POST http://your-domain/api/v2/driver/auth/forgot-password \
  -H "Content-Type: application/json" \
  -d '{"phone": "01288037214"}'

# Response: Check your phone for OTP

# Step 2: Verify OTP
curl -X POST http://your-domain/api/v2/driver/auth/verify-forgot-password-otp \
  -H "Content-Type: application/json" \
  -d '{"phone": "01288037214", "otp": "1234"}'

# Response: {"reset_token": "abc..."}

# Step 3: Reset password
curl -X POST http://your-domain/api/v2/driver/auth/reset-password \
  -H "Content-Type: application/json" \
  -d '{
    "reset_token": "abc...",
    "password": "newpassword123"
  }'

# Response: Password reset successfully

# Step 4: Login with new password
curl -X POST http://your-domain/api/v2/driver/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "+201288037214",
    "password": "newpassword123"
  }'
```

---

## ⏱️ **Timeouts & Limits**

| Item | Duration | Notes |
|------|----------|-------|
| OTP Expiry | 5 minutes | After this, user must request new OTP |
| Reset Token Expiry | 15 minutes | After this, user must verify OTP again |
| Max OTP Attempts | 3 attempts | After 3 wrong attempts, must request new OTP |
| OTP Length | 4 digits | Numeric code (0000-9999) |
| Reset Token Length | 64 characters | Cryptographically secure hex string |
| Password Min Length | 8 characters | Alphanumeric only (no special chars) |

---

## 🔒 **Password Requirements**

- ✅ Minimum 8 characters
- ✅ Maximum 128 characters
- ✅ Only letters and numbers (a-z, A-Z, 0-9)
- ❌ No special characters
- ❌ No spaces

**Valid Examples:**
- `password123`
- `MyPass2024`
- `Driver12345`

**Invalid Examples:**
- `pass` (too short)
- `password@123` (has special character)
- `my password` (has space)

---

## 📝 **Error Codes**

| Code | Message | HTTP Status | Solution |
|------|---------|-------------|----------|
| `VALIDATION_FAILED` | Validation failed | 422 | Check request format |
| `OTP_EXPIRED` | Verification code expired | 400 | Request new OTP |
| `OTP_INVALID` | Invalid verification code | 401 | Try again or request new OTP |
| `TOO_MANY_ATTEMPTS` | Too many failed attempts | 429 | Request new OTP |
| `TOKEN_EXPIRED` | Reset token expired | 400 | Start over from Step 1 |
| `DRIVER_NOT_FOUND` | Driver not found | 404 | Contact support |
| `SMS_FAILED` | Failed to send SMS | 500 | Try again later |

---

## 🔔 **SMS Template**

The OTP SMS sent to drivers:

```
Your verification code is: 1234

This code will expire in 5 minutes.
Do not share this code with anyone.
```

---

## 🛡️ **Security Considerations**

### ✅ **Implemented:**
1. **No User Enumeration** - Same response whether user exists or not
2. **Rate Limiting** - Max 3 OTP verification attempts
3. **Time Expiration** - OTP and tokens expire automatically
4. **Secure Tokens** - Cryptographically random reset tokens
5. **Audit Logging** - All attempts logged with masked phone numbers
6. **Cache-based Storage** - No database pollution with temporary tokens

### ⚠️ **Recommendations:**
1. **Add SMS Rate Limiting** - Limit OTP sends per phone/IP (future)
2. **Add CAPTCHA** - For repeated reset attempts (future)
3. **Monitor for Abuse** - Alert on suspicious patterns (future)

---

## 📊 **Logging**

All password reset attempts are logged:

```php
// OTP sent
Log::info('Forgot password OTP sent', [
    'phone' => '+201****7214',
]);

// OTP verified
Log::info('Forgot password OTP verified', [
    'phone' => '+201****7214',
]);

// Password reset
Log::info('Password reset successful', [
    'driver_id' => 'uuid',
    'phone' => '+201****7214',
]);

// Errors
Log::error('Failed to send forgot password OTP', [
    'phone' => '+201****7214',
    'error' => 'SMS service unavailable',
]);
```

---

## 🔧 **Testing**

### Test Forgot Password Flow:
```bash
# 1. Request OTP
POST /api/v2/driver/auth/forgot-password
Body: {"phone": "01288037214"}

# 2. Check cache for OTP (dev only)
php artisan tinker
>>> Cache::get('forgot_password_otp:+201288037214')

# 3. Verify OTP
POST /api/v2/driver/auth/verify-forgot-password-otp
Body: {"phone": "01288037214", "otp": "1234"}

# 4. Reset password
POST /api/v2/driver/auth/reset-password
Body: {
  "reset_token": "the-token-from-step-3",
  "password": "newpass123"
}

# 5. Login with new password
POST /api/v2/driver/auth/login
Body: {"phone": "+201288037214", "password": "newpass123"}
```

---

## 📁 **Files Modified**

1. `/app/Http/Controllers/Api/V2/Driver/DriverAuthController.php`
   - Added `forgotPassword()` method
   - Added `verifyForgotPasswordOtp()` method
   - Added `resetPassword()` method

2. `/routes/api_v2_driver_onboarding.php`
   - Added 3 new public routes for forgot password flow

---

## 🎯 **Integration with Frontend**

### UI Flow:
```
[Login Screen]
    ↓
[Forgot Password?] ← Click
    ↓
[Enter Phone Number]
    ↓
[Verify OTP] (SMS sent)
    ↓
[Enter New Password]
    ↓
[Success - Redirect to Login]
```

### State Management:
```javascript
// Step 1
const forgotPassword = async (phone) => {
  const response = await api.post('/v2/driver/auth/forgot-password', { phone });
  // Show: "Check your phone for OTP"
};

// Step 2
const verifyOtp = async (phone, otp) => {
  const response = await api.post('/v2/driver/auth/verify-forgot-password-otp',
    { phone, otp }
  );
  const resetToken = response.data.reset_token;
  // Store resetToken for Step 3
};

// Step 3
const resetPassword = async (resetToken, password) => {
  const response = await api.post('/v2/driver/auth/reset-password',
    { reset_token: resetToken, password }
  );
  // Show: "Password reset successfully"
  // Redirect to login
};
```

---

**Date:** 2026-01-08
**Status:** ✅ Implemented and Ready for Testing
**Version:** V2
