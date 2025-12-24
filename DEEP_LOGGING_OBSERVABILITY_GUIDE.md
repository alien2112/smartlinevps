# Deep Logging & Observability Guide

This guide explains the deep logging and observability system added to SmartLine to help debug delays, duplicate actions, and missing realtime updates.

## Overview

The logging system provides:
- **Trace ID correlation** across HTTP, Socket, Queue, and FCM
- **Lifecycle visibility** for requests, events, and jobs
- **Timing measurements** to identify delays
- **Status tracking** (started/success/failed/timeout)
- **Duplicate detection** for race conditions

## Log Format

All logs are structured JSON with consistent fields:

```json
{
  "trace_id": "abc123-uuid",
  "user_id": 1,
  "driver_id": 2,
  "ride_id": 100,
  "event_name": "ride_accept",
  "source": "http|socket|queue|fcm",
  "timestamp": "2024-01-01T12:00:00.000Z",
  "duration_ms": 150,
  "status": "started|success|failed|timeout"
}
```

---

## Laravel Logging

### TraceableLogService

Location: `app/Services/TraceableLogService.php`

#### Key Methods

```php
use App\Services\TraceableLogService;

// Get current trace ID (auto-generated if not exists)
$traceId = TraceableLogService::getTraceId();

// HTTP Request Lifecycle
$startTime = TraceableLogService::httpRequestStarted('endpoint/path', ['extra' => 'data']);
// ... do work ...
TraceableLogService::httpRequestCompleted('endpoint/path', $startTime, 200);

// Ride Acceptance Flow
$acceptStartTime = TraceableLogService::rideAcceptanceStarted($rideId, $driverId);
TraceableLogService::rideAcceptanceLockAcquired($rideId, $driverId, $acceptStartTime);
TraceableLogService::rideAcceptanceCompleted($rideId, $driverId, $customerId, $acceptStartTime);

// OTP Verification
$otpStartTime = TraceableLogService::otpVerificationStarted($rideId, $driverId);
TraceableLogService::otpVerificationCompleted($rideId, $driverId, true, $otpStartTime);

// FCM Notifications
$fcmStartTime = TraceableLogService::fcmDispatchStarted('notification_type', $userId, $rideId);
TraceableLogService::fcmDispatchCompleted('notification_type', true, $fcmStartTime, $userId, $rideId);

// Queue Jobs
$jobStartTime = TraceableLogService::queueJobStarted('JobName', ['ride_id' => $rideId]);
TraceableLogService::queueJobCompleted('JobName', true, $jobStartTime);

// Database Transactions
$dbStartTime = TraceableLogService::dbTransactionStarted('operation_name', $rideId);
TraceableLogService::dbTransactionCommitted('operation_name', $dbStartTime, $rideId);

// Duplicate Detection
TraceableLogService::duplicateActionDetected('action_type', $rideId, $userId);

// Timing Measurements
TraceableLogService::measureTimeBetween('event_a', 'event_b', $startTime, $rideId);
```

### Log Channels

Logs are written to different channels based on type:

| Channel | File | Purpose |
|---------|------|---------|
| `daily_json` | `storage/logs/api.log` | General API and ride events |
| `queue` | `storage/logs/queue.log` | Queue job lifecycle |
| `security` | `storage/logs/security.log` | Duplicate detection, suspicious activity |
| `performance` | `storage/logs/performance.log` | Slow requests, timing warnings |
| `websocket` | `storage/logs/websocket.log` | Socket events from Laravel side |

---

## Node.js Logging

### TraceableLogger

Location: `realtime-service/src/utils/traceableLogger.js`

#### Key Methods

```javascript
const traceableLogger = require('./utils/traceableLogger');

// Socket Connection Lifecycle
const traceId = traceableLogger.socketConnected(socketId, userId, userType);
traceableLogger.socketDisconnected(socketId, userId, userType, reason);

// Event Handler Lifecycle
const { traceId, timingKey } = traceableLogger.eventHandlerStarted('event:name', socketId, userId, data);
// ... handle event ...
traceableLogger.eventHandlerCompleted('event:name', true, timingKey);

// Socket Emit Lifecycle
traceableLogger.emitStarted('event:name', room, data);
traceableLogger.emitCompleted('event:name', room, true);

// Ride Dispatch
const { traceId, timingKey } = traceableLogger.rideDispatchStarted(rideId, customerId);
traceableLogger.rideDispatchCompleted(rideId, driverCount, timingKey);

// Driver Accept Ride
const { traceId, timingKey } = traceableLogger.driverAcceptStarted(driverId, rideId);
traceableLogger.driverAcceptCompleted(driverId, rideId, true, timingKey);

// Redis Events
traceableLogger.redisEventReceived(channel, data);
traceableLogger.redisEventProcessed(channel, true, durationMs);

// Laravel API Calls
const { timingKey } = traceableLogger.laravelApiStarted('/api/endpoint', 'POST', data);
traceableLogger.laravelApiCompleted('/api/endpoint', true, 200, timingKey);

// Timing Measurements
traceableLogger.measureTimeBetween('event_a', 'event_b', startTimeMs, rideId);

// Anomaly Detection
traceableLogger.duplicateActionDetected('action_type', rideId, userId);
```

---

## Trace ID Correlation

### How Trace IDs Flow

1. **HTTP Request** → `X-Correlation-ID` header or auto-generated
2. **LogContext Middleware** → Attaches to all Laravel logs
3. **Queue Jobs** → Captured at dispatch, restored at execution
4. **Socket Events** → Generated per connection/event

### Correlating Logs

To trace a complete flow:

```bash
# Find all logs for a specific trace ID
grep "abc123-uuid" storage/logs/api*.log storage/logs/queue*.log

# In Node.js logs
grep "abc123-uuid" realtime-service/logs/combined.log
```

---

## Key Flows to Monitor

### 1. Ride Creation → Driver Notification

```
[Laravel] http_request_started (createRideRequest)
[Laravel] ride_event (trip_created)
[Laravel] queue_job_dispatched (SendPushNotificationJob)
[Node.js] redis_event_received (laravel:ride.created)
[Node.js] ride_dispatch_started
[Node.js] socket_emit_started (ride:new) [per driver]
[Node.js] socket_emit_completed (ride:new)
[Node.js] ride_dispatch_completed
[Laravel] queue_job_started (SendPushNotificationJob)
[Laravel] fcm_dispatch_started
[Laravel] fcm_dispatch_completed
[Laravel] queue_job_completed (SendPushNotificationJob)
```

### 2. Driver Accept → Customer Notification

```
[Node.js] event_handler_started (driver:accept:ride)
[Node.js] driver_accept_started
[Node.js] laravel_api_started (/api/internal/ride/assign-driver)
[Laravel] http_request_started (driver/requestAction)
[Laravel] ride_accept_lock_acquired
[Laravel] ride_status_change (pending → accepted)
[Laravel] fcm_dispatch_started (trip_otp)
[Laravel] http_request_completed
[Node.js] laravel_api_completed
[Node.js] socket_emit_started (ride:accept:success)
[Node.js] socket_emit_started (ride:driver_assigned)
[Node.js] driver_accept_completed
[Node.js] event_handler_completed (driver:accept:ride)
```

### 3. OTP Verification → Trip Start

```
[Laravel] http_request_started (matchOtp)
[Laravel] otp_verification_started
[Laravel] db_transaction_started (otp_status_update)
[Laravel] db_transaction_committed
[Laravel] otp_verification_completed
[Laravel] ride_status_change (accepted → ongoing)
[Laravel] http_request_completed
[Background] fcm_dispatch_started (trip_started)
[Background] socket_event_emitted (DriverTripStartedEvent)
```

---

## Debugging Common Issues

### 1. Request Not Received

Check: `grep "http_request_started" storage/logs/api*.log | grep "endpoint"`

If missing: Request never reached Laravel (network/routing issue)

### 2. Event Not Processed

Check Node.js logs:
```bash
grep "event_handler_started" realtime-service/logs/combined.log
grep "event_handler_completed" realtime-service/logs/combined.log
```

If started but not completed: Handler threw exception

### 3. Socket Emit Not Received by Client

Check:
```bash
grep "socket_emit_started" realtime-service/logs/combined.log
grep "socket_connected" realtime-service/logs/combined.log | grep "user_id"
```

If emitted but user not connected: Timing issue or user disconnected

### 4. Delay Between Events

Look for `duration_ms` in logs:
```bash
grep "duration_ms" storage/logs/api*.log | sort -t'"' -k4 -rn | head -20
```

Logs with `slow_request: true` or `delay_warning: true` indicate problems.

### 5. Duplicate Actions

Check security logs:
```bash
grep "duplicate_action" storage/logs/security*.log
```

---

## Grep/Filter Commands

### Find All Events for a Ride
```bash
grep '"ride_id":123' storage/logs/api*.log storage/logs/queue*.log
```

### Find Slow Requests (>1000ms)
```bash
grep "slow_request" storage/logs/performance*.log
```

### Find Failed Events
```bash
grep '"status":"failed"' storage/logs/*.log
```

### Find FCM Failures
```bash
grep "fcm_dispatch_completed.*failed" storage/logs/queue*.log
```

### Find Socket Connection Issues
```bash
grep "socket_disconnected" realtime-service/logs/combined.log | grep -v '"reason":"client namespace disconnect"'
```

### Timeline for Specific Trace ID
```bash
grep "abc123-uuid" storage/logs/*.log realtime-service/logs/*.log | sort -t'T' -k2
```

---

## Performance Thresholds

The logging system automatically flags:

| Metric | Threshold | Log Level |
|--------|-----------|-----------|
| HTTP Request | >1000ms | warning (performance channel) |
| Socket Event Handler | >1000ms | warning |
| Laravel API Call | >2000ms | warning |
| Event Delay | >2000ms | warning |

---

## Production Considerations

### Log Rotation

Logs are configured for daily rotation with retention:
- API logs: 7 days
- Queue logs: 7 days  
- Security logs: 30 days
- Performance logs: 7 days

### Log Volume

To reduce log volume in production:

1. Set `LOG_LEVEL=info` in `.env` (hides debug logs)
2. Location updates are sampled (only 10% logged unless on active ride)
3. Use `config('performance.logging.debug_hot_paths', false)` to disable hot path logs

### Viewing Logs

```bash
# Tail Laravel logs
tail -f storage/logs/api-*.log | jq '.'

# Tail Node.js logs
tail -f realtime-service/logs/combined.log

# Real-time monitoring for specific ride
tail -f storage/logs/*.log | grep --line-buffered '"ride_id":123'
```

---

## Summary

With this logging in place, you can now answer:

1. ✅ **Did the request/event arrive?** → Check `_started` logs
2. ✅ **Did it execute?** → Check `_completed` logs with duration
3. ✅ **Did it emit/send?** → Check `socket_emit_*` and `fcm_dispatch_*` logs
4. ✅ **Did the client receive it?** → Check client is `socket_connected`
5. ✅ **Where does the delay occur?** → Check `duration_ms` and `slow_*` warnings

Correlate using `trace_id` across all systems.
