#!/bin/bash
# =============================================================================
# SmartLine 2-VPS Setup Script - VPS2 (Data + Realtime Server)
# =============================================================================
# This script sets up VPS2 with MySQL, Redis, and Node.js
#
# PREREQUISITES:
#   1. Fresh Ubuntu 22.04 LTS server
#   2. Root access
#   3. Private network configured with VPS1
#   4. Database backup file from VPS1 (optional, can import later)
#
# USAGE:
#   sudo ./05-vps2-setup.sh
#
# =============================================================================

set -e  # Exit on error

# =============================================================================
# CONFIGURATION - MODIFY THESE VALUES
# =============================================================================
VPS1_PRIVATE_IP="${VPS1_PRIVATE_IP:-10.132.0.10}"
VPS2_PRIVATE_IP="${VPS2_PRIVATE_IP:-10.132.0.11}"

# MySQL Configuration
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-}"
MYSQL_APP_USER="${MYSQL_APP_USER:-smartline_app}"
MYSQL_APP_PASSWORD="${MYSQL_APP_PASSWORD:-}"
MYSQL_DATABASE="${MYSQL_DATABASE:-merged2}"

# Redis Configuration (optimized for 16GB VPS)
REDIS_PASSWORD="${REDIS_PASSWORD:-}"
REDIS_MAX_MEMORY="${REDIS_MAX_MEMORY:-3gb}"

# Node.js Configuration
NODEJS_VERSION="${NODEJS_VERSION:-20}"
NODEJS_PORT="${NODEJS_PORT:-3002}"
NODEJS_PATH="/var/www/realtime-service"

# =============================================================================
# COLORS AND FUNCTIONS
# =============================================================================
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_header() {
    echo ""
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}"
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_info() {
    echo -e "${YELLOW}ℹ${NC} $1"
}

confirm_action() {
    read -p "$(echo -e ${YELLOW}$1 [y/N]: ${NC})" response
    case "$response" in
        [yY][eE][sS]|[yY]) return 0 ;;
        *) return 1 ;;
    esac
}

generate_password() {
    openssl rand -base64 32 | tr -d '/+=' | head -c 32
}

# =============================================================================
# PRE-FLIGHT CHECKS
# =============================================================================
print_header "PRE-FLIGHT CHECKS"

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    print_error "Please run as root or with sudo"
    exit 1
fi
print_success "Running as root"

# Check OS
if [ -f /etc/os-release ]; then
    . /etc/os-release
    print_info "OS: $PRETTY_NAME"
else
    print_warning "Could not determine OS version"
fi

# Check available memory
TOTAL_MEM=$(free -g | awk '/^Mem:/{print $2}')
print_info "Total RAM: ${TOTAL_MEM}GB"
if [ "$TOTAL_MEM" -lt 8 ]; then
    print_warning "Less than 8GB RAM detected. Recommended: 12GB+"
    if ! confirm_action "Continue anyway?"; then
        exit 1
    fi
fi

# =============================================================================
# STEP 1: GATHER CONFIGURATION
# =============================================================================
print_header "STEP 1: CONFIGURATION"

# VPS1 Private IP
if [ -z "$VPS1_PRIVATE_IP" ] || [ "$VPS1_PRIVATE_IP" = "10.132.0.10" ]; then
    read -p "Enter VPS1 private IP address [$VPS1_PRIVATE_IP]: " input
    VPS1_PRIVATE_IP="${input:-$VPS1_PRIVATE_IP}"
fi
print_info "VPS1 Private IP: $VPS1_PRIVATE_IP"

# VPS2 Private IP (this server)
current_private_ip=$(ip addr | grep 'inet 10\.' | awk '{print $2}' | cut -d'/' -f1 | head -1)
if [ -n "$current_private_ip" ]; then
    VPS2_PRIVATE_IP="$current_private_ip"
fi
print_info "VPS2 Private IP: $VPS2_PRIVATE_IP"

# MySQL Root Password
if [ -z "$MYSQL_ROOT_PASSWORD" ]; then
    print_info "Generating MySQL root password..."
    MYSQL_ROOT_PASSWORD=$(generate_password)
    print_warning "MySQL Root Password: $MYSQL_ROOT_PASSWORD"
    print_warning "SAVE THIS PASSWORD SECURELY!"
fi

# MySQL App Password
if [ -z "$MYSQL_APP_PASSWORD" ]; then
    print_info "Generating MySQL app user password..."
    MYSQL_APP_PASSWORD=$(generate_password)
    print_warning "MySQL App Password ($MYSQL_APP_USER): $MYSQL_APP_PASSWORD"
    print_warning "SAVE THIS PASSWORD SECURELY!"
fi

# Redis Password
if [ -z "$REDIS_PASSWORD" ]; then
    print_info "Generating Redis password..."
    REDIS_PASSWORD=$(generate_password)
    print_warning "Redis Password: $REDIS_PASSWORD"
    print_warning "SAVE THIS PASSWORD SECURELY!"
fi

# Save credentials to file
CREDS_FILE="/root/smartline-credentials.txt"
cat > "$CREDS_FILE" << EOF
# SmartLine VPS2 Credentials
# Generated: $(date)
# KEEP THIS FILE SECURE!

VPS1_PRIVATE_IP=$VPS1_PRIVATE_IP
VPS2_PRIVATE_IP=$VPS2_PRIVATE_IP

# MySQL
MYSQL_ROOT_PASSWORD=$MYSQL_ROOT_PASSWORD
MYSQL_APP_USER=$MYSQL_APP_USER
MYSQL_APP_PASSWORD=$MYSQL_APP_PASSWORD
MYSQL_DATABASE=$MYSQL_DATABASE

# Redis
REDIS_PASSWORD=$REDIS_PASSWORD

# Node.js
NODEJS_PORT=$NODEJS_PORT
EOF
chmod 600 "$CREDS_FILE"
print_success "Credentials saved to $CREDS_FILE"

echo ""
if ! confirm_action "Continue with setup?"; then
    exit 0
fi

# =============================================================================
# STEP 2: UPDATE SYSTEM
# =============================================================================
print_header "STEP 2: SYSTEM UPDATE"

print_info "Updating package lists..."
apt update -y

print_info "Upgrading packages..."
apt upgrade -y

print_info "Installing essential packages..."
apt install -y curl wget git unzip htop net-tools ufw

print_success "System updated"

# =============================================================================
# STEP 3: CONFIGURE HOSTS FILE
# =============================================================================
print_header "STEP 3: CONFIGURE HOSTS FILE"

# Add host entries
if ! grep -q "app-server" /etc/hosts; then
    echo "$VPS1_PRIVATE_IP  app-server smartline-app vps1" >> /etc/hosts
    print_success "Added VPS1 to hosts file"
fi

if ! grep -q "data-server" /etc/hosts; then
    echo "$VPS2_PRIVATE_IP  data-server smartline-data vps2" >> /etc/hosts
    print_success "Added VPS2 to hosts file"
fi

cat /etc/hosts | grep -E "app-server|data-server"

# =============================================================================
# STEP 4: INSTALL MYSQL
# =============================================================================
print_header "STEP 4: INSTALL MYSQL"

# Install MySQL
print_info "Installing MySQL server..."
apt install -y mysql-server

# Secure MySQL installation
print_info "Configuring MySQL security..."

# Set root password
mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '$MYSQL_ROOT_PASSWORD';"

# Remove anonymous users
mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "DELETE FROM mysql.user WHERE User='';"

# Remove test database
mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "DROP DATABASE IF EXISTS test;"
mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';"

# Reload privileges
mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "FLUSH PRIVILEGES;"

print_success "MySQL installed and secured"

# =============================================================================
# STEP 5: CONFIGURE MYSQL FOR REMOTE ACCESS
# =============================================================================
print_header "STEP 5: CONFIGURE MYSQL FOR REMOTE ACCESS"

# Backup original config
cp /etc/mysql/mysql.conf.d/mysqld.cnf /etc/mysql/mysql.conf.d/mysqld.cnf.backup

# Configure MySQL
cat > /etc/mysql/mysql.conf.d/mysqld.cnf << EOF
[mysqld]
# Connection Settings
bind-address = $VPS2_PRIVATE_IP
port = 3306
max_connections = 300
max_connect_errors = 1000
wait_timeout = 600
interactive_timeout = 600

# InnoDB Settings (optimized for ${TOTAL_MEM}GB RAM)
innodb_buffer_pool_size = $((TOTAL_MEM / 2))G
innodb_buffer_pool_instances = 4
innodb_log_file_size = 512M
innodb_log_buffer_size = 64M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
innodb_file_per_table = 1

# Query Cache (disabled for MySQL 8)
# query_cache_type = 0
# query_cache_size = 0

# Logging
slow_query_log = 1
slow_query_log_file = /var/log/mysql/mysql-slow.log
long_query_time = 2

# Thread Settings
thread_cache_size = 50
table_open_cache = 2000
table_definition_cache = 2000

# Temporary Tables
tmp_table_size = 256M
max_heap_table_size = 256M

# Binary Logging (for potential replication)
server-id = 2
log_bin = /var/log/mysql/mysql-bin
binlog_expire_logs_seconds = 604800
max_binlog_size = 100M

# Character Set
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci

# Data Directory
datadir = /var/lib/mysql
socket = /var/run/mysqld/mysqld.sock
pid-file = /var/run/mysqld/mysqld.pid
EOF

print_success "MySQL configuration updated"

# Restart MySQL
print_info "Restarting MySQL..."
systemctl restart mysql
print_success "MySQL restarted"

# =============================================================================
# STEP 6: CREATE DATABASE AND USER
# =============================================================================
print_header "STEP 6: CREATE DATABASE AND USER"

# Create database
print_info "Creating database $MYSQL_DATABASE..."
mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS $MYSQL_DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
print_success "Database created"

# Create user for VPS1 access
print_info "Creating user $MYSQL_APP_USER for VPS1 access..."
mysql -u root -p"$MYSQL_ROOT_PASSWORD" << EOF
CREATE USER IF NOT EXISTS '$MYSQL_APP_USER'@'$VPS1_PRIVATE_IP' IDENTIFIED WITH mysql_native_password BY '$MYSQL_APP_PASSWORD';
GRANT ALL PRIVILEGES ON $MYSQL_DATABASE.* TO '$MYSQL_APP_USER'@'$VPS1_PRIVATE_IP';
FLUSH PRIVILEGES;
EOF
print_success "User created with access from $VPS1_PRIVATE_IP"

# =============================================================================
# STEP 7: INSTALL REDIS
# =============================================================================
print_header "STEP 7: INSTALL REDIS"

print_info "Installing Redis..."
apt install -y redis-server

# Backup original config
cp /etc/redis/redis.conf /etc/redis/redis.conf.backup

# Configure Redis
print_info "Configuring Redis..."
cat > /etc/redis/redis.conf << EOF
# Network
bind 127.0.0.1 $VPS2_PRIVATE_IP
port 6379
protected-mode yes

# Security
requirepass $REDIS_PASSWORD

# Memory Management
maxmemory $REDIS_MAX_MEMORY
maxmemory-policy volatile-ttl

# Persistence
appendonly yes
appendfsync everysec
auto-aof-rewrite-percentage 100
auto-aof-rewrite-min-size 64mb

# RDB Snapshots
save 900 1
save 300 10
save 60 10000

# Logging
loglevel notice
logfile /var/log/redis/redis-server.log

# General
daemonize yes
supervised systemd
pidfile /var/run/redis/redis-server.pid
dir /var/lib/redis
dbfilename dump.rdb

# Connection
tcp-keepalive 300
timeout 0

# Clients
maxclients 10000
EOF

print_success "Redis configuration updated"

# Restart Redis
print_info "Restarting Redis..."
systemctl restart redis-server
print_success "Redis restarted"

# Verify Redis
print_info "Verifying Redis..."
if redis-cli -a "$REDIS_PASSWORD" ping 2>/dev/null | grep -q "PONG"; then
    print_success "Redis is responding"
else
    print_error "Redis verification failed"
fi

# =============================================================================
# STEP 8: INSTALL NODE.JS
# =============================================================================
print_header "STEP 8: INSTALL NODE.JS"

print_info "Installing Node.js $NODEJS_VERSION..."
curl -fsSL https://deb.nodesource.com/setup_${NODEJS_VERSION}.x | bash -
apt install -y nodejs

# Verify Node.js
node_version=$(node --version)
npm_version=$(npm --version)
print_success "Node.js $node_version installed"
print_success "npm $npm_version installed"

# Install PM2
print_info "Installing PM2..."
npm install -g pm2
print_success "PM2 installed"

# Setup PM2 startup
print_info "Configuring PM2 startup..."
pm2 startup systemd -u root --hp /root
print_success "PM2 startup configured"

# =============================================================================
# STEP 9: PREPARE NODE.JS APPLICATION DIRECTORY
# =============================================================================
print_header "STEP 9: PREPARE NODE.JS APPLICATION"

mkdir -p "$NODEJS_PATH"
mkdir -p "$NODEJS_PATH/logs"

# Create placeholder .env file
cat > "$NODEJS_PATH/.env" << EOF
# SmartLine Realtime Service Configuration
# Update these values after copying the application

NODE_ENV=production
PORT=$NODEJS_PORT
HOST=0.0.0.0

# Redis (local)
REDIS_ENABLED=true
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=$REDIS_PASSWORD
REDIS_PORT=6379

# Laravel API (VPS1)
LARAVEL_API_URL=http://$VPS1_PRIVATE_IP
LARAVEL_API_KEY=smartline-internal-key-change-in-production
VALIDATE_WITH_LARAVEL=true

# JWT Secret (copy from Laravel .env)
JWT_SECRET=COPY_FROM_LARAVEL_ENV

# WebSocket
WS_CORS_ORIGIN=*
WS_PING_TIMEOUT=60000
WS_PING_INTERVAL=25000

# Logging
LOG_LEVEL=info
EOF

print_success "Node.js directory prepared at $NODEJS_PATH"
print_info "Copy the realtime-service application from VPS1 to: $NODEJS_PATH"

# Create PM2 ecosystem config
cat > "$NODEJS_PATH/ecosystem.config.js" << EOF
module.exports = {
  apps: [{
    name: 'smartline-realtime',
    script: './src/server.js',
    instances: 2,
    exec_mode: 'cluster',
    env: {
      NODE_ENV: 'production',
      PORT: $NODEJS_PORT
    },
    error_file: './logs/err.log',
    out_file: './logs/out.log',
    log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
    max_memory_restart: '600M',  // For 16GB VPS
    watch: false,
    autorestart: true,
    restart_delay: 1000,
    max_restarts: 10
  }]
};
EOF

print_success "PM2 ecosystem config created"

# =============================================================================
# STEP 10: CONFIGURE FIREWALL
# =============================================================================
print_header "STEP 10: CONFIGURE FIREWALL"

print_info "Configuring UFW firewall..."

# Reset UFW
ufw --force reset

# Default policies
ufw default deny incoming
ufw default allow outgoing

# SSH (from VPS1 only for extra security, or open for all)
if confirm_action "Restrict SSH to VPS1 IP only? (More secure but requires VPS1 access)"; then
    ufw allow from $VPS1_PRIVATE_IP to any port 22 proto tcp
    print_info "SSH restricted to $VPS1_PRIVATE_IP"
else
    ufw allow 22/tcp
    print_info "SSH open from all IPs"
fi

# MySQL (private network only)
ufw allow from $VPS1_PRIVATE_IP to any port 3306 proto tcp
print_info "MySQL: $VPS1_PRIVATE_IP -> 3306"

# Redis (private network only)
ufw allow from $VPS1_PRIVATE_IP to any port 6379 proto tcp
print_info "Redis: $VPS1_PRIVATE_IP -> 6379"

# Node.js WebSocket (public or private based on setup)
if confirm_action "Open WebSocket port $NODEJS_PORT to public? (Yes for direct connection, No if proxying through VPS1)"; then
    ufw allow $NODEJS_PORT/tcp
    print_info "WebSocket: Public -> $NODEJS_PORT"
else
    ufw allow from $VPS1_PRIVATE_IP to any port $NODEJS_PORT proto tcp
    print_info "WebSocket: $VPS1_PRIVATE_IP -> $NODEJS_PORT"
fi

# Enable firewall
ufw --force enable
print_success "Firewall configured and enabled"

# Show status
ufw status verbose

# =============================================================================
# STEP 11: INSTALL NGINX (OPTIONAL)
# =============================================================================
print_header "STEP 11: NGINX (OPTIONAL)"

if confirm_action "Install Nginx for WebSocket SSL termination?"; then
    apt install -y nginx

    # Create WebSocket proxy config
    cat > /etc/nginx/sites-available/smartline-websocket << EOF
server {
    listen 3000 ssl http2;
    server_name _;

    # SSL certificates (copy from VPS1 or use Let's Encrypt)
    # ssl_certificate /etc/ssl/certs/smartline.crt;
    # ssl_certificate_key /etc/ssl/private/smartline.key;

    # For testing without SSL, comment out the ssl lines above and use:
    # listen 3000;

    location / {
        proxy_pass http://127.0.0.1:$NODEJS_PORT;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;

        # WebSocket specific
        proxy_connect_timeout 7d;
        proxy_send_timeout 7d;
        proxy_read_timeout 7d;
        proxy_buffering off;
    }

    location /health {
        proxy_pass http://127.0.0.1:$NODEJS_PORT/health;
        proxy_http_version 1.1;
    }
}
EOF

    print_success "Nginx WebSocket config created"
    print_warning "Configure SSL certificates before enabling this config"
fi

# =============================================================================
# STEP 12: CREATE BACKUP SCRIPT
# =============================================================================
print_header "STEP 12: CREATE BACKUP SCRIPT"

cat > /usr/local/bin/smartline-backup.sh << 'EOF'
#!/bin/bash
# SmartLine Database Backup Script

BACKUP_DIR="/var/backups/smartline"
DATE=$(date +%Y%m%d_%H%M%S)
MYSQL_USER="root"
MYSQL_PASSWORD="REPLACE_WITH_ROOT_PASSWORD"
DATABASE="merged2"
RETENTION_DAYS=7

mkdir -p $BACKUP_DIR

# Backup database
echo "Creating backup..."
mysqldump -u $MYSQL_USER -p"$MYSQL_PASSWORD" --single-transaction --quick \
  $DATABASE > $BACKUP_DIR/smartline-$DATE.sql

# Compress
gzip $BACKUP_DIR/smartline-$DATE.sql

# Delete old backups
find $BACKUP_DIR -name "*.sql.gz" -mtime +$RETENTION_DAYS -delete

echo "Backup completed: smartline-$DATE.sql.gz"
ls -lh $BACKUP_DIR/
EOF

# Update with actual password
sed -i "s/REPLACE_WITH_ROOT_PASSWORD/$MYSQL_ROOT_PASSWORD/" /usr/local/bin/smartline-backup.sh
chmod 700 /usr/local/bin/smartline-backup.sh

# Add to cron (daily at 2 AM)
(crontab -l 2>/dev/null | grep -v smartline-backup; echo "0 2 * * * /usr/local/bin/smartline-backup.sh >> /var/log/smartline-backup.log 2>&1") | crontab -

print_success "Backup script created and scheduled"

# =============================================================================
# SUMMARY
# =============================================================================
print_header "SETUP COMPLETE"

echo ""
echo "VPS2 Setup Summary:"
echo "==================="
echo ""
echo "MySQL:"
echo "  - Host: $VPS2_PRIVATE_IP:3306"
echo "  - Database: $MYSQL_DATABASE"
echo "  - App User: $MYSQL_APP_USER (accessible from $VPS1_PRIVATE_IP)"
echo ""
echo "Redis:"
echo "  - Host: $VPS2_PRIVATE_IP:6379"
echo "  - Password: (see credentials file)"
echo ""
echo "Node.js:"
echo "  - Directory: $NODEJS_PATH"
echo "  - Port: $NODEJS_PORT"
echo "  - PM2 ecosystem config ready"
echo ""
echo "Credentials saved to: $CREDS_FILE"
echo ""
echo -e "${YELLOW}NEXT STEPS:${NC}"
echo "1. Import database from VPS1:"
echo "   scp user@vps1:/path/to/backup.sql.gz /tmp/"
echo "   gunzip /tmp/backup.sql.gz"
echo "   mysql -u root -p $MYSQL_DATABASE < /tmp/backup.sql"
echo ""
echo "2. Copy Node.js application from VPS1:"
echo "   scp -r user@vps1:/var/www/laravel/smartlinevps/realtime-service/* $NODEJS_PATH/"
echo ""
echo "3. Update Node.js .env (especially JWT_SECRET)"
echo "   nano $NODEJS_PATH/.env"
echo ""
echo "4. Start Node.js service:"
echo "   cd $NODEJS_PATH && npm install --production"
echo "   pm2 start ecosystem.config.js"
echo "   pm2 save"
echo ""
echo "5. Run VPS1 migration script"
echo ""
print_warning "Keep the credentials file secure!"
print_warning "Test all connections before proceeding with VPS1 migration"
echo ""
