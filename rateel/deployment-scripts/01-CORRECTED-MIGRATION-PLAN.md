# SmartLine 2-VPS Migration Plan (CORRECTED)

**Date:** January 19, 2026
**Current Setup:** Single VPS (16GB RAM, 4 cores)
**Target:** Split across 2 VPS servers
**Domain:** smartline-it.com

---

## ⚠️ CORRECTIONS FROM ORIGINAL PLAN

| Item | Original (Wrong) | Corrected |
|------|------------------|-----------|
| Laravel Path | `/var/www/smartline` | `/var/www/laravel/smartlinevps/rateel/` |
| Node.js Path | `/var/www/realtime-service` | `/var/www/laravel/smartlinevps/realtime-service/` |
| Database Name | `smartline_production` | `merged2` |
| Node.js Internal Port | 3000 | 3002 (proxied via Nginx on 3000) |
| Queue Workers | 6 (2 high + 4 default) | 8 (4 high + 2 default + 2 notifications) |
| Supervisor Groups | `smartline-worker:*` | `smartline-worker-high`, `smartline-worker-default`, `smartline-worker-notifications` |
| PHP Version | 8.1/8.2 | 8.2 (confirmed) |
| MySQL User | `smartline_app` | Currently `root` (create new user for VPS2) |

---

## Current Architecture Analysis

### Current Single VPS Services:

| Service | Port | Status |
|---------|------|--------|
| Laravel 10.x (PHP 8.2 + Nginx + PHP-FPM) | 80/443 | Running |
| Node.js Realtime Service | 3002 (internal), 3000 (Nginx proxy) | Running |
| MySQL 8.0 | 3306 | Running |
| Redis 7.x | 6379 | Running |
| Queue Workers (8 total) | N/A | Running via Supervisor |
| Laravel Reverb | 443 (shared with Nginx) | Configured |
| Cron Jobs | N/A | Running |

### Current Resource Usage (Estimated):

| Service | RAM Usage |
|---------|-----------|
| MySQL | 4-6GB |
| Redis | 1-2GB |
| Laravel + PHP-FPM | 2-3GB |
| Node.js Realtime | 500MB-1GB |
| Queue Workers (8) | 1.5-2.5GB |
| System | 1GB |
| **Total** | **10-15GB** (near capacity) |

---

## Target 2-VPS Architecture

```
                    INTERNET / USERS
              (Mobile Apps, Web Clients)
                         |
                         |
         +---------------+---------------+
         |                               |
         ▼                               ▼
    +---------+                    +-----------+
    |  VPS 1  |                    |   VPS 2   |
    | 16GB/4C |                    |  16GB/4C  |
    +---------+                    +-----------+
         |                               |
    APPLICATION                    DATA + REALTIME
    SERVER                         SERVER
         |                               |
    ┌────┴────┐                    ┌─────┴─────┐
    │ Nginx   │                    │  MySQL    │
    │ 80/443  │                    │  3306     │
    └────┬────┘                    └─────┬─────┘
         │                               │
    ┌────┴────┐                    ┌─────┴─────┐
    │ PHP-FPM │                    │  Redis    │
    │ Laravel │                    │  6379     │
    └────┬────┘                    └─────┴─────┘
         │                               │
    ┌────┴────┐                    ┌─────┴─────┐
    │ Queue   │                    │  Node.js  │
    │ Workers │                    │  Realtime │
    │   (8)   │                    │  3002     │
    └─────────┘                    └───────────┘
         │                               │
         └───────────┬───────────────────┘
                     │
              PRIVATE NETWORK
               (10.x.x.x/16)
```

---

## VPS 1: Application Server (16GB RAM, 4 cores)

### Services to Keep:
- **Nginx** - Reverse proxy, SSL termination
- **PHP-FPM** - Laravel application
- **Queue Workers** - 8 workers via Supervisor
- **Cron Jobs** - Laravel scheduler
- **Laravel Reverb** - WebSocket server (optional, can move to VPS2)

### Expected Resource Usage After Migration:

| Service | RAM |
|---------|-----|
| PHP-FPM (32 workers) | 2GB |
| Queue Workers (8) | 2GB |
| Nginx | 200MB |
| System | 1GB |
| **Total** | **~5-6GB** |
| **Available** | **~10GB** (headroom for scaling) |

### Open Ports:

| Port | Service | Access |
|------|---------|--------|
| 22 | SSH | Public (key-only) |
| 80 | HTTP | Public (redirect to HTTPS) |
| 443 | HTTPS | Public |

### Outbound Connections to VPS2:

| Port | Service | Network |
|------|---------|---------|
| 3306 | MySQL | Private |
| 6379 | Redis | Private |
| 3002 | Node.js API | Private |

---

## VPS 2: Data + Realtime Server (16GB RAM, 4 cores)

### Services to Deploy:
- **MySQL 8.0** - Primary database
- **Redis 7.x** - Cache, Sessions, Queue, Pub/Sub
- **Node.js Realtime** - WebSocket service

### Expected Resource Usage:

| Service | RAM |
|---------|-----|
| MySQL (innodb_buffer_pool_size=8G) | 6-8GB |
| Redis (maxmemory=3gb) | 3GB |
| Node.js (2 cluster instances) | 1GB |
| System | 1GB |
| **Total** | **~11-13GB** |
| **Available** | **~3-5GB** (excellent headroom) |

### Open Ports:

| Port | Service | Access |
|------|---------|--------|
| 22 | SSH | Restricted (VPS1 IP only) |
| 3000 | WebSocket (via Nginx) | Public |
| 3306 | MySQL | Private network only |
| 6379 | Redis | Private network only |

---

## Migration Phases Overview

### Phase 1: Prepare VPS 2 (2-4 hours)
1. Setup VPS2 infrastructure (OS, packages)
2. Configure private network
3. Install and configure MySQL
4. Install and configure Redis
5. Install Node.js and PM2
6. Configure firewall

### Phase 2: Data Migration (1-2 hours)
1. Export database from VPS1
2. Transfer to VPS2
3. Import database
4. Create MySQL user for VPS1
5. Verify data integrity

### Phase 3: Deploy Node.js to VPS2 (30 min - 1 hour)
1. Clone/upload realtime-service
2. Configure .env for VPS2
3. Start with PM2
4. Test WebSocket connectivity

### Phase 4: Update VPS1 Configuration (30 min)
1. Update Laravel .env
2. Clear caches
3. Test connections
4. Update Nginx (if proxying WebSocket)

### Phase 5: Cutover & Testing (1-2 hours)
1. Restart all services
2. Run verification tests
3. Monitor for issues
4. Rollback if needed

**Total Estimated Time: 5-10 hours**

---

## Configuration Files to Update

### VPS1 - Laravel .env Changes:

```env
# BEFORE (Current)
DB_HOST=localhost
REDIS_HOST=127.0.0.1
NODEJS_REALTIME_URL=http://localhost:3001

# AFTER (Migration)
DB_HOST=10.132.0.11          # VPS2 private IP
REDIS_HOST=10.132.0.11       # VPS2 private IP
NODEJS_REALTIME_URL=http://10.132.0.11:3002
```

### VPS2 - Node.js Realtime .env:

```env
NODE_ENV=production
PORT=3002
HOST=0.0.0.0

# Redis (local on VPS2)
REDIS_ENABLED=true
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=YOUR_REDIS_PASSWORD
REDIS_PORT=6379

# Laravel API (VPS1 private IP)
LARAVEL_API_URL=http://10.132.0.10
LARAVEL_API_KEY=smartline-internal-key-change-in-production
VALIDATE_WITH_LARAVEL=true

# JWT (same secret as Laravel)
JWT_SECRET=YOUR_JWT_SECRET

# WebSocket
WS_CORS_ORIGIN=*
```

### VPS2 - MySQL User Creation:

```sql
CREATE USER 'smartline_app'@'10.132.0.10' IDENTIFIED BY 'STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON merged2.* TO 'smartline_app'@'10.132.0.10';
FLUSH PRIVILEGES;
```

---

## Verification Checklist

### Pre-Migration:
- [ ] VPS2 provisioned with correct specs
- [ ] Private network configured between VPS1 and VPS2
- [ ] Backup of current database created
- [ ] Backup of all .env files
- [ ] Maintenance window scheduled

### Post-Migration:
- [ ] Laravel can connect to MySQL on VPS2
- [ ] Laravel can connect to Redis on VPS2
- [ ] Queue workers processing jobs
- [ ] WebSocket connections working
- [ ] Mobile app can connect
- [ ] All API endpoints responding
- [ ] Health checks passing
- [ ] No errors in logs

---

## Quick Reference: File Paths

| Component | VPS1 Path |
|-----------|-----------|
| Laravel Root | `/var/www/laravel/smartlinevps/rateel/` |
| Laravel .env | `/var/www/laravel/smartlinevps/rateel/.env` |
| Supervisor Config | `/var/www/laravel/smartlinevps/rateel/supervisor-queue-workers.conf` |
| Nginx Config | `/etc/nginx/sites-available/smartline` |
| Nginx Realtime | `/etc/nginx/sites-available/smartline-realtime-3000` |
| PHP-FPM Config | `/etc/php/8.2/fpm/pool.d/www.conf` |

| Component | VPS2 Path (After Migration) |
|-----------|-----------|
| Node.js Root | `/var/www/realtime-service/` |
| Node.js .env | `/var/www/realtime-service/.env` |
| PM2 Ecosystem | `/var/www/realtime-service/ecosystem.config.js` |
| MySQL Config | `/etc/mysql/mysql.conf.d/mysqld.cnf` |
| Redis Config | `/etc/redis/redis.conf` |

---

**Document Version:** 2.0
**Last Updated:** January 19, 2026
**Status:** Corrected and Ready for Implementation
