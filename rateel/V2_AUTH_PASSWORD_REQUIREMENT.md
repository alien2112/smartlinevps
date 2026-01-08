# V2 Authentication - Password Requirement Implementation

## Overview
Modified the V2 driver onboarding and authentication system to **require password authentication** for all returning drivers, preventing phone-only login.

## Changes Made

### 1. **DriverOnboardingService.php** - `startOnboarding()` Method
**File:** `app/Services/Driver/DriverOnboardingService.php`
**Lines:** 99-136

**Change:** When a returning driver enters their phone number:
- ✅ **Before:** Auto-authenticated if phone was verified (security issue)
- ✅ **After:** Requires password if driver has one set

```php
// If driver has a password set, require password authentication
if (!empty($existingUser->password)) {
    return [
        'success' => true,
        'data' => [
            'phone_verified' => true,
            'phone_masked' => $this->maskPhone($normalizedPhone),
            'requires_password' => true,
            'next_step' => 'password',
            'message' => translate('Please enter your password to continue'),
        ],
    ];
}
```

---

### 2. **DriverOnboardingService.php** - `setPassword()` Method
**File:** `app/Services/Driver/DriverOnboardingService.php`
**Lines:** 586-670

**Change:** Made the password endpoint dual-purpose:

#### For **Returning Drivers** (has password):
- Verifies the provided password
- Issues authentication token if correct
- Returns error if password is wrong

#### For **New Drivers** (no password):
- Sets password for the first time
- Continues onboarding flow

```php
// Check if driver already has a password (returning driver - login scenario)
if (!empty($driver->password)) {
    // Verify the provided password
    if (!Hash::check($password, $driver->password)) {
        return [
            'success' => false,
            'error' => [
                'code' => 'INVALID_PASSWORD',
                'message' => translate('Invalid password. Please try again.'),
            ],
        ];
    }

    // Password is correct - authenticate and continue
    // ... issue token and return driver data
}

// New driver - set password for the first time
// ... set password and continue onboarding
```

---

### 3. **DriverOnboardingController.php** - `setPassword()` Method
**File:** `app/Http/Controllers/Api/V2/Driver/DriverOnboardingController.php`
**Lines:** 221-243

**Change:** Updated response handling for both scenarios:
- Returns 401 for invalid password (returning driver)
- Returns appropriate success message based on context

```php
// Check if this was a returning driver login or new password setup
$isReturning = $result['data']['is_returning'] ?? false;
$message = $isReturning
    ? translate('Login successful. Welcome back!')
    : translate('Password set successfully');
```

---

## API Flow

### New Driver Registration
```
1. POST /api/v2/driver/onboarding/start
   Body: { "phone": "+201234567890" }
   Response: { "next_step": "otp", "requires_password": false }

2. POST /api/v2/driver/onboarding/verify-otp
   Body: { "onboarding_id": "onb_xxx", "otp": "1234" }
   Response: { "next_step": "password", "token": "xxx" }

3. POST /api/v2/driver/onboarding/password
   Headers: { "Authorization": "Bearer xxx" }
   Body: { "password": "password123", "password_confirmation": "password123" }
   Response: {
     "success": true,
     "message": "Password set successfully",
     "data": {
       "authenticated": false,
       "is_returning": false,
       "next_step": "submit_profile"
     }
   }
```

### Returning Driver Login
```
1. POST /api/v2/driver/onboarding/start
   Body: { "phone": "+201234567890" }
   Response: {
     "next_step": "password",
     "requires_password": true,
     "phone_verified": true
   }

2. POST /api/v2/driver/onboarding/password
   Headers: { "Authorization": "Bearer xxx" }
   Body: { "password": "password123", "password_confirmation": "password123" }
   Response: {
     "success": true,
     "message": "Login successful. Welcome back!",
     "data": {
       "authenticated": true,
       "is_returning": true,
       "token": "new_token_xxx",
       "next_step": "submit_profile", // or current step
       "driver": { ... }
     }
   }
```

---

## Security Benefits

1. ✅ **Prevents Phone-Only Login** - Drivers cannot bypass password authentication
2. ✅ **Unified Endpoint** - Single endpoint handles both new and returning drivers
3. ✅ **Proper Authentication** - Returns token only after password verification
4. ✅ **Clear Error Messages** - Returns 401 for invalid password vs 409 for state errors
5. ✅ **Audit Logging** - Logs authentication attempts for security monitoring

---

## Response Formats

### Success Response (New Driver)
```json
{
  "success": true,
  "message": "Password set successfully",
  "data": {
    "authenticated": false,
    "is_returning": false,
    "next_step": "submit_profile",
    "onboarding_state": "password_set",
    "state_version": 2
  }
}
```

### Success Response (Returning Driver)
```json
{
  "success": true,
  "message": "Login successful. Welcome back!",
  "data": {
    "authenticated": true,
    "is_returning": true,
    "token": "eyJ0eXAiOiJKV1QiLCJh...",
    "token_type": "Bearer",
    "token_expires_at": "2026-01-10T14:30:00+00:00",
    "next_step": "submit_profile",
    "onboarding_state": "password_set",
    "state_version": 3,
    "driver": {
      "id": "uuid",
      "first_name": "John",
      "last_name": "Doe",
      "phone": "+201234567890",
      "is_approved": false
    }
  }
}
```

### Error Response (Invalid Password)
```json
{
  "success": false,
  "message": "Invalid password. Please try again.",
  "error": {
    "code": "INVALID_PASSWORD",
    "message": "Invalid password. Please try again."
  }
}
```

---

## Testing Recommendations

1. **Test New Driver Flow:**
   - Register with new phone
   - Set password
   - Complete onboarding

2. **Test Returning Driver Flow:**
   - Enter existing phone
   - Enter correct password → should login
   - Enter wrong password → should get 401 error

3. **Test Edge Cases:**
   - Driver with verified phone but no password
   - Driver who started registration but never finished
   - Multiple login attempts with wrong password

---

## Backward Compatibility

✅ **Fully backward compatible** - New drivers continue through normal onboarding flow without any changes.

---

## Related Files Modified

1. `/app/Services/Driver/DriverOnboardingService.php` - Core authentication logic
2. `/app/Http/Controllers/Api/V2/Driver/DriverOnboardingController.php` - API endpoint handler
3. `/Modules/UserManagement/Http/Controllers/Api/New/Driver/V2/DriverOnboardingV2Controller.php` - Legacy V2 controller (if used)

---

**Date:** 2026-01-08
**Status:** ✅ Implemented and Ready for Testing
