#!/bin/bash

# KYC Verification Service Health Check and Startup Script

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}KYC Verification Service Status${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Get configuration from Laravel config
cd /var/www/laravel/smartlinevps/rateel

# Use Laravel to get config (more reliable)
FASTAPI_URL=$(php artisan tinker --execute="echo config('verification.fastapi.base_url', 'http://localhost:8100');" 2>/dev/null | tail -1 | xargs)
FASTAPI_KEY=$(php artisan tinker --execute="echo config('verification.fastapi.api_key', '');" 2>/dev/null | tail -1 | xargs)

# Default if empty
if [ -z "$FASTAPI_URL" ]; then
    FASTAPI_URL="http://localhost:8100"
fi

# Extract host and port
HOST=$(echo "$FASTAPI_URL" | sed -E 's|https?://([^:/]+).*|\1|')
PORT=$(echo "$FASTAPI_URL" | sed -E 's|.*:([0-9]+).*|\1|')

if [ -z "$PORT" ]; then
    PORT="8100"
fi

if [ -z "$HOST" ] || [ "$HOST" = "$FASTAPI_URL" ]; then
    HOST="localhost"
fi

echo -e "${YELLOW}Configuration:${NC}"
echo "  URL: $FASTAPI_URL"
echo "  Host: $HOST"
echo "  Port: $PORT"
echo "  API Key: ${FASTAPI_KEY:0:10}..." 
echo ""

# Check if process is running
echo -e "${YELLOW}Checking process...${NC}"
PYTHON_PID=$(ps aux | grep -i "verification_service_main.py" | grep -v grep | awk '{print $2}')

if [ -n "$PYTHON_PID" ]; then
    echo -e "${GREEN}✓ Python service process found (PID: $PYTHON_PID)${NC}"
else
    echo -e "${RED}✗ Python service process not found${NC}"
fi
echo ""

# Check if port is listening
echo -e "${YELLOW}Checking port $PORT...${NC}"
if command -v netstat &> /dev/null; then
    PORT_CHECK=$(netstat -tlnp 2>/dev/null | grep ":$PORT " || echo "")
elif command -v ss &> /dev/null; then
    PORT_CHECK=$(ss -tlnp 2>/dev/null | grep ":$PORT " || echo "")
else
    PORT_CHECK=""
fi

if [ -n "$PORT_CHECK" ]; then
    echo -e "${GREEN}✓ Port $PORT is listening${NC}"
    echo "  $PORT_CHECK"
else
    echo -e "${RED}✗ Port $PORT is not listening${NC}"
fi
echo ""

# Test health endpoint
echo -e "${YELLOW}Testing health endpoint...${NC}"
HEALTH_URL="${FASTAPI_URL}/health"
HEALTH_RESPONSE=$(curl -s -w "\n%{http_code}" "$HEALTH_URL" 2>&1)
HTTP_CODE=$(echo "$HEALTH_RESPONSE" | tail -n1)
BODY=$(echo "$HEALTH_RESPONSE" | sed '$d')

if [ "$HTTP_CODE" = "200" ]; then
    echo -e "${GREEN}✓ Health check passed${NC}"
    echo "  Response: $BODY"
else
    echo -e "${RED}✗ Health check failed (HTTP $HTTP_CODE)${NC}"
    echo "  Response: $BODY"
fi
echo ""

# Test from Laravel
echo -e "${YELLOW}Testing from Laravel...${NC}"
cd /var/www/laravel/smartlinevps/rateel
LARAVEL_TEST=$(php artisan tinker --execute="
try {
    \$service = app(\Modules\UserManagement\Service\Interface\FastApiClientServiceInterface::class);
    \$healthy = \$service->healthCheck();
    echo json_encode(['success' => true, 'healthy' => \$healthy]);
} catch (\Exception \$e) {
    echo json_encode(['success' => false, 'error' => \$e->getMessage()]);
}
" 2>&1)

if echo "$LARAVEL_TEST" | grep -q '"success":true'; then
    echo -e "${GREEN}✓ Laravel can connect to service${NC}"
else
    echo -e "${RED}✗ Laravel cannot connect to service${NC}"
    echo "$LARAVEL_TEST"
fi
echo ""

# Summary
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Summary${NC}"
echo -e "${BLUE}========================================${NC}"

ALL_OK=true

if [ -z "$PYTHON_PID" ]; then
    echo -e "${RED}✗ Process not running${NC}"
    ALL_OK=false
fi

if [ -z "$PORT_CHECK" ]; then
    echo -e "${RED}✗ Port not listening${NC}"
    ALL_OK=false
fi

if [ "$HTTP_CODE" != "200" ]; then
    echo -e "${RED}✗ Health check failed${NC}"
    ALL_OK=false
fi

if [ "$ALL_OK" = true ]; then
    echo -e "${GREEN}✓ All checks passed - Service is running correctly${NC}"
    exit 0
else
    echo -e "${YELLOW}⚠ Service has issues - Check above for details${NC}"
    echo ""
    echo -e "${YELLOW}To start the service:${NC}"
    echo "  cd /var/www/laravel/smartlinevps/smartline-ai"
    echo "  source venv/bin/activate"
    echo "  python verification_service_main.py"
    exit 1
fi
