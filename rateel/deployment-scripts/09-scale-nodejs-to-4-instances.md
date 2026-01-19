# Scaling Node.js to 4 Instances on VPS2

**Date:** January 19, 2026
**Purpose:** Guide to scale Node.js Realtime Service from 2 to 4 cluster instances

---

## Overview

This guide walks you through scaling the Node.js Realtime Service from 2 to 4 cluster instances to handle increased WebSocket traffic.

### Before and After

| Metric | 2 Instances | 4 Instances |
|--------|-------------|-------------|
| **Total RAM** | 1.2GB | 2.4GB |
| **Concurrent Connections** | 400-600 | 800-1,200 |
| **Per Instance Connections** | 200-300 | 200-300 |
| **CPU Utilization** | ~50% | ~100% (optimal) |
| **VPS2 Free RAM** | 2.8GB | 1.4GB |

---

## Prerequisites

- [ ] VPS2 is running with 16GB RAM
- [ ] Current setup has 2 Node.js instances via PM2
- [ ] WebSocket connections consistently >500 or growing rapidly
- [ ] VPS2 has at least 1.5GB free RAM

---

## Step 1: Check Current Status

### SSH to VPS2

```bash
ssh root@<vps2-ip>
```

### Verify Current Configuration

```bash
# Check PM2 status
pm2 status

# Should show:
# smartline-realtime │ 0 │ cluster │ online │
# smartline-realtime │ 1 │ cluster │ online │
```

### Check Current Resource Usage

```bash
# Memory usage
free -h

# CPU usage
top -bn1 | head -20

# Node.js memory per instance
pm2 monit
# Press Ctrl+C to exit
```

### Check Current Connection Count

```bash
# Via health endpoint
curl -s http://localhost:3002/health | jq

# Or via metrics
curl -s http://localhost:3002/metrics | jq
```

**Decision Point:** If connections are consistently >500, proceed with scaling.

---

## Step 2: Backup Current Configuration

```bash
# Navigate to Node.js directory
cd /var/www/realtime-service

# Backup current PM2 config
cp ecosystem.config.js ecosystem.config.js.backup.$(date +%Y%m%d_%H%M%S)

# Verify backup
ls -la ecosystem.config.js*
```

---

## Step 3: Update PM2 Configuration

### Edit ecosystem.config.js

```bash
nano /var/www/realtime-service/ecosystem.config.js
```

### Update Configuration

**Before (2 instances):**
```javascript
module.exports = {
  apps: [{
    name: 'smartline-realtime',
    script: './src/server.js',
    instances: 2,              // ← Change this
    exec_mode: 'cluster',
    env: {
      NODE_ENV: 'production',
      PORT: 3002
    },
    error_file: './logs/err.log',
    out_file: './logs/out.log',
    log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
    max_memory_restart: '600M',
    watch: false,
    autorestart: true,
    restart_delay: 1000,
    max_restarts: 10
  }]
};
```

**After (4 instances):**
```javascript
module.exports = {
  apps: [{
    name: 'smartline-realtime',
    script: './src/server.js',
    instances: 4,              // ← Changed from 2 to 4
    exec_mode: 'cluster',
    env: {
      NODE_ENV: 'production',
      PORT: 3002
    },
    error_file: './logs/err.log',
    out_file: './logs/out.log',
    log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
    max_memory_restart: '600M',
    watch: false,
    autorestart: true,
    restart_delay: 1000,
    max_restarts: 10
  }]
};
```

Save and exit (Ctrl+X, Y, Enter)

---

## Step 4: Apply the Changes

### Option A: Reload (Zero-Downtime - Recommended)

```bash
cd /var/www/realtime-service

# Reload with new configuration (zero-downtime)
pm2 reload ecosystem.config.js

# Wait 5-10 seconds for new instances to start
sleep 10

# Verify
pm2 status
```

You should see:
```
┌─────┬───────────────────────┬─────────┬─────────┬──────────┐
│ id  │ name                  │ mode    │ status  │ cpu      │
├─────┼───────────────────────┼─────────┼─────────┼──────────┤
│ 0   │ smartline-realtime    │ cluster │ online  │ 15%      │
│ 1   │ smartline-realtime    │ cluster │ online  │ 12%      │
│ 2   │ smartline-realtime    │ cluster │ online  │ 18%      │
│ 3   │ smartline-realtime    │ cluster │ online  │ 14%      │
└─────┴───────────────────────┴─────────┴─────────┴──────────┘
```

### Option B: Restart (With Brief Downtime)

```bash
cd /var/www/realtime-service

# Stop all instances
pm2 stop smartline-realtime

# Start with new configuration
pm2 start ecosystem.config.js

# Verify
pm2 status
```

### Save PM2 Configuration

```bash
# Save PM2 process list for auto-restart
pm2 save

# Verify it persists across reboots
pm2 startup
```

---

## Step 5: Verification

### Check All Instances are Running

```bash
pm2 status
pm2 monit  # Real-time monitoring (Ctrl+C to exit)
```

All 4 instances should show status: `online`

### Check Memory Usage

```bash
# Overall system memory
free -h

# Expected: Used memory increased by ~1.2GB (2 new instances × 600MB)
```

### Check CPU Distribution

```bash
# Watch CPU usage
htop

# Should see Node.js processes distributed across all 4 cores
# Press 'q' to quit
```

### Check Logs for Errors

```bash
# View recent logs
pm2 logs smartline-realtime --lines 50

# Check error logs
tail -50 /var/www/realtime-service/logs/err.log

# No errors should appear
```

### Test Health Endpoint

```bash
# Test health
curl -s http://localhost:3002/health | jq

# Should return healthy status
```

### Test WebSocket Connection

```bash
# From VPS1 or local machine
wscat -c ws://<vps2-public-ip>:3000

# Or if proxied through VPS1
wscat -c wss://smartline-it.com/socket.io/
```

---

## Step 6: Monitor Performance

### Initial Monitoring (First 30 Minutes)

```bash
# Watch PM2 processes
pm2 monit

# Check resource usage
watch -n 5 'free -h && echo "" && pm2 status'

# Monitor logs in real-time
pm2 logs smartline-realtime
```

### Key Metrics to Watch

| Metric | Expected | Alert If |
|--------|----------|----------|
| CPU Usage | 40-80% | >90% |
| RAM per Instance | 200-400MB | >550MB |
| Total RAM Used | ~2.4GB | >3GB |
| Instance Restarts | 0 | >2 per hour |
| Error Log Entries | 0 | Any errors |

### Check Connection Distribution

```bash
# View metrics endpoint
curl -s http://localhost:3002/metrics | jq

# Should show connections distributed across instances
```

---

## Step 7: Update Monitoring Thresholds

Update your monitoring/alerting to reflect the new configuration:

**VPS2 Monitoring Adjustments:**

| Threshold | Before (2 instances) | After (4 instances) |
|-----------|---------------------|---------------------|
| Node.js RAM | 1.2GB baseline | 2.4GB baseline |
| Free RAM Warning | <2GB | <1GB |
| Max Connections | 600 | 1,200 |
| CPU Usage Normal | 30-50% | 50-80% |

---

## Troubleshooting

### Issue: Instances Keep Restarting

**Symptoms:**
```bash
pm2 status
# Shows: ↺ (restarting) status
```

**Solutions:**

1. **Check Memory:**
```bash
# If instances exceed 600MB, they auto-restart
pm2 monit
```

2. **Increase Memory Limit (if needed):**
```bash
nano ecosystem.config.js
# Change: max_memory_restart: '700M'
pm2 reload ecosystem.config.js
```

3. **Check for Memory Leaks:**
```bash
pm2 logs smartline-realtime --err
# Look for "out of memory" errors
```

### Issue: High CPU Usage (>95%)

**Cause:** 4 instances may be too many for the workload

**Solution:**
```bash
# Scale back to 3 instances
nano ecosystem.config.js
# Change: instances: 3
pm2 reload ecosystem.config.js
```

### Issue: Uneven Connection Distribution

**Symptoms:** Some instances have 500 connections, others have 50

**Solution:**
This is normal with Socket.IO clustering. Connections will balance over time as new connections are established.

### Issue: WebSocket Connections Dropping

**Check:**
```bash
# Check for errors
pm2 logs smartline-realtime --err --lines 100

# Check Redis connection
redis-cli -a "$REDIS_PASSWORD" ping

# Check network latency to VPS1
ping -c 10 <vps1-private-ip>
```

---

## Rollback Procedure

If something goes wrong, rollback to 2 instances:

```bash
# Method 1: Restore backup config
cd /var/www/realtime-service
cp ecosystem.config.js.backup.YYYYMMDD_HHMMSS ecosystem.config.js
pm2 reload ecosystem.config.js

# Method 2: Quick edit
nano ecosystem.config.js
# Change: instances: 2
pm2 reload ecosystem.config.js

# Verify
pm2 status
# Should show only instances 0 and 1

# Save
pm2 save
```

---

## Performance Expectations

### Connection Capacity

**Before (2 instances):**
- Peak capacity: 600 concurrent connections
- Comfortable: 400-500 connections

**After (4 instances):**
- Peak capacity: 1,200 concurrent connections
- Comfortable: 800-1,000 connections

### Response Times

- **No change expected** - Each instance handles same load per connection
- Connection establishment: <100ms
- Message latency: <50ms

### Resource Usage

**CPU:**
- Before: 30-50% average
- After: 50-80% average
- Peak: Can reach 90-95% during high load

**RAM:**
- Before: 1.2GB baseline
- After: 2.4GB baseline
- Peak: Can reach 3GB with full load

---

## When to Scale Beyond 4 Instances

### Indicators You Need More Capacity:

1. ✅ **Consistent >1,000 connections** for 24+ hours
2. ✅ **CPU consistently >90%** across all cores
3. ✅ **RAM usage >14GB** on VPS2 (leaves <2GB free)
4. ✅ **Connection latency >100ms** regularly

### Recommended Next Steps:

**Option 1: Scale to 5-6 Instances (Temporary)**
- Only if you need immediate capacity
- Not ideal for long-term (CPU oversubscription)
- Free RAM will be <1GB (risky)

**Option 2: Dedicated VPS3 for Node.js (Recommended)**
- Move all Node.js instances to new VPS3 (8-16GB)
- VPS2 becomes pure data server (MySQL + Redis)
- Better performance and redundancy

---

## Post-Scaling Checklist

After scaling to 4 instances:

- [ ] All 4 instances show `online` status
- [ ] No errors in logs for 30 minutes
- [ ] Memory usage per instance: 200-400MB
- [ ] Total RAM used on VPS2: ~13-14GB
- [ ] Free RAM on VPS2: >1GB
- [ ] CPU usage: 50-80% under normal load
- [ ] WebSocket connections working from mobile app
- [ ] Health endpoint returns healthy
- [ ] PM2 configuration saved
- [ ] Monitoring alerts updated
- [ ] Documentation updated

---

## Monitoring Commands

Keep these handy for ongoing monitoring:

```bash
# Quick status
pm2 status

# Real-time monitoring
pm2 monit

# Check logs
pm2 logs smartline-realtime --lines 50

# System resources
htop

# Memory details
free -h

# Detailed per-process memory
pm2 show smartline-realtime

# Connection metrics
curl -s http://localhost:3002/metrics | jq
```

---

**Document Version:** 1.0
**Last Updated:** January 19, 2026
**Recommended Scaling Path:** 2 → 4 → Dedicated VPS3
