# SmartLine 2-VPS Deployment Scripts

**Created:** January 19, 2026
**Project:** SmartLine Ride-Hailing Platform
**Purpose:** Migration from single VPS to 2-VPS architecture

---

## Overview

This directory contains all scripts and documentation needed to migrate SmartLine from a single VPS to a 2-VPS architecture:

- **VPS1 (Application Server):** Laravel API, Queue Workers, Nginx
- **VPS2 (Data + Realtime Server):** MySQL, Redis, Node.js Realtime Service

---

## File Structure

```
deployment-scripts/
├── README.md                             # This file
├── 01-CORRECTED-MIGRATION-PLAN.md       # Updated migration plan with correct paths
├── 02-RISKS-AND-MITIGATION.md           # Risk assessment and mitigation strategies
├── 03-ESTIMATED-CAPACITY.md             # Capacity planning and resource estimates
├── 04-vps1-migration.sh                 # VPS1 migration script (run after VPS2 is ready)
├── 05-vps2-setup.sh                     # VPS2 setup script (run first on new server)
├── 06-verify-vps1.sh                    # VPS1 verification script
├── 07-verify-vps2.sh                    # VPS2 verification script
├── 08-rollback-vps1.sh                  # Emergency rollback script for VPS1
├── 09-scale-nodejs-to-4-instances.md    # Guide to scale Node.js from 2 to 4 instances
└── 10-scale-nodejs.sh                   # Automated Node.js scaling script
```

---

## Quick Start Guide

### Step 1: Review Documentation

Before starting, read these documents:

1. **01-CORRECTED-MIGRATION-PLAN.md** - Understanding the architecture
2. **02-RISKS-AND-MITIGATION.md** - Know the risks and how to handle them
3. **03-ESTIMATED-CAPACITY.md** - VPS2 sizing requirements

### Step 2: Provision VPS2

- **Actual Spec:** 16GB RAM, 4 cores, 100GB+ SSD
- Must support private networking with VPS1

### Step 3: Setup VPS2

```bash
# Copy script to VPS2
scp 05-vps2-setup.sh root@<vps2-ip>:/root/

# SSH to VPS2 and run
ssh root@<vps2-ip>
chmod +x /root/05-vps2-setup.sh
./05-vps2-setup.sh
```

### Step 4: Import Database to VPS2

```bash
# On VPS1: Create database backup
mysqldump -u root -p merged2 > /tmp/smartline-backup.sql
gzip /tmp/smartline-backup.sql

# Copy to VPS2
scp /tmp/smartline-backup.sql.gz root@<vps2-ip>:/tmp/

# On VPS2: Import database
gunzip /tmp/smartline-backup.sql.gz
mysql -u root -p merged2 < /tmp/smartline-backup.sql
```

### Step 5: Deploy Node.js to VPS2

```bash
# On VPS2
scp -r root@<vps1-ip>:/var/www/laravel/smartlinevps/realtime-service/* /var/www/realtime-service/

# Update .env with correct values
nano /var/www/realtime-service/.env

# Install and start
cd /var/www/realtime-service
npm install --production
pm2 start ecosystem.config.js
pm2 save
```

### Step 6: Verify VPS2

```bash
# On VPS2
./07-verify-vps2.sh
```

### Step 7: Migrate VPS1

```bash
# On VPS1
cd /var/www/laravel/smartlinevps/rateel/deployment-scripts
sudo ./04-vps1-migration.sh
```

### Step 8: Verify VPS1

```bash
# On VPS1
./06-verify-vps1.sh
```

---

## Script Details

### 04-vps1-migration.sh

**Purpose:** Updates VPS1 to connect to VPS2 for database, Redis, and Node.js

**What it does:**
- Backs up current .env configuration
- Optionally creates database backup
- Tests connectivity to VPS2 (MySQL, Redis, Node.js)
- Updates Laravel .env with VPS2 connection details
- Clears and rebuilds Laravel caches
- Restarts PHP-FPM, Nginx, and queue workers
- Runs verification tests

**Environment Variables:**
```bash
VPS2_PRIVATE_IP=10.132.0.11
MYSQL_USER=smartline_app
MYSQL_PASSWORD=xxx
MYSQL_DATABASE=merged2
REDIS_PASSWORD=xxx
```

### 05-vps2-setup.sh

**Purpose:** Sets up a fresh VPS2 with MySQL, Redis, and Node.js

**What it does:**
- Updates system packages
- Installs and configures MySQL with remote access
- Installs and configures Redis with authentication
- Installs Node.js and PM2
- Configures firewall rules
- Creates backup script and cron job
- Generates and saves credentials

**Important:** Run this BEFORE migrating VPS1

### 06-verify-vps1.sh

**Purpose:** Comprehensive verification of VPS1 after migration

**Checks:**
- System resources (disk, memory, load)
- Service status (Nginx, PHP-FPM, Supervisor)
- Network connectivity to VPS2
- Database and Redis connections
- Queue workers
- Laravel application
- API health endpoints
- Recent error logs
- SSL certificate

### 07-verify-vps2.sh

**Purpose:** Comprehensive verification of VPS2 setup

**Checks:**
- System resources
- MySQL service and configuration
- Redis service and configuration
- Node.js and PM2
- Firewall status
- Security (no public exposure of DB/Redis)
- Log files

### 08-rollback-vps1.sh

**Purpose:** Emergency rollback to local services

**What it does:**
- Enables maintenance mode
- Stops queue workers
- Starts local MySQL and Redis
- Restores original .env from backup
- Clears and rebuilds caches
- Restarts all services
- Runs verification

**Usage:**
```bash
# Use most recent backup automatically
sudo ./08-rollback-vps1.sh

# Or specify backup directory
sudo ./08-rollback-vps1.sh /tmp/smartline_migration_backup_20260119_140000
```

---

## Key Paths Reference

### VPS1

| Path | Description |
|------|-------------|
| `/var/www/laravel/smartlinevps/rateel/` | Laravel application root |
| `/var/www/laravel/smartlinevps/rateel/.env` | Laravel environment config |
| `/var/www/laravel/smartlinevps/rateel/storage/logs/` | Laravel logs |
| `/etc/nginx/sites-available/smartline` | Nginx configuration |
| `/var/www/laravel/smartlinevps/rateel/supervisor-queue-workers.conf` | Supervisor config |

### VPS2

| Path | Description |
|------|-------------|
| `/var/www/realtime-service/` | Node.js application |
| `/var/www/realtime-service/.env` | Node.js environment config |
| `/etc/mysql/mysql.conf.d/mysqld.cnf` | MySQL configuration |
| `/etc/redis/redis.conf` | Redis configuration |
| `/root/smartline-credentials.txt` | Generated credentials (keep secure!) |
| `/var/backups/smartline/` | Database backups |

---

## Corrections from Original Plan

The original migration plan had several incorrect paths and configurations. Here are the corrections:

| Item | Original (Wrong) | Correct |
|------|------------------|---------|
| Laravel Path | `/var/www/smartline` | `/var/www/laravel/smartlinevps/rateel/` |
| Node.js Path | `/var/www/realtime-service` | `/var/www/laravel/smartlinevps/realtime-service/` |
| Database Name | `smartline_production` | `merged2` |
| Node.js Port | 3000 | 3002 (proxied via 3000) |
| Supervisor Groups | `smartline-worker:*` | `smartline-worker-high`, `smartline-worker-default`, `smartline-worker-notifications` |
| Queue Workers | 6 | 8 (4 high + 2 default + 2 notifications) |

---

## Troubleshooting

### MySQL Connection Issues

```bash
# Test from VPS1
mysql -h <vps2-private-ip> -u smartline_app -p

# Check MySQL is listening on VPS2
netstat -tlnp | grep 3306

# Check firewall on VPS2
ufw status
```

### Redis Connection Issues

```bash
# Test from VPS1
redis-cli -h <vps2-private-ip> -p 6379 -a "password" ping

# Check Redis is listening on VPS2
netstat -tlnp | grep 6379
```

### Queue Worker Issues

```bash
# Check supervisor status
supervisorctl status

# View worker logs
tail -f /var/www/laravel/smartlinevps/rateel/storage/logs/worker-*.log

# Restart workers
supervisorctl restart smartline-worker-high:* smartline-worker-default:* smartline-worker-notifications:*
```

### Laravel Cache Issues

```bash
cd /var/www/laravel/smartlinevps/rateel
php artisan config:clear
php artisan cache:clear
php artisan config:cache
php artisan route:cache
```

---

## Scaling Node.js Instances

### When to Scale

Scale from 2 to 4 instances when:
- Consistent >500 concurrent WebSocket connections
- Planning for 2-3x traffic growth
- Need better CPU utilization (1 instance per core)

### Quick Scaling (Automated)

```bash
# On VPS2
cd /var/www/laravel/smartlinevps/rateel/deployment-scripts

# Scale to 4 instances
sudo ./10-scale-nodejs.sh 4

# Verify
pm2 status
```

### Manual Scaling (Step-by-Step)

See detailed guide: **09-scale-nodejs-to-4-instances.md**

### Instance Capacity

| Instances | Connections | CPU Usage | RAM Usage | Recommended For |
|-----------|-------------|-----------|-----------|-----------------|
| 2 | 400-600 | 50% | 1.2GB | **Current baseline** |
| 3 | 600-900 | 75% | 1.8GB | 2x growth |
| 4 | 800-1,200 | 100% | 2.4GB | **Optimal (1 per core)** |
| 5-6 | 1,000-1,800 | 125%+ | 3.0-3.6GB | Peak only (not ideal) |

**Recommendation:** Stick with 2-4 instances max on 16GB VPS2

---

## Support

For issues or questions:
1. Check the logs first
2. Run the verification scripts
3. Review the RISKS-AND-MITIGATION.md document
4. Contact the development team

---

**Last Updated:** January 19, 2026
