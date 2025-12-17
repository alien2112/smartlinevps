# Node.js Real-time Service Testing Guide

## Overview

This guide shows you how to test the Node.js real-time service with and without Redis.

**Redis is now OPTIONAL** - the service works in both modes:
- **With Redis:** Production mode, supports clustering and pub/sub
- **Without Redis:** Development/testing mode, uses in-memory storage

---

## Quick Start

### Test WITHOUT Redis (Easiest - No Setup Required)

```bash
cd realtime-service

# Redis is disabled by default in .env
# REDIS_ENABLED=false

# Start the service
npm start

# In another terminal, run tests
node test-service.js
```

### Test WITH Redis

```bash
cd realtime-service

# Enable Redis in .env
# Change: REDIS_ENABLED=false
# To:     REDIS_ENABLED=true

# Make sure Redis is running
redis-cli ping
# Should return: PONG

# Start the service
npm start

# In another terminal, run tests
REDIS_ENABLED=true node test-service.js
```

---

## Step-by-Step Testing

### Step 1: Start Laravel API

```bash
# In main project directory
php artisan serve

# Should start on http://localhost:8000
```

Keep this running in a terminal.

### Step 2: Start Node.js Service (WITHOUT Redis)

```bash
cd realtime-service

# Check .env file
cat .env | grep REDIS_ENABLED
# Should show: REDIS_ENABLED=false

# Start the service
npm start
```

**Expected Output:**
```
SmartLine Real-time Service started
  port: 3000
  host: 0.0.0.0
  environment: development
Using in-memory Redis mock (for testing only)
Redis is DISABLED - using in-memory fallback
```

### Step 3: Run Tests

In another terminal:

```bash
cd realtime-service
node test-service.js
```

**Expected Output:**
```
================================================================================
NODE.JS REAL-TIME SERVICE TEST
================================================================================
Service URL: http://localhost:3000
Redis Enabled: NO (In-Memory)
================================================================================

Test 1: Health Check
-------------------------------------------
✓ Service is running
  Status: ok
  Uptime: 5.123s
  Connections: 0

Test 2: Metrics Endpoint
-------------------------------------------
✓ Metrics endpoint working
  Active Drivers: 0
  Active Rides: 0
  Memory Usage: 45MB

Test 3: WebSocket Connection (Driver)
-------------------------------------------
✓ Driver connected to WebSocket
  Socket ID: abc123...

Test 4: Driver Goes Online
-------------------------------------------
✓ Driver went online successfully
  Message: You are now online

Test 5: Driver Location Update
-------------------------------------------
✓ Location update sent
  (No response expected - fire and forget)

Test 6: Ping/Pong (Heartbeat)
-------------------------------------------
✓ Heartbeat working
  Timestamp: 1702776000000

Test 7: WebSocket Connection (Customer)
-------------------------------------------
✓ Customer connected to WebSocket
  Socket ID: def456...

Test 8: Customer Subscribes to Ride
-------------------------------------------
✓ Customer subscribed to ride
  Ride ID: test-ride-id

Test 9: Check Metrics After Activity
-------------------------------------------
✓ Final metrics retrieved
  Connections: 2
  Active Drivers: 1
  Memory: 48MB

Cleanup
-------------------------------------------
✓ Disconnected all test clients

================================================================================
TEST SUMMARY
================================================================================
✓ All tests passed!
  Redis Mode: IN-MEMORY
  Service Status: OPERATIONAL
  WebSocket: WORKING
  Location Tracking: WORKING
  Heartbeat: WORKING
================================================================================
```

---

## Testing WITH Redis

### Step 1: Install and Start Redis (Windows)

**Option A: Using Chocolatey**
```powershell
choco install redis-64
redis-server
```

**Option B: Using WSL (Windows Subsystem for Linux)**
```bash
wsl
sudo service redis-server start
redis-cli ping
```

**Option C: Using Docker**
```bash
docker run -d -p 6379:6379 redis:latest
```

### Step 2: Enable Redis in .env

Edit `realtime-service/.env`:

```env
# Change from:
REDIS_ENABLED=false

# To:
REDIS_ENABLED=true
```

### Step 3: Verify Redis Connection

```bash
redis-cli ping
# Should return: PONG

redis-cli
127.0.0.1:6379> INFO
# Should show Redis server info
```

### Step 4: Start Node.js Service

```bash
cd realtime-service
npm start
```

**Expected Output:**
```
SmartLine Real-time Service started
  port: 3000
  host: 0.0.0.0
  environment: development
Redis client connected
Redis client ready
```

### Step 5: Run Tests

```bash
node test-service.js
```

All tests should pass with "Redis Mode: ENABLED"

---

## Manual Testing with WebSocket Client

### Using Browser Console

```javascript
// Open http://localhost:3000 in browser
// Open DevTools Console

const socket = io('http://localhost:3000', {
  auth: { token: 'test-token' }
});

socket.on('connect', () => {
  console.log('Connected!', socket.id);

  // Go online as driver
  socket.emit('driver:online', {
    location: { latitude: 30.0444, longitude: 31.2357 },
    vehicle_category_id: 'category-1',
    vehicle_id: 'vehicle-1',
    name: 'Test Driver'
  });
});

socket.on('driver:online:success', (data) => {
  console.log('Online!', data);

  // Send location update
  socket.emit('driver:location', {
    latitude: 30.0445,
    longitude: 31.2358,
    speed: 30,
    heading: 90
  });
});

// Test heartbeat
socket.emit('ping');
socket.on('pong', (data) => {
  console.log('Pong!', data);
});
```

### Using Postman

1. **Health Check**
   - Method: GET
   - URL: `http://localhost:3000/health`
   - Expected: `{"status":"ok",...}`

2. **Metrics**
   - Method: GET
   - URL: `http://localhost:3000/metrics`
   - Expected: `{"connections":0,"activeDrivers":0,...}`

---

## Testing Laravel Integration

### Test 1: Internal API Health Check

```bash
curl http://localhost:8000/api/internal/health
```

**Expected:**
```json
{
  "status": "ok",
  "service": "laravel-api",
  "timestamp": "2025-12-16T..."
}
```

### Test 2: Assign Driver (Internal API)

```bash
curl -X POST http://localhost:8000/api/internal/ride/assign-driver \
  -H "Content-Type: application/json" \
  -H "X-API-Key: smartline-internal-key-change-in-production" \
  -d '{
    "ride_id": "test-ride-id",
    "driver_id": "test-driver-id"
  }'
```

**Expected:** Should call Laravel's TripLockingService

### Test 3: Event Callback (Internal API)

```bash
curl -X POST http://localhost:8000/api/internal/events/ride.no_drivers \
  -H "Content-Type: application/json" \
  -H "X-API-Key: smartline-internal-key-change-in-production" \
  -d '{
    "ride_id": "test-ride-id"
  }'
```

**Expected:** `{"success":true}`

---

## Troubleshooting

### Issue: "Cannot connect to WebSocket"

**Check:**
1. Service is running: `curl http://localhost:3000/health`
2. Port is not blocked by firewall
3. CORS is configured: Check `.env` → `WS_CORS_ORIGIN=*`

**Solution:**
```bash
cd realtime-service
npm start
# Should show "SmartLine Real-time Service started"
```

### Issue: "Redis connection refused"

**Check:**
1. Redis is running: `redis-cli ping`
2. Redis host/port in `.env`: `REDIS_HOST=127.0.0.1` and `REDIS_PORT=6379`

**Solution:**
```bash
# Option 1: Start Redis
redis-server

# Option 2: Disable Redis (use in-memory)
# Edit .env: REDIS_ENABLED=false
```

### Issue: "Driver not going online"

**Check:**
1. WebSocket connection established
2. Auth token is valid (for testing, any token works)
3. Check logs: Service should log "Driver went online"

**Solution:**
```bash
# Check service logs
npm start
# Look for: "Driver went online"
```

### Issue: "Tests fail with authentication error"

**Note:** The test script uses a dummy token for testing. In production, you'll need real JWT tokens from Laravel.

**For Testing:**
- Auth is disabled by default in development
- Any token will work

**For Production:**
- Set `JWT_SECRET` in `.env` (same as Laravel)
- Use real JWT tokens from Laravel authentication

---

## Performance Testing

### Load Test with Artillery

Install Artillery:
```bash
npm install -g artillery
```

Create `load-test.yml`:
```yaml
config:
  target: "http://localhost:3000"
  phases:
    - duration: 60
      arrivalRate: 10
      name: "Ramp up"
scenarios:
  - name: "WebSocket Connection"
    engine: "socketio"
    flow:
      - emit:
          channel: "driver:online"
          data:
            location:
              latitude: 30.0444
              longitude: 31.2357
      - think: 5
      - emit:
          channel: "driver:location"
          data:
            latitude: 30.0445
            longitude: 31.2358
```

Run test:
```bash
artillery run load-test.yml
```

---

## Next Steps

Once testing is complete:

1. **Review Results:** Ensure all tests pass
2. **Test with Real Data:** Integrate with actual ride requests
3. **Update Frontend:** Connect mobile apps to WebSocket
4. **Deploy to Production:** Follow deployment guide
5. **Enable Redis:** For production clustering

---

## Summary

**What We Tested:**
- ✅ Node.js service starts successfully
- ✅ Health and metrics endpoints work
- ✅ WebSocket connections established
- ✅ Driver can go online
- ✅ Location updates processed
- ✅ Heartbeat/ping working
- ✅ Customer can subscribe to rides
- ✅ Works WITHOUT Redis (in-memory)
- ✅ Works WITH Redis (when enabled)
- ✅ Laravel internal API accessible

**Status:** Ready for integration with frontend apps! 🚀
