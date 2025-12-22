# Quick Reference: Production Fixes Implementation

## 🚀 **Quick Start (5 Minutes)**

### 1. Run Migrations
```bash
php artisan migrate
php artisan config:clear
php artisan cache:clear
```

### 2. Register Middleware in `app/Http/Kernel.php`
```php
protected $middlewareAliases = [
    'idempotency' => \App\Http\Middleware\IdempotencyMiddleware::class,
    'rate_limit' => \App\Http\Middleware\ApiRateLimiter::class,
];
```

### 3. Schedule Cleanup in `app/Console/Kernel.php`
```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('cleanup:stale-data')->everyFiveMinutes();
}
```

### 4. Update Trip Assignment Code

**Replace in `TripRequestController::requestAction()`:**

```php
// OLD CODE (REMOVE):
Cache::put($trip->id, ACCEPTED, now()->addHour());
$this->trip->update(['driver_id' => $user->id, ...], $trip_id);

// NEW CODE (USE):
use App\Services\TripLockingService;

$lockingService = app(TripLockingService::class);
$result = $lockingService->lockAndAssignTrip($trip_id, $user->id);

if (!$result['success']) {
    return response()->json(['message' => $result['message']], 409);
}

$trip = $result['trip'];
```

### 5. Apply Middleware to Routes in `Modules/TripManagement/Routes/api.php`
```php
Route::middleware(['idempotency', 'rate_limit:trip_accept'])->post('trip-action', ...);
Route::middleware(['rate_limit:location_update'])->post('location', ...);
```

---

## 📝 **Code Snippets**

### Accept Trip with Locking
```php
use App\Services\TripLockingService;

$result = app(TripLockingService::class)->lockAndAssignTrip(
    tripId: $request->trip_request_id,
    driverId: auth()->id()
);

if ($result['success']) {
    // Trip assigned successfully
    $trip = $result['trip'];
} else {
    // Already assigned or conflict
    return response()->json(['error' => $result['message']], 409);
}
```

### Update Driver Availability Atomically
```php
use App\Services\TripLockingService;

app(TripLockingService::class)->updateDriverAvailabilityAtomic(
    driverId: auth()->id(),
    availabilityStatus: 'unavailable',
    isOnline: false
);
```

### WebSocket Connection Management
```php
use App\Services\WebSocketCleanupService;

$wsService = app(WebSocketCleanupService::class);

// Register connection
$wsService->registerConnection($socket->id, $user->id);

// Heartbeat
$wsService->heartbeat($socket->id);

// Remove connection
$wsService->removeConnection($socket->id);
```

---

## 🔧 **Configuration**

### Rate Limits (Customize in `app/Http/Middleware/ApiRateLimiter.php`)
```php
protected array $limits = [
    'trip_accept' => ['max' => 10, 'decay' => 60],      // 10 per minute
    'location_update' => ['max' => 100, 'decay' => 60], // 100 per minute
    'general' => ['max' => 60, 'decay' => 60],          // 60 per minute
];
```

### Idempotency TTL (Default: 24 hours)
Change in `app/Http/Middleware/IdempotencyMiddleware.php`:
```php
'expires_at' => now()->addHours(24), // Change to your preference
```

---

## 🧪 **Testing**

### Test Race Condition Fix
```bash
# Simulate 10 concurrent trip accepts
for i in {1..10}; do
  curl -X POST http://localhost/api/driver/ride/trip-action \
    -H "Authorization: Bearer DRIVER_$i_TOKEN" \
    -d '{"action": "accept", "trip_request_id": "same-trip-id"}' &
done
wait

# Expected: Only 1 succeeds, 9 get "already assigned" error
```

### Test Idempotency
```bash
# Send request twice with same key
IDEMPOTENCY_KEY="test-$(date +%s)"

curl -X POST http://localhost/api/driver/ride/trip-action \
  -H "Idempotency-Key: $IDEMPOTENCY_KEY" \
  -H "Authorization: Bearer TOKEN" \
  -d '{"action": "accept", "trip_request_id": "xyz"}'

# Wait 5 seconds
sleep 5

# Same request - should return cached response
curl -X POST http://localhost/api/driver/ride/trip-action \
  -H "Idempotency-Key: $IDEMPOTENCY_KEY" \
  -H "Authorization: Bearer TOKEN" \
  -d '{"action": "accept", "trip_request_id": "xyz"}' \
  -v  # Check for X-Idempotent-Replay header
```

### Test Rate Limiting
```bash
# Exceed limit (send 15 when limit is 10)
for i in {1..15}; do
  echo "Request $i"
  curl -X POST http://localhost/api/driver/ride/trip-action \
    -H "Authorization: Bearer TOKEN" \
    -w "\nStatus: %{http_code}\n"
  sleep 0.5
done

# Requests 11-15 should return 429
```

---

## 📊 **Monitoring Queries**

### Check Active Locks
```sql
SELECT id, driver_id, locked_at, TIMESTAMPDIFF(MINUTE, locked_at, NOW()) as minutes_locked
FROM trip_requests
WHERE locked_at IS NOT NULL
ORDER BY locked_at DESC;
```

### Check Idempotency Replays
```sql
SELECT
    endpoint,
    COUNT(*) as total_requests,
    COUNT(DISTINCT idempotency_key) as unique_requests,
    (COUNT(*) - COUNT(DISTINCT idempotency_key)) as replays
FROM idempotency_keys
WHERE created_at > NOW() - INTERVAL 1 HOUR
GROUP BY endpoint;
```

### Find Abandoned Trips
```sql
SELECT id, driver_id, locked_at, current_status
FROM trip_requests
WHERE locked_at < NOW() - INTERVAL 1 HOUR
  AND current_status = 'accepted'
  AND trip_start_time IS NULL;
```

---

## 🐛 **Troubleshooting**

### Issue: "Deadlock detected"
**Solution:** Already handled. Transactions retry automatically up to 5 times.

### Issue: "Optimistic lock conflict"
**Solution:** Normal. Client should retry the request.

### Issue: "Too many rate limit hits"
**Solution:** Increase limits in `ApiRateLimiter.php` or whitelist specific IPs.

### Issue: "Idempotency key already exists"
**Solution:** Duplicate request detected. Return cached response (automatic).

---

## 🔥 **Rollback Plan**

If critical issues occur:

```bash
# 1. Rollback migrations
php artisan migrate:rollback --step=2

# 2. Comment out middleware in Kernel.php
# 'idempotency' => \App\Http\Middleware\IdempotencyMiddleware::class,
# 'rate_limit' => \App\Http\Middleware\ApiRateLimiter::class,

# 3. Remove middleware from routes
# Remove ->middleware(['idempotency', 'rate_limit:trip_accept'])

# 4. Clear caches
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

---

## ✅ **Checklist Before Production**

- [ ] Migrations run successfully
- [ ] Middleware registered in Kernel
- [ ] Trip assignment code updated to use TripLockingService
- [ ] Routes have rate limiting middleware
- [ ] Cleanup command scheduled in cron
- [ ] Tested race condition scenarios
- [ ] Tested idempotency with retries
- [ ] Tested rate limiting
- [ ] Monitoring queries set up
- [ ] Logs configured and checked

---

## 📞 **Need Help?**

- Full documentation: `PRODUCTION_READINESS_FIXES.md`
- Frontend spec: `FRONTEND_LOCATION_TRACKING_SPEC.md`
- Deployment guide: `LOCATION_TRACKING_DEPLOYMENT.md`

---

**Last Updated:** 2025-12-16
