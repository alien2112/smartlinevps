#!/bin/bash
# SmartLine Server Health Check Script
# Run: bash health_check.sh

echo "==========================================="
echo "   SmartLine Server Health Check"
echo "   $(date)"
echo "==========================================="

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

check_service() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✓ PASS${NC}"
    else
        echo -e "${RED}✗ FAIL${NC}"
    fi
}

echo -e "\n1. Redis Server:"
redis-cli ping > /dev/null 2>&1
check_service $?
redis-cli ping 2>/dev/null || echo "   Not responding"

echo -e "\n2. Node.js Realtime Service (Port 3000):"
netstat -tlnp 2>/dev/null | grep -q ":3000"
check_service $?
netstat -tlnp 2>/dev/null | grep ":3000" || echo "   Not listening"

echo -e "\n3. Laravel Queue Worker:"
ps aux | grep -v grep | grep -q "queue:work"
check_service $?
ps aux | grep -v grep | grep "queue:work" | head -1 || echo "   Not running"

echo -e "\n4. PHP-FPM:"
systemctl is-active --quiet php8.1-fpm 2>/dev/null || systemctl is-active --quiet php8.2-fpm 2>/dev/null
check_service $?

echo -e "\n5. Nginx:"
systemctl is-active --quiet nginx
check_service $?

echo -e "\n6. MySQL:"
systemctl is-active --quiet mysql
check_service $?

echo -e "\n7. Active Zones in Database:"
ZONES=$(mysql -u smartline -p"$DB_PASSWORD" smartline -N -e "SELECT COUNT(*) FROM zones WHERE is_active=1 AND deleted_at IS NULL;" 2>/dev/null)
if [ -n "$ZONES" ] && [ "$ZONES" -gt 0 ]; then
    echo -e "${GREEN}✓ $ZONES active zones${NC}"
else
    echo -e "${YELLOW}⚠ No zones or DB error${NC}"
fi

echo -e "\n8. Online Drivers:"
DRIVERS=$(mysql -u smartline -p"$DB_PASSWORD" smartline -N -e "SELECT COUNT(*) FROM driver_details WHERE is_online=1;" 2>/dev/null)
if [ -n "$DRIVERS" ]; then
    echo "   $DRIVERS drivers online"
else
    echo "   Cannot check (DB error)"
fi

echo -e "\n9. Node.js Logs (last 3 lines):"
tail -3 /var/www/laravel/smartlinevps/realtime-service/logs/combined.log 2>/dev/null || echo "   No logs found"

echo -e "\n==========================================="
echo "   Health Check Complete"
echo "==========================================="
