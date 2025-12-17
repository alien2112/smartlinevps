# Testing Laravel + Node.js Together - Complete Guide

## Overview

This guide will help you run and test both the Laravel backend and Node.js real-time service simultaneously. The architecture consists of:

- **Laravel (Port 8000)**: REST API for business logic, database operations, authentication
- **Node.js (Port 3000)**: WebSocket server for real-time features (location tracking, ride dispatch)
- **Redis**: Bridge between Laravel and Node.js via pub/sub
- **MySQL**: Database for persistent data

---

## Prerequisites

Before starting, ensure you have:

- [x] PHP >= 8.1 installed
- [x] Composer installed
- [x] Node.js >= 18.0.0 installed
- [x] MySQL running
- [x] Redis installed and running
- [x] Postman installed (for API testing)

---

## Part 1: Setup Both Services

### Step 1: Configure and Start Redis

Redis is critical for communication between Laravel and Node.js.

```bash
# Check if Redis is running
redis-cli ping
# Expected output: PONG

# If not running, start Redis:
# Windows: redis-server
# Linux/Mac: sudo systemctl start redis
```

### Step 2: Configure Laravel

Your Laravel `.env` is already configured with:
```env
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
NODEJS_REALTIME_URL=http://localhost:3000
NODEJS_REALTIME_API_KEY=smartline-internal-key-change-in-production
```

No changes needed!

### Step 3: Configure Node.js Service

```bash
# Navigate to realtime service directory
cd realtime-service

# Create .env file if it doesn't exist
copy .env.example .env
```

Edit `realtime-service/.env` with the following:

```env
# Server Configuration
NODE_ENV=development
PORT=3000
HOST=0.0.0.0

# Redis Configuration (must match Laravel)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DB=0

# Laravel API Configuration
LARAVEL_API_URL=http://localhost:8000
LARAVEL_API_KEY=smartline-internal-key-change-in-production

# JWT Configuration (use Laravel's APP_KEY)
JWT_SECRET=aj6UCF3URvpY7oC92LcoKuDKWJqP2u5LKgSOBTP8mFQ=

# WebSocket Configuration
WS_CORS_ORIGIN=*
WS_PING_TIMEOUT=60000
WS_PING_INTERVAL=25000

# Location Tracking Configuration
LOCATION_UPDATE_THROTTLE_MS=1000
MAX_LOCATION_HISTORY=100
LOCATION_EXPIRY_SECONDS=3600

# Driver Matching Configuration
DRIVER_SEARCH_RADIUS_KM=10
MAX_DRIVERS_TO_NOTIFY=10
DRIVER_MATCH_TIMEOUT_SECONDS=120

# Performance Configuration
MAX_CONNECTIONS_PER_INSTANCE=10000
CLUSTER_MODE=false
WORKER_PROCESSES=2

# Logging
LOG_LEVEL=info
LOG_DIR=./logs
```

### Step 4: Install Node.js Dependencies

```bash
# Still in realtime-service directory
npm install
```

---

## Part 2: Start Both Services

You'll need **3 terminal windows**:

### Terminal 1: Start Laravel

```bash
# From project root directory
php artisan serve
```

Expected output:
```
Laravel development server started: http://127.0.0.1:8000
```

### Terminal 2: Start Node.js Real-time Service

```bash
# From realtime-service directory
cd realtime-service
npm run dev
```

Expected output:
```
[INFO] SmartLine Realtime Service starting...
[INFO] Connected to Redis successfully
[INFO] Subscribed to Laravel events
[INFO] WebSocket server listening on http://0.0.0.0:3000
```

### Terminal 3: Monitor Redis (Optional but Recommended)

```bash
redis-cli MONITOR
```

This will show all Redis commands in real-time, helping you debug communication between Laravel and Node.js.

---

## Part 3: Verify Both Services Are Running

### Test 1: Check Laravel Health

```bash
curl http://localhost:8000/api/health
```

### Test 2: Check Node.js Health

```bash
curl http://localhost:3000/health
```

Expected response:
```json
{
  "status": "ok",
  "service": "smartline-realtime",
  "uptime": 10.5,
  "timestamp": "2025-12-17T12:00:00.000Z",
  "connections": 0
}
```

### Test 3: Check Redis Connection

```bash
redis-cli ping
```

Expected output: `PONG`

---

## Part 4: Using the Enhanced Postman Collection

### Import the Collection

1. Open Postman
2. Click **Import**
3. Select: `SmartLine_Laravel_NodeJS_Testing.postman_collection.json`
4. Click **Import**

### Configure Collection Variables

1. Click on the collection name
2. Go to **Variables** tab
3. Set these values:

| Variable | Value | Description |
|----------|-------|-------------|
| `base_url` | `http://localhost:8000` | Laravel API base URL |
| `nodejs_url` | `http://localhost:3000` | Node.js service URL |
| `zone_id` | `778d28d6-1193-436d-9d2b-6c2c31185c8a` | Test zone ID (Cairo) |
| `auth_token` | (auto-filled) | JWT token from login |
| `driver_id` | (auto-filled) | Driver ID from login |
| `customer_id` | (auto-filled) | Customer ID from login |

4. Click **Save**

---

## Part 5: Testing Workflow

### Scenario 1: Complete Ride Flow

#### Step 1: Driver Login and Go Online

1. **Run: "1. Driver Login"**
   - Saves `auth_token` and `driver_id` automatically

2. **Run: "2. Node.js - Driver Go Online"**
   - Connects driver to WebSocket
   - Publishes location to Redis

#### Step 2: Customer Creates Ride Request

1. **Run: "3. Customer Login"**
   - Saves customer `auth_token` and `customer_id`

2. **Run: "4. Customer - Create Ride Request"**
   - Creates ride in Laravel database
   - Laravel publishes event to Redis
   - Node.js receives event and notifies nearby drivers

#### Step 3: Driver Receives and Accepts Ride

1. **Run: "5. Driver - Get Pending Rides"**
   - Should see the newly created ride

2. **Run: "6. Node.js - Driver Accept Ride"**
   - Driver accepts via WebSocket
   - Node.js calls Laravel internal API to assign driver
   - Customer receives real-time notification

#### Step 4: Track Ride in Real-time

1. **Run: "7. Node.js - Update Driver Location"**
   - Send driver GPS updates
   - Customer sees driver moving on map

2. **Run: "8. Customer - Subscribe to Ride Updates"**
   - Customer receives all real-time updates

#### Step 5: Complete the Ride

1. **Run: "9. Driver - Update Ride Status to Started"**
2. **Run: "10. Driver - Update Ride Status to Completed"**
3. **Run: "11. Customer - Rate Driver"**

---

## Part 6: Testing Individual Components

### Test Laravel API Endpoints

| Request | Purpose | Expected Result |
|---------|---------|-----------------|
| Driver Login | Authenticate driver | Returns JWT token |
| Customer Login | Authenticate customer | Returns JWT token |
| Get Pending Rides | List available rides | Returns array of pending rides |
| Create Ride Request | Customer requests ride | Creates trip in database |
| Update Ride Status | Change trip status | Updates database and publishes to Redis |

### Test Node.js WebSocket Events

| Request | Purpose | Expected Result |
|---------|---------|-----------------|
| Driver Go Online | Register driver as available | Stores location in Redis GEO |
| Update Driver Location | Send GPS coordinates | Updates Redis, broadcasts to customers |
| Driver Accept Ride | Accept a ride request | Calls Laravel API, assigns driver |
| Subscribe to Ride Updates | Customer tracks ride | Receives real-time location updates |

### Test Node.js Health Endpoints

| Endpoint | Purpose |
|----------|---------|
| `GET /health` | Check service health |
| `GET /metrics` | View active connections, drivers, rides |

---

## Part 7: Monitoring and Debugging

### View Laravel Logs

```bash
# Terminal 4
tail -f storage/logs/laravel.log
```

### View Node.js Logs

```bash
# Terminal 5
cd realtime-service
tail -f logs/combined.log
```

### Monitor Redis Activity

```bash
# Terminal 6
redis-cli MONITOR
```

You should see messages like:
```
1702841234.567890 [0 127.0.0.1:12345] "PUBLISH" "laravel:ride.created" "{\"ride_id\":\"...\",\"customer_id\":\"...\"}"
1702841234.678901 [0 127.0.0.1:54321] "GEOADD" "drivers:locations" "31.2357" "30.0444" "driver-uuid"
```

### Check Active Drivers in Redis

```bash
redis-cli
ZCARD drivers:locations
GEORADIUS drivers:locations 30.0444 31.2357 10 km WITHDIST
```

### Check Driver Status

```bash
redis-cli
HGETALL driver:status:YOUR_DRIVER_ID
```

---

## Part 8: Troubleshooting

### Issue: Node.js won't start

**Error:** "Cannot connect to Redis"

**Solution:**
1. Verify Redis is running: `redis-cli ping`
2. Check Redis host/port in `.env`
3. Check firewall isn't blocking port 6379

---

**Error:** "Port 3000 already in use"

**Solution:**
```bash
# Windows
netstat -ano | findstr :3000
taskkill /PID <process_id> /F

# Linux/Mac
lsof -ti:3000 | xargs kill -9
```

---

### Issue: Driver not receiving ride notifications

**Check:**
1. Driver is online: Look for "Driver went online" in Node.js logs
2. Laravel published event: Check Laravel logs for "Published realtime event"
3. Redis pub/sub working: Check `redis-cli MONITOR` output
4. Driver in correct zone: Verify `zone_id` matches ride's zone

---

### Issue: WebSocket connection fails

**Check:**
1. JWT token is valid (not expired)
2. CORS origin matches in Node.js `.env`
3. Port 3000 is accessible
4. Check browser console for errors

---

### Issue: High CPU/Memory usage

**Solution:**
1. Reduce `LOCATION_UPDATE_THROTTLE_MS` (increase value = less frequent updates)
2. Enable cluster mode: `CLUSTER_MODE=true`
3. Reduce `MAX_LOCATION_HISTORY`
4. Check for memory leaks: `pm2 monit`

---

## Part 9: Production Deployment

### For Production, use PM2 instead of npm run dev

```bash
# Install PM2 globally
npm install -g pm2

# Start Node.js service
cd realtime-service
pm2 start ecosystem.config.js --env production

# View status
pm2 status

# View logs
pm2 logs smartline-realtime

# Setup auto-start on reboot
pm2 startup
pm2 save
```

### Use Process Manager for Laravel (Supervisor)

Create `/etc/supervisor/conf.d/laravel-worker.conf`:

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/worker.log
```

---

## Part 10: Testing Checklist

Before going to production, verify:

- [ ] Laravel API responds: `curl http://localhost:8000/api/health`
- [ ] Node.js service responds: `curl http://localhost:3000/health`
- [ ] Redis is running: `redis-cli ping`
- [ ] Driver can login via Postman
- [ ] Customer can login via Postman
- [ ] Driver can go online (check Redis: `ZCARD drivers:locations`)
- [ ] Customer can create ride request
- [ ] Driver receives ride notification (check Node.js logs)
- [ ] Driver can accept ride
- [ ] Customer receives driver assignment notification
- [ ] Driver location updates appear in real-time
- [ ] Ride can be started and completed
- [ ] All status updates are logged correctly

---

## Quick Reference Commands

```bash
# Start Laravel
php artisan serve

# Start Node.js (development)
cd realtime-service && npm run dev

# Start Node.js (production)
cd realtime-service && pm2 start ecosystem.config.js

# Check Redis
redis-cli ping

# Monitor Redis
redis-cli MONITOR

# Check active drivers
redis-cli ZCARD drivers:locations

# View Laravel logs
tail -f storage/logs/laravel.log

# View Node.js logs
cd realtime-service && tail -f logs/combined.log

# PM2 commands
pm2 status
pm2 logs smartline-realtime
pm2 restart smartline-realtime
pm2 stop smartline-realtime
```

---

## Additional Resources

- Laravel Documentation: [https://laravel.com/docs](https://laravel.com/docs)
- Socket.IO Documentation: [https://socket.io/docs/](https://socket.io/docs/)
- Redis Documentation: [https://redis.io/docs/](https://redis.io/docs/)
- PM2 Documentation: [https://pm2.keymetrics.io/docs/](https://pm2.keymetrics.io/docs/)

---

## Need Help?

1. Check logs (Laravel, Node.js, Redis)
2. Review this guide
3. Check `realtime-service/README.md`
4. Review `NODEJS_REALTIME_DEPLOYMENT.md`

---

**Status: Ready for Testing**

You now have everything you need to run and test both Laravel and Node.js services together!
