#!/bin/bash
# =============================================================================
# SmartLine VPS1 Rollback Script
# =============================================================================
# Run this to rollback VPS1 to use local MySQL and Redis
#
# USAGE:
#   sudo ./08-rollback-vps1.sh [backup_directory]
#
# EXAMPLE:
#   sudo ./08-rollback-vps1.sh /tmp/smartline_migration_backup_20260119_140000
#
# =============================================================================

set -e

# Configuration
LARAVEL_PATH="/var/www/laravel/smartlinevps/rateel"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

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
print_header "VPS1 ROLLBACK SCRIPT"
# =============================================================================

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    print_error "Please run as root or with sudo"
    exit 1
fi

# Find backup directory
BACKUP_DIR="$1"
if [ -z "$BACKUP_DIR" ]; then
    # Try to find most recent backup
    BACKUP_DIR=$(ls -td /tmp/smartline_migration_backup_* 2>/dev/null | head -1)
fi

if [ -z "$BACKUP_DIR" ] || [ ! -d "$BACKUP_DIR" ]; then
    print_error "Backup directory not found"
    print_info "Usage: $0 /path/to/backup/directory"
    print_info ""
    print_info "Available backups:"
    ls -td /tmp/smartline_migration_backup_* 2>/dev/null || echo "  None found"
    exit 1
fi

print_info "Using backup directory: $BACKUP_DIR"

# Check backup files exist
if [ ! -f "$BACKUP_DIR/laravel.env.backup" ]; then
    print_error "Backup .env file not found at $BACKUP_DIR/laravel.env.backup"
    exit 1
fi

print_success "Backup .env file found"

# =============================================================================
print_header "WARNING"
# =============================================================================

echo -e "${RED}"
echo "This script will:"
echo "  1. Restore Laravel .env to use LOCAL MySQL and Redis"
echo "  2. Start local MySQL and Redis services"
echo "  3. Clear and rebuild Laravel caches"
echo "  4. Restart queue workers"
echo ""
echo "This will DISCONNECT from VPS2 services!"
echo -e "${NC}"

if ! confirm_action "Are you sure you want to rollback?"; then
    print_info "Rollback cancelled"
    exit 0
fi

# =============================================================================
print_header "STEP 1: ENABLE MAINTENANCE MODE"
# =============================================================================

cd "$LARAVEL_PATH"
php artisan down --message="System rollback in progress. Please try again shortly." --retry=60
print_success "Maintenance mode enabled"

# =============================================================================
print_header "STEP 2: STOP QUEUE WORKERS"
# =============================================================================

supervisorctl stop smartline-worker-high:* 2>/dev/null || true
supervisorctl stop smartline-worker-default:* 2>/dev/null || true
supervisorctl stop smartline-worker-notifications:* 2>/dev/null || true
print_success "Queue workers stopped"

# =============================================================================
print_header "STEP 3: START LOCAL SERVICES"
# =============================================================================

# Start MySQL
print_info "Starting local MySQL..."
if systemctl start mysql 2>/dev/null; then
    systemctl enable mysql 2>/dev/null || true
    print_success "MySQL started"
else
    print_warning "MySQL may already be running or not installed"
fi

# Start Redis
print_info "Starting local Redis..."
if systemctl start redis-server 2>/dev/null; then
    systemctl enable redis-server 2>/dev/null || true
    print_success "Redis started"
else
    print_warning "Redis may already be running or not installed"
fi

# Wait for services
sleep 3

# =============================================================================
print_header "STEP 4: RESTORE .ENV FILE"
# =============================================================================

# Create backup of current (VPS2) configuration
cp "$LARAVEL_PATH/.env" "$LARAVEL_PATH/.env.vps2.$(date +%Y%m%d_%H%M%S)"
print_info "Current .env backed up"

# Restore original .env
cp "$BACKUP_DIR/laravel.env.backup" "$LARAVEL_PATH/.env"
print_success "Original .env restored"

# Show the database configuration
echo ""
print_info "Restored configuration:"
grep -E "^DB_HOST=|^REDIS_HOST=" "$LARAVEL_PATH/.env"

# =============================================================================
print_header "STEP 5: CLEAR AND REBUILD CACHES"
# =============================================================================

cd "$LARAVEL_PATH"

php artisan config:clear
print_success "Config cache cleared"

php artisan cache:clear
print_success "Application cache cleared"

php artisan route:clear
print_success "Route cache cleared"

php artisan view:clear
print_success "View cache cleared"

php artisan config:cache
print_success "Config cache rebuilt"

php artisan route:cache
print_success "Route cache rebuilt"

# =============================================================================
print_header "STEP 6: TEST LOCAL CONNECTIONS"
# =============================================================================

# Test database
print_info "Testing local database connection..."
if php artisan tinker --execute="try { DB::connection()->getPdo(); echo 'OK'; } catch(Exception \$e) { echo 'FAIL: '.\$e->getMessage(); }" 2>/dev/null | grep -q "OK"; then
    print_success "Local database connection working"
else
    print_error "Local database connection FAILED"
    print_info "You may need to:"
    print_info "  1. Restore database from backup: mysql -u root -p DB_NAME < $BACKUP_DIR/database_backup.sql"
    print_info "  2. Check MySQL credentials in .env"
fi

# Test Redis
print_info "Testing local Redis connection..."
if php artisan tinker --execute="try { Redis::connection()->ping(); echo 'OK'; } catch(Exception \$e) { echo 'FAIL'; }" 2>/dev/null | grep -q "OK"; then
    print_success "Local Redis connection working"
else
    print_warning "Local Redis connection failed - sessions/cache may not work"
fi

# =============================================================================
print_header "STEP 7: RESTART SERVICES"
# =============================================================================

# Restart PHP-FPM
print_info "Restarting PHP-FPM..."
systemctl restart php8.2-fpm 2>/dev/null || systemctl restart php8.1-fpm 2>/dev/null || true
print_success "PHP-FPM restarted"

# Reload Nginx
print_info "Reloading Nginx..."
systemctl reload nginx
print_success "Nginx reloaded"

# Start queue workers
print_info "Starting queue workers..."
supervisorctl reread
supervisorctl update
supervisorctl start smartline-worker-high:* 2>/dev/null || true
supervisorctl start smartline-worker-default:* 2>/dev/null || true
supervisorctl start smartline-worker-notifications:* 2>/dev/null || true
print_success "Queue workers started"

# =============================================================================
print_header "STEP 8: DISABLE MAINTENANCE MODE"
# =============================================================================

cd "$LARAVEL_PATH"
php artisan up
print_success "Maintenance mode disabled"

# =============================================================================
print_header "STEP 9: VERIFICATION"
# =============================================================================

# Quick health check
sleep 2
health_response=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/api/health 2>/dev/null)
if [ "$health_response" = "200" ]; then
    print_success "API health check passed (HTTP $health_response)"
else
    print_warning "API health check returned HTTP $health_response"
fi

# Check worker status
worker_count=$(supervisorctl status 2>/dev/null | grep -c "smartline-worker.*RUNNING" || echo "0")
print_info "Queue workers running: $worker_count"

# =============================================================================
print_header "ROLLBACK COMPLETE"
# =============================================================================

echo ""
print_success "VPS1 has been rolled back to local services"
echo ""
echo "Current configuration:"
grep -E "^DB_HOST=|^REDIS_HOST=|^NODEJS_REALTIME_URL=" "$LARAVEL_PATH/.env"
echo ""
print_warning "If database is out of sync, restore from backup:"
echo "  gunzip -c $BACKUP_DIR/database_backup.sql.gz | mysql -u root -p $DB_NAME"
echo ""
print_info "Monitor logs for any issues:"
echo "  tail -f $LARAVEL_PATH/storage/logs/laravel.log"
echo ""
