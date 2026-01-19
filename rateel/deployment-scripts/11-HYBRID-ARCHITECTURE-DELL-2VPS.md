# SmartLine Hybrid Architecture: Dell Server + 2 VPS

**Date:** January 19, 2026
**Architecture Type:** Hybrid Cloud (On-Premise + Cloud)

---

## Server Specifications

### Dell PowerEdge R440 (On-Premise/Local)
```
CPU:     Dual Intel Xeon Silver 4110
         - 16 cores, 32 threads total (2x8 cores)
         - 2.10 GHz base clock
         - 22MB cache total

RAM:     64GB DDR4

Storage: 9.6TB SAS (8x 1.2TB in RAID)
         - RAID Controller: DELL PERC H730mini
         - 2.5" SAS drives

Network: 2-Port Ethernet 1Gbps

Power:   Dual 550W redundant PSU
```

### VPS 1 (Cloud - Application Edge)
```
CPU:     4 cores
RAM:     16GB
Storage: 100GB+ SSD
Network: 1Gbps
```

### VPS 2 (Cloud - Realtime + Backup)
```
CPU:     4 cores
RAM:     16GB
Storage: 100GB+ SSD
Network: 1Gbps
```

---

## Recommended Architecture: Hybrid Multi-Tier

```
                         INTERNET
                            |
                     [DNS/Load Balancer]
                            |
              +-------------+-------------+
              |                           |
         [VPS 1 Edge]              [VPS 2 Edge]
        Nginx + Cache              WebSocket + Backup
              |                           |
              +-------------+-------------+
                            |
                    Private Network
                     (VPN/Tunnel)
                            |
                   [DELL R440 Server]
                  Central Backend Hub
                            |
        +-------------------+-------------------+
        |                   |                   |
    Laravel API         MySQL DB            Redis Cache
    Queue Workers      (Primary)          Queue/Sessions
```

---

## Optimal Resource Allocation

### DELL R440 - **Central Backend Hub** (Primary Power)

**Role:** Core application logic, database, heavy processing

| Service | Allocation | Justification |
|---------|------------|---------------|
| **MySQL 8.0** | 32GB RAM (50%) | Primary database with huge buffer pool |
| **Redis** | 8GB RAM | Master cache, queue, sessions |
| **Laravel API** | 8GB RAM | 100+ PHP-FPM workers |
| **Queue Workers** | 4GB RAM | 20+ workers for background jobs |
| **Node.js** | 4GB RAM | 8-16 instances for admin/internal |
| **System/Buffer** | 8GB RAM | OS, monitoring, logs |
| **Total Used** | ~64GB | Full utilization |

**CPU Allocation:**
- MySQL: 8-12 cores
- Laravel/PHP-FPM: 8-12 cores
- Queue Workers: 4-6 cores
- Node.js: 2-4 cores
- **Total:** All 16 cores/32 threads fully utilized

**Storage Strategy:**
```
RAID 10 (Recommended):
- 4.8TB usable (50% of 9.6TB)
- Excellent performance + redundancy
- Database: 2TB
- Backups: 1TB
- Logs/Archives: 1TB
- Free: 800GB

RAID 5 (Alternative):
- 8.4TB usable (87.5% of 9.6TB)
- Good performance, some redundancy
- More storage, less performance
```

---

### VPS 1 - **Edge Application Server** (Public-Facing)

**Role:** Internet-facing API gateway, static assets, load balancer

| Service | RAM | Purpose |
|---------|-----|---------|
| **Nginx** | 2GB | Reverse proxy, SSL termination, caching |
| **Varnish Cache** | 6GB | HTTP cache layer (optional) |
| **Laravel (Light)** | 4GB | Edge API for simple queries |
| **Monitoring** | 1GB | Health checks, metrics |
| **System** | 3GB | OS, buffers |
| **Total** | 16GB | |

**Key Functions:**
- SSL/TLS termination (Let's Encrypt)
- Static file serving (images, CSS, JS)
- API rate limiting
- DDoS protection
- Geographic load balancing
- Proxies heavy requests to Dell server

---

### VPS 2 - **Realtime + Backup Server**

**Role:** WebSocket service, real-time features, disaster recovery

| Service | RAM | Purpose |
|---------|-----|---------|
| **Node.js Realtime** | 4GB | 8 cluster instances, WebSocket |
| **MySQL (Replica)** | 6GB | Read replica, automatic failover |
| **Redis (Replica)** | 2GB | Cache replica, failover |
| **Monitoring** | 1GB | Metrics, alerts |
| **System** | 3GB | OS, buffers |
| **Total** | 16GB | |

**Key Functions:**
- Primary WebSocket server (driver/rider real-time)
- Database read replica (automatic failover to master)
- Redis replica (failover capability)
- Monitoring and alerting
- Backup server (can take over if Dell fails)

---

## Network Architecture

### Internet Traffic Flow

```
1. Client Request (Mobile App/Web)
   ↓
2. DNS → VPS1 Public IP (or Load Balancer)
   ↓
3. VPS1: Nginx SSL Termination
   ↓
   Decision:
   ├─→ Static files? → Serve from VPS1 cache
   ├─→ Simple query? → VPS1 Laravel (local DB replica)
   └─→ Complex query? → Forward to Dell Server
       ↓
4. Dell Server: Process Request
   ├─→ Database query (32GB buffer pool - ultra fast)
   ├─→ Business logic
   └─→ Queue heavy jobs
       ↓
5. Response → VPS1 → Client
```

### WebSocket Traffic Flow

```
1. Client WebSocket Connection
   ↓
2. VPS2:3000 (Direct or via VPS1 proxy)
   ↓
3. Node.js (8 instances, 1,600-2,400 connections)
   ↓
4. Redis Pub/Sub ←→ Dell Server Redis
   ↓
5. Real-time updates to connected clients
```

### Private Network (VPS ↔ Dell)

**Options:**

**Option A: VPN Tunnel (Recommended)**
```
VPS1/VPS2 ←→ WireGuard VPN ←→ Dell Server
- Encrypted connection
- Low latency (<10ms if same region)
- Secure MySQL/Redis access
```

**Option B: SSH Tunnel**
```
VPS ←→ SSH Tunnel ←→ Dell Server
- Simple setup
- Good for initial testing
- Higher latency
```

**Option C: Private VLAN (If VPS provider supports)**
```
VPS ←→ Provider Private Network ←→ Dell Server (if co-located)
- Best performance (<1ms)
- Most secure
- Requires co-location
```

---

## Capacity Planning

### Current vs Hybrid Architecture

| Metric | Current (2 VPS) | With Dell R440 | Improvement |
|--------|-----------------|----------------|-------------|
| **Total RAM** | 32GB | 96GB | +200% |
| **Total CPU Cores** | 8 | 24 | +200% |
| **Database Buffer Pool** | 8GB | 32GB | +300% |
| **Concurrent Connections** | 1,200 | 10,000+ | +733% |
| **Queue Workers** | 8 | 28+ | +250% |
| **Storage** | ~200GB | 4.8TB+ | +2,300% |
| **WebSocket Capacity** | 1,200 | 2,400 | +100% |

### Traffic Capacity Estimates

| Load Scenario | Users/Day | Concurrent | Can Handle? |
|---------------|-----------|------------|-------------|
| **Current** | 1,000 | 100 | ✅ Easy |
| **5x Growth** | 5,000 | 500 | ✅ Easy |
| **10x Growth** | 10,000 | 1,000 | ✅ Comfortable |
| **50x Growth** | 50,000 | 5,000 | ✅ Yes |
| **100x Growth** | 100,000 | 10,000 | ✅ Possible (with optimization) |

---

## Cost Analysis

### Monthly Costs

| Server | Cost | Purpose |
|--------|------|---------|
| VPS 1 (16GB) | $40-60 | Edge/Public facing |
| VPS 2 (16GB) | $40-60 | Realtime + Backup |
| Dell R440 | $50-100* | Central backend |
| **Total** | **$130-220** | |

*Dell costs: Electricity (~$30-50), Internet (~$20-50), Cooling

### Cost Comparison

| Setup | Monthly Cost | Capacity |
|-------|-------------|----------|
| 2 VPS Only | $80-120 | 5x current |
| **Hybrid (2 VPS + Dell)** | **$130-220** | **50-100x current** |
| 3-5 Large VPS | $300-500 | 20-30x current |

**ROI:** +63% cost for +10-20x capacity = Excellent value

---

## Configuration: MySQL Replication

### Dell Server (Master)

```ini
# /etc/mysql/mysql.conf.d/mysqld.cnf
[mysqld]
# Server identification
server-id = 1

# Binary logging for replication
log_bin = /var/log/mysql/mysql-bin
binlog_format = ROW
expire_logs_days = 7
max_binlog_size = 100M

# InnoDB settings (32GB buffer pool)
innodb_buffer_pool_size = 32G
innodb_buffer_pool_instances = 8
innodb_log_file_size = 2G
innodb_flush_log_at_trx_commit = 1

# Connection settings
max_connections = 1000
max_connect_errors = 10000

# Query cache disabled (MySQL 8.0)
# Performance schema
performance_schema = ON

# Listen on all interfaces
bind-address = 0.0.0.0  # Or VPN IP
```

### VPS2 (Read Replica/Slave)

```ini
# /etc/mysql/mysql.conf.d/mysqld.cnf
[mysqld]
server-id = 2
read_only = 1

# Relay log
relay-log = /var/log/mysql/mysql-relay-bin
relay-log-index = /var/log/mysql/mysql-relay-bin.index

# InnoDB settings (6GB buffer pool)
innodb_buffer_pool_size = 6G
innodb_buffer_pool_instances = 2

# Connection settings
max_connections = 500

bind-address = 0.0.0.0
```

**Setup Replication:**

```sql
-- On Dell Server (Master)
CREATE USER 'replicator'@'%' IDENTIFIED BY 'strong_password';
GRANT REPLICATION SLAVE ON *.* TO 'replicator'@'%';
FLUSH PRIVILEGES;
SHOW MASTER STATUS;  -- Note File and Position

-- On VPS2 (Slave)
CHANGE MASTER TO
  MASTER_HOST='<dell-vpn-ip>',
  MASTER_USER='replicator',
  MASTER_PASSWORD='strong_password',
  MASTER_LOG_FILE='mysql-bin.000001',  -- From SHOW MASTER STATUS
  MASTER_LOG_POS=12345;                 -- From SHOW MASTER STATUS

START SLAVE;
SHOW SLAVE STATUS\G  -- Verify replication is working
```

---

## Configuration: Redis Sentinel (High Availability)

### Dell Server (Master)

```conf
# /etc/redis/redis.conf
bind 0.0.0.0
port 6379
requirepass strong_redis_password
maxmemory 8gb
maxmemory-policy volatile-ttl
appendonly yes
```

### VPS2 (Replica + Sentinel)

```conf
# /etc/redis/redis-replica.conf
bind 0.0.0.0
port 6379
slaveof <dell-vpn-ip> 6379
masterauth strong_redis_password
requirepass strong_redis_password
maxmemory 2gb
```

**Redis Sentinel Config:**

```conf
# /etc/redis/sentinel.conf
port 26379
sentinel monitor mymaster <dell-vpn-ip> 6379 2
sentinel auth-pass mymaster strong_redis_password
sentinel down-after-milliseconds mymaster 5000
sentinel failover-timeout mymaster 60000
```

---

## Deployment Strategy

### Phase 1: Setup Dell Server (Week 1)

1. **Install Ubuntu 22.04 LTS Server**
2. **Configure RAID** (RAID 10 recommended)
3. **Install Stack:**
   - MySQL 8.0
   - Redis 7.x
   - PHP 8.2 + Nginx
   - Node.js 20.x
   - Monitoring (Prometheus, Grafana)

### Phase 2: Migrate Database (Week 2)

1. Export from current VPS
2. Import to Dell server
3. Configure replication to VPS2
4. Test failover

### Phase 3: Setup VPN/Private Network (Week 2)

1. Install WireGuard on all servers
2. Configure secure tunnels
3. Test latency and throughput

### Phase 4: Migrate Application (Week 3)

1. Deploy Laravel to Dell
2. Configure VPS1 as reverse proxy
3. Test API requests
4. Gradual traffic migration

### Phase 5: Optimize and Monitor (Week 4)

1. Performance tuning
2. Set up monitoring and alerts
3. Load testing
4. Documentation

---

## Monitoring Strategy

### Dell Server Monitoring

**Key Metrics:**
- CPU usage per core (32 threads)
- RAM usage (64GB total)
- MySQL: Buffer pool hit ratio, slow queries
- Disk I/O (RAID performance)
- Network throughput
- Temperature (hardware sensors)

**Tools:**
- Prometheus + Grafana
- MySQL monitoring (PMM - Percona Monitoring)
- Node Exporter for system metrics
- SMART monitoring for drives

### VPS Monitoring

**Key Metrics:**
- Response time from edge servers
- Cache hit ratio (Varnish/Nginx)
- WebSocket connections (VPS2)
- Replication lag (MySQL/Redis)

---

## Disaster Recovery

### Scenario 1: Dell Server Failure

**Automatic Failover:**
1. MySQL: VPS2 replica promoted to master (Sentinel)
2. Redis: VPS2 replica becomes master
3. Laravel: VPS1 handles all requests (degraded mode)
4. **Downtime:** <5 minutes

**Manual Recovery:**
1. Fix Dell server hardware
2. Restore data from VPS2
3. Re-establish replication
4. Switch back to Dell as master

### Scenario 2: VPS1 Failure

**Impact:** Edge server down, direct access to Dell

**Mitigation:**
1. Update DNS to point to Dell public IP (if available)
2. Or use VPS2 as temporary edge server
3. **Downtime:** DNS propagation time (~5-30 minutes)

### Scenario 3: VPS2 Failure

**Impact:** WebSocket down, no read replica

**Mitigation:**
1. Node.js instances can run on Dell temporarily
2. All traffic goes to Dell MySQL master
3. **Downtime:** <2 minutes for WebSocket

---

## Security Considerations

### Dell Server

- **Firewall:** Only VPN IPs allowed (VPS1, VPS2)
- **No public MySQL/Redis ports**
- **SSH:** Key-only authentication
- **Updates:** Automatic security updates
- **Backups:** Daily full backups to separate NAS/Cloud

### VPS Servers

- **VPS1:** Only ports 80, 443, 22 public
- **VPS2:** Only port 3000 (WebSocket) public
- **UFW/iptables:** Strict rules
- **Fail2ban:** Protection against brute force

### VPN Security

- **WireGuard:** Modern, fast, secure
- **Encryption:** ChaCha20-Poly1305
- **Key rotation:** Every 90 days
- **Monitoring:** Connection logs and alerts

---

## Performance Optimizations

### Dell Server

**MySQL:**
- 32GB InnoDB buffer pool = entire dataset in memory
- SSD RAID 10 = 500k+ IOPS
- Query caching via ProxySQL (if needed)
- Connection pooling

**PHP-FPM:**
- 100+ workers (2GB RAM, ~20MB per worker)
- OPcache with 512MB
- Preloading enabled

**Queue Workers:**
- 20 workers across all queues
- Horizon for monitoring
- Parallel job processing

### VPS1 (Edge)

**Nginx:**
- FastCGI caching (1GB)
- Static file caching (2GB)
- Gzip compression
- HTTP/2 enabled

**Varnish (Optional):**
- 6GB cache for API responses
- TTL: 60-300 seconds
- Cache warming

### VPS2 (Realtime)

**Node.js:**
- 8 cluster instances
- Redis Pub/Sub for inter-process communication
- Sticky sessions for WebSocket

---

## Recommended Next Steps

1. **Order/Setup Dell Server**
   - Install Ubuntu Server 22.04 LTS
   - Configure RAID 10
   - Set up VPN access

2. **Test Private Connectivity**
   - VPN latency tests
   - Bandwidth tests
   - MySQL replication test

3. **Gradual Migration**
   - Start with database migration
   - Then application layer
   - Finally, full traffic cutover

4. **Monitoring Setup**
   - Grafana dashboards
   - Alert rules
   - Performance baselines

---

## Conclusion

The hybrid architecture leverages the Dell R440's massive compute power while maintaining cloud VPS servers for:
- **High availability** (geographic redundancy)
- **Edge performance** (close to users)
- **Disaster recovery** (automatic failover)

**Key Benefits:**
- 💪 **50-100x capacity increase** with Dell server
- 🌍 **Geographic redundancy** with cloud VPS
- 💰 **Cost-effective** ($130-220/mo vs $500+ for equivalent cloud)
- 🔄 **High availability** (automatic failover)
- 🚀 **Room for massive growth** (100k+ users)

---

**Document Version:** 1.0
**Last Updated:** January 19, 2026
**Status:** Architecture Design - Ready for Implementation
