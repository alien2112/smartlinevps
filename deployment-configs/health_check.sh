#!/bin/bash
# ============================================
# SmartLine Server Health Check
# ============================================
# Run: bash health_check.sh
# ============================================

echo "==========================================="
echo "   SmartLine Server Health Check"
echo "   $(date)"
echo "==========================================="

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

PASSED=0
FAILED=0

check_service() {
    if [ $1 -eq 0 ]; then
        echo -e "   ${GREEN}✓ PASS${NC}"
        ((PASSED++))
    else
        echo -e "   ${RED}✗ FAIL${NC}"
        ((FAILED++))
    fi
}

PROJECT_DIR="/var/www/smartline"
cd "$PROJECT_DIR" 2>/dev/null || { echo "Project directory not found"; exit 1; }

# 1. Redis
echo -e "\n${YELLOW}1. Redis Server:${NC}"
redis-cli ping > /dev/null 2>&1
check_service $?

# 2. MySQL
echo -e "\n${YELLOW}2. MySQL Server:${NC}"
systemctl is-active --quiet mysql
check_service $?

# 3. PHP-FPM
echo -e "\n${YELLOW}3. PHP-FPM:${NC}"
systemctl is-active --quiet php8.2-fpm 2>/dev/null || systemctl is-active --quiet php8.1-fpm 2>/dev/null
check_service $?

# 4. Nginx
echo -e "\n${YELLOW}4. Nginx:${NC}"
systemctl is-active --quiet nginx
check_service $?

# 5. Queue Workers
echo -e "\n${YELLOW}5. Queue Workers:${NC}"
WORKERS=$(supervisorctl status smartline-worker:* 2>/dev/null | grep -c RUNNING)
if [ "$WORKERS" -gt 0 ]; then
    echo -e "   ${GREEN}✓ $WORKERS workers running${NC}"
    ((PASSED++))
else
    echo -e "   ${RED}✗ No workers running${NC}"
    ((FAILED++))
fi

# 6. Reverb WebSocket
echo -e "\n${YELLOW}6. Reverb WebSocket:${NC}"
supervisorctl status smartline-reverb 2>/dev/null | grep -q RUNNING
check_service $?

# 7. Node.js Realtime Service
echo -e "\n${YELLOW}7. Node.js Realtime Service (Port 3000):${NC}"
netstat -tlnp 2>/dev/null | grep -q ":3000" || ss -tlnp 2>/dev/null | grep -q ":3000"
check_service $?

# 8. Laravel Storage Writable
echo -e "\n${YELLOW}8. Laravel Storage:${NC}"
touch "$PROJECT_DIR/storage/logs/.health_check" 2>/dev/null
check_service $?
rm -f "$PROJECT_DIR/storage/logs/.health_check" 2>/dev/null

# 9. Cache Connection
echo -e "\n${YELLOW}9. Laravel Cache:${NC}"
php artisan tinker --execute="Cache::put('health_check', true, 10); echo Cache::get('health_check') ? 'OK' : 'FAIL';" 2>/dev/null | grep -q OK
check_service $?

# 10. Queue Depth
echo -e "\n${YELLOW}10. Queue Depth:${NC}"
HIGH_QUEUE=$(redis-cli llen queues:high 2>/dev/null || echo 0)
DEFAULT_QUEUE=$(redis-cli llen queues:default 2>/dev/null || echo 0)
BROADCASTING_QUEUE=$(redis-cli llen queues:broadcasting 2>/dev/null || echo 0)
echo "   High: $HIGH_QUEUE | Default: $DEFAULT_QUEUE | Broadcasting: $BROADCASTING_QUEUE"
if [ "$HIGH_QUEUE" -lt 100 ] && [ "$DEFAULT_QUEUE" -lt 1000 ]; then
    echo -e "   ${GREEN}✓ Queue depth acceptable${NC}"
    ((PASSED++))
else
    echo -e "   ${YELLOW}⚠ Queue depth high - may need more workers${NC}"
    ((PASSED++))  # Warning, not failure
fi

# 11. Failed Jobs
echo -e "\n${YELLOW}11. Failed Jobs:${NC}"
FAILED_JOBS=$(php artisan queue:failed --no-interaction 2>/dev/null | tail -n +4 | wc -l)
if [ "$FAILED_JOBS" -eq 0 ]; then
    echo -e "   ${GREEN}✓ No failed jobs${NC}"
    ((PASSED++))
else
    echo -e "   ${YELLOW}⚠ $FAILED_JOBS failed jobs${NC}"
    ((PASSED++))  # Warning, not failure
fi

# 12. Disk Space
echo -e "\n${YELLOW}12. Disk Space:${NC}"
DISK_USAGE=$(df -h / | tail -1 | awk '{print $5}' | tr -d '%')
echo "   Usage: ${DISK_USAGE}%"
if [ "$DISK_USAGE" -lt 80 ]; then
    echo -e "   ${GREEN}✓ Disk space OK${NC}"
    ((PASSED++))
elif [ "$DISK_USAGE" -lt 90 ]; then
    echo -e "   ${YELLOW}⚠ Disk space warning${NC}"
    ((PASSED++))
else
    echo -e "   ${RED}✗ Disk space critical!${NC}"
    ((FAILED++))
fi

# 13. Memory Usage
echo -e "\n${YELLOW}13. Memory Usage:${NC}"
MEM_USAGE=$(free | grep Mem | awk '{printf("%.0f", $3/$2 * 100)}')
echo "   Usage: ${MEM_USAGE}%"
if [ "$MEM_USAGE" -lt 85 ]; then
    echo -e "   ${GREEN}✓ Memory OK${NC}"
    ((PASSED++))
else
    echo -e "   ${YELLOW}⚠ Memory usage high${NC}"
    ((PASSED++))
fi

# 14. API Response Test
echo -e "\n${YELLOW}14. API Health Endpoint:${NC}"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/api/health 2>/dev/null)
if [ "$HTTP_CODE" = "200" ]; then
    echo -e "   ${GREEN}✓ API responding (HTTP $HTTP_CODE)${NC}"
    ((PASSED++))
else
    echo -e "   ${RED}✗ API not responding (HTTP $HTTP_CODE)${NC}"
    ((FAILED++))
fi

# Summary
echo -e "\n==========================================="
echo -e "   Health Check Summary"
echo -e "==========================================="
echo -e "   ${GREEN}Passed: $PASSED${NC}"
echo -e "   ${RED}Failed: $FAILED${NC}"

if [ "$FAILED" -eq 0 ]; then
    echo -e "\n   ${GREEN}✓ All checks passed!${NC}"
    exit 0
else
    echo -e "\n   ${RED}✗ Some checks failed. Review above.${NC}"
    exit 1
fi
