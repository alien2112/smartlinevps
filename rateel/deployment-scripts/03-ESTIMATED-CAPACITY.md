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

### VPS 2: Data + Realtime Server (16GB RAM, 4 cores)

```
┌─────────────────────────────────────────────────────────────┐
│                VPS 2 - 16GB RAM (ACTUAL SPEC)               │
├─────────────────────────────────────────────────────────────┤
│ ████████████████████████░░░░░░░░░░░░░░░░░░░░░░░░ ~70-75%   │
│                                                             │
│ MySQL        [████████████████████████████████]  8 GB (50%) │
│ Redis        [████████████████]                  3 GB (19%) │
│ Node.js      [████████]                          1 GB (6%)  │
│ System/OS    [████]                              1 GB (6%)  │
│                                                             │
│ TOTAL USED:  ~13 GB                                         │
│ FREE:        ~3 GB (Good headroom for growth)               │
└─────────────────────────────────────────────────────────────┘
```

**Configuration (16GB VPS2):**
- MySQL: `innodb_buffer_pool_size = 8G` (50% of RAM - optimal for InnoDB)
- Redis: `maxmemory = 3gb` (room for cache + sessions + queue)
- Node.js: 2 cluster instances @ 500MB each

**Benefits of 16GB Configuration:**
- ✅ **Maximum MySQL Performance** - Full InnoDB buffer pool
- ✅ **Entire Database Can Be Cached** - 8GB buffer pool can hold entire 2-8GB dataset
- ✅ **3GB Redis** - Plenty of room for sessions, cache, and queue data
- ✅ **Handles 3-5x Traffic Growth** - Without needing upgrade
- ✅ **No Upgrades Needed** - For at least 1-2 years
- ✅ **Same Specs as VPS1** - Easier management and cost predictability

---

## 3. VPS 2 Actual Specifications

### Confirmed VPS2 Specifications ✅
| Resource | Specification | Configuration |
|----------|---------------|---------------|
| RAM | 16GB | `innodb_buffer_pool_size = 8G`, `redis maxmemory = 3gb` |
| CPU | 4 cores | Excellent for MySQL + Redis + Node.js |
| Storage | 100GB+ SSD | Database + logs + backups |
| Network | 1Gbps | Private network required |

### Why 16GB is Excellent for VPS2

**MySQL Optimization:**
- 8GB InnoDB buffer pool = 50% of RAM (industry best practice)
- Can cache entire current dataset (2-8GB) in memory
- Handles 3-5x database growth without performance degradation
- Fast query execution with full in-memory operations

**Redis Capacity:**
- 3GB allocation provides ample room for:
  - Sessions (500MB-1GB expected)
  - Application cache (1-1.5GB expected)
  - Queue data (200-500MB expected)
  - Driver location data (100-200MB expected)
- 50%+ headroom for growth

**Node.js Performance:**
- 2 cluster instances for redundancy and load distribution
- Each instance can handle 200-300 concurrent WebSocket connections
- Total capacity: 400-600 concurrent connections

**Overall Benefits:**
- ✅ **Identical to VPS1** - Same specs make management easier
- ✅ **No Bottlenecks** - Each service has optimal resource allocation
- ✅ **Future-Proof** - Can handle 3-5x traffic growth
- ✅ **Cost-Effective** - No need for upgrades for 1-2 years
- ✅ **High Performance** - Full dataset caching in MySQL

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

**Recommended Redis Configuration (16GB VPS2):**

```conf
# For 16GB VPS2 (actual configuration)
maxmemory 3gb
maxmemory-policy volatile-ttl
appendonly yes
appendfsync everysec
tcp-keepalive 300
```

**Why 3GB?**
- Current usage: 450MB - 1.2GB
- Growth to 50K users: 2.4GB
- Headroom: 600MB (20%)
- Allows for traffic spikes without evictions

### Node.js Capacity

**Current Resource Usage:**
| Metric | Value |
|--------|-------|
| Base Memory | 100-150 MB |
| Per Connection | ~1-2 MB |
| Peak Memory (100 connections) | 250-350 MB |
| Peak Memory (500 connections) | 600-1,100 MB |

**Recommended PM2 Configuration (16GB VPS2):**

```javascript
// For 16GB VPS2 (actual configuration)
module.exports = {
  apps: [{
    name: 'smartline-realtime',
    script: './src/server.js',
    instances: 2,              // 2 cluster instances for redundancy
    exec_mode: 'cluster',
    max_memory_restart: '600M', // Restart if exceeds 600MB per instance
    node_args: '--max-old-space-size=600' // Limit V8 heap
  }]
};
```

**Total Node.js Allocation:** 1.2GB (2 instances × 600MB)
- Handles 400-600 concurrent WebSocket connections
- Automatic restart if memory leak detected
- Load balanced across CPU cores

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

**Can Handle with 16GB VPS2:** ✅ Yes, easily

#### 5x Growth
| Metric | Value | VPS1 Impact | VPS2 Impact |
|--------|-------|-------------|-------------|
| Daily Users | 5,000 | +60% RAM | +100% DB size |
| Concurrent Users | 500 | +100% CPU | +100% Redis |
| API Requests/min | 5,000 | +150% PHP-FPM | +150% MySQL |
| WebSocket Connections | 500 | - | +200% Node.js |

**Can Handle with 16GB VPS2:** ✅ Yes, comfortably
- MySQL: 8GB buffer pool can handle up to 15-20GB database
- Redis: 3GB handles up to 50K users
- Node.js: Can scale to 3-4 instances if needed

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
| 16GB (Actual) | $40-60 | $40-60 | $80-120 | +100% |

### Value Analysis

| Metric | Current | With 16GB VPS2 | Improvement |
|--------|---------|----------------|-------------|
| Available RAM (VPS1) | 1-6 GB | 9-11 GB | +166-450% |
| Available RAM (VPS2) | - | 3-5 GB | New headroom |
| Database Isolation | ❌ No | ✅ Yes | Security++ |
| Scalability | Limited | Independent scaling | Flexibility++ |
| Backup Isolation | ❌ No | ✅ Yes | Safety++ |
| Potential Capacity | 1x | 3-5x | Growth ready |
| MySQL Performance | Limited cache | Full dataset cached | Speed++ |

**ROI:** +100% cost for +300-500% capacity and significantly improved architecture

**Key Benefits:**
- Both VPS servers have identical specs (easier management)
- Can handle 5x traffic growth without infrastructure changes
- MySQL performance maximized with 8GB buffer pool
- No need for upgrades for 1-2 years

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

### VPS 2: Data + Realtime Server (16GB Actual)
| Resource | Allocation |
|----------|------------|
| RAM Total | 16 GB |
| MySQL | 8 GB (innodb_buffer_pool) |
| Redis | 3 GB (maxmemory) |
| Node.js | 1.2 GB (2 instances × 600MB) |
| System/Buffer | 1 GB |
| **Free for Scaling** | **~3 GB** |

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

### VPS 2 Alert Thresholds (16GB VPS)
| Metric | Warning | Critical |
|--------|---------|----------|
| RAM Usage | >80% | >90% |
| CPU Usage | >70% | >90% |
| Disk Usage | >70% | >85% |
| MySQL Connections | >300 | >450 |
| MySQL Slow Queries/min | >10 | >50 |
| Redis Memory | >2.4GB | >2.8GB |
| WebSocket Connections | >500 | >900 |

---

## 10. Capacity Planning Checklist

### Before Migration:
- [ ] Confirm VPS2 has 16GB RAM, 4 cores (actual spec)
- [ ] Ensure 100GB+ SSD storage on VPS2
- [ ] Confirm private network configured and 1Gbps+ speed
- [ ] Test latency between VPS1 and VPS2 (<2ms target)
- [ ] Verify both VPS servers are in same datacenter/region

### After Migration:
- [ ] Verify MySQL buffer pool is 8GB (`innodb_buffer_pool_size = 8G`)
- [ ] Confirm Redis maxmemory is 3GB (`maxmemory 3gb`)
- [ ] Check Node.js PM2 running 2 instances with 600MB limit each
- [ ] Validate VPS1 has 9-11GB free RAM
- [ ] Validate VPS2 has 3-5GB free RAM
- [ ] Set up monitoring for all thresholds

### Growth Triggers (When to Scale):
- [ ] VPS2 RAM consistently >85% → Review and optimize before adding hardware
- [ ] MySQL connections >300 → Tune connection pooling or add read replica
- [ ] WebSocket connections >600 → Scale to 3-4 Node.js instances
- [ ] Database size >15GB → Consider adding dedicated MySQL server
- [ ] API response >200ms → Investigate/optimize queries
- [ ] Queue backlog >5,000 → Add more queue workers on VPS1

---

**Document Version:** 1.0
**Last Updated:** January 19, 2026
**Review Schedule:** Quarterly or at 50% capacity thresholds
