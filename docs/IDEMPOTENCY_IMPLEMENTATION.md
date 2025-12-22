# Database-Backed Idempotency Implementation

## Overview

This document describes the robust database-backed idempotency system that replaces the previous cache-based approach in the SmartLine trip management system.

## Problem with Cache-Based Idempotency

The previous implementation used Laravel's Cache to track trip acceptance:

```php
// Old approach - PROBLEMATIC
if (Cache::get($request['trip_request_id']) == ACCEPTED && $trip->driver_id == $driver->id) {
    return response()->json(responseFormatter(DEFAULT_UPDATE_200));
}
Cache::put($trip->id, ACCEPTED, now()->addHour());
```

### Issues:

1. **Cache Volatility**
   - Cache can be cleared, causing idempotency to fail
   - 1-hour TTL can expire mid-operation
   - Memory pressure can cause cache eviction

2. **No Request Deduplication**
   - Same API request (retry) treated as new request
   - No idempotency key in request headers
   - No request signature tracking

3. **Incomplete Idempotency Key**
   - Only checks trip ID
   - Doesn't include action type (accept vs reject vs cancel)
   - Doesn't include driver ID

## New Database-Backed Solution

### Architecture

The new system uses:

1. **Database Table** (`idempotency_keys`) - Persistent storage for idempotency
2. **Middleware** (`EnsureIdempotency`) - Request interception and validation
3. **Model** (`IdempotencyKey`) - Eloquent model for database operations
4. **Cleanup Command** - Automatic cleanup of old keys

### Database Schema

```sql
CREATE TABLE idempotency_keys (
    id CHAR(36) PRIMARY KEY,
    key VARCHAR(255) UNIQUE NOT NULL,
    resource_type VARCHAR(50) NULLABLE,
    resource_id CHAR(36) NULLABLE,
    request_hash VARCHAR(64) NULLABLE,
    response_code INT NULLABLE,
    response_body JSON NULLABLE,
    created_at TIMESTAMP NOT NULL,
    INDEX idx_key (key),
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_created (created_at)
);
```

### How It Works

1. **Client sends request with Idempotency-Key header:**
   ```
   POST /api/customer/trip/request-action
   Headers:
     Idempotency-Key: uuid-or-unique-string
     Content-Type: application/json
   Body:
     {
       "trip_request_id": "123",
       "driver_id": "456",
       "action": "accepted"
     }
   ```

2. **Middleware checks for existing key:**
   - If key exists with same request hash → return cached response
   - If key exists with different request hash → return 422 error
   - If key doesn't exist → process request and store response

3. **Request hash includes:**
   - HTTP method
   - Request path
   - Request body (excluding CSRF token)
   - User ID

4. **Race condition protection:**
   - Uses database row-level locking (`lockForUpdate()`)
   - Transaction-based to ensure atomicity

### Controller Changes

Removed cache-based idempotency checks and replaced with database state checks:

```php
// New approach - ROBUST
if ($trip->current_status == ACCEPTED && $trip->driver_id == $driver->id) {
    return response()->json(responseFormatter(DEFAULT_UPDATE_200));
}
```

The idempotency middleware handles duplicate request detection automatically.

## Usage

### For API Clients

Include an `Idempotency-Key` header in POST/PUT/PATCH requests:

```javascript
fetch('/api/customer/trip/request-action', {
  method: 'POST',
  headers: {
    'Idempotency-Key': generateUniqueId(), // Use UUID or unique identifier
    'Content-Type': 'application/json',
    'Authorization': 'Bearer ' + token
  },
  body: JSON.stringify({
    trip_request_id: '123',
    driver_id: '456',
    action: 'accepted'
  })
});
```

### Generating Idempotency Keys

Best practices for clients:

1. **Use UUIDs** for guaranteed uniqueness:
   ```javascript
   const idempotencyKey = crypto.randomUUID();
   ```

2. **Store key locally** for retries:
   ```javascript
   const key = localStorage.getItem('pending-trip-action-key') || crypto.randomUUID();
   localStorage.setItem('pending-trip-action-key', key);
   // After success, clear it
   localStorage.removeItem('pending-trip-action-key');
   ```

3. **Per-operation keys** - don't reuse keys across different operations

### Route Protection

Apply the `idempotency` middleware to routes that need protection:

```php
Route::post('/trip/request-action', [TripRequestController::class, 'requestAction'])
    ->middleware(['auth:api', 'idempotency']);
```

## Maintenance

### Automatic Cleanup

Old idempotency keys are automatically cleaned up hourly via scheduled command:

```php
// app/Console/Kernel.php
$schedule->command('idempotency:cleanup')->hourly();
```

### Manual Cleanup

Clean up keys older than 24 hours (default):
```bash
php artisan idempotency:cleanup
```

Clean up keys older than custom hours:
```bash
php artisan idempotency:cleanup --hours=48
```

### Monitoring

Monitor idempotency table size:
```sql
SELECT COUNT(*) FROM idempotency_keys;
SELECT MAX(created_at), MIN(created_at) FROM idempotency_keys;
```

## Edge Cases Handled

1. **Network retry** - Same request sent multiple times due to network issues
2. **Client-side retry** - User clicks button multiple times
3. **Load balancer retry** - Request retried by infrastructure
4. **Different payload** - Same key with different data returns 422 error
5. **Concurrent requests** - Row-level locking prevents race conditions
6. **Cache invalidation** - Database persistence eliminates cache issues

## Performance Considerations

1. **Database queries:**
   - Single index lookup for key check: `O(log n)`
   - Row-level lock only during transaction
   - Minimal overhead for non-idempotent requests

2. **Storage:**
   - ~500 bytes per key
   - With 24-hour retention: ~43,200 requests/day = ~21 MB storage
   - Automatic cleanup prevents unbounded growth

3. **Indexes:**
   - `idx_key` - Fast key lookups
   - `idx_resource` - Query by resource type/ID
   - `idx_created` - Efficient cleanup operations

## Migration Guide

### From Cache to Database

1. ✅ Database migration already run
2. ✅ Middleware registered in `app/Http/Kernel.php`
3. ✅ Controller code updated to remove cache calls
4. ✅ Cleanup command scheduled

### Adding to New Routes

Add `idempotency` middleware to route definition:

```php
Route::middleware(['idempotency'])->group(function () {
    Route::post('/critical-endpoint', [Controller::class, 'method']);
});
```

## Security Considerations

1. **Key uniqueness** - Clients must generate truly unique keys
2. **Key secrecy** - Keys don't need to be secret (unlike API keys)
3. **Payload validation** - Request hash prevents key reuse with different data
4. **User isolation** - Request hash includes user ID
5. **No sensitive data** - Response body stored as JSON (ensure no secrets in responses)

## Testing

### Test Idempotent Behavior

```bash
# First request
curl -X POST https://api.example.com/trip/request-action \
  -H "Idempotency-Key: test-key-123" \
  -H "Authorization: Bearer token" \
  -d '{"trip_request_id":"123","driver_id":"456","action":"accepted"}'

# Second request (should return same response)
curl -X POST https://api.example.com/trip/request-action \
  -H "Idempotency-Key: test-key-123" \
  -H "Authorization: Bearer token" \
  -d '{"trip_request_id":"123","driver_id":"456","action":"accepted"}'
```

### Test Payload Change Detection

```bash
# Different payload with same key (should return 422)
curl -X POST https://api.example.com/trip/request-action \
  -H "Idempotency-Key: test-key-123" \
  -H "Authorization: Bearer token" \
  -d '{"trip_request_id":"123","driver_id":"789","action":"accepted"}'
```

## Troubleshooting

### Issue: Idempotency not working

**Check:**
1. Is `Idempotency-Key` header present?
2. Is middleware applied to route?
3. Check `idempotency_keys` table for entries

### Issue: 422 errors on retry

**Cause:** Different payload with same key

**Solution:** Ensure exact same payload for retries, or use new key for new requests

### Issue: Table growing too large

**Check cleanup schedule:**
```bash
php artisan schedule:list | grep idempotency
```

**Manual cleanup:**
```bash
php artisan idempotency:cleanup --hours=12
```

## Files Modified/Created

### Created:
- `app/Models/IdempotencyKey.php` - Eloquent model
- `app/Http/Middleware/EnsureIdempotency.php` - Middleware
- `app/Console/Commands/CleanupIdempotencyKeys.php` - Cleanup command
- `docs/IDEMPOTENCY_IMPLEMENTATION.md` - This documentation

### Modified:
- `app/Http/Kernel.php` - Added middleware registration
- `app/Console/Kernel.php` - Added cleanup schedule
- `Modules/TripManagement/Http/Controllers/Api/New/Customer/TripRequestController.php` - Removed cache calls
- `Modules/TripManagement/Http/Controllers/Api/Customer/TripRequestController.php` - Removed cache calls

### Database:
- `2025_12_16_142111_create_idempotency_keys_table.php` - Migration (already existed)

## References

- [Stripe's Idempotency Guide](https://stripe.com/docs/api/idempotent_requests)
- [RFC 7231 - Idempotent Methods](https://tools.ietf.org/html/rfc7231#section-4.2.2)
- [Laravel Database Locking](https://laravel.com/docs/queries#pessimistic-locking)
