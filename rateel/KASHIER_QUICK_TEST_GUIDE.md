# Kashier Payment - Quick Test Guide

## Immediate Testing Steps

### 1. Clear Cache
```bash
cd /var/www/laravel/smartlinevps/rateel
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

### 2. Verify Configuration
```bash
php artisan tinker
```
Then run:
```php
DB::table('addon_payment_settings')->where('key_name', 'kashier')->first();
```

Expected output should show:
- `mode`: 'test' or 'live'
- `test_values` or `live_values`: Should contain `merchant_id` and `api_key`

### 3. Test Payment Flow

#### From Flutter App:
1. Create a new trip request
2. Select Kashier as payment method
3. App should open WebView with payment page
4. **Expected**: Page redirects to `https://checkout.kashier.io` (NOT `test-checkout.kashier.io`)
5. Complete payment with test card
6. Verify redirect back to app with success/fail status

#### Test Cards (Kashier Test Mode):
- **Success**: 4508750015741019, CVV: 100, Expiry: 05/25
- **3DS Success**: 5123450000000008, CVV: 100, Expiry: 05/25
- **Decline**: 4000000000000002, CVV: 100, Expiry: 05/25

### 4. Monitor Logs in Real-Time
```bash
# Terminal 1: Watch Laravel logs
tail -f storage/logs/laravel.log | grep Kashier

# Terminal 2: Watch web server logs
tail -f /var/log/nginx/error.log
```

### 5. Verify Payment Status
```bash
php artisan tinker
```
```php
// Replace {payment_id} with your actual payment ID
$payment = \Modules\Gateways\Entities\PaymentRequest::find('{payment_id}');
echo "Is Paid: " . $payment->is_paid . "\n";
echo "Transaction ID: " . $payment->transaction_id . "\n";
echo "Payment Method: " . $payment->payment_method . "\n";
```

## Common Issues & Quick Fixes

### Issue 1: Still seeing ERR_NAME_NOT_RESOLVED
**Fix**: Clear all caches again:
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
composer dump-autoload
```

### Issue 2: "Invalid hash" error from Kashier
**Check**:
1. Verify API key in database matches Kashier dashboard
2. Check logs for the generated hash:
```bash
grep "Kashier payment initiated" storage/logs/laravel.log | tail -1
```

### Issue 3: Payment succeeds but trip not updated
**Check**:
1. Look for hook execution in logs:
```bash
grep "Calling hook" storage/logs/laravel.log
```
2. Verify payment record:
```bash
php artisan tinker
```
```php
$payment = \Modules\Gateways\Entities\PaymentRequest::latest()->first();
dd($payment->hook);
```

### Issue 4: Callback not working
**Check**:
1. Verify route is accessible:
```bash
curl -I https://smartline-it.com/payment/kashier/callback
```
2. Check callback logs:
```bash
grep "Kashier callback received" storage/logs/laravel.log
```

### Issue 5: Signature validation failing
**Debug**:
```bash
grep "signature validation failed" storage/logs/laravel.log | tail -1
```
This will show expected vs received signature.

## Quick SQL Queries

### Check Recent Payments
```sql
SELECT id, payment_amount, currency_code, is_paid, payment_method, transaction_id, created_at
FROM payment_requests
WHERE payment_method = 'kashier'
ORDER BY created_at DESC
LIMIT 10;
```

### Check Payment Configuration
```sql
SELECT key_name, mode, is_active, test_values, live_values
FROM addon_payment_settings
WHERE key_name = 'kashier';
```

### Update to Test Mode (if needed)
```sql
UPDATE addon_payment_settings
SET mode = 'test'
WHERE key_name = 'kashier';
```

### Update to Live Mode (Production)
```sql
UPDATE addon_payment_settings
SET mode = 'live'
WHERE key_name = 'kashier';
```

## Testing Checklist

- [ ] Cache cleared
- [ ] Configuration verified in database
- [ ] Payment page loads without errors
- [ ] URL is `checkout.kashier.io` (not `test-checkout.kashier.io`)
- [ ] Can complete test payment
- [ ] Callback receives success status
- [ ] Payment marked as paid in database
- [ ] Trip status updated
- [ ] Webhook receives confirmation (check logs)
- [ ] Failed payment handled correctly
- [ ] Logs show no errors

## Production Deployment Steps

### 1. Switch to Live Mode
```sql
UPDATE addon_payment_settings
SET mode = 'live',
    live_values = '{"merchant_id": "YOUR_LIVE_MID", "api_key": "YOUR_LIVE_API_KEY"}'
WHERE key_name = 'kashier';
```

### 2. Test with Real Small Amount
- Make a small real transaction (e.g., 1 EGP)
- Verify entire flow works
- Check webhook delivery

### 3. Enable in Production
```sql
UPDATE addon_payment_settings
SET is_active = 1
WHERE key_name = 'kashier';
```

### 4. Monitor First Transactions
```bash
# Keep this running for first few transactions
tail -f storage/logs/laravel.log | grep -E "(Kashier|payment)"
```

## Support

If issues persist:
1. Check `KASHIER_PAYMENT_INTEGRATION.md` for detailed troubleshooting
2. Review logs: `storage/logs/laravel.log`
3. Contact Kashier support: support@kashier.io
4. Check Kashier dashboard for transaction details

---

**Integration Status**: ✓ Production Ready
**Last Updated**: 2025-12-24
