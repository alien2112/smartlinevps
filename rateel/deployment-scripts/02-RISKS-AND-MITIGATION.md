# SmartLine 2-VPS Migration: Risks and Mitigation

**Date:** January 19, 2026
**Document Type:** Risk Assessment and Mitigation Plan

---

## Risk Matrix

| Risk Level | Description |
|------------|-------------|
| 🔴 **Critical** | Service outage, data loss, security breach |
| 🟠 **High** | Significant degradation, extended downtime |
| 🟡 **Medium** | Temporary issues, recoverable problems |
| 🟢 **Low** | Minor inconvenience, quick fix |

---

## 1. Data Migration Risks

### Risk 1.1: Database Corruption During Transfer
| Attribute | Value |
|-----------|-------|
| **Level** | 🔴 Critical |
| **Probability** | Low |
| **Impact** | Complete data loss or corruption |

**Causes:**
- Network interruption during transfer
- Disk space issues on target server
- MySQL version incompatibility

**Mitigation:**
1. Use `--single-transaction` flag for consistent backup
2. Verify backup integrity with checksum before transfer
3. Use compressed transfer to reduce transfer time
4. Keep original database on VPS1 until migration verified

**Recovery:**
```bash
# Verify backup integrity
md5sum /tmp/smartline_backup.sql.gz
# Compare with original on VPS1
```

### Risk 1.2: Data Loss During Cutover
| Attribute | Value |
|-----------|-------|
| **Level** | 🔴 Critical |
| **Probability** | Medium |
| **Impact** | Transactions lost during migration window |

**Causes:**
- New data written to VPS1 database after export
- Queue jobs processed during migration
- Real-time events lost during transition

**Mitigation:**
1. Schedule migration during low-traffic period (2-4 AM local time)
2. Enable maintenance mode before final export
3. Stop queue workers during cutover
4. Use binary log position for incremental sync if needed

**Recovery:**
```bash
# Enable maintenance mode
cd /var/www/laravel/smartlinevps/rateel
php artisan down --message="System maintenance in progress" --retry=60
```

### Risk 1.3: Replication Lag / Data Inconsistency
| Attribute | Value |
|-----------|-------|
| **Level** | 🟠 High |
| **Probability** | Low (one-time migration) |
| **Impact** | Stale data served to users |

**Mitigation:**
- This is a one-time migration, not replication setup
- Verify row counts match after import
- Run data integrity checks on critical tables

**Verification Script:**
```sql
-- Run on both servers and compare
SELECT 'users' as tbl, COUNT(*) as cnt FROM users
UNION ALL
SELECT 'trips', COUNT(*) FROM trips
UNION ALL
SELECT 'drivers', COUNT(*) FROM drivers
UNION ALL
SELECT 'transactions', COUNT(*) FROM transactions;
```

---

## 2. Network and Connectivity Risks

### Risk 2.1: Private Network Failure
| Attribute | Value |
|-----------|-------|
| **Level** | 🔴 Critical |
| **Probability** | Low |
| **Impact** | Complete service outage |

**Causes:**
- VPS provider network issues
- Misconfigured firewall rules
- IP address conflicts

**Mitigation:**
1. Test private network connectivity before migration
2. Document all IP addresses and firewall rules
3. Have public IP fallback plan (less secure but functional)
4. Set up monitoring for private network latency

**Testing:**
```bash
# From VPS1, test VPS2 connectivity
ping -c 10 10.132.0.11
traceroute 10.132.0.11

# Test specific ports
nc -zv 10.132.0.11 3306
nc -zv 10.132.0.11 6379
nc -zv 10.132.0.11 3002
```

**Fallback Plan:**
If private network fails, temporarily use public IPs with strict firewall rules:
```bash
# On VPS2 - Allow VPS1 public IP temporarily
ufw allow from <VPS1_PUBLIC_IP> to any port 3306
ufw allow from <VPS1_PUBLIC_IP> to any port 6379
```

### Risk 2.2: Network Latency Impact
| Attribute | Value |
|-----------|-------|
| **Level** | 🟡 Medium |
| **Probability** | Medium |
| **Impact** | Slower API response times |

**Causes:**
- Distance between VPS servers
- Network congestion
- Suboptimal routing

**Expected Latency:**
| Connection | Expected | Acceptable | Critical |
|------------|----------|------------|----------|
| VPS1 → MySQL (VPS2) | <2ms | <5ms | >10ms |
| VPS1 → Redis (VPS2) | <1ms | <3ms | >5ms |
| VPS1 → Node.js (VPS2) | <2ms | <5ms | >10ms |

**Mitigation:**
1. Choose VPS2 in same datacenter/region as VPS1
2. Use connection pooling for MySQL
3. Enable Redis connection persistence
4. Monitor latency continuously after migration

**Monitoring:**
```bash
# Add to cron for continuous monitoring
*/5 * * * * ping -c 3 10.132.0.11 >> /var/log/vps2-latency.log
```

### Risk 2.3: DNS/SSL Issues
| Attribute | Value |
|-----------|-------|
| **Level** | 🟠 High |
| **Probability** | Low |
| **Impact** | WebSocket connections fail |

**Causes:**
- SSL certificate not covering new WebSocket endpoint
- DNS propagation delays if changing endpoints
- Mixed content issues (HTTP/HTTPS)

**Mitigation:**
1. Keep WebSocket on same domain (proxy through VPS1 Nginx)
2. Verify SSL certificate covers all subdomains
3. Test WebSocket over HTTPS before cutover

**Current Setup (Keep):**
```
Client → smartline-it.com:443 → VPS1 Nginx → VPS2:3002
```

---

## 3. Service Availability Risks

### Risk 3.1: Extended Downtime
| Attribute | Value |
|-----------|-------|
| **Level** | 🟠 High |
| **Probability** | Medium |
| **Impact** | Users cannot book rides |

**Causes:**
- Unexpected issues during migration
- Configuration errors
- Service startup failures

**Mitigation:**
1. Prepare detailed runbook with commands
2. Test migration on staging environment first (if available)
3. Have rollback plan ready
4. Allocate 2x estimated time for migration window

**Maximum Acceptable Downtime:**
- **Target:** 30 minutes
- **Maximum:** 2 hours
- **Trigger Rollback If:** >1 hour with no progress

### Risk 3.2: Queue Job Failures
| Attribute | Value |
|-----------|-------|
| **Level** | 🟡 Medium |
| **Probability** | Medium |
| **Impact** | Delayed notifications, failed payments |

**Causes:**
- Redis connection lost during migration
- Jobs in transit during cutover
- Different Redis credentials

**Mitigation:**
1. Drain queues before migration
2. Stop workers, wait for in-progress jobs to complete
3. Clear failed jobs table after migration if needed
4. Monitor queue backlog after restart

**Pre-Migration Queue Drain:**
```bash
cd /var/www/laravel/smartlinevps/rateel

# Check queue sizes
php artisan queue:size

# Process remaining jobs
php artisan queue:work --stop-when-empty

# Stop workers
supervisorctl stop smartline-worker-high:*
supervisorctl stop smartline-worker-default:*
supervisorctl stop smartline-worker-notifications:*
```

### Risk 3.3: WebSocket Connection Drops
| Attribute | Value |
|-----------|-------|
| **Level** | 🟡 Medium |
| **Probability** | High (expected during migration) |
| **Impact** | Temporary loss of real-time updates |

**Causes:**
- Node.js service restart
- Client needs to reconnect to new endpoint
- Redis pub/sub reconnection

**Mitigation:**
1. Implement client-side reconnection logic (should already exist)
2. Send push notification about temporary service interruption
3. Node.js service has graceful shutdown

**Verification:**
```javascript
// Client should automatically reconnect
// Verify reconnection settings in mobile app
socket.io reconnection: true
socket.io reconnectionAttempts: Infinity
socket.io reconnectionDelay: 1000
```

---

## 4. Security Risks

### Risk 4.1: Exposed Database Port
| Attribute | Value |
|-----------|-------|
| **Level** | 🔴 Critical |
| **Probability** | Low (with proper config) |
| **Impact** | Data breach, unauthorized access |

**Causes:**
- Firewall misconfiguration
- MySQL bound to wrong interface
- Weak passwords

**Mitigation:**
1. MySQL binds to private IP only, not 0.0.0.0
2. Firewall allows only VPS1 private IP
3. Use strong generated passwords (32+ chars)
4. Verify with external port scan

**Verification:**
```bash
# From external server (NOT VPS1 or VPS2)
nmap -p 3306 <VPS2_PUBLIC_IP>
# Should show: filtered or closed, NOT open

# From VPS2
netstat -tlnp | grep 3306
# Should show: 10.132.0.11:3306 (private IP only)
```

### Risk 4.2: Redis Unauthorized Access
| Attribute | Value |
|-----------|-------|
| **Level** | 🔴 Critical |
| **Probability** | Low (with proper config) |
| **Impact** | Session hijacking, cache poisoning |

**Mitigation:**
1. Redis requires password authentication
2. Redis binds to localhost + private IP only
3. Firewall restricts access to VPS1 only

**Redis Security Checklist:**
```bash
# Verify Redis config
grep "requirepass" /etc/redis/redis.conf  # Should have password
grep "bind" /etc/redis/redis.conf         # Should be 127.0.0.1 + private IP

# Test auth requirement
redis-cli -h 10.132.0.11 ping  # Should fail without password
redis-cli -h 10.132.0.11 -a "PASSWORD" ping  # Should work
```

### Risk 4.3: Credentials in Transit
| Attribute | Value |
|-----------|-------|
| **Level** | 🟡 Medium |
| **Probability** | Low |
| **Impact** | Credential exposure |

**Causes:**
- Unencrypted private network traffic
- Logging sensitive data

**Mitigation:**
1. Private network traffic is isolated (VPS provider level)
2. Consider TLS for MySQL connection (optional for private network)
3. Never log passwords or tokens
4. Use environment variables, not hardcoded credentials

---

## 5. Configuration Risks

### Risk 5.1: Environment Variable Mismatch
| Attribute | Value |
|-----------|-------|
| **Level** | 🟠 High |
| **Probability** | Medium |
| **Impact** | Service failures, connection errors |

**Causes:**
- Typos in .env files
- Missing required variables
- Wrong password/secrets

**Mitigation:**
1. Use checklist for all env variable changes
2. Test each connection individually after updating
3. Keep backup of original .env files

**Critical Environment Variables:**
```bash
# VPS1 .env - Must update these
DB_HOST=10.132.0.11
DB_PASSWORD=<NEW_PASSWORD>
REDIS_HOST=10.132.0.11
REDIS_PASSWORD=<NEW_PASSWORD>
NODEJS_REALTIME_URL=http://10.132.0.11:3002

# VPS2 Node.js .env - Must have these
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=<SAME_AS_ABOVE>
LARAVEL_API_URL=http://10.132.0.10
```

### Risk 5.2: Supervisor Configuration Errors
| Attribute | Value |
|-----------|-------|
| **Level** | 🟡 Medium |
| **Probability** | Low |
| **Impact** | Queue workers don't start |

**Causes:**
- Wrong process group names
- Path errors
- Permission issues

**Current Supervisor Groups (Don't Change):**
- `smartline-worker-high` (4 processes)
- `smartline-worker-default` (2 processes)
- `smartline-worker-notifications` (2 processes)

**Verification:**
```bash
supervisorctl status
# All should show RUNNING
```

---

## 6. Rollback Plan

### Rollback Trigger Conditions:
1. Database connection fails after 15 minutes of troubleshooting
2. More than 50% of API requests failing
3. Queue workers unable to process jobs
4. WebSocket connections cannot establish
5. Any security vulnerability discovered

### Rollback Steps (15-20 minutes):

```bash
# Step 1: Put app in maintenance mode
cd /var/www/laravel/smartlinevps/rateel
php artisan down

# Step 2: Restore original .env
cp /tmp/smartline_migration_backup_*/env.backup .env

# Step 3: Clear Laravel cache
php artisan config:clear
php artisan cache:clear

# Step 4: Restart local MySQL and Redis (if stopped)
systemctl start mysql
systemctl start redis-server

# Step 5: Verify local connections
php artisan tinker --execute="DB::connection()->getPdo();"
php artisan tinker --execute="Redis::connection()->ping();"

# Step 6: Restart queue workers
supervisorctl restart smartline-worker-high:*
supervisorctl restart smartline-worker-default:*
supervisorctl restart smartline-worker-notifications:*

# Step 7: Bring app back online
php artisan up

# Step 8: Verify
curl -s https://smartline-it.com/api/health | jq
```

### Post-Rollback Actions:
1. Document what went wrong
2. Analyze logs for root cause
3. Fix issues before next attempt
4. Schedule new migration window

---

## 7. Risk Summary Matrix

| Risk | Level | Probability | Mitigation Ready | Rollback Ready |
|------|-------|-------------|------------------|----------------|
| Database Corruption | 🔴 Critical | Low | ✅ | ✅ |
| Data Loss During Cutover | 🔴 Critical | Medium | ✅ | ✅ |
| Private Network Failure | 🔴 Critical | Low | ✅ | ✅ |
| Exposed Database Port | 🔴 Critical | Low | ✅ | N/A |
| Redis Unauthorized Access | 🔴 Critical | Low | ✅ | N/A |
| Extended Downtime | 🟠 High | Medium | ✅ | ✅ |
| Env Variable Mismatch | 🟠 High | Medium | ✅ | ✅ |
| Network Latency | 🟡 Medium | Medium | ✅ | ⚠️ Monitor |
| Queue Job Failures | 🟡 Medium | Medium | ✅ | ✅ |
| WebSocket Drops | 🟡 Medium | High | ✅ | Auto-recover |

---

## 8. Pre-Migration Checklist

### 48 Hours Before:
- [ ] VPS2 provisioned and accessible
- [ ] Private network tested between VPS1 and VPS2
- [ ] All scripts tested on non-production data
- [ ] Rollback procedure documented and tested
- [ ] Team notified of maintenance window

### 24 Hours Before:
- [ ] Full database backup taken and verified
- [ ] All configuration files backed up
- [ ] Monitoring alerts configured
- [ ] Communication prepared for users (if needed)

### 1 Hour Before:
- [ ] Queue sizes checked and documented
- [ ] Active WebSocket connections noted
- [ ] All team members available
- [ ] Rollback scripts ready to execute

### During Migration:
- [ ] Maintenance mode enabled
- [ ] Each step verified before proceeding
- [ ] Logs monitored continuously
- [ ] Regular status updates to team

### Post-Migration:
- [ ] All health checks passing
- [ ] Queue workers processing
- [ ] WebSocket connections established
- [ ] No errors in application logs
- [ ] Performance metrics normal
- [ ] Monitoring confirmed working

---

**Document Version:** 1.0
**Last Updated:** January 19, 2026
**Review Required Before:** Migration execution
