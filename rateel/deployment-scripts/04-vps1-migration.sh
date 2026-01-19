#!/bin/bash
# =============================================================================
# SmartLine 2-VPS Migration Script - VPS1 (Application Server)
# =============================================================================
# This script updates VPS1 to connect to VPS2 for database, Redis, and Node.js
#
# PREREQUISITES:
#   1. VPS2 is already setup with MySQL, Redis, and Node.js running
#   2. Database has been exported from VPS1 and imported to VPS2
#   3. MySQL user created on VPS2 for VPS1 access
#   4. Private network connectivity verified between VPS1 and VPS2
#
# USAGE:
#   sudo ./04-vps1-migration.sh
#
# =============================================================================

set -e  # Exit on error

# =============================================================================
# CONFIGURATION - MODIFY THESE VALUES
# =============================================================================
VPS2_PRIVATE_IP="${VPS2_PRIVATE_IP:-10.132.0.11}"
VPS2_PUBLIC_IP="${VPS2_PUBLIC_IP:-}"

# MySQL Configuration
MYSQL_USER="${MYSQL_USER:-smartline_app}"
MYSQL_PASSWORD="${MYSQL_PASSWORD:-}"
MYSQL_DATABASE="${MYSQL_DATABASE:-merged2}"

# Redis Configuration
REDIS_PASSWORD="${REDIS_PASSWORD:-}"

# Node.js Configuration
NODEJS_PORT="${NODEJS_PORT:-3002}"

# Paths (CORRECTED for SmartLine)
LARAVEL_PATH="/var/www/laravel/smartlinevps/rateel"
REALTIME_PATH="/var/www/laravel/smartlinevps/realtime-service"

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

# Check Laravel path exists
if [ ! -d "$LARAVEL_PATH" ]; then
    print_error "Laravel directory not found at $LARAVEL_PATH"
    exit 1
fi
print_success "Laravel directory found"

# Check .env exists
if [ ! -f "$LARAVEL_PATH/.env" ]; then
    print_error ".env file not found at $LARAVEL_PATH/.env"
    exit 1
fi
print_success ".env file found"

# Check artisan exists
if [ ! -f "$LARAVEL_PATH/artisan" ]; then
    print_error "artisan not found - not a Laravel project?"
    exit 1
fi
print_success "Laravel artisan found"

# =============================================================================
# STEP 1: BACKUP CURRENT CONFIGURATION
# =============================================================================
print_header "STEP 1: BACKUP CURRENT CONFIGURATION"

BACKUP_DIR="/tmp/smartline_migration_backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"
print_info "Backup directory: $BACKUP_DIR"

# Backup .env
cp "$LARAVEL_PATH/.env" "$BACKUP_DIR/laravel.env.backup"
print_success "Backed up Laravel .env"

# Backup Node.js .env if exists
if [ -f "$REALTIME_PATH/.env" ]; then
    cp "$REALTIME_PATH/.env" "$BACKUP_DIR/nodejs.env.backup"
    print_success "Backed up Node.js .env"
fi

# Get current database info
DB_NAME=$(grep "^DB_DATABASE=" "$LARAVEL_PATH/.env" | cut -d '=' -f2 | tr -d ' "'"'"'')
DB_USER=$(grep "^DB_USERNAME=" "$LARAVEL_PATH/.env" | cut -d '=' -f2 | tr -d ' "'"'"'')
DB_PASS=$(grep "^DB_PASSWORD=" "$LARAVEL_PATH/.env" | cut -d '=' -f2 | tr -d '"'"'"'')
DB_HOST=$(grep "^DB_HOST=" "$LARAVEL_PATH/.env" | cut -d '=' -f2 | tr -d ' "'"'"'')

print_info "Current database: $DB_NAME on $DB_HOST"

# Backup database (optional but recommended)
if confirm_action "Create database backup before migration? (Recommended)"; then
    print_info "Creating database backup..."
    if mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_DIR/database_backup.sql" 2>/dev/null; then
        gzip "$BACKUP_DIR/database_backup.sql"
        print_success "Database backed up to $BACKUP_DIR/database_backup.sql.gz"
    else
        print_warning "Could not backup database with current credentials"
        if confirm_action "Try with root password?"; then
            read -sp "Enter MySQL root password: " ROOT_PASS
            echo ""
            if mysqldump -h "$DB_HOST" -u root -p"$ROOT_PASS" "$DB_NAME" > "$BACKUP_DIR/database_backup.sql" 2>/dev/null; then
                gzip "$BACKUP_DIR/database_backup.sql"
                print_success "Database backed up"
            else
                print_warning "Database backup failed - continuing without backup"
            fi
        fi
    fi
fi

# =============================================================================
# STEP 2: GET VPS2 CREDENTIALS
# =============================================================================
print_header "STEP 2: VPS2 CONNECTION DETAILS"

# VPS2 Private IP
if [ -z "$VPS2_PRIVATE_IP" ] || [ "$VPS2_PRIVATE_IP" = "10.132.0.11" ]; then
    read -p "Enter VPS2 private IP address [$VPS2_PRIVATE_IP]: " input
    VPS2_PRIVATE_IP="${input:-$VPS2_PRIVATE_IP}"
fi
print_info "VPS2 Private IP: $VPS2_PRIVATE_IP"

# MySQL Password
if [ -z "$MYSQL_PASSWORD" ]; then
    read -sp "Enter MySQL password for $MYSQL_USER on VPS2: " MYSQL_PASSWORD
    echo ""
fi

# Redis Password
if [ -z "$REDIS_PASSWORD" ]; then
    read -sp "Enter Redis password for VPS2: " REDIS_PASSWORD
    echo ""
fi

# =============================================================================
# STEP 3: TEST VPS2 CONNECTIVITY
# =============================================================================
print_header "STEP 3: TESTING VPS2 CONNECTIVITY"

# Test ping
print_info "Testing network connectivity..."
if ping -c 3 "$VPS2_PRIVATE_IP" > /dev/null 2>&1; then
    print_success "VPS2 is reachable at $VPS2_PRIVATE_IP"
else
    print_error "Cannot reach VPS2 at $VPS2_PRIVATE_IP"
    print_info "Please verify:"
    print_info "  1. VPS2 is running"
    print_info "  2. Private network is configured"
    print_info "  3. Firewall allows connections"
    exit 1
fi

# Test MySQL connection
print_info "Testing MySQL connection..."
if mysql -h "$VPS2_PRIVATE_IP" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "SELECT 1;" > /dev/null 2>&1; then
    print_success "MySQL connection successful"
else
    print_error "MySQL connection failed"
    print_info "Please verify:"
    print_info "  1. MySQL is running on VPS2"
    print_info "  2. User $MYSQL_USER exists with correct password"
    print_info "  3. MySQL is bound to private IP"
    print_info "  4. Firewall allows port 3306 from VPS1"
    exit 1
fi

# Verify database exists
print_info "Verifying database $MYSQL_DATABASE exists..."
if mysql -h "$VPS2_PRIVATE_IP" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "USE $MYSQL_DATABASE;" > /dev/null 2>&1; then
    print_success "Database $MYSQL_DATABASE accessible"
else
    print_error "Database $MYSQL_DATABASE not found or not accessible"
    print_info "Please ensure database is imported on VPS2"
    exit 1
fi

# Test Redis connection
print_info "Testing Redis connection..."
if redis-cli -h "$VPS2_PRIVATE_IP" -p 6379 -a "$REDIS_PASSWORD" ping 2>/dev/null | grep -q "PONG"; then
    print_success "Redis connection successful"
else
    print_error "Redis connection failed"
    print_info "Please verify:"
    print_info "  1. Redis is running on VPS2"
    print_info "  2. Redis password is correct"
    print_info "  3. Redis is bound to private IP"
    print_info "  4. Firewall allows port 6379 from VPS1"
    exit 1
fi

# Test Node.js (optional)
print_info "Testing Node.js connection..."
if curl -s --connect-timeout 5 "http://$VPS2_PRIVATE_IP:$NODEJS_PORT/health" > /dev/null 2>&1; then
    print_success "Node.js realtime service is responding"
else
    print_warning "Node.js health check failed (may not be running yet)"
    if ! confirm_action "Continue without Node.js verification?"; then
        exit 1
    fi
fi

# =============================================================================
# STEP 4: ENABLE MAINTENANCE MODE
# =============================================================================
print_header "STEP 4: MAINTENANCE MODE"

if confirm_action "Enable maintenance mode during migration?"; then
    cd "$LARAVEL_PATH"
    php artisan down --message="System maintenance in progress. Please try again shortly." --retry=60
    print_success "Maintenance mode enabled"
    MAINTENANCE_MODE=true
else
    print_warning "Proceeding without maintenance mode (not recommended)"
    MAINTENANCE_MODE=false
fi

# =============================================================================
# STEP 5: STOP QUEUE WORKERS
# =============================================================================
print_header "STEP 5: STOPPING QUEUE WORKERS"

# Check current queue size
print_info "Checking queue sizes..."
cd "$LARAVEL_PATH"
php artisan queue:size 2>/dev/null || print_warning "Could not check queue size"

# Stop supervisor workers (CORRECTED group names)
print_info "Stopping queue workers..."
supervisorctl stop smartline-worker-high:* 2>/dev/null || print_warning "smartline-worker-high not found"
supervisorctl stop smartline-worker-default:* 2>/dev/null || print_warning "smartline-worker-default not found"
supervisorctl stop smartline-worker-notifications:* 2>/dev/null || print_warning "smartline-worker-notifications not found"
print_success "Queue workers stopped"

# =============================================================================
# STEP 6: UPDATE LARAVEL .ENV
# =============================================================================
print_header "STEP 6: UPDATING LARAVEL CONFIGURATION"

cd "$LARAVEL_PATH"

# Create timestamped backup
cp .env ".env.backup.$(date +%Y%m%d_%H%M%S)"

# Update database settings
print_info "Updating database configuration..."
sed -i "s|^DB_HOST=.*|DB_HOST=$VPS2_PRIVATE_IP|" .env
sed -i "s|^DB_USERNAME=.*|DB_USERNAME=$MYSQL_USER|" .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$MYSQL_PASSWORD|" .env
sed -i "s|^DB_DATABASE=.*|DB_DATABASE=$MYSQL_DATABASE|" .env
print_success "Database configuration updated"

# Update Redis settings
print_info "Updating Redis configuration..."
sed -i "s|^REDIS_HOST=.*|REDIS_HOST=$VPS2_PRIVATE_IP|" .env
sed -i "s|^REDIS_PASSWORD=.*|REDIS_PASSWORD=$REDIS_PASSWORD|" .env
print_success "Redis configuration updated"

# Update Node.js realtime URL
print_info "Updating Node.js realtime URL..."
if grep -q "^NODEJS_REALTIME_URL=" .env; then
    sed -i "s|^NODEJS_REALTIME_URL=.*|NODEJS_REALTIME_URL=http://$VPS2_PRIVATE_IP:$NODEJS_PORT|" .env
else
    echo "NODEJS_REALTIME_URL=http://$VPS2_PRIVATE_IP:$NODEJS_PORT" >> .env
fi
print_success "Node.js configuration updated"

# Show changes
print_info "Configuration changes:"
echo "  DB_HOST=$VPS2_PRIVATE_IP"
echo "  DB_USERNAME=$MYSQL_USER"
echo "  DB_DATABASE=$MYSQL_DATABASE"
echo "  REDIS_HOST=$VPS2_PRIVATE_IP"
echo "  NODEJS_REALTIME_URL=http://$VPS2_PRIVATE_IP:$NODEJS_PORT"

# =============================================================================
# STEP 7: CLEAR LARAVEL CACHES
# =============================================================================
print_header "STEP 7: CLEARING LARAVEL CACHES"

cd "$LARAVEL_PATH"

print_info "Clearing configuration cache..."
php artisan config:clear
print_success "Config cache cleared"

print_info "Clearing application cache..."
php artisan cache:clear
print_success "App cache cleared"

print_info "Clearing route cache..."
php artisan route:clear
print_success "Route cache cleared"

print_info "Clearing view cache..."
php artisan view:clear
print_success "View cache cleared"

# Rebuild caches
print_info "Rebuilding configuration cache..."
php artisan config:cache
print_success "Config cache rebuilt"

print_info "Rebuilding route cache..."
php artisan route:cache
print_success "Route cache rebuilt"

# =============================================================================
# STEP 8: TEST LARAVEL CONNECTIONS
# =============================================================================
print_header "STEP 8: TESTING LARAVEL CONNECTIONS"

cd "$LARAVEL_PATH"

# Test database
print_info "Testing database connection..."
if php artisan tinker --execute="try { DB::connection()->getPdo(); echo 'OK'; } catch(Exception \$e) { echo 'FAIL: '.\$e->getMessage(); }" 2>/dev/null | grep -q "OK"; then
    print_success "Laravel database connection working"
else
    print_error "Laravel database connection failed!"
    print_info "Rolling back..."
    cp "$BACKUP_DIR/laravel.env.backup" .env
    php artisan config:clear
    php artisan config:cache
    exit 1
fi

# Test Redis
print_info "Testing Redis connection..."
if php artisan tinker --execute="try { Redis::connection()->ping(); echo 'OK'; } catch(Exception \$e) { echo 'FAIL: '.\$e->getMessage(); }" 2>/dev/null | grep -q "OK"; then
    print_success "Laravel Redis connection working"
else
    print_error "Laravel Redis connection failed!"
    print_info "Rolling back..."
    cp "$BACKUP_DIR/laravel.env.backup" .env
    php artisan config:clear
    php artisan config:cache
    exit 1
fi

# =============================================================================
# STEP 9: RESTART SERVICES
# =============================================================================
print_header "STEP 9: RESTARTING SERVICES"

# Restart PHP-FPM
print_info "Restarting PHP-FPM..."
if systemctl restart php8.2-fpm 2>/dev/null; then
    print_success "PHP 8.2-FPM restarted"
elif systemctl restart php8.1-fpm 2>/dev/null; then
    print_success "PHP 8.1-FPM restarted"
else
    print_warning "Could not restart PHP-FPM automatically"
fi

# Reload Nginx
print_info "Reloading Nginx..."
if nginx -t > /dev/null 2>&1; then
    systemctl reload nginx
    print_success "Nginx reloaded"
else
    print_warning "Nginx config test failed - not reloading"
fi

# Start queue workers
print_info "Starting queue workers..."
supervisorctl reread
supervisorctl update
supervisorctl start smartline-worker-high:* 2>/dev/null || print_warning "Could not start smartline-worker-high"
supervisorctl start smartline-worker-default:* 2>/dev/null || print_warning "Could not start smartline-worker-default"
supervisorctl start smartline-worker-notifications:* 2>/dev/null || print_warning "Could not start smartline-worker-notifications"
print_success "Queue workers started"

# =============================================================================
# STEP 10: DISABLE MAINTENANCE MODE
# =============================================================================
print_header "STEP 10: DISABLING MAINTENANCE MODE"

if [ "$MAINTENANCE_MODE" = true ]; then
    cd "$LARAVEL_PATH"
    php artisan up
    print_success "Maintenance mode disabled"
fi

# =============================================================================
# STEP 11: FINAL VERIFICATION
# =============================================================================
print_header "STEP 11: FINAL VERIFICATION"

# Test API health
print_info "Testing API health endpoint..."
sleep 2  # Give services time to stabilize
if curl -s http://localhost/api/health 2>/dev/null | grep -q "healthy\|ok\|success"; then
    print_success "API health check passed"
else
    print_warning "API health check inconclusive (may need manual verification)"
fi

# Check supervisor status
print_info "Queue worker status:"
supervisorctl status | grep smartline || print_warning "No smartline workers found in supervisor"

# =============================================================================
# SUMMARY
# =============================================================================
print_header "MIGRATION SUMMARY"

echo ""
echo "Migration completed!"
echo ""
echo "Configuration Changes:"
echo "  - Database: $MYSQL_DATABASE @ $VPS2_PRIVATE_IP:3306"
echo "  - Redis: $VPS2_PRIVATE_IP:6379"
echo "  - Node.js: http://$VPS2_PRIVATE_IP:$NODEJS_PORT"
echo ""
echo "Backup Location: $BACKUP_DIR"
echo ""
echo "To rollback if needed:"
echo "  cp $BACKUP_DIR/laravel.env.backup $LARAVEL_PATH/.env"
echo "  cd $LARAVEL_PATH && php artisan config:clear && php artisan config:cache"
echo "  supervisorctl restart smartline-worker-high:* smartline-worker-default:* smartline-worker-notifications:*"
echo ""
print_warning "IMPORTANT: Monitor logs and performance for the next 24-48 hours"
print_info "Log files:"
print_info "  - Laravel: $LARAVEL_PATH/storage/logs/laravel.log"
print_info "  - Queue Workers: $LARAVEL_PATH/storage/logs/worker-*.log"
echo ""
