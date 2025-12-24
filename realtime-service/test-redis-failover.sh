#!/bin/bash
#
# Redis Failover Test Script
# Demonstrates automatic in-memory fallback when Redis goes down
#

set -e

echo "==================================================================="
echo "Redis Failover Test - SmartLine Realtime Service"
echo "==================================================================="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}Step 1: Check initial Redis status${NC}"
echo "-------------------------------------------------------------------"
curl -s http://localhost:3000/health | jq '.redis'
echo ""

echo -e "${YELLOW}Step 2: Stopping Redis to trigger failover...${NC}"
echo "-------------------------------------------------------------------"
sudo systemctl stop redis
echo "Redis service stopped"
echo ""

echo -e "${YELLOW}Step 3: Waiting 15 seconds for failover detection...${NC}"
sleep 15
echo ""

echo -e "${RED}Step 4: Check Redis status (should show in-memory fallback)${NC}"
echo "-------------------------------------------------------------------"
curl -s http://localhost:3000/health | jq '.redis'
echo ""

echo -e "${YELLOW}Step 5: Check PM2 logs for failover messages${NC}"
echo "-------------------------------------------------------------------"
pm2 logs smartline-realtime --lines 20 --nostream | grep -E "(fallback|IN-MEMORY|DEGRADED)" || echo "No failover logs yet (may take a moment)"
echo ""

echo -e "${GREEN}Step 6: Restarting Redis...${NC}"
echo "-------------------------------------------------------------------"
sudo systemctl start redis
echo "Redis service started"
echo ""

echo -e "${YELLOW}Step 7: Waiting 10 seconds for recovery...${NC}"
sleep 10
echo ""

echo -e "${GREEN}Step 8: Check Redis status (should show recovery)${NC}"
echo "-------------------------------------------------------------------"
curl -s http://localhost:3000/health | jq '.redis'
echo ""

echo -e "${YELLOW}Step 9: Check PM2 logs for recovery messages${NC}"
echo "-------------------------------------------------------------------"
pm2 logs smartline-realtime --lines 20 --nostream | grep -E "(recovery|Redis mode)" || echo "No recovery logs yet"
echo ""

echo "==================================================================="
echo -e "${GREEN}Failover test complete!${NC}"
echo "==================================================================="
echo ""
echo "Summary:"
echo "  - Service should have automatically switched to in-memory mode"
echo "  - When Redis came back, it should have recovered automatically"
echo "  - No manual intervention required"
echo ""
