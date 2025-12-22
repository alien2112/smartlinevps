#!/bin/bash
# ============================================
# SmartLine Complete Deployment Script
# ============================================
# Run: bash deploy.sh
# ============================================

set -e  # Exit on error

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_DIR="/var/www/smartline"
PHP_VERSION="8.2"
BRANCH="main"

echo -e "${BLUE}============================================${NC}"
echo -e "${BLUE}   SmartLine Deployment Script${NC}"
echo -e "${BLUE}   $(date)${NC}"
echo -e "${BLUE}============================================${NC}"

# Check if running as correct user
if [ "$(whoami)" != "smartline" ] && [ "$(whoami)" != "root" ]; then
    echo -e "${RED}Please run as smartline user or root${NC}"
    exit 1
fi

cd "$PROJECT_DIR"

# 1. Enable maintenance mode
echo -e "\n${YELLOW}1. Enabling maintenance mode...${NC}"
php artisan down --message="Upgrading... Please wait." --retry=60 || true

# 2. Backup database (optional - uncomment if needed)
# echo -e "\n${YELLOW}2. Backing up database...${NC}"
# php artisan backup:run --only-db

# 3. Pull latest code
echo -e "\n${YELLOW}3. Pulling latest code...${NC}"
git fetch origin
git checkout $BRANCH
git pull origin $BRANCH

# 4. Install dependencies
echo -e "\n${YELLOW}4. Installing dependencies...${NC}"
composer install --optimize-autoloader --no-dev --no-interaction

# 5. Run migrations
echo -e "\n${YELLOW}5. Running database migrations...${NC}"
php artisan migrate --force

# 6. Clear old caches
echo -e "\n${YELLOW}6. Clearing old caches...${NC}"
php artisan optimize:clear

# 7. Rebuild caches
echo -e "\n${YELLOW}7. Rebuilding caches...${NC}"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan optimize

# 8. Restart queue workers
echo -e "\n${YELLOW}8. Restarting queue workers...${NC}"
if command -v supervisorctl &> /dev/null; then
    sudo supervisorctl restart smartline-worker:* 2>/dev/null || echo "Workers restarted or not configured"
    sudo supervisorctl restart smartline-reverb 2>/dev/null || echo "Reverb restarted or not configured"
else
    echo "Supervisor not found, restarting workers manually..."
    php artisan queue:restart
fi

# 9. Restart PHP-FPM
echo -e "\n${YELLOW}9. Restarting PHP-FPM...${NC}"
sudo systemctl restart php${PHP_VERSION}-fpm 2>/dev/null || echo "PHP-FPM restart skipped"

# 10. Restart Node.js service
echo -e "\n${YELLOW}10. Restarting Node.js realtime service...${NC}"
if command -v pm2 &> /dev/null; then
    pm2 restart smartline-realtime 2>/dev/null || echo "PM2 process not found"
fi

# 11. Fix permissions
echo -e "\n${YELLOW}11. Fixing permissions...${NC}"
chmod -R 775 storage bootstrap/cache
chown -R smartline:www-data storage bootstrap/cache 2>/dev/null || true

# 12. Disable maintenance mode
echo -e "\n${YELLOW}12. Disabling maintenance mode...${NC}"
php artisan up

# 13. Health check
echo -e "\n${YELLOW}13. Running health check...${NC}"
if [ -f "$PROJECT_DIR/deployment-configs/health_check_quick.sh" ]; then
    bash "$PROJECT_DIR/deployment-configs/health_check_quick.sh"
else
    # Quick inline health check
    echo -n "Redis: "
    redis-cli ping 2>/dev/null || echo "FAILED"
    
    echo -n "Queue workers: "
    supervisorctl status smartline-worker:* 2>/dev/null | head -1 || echo "Not running"
    
    echo -n "PHP-FPM: "
    systemctl is-active php${PHP_VERSION}-fpm 2>/dev/null || echo "FAILED"
fi

echo -e "\n${GREEN}============================================${NC}"
echo -e "${GREEN}   Deployment Complete!${NC}"
echo -e "${GREEN}   Time: $(date)${NC}"
echo -e "${GREEN}============================================${NC}"

echo -e "\n${BLUE}Post-deployment checklist:${NC}"
echo "  [ ] Monitor logs for 30 minutes: tail -f storage/logs/laravel.log"
echo "  [ ] Test login endpoint"
echo "  [ ] Test trip creation"
echo "  [ ] Check Sentry for new errors"
echo "  [ ] Verify queue is processing: php artisan queue:monitor redis:high,redis:default"
