#!/bin/bash
# SmartLine Quick Start Script
# Run: bash start_services.sh

echo "Starting SmartLine Services..."

# 1. Start Redis
echo "1. Starting Redis..."
systemctl start redis-server
systemctl enable redis-server

# 2. Start Node.js Realtime Service
echo "2. Starting Node.js Realtime Service..."
cd /var/www/laravel/smartlinevps/realtime-service

# Kill existing node processes
pkill -9 node 2>/dev/null
sleep 2

# Check if PM2 is installed
if command -v pm2 &> /dev/null; then
    pm2 delete smartline-realtime 2>/dev/null
    pm2 start npm --name "smartline-realtime" -- run start
    pm2 save
else
    # Run with nohup if PM2 not available
    nohup node src/server.js > /dev/null 2>&1 &
fi

# 3. Start Laravel Queue Worker
echo "3. Starting Laravel Queue Worker..."
cd /var/www/laravel/smartlinevps

# Check if Supervisor is running
if command -v supervisorctl &> /dev/null; then
    supervisorctl restart smartline-worker:* 2>/dev/null || echo "Supervisor not configured, starting manually..."
    nohup php artisan queue:work --queue=high,default > /dev/null 2>&1 &
else
    nohup php artisan queue:work --queue=high,default > /dev/null 2>&1 &
fi

# 4. Clear Laravel Cache
echo "4. Clearing Laravel Cache..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# 5. Restart PHP-FPM
echo "5. Restarting PHP-FPM..."
systemctl restart php8.1-fpm 2>/dev/null || systemctl restart php8.2-fpm 2>/dev/null

# 6. Restart Nginx
echo "6. Restarting Nginx..."
systemctl restart nginx

echo ""
echo "==================================="
echo "All services started!"
echo "==================================="
echo ""
echo "Run health_check.sh to verify all services are running."
