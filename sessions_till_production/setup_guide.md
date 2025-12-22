# Multi-VPS Deployment Architecture
## 2 Node.js + 1 Laravel + 1 Redis/Database VPS

**Date:** December 17, 2025
**Architecture:** Distributed microservices deployment for Uber-like app

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                         INTERNET / USERS                        │
│                    (Mobile Apps, Web Clients)                   │
└─────────────────────┬───────────────────────────────────────────┘
                      │
         ┌────────────┴────────────┐
         │                         │
         │   Load Balancer (Optional)
         │   - Nginx/HAProxy       │
         │   - Round-robin         │
         │   - Health checks       │
         └────────────┬────────────┘
                      │
          ┌───────────┴───────────┐
          │                       │
┌─────────▼─────────┐   ┌─────────▼─────────┐
│   NODE VPS #1     │   │   NODE VPS #2     │
│ Real-time Service │   │ Real-time Service │
│                   │   │                   │
│ - Socket.IO       │   │ - Socket.IO       │
│ - WebSockets      │   │ - WebSockets      │
│ - Port 3000       │   │ - Port 3000       │
│ - Driver matching │   │ - Driver matching │
│ - Location track  │   │ - Location track  │
└─────────┬─────────┘   └─────────┬─────────┘
          │                       │
          │    PRIVATE NETWORK    │
          │    (10.x.x.x/16)     │
          │                       │
          └───────────┬───────────┘
                      │
         ┌────────────▼────────────┐
         │    LARAVEL VPS          │
         │  Business Logic API     │
         │                         │
         │  - PHP 8.2 / Laravel    │
         │  - REST API             │
         │  - Port 80/443          │
         │  - Business logic       │
         │  - Authentication       │
         │  - Trip management      │
         └────────────┬────────────┘
                      │
          ┌───────────▼───────────┐
          │                       │
┌─────────▼─────────┐   ┌─────────▼─────────┐
│  REDIS + DB VPS   │   │  (Future: Replica)│
│                   │   │                   │
│ - Redis (port 6379)   │ - Read replicas   │
│ - MySQL (port 3306)   │ - Failover        │
│ - Private access  │   │                   │
│ - Backups         │   │                   │
└───────────────────┘   └───────────────────┘
```

---

## VPS Specifications

### VPS #1 & #2: Node.js Real-time Service

**Purpose:** Handle WebSocket connections, driver matching, location tracking

**Specifications:**
- **RAM:** 4GB (minimum) / 8GB (recommended)
- **CPU:** 2 vCPU
- **Storage:** 40GB SSD
- **OS:** Ubuntu 22.04 LTS
- **Network:** Private network enabled
- **Public IP:** Yes (for WebSocket connections)

**Installed Software:**
- Node.js v20.x
- PM2 (process manager)
- Nginx (reverse proxy, optional)
- UFW (firewall)

**Open Ports:**
- 22 (SSH)
- 3000 (WebSocket/HTTP - public)
- 10.x.x.x:6379 (Redis - private network only)
- 10.x.x.x:3306 (MySQL - private network only)

**Estimated Cost:** $10-20/month each

---

### VPS #3: Laravel Business API

**Purpose:** RESTful API, business logic, authentication, database operations

**Specifications:**
- **RAM:** 4GB (minimum) / 8GB (recommended)
- **CPU:** 2 vCPU
- **Storage:** 60GB SSD
- **OS:** Ubuntu 22.04 LTS
- **Network:** Private network enabled
- **Public IP:** Yes (for API access)

**Installed Software:**
- PHP 8.2 + Extensions
- Composer
- Nginx + PHP-FPM
- Supervisor (queue workers)
- UFW (firewall)

**Open Ports:**
- 22 (SSH)
- 80 (HTTP - public)
- 443 (HTTPS - public)
- 10.x.x.x:6379 (Redis - private network only)
- 10.x.x.x:3306 (MySQL - private network only)

**Estimated Cost:** $15-25/month

---

### VPS #4: Redis + MySQL Database

**Purpose:** Centralized data storage and caching

**Specifications:**
- **RAM:** 8GB (minimum) / 16GB (recommended)
- **CPU:** 4 vCPU
- **Storage:** 100GB SSD (with auto-backup)
- **OS:** Ubuntu 22.04 LTS
- **Network:** Private network enabled
- **Public IP:** Optional (only for admin access)

**Installed Software:**
- Redis 7.x
- MySQL 8.0
- Automated backup scripts
- Monitoring tools
- UFW (firewall)

**Open Ports:**
- 22 (SSH - from specific IPs only)
- 10.x.x.x:6379 (Redis - private network only)
- 10.x.x.x:3306 (MySQL - private network only)

**Estimated Cost:** $25-40/month

---

## Total Monthly Cost Estimate

| Component | Specs | Cost |
|-----------|-------|------|
| Node VPS #1 | 4GB RAM, 2 vCPU | $15 |
| Node VPS #2 | 4GB RAM, 2 vCPU | $15 |
| Laravel VPS | 4GB RAM, 2 vCPU | $20 |
| Redis+DB VPS | 8GB RAM, 4 vCPU | $35 |
| **Total** | | **$85/month** |

**Recommended Providers:**
- DigitalOcean (has private networking)
- Vultr (has private networking)
- Linode/Akamai (has private networking)
- Hetzner (cheapest, has private networking)

---

## Network Architecture

### Private Network Configuration

**Network:** `10.132.0.0/16` (example from DigitalOcean)

| Server | Public IP | Private IP | Purpose |
|--------|-----------|------------|---------|
| Node #1 | 203.0.113.10 | 10.132.0.2 | WebSocket service |
| Node #2 | 203.0.113.11 | 10.132.0.3 | WebSocket service |
| Laravel | 203.0.113.12 | 10.132.0.4 | REST API |
| Redis+DB | 203.0.113.13* | 10.132.0.5 | Data layer |

*Optional - only needed for admin SSH access

### Communication Flow

```
1. Mobile App → Node VPS (Public IP:3000) → WebSocket established
2. Node VPS → Laravel VPS (Private IP:80) → Internal API calls
3. Node VPS → Redis VPS (Private IP:6379) → Cache/geo queries
4. Laravel VPS → MySQL VPS (Private IP:3306) → Database queries
5. Laravel VPS → Redis VPS (Private IP:6379) → Cache/pub-sub
```

**Benefits:**
- Low latency (private network is faster)
- No bandwidth costs for internal traffic
- Secure (not exposed to internet)
- No need for VPN

---

## Deployment Steps

### Phase 1: Setup Infrastructure

#### Step 1: Create VPS Instances

**On your VPS provider (e.g., DigitalOcean):**

```bash
# Create 4 droplets with:
# - Ubuntu 22.04 LTS
# - Private networking enabled
# - SSH keys added
# - Same datacenter region (important!)

# Note down the private IPs for each server
```

#### Step 2: Configure Hostnames (on each server)

```bash
# On Node VPS #1
sudo hostnamectl set-hostname node1.smartline.local

# On Node VPS #2
sudo hostnamectl set-hostname node2.smartline.local

# On Laravel VPS
sudo hostnamectl set-hostname laravel.smartline.local

# On Redis+DB VPS
sudo hostnamectl set-hostname data.smartline.local
```

#### Step 3: Update /etc/hosts (on all servers)

```bash
# Add to /etc/hosts on ALL servers
sudo nano /etc/hosts

# Add these lines:
10.132.0.2  node1.smartline.local node1
10.132.0.3  node2.smartline.local node2
10.132.0.4  laravel.smartline.local laravel
10.132.0.5  data.smartline.local data redis-server mysql-server
```

#### Step 4: Test Private Network Connectivity

```bash
# From Node #1, ping other servers via private network
ping -c 3 10.132.0.3  # Node #2
ping -c 3 10.132.0.4  # Laravel
ping -c 3 10.132.0.5  # Redis+DB

# Should have <1ms latency
```

---

### Phase 2: Deploy Redis + MySQL (Data VPS)

#### SSH into Redis+DB VPS

```bash
ssh root@<data-vps-public-ip>
```

#### Install Redis

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Redis
sudo apt install redis-server -y

# Generate password
REDIS_PASSWORD=$(openssl rand -base64 32)
echo "Redis Password: $REDIS_PASSWORD" >> ~/credentials.txt
```

#### Configure Redis for Remote Access

```bash
# Backup config
sudo cp /etc/redis/redis.conf /etc/redis/redis.conf.backup

# Edit config
sudo nano /etc/redis/redis.conf

# CHANGE these settings:
# 1. Bind to private IP (not localhost!)
bind 127.0.0.1 10.132.0.5

# 2. Set password
requirepass YOUR_REDIS_PASSWORD

# 3. Memory limit (4GB for 8GB VPS)
maxmemory 4gb
maxmemory-policy volatile-ttl

# 4. Persistence
appendonly yes
appendfsync everysec
save 900 1
save 300 100
save 60 10000

# 5. Disable dangerous commands
rename-command FLUSHDB "FLUSHDB_SECRET_2024"
rename-command FLUSHALL "FLUSHALL_SECRET_2024"
rename-command CONFIG "CONFIG_SECRET_2024"

# Save and exit (Ctrl+X, Y, Enter)
```

#### Configure Firewall for Redis

```bash
# Allow SSH (public)
sudo ufw allow 22/tcp

# Allow Redis from private network ONLY
sudo ufw allow from 10.132.0.0/16 to any port 6379 proto tcp

# Deny Redis from public internet
sudo ufw deny 6379/tcp

# Enable firewall
sudo ufw enable

# Verify
sudo ufw status verbose
```

#### Restart Redis

```bash
sudo systemctl restart redis-server
sudo systemctl status redis-server
```

#### Test Redis from Remote Server

```bash
# From Node VPS #1 or Laravel VPS
redis-cli -h 10.132.0.5 -p 6379 -a "YOUR_REDIS_PASSWORD" ping
# Expected: PONG
```

#### Install MySQL

```bash
# Install MySQL
sudo apt install mysql-server -y

# Secure installation
sudo mysql_secure_installation
# - Set root password (save to ~/credentials.txt)
# - Remove anonymous users: Yes
# - Disallow root login remotely: No (we need remote access via private network)
# - Remove test database: Yes
# - Reload privilege tables: Yes
```

#### Configure MySQL for Remote Access

```bash
# Edit MySQL config
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf

# CHANGE:
# 1. Bind to private IP (not 127.0.0.1!)
bind-address = 10.132.0.5

# 2. Increase max connections (for 10k+ users)
max_connections = 500

# 3. Buffer pool size (4GB for 8GB VPS)
innodb_buffer_pool_size = 4G

# Save and exit
```

#### Create Database and User

```bash
# Login to MySQL
sudo mysql -u root -p

# Create database
CREATE DATABASE smartline_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Create user for Laravel (accessible from private network)
CREATE USER 'smartline_user'@'10.132.0.%' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON smartline_production.* TO 'smartline_user'@'10.132.0.%';

# Create user for Node.js (read-only access if needed)
CREATE USER 'smartline_readonly'@'10.132.0.%' IDENTIFIED BY 'ANOTHER_STRONG_PASSWORD';
GRANT SELECT ON smartline_production.* TO 'smartline_readonly'@'10.132.0.%';

# Flush privileges
FLUSH PRIVILEGES;

# Exit
EXIT;
```

#### Configure Firewall for MySQL

```bash
# Allow MySQL from private network ONLY
sudo ufw allow from 10.132.0.0/16 to any port 3306 proto tcp

# Deny MySQL from public internet
sudo ufw deny 3306/tcp

# Reload firewall
sudo ufw reload

# Verify
sudo ufw status verbose
```

#### Restart MySQL

```bash
sudo systemctl restart mysql
sudo systemctl status mysql
```

#### Test MySQL from Remote Server

```bash
# From Laravel VPS
mysql -h 10.132.0.5 -u smartline_user -p smartline_production
# Enter password
# If successful, you'll see MySQL prompt
```

---

### Phase 3: Deploy Laravel (Laravel VPS)

#### SSH into Laravel VPS

```bash
ssh root@<laravel-vps-public-ip>
```

#### Install PHP and Dependencies

```bash
# Install PHP 8.2 and extensions
sudo apt update
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update

sudo apt install -y php8.2 php8.2-fpm php8.2-cli php8.2-mysql php8.2-redis \
  php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-gd \
  php8.2-bcmath php8.2-intl php8.2-soap php8.2-imagick

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Nginx
sudo apt install nginx -y

# Install Supervisor (for queue workers)
sudo apt install supervisor -y
```

#### Clone/Upload Laravel Application

```bash
# Create app directory
sudo mkdir -p /var/www/smartline
sudo chown -R $USER:$USER /var/www/smartline

# Option 1: Clone from Git
cd /var/www/smartline
git clone <your-repo-url> .

# Option 2: Upload via SCP/SFTP
# (use FileZilla or similar to upload your Laravel app)

# Install dependencies
cd /var/www/smartline
composer install --no-dev --optimize-autoloader
```

#### Configure Laravel .env

```bash
cd /var/www/smartline
cp .env.example .env
nano .env

# Configure these settings:
APP_NAME=SmartLine
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com

# Database (using private IP)
DB_CONNECTION=mysql
DB_HOST=10.132.0.5
DB_PORT=3306
DB_DATABASE=smartline_production
DB_USERNAME=smartline_user
DB_PASSWORD=YOUR_MYSQL_PASSWORD

# Redis (using private IP)
REDIS_HOST=10.132.0.5
REDIS_PASSWORD=YOUR_REDIS_PASSWORD
REDIS_PORT=6379

# Cache/Session/Queue
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Node.js Real-time Service
NODEJS_REALTIME_URL=http://10.132.0.2:3000
NODEJS_REALTIME_API_KEY=YOUR_INTERNAL_API_KEY
```

#### Generate App Key and Run Migrations

```bash
# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate --force

# Run seeders (if any)
php artisan db:seed --force

# Clear and cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

#### Set Permissions

```bash
sudo chown -R www-data:www-data /var/www/smartline
sudo chmod -R 755 /var/www/smartline
sudo chmod -R 775 /var/www/smartline/storage
sudo chmod -R 775 /var/www/smartline/bootstrap/cache
```

#### Configure Nginx

```bash
sudo nano /etc/nginx/sites-available/smartline

# Add this configuration:
```

```nginx
server {
    listen 80;
    server_name api.yourdomain.com;  # Change this
    root /var/www/smartline/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
# Enable site
sudo ln -s /etc/nginx/sites-available/smartline /etc/nginx/sites-enabled/

# Remove default site
sudo rm /etc/nginx/sites-enabled/default

# Test config
sudo nginx -t

# Restart Nginx
sudo systemctl restart nginx
```

#### Configure Firewall

```bash
# Allow SSH, HTTP, HTTPS
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Enable firewall
sudo ufw enable
```

#### Setup Queue Workers with Supervisor

```bash
sudo nano /etc/supervisor/conf.d/smartline-worker.conf

# Add this:
```

```ini
[program:smartline-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/smartline/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/smartline/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
# Update supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start smartline-worker:*

# Check status
sudo supervisorctl status
```

#### Test Laravel API

```bash
# From your local machine or browser
curl http://<laravel-public-ip>/api/health

# Should return JSON response
```

---

### Phase 4: Deploy Node.js (Both Node VPS)

#### Repeat these steps on BOTH Node VPS #1 and #2

#### SSH into Node VPS

```bash
ssh root@<node-vps-public-ip>
```

#### Install Node.js

```bash
# Install Node.js v20.x
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Verify installation
node --version  # Should be v20.x
npm --version

# Install PM2 globally
sudo npm install -g pm2
```

#### Clone/Upload Node.js Application

```bash
# Create app directory
sudo mkdir -p /var/www/realtime-service
sudo chown -R $USER:$USER /var/www/realtime-service

# Clone from Git
cd /var/www/realtime-service
git clone <your-repo-url> .

# Install dependencies
npm install --production
```

#### Configure .env

```bash
cd /var/www/realtime-service
cp .env.example .env
nano .env

# Configure:
NODE_ENV=production
PORT=3000
HOST=0.0.0.0

# Redis (using private IP)
REDIS_ENABLED=true
REDIS_HOST=10.132.0.5
REDIS_PASSWORD=YOUR_REDIS_PASSWORD
REDIS_PORT=6379

# Laravel API (using private IP)
LARAVEL_API_URL=http://10.132.0.4
LARAVEL_API_KEY=YOUR_INTERNAL_API_KEY

# JWT (same secret as Laravel)
JWT_SECRET=YOUR_JWT_SECRET_FROM_LARAVEL

# WebSocket
WS_CORS_ORIGIN=*
WS_PING_TIMEOUT=60000
WS_PING_INTERVAL=25000
```

#### Configure PM2

```bash
# Create PM2 ecosystem file
nano /var/www/realtime-service/ecosystem.config.js
```

```javascript
module.exports = {
  apps: [{
    name: 'smartline-realtime',
    script: './src/server.js',
    instances: 2,  // Use 2 CPU cores
    exec_mode: 'cluster',
    env: {
      NODE_ENV: 'production',
      PORT: 3000
    },
    error_file: './logs/err.log',
    out_file: './logs/out.log',
    log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
    max_memory_restart: '1G',
    watch: false,
    autorestart: true,
    max_restarts: 10,
    min_uptime: '10s'
  }]
};
```

```bash
# Create logs directory
mkdir -p /var/www/realtime-service/logs

# Start with PM2
cd /var/www/realtime-service
pm2 start ecosystem.config.js

# Save PM2 process list
pm2 save

# Setup PM2 to start on boot
pm2 startup systemd
# Follow the command it prints

# Check status
pm2 status
pm2 logs smartline-realtime
```

#### Configure Firewall

```bash
# Allow SSH and WebSocket port
sudo ufw allow 22/tcp
sudo ufw allow 3000/tcp

# Enable firewall
sudo ufw enable
```

#### Test Node.js Service

```bash
# Test from local machine
curl http://<node-public-ip>:3000/health

# Should return:
# {"status":"ok","service":"smartline-realtime",...}
```

---

### Phase 5: Load Balancing (Optional but Recommended)

#### Option 1: DNS Round-Robin

**Simplest approach:**

```
# In your DNS provider, create A records:
ws.yourdomain.com  →  203.0.113.10 (Node #1)
ws.yourdomain.com  →  203.0.113.11 (Node #2)
```

**Pros:**
- Free
- No additional server needed
- Automatic distribution

**Cons:**
- No health checks
- Sticky sessions not guaranteed

#### Option 2: Nginx Load Balancer

**Deploy on separate VPS or on Laravel VPS:**

```bash
# Install Nginx (if not already installed)
sudo apt install nginx -y

# Configure load balancer
sudo nano /etc/nginx/sites-available/websocket-lb
```

```nginx
upstream websocket_backend {
    # Sticky sessions based on IP (important for WebSocket)
    ip_hash;

    server 10.132.0.2:3000 max_fails=3 fail_timeout=30s;
    server 10.132.0.3:3000 max_fails=3 fail_timeout=30s;
}

server {
    listen 80;
    server_name ws.yourdomain.com;

    location / {
        proxy_pass http://websocket_backend;
        proxy_http_version 1.1;

        # WebSocket headers
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";

        # Headers
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # Timeouts
        proxy_connect_timeout 7d;
        proxy_send_timeout 7d;
        proxy_read_timeout 7d;
    }
}
```

```bash
# Enable site
sudo ln -s /etc/nginx/sites-available/websocket-lb /etc/nginx/sites-enabled/

# Test config
sudo nginx -t

# Restart Nginx
sudo systemctl restart nginx
```

#### Option 3: HAProxy (More Advanced)

**Better for larger scale:**

```bash
# Install HAProxy
sudo apt install haproxy -y

# Configure
sudo nano /etc/haproxy/haproxy.cfg
```

```haproxy
frontend websocket_frontend
    bind *:3000
    mode http
    default_backend websocket_backend

backend websocket_backend
    mode http
    balance roundrobin
    option httpchk GET /health
    http-check expect status 200

    server node1 10.132.0.2:3000 check inter 5s rise 2 fall 3
    server node2 10.132.0.3:3000 check inter 5s rise 2 fall 3
```

```bash
# Restart HAProxy
sudo systemctl restart haproxy

# Check status
sudo systemctl status haproxy
```

---

## Security Hardening

### All Servers

```bash
# 1. Update system
sudo apt update && sudo apt upgrade -y

# 2. Install fail2ban (prevent brute force)
sudo apt install fail2ban -y
sudo systemctl enable fail2ban
sudo systemctl start fail2ban

# 3. Disable password authentication (use SSH keys only)
sudo nano /etc/ssh/sshd_config
# Set: PasswordAuthentication no
sudo systemctl restart sshd

# 4. Install automatic security updates
sudo apt install unattended-upgrades -y
sudo dpkg-reconfigure -plow unattended-upgrades
```

### Redis+DB VPS (Most Critical)

```bash
# 1. Restrict SSH to specific IPs only
sudo ufw delete allow 22/tcp
sudo ufw allow from <your-office-ip> to any port 22 proto tcp

# 2. Set up automated backups
# See backup section below

# 3. Enable MySQL slow query log
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
# Add:
# slow_query_log = 1
# slow_query_log_file = /var/log/mysql/slow-query.log
# long_query_time = 2

# 4. Regular security audits
sudo apt install lynis -y
sudo lynis audit system
```

---

## Monitoring and Maintenance

### Setup Monitoring Scripts

**On Redis+DB VPS:**

```bash
# Create monitoring script
sudo nano /usr/local/bin/system-monitor.sh
```

```bash
#!/bin/bash

# Redis monitoring
REDIS_CLI="redis-cli -h 127.0.0.1 -p 6379 -a YOUR_PASSWORD"
REDIS_MEMORY=$($REDIS_CLI INFO memory | grep 'used_memory_human' | awk -F: '{print $2}' | tr -d '\r')
REDIS_CONNECTIONS=$($REDIS_CLI INFO clients | grep 'connected_clients' | awk -F: '{print $2}' | tr -d '\r')

# MySQL monitoring
MYSQL_CONNECTIONS=$(mysql -u root -pYOUR_PASSWORD -e "SHOW STATUS LIKE 'Threads_connected';" | awk 'NR==2 {print $2}')

# Disk space
DISK_USAGE=$(df -h / | awk 'NR==2 {print $5}' | tr -d '%')

# Send alerts if thresholds exceeded
if [ $DISK_USAGE -gt 80 ]; then
    echo "ALERT: Disk usage is ${DISK_USAGE}%" | mail -s "Server Alert" admin@yourdomain.com
fi

# Log to file
echo "$(date) - Redis Memory: $REDIS_MEMORY, Redis Connections: $REDIS_CONNECTIONS, MySQL Connections: $MYSQL_CONNECTIONS, Disk: ${DISK_USAGE}%" >> /var/log/system-monitor.log
```

```bash
# Make executable
sudo chmod +x /usr/local/bin/system-monitor.sh

# Add to cron (run every 5 minutes)
(crontab -l 2>/dev/null; echo "*/5 * * * * /usr/local/bin/system-monitor.sh") | crontab -
```

### Automated Backups

**Database backup script:**

```bash
sudo nano /usr/local/bin/backup-database.sh
```

```bash
#!/bin/bash

BACKUP_DIR="/var/backups/mysql"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# Backup all databases
mysqldump -u root -pYOUR_PASSWORD --all-databases --single-transaction --quick --lock-tables=false > $BACKUP_DIR/all-databases-$DATE.sql

# Compress
gzip $BACKUP_DIR/all-databases-$DATE.sql

# Delete backups older than 7 days
find $BACKUP_DIR -name "*.sql.gz" -mtime +7 -delete

# Optional: Upload to S3 or remote storage
# aws s3 cp $BACKUP_DIR/all-databases-$DATE.sql.gz s3://your-bucket/backups/

echo "Backup completed: $DATE"
```

```bash
# Make executable
sudo chmod +x /usr/local/bin/backup-database.sh

# Add to cron (daily at 2 AM)
(crontab -l 2>/dev/null; echo "0 2 * * * /usr/local/bin/backup-database.sh") | crontab -
```

---

## Testing Multi-VPS Setup

### Test 1: Redis Connectivity

```bash
# From Node VPS #1
redis-cli -h 10.132.0.5 -p 6379 -a "YOUR_PASSWORD" ping
# Expected: PONG

# From Laravel VPS
redis-cli -h 10.132.0.5 -p 6379 -a "YOUR_PASSWORD" INFO server
# Should show Redis server info
```

### Test 2: MySQL Connectivity

```bash
# From Laravel VPS
mysql -h 10.132.0.5 -u smartline_user -p smartline_production
# Enter password, should connect

# Run test query
SHOW TABLES;
```

### Test 3: Internal API Communication

```bash
# From Node VPS, test Laravel internal API
curl -H "X-API-Key: YOUR_INTERNAL_API_KEY" http://10.132.0.4/api/internal/health

# Should return JSON response
```

### Test 4: WebSocket from Public

```bash
# From your local machine
npm install -g wscat

# Connect to Node #1
wscat -c ws://<node1-public-ip>:3000

# Try authentication
> {"event":"ping"}
# Should receive pong response
```

### Test 5: Load Distribution

```bash
# Run this multiple times and check which server responds
curl http://ws.yourdomain.com/health

# Check PM2 logs on both Node servers to see distribution
# On Node #1:
pm2 logs smartline-realtime --lines 50

# On Node #2:
pm2 logs smartline-realtime --lines 50
```

---

## Deployment Checklist

### Pre-Deployment

- [ ] All VPS instances created with private networking
- [ ] Private IPs documented and configured in /etc/hosts
- [ ] SSH keys added to all servers
- [ ] Firewalls configured (UFW)
- [ ] Private network connectivity tested

### Data Layer (Redis+DB VPS)

- [ ] Redis installed and configured for remote access
- [ ] Redis firewall rules (private network only)
- [ ] MySQL installed and configured for remote access
- [ ] MySQL firewall rules (private network only)
- [ ] Database and users created
- [ ] Backups configured (daily)
- [ ] Monitoring script installed

### Laravel VPS

- [ ] PHP 8.2 installed with all extensions
- [ ] Composer installed
- [ ] Nginx installed and configured
- [ ] Laravel application deployed
- [ ] .env configured with private IPs for Redis/MySQL
- [ ] Migrations run successfully
- [ ] Permissions set correctly
- [ ] Supervisor configured for queue workers
- [ ] SSL certificate installed (Let's Encrypt)

### Node VPS (Both)

- [ ] Node.js v20.x installed
- [ ] PM2 installed
- [ ] Application deployed
- [ ] .env configured with private IPs
- [ ] PM2 configured and started
- [ ] PM2 startup script enabled
- [ ] Health endpoint responding
- [ ] Logs being written

### Load Balancing (Optional)

- [ ] Load balancer configured (Nginx or HAProxy)
- [ ] Health checks working
- [ ] SSL certificate installed
- [ ] DNS pointing to load balancer

### Security

- [ ] All servers updated (apt upgrade)
- [ ] Fail2ban installed on all servers
- [ ] SSH password authentication disabled
- [ ] Only necessary ports open
- [ ] Redis+DB only accessible via private network
- [ ] Strong passwords used for all services
- [ ] Credentials documented securely

### Testing

- [ ] Redis connectivity from Node and Laravel
- [ ] MySQL connectivity from Laravel
- [ ] Internal API calls working
- [ ] WebSocket connections working from public
- [ ] Load distribution working (if using load balancer)
- [ ] Backups running successfully
- [ ] Monitoring scripts working

---

## Environment Variables Summary

### Node VPS (.env)

```env
NODE_ENV=production
PORT=3000
HOST=0.0.0.0

REDIS_ENABLED=true
REDIS_HOST=10.132.0.5
REDIS_PASSWORD=<redis-password>
REDIS_PORT=6379

LARAVEL_API_URL=http://10.132.0.4
LARAVEL_API_KEY=<internal-api-key>

JWT_SECRET=<same-as-laravel>
WS_CORS_ORIGIN=*
```

### Laravel VPS (.env)

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com

DB_CONNECTION=mysql
DB_HOST=10.132.0.5
DB_PORT=3306
DB_DATABASE=smartline_production
DB_USERNAME=smartline_user
DB_PASSWORD=<mysql-password>

REDIS_HOST=10.132.0.5
REDIS_PASSWORD=<redis-password>
REDIS_PORT=6379

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

NODEJS_REALTIME_URL=http://10.132.0.2:3000
NODEJS_REALTIME_API_KEY=<internal-api-key>
```

---

## Next Steps

1. **Create VPS instances** on your provider
2. **Note down all IPs** (public and private)
3. **Follow deployment steps** in order (Data → Laravel → Node)
4. **Test connectivity** between all servers
5. **Configure monitoring** and backups
6. **Set up SSL certificates** for Laravel and WebSocket
7. **Configure load balancer** (optional)
8. **Run load tests** to verify performance
9. **Monitor for 48 hours** before full production launch

---

## Troubleshooting

### Can't Connect to Redis from Node/Laravel

```bash
# Check if Redis is listening on private IP
sudo netstat -tlnp | grep 6379

# Check firewall rules
sudo ufw status verbose

# Test from server
redis-cli -h 10.132.0.5 -p 6379 -a "PASSWORD" ping

# Check Redis logs
sudo tail -f /var/log/redis/redis-server.log
```

### Can't Connect to MySQL from Laravel

```bash
# Check if MySQL is listening on private IP
sudo netstat -tlnp | grep 3306

# Check MySQL users
mysql -u root -p
SELECT user, host FROM mysql.user;

# Test connection
mysql -h 10.132.0.5 -u smartline_user -p

# Check MySQL logs
sudo tail -f /var/log/mysql/error.log
```

### Node.js Service Not Starting

```bash
# Check PM2 logs
pm2 logs smartline-realtime --err

# Check Node.js can connect to Redis
node -e "const Redis = require('ioredis'); const r = new Redis({host:'10.132.0.5',port:6379,password:'PASSWORD'}); r.ping().then(console.log);"

# Restart PM2
pm2 restart smartline-realtime
pm2 save
```

### High Latency Between Servers

```bash
# Check private network connectivity
ping 10.132.0.5

# Check if traffic is going through private network
traceroute 10.132.0.5

# Verify servers are in same datacenter/region
```

---

## Cost Optimization Tips

1. **Start with smaller instances** - Scale up as needed
2. **Use Hetzner** - Cheapest provider with good performance (~50% less than DigitalOcean)
3. **Combine services initially** - Run Node #2 on Laravel VPS if budget is tight
4. **Reserved instances** - Get discounts for annual commitments
5. **Monitor resource usage** - Downsize if underutilized

**Minimal Budget Setup (~$40/month):**
- 1 VPS: Node.js (2GB)
- 1 VPS: Laravel (2GB)
- 1 VPS: Redis+MySQL (4GB)
- Total: ~$40/month

**Recommended Production Setup (~$85/month):**
- 2 VPS: Node.js (4GB each)
- 1 VPS: Laravel (4GB)
- 1 VPS: Redis+MySQL (8GB)
- Total: ~$85/month

**Enterprise Setup (~$200/month):**
- 3+ VPS: Node.js (8GB each)
- 2 VPS: Laravel (8GB each, load balanced)
- 1 VPS: Redis (16GB, with replica)
- 1 VPS: MySQL Master (16GB)
- 1 VPS: MySQL Replica (16GB)
- Total: ~$200-300/month

---

**This guide provides a complete multi-VPS deployment architecture for production. Follow the steps carefully and test thoroughly before going live.**