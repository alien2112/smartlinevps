# SmartLine Production Readiness (Hostinger, Multi-VPS)
Date: 2025-12-18

Goal: run SmartLine with two Node.js real-time servers, one Laravel API server, and one data server (MySQL + Redis) on Hostinger VPS. This document focuses on a reproducible build, secure network layout, and operational readiness.

## Target Topology
- node1: WebSocket real-time service (`realtime-service/`), public WebSocket endpoint, connects privately to Laravel + Redis.
- node2: Same as node1 for redundancy and load sharing.
- api: Laravel application (this repo root), public HTTPS API, private access to Redis/MySQL, internal API key shared with Node.
- data: MySQL 8 + Redis 7, no public exposure except controlled SSH.
- Optional LB: DNS round-robin for WebSocket, or Nginx/HAProxy terminator in front of node1/node2.

## Recommended Hostinger VPS Sizes
- node1 and node2: 4GB RAM, 2 vCPU, 40GB SSD, Ubuntu 22.04 LTS.
- api: 4GB RAM, 2 vCPU, 60GB SSD, Ubuntu 22.04 LTS.
- data: 8GB RAM, 4 vCPU, 120GB SSD, Ubuntu 22.04 LTS, daily snapshots enabled.

## Host Naming and IP Plan (example)
| Hostname | Role | Public ports | Notes |
| --- | --- | --- | --- |
| node1.smartline | Node.js real-time | 22, 80/443 (if terminating TLS), 3000 (if exposed) | Prefer LB or proxy in front of 3000 |
| node2.smartline | Node.js real-time | same as node1 | Same config as node1 |
| api.smartline | Laravel API | 22, 80, 443 | Only API and queue workers run here |
| data.smartline | MySQL + Redis | 22 (allowlisted), 3306 (allowlisted), 6379 (allowlisted) | No public exposure beyond allowlist |

If Hostinger private networking is available in your plan, attach all VPSs to the same region/network and use the private IPs for MySQL/Redis/API calls. If not, strictly allowlist traffic with UFW and consider a lightweight mesh VPN (WireGuard/ZeroTier) for private links.

## Base Build (all servers)
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y fail2ban ufw chrony unattended-upgrades
sudo dpkg-reconfigure -plow unattended-upgrades
# Add a deploy user
sudo adduser deploy && sudo usermod -aG sudo deploy
# SSH hardening: disable root/password login after keys are confirmed
```

Suggested UFW rules:
- node1/node2: allow 22 (from admin IPs), allow 80/443 (or 3000) from internet or LB, deny others.
- api: allow 22 (from admin IPs), allow 80/443 from internet, allow 3306/6379 only from data (if needed), deny others.
- data: allow 22 from admin IPs, allow 3306/6379 only from api/node1/node2 IPs, deny others.

## Data Server (MySQL + Redis)
```bash
sudo apt install -y mysql-server redis-server
# MySQL bind to private IP (or 0.0.0.0 with UFW allowlist only)
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
# set: bind-address = <data-private-ip>, max_connections=500, innodb_buffer_pool_size=4G
sudo systemctl restart mysql

# Create database and users
sudo mysql -uroot -p
CREATE DATABASE smartline_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'smartline_app'@'<api-or-private-subnet>' IDENTIFIED BY '<strong-password>';
GRANT ALL PRIVILEGES ON smartline_production.* TO 'smartline_app'@'<api-or-private-subnet>';
FLUSH PRIVILEGES;

# Redis config
sudo cp /etc/redis/redis.conf /etc/redis/redis.conf.bak
# set: bind 127.0.0.1 <data-private-ip>, requirepass <strong-password>, maxmemory 4gb, maxmemory-policy volatile-ttl
sudo systemctl restart redis-server
```

Backups and durability:
- Nightly `mysqldump` with gzip, keep 7 days locally, push to remote/object storage if available.
- Enable Hostinger snapshots for the data VPS.
- Turn on MySQL slow query log and review weekly.

## Laravel Server (api)
System packages:
```bash
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.2 php8.2-fpm php8.2-cli php8.2-mysql php8.2-redis php8.2-mbstring \
  php8.2-xml php8.2-curl php8.2-zip php8.2-gd php8.2-bcmath php8.2-intl php8.2-soap php8.2-imagick
sudo apt install -y nginx supervisor
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
```

Deploy code (adjust path as needed):
```bash
sudo mkdir -p /var/www/smartline && sudo chown -R $USER:$USER /var/www/smartline
cd /var/www/smartline
# upload or git clone the repo here
composer install --no-dev --optimize-autoloader
cp .env.example .env
```

Key `.env` entries:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com

DB_CONNECTION=mysql
DB_HOST=<data-private-ip-or-allowlisted-ip>
DB_PORT=3306
DB_DATABASE=smartline_production
DB_USERNAME=smartline_app
DB_PASSWORD=<mysql-password>

REDIS_HOST=<data-private-ip-or-allowlisted-ip>
REDIS_PORT=6379
REDIS_PASSWORD=<redis-password>

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

INTERNAL_API_KEY=<strong-internal-key>
NODEJS_REALTIME_URL=http://<node1-private-ip>:3000
```

Finalize:
```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force    # if needed
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo chown -R www-data:www-data /var/www/smartline
sudo chmod -R 775 storage bootstrap/cache
```

Nginx site (basic):
```nginx
server {
    listen 80;
    server_name api.yourdomain.com;
    root /var/www/smartline/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
```
Enable the site, run `nginx -t`, then `sudo systemctl reload nginx`. Use Certbot for TLS (`sudo apt install certbot python3-certbot-nginx && sudo certbot --nginx -d api.yourdomain.com`).

Queue workers (Supervisor):
```ini
[program:smartline-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/smartline/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
numprocs=4
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/smartline/storage/logs/worker.log
stopwaitsecs=3600
```
`sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start smartline-worker:*`

## Node.js Servers (node1 and node2)
Install runtime:
```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs build-essential
sudo npm install -g pm2
```

Deploy service:
```bash
sudo mkdir -p /var/www/realtime-service && sudo chown -R $USER:$USER /var/www/realtime-service
cd /var/www/realtime-service
# upload or git clone only the realtime-service directory here
npm ci --omit=dev   # or npm install --production
cp .env.example .env
```

Key `.env` entries:
```env
NODE_ENV=production
PORT=3000
HOST=0.0.0.0

REDIS_HOST=<data-private-ip-or-allowlisted-ip>
REDIS_PORT=6379
REDIS_PASSWORD=<redis-password>

LARAVEL_API_URL=http://<api-private-ip>
LARAVEL_API_KEY=<same-as-INTERNAL_API_KEY>
JWT_SECRET=<same-secret-used-by-Laravel>

WS_CORS_ORIGIN=https://app.yourdomain.com
CLUSTER_MODE=true
WORKER_PROCESSES=2
```

Run with PM2:
```bash
cd /var/www/realtime-service
pm2 start ecosystem.config.js --env production
pm2 save
pm2 startup systemd   # follow printed command
pm2 status
pm2 logs smartline-realtime
```

Network:
- Expose port 3000 only to the LB or allowlisted client IP ranges.
- If you want TLS termination on each Node, run Nginx as a reverse proxy that terminates HTTPS/WSS and forwards to localhost:3000.

## Load Balancing for WebSocket
- Simple: DNS round robin on `ws.yourdomain.com` pointing to node1 and node2 public IPs.
- Better: Nginx/HAProxy (on api server or a small LB VPS) with `ip_hash` for sticky sessions and health checks. Example Nginx upstream:
```nginx
upstream websocket_backend {
    ip_hash;
    server <node1-private-or-public-ip>:3000 max_fails=3 fail_timeout=30s;
    server <node2-private-or-public-ip>:3000 max_fails=3 fail_timeout=30s;
}
server {
    listen 443 ssl;
    server_name ws.yourdomain.com;
    ssl_certificate /etc/letsencrypt/live/ws.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/ws.yourdomain.com/privkey.pem;
    location / {
        proxy_pass http://websocket_backend;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $remote_addr;
        proxy_read_timeout 3600;
    }
}
```

## Monitoring and Ops
- PM2: `pm2 monit`, `pm2 logs smartline-realtime`.
- Laravel: `storage/logs/laravel.log`, `supervisorctl status`.
- Redis: `redis-cli -h <data-ip> -a <password> info server`, set up `maxmemory` alerts.
- MySQL: enable slow query log, `mysqladmin status`, watch `information_schema.processlist`.
- System: `htop`, `df -h`, `journalctl -u nginx|php8.2-fpm|redis-server|mysql`.
- Backups: verify nightly dumps and Hostinger snapshots restore.
- Update cadence: weekly `sudo apt update && sudo apt upgrade -y` on a staggered schedule per host.

## Validation Checklist (run before go-live)
- Data:
  - `mysql -h <data-ip> -u smartline_app -p smartline_production -e "SHOW TABLES;"` from api host.
  - `redis-cli -h <data-ip> -a <password> ping` from node1/node2/api.
- Laravel:
  - `curl -I https://api.yourdomain.com/health` returns 200.
  - `php artisan config:cache` succeeds; `supervisorctl status` shows workers running.
- Node:
  - `curl http://<node-ip>:3000/health` returns ok.
  - WebSocket connect test via `wscat -c "ws://ws.yourdomain.com?token=<jwt>"`.
- Load balance:
  - Requests rotate across node1/node2; fail one node and confirm traffic shifts.
- Security:
  - UFW rules match the matrix; SSH root login disabled; fail2ban active.
- Backups:
  - Restore a recent MySQL dump to a staging schema; verify Redis persistence works.

## Rollout and Recovery
- Deploy order: data -> api -> node1/node2 -> LB/DNS.
- Keep `.env` files versioned as secrets (not in git) and stored in Hostinger secret vault or encrypted storage.
- During deploys use `pm2 reload smartline-realtime` (zero downtime) and `php artisan down`/`up` for brief API maintenance windows if needed.
- Have rollback package ready: latest DB snapshot and previous app build tarballs for api/node servers.

## Production Go/No-Go Gates
- All health checks green, SSL valid, and latency between VPSs acceptable.
- No unexpected 4xx/5xx in Laravel logs; Redis and MySQL under target CPU/memory.
- Backups verified within last 24 hours.
- Monitoring/alerts configured (at least email/Telegram for PM2 restarts, disk usage, MySQL down).
- Stakeholders informed of cutover window and rollback plan.

Use this document as the single source for Hostinger production setup; update IPs, domains, and secrets per environment.
