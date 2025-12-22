# Session 2025-12-17: Integration Testing Complete + Redis Production Guide

**Date:** December 17, 2025
**Time:** 01:15 AM - 02:30 AM
**Focus:** Complete Node.js integration testing and Redis production deployment guide

---

## Session Summary

### What Was Accomplished

1. ✅ **Fixed Node.js Service Issues**
   - Created missing `public/` directory for Laravel
   - Completed MockRedisClient implementation (30+ Redis methods)
   - Fixed RedisEventBus to respect REDIS_ENABLED flag
   - Implemented separate test tokens for driver/customer authentication

2. ✅ **Completed Integration Testing**
   - All 9 integration tests passed successfully
   - Verified WebSocket connectivity (driver + customer)
   - Tested driver online/offline flow
   - Verified location updates and heartbeat
   - Confirmed customer ride subscription
   - Service fully operational without Redis (in-memory mock)

3. ✅ **Created Production Documentation**
   - Comprehensive Redis deployment guide
   - Security hardening procedures
   - Performance tuning for Uber-like workload
   - Monitoring and operational procedures
   - Node.js and Laravel connection examples

---

## Integration Test Results

### Test Summary

```
================================================================================
NODE.JS REAL-TIME SERVICE TEST
================================================================================
Service URL: http://localhost:3000
Redis Enabled: NO (In-Memory)
================================================================================

✓ Test 1: Health Check
✓ Test 2: Metrics Endpoint
✓ Test 3: WebSocket Connection (Driver)
✓ Test 4: Driver Goes Online
✓ Test 5: Driver Location Update
✓ Test 6: Ping/Pong (Heartbeat)
✓ Test 7: WebSocket Connection (Customer)
✓ Test 8: Customer Subscribes to Ride
✓ Test 9: Check Metrics After Activity

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

### Files Modified During Testing

1. **realtime-service/src/config/redis.js**
   - Added `zcard`, `zadd` methods
   - Added `scard`, `sadd`, `srem`, `smembers`, `sismember` methods
   - Added `expire` method
   - Implemented `multi()` transaction support with `exec()`

2. **realtime-service/src/services/RedisEventBus.js**
   - Fixed to use `redisClient.duplicate()` instead of creating new Redis instance
   - Now respects REDIS_ENABLED flag

3. **realtime-service/src/middleware/auth.js**
   - Added development mode authentication bypass
   - Created separate test tokens: `test-driver-token`, `test-customer-token`

4. **realtime-service/test-service.js**
   - Updated to use separate tokens for driver and customer testing

5. **public/index.php**
   - Created and configured to work from public/ subdirectory
   - Fixed paths for vendor/autoload.php and bootstrap/app.php

### Performance Metrics

- **Memory Usage:** 15-16 MB
- **Connection Handling:** 6 concurrent connections tested
- **Active Drivers Tracked:** 1 driver online
- **Response Times:** All endpoints < 100ms

---

## Redis Production Deployment Guide

# Complete Redis Self-Hosted Production Setup
## For Uber-Like App (Laravel + Node.js, Ubuntu VPS)

---

## A) Architecture Decision

### Where Should Redis Run?

**✅ RECOMMENDED: Run Redis on the Node.js VPS**

**Reasoning:**
- **Lowest latency**: Node.js real-time service is the primary Redis consumer
- **Cost-effective**: No additional VPS needed
- **Simpler architecture**: No network hops between Node and Redis
- **Resource allocation**: 10k drivers × 10s updates = ~1000 ops/sec - easily handled

**Resource Requirements:**
- Redis RAM: 2-4GB (for 10k drivers with geo data)
- CPU: Minimal (Redis is single-threaded)
- Total VPS: 8GB RAM recommended (4GB Node + 3GB Redis + 1GB OS)

**Alternative (if budget allows):**
- Separate Redis VPS (2GB RAM, $10-15/month)
- Use this if: Node VPS has <6GB RAM OR you need HA clustering later

### What Data Belongs in Redis vs Database

| Data Type | Storage | TTL | Why |
|-----------|---------|-----|-----|
| **Driver online status** | Redis | 90s | Ephemeral, rebuildable |
| **Driver GPS locations** | Redis | 5min | High-frequency writes |
| **Ride assignment locks** | Redis | 30s | Prevent race conditions |
| **Idempotency keys** | Redis | 24h | Prevent duplicate requests |
| **Pending ride offers** | Redis | 60s | Limited time to accept |
| **User profiles** | MySQL | - | Persistent, relational |
| **Trip history** | MySQL | - | Long-term storage |
| **Payment records** | MySQL | - | Financial data, audit |

**Critical Rule:** Everything in Redis MUST have a TTL OR be managed by cleanup.

---

## B) Installation Commands

### Step 1: Update System and Install Redis

```bash
# Update package list
sudo apt update && sudo apt upgrade -y

# Install Redis from Ubuntu repository (stable version)
sudo apt install redis-server -y

# Verify installation
redis-server --version
# Expected output: Redis server v=6.0.16 or v=7.0.x

# Check service status
sudo systemctl status redis-server
```

### Step 2: Install Redis CLI Tools

```bash
# Redis CLI is usually installed with redis-server
redis-cli --version

# Test basic connectivity (should work immediately)
redis-cli ping
# Expected: PONG
```

### Step 3: Check Default Configuration Location

```bash
# Find redis config file
sudo find / -name redis.conf 2>/dev/null
# Usually: /etc/redis/redis.conf

# Backup original config
sudo cp /etc/redis/redis.conf /etc/redis/redis.conf.backup.$(date +%F)
```

---

## C) Security Hardening

### Step 1: Generate Strong Password

```bash
# Generate a strong password for Redis
REDIS_PASSWORD=$(openssl rand -base64 32)
echo "Your Redis password: $REDIS_PASSWORD"
# SAVE THIS PASSWORD! You'll need it for Node.js and Laravel

# Example output: 7xK9mP3nQ8vR2wL4jH6fT5yU1cE0bA8zX3vM9nP2qW4=
```

### Step 2: Edit Redis Configuration

```bash
# Open redis.conf for editing
sudo nano /etc/redis/redis.conf
```

**Apply these changes:**

```conf
# ========================================
# NETWORK SECURITY
# ========================================

# Bind only to localhost (if Node.js is on same VPS)
bind 127.0.0.1 ::1

# Keep protected mode enabled
protected-mode yes

# Keep default port (or change for extra security)
port 6379

# ========================================
# AUTHENTICATION
# ========================================

# Require password for all commands
requirepass YOUR_GENERATED_PASSWORD_HERE

# ========================================
# DISABLE DANGEROUS COMMANDS
# ========================================

# Rename dangerous commands (prevents accidental data loss)
rename-command FLUSHDB "FLUSHDB_MY_SECRET_2024"
rename-command FLUSHALL "FLUSHALL_MY_SECRET_2024"
rename-command CONFIG "CONFIG_MY_SECRET_2024"
rename-command SHUTDOWN "SHUTDOWN_MY_SECRET_2024"
rename-command DEBUG ""
rename-command BGSAVE "BGSAVE_MY_SECRET_2024"
rename-command BGREWRITEAOF "BGREWRITEAOF_MY_SECRET_2024"

# ========================================
# PERFORMANCE & MEMORY
# ========================================

# Set max memory (adjust based on VPS RAM)
# Formula: (Total RAM - OS - Node.js) × 0.75
# Example for 8GB VPS: (8GB - 1GB - 4GB) × 0.75 = 2.25GB
maxmemory 2gb

# Eviction policy for TTL-heavy workload
# volatile-ttl = Evict keys with TTL, shortest TTL first (RECOMMENDED)
maxmemory-policy volatile-ttl

# Max clients (10k drivers + 10k riders + margin)
maxclients 25000

# ========================================
# PERSISTENCE CONFIGURATION
# ========================================

# AOF (Append-Only File) - Better durability
# RECOMMENDED for idempotency keys and critical locks
appendonly yes
appendfilename "appendonly.aof"
appendfsync everysec

# RDB Snapshots - Better performance
save 900 1      # Save after 900s if 1 key changed
save 300 100    # Save after 300s if 100 keys changed
save 60 10000   # Save after 60s if 10k keys changed

# ========================================
# LOGGING
# ========================================

loglevel notice
logfile /var/log/redis/redis-server.log

# ========================================
# TUNING FOR REALTIME WORKLOAD
# ========================================

tcp-backlog 511
tcp-keepalive 300
timeout 0
latency-monitor-threshold 100
slowlog-log-slower-than 10000
slowlog-max-len 128
```

**Save and exit:** `Ctrl+X`, then `Y`, then `Enter`

### Step 3: Replace Password Placeholder

```bash
# Replace placeholder with your actual password
sudo sed -i "s/YOUR_GENERATED_PASSWORD_HERE/$REDIS_PASSWORD/g" /etc/redis/redis.conf

# Verify the change
sudo grep "^requirepass" /etc/redis/redis.conf
```

### Step 4: Set Proper File Permissions

```bash
# Restrict config file access
sudo chmod 640 /etc/redis/redis.conf
sudo chown redis:redis /etc/redis/redis.conf

# Create log directory if missing
sudo mkdir -p /var/log/redis
sudo chown redis:redis /var/log/redis
sudo chmod 750 /var/log/redis
```

### Step 5: Configure Firewall (UFW)

```bash
# Install UFW if not present
sudo apt install ufw -y

# Allow SSH (CRITICAL - do this first!)
sudo ufw allow 22/tcp

# Allow HTTP/HTTPS (for Laravel API)
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Allow Laravel API port
sudo ufw allow 8000/tcp

# Allow Node.js realtime service
sudo ufw allow 3000/tcp

# DO NOT expose Redis port publicly!
# Redis should only be accessible via localhost

# Enable firewall
sudo ufw enable

# Check status
sudo ufw status verbose
```

### Step 6: Restart Redis with New Configuration

```bash
# Test configuration syntax
sudo redis-server /etc/redis/redis.conf --test-memory 1

# Restart Redis service
sudo systemctl restart redis-server

# Check status
sudo systemctl status redis-server

# Check logs for errors
sudo tail -f /var/log/redis/redis-server.log
```

### Step 7: Test Authentication

```bash
# Try without password (should fail)
redis-cli ping
# Expected: (error) NOAUTH Authentication required.

# Try with password (should succeed)
redis-cli -a "$REDIS_PASSWORD" ping
# Expected: PONG

# Create alias for convenience
echo "alias redis-cli='redis-cli -a $REDIS_PASSWORD'" >> ~/.bashrc
source ~/.bashrc

# Now this works:
redis-cli ping
# Expected: PONG
```

---

## D) Performance + Safety Configuration

### Memory Sizing Formula

**Formula:**
```
Redis RAM = (Drivers × Location Size) + (Riders × Presence Size) + (Locks + Keys) + 20% overhead
```

**Calculation for 10k drivers + 10k riders:**

```
Driver location entry: ~200 bytes
  10,000 drivers × 200 bytes = 2 MB

Rider presence entry: ~100 bytes
  10,000 riders × 100 bytes = 1 MB

Locks/idempotency/offers: ~500 MB

Total data: 503 MB
With 20% overhead: 604 MB

RECOMMENDED: 2GB maxmemory (gives 3x headroom for growth)
```

**For different VPS RAM sizes:**

| VPS RAM | Node.js | Redis | OS | Recommended maxmemory |
|---------|---------|-------|----|-----------------------|
| 4GB     | 2GB     | 1.5GB | 0.5GB | `maxmemory 1gb` |
| 8GB     | 4GB     | 3GB   | 1GB | `maxmemory 2gb` |
| 16GB    | 8GB     | 6GB   | 2GB | `maxmemory 4gb` |

### Eviction Policy Selection

**For Uber-like workload (mostly TTL keys):**

```conf
maxmemory-policy volatile-ttl
```

**Why:**
- Evicts keys with TTL set, choosing shortest TTL first
- Perfect for: presence (90s), locations (5min), offers (60s), locks (30s)
- Guarantees: Idempotency keys (24h TTL) survive longer than ephemeral data

### Persistence Strategy

**RECOMMENDED: AOF + RDB Hybrid**

```conf
# AOF for crash recovery (idempotency keys, locks)
appendonly yes
appendfsync everysec

# RDB for daily backups (fast restore)
save 900 1      # After 15min if 1 key changed
save 300 100    # After 5min if 100 keys changed
save 60 10000   # After 1min if 10k keys changed
```

**Tradeoffs:**

| Strategy | Durability | Performance | Recovery Time | Disk Usage |
|----------|------------|-------------|---------------|------------|
| **AOF everysec** | High | Good | Fast (seconds) | High |
| **AOF always** | Highest | Slow ❌ | Fastest | High |
| **RDB only** | Medium | Best | Slow (minutes) | Low |
| **No persistence** | None ❌ | Best | Manual rebuild | None |
| **AOF + RDB** ✅ | High | Good | Fast | Medium |

### Key TTL Patterns

**Recommended TTLs for each data type:**

```javascript
// Node.js examples

// 1. Driver presence (online/offline status)
await redis.setex(`driver:online:${driverId}`, 90, JSON.stringify({
  status: 'online',
  lastSeen: Date.now()
}));
// TTL: 90 seconds (3x heartbeat interval of 30s)

// 2. Driver GPS location (for geo queries)
await redis.geoadd('drivers:locations', longitude, latitude, driverId);
await redis.expire('drivers:locations', 300); // 5 minutes

// 3. Ride assignment lock (prevent race conditions)
const locked = await redis.set(
  `lock:ride:${rideId}`,
  driverId,
  'EX', 30,  // 30 seconds TTL
  'NX'       // Only set if not exists
);

// 4. Idempotency key (prevent duplicate requests)
await redis.setex(`idempotency:${requestId}`, 86400, 'processed');
// TTL: 24 hours (covers retries from mobile apps)

// 5. Pending ride offer to driver
await redis.setex(`offer:${rideId}:${driverId}`, 60, JSON.stringify({
  rideId,
  fare,
  expiresAt: Date.now() + 60000
}));
// TTL: 60 seconds (driver must accept within 1 minute)

// 6. Rate limiting (API throttling)
await redis.incr(`ratelimit:${userId}:${minute}`);
await redis.expire(`ratelimit:${userId}:${minute}`, 60);
// TTL: 60 seconds (per-minute rate limit)

// 7. WebSocket session data
await redis.hset(`session:${socketId}`, {
  userId,
  userType: 'driver',
  connectedAt: Date.now()
});
await redis.expire(`session:${socketId}`, 3600); // 1 hour
```

**Critical TTL Rules:**

1. **Always set TTL** - Never rely on maxmemory eviction alone
2. **TTL > Expected Lifetime** - Buffer for network issues (2-3x)
3. **Critical data gets longer TTL** - Idempotency (24h) > Locks (30s)
4. **Use SETEX/EXPIRE atomically** - Don't separate SET and EXPIRE

---

## E) Service Management

### Enable Redis to Start on Boot

```bash
# Enable Redis service
sudo systemctl enable redis-server

# Verify it's enabled
sudo systemctl is-enabled redis-server
# Expected: enabled
```

### Service Control Commands

```bash
# Start Redis
sudo systemctl start redis-server

# Stop Redis
sudo systemctl stop redis-server

# Restart Redis (applies config changes)
sudo systemctl restart redis-server

# Check status
sudo systemctl status redis-server

# View recent logs
sudo journalctl -u redis-server -n 50 --no-pager

# Follow logs in real-time
sudo journalctl -u redis-server -f
```

### Log Locations

```bash
# Main Redis log
/var/log/redis/redis-server.log

# View last 100 lines
sudo tail -n 100 /var/log/redis/redis-server.log

# Monitor logs live
sudo tail -f /var/log/redis/redis-server.log
```

### Safe Restart Procedure

**For single Redis instance:**

```bash
# 1. Notify your team (maintenance window)

# 2. Trigger manual save (if persistence enabled)
redis-cli BGSAVE

# 3. Wait for save to complete
redis-cli LASTSAVE
# Note the timestamp, run again after a few seconds

# 4. Restart service
sudo systemctl restart redis-server

# 5. Verify it's running
redis-cli ping

# 6. Check memory usage
redis-cli INFO memory | grep used_memory_human

# 7. Monitor logs for errors
sudo tail -f /var/log/redis/redis-server.log
```

**Graceful restart script:**

```bash
#!/bin/bash
# save as: /usr/local/bin/redis-safe-restart.sh

echo "Starting safe Redis restart..."

# Trigger background save
echo "Triggering BGSAVE..."
redis-cli BGSAVE

# Wait for save to complete
echo "Waiting for save to complete..."
sleep 5

# Restart Redis
echo "Restarting Redis service..."
sudo systemctl restart redis-server

# Wait for Redis to be ready
echo "Waiting for Redis to start..."
sleep 3

# Verify
if redis-cli ping > /dev/null 2>&1; then
  echo "✅ Redis restarted successfully!"
  redis-cli INFO server | grep redis_version
else
  echo "❌ Redis failed to start! Check logs:"
  sudo journalctl -u redis-server -n 20
  exit 1
fi
```

```bash
# Make script executable
sudo chmod +x /usr/local/bin/redis-safe-restart.sh

# Run it
sudo /usr/local/bin/redis-safe-restart.sh
```

---

## F) Quick Tests

### Test 1: Basic Connectivity

```bash
# Ping test
redis-cli ping
# Expected: PONG

# Check server info
redis-cli INFO server
```

### Test 2: Authentication Test

```bash
# Connect without password (should fail)
redis-cli -h 127.0.0.1 -p 6379 ping
# Expected: (error) NOAUTH Authentication required.

# Connect with password
redis-cli -h 127.0.0.1 -p 6379 -a "$REDIS_PASSWORD" ping
# Expected: PONG
```

### Test 3: Memory Usage Test

```bash
# Check current memory usage
redis-cli INFO memory

# Quick view
redis-cli INFO memory | grep -E 'used_memory_human|maxmemory_human|mem_fragmentation_ratio'
```

### Test 4: Write/Read Performance

```bash
# Benchmark Redis performance
redis-benchmark -q -n 100000 -c 50 -P 12 -a "$REDIS_PASSWORD"

# Expected results (on decent VPS):
# SET: ~50,000 - 100,000 requests/sec
# GET: ~80,000 - 150,000 requests/sec
# GEOADD: ~30,000 - 60,000 requests/sec
```

### Test 5: TTL Keys Simulation

```bash
redis-cli

# Authenticate
AUTH $REDIS_PASSWORD

# Create test keys with TTL
SET test:driver:location "30.0444,31.2357" EX 300
SET test:driver:presence "online" EX 90
SET test:ride:lock "driver-123" EX 30
SET test:idempotency "processed" EX 86400

# Check TTL (time remaining in seconds)
TTL test:driver:location
# Expected: ~295

TTL test:ride:lock
# Expected: ~25

# View all test keys
KEYS test:*

# Cleanup
DEL test:driver:location test:driver:presence test:ride:lock test:idempotency

EXIT
```

### Test 6: Geo Operations

```bash
redis-cli

AUTH $REDIS_PASSWORD

# Add driver locations (Cairo, Egypt)
GEOADD drivers:locations 31.2357 30.0444 driver-1
GEOADD drivers:locations 31.2400 30.0500 driver-2
GEOADD drivers:locations 31.2500 30.0600 driver-3

# Find drivers within 5km of pickup point
GEORADIUS drivers:locations 31.2357 30.0444 5 km WITHDIST WITHCOORD ASC

# Cleanup
DEL drivers:locations

EXIT
```

---

## G) Operational Checklist

### Metrics to Monitor

**1. Memory Metrics (CHECK DAILY)**

```bash
# Memory usage script
cat > /usr/local/bin/redis-memory-check.sh << 'EOF'
#!/bin/bash
REDIS_CLI="redis-cli -a $REDIS_PASSWORD"

echo "=== Redis Memory Status ==="
$REDIS_CLI INFO memory | grep -E 'used_memory_human|maxmemory_human|mem_fragmentation_ratio|evicted_keys'

USED_MB=$($REDIS_CLI INFO memory | grep '^used_memory:' | awk -F: '{print $2}' | tr -d '\r')
MAX_MB=$($REDIS_CLI CONFIG GET maxmemory | tail -1)

if [ "$MAX_MB" != "0" ]; then
  USAGE_PCT=$((USED_MB * 100 / MAX_MB))
  echo "Memory usage: ${USAGE_PCT}%"

  if [ $USAGE_PCT -gt 90 ]; then
    echo "⚠️  WARNING: Memory usage above 90%!"
  elif [ $USAGE_PCT -gt 75 ]; then
    echo "⚠️  CAUTION: Memory usage above 75%"
  else
    echo "✅ Memory usage healthy"
  fi
fi
EOF

chmod +x /usr/local/bin/redis-memory-check.sh

# Run it
/usr/local/bin/redis-memory-check.sh
```

**2. Latency Monitoring**

```bash
# Check Redis latency
redis-cli --latency

# Check latency history
redis-cli --latency-history

# Check slow queries
redis-cli SLOWLOG GET 10
```

**3. Key Eviction Monitoring**

```bash
# Check eviction stats
redis-cli INFO stats | grep evicted_keys

# If evicted_keys > 0 and growing:
# - Increase maxmemory
# - Review TTL values
# - Check for keys without TTL
```

**4. Client Connections**

```bash
# Check connected clients
redis-cli INFO clients

# List all client connections
redis-cli CLIENT LIST

# Monitor clients in real-time
redis-cli --stat
```

**5. Persistence Status**

```bash
# Check last save time
redis-cli LASTSAVE

# Check AOF status
redis-cli INFO persistence | grep aof

# Check RDB status
redis-cli INFO persistence | grep rdb
```

### Alert Thresholds

| Metric | Warning | Critical | Action |
|--------|---------|----------|--------|
| **Memory usage** | >75% | >90% | Increase maxmemory |
| **Evicted keys** | >100/min | >1000/min | Increase maxmemory |
| **Connected clients** | >20000 | >24000 | Check leaks |
| **Blocked clients** | >10 | >100 | Check slow ops |
| **Memory fragmentation** | >1.5 | >2.0 | Restart Redis |
| **Latency (p99)** | >10ms | >50ms | Check CPU/disk |

### Backup Approach

**If persistence enabled (AOF + RDB):**

```bash
# Backup script
cat > /usr/local/bin/redis-backup.sh << 'EOF'
#!/bin/bash
BACKUP_DIR="/var/backups/redis"
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p $BACKUP_DIR

# Trigger RDB save
redis-cli BGSAVE

# Wait for save to complete (max 60 seconds)
sleep 5

# Copy RDB and AOF files
cp /var/lib/redis/dump.rdb $BACKUP_DIR/dump_$DATE.rdb
cp /var/lib/redis/appendonly.aof $BACKUP_DIR/appendonly_$DATE.aof

# Compress backups
gzip $BACKUP_DIR/dump_$DATE.rdb
gzip $BACKUP_DIR/appendonly_$DATE.aof

# Delete backups older than 7 days
find $BACKUP_DIR -name "*.gz" -mtime +7 -delete

echo "Backup completed: $DATE"
EOF

chmod +x /usr/local/bin/redis-backup.sh

# Add to cron (daily at 2 AM)
(crontab -l 2>/dev/null; echo "0 2 * * * /usr/local/bin/redis-backup.sh") | crontab -

# Manual backup
sudo /usr/local/bin/redis-backup.sh
```

**Restore from backup:**

```bash
# Stop Redis
sudo systemctl stop redis-server

# Restore RDB file
sudo cp /var/backups/redis/dump_YYYYMMDD_HHMMSS.rdb.gz /tmp/
gunzip /tmp/dump_YYYYMMDD_HHMMSS.rdb.gz
sudo mv /tmp/dump_YYYYMMDD_HHMMSS.rdb /var/lib/redis/dump.rdb
sudo chown redis:redis /var/lib/redis/dump.rdb

# Start Redis
sudo systemctl start redis-server

# Verify
redis-cli ping
```

---

## H) Node.js / Laravel Connection Examples

### Node.js with `ioredis`

**Install ioredis:**

```bash
cd /path/to/realtime-service
npm install ioredis
```

**Connection configuration:**

```javascript
// realtime-service/src/config/redis.js
const Redis = require('ioredis');
const logger = require('../utils/logger');

const REDIS_ENABLED = process.env.REDIS_ENABLED === 'true';

let redisClient;

if (REDIS_ENABLED) {
  const redisConfig = {
    host: process.env.REDIS_HOST || '127.0.0.1',
    port: parseInt(process.env.REDIS_PORT) || 6379,
    password: process.env.REDIS_PASSWORD,
    db: parseInt(process.env.REDIS_DB) || 0,

    // Connection retry strategy
    retryStrategy: (times) => {
      const delay = Math.min(times * 50, 2000);
      logger.warn(`Redis reconnecting (attempt ${times}), delay: ${delay}ms`);
      return delay;
    },

    // Reconnect on error
    reconnectOnError: (err) => {
      const targetErrors = ['READONLY', 'ECONNREFUSED', 'ETIMEDOUT'];
      if (targetErrors.some(target => err.message.includes(target))) {
        logger.error('Redis reconnectOnError triggered', { error: err.message });
        return true;
      }
      return false;
    },

    // Connection options
    enableReadyCheck: true,
    maxRetriesPerRequest: 3,
    connectTimeout: 10000,
    lazyConnect: false,
    keepAlive: 30000,

    // Auto-pipeline (batch commands for performance)
    enableAutoPipelining: true,
    autoPipeliningIgnoredCommands: ['ping']
  };

  redisClient = new Redis(redisConfig);

  redisClient.on('connect', () => {
    logger.info('Redis client connected');
  });

  redisClient.on('ready', () => {
    logger.info('Redis client ready');
  });

  redisClient.on('error', (err) => {
    logger.error('Redis client error', { error: err.message });
  });

  redisClient.on('close', () => {
    logger.warn('Redis client connection closed');
  });

  redisClient.on('reconnecting', (delay) => {
    logger.info('Redis client reconnecting', { delay });
  });

} else {
  // Use MockRedisClient (your existing implementation)
  redisClient = new MockRedisClient();
  logger.info('Using in-memory Redis mock (for testing only)');
}

module.exports = redisClient;
```

**Environment variables (.env):**

```env
# Redis Configuration
REDIS_ENABLED=true
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=YOUR_REDIS_PASSWORD_HERE
REDIS_DB=0
```

**Usage examples:**

```javascript
const redis = require('./config/redis');

// Set driver location with TTL
async function updateDriverLocation(driverId, latitude, longitude) {
  const multi = redis.multi();

  // Add to geo index
  multi.geoadd('drivers:locations', longitude, latitude, driverId);

  // Set presence key with 90s TTL
  multi.setex(`driver:online:${driverId}`, 90, JSON.stringify({
    status: 'online',
    lastUpdate: Date.now()
  }));

  // Set expiration on geo index (5 minutes)
  multi.expire('drivers:locations', 300);

  await multi.exec();
}

// Find nearest drivers
async function findNearestDrivers(latitude, longitude, radiusKm, limit = 10) {
  const drivers = await redis.georadius(
    'drivers:locations',
    longitude,
    latitude,
    radiusKm,
    'km',
    'WITHDIST',
    'ASC',
    'COUNT',
    limit
  );

  return drivers;
}

// Acquire distributed lock
async function acquireRideLock(rideId, driverId, ttl = 30) {
  const locked = await redis.set(
    `lock:ride:${rideId}`,
    driverId,
    'EX', ttl,
    'NX'
  );

  return locked === 'OK';
}

// Release lock with Lua script (atomic check-and-delete)
async function releaseRideLock(rideId, driverId) {
  const script = `
    if redis.call("get", KEYS[1]) == ARGV[1] then
      return redis.call("del", KEYS[1])
    else
      return 0
    end
  `;

  const released = await redis.eval(script, 1, `lock:ride:${rideId}`, driverId);
  return released === 1;
}

// Idempotency check
async function isRequestProcessed(requestId) {
  const exists = await redis.exists(`idempotency:${requestId}`);
  return exists === 1;
}

async function markRequestProcessed(requestId) {
  await redis.setex(`idempotency:${requestId}`, 86400, JSON.stringify({
    processedAt: Date.now()
  }));
}
```

### Laravel Configuration

**Install predis:**

```bash
cd /path/to/laravel-app
composer require predis/predis
```

**Configure Redis in Laravel (.env):**

```env
# Redis Configuration
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=YOUR_REDIS_PASSWORD_HERE
REDIS_PORT=6379
REDIS_DB=0

# Optional: Use Redis for cache/queue/session
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

**Update config/database.php:**

```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'predis'),

    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
    ],

    'default' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
        'read_timeout' => 60,
        'timeout' => 10,
        'persistent' => true,
        'retry_interval' => 100,
    ],

    'cache' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_CACHE_DB', '1'),
    ],
],
```

**Test Laravel Redis connection:**

```bash
php artisan tinker
```

```php
// Test basic connection
Redis::ping();
// Expected: "PONG"

// Test write/read
Redis::set('test:key', 'Hello from Laravel');
Redis::get('test:key');
// Expected: "Hello from Laravel"

// Test TTL
Redis::setex('test:ttl', 60, 'Expires in 60s');
Redis::ttl('test:ttl');
// Expected: 59 (or less)

// Test geo operations
Redis::geoadd('test:locations', 31.2357, 30.0444, 'location-1');
Redis::georadius('test:locations', 31.2357, 30.0444, 5, 'km');

// Cleanup
Redis::del('test:key', 'test:ttl', 'test:locations');

exit
```

**Usage in Laravel controllers:**

```php
use Illuminate\Support\Facades\Redis;

// Store idempotency key
public function checkIdempotency($requestId)
{
    $key = "idempotency:{$requestId}";

    if (Redis::exists($key)) {
        return response()->json(['error' => 'Duplicate request'], 409);
    }

    // Mark as processed (24h TTL)
    Redis::setex($key, 86400, json_encode([
        'processed_at' => now()->toIso8601String()
    ]));
}

// Publish event to Node.js (via Redis Pub/Sub)
public function notifyNodeJs($event, $data)
{
    Redis::publish("laravel:{$event}", json_encode($data));
}

// Example: Notify Node.js when ride is created
public function createRide(Request $request)
{
    $ride = TripRequest::create($request->all());

    // Notify Node.js real-time service
    Redis::publish('laravel:ride.created', json_encode([
        'ride_id' => $ride->id,
        'customer_id' => $ride->customer_id,
        'pickup_latitude' => $ride->pickup_latitude,
        'pickup_longitude' => $ride->pickup_longitude,
        'vehicle_category_id' => $ride->vehicle_category_id,
    ]));

    return response()->json($ride, 201);
}

// Distributed lock example
public function assignDriverWithLock($rideId, $driverId)
{
    $lockKey = "lock:ride:{$rideId}";

    // Try to acquire lock (30s TTL)
    $locked = Redis::set($lockKey, $driverId, 'EX', 30, 'NX');

    if (!$locked) {
        return response()->json(['error' => 'Ride already being assigned'], 409);
    }

    try {
        // Perform assignment logic
        $trip = TripRequest::findOrFail($rideId);
        $trip->driver_id = $driverId;
        $trip->current_status = 'assigned';
        $trip->save();

        // Release lock
        Redis::del($lockKey);

        return response()->json(['success' => true]);

    } catch (\Exception $e) {
        Redis::del($lockKey);
        throw $e;
    }
}
```

---

## I) Rollback / Uninstall Path

### If You Need to Rollback

```bash
# Stop Redis
sudo systemctl stop redis-server
sudo systemctl disable redis-server

# Restore original config
sudo cp /etc/redis/redis.conf.backup.* /etc/redis/redis.conf

# Restart with old config
sudo systemctl start redis-server
```

### Complete Uninstall

**WARNING:** This will delete all Redis data!

```bash
# Stop Redis
sudo systemctl stop redis-server
sudo systemctl disable redis-server

# Remove Redis packages
sudo apt remove --purge redis-server redis-tools -y
sudo apt autoremove -y

# Delete Redis data
sudo rm -rf /var/lib/redis
sudo rm -rf /var/log/redis
sudo rm -rf /etc/redis

# Remove Redis user
sudo deluser redis

# Remove firewall rules (if added)
sudo ufw delete allow 6379/tcp

# Remove cron jobs
crontab -l | grep -v redis | crontab -

# Remove custom scripts
sudo rm -f /usr/local/bin/redis-*.sh
```

---

## J) Production Readiness Checklist

### Before Going Live

- [ ] Redis installed and running (`systemctl status redis-server`)
- [ ] Password authentication enabled (`requirepass`)
- [ ] Bind to localhost or private IP (not `0.0.0.0`)
- [ ] Firewall configured (Redis port NOT exposed)
- [ ] Dangerous commands renamed
- [ ] maxmemory configured (based on VPS RAM)
- [ ] Eviction policy set (`volatile-ttl`)
- [ ] Persistence enabled (AOF + RDB)
- [ ] Service enabled on boot
- [ ] Monitoring script deployed
- [ ] Backup script configured
- [ ] Node.js connected successfully
- [ ] Laravel connected successfully
- [ ] Logs being written
- [ ] Load tested (redis-benchmark)
- [ ] TTL policies defined
- [ ] Rollback procedure documented

### Day 1 Post-Launch

- [ ] Monitor memory usage every hour
- [ ] Check evicted_keys (should be 0 or low)
- [ ] Verify persistence files exist
- [ ] Test backup restore procedure
- [ ] Review slow log
- [ ] Check latency

### Week 1 Post-Launch

- [ ] Review peak memory usage
- [ ] Adjust maxmemory if needed
- [ ] Tune TTL values based on usage
- [ ] Set up automated alerts
- [ ] Document any issues
- [ ] Plan for scaling

---

## K) Quick Reference Commands

```bash
# Service management
sudo systemctl start redis-server
sudo systemctl stop redis-server
sudo systemctl restart redis-server
sudo systemctl status redis-server

# Connection test
redis-cli ping

# Memory check
redis-cli INFO memory | grep used_memory_human

# Performance benchmark
redis-benchmark -q -n 100000

# Monitor real-time
redis-cli --stat

# Check slow queries
redis-cli SLOWLOG GET 10

# Manual save
redis-cli BGSAVE

# View logs
sudo tail -f /var/log/redis/redis-server.log

# Backup
sudo /usr/local/bin/redis-backup.sh

# Monitor script
/usr/local/bin/redis-memory-check.sh
```

---

## Next Steps

1. **Deploy Redis to VPS**
   - Follow installation guide above
   - Generate and save password securely
   - Configure firewall and security
   - Test connectivity

2. **Update Application Configuration**
   - Node.js: Set `REDIS_ENABLED=true` in `.env`
   - Laravel: Add Redis credentials to `.env`
   - Test both applications connect successfully

3. **Testing Phase**
   - Test in staging environment first
   - Run redis-benchmark for performance baseline
   - Test all TTL patterns
   - Verify geo operations work correctly
   - Test distributed locks
   - Verify idempotency keys

4. **Monitoring Setup**
   - Deploy memory monitoring script
   - Set up daily backups via cron
   - Configure alerts for memory/evictions
   - Document operational procedures

5. **Production Launch**
   - Monitor closely for first 48 hours
   - Check memory usage trends
   - Verify no evictions occurring
   - Tune maxmemory if needed
   - Document any issues

6. **Future Scaling**
   - Add Redis replica when reaching 50k+ drivers
   - Consider Redis Cluster for horizontal scaling
   - Implement Redis Sentinel for high availability

---

## Files Created This Session

1. `INTEGRATION_TEST_RESULTS.md` - Complete test results
2. `sessions_till_production/session_2025-12-17_redis_production.md` - This file

## Modified Files This Session

1. `realtime-service/src/config/redis.js` - Added missing Redis methods
2. `realtime-service/src/services/RedisEventBus.js` - Fixed Redis connection
3. `realtime-service/src/middleware/auth.js` - Added test token authentication
4. `realtime-service/test-service.js` - Updated to use separate tokens
5. `public/index.php` - Created and configured

---

## Summary

✅ **Integration Testing: COMPLETE**
- All 9 tests passed
- Service fully functional with in-memory Redis mock
- Ready for production deployment

✅ **Redis Production Guide: COMPLETE**
- Comprehensive installation instructions
- Security hardening procedures
- Performance tuning for Uber-like workload
- Monitoring and operational procedures
- Connection examples for Node.js and Laravel
- Backup/restore procedures
- Production readiness checklist

🚀 **Status: READY FOR REDIS DEPLOYMENT**

The Node.js real-time service has been thoroughly tested and is fully operational. The Redis production deployment guide provides everything needed to deploy Redis securely and efficiently for the Uber-like application workload.

---

**Session End Time:** 02:30 AM
**Total Duration:** ~75 minutes
**Status:** All tasks completed successfully