#!/bin/bash
# =============================================================================
# SmartLine VPS1 Verification Script
# =============================================================================
# Run this after migration to verify VPS1 is working correctly
#
# USAGE:
#   sudo ./06-verify-vps1.sh
#
# =============================================================================

set -e

# Configuration
LARAVEL_PATH="${LARAVEL_PATH:-/var/www/laravel/smartlinevps/rateel}"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

PASSED=0
FAILED=0
WARNINGS=0

print_header() {
    echo ""
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}"
}

pass() {
    echo -e "${GREEN}✓ PASS${NC}: $1"
    ((PASSED++))
}

fail() {
    echo -e "${RED}✗ FAIL${NC}: $1"
    ((FAILED++))
}

warn() {
    echo -e "${YELLOW}⚠ WARN${NC}: $1"
    ((WARNINGS++))
}

info() {
    echo -e "${YELLOW}ℹ${NC} $1"
}

# =============================================================================
print_header "VPS1 VERIFICATION TESTS"
# =============================================================================

# Get VPS2 IP from .env
VPS2_IP=$(grep "^DB_HOST=" "$LARAVEL_PATH/.env" | cut -d '=' -f2 | tr -d ' "'"'"'')
info "VPS2 IP from config: $VPS2_IP"

# =============================================================================
print_header "1. SYSTEM CHECKS"
# =============================================================================

# Check disk space
disk_usage=$(df -h / | awk 'NR==2 {print $5}' | tr -d '%')
if [ "$disk_usage" -lt 80 ]; then
    pass "Disk usage: ${disk_usage}%"
else
    warn "Disk usage high: ${disk_usage}%"
fi

# Check memory
mem_available=$(free -m | awk '/^Mem:/{print $7}')
if [ "$mem_available" -gt 2000 ]; then
    pass "Available memory: ${mem_available}MB"
else
    warn "Low available memory: ${mem_available}MB"
fi

# Check load average
load=$(cat /proc/loadavg | awk '{print $1}')
pass "Load average: $load"

# =============================================================================
print_header "2. SERVICE STATUS"
# =============================================================================

# Nginx
if systemctl is-active --quiet nginx; then
    pass "Nginx is running"
else
    fail "Nginx is not running"
fi

# PHP-FPM
if systemctl is-active --quiet php8.2-fpm; then
    pass "PHP-FPM 8.2 is running"
elif systemctl is-active --quiet php8.1-fpm; then
    pass "PHP-FPM 8.1 is running"
else
    fail "PHP-FPM is not running"
fi

# Supervisor
if systemctl is-active --quiet supervisor; then
    pass "Supervisor is running"
else
    fail "Supervisor is not running"
fi

# =============================================================================
print_header "3. NETWORK CONNECTIVITY TO VPS2"
# =============================================================================

# Ping VPS2
if ping -c 3 "$VPS2_IP" > /dev/null 2>&1; then
    pass "VPS2 is reachable ($VPS2_IP)"
else
    fail "Cannot reach VPS2 ($VPS2_IP)"
fi

# MySQL port
if nc -zv "$VPS2_IP" 3306 2>&1 | grep -q "succeeded\|Connected"; then
    pass "MySQL port 3306 accessible"
else
    fail "MySQL port 3306 not accessible"
fi

# Redis port
if nc -zv "$VPS2_IP" 6379 2>&1 | grep -q "succeeded\|Connected"; then
    pass "Redis port 6379 accessible"
else
    fail "Redis port 6379 not accessible"
fi

# Node.js port (if configured)
NODEJS_PORT=$(grep "^NODEJS_REALTIME_URL=" "$LARAVEL_PATH/.env" | grep -oP ':\K[0-9]+' || echo "3002")
if nc -zv "$VPS2_IP" "$NODEJS_PORT" 2>&1 | grep -q "succeeded\|Connected"; then
    pass "Node.js port $NODEJS_PORT accessible"
else
    warn "Node.js port $NODEJS_PORT not accessible (may not be deployed yet)"
fi

# =============================================================================
print_header "4. DATABASE CONNECTION"
# =============================================================================

cd "$LARAVEL_PATH"

# Test Laravel DB connection
if php artisan tinker --execute="try { \$pdo = DB::connection()->getPdo(); echo 'OK'; } catch(Exception \$e) { echo 'FAIL'; }" 2>/dev/null | grep -q "OK"; then
    pass "Laravel database connection"
else
    fail "Laravel database connection"
fi

# Test query execution
user_count=$(php artisan tinker --execute="echo DB::table('users')->count();" 2>/dev/null | tail -1)
if [ -n "$user_count" ] && [ "$user_count" -gt 0 ]; then
    pass "Database query execution (users: $user_count)"
else
    warn "Could not verify database query (users count: $user_count)"
fi

# =============================================================================
print_header "5. REDIS CONNECTION"
# =============================================================================

# Test Laravel Redis connection
if php artisan tinker --execute="try { Redis::connection()->ping(); echo 'OK'; } catch(Exception \$e) { echo 'FAIL'; }" 2>/dev/null | grep -q "OK"; then
    pass "Laravel Redis connection"
else
    fail "Laravel Redis connection"
fi

# Test Redis set/get
if php artisan tinker --execute="Redis::set('test_key', 'test_value'); echo Redis::get('test_key');" 2>/dev/null | grep -q "test_value"; then
    pass "Redis read/write operations"
    # Cleanup
    php artisan tinker --execute="Redis::del('test_key');" 2>/dev/null
else
    fail "Redis read/write operations"
fi

# =============================================================================
print_header "6. QUEUE WORKERS"
# =============================================================================

# Check supervisor workers
worker_count=$(supervisorctl status 2>/dev/null | grep -c "smartline-worker.*RUNNING" || echo "0")
if [ "$worker_count" -gt 0 ]; then
    pass "Queue workers running: $worker_count"
    supervisorctl status | grep smartline-worker
else
    fail "No queue workers running"
fi

# Check queue size
queue_size=$(php artisan queue:size 2>/dev/null | grep -oP '\d+' | head -1 || echo "0")
if [ "$queue_size" -lt 1000 ]; then
    pass "Queue backlog: $queue_size jobs"
else
    warn "Queue backlog high: $queue_size jobs"
fi

# =============================================================================
print_header "7. LARAVEL APPLICATION"
# =============================================================================

# Check config is cached
if [ -f "$LARAVEL_PATH/bootstrap/cache/config.php" ]; then
    pass "Laravel config is cached"
else
    warn "Laravel config not cached"
fi

# Check routes are cached
if [ -f "$LARAVEL_PATH/bootstrap/cache/routes-v7.php" ]; then
    pass "Laravel routes are cached"
else
    warn "Laravel routes not cached"
fi

# Test artisan
if php artisan --version > /dev/null 2>&1; then
    pass "Laravel artisan working"
else
    fail "Laravel artisan not working"
fi

# =============================================================================
print_header "8. API HEALTH CHECK"
# =============================================================================

# Test internal health endpoint
health_response=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/api/health 2>/dev/null)
if [ "$health_response" = "200" ]; then
    pass "API health endpoint (HTTP $health_response)"
else
    fail "API health endpoint (HTTP $health_response)"
fi

# Test detailed health
detailed_health=$(curl -s http://localhost/api/health/detailed 2>/dev/null)
if echo "$detailed_health" | grep -q "database.*healthy\|status.*healthy"; then
    pass "Detailed health check: database healthy"
else
    warn "Detailed health check: could not verify database status"
fi

if echo "$detailed_health" | grep -q "redis.*healthy"; then
    pass "Detailed health check: redis healthy"
else
    warn "Detailed health check: could not verify redis status"
fi

# =============================================================================
print_header "9. LOGS CHECK"
# =============================================================================

# Check for recent errors in Laravel log
error_count=$(tail -100 "$LARAVEL_PATH/storage/logs/laravel.log" 2>/dev/null | grep -c "ERROR\|CRITICAL\|EMERGENCY" || echo "0")
if [ "$error_count" -eq 0 ]; then
    pass "No recent errors in Laravel log"
else
    warn "Found $error_count errors in recent Laravel log"
fi

# Check worker logs
for log in "$LARAVEL_PATH/storage/logs/worker-"*.log; do
    if [ -f "$log" ]; then
        worker_errors=$(tail -50 "$log" 2>/dev/null | grep -c "ERROR\|CRITICAL\|failed" || echo "0")
        log_name=$(basename "$log")
        if [ "$worker_errors" -eq 0 ]; then
            pass "No recent errors in $log_name"
        else
            warn "Found $worker_errors issues in $log_name"
        fi
    fi
done

# =============================================================================
print_header "10. SSL CERTIFICATE"
# =============================================================================

# Check SSL certificate expiry
domain=$(grep "^APP_URL=" "$LARAVEL_PATH/.env" | cut -d '=' -f2 | tr -d ' "'"'"'' | sed 's|https://||' | sed 's|/.*||')
if [ -n "$domain" ]; then
    expiry=$(echo | openssl s_client -servername "$domain" -connect "$domain":443 2>/dev/null | openssl x509 -noout -enddate 2>/dev/null | cut -d= -f2)
    if [ -n "$expiry" ]; then
        expiry_epoch=$(date -d "$expiry" +%s 2>/dev/null || echo "0")
        now_epoch=$(date +%s)
        days_left=$(( (expiry_epoch - now_epoch) / 86400 ))
        if [ "$days_left" -gt 30 ]; then
            pass "SSL certificate valid ($days_left days remaining)"
        elif [ "$days_left" -gt 0 ]; then
            warn "SSL certificate expiring soon ($days_left days remaining)"
        else
            fail "SSL certificate expired or invalid"
        fi
    else
        warn "Could not check SSL certificate"
    fi
fi

# =============================================================================
print_header "VERIFICATION SUMMARY"
# =============================================================================

echo ""
echo "Results:"
echo "  ✓ Passed:   $PASSED"
echo "  ✗ Failed:   $FAILED"
echo "  ⚠ Warnings: $WARNINGS"
echo ""

if [ "$FAILED" -eq 0 ]; then
    echo -e "${GREEN}All critical tests passed!${NC}"
    exit 0
else
    echo -e "${RED}Some tests failed. Please review and fix issues.${NC}"
    exit 1
fi
