# SmartLine 2-VPS Migration: Capacity Planning and Estimates

**Date:** January 19, 2026
**Document Type:** Capacity Planning and Resource Estimates

---

## 1. Current System Capacity Analysis

### Current VPS Specifications (Single Server)
| Resource | Specification |
|----------|---------------|
| RAM | 16GB |
| CPU | 4 cores |
| Storage | ~100GB SSD (estimated) |
| Network | 1Gbps (typical) |

### Current Resource Utilization (Estimated)

```
┌─────────────────────────────────────────────────────────────┐
│                    CURRENT VPS (16GB RAM)                   │
├─────────────────────────────────────────────────────────────┤
│ ████████████████████████████████████████░░░░░░░░░░ ~75-90% │
│                                                             │
│ MySQL        [████████████████]          4-6 GB   (25-38%)  │
│ Redis        [████████]                  1-2 GB   (6-13%)   │
│ PHP-FPM      [████████████]              2-3 GB   (13-19%)  │
│ Node.js      [████]                      0.5-1 GB (3-6%)    │
│ Queue Workers[████████]                  1.5-2.5 GB (9-16%) │
│ System/OS    [████]                      1 GB     (6%)      │
│                                                             │
│ TOTAL USED:  ~10-15 GB                                      │
│ FREE:        ~1-6 GB (limited headroom!)                    │
└─────────────────────────────────────────────────────────────┘
```

### Current Traffic Estimates
| Metric | Estimate | Notes |
|--------|----------|-------|
| Daily Active Users | 500-2,000 | Riders + Drivers |
| Concurrent Users | 50-200 | Peak hours |
| API Requests/min | 500-2,000 | Including polling |
| WebSocket Connections | 50-300 | Active drivers/riders |
| Database Queries/min | 1,000-5,000 | Read + Write |
| Queue Jobs/hour | 500-2,000 | Notifications, background tasks |

---

## 2. Post-Migration Capacity Estimates

### VPS 1: Application Server (16GB RAM, 4 cores)

```
┌─────────────────────────────────────────────────────────────┐
│                VPS 1 AFTER MIGRATION (16GB RAM)             │
├─────────────────────────────────────────────────────────────┤
│ ████████████████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░ ~35-40%   │
│                                                             │
│ PHP-FPM      [████████████]              2-3 GB   (13-19%)  │
│ Queue Workers[████████]                  1.5-2.5 GB (9-16%) │
│ Nginx        [█]                         200 MB   (1%)      │
│ System/OS    [████]                      1 GB     (6%)      │
│                                                             │
│ TOTAL USED:  ~5-7 GB                                        │
│ FREE:        ~9-11 GB (EXCELLENT headroom!)                 │
└─────────────────────────────────────────────────────────────┘
```

**Capacity Improvements:**
| Metric | Before | After | Improvement |
|--------|--------|-------|-------------|
| Available RAM | 1-6 GB | 9-11 GB | +166% to +450% |
| PHP-FPM Workers | 20-30 | 40-60 | +100% potential |
| Queue Workers | 8 | 8-16 | +100% potential |
| Concurrent Requests | ~200 | ~400+ | +100% potential |

### VPS 2: Data + Realtime Server (8-12GB RAM, 4 cores)

#### Option A: 8GB RAM VPS (Minimum)
```
┌─────────────────────────────────────────────────────────────┐
│                VPS 2 - 8GB RAM (MINIMUM)                    │
├─────────────────────────────────────────────────────────────┤
│ ████████████████████████████████████████████████ ~95-100%  │
│                                                             │
│ MySQL        [████████████████████]      4 GB     (50%)     │
│ Redis        [████████████]              2 GB     (25%)     │
│ Node.js      [████████]                  1 GB     (12.5%)   │
│ System/OS    [████]                      1 GB     (12.5%)   │
│                                                             │
│ TOTAL USED:  ~8 GB                                          │
│ FREE:        ~0 GB (TIGHT - not recommended!)               │
└─────────────────────────────────────────────────────────────┘
```

#### Option B: 12GB RAM VPS (RECOMMENDED)
```
┌─────────────────────────────────────────────────────────────┐
│                VPS 2 - 12GB RAM (RECOMMENDED)               │
├─────────────────────────────────────────────────────────────┤
│ ████████████████████████████████░░░░░░░░░░░░░░░░ ~70-75%   │
│                                                             │
│ MySQL        [████████████████████████]  5 GB     (42%)     │
│ Redis        [████████████]              2 GB     (17%)     │
│ Node.js      [████████]                  1 GB     (8%)      │
│ System/OS    [████]                      1 GB     (8%)      │
│                                                             │
│ TOTAL USED:  ~9 GB                                          │
│ FREE:        ~3 GB (Good headroom)                          │
└─────────────────────────────────────────────────────────────┘
```

#### Option C: 16GB RAM VPS (Best Performance)
```
┌─────────────────────────────────────────────────────────────┐
│                VPS 2 - 16GB RAM (BEST)                      │
├─────────────────────────────────────────────────────────────┤
│ ████████████████████████░░░░░░░░░░░░░░░░░░░░░░░░ ~55-60%   │
│                                                             │
│ MySQL        [████████████████████████████]  6 GB  (38%)    │
│ Redis        [████████████████]              3 GB  (19%)    │
│ Node.js      [████████]                      1 GB  (6%)     │
│ System/OS    [████]                          1 GB  (6%)     │
│                                                             │
│ TOTAL USED:  ~11 GB                                         │
│ FREE:        ~5 GB (Excellent headroom)                     │
└─────────────────────────────────────────────────────────────┘
```

---

## 3. VPS 2 Specification Recommendations

### Minimum Requirements (8GB)
| Resource | Specification | Configuration |
|----------|---------------|---------------|
| RAM | 8GB | `innodb_buffer_pool_size = 3G`, `redis maxmemory = 1.5gb` |
| CPU | 4 cores | Minimum for MySQL + Redis + Node.js |
| Storage | 50GB SSD | Database + logs |
| Network | 1Gbps | Private network required |

**Limitations at 8GB:**
- ⚠️ No room for growth
- ⚠️ MySQL cache will be limited
- ⚠️ May need to upgrade soon
- ⚠️ Risk of OOM during peak load

### Recommended Requirements (12GB) ✅
| Resource | Specification | Configuration |
|----------|---------------|---------------|
| RAM | 12GB | `innodb_buffer_pool_size = 5G`, `redis maxmemory = 2gb` |
| CPU | 4 cores | Good for current + growth |
| Storage | 100GB SSD | Database + logs + backups |
| Network | 1Gbps | Private network required |

**Benefits at 12GB:**
- ✅ MySQL can cache more data (faster queries)
- ✅ Redis has room for growth
- ✅ 25% headroom for traffic spikes
- ✅ Good value for money

### Optimal Requirements (16GB)
| Resource | Specification | Configuration |
|----------|---------------|---------------|
| RAM | 16GB | `innodb_buffer_pool_size = 8G`, `redis maxmemory = 3gb` |
| CPU | 4-8 cores | Future-proof |
| Storage | 200GB SSD | Long-term data growth |
| Network | 1Gbps | Private network required |

**Benefits at 16GB:**
- ✅ Maximum MySQL performance
- ✅ Full dataset can be cached
- ✅ Handles 2-3x traffic growth
- ✅ No upgrades needed for 1-2 years

---

## 4. Service-Specific Capacity Planning

### MySQL Capacity

**Current Database Size Estimate:**
| Table Type | Rows (Est.) | Size (Est.) |
|------------|-------------|-------------|
| Users | 10,000-50,000 | 50-200 MB |
| Drivers | 1,000-5,000 | 10-50 MB |
| Trips | 100,000-500,000 | 500 MB - 2 GB |
| Transactions | 50,000-200,000 | 200-800 MB |
| Logs/Audit | Variable | 1-5 GB |
| **Total** | - | **2-8 GB** |

**Recommended MySQL Configuration:**

| RAM | Buffer Pool | Max Connections | Log File Size |
|-----|-------------|-----------------|---------------|
| 8GB VPS | 3G | 200 | 256M |
| 12GB VPS | 5G | 300 | 512M |
| 16GB VPS | 8G | 500 | 512M |

```ini
# For 12GB VPS (recommended)
[mysqld]
innodb_buffer_pool_size = 5G
innodb_buffer_pool_instances = 4
innodb_log_file_size = 512M
max_connections = 300
thread_cache_size = 50
table_open_cache = 2000
query_cache_type = 0
query_cache_size = 0
```

### Redis Capacity

**Current Redis Usage Estimate:**
| Use Case | Memory (Est.) |
|----------|---------------|
| Sessions | 100-300 MB |
| Cache | 200-500 MB |
| Queue | 50-200 MB |
| Driver Locations (GEO) | 50-100 MB |
| Pub/Sub Buffers | 50-100 MB |
| **Total** | **450 MB - 1.2 GB** |

**Growth Projection:**
| Users | Sessions | Cache | Locations | Total |
|-------|----------|-------|-----------|-------|
| 5,000 | 200 MB | 300 MB | 50 MB | 600 MB |
| 20,000 | 500 MB | 500 MB | 100 MB | 1.2 GB |
| 50,000 | 1 GB | 1 GB | 200 MB | 2.4 GB |

**Recommended Redis Configuration:**

| VPS RAM | Redis maxmemory | Eviction Policy |
|---------|-----------------|-----------------|
| 8GB | 1.5gb | volatile-ttl |
| 12GB | 2gb | volatile-ttl |
| 16GB | 3gb | volatile-ttl |

```conf
# For 12GB VPS (recommended)
maxmemory 2gb
maxmemory-policy volatile-ttl
appendonly yes
appendfsync everysec
tcp-keepalive 300
```

### Node.js Capacity

**Current Resource Usage:**
| Metric | Value |
|--------|-------|
| Base Memory | 100-150 MB |
| Per Connection | ~1-2 MB |
| Peak Memory (100 connections) | 250-350 MB |
| Peak Memory (500 connections) | 600-1,100 MB |

**Recommended PM2 Configuration:**

```javascript
// For 12GB VPS
module.exports = {
  apps: [{
    name: 'smartline-realtime',
    script: './src/server.js',
    instances: 2,              // 2 cluster instances
    exec_mode: 'cluster',
    max_memory_restart: '500M', // Restart if exceeds 500MB per instance
    node_args: '--max-old-space-size=512' // Limit V8 heap
  }]
};
```

---

## 5. Network Capacity Estimates

### Bandwidth Requirements

| Traffic Type | Bandwidth (Est.) | Direction |
|--------------|------------------|-----------|
| API Requests | 10-50 Mbps | Inbound/Outbound |
| WebSocket | 5-20 Mbps | Bidirectional |
| Database Sync | 1-5 Mbps | Internal |
| Redis Sync | 1-5 Mbps | Internal |

**Total External Bandwidth:** 20-100 Mbps peak
**Internal (VPS1 ↔ VPS2):** 10-50 Mbps

### Private Network Latency Impact

| Latency | API Response Impact | User Experience |
|---------|---------------------|-----------------|
| <1ms | +1-2ms | Imperceptible |
| 1-2ms | +3-5ms | Imperceptible |
| 2-5ms | +5-10ms | Minimal |
| 5-10ms | +10-20ms | Noticeable |
| >10ms | +20ms+ | Degraded |

**Target:** <2ms latency on private network

---

## 6. Scaling Projections

### Growth Scenario Analysis

#### Current Load (Baseline)
| Metric | Value |
|--------|-------|
| Daily Users | 1,000 |
| Concurrent Users | 100 |
| API Requests/min | 1,000 |
| WebSocket Connections | 100 |

#### 2x Growth
| Metric | Value | VPS1 Impact | VPS2 Impact |
|--------|-------|-------------|-------------|
| Daily Users | 2,000 | +25% RAM | +30% DB size |
| Concurrent Users | 200 | +30% CPU | +20% Redis |
| API Requests/min | 2,000 | +50% PHP-FPM | +40% MySQL |
| WebSocket Connections | 200 | - | +50% Node.js |

**Can Handle with 12GB VPS2:** ✅ Yes

#### 5x Growth
| Metric | Value | VPS1 Impact | VPS2 Impact |
|--------|-------|-------------|-------------|
| Daily Users | 5,000 | +60% RAM | +100% DB size |
| Concurrent Users | 500 | +100% CPU | +100% Redis |
| API Requests/min | 5,000 | +150% PHP-FPM | +150% MySQL |
| WebSocket Connections | 500 | - | +200% Node.js |

**Can Handle with 12GB VPS2:** ⚠️ Borderline - may need upgrade

#### 10x Growth
| Metric | Value | Required Changes |
|--------|-------|------------------|
| Daily Users | 10,000 | Add read replicas |
| Concurrent Users | 1,000 | Add VPS3 for Node.js |
| API Requests/min | 10,000 | Add load balancer |
| WebSocket Connections | 1,000 | Multiple Node.js instances |

**Requires:** Additional infrastructure (VPS3, load balancer)

---

## 7. Cost-Benefit Analysis

### Current Monthly Cost
| Item | Cost (Est.) |
|------|-------------|
| VPS 1 (16GB, 4 cores) | $40-60/month |
| **Total** | **$40-60/month** |

### Post-Migration Monthly Cost

| VPS2 Spec | VPS1 | VPS2 | Total | Increase |
|-----------|------|------|-------|----------|
| 8GB | $40-60 | $20-30 | $60-90 | +50% |
| 12GB (Recommended) | $40-60 | $30-45 | $70-105 | +75% |
| 16GB | $40-60 | $40-60 | $80-120 | +100% |

### Value Analysis

| Metric | Current | With 12GB VPS2 | Improvement |
|--------|---------|----------------|-------------|
| Available RAM (VPS1) | 1-6 GB | 9-11 GB | +166-450% |
| Database Isolation | ❌ No | ✅ Yes | Security++ |
| Scalability | Limited | Independent scaling | Flexibility++ |
| Backup Isolation | ❌ No | ✅ Yes | Safety++ |
| Potential Capacity | 1x | 2-3x | Growth ready |

**ROI:** +75% cost for +200-300% capacity and improved architecture

---

## 8. Recommended Configuration Summary

### VPS 1: Application Server
| Resource | Allocation |
|----------|------------|
| RAM Total | 16 GB |
| PHP-FPM | 3 GB (40 workers) |
| Queue Workers | 2.5 GB (8 workers) |
| Nginx | 200 MB |
| System/Buffer | 2 GB |
| **Free for Scaling** | **8+ GB** |

### VPS 2: Data + Realtime Server (12GB Recommended)
| Resource | Allocation |
|----------|------------|
| RAM Total | 12 GB |
| MySQL | 5 GB (innodb_buffer_pool) |
| Redis | 2 GB (maxmemory) |
| Node.js | 1 GB (2 instances × 500MB) |
| System/Buffer | 1 GB |
| **Free for Scaling** | **3 GB** |

---

## 9. Monitoring Thresholds

### VPS 1 Alert Thresholds
| Metric | Warning | Critical |
|--------|---------|----------|
| RAM Usage | >70% | >85% |
| CPU Usage | >70% | >90% |
| Disk Usage | >70% | >85% |
| PHP-FPM Queue | >10 | >50 |
| Queue Backlog | >1,000 | >5,000 |

### VPS 2 Alert Thresholds
| Metric | Warning | Critical |
|--------|---------|----------|
| RAM Usage | >75% | >90% |
| CPU Usage | >70% | >90% |
| Disk Usage | >70% | >85% |
| MySQL Connections | >200 | >400 |
| MySQL Slow Queries/min | >10 | >50 |
| Redis Memory | >1.5GB | >1.8GB |
| WebSocket Connections | >400 | >800 |

---

## 10. Capacity Planning Checklist

### Before Migration:
- [ ] Confirm VPS2 has at least 12GB RAM (recommended)
- [ ] Verify 4+ CPU cores on VPS2
- [ ] Ensure 100GB+ SSD storage on VPS2
- [ ] Confirm private network 1Gbps+ speed
- [ ] Test latency between VPS1 and VPS2 (<2ms)

### After Migration:
- [ ] Verify MySQL buffer pool is using allocated memory
- [ ] Confirm Redis maxmemory is set correctly
- [ ] Check Node.js PM2 cluster is running 2 instances
- [ ] Validate VPS1 freed resources as expected
- [ ] Set up monitoring for all thresholds

### Growth Triggers (When to Scale):
- [ ] VPS2 RAM consistently >80% → Upgrade to 16GB
- [ ] MySQL connections >250 → Add connection pooling
- [ ] WebSocket connections >500 → Add Node.js instance
- [ ] API response >200ms → Investigate/optimize
- [ ] Queue backlog >5,000 → Add more workers

---

**Document Version:** 1.0
**Last Updated:** January 19, 2026
**Review Schedule:** Quarterly or at 50% capacity thresholds
