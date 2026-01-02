# Kashier Payment Integration - Troubleshooting Guide

## Current Error: "Invalid Authorization"

**Error Response:**
```json
{
    "error": { "cause": "invalid authorization" },
    "messages": { "en": "Forbidden request" },
    "status": "FAILURE"
}
```

## Current Credentials Being Used

- **Merchant ID**: `MID-36316-436`
- **API Key**: `d5d3dd58-50b2-4203-b397-3f83b3a93f24`
- **Secret Key**: `59fcb1458a25070cfab354f3d1b3e62f$e2a9eda8e49f8dccda2e7c550cf5889a8ec99df5cafcbc05ea83e386f92f95984308afd707dc2433f75e064baeae395a`
- **Mode**: `live`

## Hash Generation Method

Currently using: **Part AFTER `$` in secret key**

```php
$secretParts = explode('$', $secretKey);
$actualSecret = $secretParts[1]; // Using this part
$hash = hash_hmac('sha256', $path, $actualSecret);
```

## Steps to Verify in Kashier Dashboard

### 1. Login to Dashboard
- Go to: https://merchant.kashier.io or https://dashboard.kashier.io
- Login with your merchant account

### 2. Verify API Credentials
Navigate to: **Settings → API Keys**

**Check:**
- ✅ Merchant ID matches: `MID-36316-436`
- ✅ API Key matches: `d5d3dd58-50b2-4203-b397-3f83b3a93f24`
- ✅ Secret Key matches: `59fcb1458a25070cfab354f3d1b3e62f$...`
- ✅ **IMPORTANT**: Are these marked as **LIVE** keys or **TEST** keys?

### 3. Check Account Status
Navigate to: **Account Settings** or **Profile**

**Verify:**
- ✅ Account status is **"Active"** or **"Verified"**
- ✅ KYC verification is **completed**
- ✅ Account is enabled for **live transactions**
- ✅ No account restrictions or holds

### 4. Check Integration Settings
Navigate to: **Settings → Integration** or **Payment Settings**

**Verify:**
- ✅ **Card Payments** are enabled
- ✅ **Wallet Payments** are enabled (if needed)
- ✅ **3D Secure** is enabled
- ✅ Domain `smartline-it.com` is **whitelisted** in allowed domains
- ✅ No IP restrictions blocking your server

### 5. Check Payment Methods
Navigate to: **Settings → Payment Methods**

**Verify:**
- ✅ All required payment methods are activated
- ✅ No payment method restrictions

## Common Issues & Solutions

### Issue 1: Using TEST Keys in LIVE Mode
**Symptom**: Invalid authorization error
**Solution**: Make sure you're using **LIVE** API keys, not test keys

### Issue 2: Account Not Activated
**Symptom**: Invalid authorization error
**Solution**: Contact Kashier support to activate your account for live transactions

### Issue 3: Domain Not Whitelisted
**Symptom**: Invalid authorization error
**Solution**: Add `smartline-it.com` to allowed domains in Kashier dashboard

### Issue 4: Wrong Secret Key Format
**Symptom**: Invalid authorization error
**Solution**: Verify the secret key format in dashboard matches exactly

### Issue 5: API Key Permissions
**Symptom**: Invalid authorization error
**Solution**: Check if API key has permissions for payment processing

## Test URLs Generated

### With API Key Parameter:
```
https://payments.kashier.io/?merchantId=MID-36316-436&orderId=...&amount=5.00&currency=EGP&hash=...&mode=live&apiKey=d5d3dd58-50b2-4203-b397-3f83b3a93f24&...
```

### Without API Key Parameter:
```
https://payments.kashier.io/?merchantId=MID-36316-436&orderId=...&amount=5.00&currency=EGP&hash=...&mode=live&...
```

## Contact Kashier Support

If all above checks pass, contact Kashier support:

**Email**: support@kashier.io

**Include in your email:**
```
Subject: Invalid Authorization Error - Live Mode Payment

Merchant ID: MID-36316-436
API Key: d5d3dd58-50b2-4203-b397-3f83b3a93f24
Mode: Live
Domain: smartline-it.com

Error Details:
{
    "error": { "cause": "invalid authorization" },
    "messages": { "en": "Forbidden request" },
    "status": "FAILURE"
}

We are trying to integrate the Hosted Payment Page but getting 
authorization errors. Please verify:
1. Is our account activated for live transactions?
2. Is the API key correct and enabled for live mode?
3. Are there any domain restrictions we need to configure?
4. Is the secret key format correct?
```

## Alternative: Try Test Mode First

To verify the integration works, try with **TEST** mode first:

1. Get your **TEST** API keys from Kashier dashboard
2. Update the code to use `mode=test`
3. Test the payment flow
4. If test works, then the issue is specifically with live credentials

## Hash Generation Alternatives to Test

If credentials are correct, try these hash methods:

1. **Current**: Using part AFTER `$` in secret key
2. **Alternative 1**: Using FULL secret key (both parts)
3. **Alternative 2**: Using part BEFORE `$` in secret key
4. **Alternative 3**: Using API key instead of secret key
