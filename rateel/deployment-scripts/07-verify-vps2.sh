#!/bin/bash
# =============================================================================
# SmartLine VPS2 Verification Script
# =============================================================================
# Run this after VPS2 setup to verify all services are working correctly
#
# USAGE:
#   sudo ./07-verify-vps2.sh
#
# =============================================================================

set -e

# Configuration
NODEJS_PATH="${NODEJS_PATH:-/var/www/realtime-service}"
CREDS_FILE="${CREDS_FILE:-/root/smartline-credentials.txt}"

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

# Load credentials if available
if [ -f "$CREDS_FILE" ]; then
    source "$CREDS_FILE"
    info "Loaded credentials from $CREDS_FILE"
fi

# =============================================================================
print_header "VPS2 VERIFICATION TESTS"
# =============================================================================

# Get private IP
PRIVATE_IP=$(ip addr | grep 'inet 10\.' | awk '{print $2}' | cut -d'/' -f1 | head -1)
info "Private IP: $PRIVATE_IP"

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
total_mem=$(free -g | awk '/^Mem:/{print $2}')
mem_available=$(free -m | awk '/^Mem:/{print $7}')
pass "Total RAM: ${total_mem}GB"
if [ "$mem_available" -gt 1000 ]; then
    pass "Available memory: ${mem_available}MB"
else
    warn "Low available memory: ${mem_available}MB"
fi

# Check load average
load=$(cat /proc/loadavg | awk '{print $1}')
pass "Load average: $load"

# =============================================================================
print_header "2. MYSQL SERVICE"
# =============================================================================

# Check MySQL is running
if systemctl is-active --quiet mysql; then
    pass "MySQL service is running"
else
    fail "MySQL service is not running"
fi

# Check MySQL is listening on private IP
mysql_bind=$(netstat -tlnp 2>/dev/null | grep mysql | grep -oP '\d+\.\d+\.\d+\.\d+' | head -1)
if [ "$mysql_bind" = "$PRIVATE_IP" ]; then
    pass "MySQL bound to private IP ($mysql_bind)"
elif [ "$mysql_bind" = "0.0.0.0" ]; then
    warn "MySQL bound to all interfaces (0.0.0.0) - consider restricting"
else
    info "MySQL bind address: $mysql_bind"
fi

# Check MySQL port
if netstat -tlnp 2>/dev/null | grep -q ":3306"; then
    pass "MySQL listening on port 3306"
else
    fail "MySQL not listening on port 3306"
fi

# Test MySQL root connection
if [ -n "$MYSQL_ROOT_PASSWORD" ]; then
    if mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "SELECT 1;" > /dev/null 2>&1; then
        pass "MySQL root authentication"
    else
        fail "MySQL root authentication"
    fi
fi

# Check database exists
if [ -n "$MYSQL_ROOT_PASSWORD" ] && [ -n "$MYSQL_DATABASE" ]; then
    if mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "USE $MYSQL_DATABASE;" > /dev/null 2>&1; then
        pass "Database '$MYSQL_DATABASE' exists"

        # Check table count
        table_count=$(mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$MYSQL_DATABASE';" -N 2>/dev/null)
        if [ "$table_count" -gt 0 ]; then
            pass "Database has $table_count tables"
        else
            warn "Database appears empty (0 tables)"
        fi
    else
        fail "Database '$MYSQL_DATABASE' not accessible"
    fi
fi

# Check app user exists
if [ -n "$MYSQL_ROOT_PASSWORD" ] && [ -n "$MYSQL_APP_USER" ] && [ -n "$VPS1_PRIVATE_IP" ]; then
    user_exists=$(mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "SELECT User FROM mysql.user WHERE User='$MYSQL_APP_USER' AND Host='$VPS1_PRIVATE_IP';" -N 2>/dev/null)
    if [ -n "$user_exists" ]; then
        pass "App user '$MYSQL_APP_USER'@'$VPS1_PRIVATE_IP' exists"
    else
        fail "App user '$MYSQL_APP_USER'@'$VPS1_PRIVATE_IP' not found"
    fi
fi

# Check MySQL configuration
buffer_pool=$(mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "SHOW VARIABLES LIKE 'innodb_buffer_pool_size';" -N 2>/dev/null | awk '{print $2}')
if [ -n "$buffer_pool" ]; then
    buffer_pool_gb=$(echo "scale=1; $buffer_pool / 1073741824" | bc 2>/dev/null || echo "N/A")
    info "InnoDB buffer pool size: ${buffer_pool_gb}GB"
fi

# =============================================================================
print_header "3. REDIS SERVICE"
# =============================================================================

# Check Redis is running
if systemctl is-active --quiet redis-server; then
    pass "Redis service is running"
else
    fail "Redis service is not running"
fi

# Check Redis is listening
if netstat -tlnp 2>/dev/null | grep -q ":6379"; then
    pass "Redis listening on port 6379"
else
    fail "Redis not listening on port 6379"
fi

# Test Redis connection with password
if [ -n "$REDIS_PASSWORD" ]; then
    if redis-cli -a "$REDIS_PASSWORD" ping 2>/dev/null | grep -q "PONG"; then
        pass "Redis authentication"
    else
        fail "Redis authentication"
    fi

    # Test Redis set/get
    if redis-cli -a "$REDIS_PASSWORD" SET test_key test_value > /dev/null 2>&1; then
        if redis-cli -a "$REDIS_PASSWORD" GET test_key 2>/dev/null | grep -q "test_value"; then
            pass "Redis read/write operations"
            redis-cli -a "$REDIS_PASSWORD" DEL test_key > /dev/null 2>&1
        else
            fail "Redis read/write operations"
        fi
    fi

    # Check Redis memory
    redis_memory=$(redis-cli -a "$REDIS_PASSWORD" INFO memory 2>/dev/null | grep "used_memory_human" | cut -d: -f2 | tr -d '\r')
    if [ -n "$redis_memory" ]; then
        info "Redis memory usage: $redis_memory"
    fi

    # Check maxmemory
    max_memory=$(redis-cli -a "$REDIS_PASSWORD" CONFIG GET maxmemory 2>/dev/null | tail -1)
    if [ -n "$max_memory" ] && [ "$max_memory" != "0" ]; then
        max_memory_gb=$(echo "scale=2; $max_memory / 1073741824" | bc 2>/dev/null || echo "N/A")
        info "Redis maxmemory: ${max_memory_gb}GB"
    fi
fi

# Check Redis requires password
if redis-cli ping 2>/dev/null | grep -q "NOAUTH"; then
    pass "Redis requires authentication"
else
    warn "Redis may not require authentication (security risk)"
fi

# =============================================================================
print_header "4. NODE.JS SERVICE"
# =============================================================================

# Check if Node.js is installed
if command -v node &> /dev/null; then
    node_version=$(node --version)
    pass "Node.js installed: $node_version"
else
    fail "Node.js not installed"
fi

# Check if PM2 is installed
if command -v pm2 &> /dev/null; then
    pass "PM2 installed"
else
    fail "PM2 not installed"
fi

# Check Node.js application directory
if [ -d "$NODEJS_PATH" ]; then
    pass "Node.js application directory exists"

    # Check for package.json
    if [ -f "$NODEJS_PATH/package.json" ]; then
        pass "package.json found"
    else
        warn "package.json not found - application may not be deployed"
    fi

    # Check for .env
    if [ -f "$NODEJS_PATH/.env" ]; then
        pass ".env file found"
    else
        warn ".env file not found"
    fi
else
    warn "Node.js application directory not found at $NODEJS_PATH"
fi

# Check PM2 processes
pm2_status=$(pm2 list 2>/dev/null | grep "smartline-realtime" || echo "")
if echo "$pm2_status" | grep -q "online"; then
    pass "Node.js realtime service running in PM2"

    # Show PM2 status
    pm2 show smartline-realtime 2>/dev/null | grep -E "status|memory|restart" | head -5
else
    warn "Node.js realtime service not running in PM2"
fi

# Test Node.js health endpoint
NODEJS_PORT="${NODEJS_PORT:-3002}"
if curl -s --connect-timeout 5 "http://127.0.0.1:$NODEJS_PORT/health" > /dev/null 2>&1; then
    pass "Node.js health endpoint responding (port $NODEJS_PORT)"

    # Get health details
    health_response=$(curl -s "http://127.0.0.1:$NODEJS_PORT/health" 2>/dev/null)
    if echo "$health_response" | grep -q "redis.*connected\|healthy"; then
        pass "Node.js Redis connection healthy"
    fi
else
    warn "Node.js health endpoint not responding on port $NODEJS_PORT"
fi

# =============================================================================
print_header "5. FIREWALL STATUS"
# =============================================================================

# Check UFW is active
if ufw status | grep -q "Status: active"; then
    pass "UFW firewall is active"

    # Check required ports
    echo ""
    info "Firewall rules:"
    ufw status | grep -E "22|3306|6379|300[0-9]" || info "No specific port rules found"
else
    warn "UFW firewall is not active"
fi

# =============================================================================
print_header "6. CONNECTIVITY FROM VPS1"
# =============================================================================

# Check if VPS1 can be reached (if configured)
if [ -n "$VPS1_PRIVATE_IP" ]; then
    if ping -c 3 "$VPS1_PRIVATE_IP" > /dev/null 2>&1; then
        pass "VPS1 is reachable ($VPS1_PRIVATE_IP)"
    else
        fail "Cannot reach VPS1 ($VPS1_PRIVATE_IP)"
    fi
fi

# =============================================================================
print_header "7. DISK AND STORAGE"
# =============================================================================

# MySQL data directory size
mysql_data_size=$(du -sh /var/lib/mysql 2>/dev/null | awk '{print $1}')
info "MySQL data directory: $mysql_data_size"

# Redis data directory size
redis_data_size=$(du -sh /var/lib/redis 2>/dev/null | awk '{print $1}')
info "Redis data directory: $redis_data_size"

# Check for backup directory
if [ -d "/var/backups/smartline" ]; then
    backup_count=$(ls -1 /var/backups/smartline/*.sql.gz 2>/dev/null | wc -l)
    pass "Backup directory exists ($backup_count backups)"
else
    warn "Backup directory not found"
fi

# =============================================================================
print_header "8. LOGS CHECK"
# =============================================================================

# Check MySQL error log
mysql_errors=$(tail -100 /var/log/mysql/error.log 2>/dev/null | grep -c "ERROR" || echo "0")
if [ "$mysql_errors" -eq 0 ]; then
    pass "No recent errors in MySQL log"
else
    warn "Found $mysql_errors errors in MySQL log"
fi

# Check Redis log
redis_errors=$(tail -100 /var/log/redis/redis-server.log 2>/dev/null | grep -c "ERROR\|FATAL" || echo "0")
if [ "$redis_errors" -eq 0 ]; then
    pass "No recent errors in Redis log"
else
    warn "Found $redis_errors errors in Redis log"
fi

# Check Node.js logs (if exists)
if [ -d "$NODEJS_PATH/logs" ]; then
    nodejs_errors=$(tail -100 "$NODEJS_PATH/logs/err.log" 2>/dev/null | grep -c "Error\|error" || echo "0")
    if [ "$nodejs_errors" -eq 0 ]; then
        pass "No recent errors in Node.js error log"
    else
        warn "Found $nodejs_errors errors in Node.js error log"
    fi
fi

# =============================================================================
print_header "9. SECURITY CHECKS"
# =============================================================================

# Check MySQL not bound to public IP
public_ip=$(curl -s ifconfig.me 2>/dev/null || echo "")
if [ -n "$public_ip" ]; then
    if netstat -tlnp 2>/dev/null | grep "3306" | grep -q "$public_ip"; then
        fail "MySQL appears to be bound to public IP ($public_ip) - SECURITY RISK"
    else
        pass "MySQL not exposed on public IP"
    fi
fi

# Check Redis not bound to public IP
if [ -n "$public_ip" ]; then
    if netstat -tlnp 2>/dev/null | grep "6379" | grep -q "$public_ip"; then
        fail "Redis appears to be bound to public IP ($public_ip) - SECURITY RISK"
    else
        pass "Redis not exposed on public IP"
    fi
fi

# Check credentials file permissions
if [ -f "$CREDS_FILE" ]; then
    creds_perms=$(stat -c "%a" "$CREDS_FILE")
    if [ "$creds_perms" = "600" ]; then
        pass "Credentials file has correct permissions (600)"
    else
        warn "Credentials file permissions: $creds_perms (should be 600)"
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
    echo ""
    echo "VPS2 is ready. Next steps:"
    echo "1. Import database from VPS1 (if not done)"
    echo "2. Deploy and start Node.js application"
    echo "3. Run VPS1 migration script"
    exit 0
else
    echo -e "${RED}Some tests failed. Please review and fix issues.${NC}"
    exit 1
fi
