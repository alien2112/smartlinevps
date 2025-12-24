# SmartLine Deep Observability Guide

## Date: 2025-12-24

## Purpose

This document describes the observability layer added to help diagnose timing, ordering, and delivery issues in the SmartLine ride-hailing system.

**IMPORTANT**: This is OBSERVE ONLY. No fixes or logic changes have been made. The goal is to understand what's happening before making any changes.

---

## How to Use This Observability

### Step 1: Enable Debug Logging

**Laravel (.env)**
```env
APP_LOG_LEVEL=debug
LOG_CHANNEL=stack
```

**Node.js (.env)**
```env
LOG_LEVEL=debug
NODE_ENV=development
```

### Step 2: Start All Services

```bash
# Terminal 1: Laravel
cd rateel
php artisan serve

# Terminal 2: Queue Worker (watch for job logs)
cd rateel
php artisan queue:work --queue=high,default -vvv

# Terminal 3: Node.js Realtime Service
cd realtime-service
npm run dev
```

### Step 3: Tail Logs

```bash
# Laravel logs
tail -f rateel/storage/logs/laravel.log | grep OBSERVE

# Node.js logs
tail -f realtime-service/logs/combined.log | grep OBSERVE

# Rides only
tail -f realtime-service/logs/rides.log
```

---

## Log Format

All logs follow this structured format:

```json
{
  "timestamp": "2025-12-24T15:30:00.000Z",
  "timestamp_ms": 1735058200000,
  "service": "laravel-api | node-realtime",
  "trace_id": "trc_1735058200_abc123",
  "event_type": "...",
  "source": "http | socket | queue | fcm | redis | database",
  "status": "started | success | failed | timeout",
  "...context"
}
```

---

## Trace ID Correlation

Every request/event gets a unique `trace_id`:

1. **HTTP Request** → `TraceIdMiddleware` generates `trc_<timestamp>_<random>`
2. **Response Header** → `X-Trace-Id` header returned to client
3. **Redis Publish** → `trace_id` included in event data
4. **Node.js Receive** → Logs include same `trace_id`
5. **Socket Emit** → `trace_id` included in `_trace` object

### Example Flow Correlation

```
[Laravel] OBSERVE: Controller entry | trace_id=trc_1735058200_abc123
[Laravel] OBSERVE: Trip lock result | trace_id=trc_1735058200_abc123
[Laravel] OBSERVE: Redis publish | trace_id=trc_1735058200_abc123
[Node.js] OBSERVE: Redis event received | trace_id=trc_1735058200_abc123
[Node.js] OBSERVE: Emitting to client | trace_id=trc_1735058200_abc123
```

**If trace_ids don't match, events are not correlated!**

---

## Key Log Events to Watch

### 1. Trip Accept Flow

| Log Message | Source | What It Tells You |
|-------------|--------|-------------------|
| `Controller entry` | Laravel | Request arrived at server |
| `Trip lock result` | Laravel | Did DB lock succeed? |
| `Trip state change` | Laravel | Status changed from X to Y |
| `Redis publish` | Laravel | Event sent to Node.js |
| `Redis event received` | Node.js | Node.js got the event |
| `Emitting to client` | Node.js | Socket event sent to app |

### 2. OTP Verification Flow

| Log Message | Source | What It Tells You |
|-------------|--------|-------------------|
| `OTP verification` status=received | Laravel | Driver submitted OTP |
| `OTP verification` status=validated | Laravel | OTP format OK |
| `Trip state change` | Laravel | Status → ONGOING |
| `DB Transaction commit` | Laravel | DB persisted |
| `Redis publish` | Laravel | Event sent to Node.js |

### 3. Socket Events

| Log Message | Source | What It Tells You |
|-------------|--------|-------------------|
| `Socket connected` | Node.js | Client connected |
| `driver:accept:ride received` | Node.js | Accept event from app |
| `Calling handleDriverAcceptRide` | Node.js | Processing started |
| `driver:accept:ride completed` | Node.js | Processing finished |
| `ride:accept:failed` | Node.js | Something went wrong |

### 4. Background Jobs

| Log Message | Source | What It Tells You |
|-------------|--------|-------------------|
| `Job dispatched` | Laravel | Job queued |
| `Job started` | Laravel | Worker picked it up |
| `FCM attempt` | Laravel | Sending notification |
| `FCM result` | Laravel | Did it send? |
| `Job completed` | Laravel | Job finished |

---

## What to Look For

### Problem: "Driver must press Accept twice"

**Look for these in logs:**

1. Is `Controller entry` logged? → Request arrived
2. Is `Trip lock result` success=true? → Lock succeeded
3. Is `Redis publish` logged? → Event sent to Node
4. Is `Redis event received` logged in Node? → Node got it
5. Is `Emitting to client` logged? → Socket sent to app
6. Is `trip:accepted:confirmed` in socket emit? → Correct event?

**Red flags:**
- Long `duration_ms` between steps (> 2000ms)
- `status=error` anywhere
- Different `trace_id` between steps (correlation broken)
- `Redis publish` logged but no `Redis event received` (Redis issue)

### Problem: "OTP verification succeeds but app updates late"

**Look for these in logs:**

1. `OTP verification` status=received → Did request arrive?
2. `OTP verification` status=validated → Was OTP correct?
3. `DB Transaction commit` → Was DB updated?
4. `Redis publish` on channel `laravel:otp.verified` → Was event sent?
5. Node.js `OBSERVE: Emitting` with event `trip:otp:verified` → Socket sent?

**Red flags:**
- Missing `Redis publish` log
- Long delay between `DB commit` and `Redis publish`
- Node.js not logging the event receipt

### Problem: "FCM arrives late or not at all"

**Look for these in logs:**

1. `Job dispatched` → Was job queued?
2. `Job started` → How long between dispatch and start? (queue wait time)
3. `FCM attempt` → Is FCM token present?
4. `FCM result` → success=true or error message?
5. `duration_ms` on FCM result → How long did Firebase take?

**Red flags:**
- Large gap between `Job dispatched` and `Job started` (queue backlog)
- `has_fcm_token=false` → Token not in database
- `FCM result` status=failed → Firebase rejected it
- `duration_ms > 5000` → Firebase slow

---

## Timing Measurements

All logs include `duration_ms` where applicable:

| Location | Normal | Warning | Critical |
|----------|--------|---------|----------|
| HTTP Request | < 500ms | 500-2000ms | > 2000ms |
| DB Transaction | < 50ms | 50-200ms | > 200ms |
| Trip Lock | < 100ms | 100-500ms | > 500ms |
| Redis Publish | < 10ms | 10-50ms | > 50ms |
| Socket Handler | < 100ms | 100-500ms | > 500ms |
| FCM Send | < 2000ms | 2000-5000ms | > 5000ms |
| Queue Job Execution | < 3000ms | 3000-10000ms | > 10000ms |

---

## Grep Commands for Debugging

```bash
# All logs for a specific trip
grep "trip_id.*abc123" storage/logs/laravel.log

# All logs for a specific trace
grep "trc_1735058200_abc123" storage/logs/laravel.log

# All errors
grep "status.*error" storage/logs/laravel.log

# All slow operations
grep "slow" storage/logs/laravel.log

# Driver accept events in Node.js
grep "driver:accept:ride" realtime-service/logs/combined.log

# Redis events
grep "redis" realtime-service/logs/combined.log

# Socket emits
grep "Emitting" realtime-service/logs/combined.log

# FCM attempts and results
grep "fcm" storage/logs/laravel.log
```

---

## Files with Observability Added

### Laravel (rateel/)

| File | What's Logged |
|------|---------------|
| `app/Services/ObservabilityService.php` | Helper service for structured logging |
| `app/Http/Middleware/TraceIdMiddleware.php` | Request entry, exit, trace ID generation |
| `Modules/.../TripRequestController.php` | Controller entry/exit, validation, DB transactions |
| `app/Jobs/ProcessTripAcceptNotificationsJob.php` | Job lifecycle, FCM attempts/results |
| `app/Services/RealtimeEventPublisher.php` | Redis publish events |

### Node.js (realtime-service/)

| File | What's Logged |
|------|---------------|
| `src/utils/observability.js` | Helper service for structured logging |
| `src/utils/logger.js` | Enhanced logger with trace support |
| `src/server.js` | Socket connection, event handlers |
| `src/services/RedisEventBus.js` | Redis subscription, event routing |

---

## Next Steps After Observing

Once you have logs showing the issue:

1. **Identify the exact failure point** using trace_id correlation
2. **Measure the delays** using duration_ms
3. **Note the state** before/after each operation
4. **Document the pattern** (happens always? sometimes? under load?)

Then, and ONLY then, should you make fixes.

---

## Safety Notes

- All observability code is wrapped in try/catch to prevent logging from breaking the app
- No sensitive data (passwords, full FCM tokens) is logged
- Logs are truncated to prevent excessive disk usage
- This observability layer adds ~5-10ms overhead per request
