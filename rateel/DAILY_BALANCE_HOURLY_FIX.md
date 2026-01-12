# Daily Balance Hourly Breakdown - Fix Applied

## Issue
The `include_hourly=true` parameter in `/api/driver/wallet/daily-balance` was not returning hourly data.

## Root Cause
The hourly query was only looking for transactions in the `receivable_balance` account:
```php
->where('account', 'receivable_balance')  // ❌ Too restrictive
```

But drivers actually have transactions in multiple accounts:
- `receivable_balance` - Earnings (old format)
- `received_balance` - Earnings (current format)  
- `payable_balance` - Commissions/fees

## Fix Applied
**File:** `Modules/UserManagement/Http/Controllers/Api/New/Driver/DriverWalletController.php`

**Line:** 366-416

**Changes:**
1. ✅ Changed hourly query to include all relevant account types
2. ✅ Separated earnings and payable calculations (matching daily summary logic)
3. ✅ Added `net_earnings` to hourly breakdown
4. ✅ Added `payable` and `formatted_payable` fields

### New Query
```php
$hourlyData = Transaction::where('user_id', $driver->id)
    ->whereBetween('created_at', [$startOfDay, $endOfDay])
    ->whereIn('account', ['receivable_balance', 'received_balance', 'payable_balance'])
    ->selectRaw("
        HOUR(created_at) as hour,
        SUM(CASE WHEN account IN ('receivable_balance', 'received_balance') THEN credit ELSE 0 END) as earnings,
        SUM(CASE WHEN account = 'payable_balance' THEN credit ELSE 0 END) as payable,
        COUNT(*) as transactions
    ")
    ->groupBy('hour')
    ->orderBy('hour')
    ->get();
```

## New Response Format

### Before Fix
```json
{
  "hourly_breakdown": []  // Always empty!
}
```

### After Fix
```json
{
  "hourly_breakdown": [
    {
      "hour": "17:00",
      "earnings": 928.56,
      "formatted_earnings": "928.56 EGP",
      "payable": 266.96,
      "formatted_payable": "266.96 EGP",
      "net_earnings": 661.6,
      "formatted_net": "661.60 EGP",
      "trips": 1,
      "transactions": 2
    },
    {
      "hour": "18:00",
      "earnings": 928.56,
      "formatted_earnings": "928.56 EGP",
      "payable": 266.96,
      "formatted_payable": "266.96 EGP",
      "net_earnings": 661.6,
      "formatted_net": "661.60 EGP",
      "trips": 1,
      "transactions": 2
    }
  ]
}
```

## Testing

### Test Command
```bash
curl -X GET "https://smartline-it.com/api/driver/wallet/daily-balance?date=2026-01-10&include_hourly=true" \
  -H "Authorization: Bearer YOUR_DRIVER_TOKEN" \
  -H "Accept: application/json"
```

### Verified Results
✅ Hourly breakdown now shows data for hours with activity
✅ Earnings calculated correctly from both account types
✅ Payable (commission) separated and calculated
✅ Net earnings = earnings - payable
✅ Trips count matches hour
✅ Transaction count accurate

## Additional Improvements

### Added Fields to Hourly Breakdown:
- `payable` - Commission/fees for that hour
- `formatted_payable` - Formatted payable amount
- `net_earnings` - Earnings minus payable
- `formatted_net` - Formatted net earnings

This provides more detailed financial breakdown per hour, matching the daily summary structure.

## Cache Note
Response is cached for 30 minutes. After the fix, cache was cleared:
```bash
php artisan cache:clear
```

## Impact
- ✅ Hourly breakdown now works correctly
- ✅ Shows all transaction types (earnings + payable)
- ✅ Provides detailed hour-by-hour financial data
- ✅ Helps drivers understand their earnings timeline
- ✅ Only includes hours with activity (0-24 hours)

## Date: January 12, 2026
