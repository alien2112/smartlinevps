# Driver Referral Endpoints - What They Return

## Endpoint 1: `/api/driver/referral-details`
**Returns:** Referral code and earning **RATES** (settings)

```json
{
  "referral_code": "TEST-B3589981",
  "share_code_earning": 100,    // ← This is the RATE (how much they earn per referral)
  "use_code_earning": 100        // ← This is the RATE (how much they earn when using someone's code)
}
```

**What this means:**
- `share_code_earning: 100` = Driver earns 100 when someone uses their referral code
- `use_code_earning: 100` = Driver earns 100 when they use someone else's referral code
- These are **settings/rates**, NOT actual earnings

---

## Endpoint 2: `/api/driver/transaction/referral-earning-list`
**Returns:** Actual **TRANSACTION HISTORY** of referral earnings

```json
{
  "total_size": 0,
  "data": []  // ← Empty because no referral transactions have occurred yet
}
```

**What this means:**
- This shows actual earnings **transactions** that have been recorded
- Empty array = No referrals have happened yet, so no earnings transactions exist
- When referrals occur, transactions will appear here

---

## Summary

| Endpoint | What It Returns | Test Result |
|----------|----------------|-------------|
| `/referral-details` | Earning **RATES** (settings) | ✅ Returned rates: 100, 100 |
| `/referral-earning-list` | Actual earnings **TRANSACTIONS** | ✅ Returned empty (no referrals yet) |

**Both endpoints are working correctly!**
- First shows the earning rates (what they'll earn)
- Second shows actual earnings history (empty for new driver with no referrals)
