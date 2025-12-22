# Production Readiness Review (Hostinger, Multi-VPS)
Date: 2025-12-18

Scope: refreshed readiness check after recent changes. Targets Hostinger with 2x Node.js realtime, 1x Laravel API, 1x DB+Redis. Includes setup commands per host and current blockers to fix before go-live.

## Target Topology
- node1 / node2: `realtime-service` (WebSocket + matching) on port 3000 behind TLS/LB.
- api: Laravel app + queue workers, HTTPS on 443.
- data: MySQL 8 + Redis 7, private-only.
- Optional LB: Nginx/HAProxy or DNS RR on `ws.yourdomain.com`.

## Host Sizing (recommended)
- node1/node2: 4GB RAM, 2 vCPU, 40GB SSD, Ubuntu 22.04.
- api: 4GB RAM, 2 vCPU, 60GB SSD, Ubuntu 22.04.
- data: 8GB RAM, 4 vCPU, 120GB SSD, Ubuntu 22.04 + daily snapshots.

## Base OS Prep (all hosts)
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y fail2ban ufw chrony unattended-upgrades htop curl git
sudo dpkg-reconfigure -plow unattended-upgrades
sudo adduser deploy && sudo usermod -aG sudo deploy
# SSH hardening after key access is verified:
#   PasswordAuthentication no
#   PermitRootLogin no
sudo systemctl restart ssh
```

## Data Host (MySQL 8 + Redis 7)
```bash
sudo apt install -y mysql-server redis-server

# MySQL bind and tuning
sudo cp /etc/mysql/mysql.conf.d/mysqld.cnf /etc/mysql/mysql.conf.d/mysqld.cnf.bak
sudo sed -i "s/^bind-address.*/bind-address = <data-private-ip>/" /etc/mysql/mysql.conf.d/mysqld.cnf
cat <<'EOF' | sudo tee -a /etc/mysql/mysql.conf.d/mysqld.cnf
max_connections = 500
innodb_buffer_pool_size = 4G
EOF
sudo systemctl restart mysql

# Create DB and user
sudo mysql -uroot -p <<'SQL'
CREATE DATABASE smartline_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'smartline_app'@'10.%' IDENTIFIED BY 'REPLACE_ME_STRONG';
GRANT ALL PRIVILEGES ON smartline_production.* TO 'smartline_app'@'10.%';
FLUSH PRIVILEGES;
SQL

# Redis config
sudo cp /etc/redis/redis.conf /etc/redis/redis.conf.bak
sudo sed -i "s/^bind .*/bind 127.0.0.1 <data-private-ip>/" /etc/redis/redis.conf
sudo sed -i "s/^# requirepass .*/requirepass REPLACE_ME_STRONG/" /etc/redis/redis.conf
echo "maxmemory 4gb" | sudo tee -a /etc/redis/redis.conf
echo "maxmemory-policy volatile-ttl" | sudo tee -a /etc/redis/redis.conf
sudo systemctl restart redis-server

# Firewall (tighten with your IPs)
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow from <admin-ip>/32 to any port 22 proto tcp
sudo ufw allow from 10.0.0.0/8 to any port 3306 proto tcp
sudo ufw allow from 10.0.0.0/8 to any port 6379 proto tcp
sudo ufw enable
sudo ufw status verbose
```

## Laravel Host (api)
```bash
# PHP/Nginx/Composer
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.2 php8.2-fpm php8.2-cli php8.2-mysql php8.2-redis \
  php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-gd php8.2-bcmath \
  php8.2-intl php8.2-soap php8.2-imagick nginx supervisor
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Deploy app
sudo mkdir -p /var/www/smartline && sudo chown -R $USER:$USER /var/www/smartline
cd /var/www/smartline
# git clone or upload code here
composer install --no-dev --optimize-autoloader
cp .env.example .env

# .env essentials (fill real values)
cat <<'ENV' > .env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com
DB_CONNECTION=mysql
DB_HOST=<data-private-ip>
DB_PORT=3306
DB_DATABASE=smartline_production
DB_USERNAME=smartline_app
DB_PASSWORD=REPLACE_ME_STRONG
REDIS_HOST=<data-private-ip>
REDIS_PORT=6379
REDIS_PASSWORD=REPLACE_ME_STRONG
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
INTERNAL_API_KEY=REPLACE_ME_STRONG
NODEJS_REALTIME_URL=http://<node1-private-ip>:3000
JWT_SECRET=<shared-with-node>
ENV

php artisan key:generate
php artisan migrate --force
# php artisan db:seed --force  # if needed
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo chown -R www-data:www-data /var/www/smartline
sudo chmod -R 775 storage bootstrap/cache

# Nginx vhost
cat <<'NGINX' | sudo tee /etc/nginx/sites-available/smartline
server {
    listen 80;
    server_name api.yourdomain.com;
    root /var/www/smartline/public;
    index index.php;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
NGINX
sudo ln -s /etc/nginx/sites-available/smartline /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
# TLS: sudo apt install -y certbot python3-certbot-nginx && sudo certbot --nginx -d api.yourdomain.com

# Queue workers
cat <<'SUP' | sudo tee /etc/supervisor/conf.d/smartline-worker.conf
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
SUP
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start smartline-worker:*

# Firewall
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow from <admin-ip>/32 to any port 22 proto tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

## Node Hosts (node1 and node2)
```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs build-essential
sudo npm install -g pm2

sudo mkdir -p /var/www/realtime-service && sudo chown -R $USER:$USER /var/www/realtime-service
cd /var/www/realtime-service
# git clone or upload realtime-service here
npm ci --omit=dev   # or npm install --production
cp .env.example .env

cat <<'ENV' > .env
NODE_ENV=production
PORT=3000
HOST=0.0.0.0
REDIS_HOST=<data-private-ip>
REDIS_PORT=6379
REDIS_PASSWORD=REPLACE_ME_STRONG
LARAVEL_API_URL=http://<api-private-ip>
LARAVEL_API_KEY=REPLACE_ME_STRONG     # matches INTERNAL_API_KEY
JWT_SECRET=<shared-with-laravel>
WS_CORS_ORIGIN=https://app.yourdomain.com
CLUSTER_MODE=true
WORKER_PROCESSES=2
ENV

pm2 start ecosystem.config.js --env production
pm2 save
pm2 startup systemd  # run the printed command
pm2 status

# Firewall
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow from <admin-ip>/32 to any port 22 proto tcp
# If LB terminates TLS in front, allow only LB IP to 3000
sudo ufw allow from <lb-ip-or-0.0.0.0/0> to any port 3000 proto tcp
sudo ufw enable
```

## Optional WebSocket LB (Nginx example)
```nginx
upstream websocket_backend {
    ip_hash;
    server <node1-ip>:3000 max_fails=3 fail_timeout=30s;
    server <node2-ip>:3000 max_fails=3 fail_timeout=30s;
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

## Health and Validation
- Data: `redis-cli -h <data-ip> -a <pw> ping`; `mysql -h <data-ip> -u smartline_app -p smartline_production -e "SELECT 1;"`
- Laravel: `curl -I https://api.yourdomain.com/health`; `supervisorctl status`; check `storage/logs/laravel.log`.
- Node: `curl http://<node-ip>:3000/health`; `pm2 status`; WebSocket via `wscat -c "ws://ws.yourdomain.com?token=<jwt>"`.
- LB: kill node1 process and confirm traffic still flows via node2.

## Current Blockers (must fix before production)
P0 (blockers):
- Trip assignment race conditions: add DB transaction + locking / unique constraint to prevent double-accept; idempotent accept endpoint.
- Payment webhooks: verify signatures, store gateway event IDs, ensure idempotent processing and retries.
- Secrets: rotate all exposed keys, remove from repo, enforce env-only; audit configs.
- Rate limiting: apply to auth, OTP, trip creation, bidding, WebSocket critical actions.
- Indexes: deploy missing composite indexes (e.g., `trip_requests(zone_id,current_status)`, `user_last_locations(zone_id,type)`, payment/transactions by user/date).
- WebSocket connection cleanup: heartbeat/ping + onClose to mark drivers offline and clear stale sessions.

P1 (next):
- Spatial/location storage: move to POINT + SPATIAL INDEX or Redis GEO; cap driver search fanout.
- Cache/queue: ensure Redis used for cache/session/queue; workers running and monitored.
- Internal API contract: enforce `INTERNAL_API_KEY` on callbacks; align JWT secret between Laravel/Node; restrict API/Redis/MySQL to private IPs only.
- Logging/monitoring: structured JSON logs, health endpoints, error tracking (Sentry), slow query log on MySQL; backup and restore test.

## Cutover Checklist
- All env files filled with real secrets; TLS valid on api/ws.
- UFW rules match IP plan; root SSH disabled; fail2ban active.
- DB backups and Redis persistence verified; snapshot ready.
- Health endpoints green; load test at expected RPS; no double-assign incidents in staging.
- PM2 saved and auto-start enabled; Supervisor workers healthy.
- Rollback artifacts: latest DB snapshot + previous app/node builds.

Use this doc as the current readiness and setup reference; update placeholders with your real IPs/domains/secrets and execute in the order: data -> api -> node1/node2 -> LB/DNS -> validation.
