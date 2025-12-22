#!/bin/bash
#=============================================================================
# Performance Optimization Deployment Script for VPS
# Smart Line Ride-Hailing Application
#=============================================================================
# 
# This script applies all performance optimizations to your VPS.
# Run with: bash deploy-performance.sh
#
# Prerequisites:
# - SSH access to VPS
# - sudo privileges
# - MySQL 8.0+ with spatial support
# - Redis installed and running
# - PHP 8.1+
#=============================================================================

set -e  # Exit on error

# Configuration - UPDATE THESE
APP_DIR="/var/www/smart-line.space"
MYSQL_USER="your_mysql_user"
MYSQL_PASS="your_mysql_password"
MYSQL_DB="smartline_db"

echo "=============================================="
echo "Starting Performance Optimization Deployment"
echo "=============================================="

# 1. Navigate to application directory
cd $APP_DIR

# 2. Backup database
echo "[1/10] Creating database backup..."
mysqldump -u$MYSQL_USER -p$MYSQL_PASS $MYSQL_DB > /tmp/backup_before_perf_$(date +%Y%m%d_%H%M%S).sql
echo "       Backup created at /tmp/"

# 3. Pull latest code
echo "[2/10] Pulling latest code from GitHub..."
git stash
git pull origin main
git stash pop || true

# 4. Install/update composer dependencies
echo "[3/10] Updating composer dependencies..."
composer install --no-dev --optimize-autoloader

# 5. Clear all caches
echo "[4/10] Clearing all caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear

# 6. Run database migrations
echo "[5/10] Running database migrations..."
php artisan migrate --force

# 7. Verify spatial column and index exist
echo "[6/10] Verifying spatial indexes..."
mysql -u$MYSQL_USER -p$MYSQL_PASS $MYSQL_DB -e "
    -- Check if location_point column exists
    SELECT COUNT(*) as has_column FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = '$MYSQL_DB' 
    AND TABLE_NAME = 'user_last_locations' 
    AND COLUMN_NAME = 'location_point';
    
    -- Check if spatial index exists
    SHOW INDEX FROM user_last_locations WHERE Key_name = 'idx_location_point';
"

# 8. Verify triggers exist
echo "[7/10] Verifying database triggers..."
mysql -u$MYSQL_USER -p$MYSQL_PASS $MYSQL_DB -e "
    SHOW TRIGGERS LIKE 'user_last_locations';
"

# 9. Optimize caches
echo "[8/10] Building optimized caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 10. Warm up performance caches
echo "[9/10] Warming up performance caches..."
php artisan cache:warmup

# 11. Restart queue workers
echo "[10/10] Restarting queue workers..."
php artisan queue:restart

echo ""
echo "=============================================="
echo "Deployment Complete!"
echo "=============================================="
echo ""
echo "IMPORTANT: Start queue worker for async broadcasting:"
echo "  php artisan queue:work --queue=broadcasting,default --tries=3"
echo ""
echo "Or add to supervisor config:"
echo "  /etc/supervisor/conf.d/smartline-broadcasting.conf"
echo ""
