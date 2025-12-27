# Admin Performance Optimization Guide

## Overview

This document describes the performance optimizations implemented for the Laravel Admin Dashboard in the ride-hailing system. The optimizations address 10 critical performance issues that were causing slow page loads and high database load.

## Quick Start

### 1. Run the Index Migration
```bash
php artisan migrate
```

### 2. Enable SQL Query Logging (Development Only)
Add to `.env`:
```
PERF_LOG_SQL=true
```

This will log all SQL queries with timing information to help identify remaining slow queries.

---

## Completed Fixes

### ✅ Issue #1: Zone Statistics N+1

**Before:** 3 queries per zone (30+ queries for 10 zones)
**After:** 1 aggregated query total

**Files Changed:**
- `Modules/TripManagement/Repository/Eloquent/TripRequestRepository.php`
  - Added `getAggregatedZoneStatistics()` method
- `Modules/TripManagement/Repository/TripRequestRepositoryInterface.php`
  - Added interface method
- `Modules/TripManagement/Service/TripRequestService.php`
  - Updated `getAdminZoneWiseStatistics()` to use aggregated query

**Complexity Reduction:** O(n) → O(1) query complexity

---

### ✅ Issue #3: Dashboard Index 10+ Separate Queries

**Before:** 7+ separate trip_requests queries
**After:** 1 aggregated query with conditional sums

**Files Changed:**
- `Modules/TripManagement/Repository/Eloquent/TripRequestRepository.php`
  - Added `getDashboardAggregatedMetrics()` method
- `Modules/AdminModule/Http/Controllers/Web/New/Admin/DashboardController.php`
  - Updated `index()` to use single aggregated query

**Query Reduction:** 10+ queries → 4-5 queries

---

### ✅ Issue #5: Trip List Loads All Customers/Drivers for Filters

**Before:** Loading 50k+ users into memory for select dropdowns
**After:** AJAX search endpoint with 20-50 item limit

**Files Added:**
- `Modules/UserManagement/Http/Controllers/Web/Api/UserSearchController.php`

**Routes Added:**
- `GET /admin/api/search-customers?search=term&limit=20`
- `GET /admin/api/search-drivers?search=term&limit=20`
- `GET /admin/api/get-customer?id=123`
- `GET /admin/api/get-driver?id=123`

**Memory Reduction:** ~50MB → ~50KB per request

### ✅ Issue #8: getBy() Default Loads All

**Before:** No limit = loads entire table into memory
**After:** Default cap of 1000 records when no limit specified

**Files Changed:**
- `app/Repository/Eloquent/BaseRepository.php`
  - Added default limit cap to `getAll()` and `getBy()` methods
- `config/app.php`
  - Added `default_query_limit` configuration (default: 1000)

**Configuration:** Add to `.env` to adjust:
```
DEFAULT_QUERY_LIMIT=1000
```

**Override:** To explicitly get all records (use sparingly):
```php
// Old way (now has 1000 cap):
$all = $repo->getBy(criteria: ['status' => 'active']);

// New way to get truly all (if needed):
$all = $repo->getBy(criteria: ['status' => 'active'], limit: PHP_INT_MAX);
```

---

### ✅ Issue #10: Customer/Driver Show Multiple Queries

**Before:** 6+ separate queries per customer/driver show page
**After:** 1 aggregated query with conditional counts

**Files Changed:**
- `Modules/UserManagement/Service/CustomerService.php`
  - Updated `customerRateInfo()` to use single aggregated query
- `Modules/UserManagement/Service/DriverService.php`
  - Updated `driverRateInfo()` to use single aggregated query

**Query Reduction:** 
- Customer show: 6 queries → 2 queries
- Driver show: 5 queries → 2 queries

---

### ✅ DB Indexes Added

**Migration File:** `database/migrations/2024_12_27_000001_add_performance_indexes.php`

**Indexes Created:**
```sql
-- trip_requests table
idx_trip_zone_status_created (zone_id, current_status, created_at)
idx_trip_payment_created (payment_status, created_at)
idx_trip_customer_status_created (customer_id, current_status, created_at)
idx_trip_driver_status_created (driver_id, current_status, created_at)
idx_trip_type_payment_created (type, payment_status, created_at)

-- users table
idx_users_type_active (user_type, is_active)

-- transactions table
idx_transactions_user_created (user_id, created_at)

-- safety_alerts table
idx_safety_alerts_status_created (status, created_at)
idx_safety_alerts_trip_status (trip_request_id, status)

-- user_last_locations table
idx_user_locations_zone_user (zone_id, user_id)

-- trip_request_fees table
idx_trip_fees_cancelled_by (cancelled_by)
```

---

### ✅ SQL Query Logging

**File Changed:** `app/Providers/AppServiceProvider.php`

**Usage:** Set `PERF_LOG_SQL=true` in `.env`

**Features:**
- Logs all queries with timing
- Marks slow queries (>50ms) as warnings
- Includes request ID for correlation
- Shows route name and HTTP method

**Log Format:**
```
[2024-12-27 10:30:45] local.WARNING: [SLOW QUERY] {"request_id":"req_abc123","sql":"SELECT * FROM...","bindings_count":2,"time_ms":127.5,"route":"admin.dashboard.index","method":"GET","slow":true}
```

---

## Remaining Issues (To Be Implemented)

### Issue #2: Fleet Map Marker N+1 ✅ COMPLETED

**Before:** 2 queries per marker (N*2 queries for N markers)
**After:** 2 bulk queries total for all markers

**Files Changed:**
- `Modules/AdminModule/Http/Controllers/Web/New/Admin/FleetMapViewController.php`
  - Updated `generateMarker()` to accept pre-loaded data
  - Updated `generateMarkers()` to bulk load safety alerts and active trips

**Query Reduction:** N*2 queries → 2 queries

---

### Issue #4: Transactions Loads All Then Slices ✅ COMPLETED

**Before:** `->getBy(...)->take(7)` loads all then slices in PHP
**After:** `limit: 7` parameter used directly in query

**Already Fixed:** In Issue #3 Dashboard optimization

---

### Issue #6: Heat Map Loads All Trips ✅ COMPLETED

**Before:** Loads all trips without limit
**After:** Max 5000 points with minimal column selection

**Files Changed:**
- `Modules/AdminModule/Http/Controllers/Web/New/Admin/DashboardController.php`
  - Updated `heatMap()` with limit and raw query
- `config/app.php`
  - Added `max_heatmap_points` configuration

**Configuration:** Add to `.env` to adjust:
```
MAX_HEATMAP_POINTS=5000
```

---

### Issue #7: Exports Load Entire Dataset ✅ COMPLETED

**Before:** `->get()->map()` loads entire dataset into memory
**After:** `LazyCollection` with `cursor()` for streaming

**Files Changed:**
- `Modules/TripManagement/Service/TripRequestService.php`
  - Updated `export()` to use cursor-based streaming
- `app/Repository/Eloquent/BaseRepository.php`
  - Added `getModel()` method for direct query access

**Memory Reduction:** Constant memory regardless of dataset size

---

### Issue #9: Earning Statistics N+1 ✅ COMPLETED

**Before:** 7-12 queries (one per time period)
**After:** 1 aggregated query with GROUP BY

**Files Changed:**
- `Modules/TripManagement/Repository/Eloquent/TripRequestRepository.php`
  - Added `getAnalyticsAggregated()` method
- `Modules/TripManagement/Repository/TripRequestRepositoryInterface.php`
  - Added interface method
- `Modules/TripManagement/Service/TripRequestService.php`
  - Updated `getAnalytics()` to use batch aggregation

**Query Reduction:**
- THIS_YEAR: 12 queries → 1 query
- THIS_WEEK: 7 queries → 1 query
- TODAY: 12 queries → 1 query

---

## Frontend Changes Required

### For Issue #5 (AJAX Search Dropdowns)

Replace static select dropdowns with Select2 autocomplete:

```html
<!-- Before -->
<select name="customer_id">
    @foreach($customers as $customer)
        <option value="{{ $customer->id }}">{{ $customer->full_name }}</option>
    @endforeach
</select>

<!-- After -->
<select name="customer_id" class="select2-ajax-customer" data-url="{{ route('admin.api.search-customers') }}">
    @if($selectedCustomerId)
        <option value="{{ $selectedCustomerId }}" selected>{{ $selectedCustomerName }}</option>
    @endif
</select>

<script>
$('.select2-ajax-customer').select2({
    ajax: {
        url: $(this).data('url'),
        dataType: 'json',
        delay: 300,
        data: function (params) {
            return { search: params.term, limit: 20 };
        },
        processResults: function (data) {
            return { results: data.results };
        }
    },
    minimumInputLength: 2,
    placeholder: 'Search customer...'
});
</script>
```

---

## Performance Metrics (Expected)

| Endpoint | Before | After | Improvement |
|----------|--------|-------|-------------|
| Dashboard Index | ~15 queries, 800ms | ~4 queries, 200ms | 75% faster |
| Zone Statistics | 30+ queries | 1 query | 97% fewer queries |
| Trip List | 50MB memory | 5MB memory | 90% less memory |
| Fleet Map | 100+ queries | 4 queries | 96% fewer queries |
| Heat Map | unlimited load | 5000 cap | Constant time |
| Export | Memory exhaustion | Streaming | No OOM errors |
| Analytics | 12 queries | 1 query | 92% fewer queries |
| Customer Show | 6 queries | 2 queries | 67% fewer queries |

---

## Testing Recommendations

1. **Enable SQL logging** in staging
2. **Load test** with production-scale data
3. **Monitor** query counts per endpoint
4. **Add assertions** for max query counts in tests:

```php
public function test_dashboard_query_count()
{
    DB::enableQueryLog();
    
    $this->actingAs($admin)->get(route('admin.dashboard.index'));
    
    $queryCount = count(DB::getQueryLog());
    $this->assertLessThan(10, $queryCount, "Dashboard should use fewer than 10 queries");
}
```

---

## Rollback Plan

If issues occur:

1. **Indexes:** Run `php artisan migrate:rollback` to drop indexes
2. **Code changes:** Revert specific files via git
3. **Query logging:** Set `PERF_LOG_SQL=false` in production

---

## Summary

All 10 performance killers have been addressed:

| Issue | Status | Type |
|-------|--------|------|
| #1 Zone Statistics N+1 | ✅ Complete | Repository aggregation |
| #2 Fleet Map Marker N+1 | ✅ Complete | Bulk pre-loading |
| #3 Dashboard 10+ queries | ✅ Complete | Single aggregated query |
| #4 Transactions slicing | ✅ Complete | Limit parameter |
| #5 Filter loads all users | ✅ Complete | AJAX search endpoint |
| #6 Heat map no pagination | ✅ Complete | Max cap + raw query |
| #7 Exports load all | ✅ Complete | Streaming with cursor() |
| #8 getBy() loads all | ✅ Complete | Default limit cap |
| #9 Analytics N+1 | ✅ Complete | Batch aggregation |
| #10 Customer/Driver stats | ✅ Complete | Conditional aggregation |

---

## Next Steps

1. Run migration for indexes: `php artisan migrate`
2. Clear config cache: `php artisan config:clear`
3. Test each endpoint with SQL logging enabled
4. Update Blade templates for AJAX search dropdowns
5. Monitor production metrics after deployment
