# Kashier Payment Gateway Setup Guide

## SmartLine Ride-Hailing Platform

This document explains how to configure the Kashier payment gateway for SmartLine.

---

## Overview

SmartLine uses **Kashier** as its primary payment gateway. Kashier is an Egyptian payment provider supporting:
- Credit/Debit Cards (Visa, Mastercard)
- Digital Wallets
- 3D Secure (3DS) authentication

**Official Documentation:** https://developers.kashier.io/

---

## Configuration Settings

### Database Configuration

Kashier settings are stored in the `settings` table with:
- `key_name`: `kashier`
- `settings_type`: `payment_config`

### Required Configuration Values

```json
{
    "merchant_id": "MID-XXXXX-XXX",
    "public_key": "your-public-key",
    "secret_key": "your-api-secret-key",
    "callback_url": "https://yourdomain.com/payment/kashier/callback",
    "webhook_url": "https://yourdomain.com/payment/kashier/webhook",
    "currency": "EGP",
    "brand_color": "#00bcbc"
}
```

### Configuration Fields Explained

| Field | Description | Example |
|-------|-------------|---------|
| `merchant_id` | Your Kashier Merchant ID | `MID-36316-436` |
| `public_key` | Public API key for client-side operations | `pk_xxxxx` |
| `secret_key` | Secret API key for server-side operations (HMAC signing) | `8e034a5d-3fd0-40f7-b63f-9060144cea5c` |
| `callback_url` | URL where Kashier redirects after payment | `https://yourdomain.com/payment/kashier/callback` |
| `webhook_url` | URL for server-to-server payment notifications | `https://yourdomain.com/payment/kashier/webhook` |
| `currency` | Payment currency code | `EGP` |
| `brand_color` | Checkout page brand color | `#00bcbc` |

---

## Getting Kashier API Keys

1. Sign up at [Kashier Dashboard](https://dashboard.kashier.io/)
2. Complete business verification
3. Navigate to **Settings** → **API Keys**
4. Copy your:
   - **Merchant ID** (starts with `MID-`)
   - **Test API Key** (for testing)
   - **Live API Key** (for production)

---

## Setting Up in Admin Panel

1. Log into SmartLine Admin Panel
2. Navigate to **Business Settings** → **Payment Configuration**
3. Enable **Kashier** payment gateway
4. Fill in the configuration values:
   - Mode: `test` or `live`
   - Merchant ID
   - Secret Key (API Key)
   - Currency: `EGP`
5. Save settings

---

## SQL Configuration (Alternative)

If you need to configure directly in the database:

```sql
-- Insert or update Kashier configuration
INSERT INTO settings (key_name, settings_type, mode, live_values, test_values, created_at, updated_at)
VALUES (
    'kashier',
    'payment_config',
    'test',
    '{"merchant_id":"MID-XXXXX-XXX","secret_key":"your-live-secret-key","currency":"EGP","callback_url":"https://yourdomain.com/payment/kashier/callback","webhook_url":"https://yourdomain.com/payment/kashier/webhook","brand_color":"#00bcbc"}',
    '{"merchant_id":"MID-XXXXX-XXX","secret_key":"your-test-secret-key","currency":"EGP","callback_url":"https://yourdomain.com/payment/kashier/callback","webhook_url":"https://yourdomain.com/payment/kashier/webhook","brand_color":"#00bcbc"}',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    live_values = VALUES(live_values),
    test_values = VALUES(test_values),
    updated_at = NOW();
```

---

## Payment Flow

### 1. Payment Initiation
```
Customer → App → Laravel API → Create PaymentRequest → Redirect to Kashier
```

### 2. Kashier Hosted Payment Page
```
Customer → Kashier UI → Enter Card Details → 3DS Authentication → Payment Processing
```

### 3. Payment Callback
```
Kashier → Redirect to callback URL → Laravel validates signature → Update payment status
```

### 4. Webhook Notification (Server-to-Server)
```
Kashier → POST to webhook URL → Laravel processes → Confirm payment
```

---

## Security: Signature Validation

Kashier uses **HMAC-SHA256** for signature validation.

### Hash Generation (for Payment URL)
```php
$hashPath = "/?payment={$merchantId}.{$orderId}.{$amount}.{$currency}";
$hash = hash_hmac('sha256', $hashPath, $secretKey, false);
```

### Signature Validation (for Callback)
```php
// Build query string from params (excluding 'signature' and 'mode')
$params = $request->except(['signature', 'mode']);
ksort($params); // Sort alphabetically
$queryString = http_build_query($params);

// Generate signature
$generatedSignature = hash_hmac('sha256', $queryString, $secretKey, false);

// Compare with received signature
$isValid = hash_equals($generatedSignature, $request->query('signature'));
```

---

## Testing

### Test Cards

| Card Number | Result |
|-------------|--------|
| 4111 1111 1111 1111 | Successful payment |
| 4000 0000 0000 0002 | Declined |
| 4000 0000 0000 3220 | 3DS Required |

**Expiry:** Any future date  
**CVV:** Any 3 digits

### Test Mode
Set `mode` to `test` in the configuration to use test API keys and sandbox environment.

---

## Troubleshooting

### Common Issues

1. **Signature validation failed**
   - Ensure `secret_key` is correct
   - Check that parameters are sorted alphabetically
   - Verify URL encoding is consistent

2. **Payment not found**
   - Session may have expired
   - Payment already processed
   - Check `payment_requests` table

3. **Callback not received**
   - Verify callback URL is accessible
   - Check SSL certificate validity
   - Ensure no firewall blocking

### Enable Debug Logging

Check logs in:
- `storage/logs/laravel.log`
- Search for "Kashier" entries

---

## API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/payment/kashier/pay` | GET/POST | Initiate payment |
| `/payment/kashier/callback` | GET | Handle payment callback |
| `/payment/kashier/webhook` | POST | Handle webhook notifications |

---

## Environment Variables (Optional)

For additional flexibility, you can add to `.env`:

```env
# Kashier Configuration (optional - can use database config instead)
KASHIER_MERCHANT_ID=MID-XXXXX-XXX
KASHIER_SECRET_KEY=your-secret-key
KASHIER_MODE=test
KASHIER_CURRENCY=EGP
```

---

## Support

- **Kashier Support:** https://kashier.io/contact
- **Kashier Documentation:** https://developers.kashier.io/
- **Kashier Dashboard:** https://dashboard.kashier.io/

---

*Last Updated: December 19, 2025*
