#!/bin/bash

# ============================================
# SmartLine AI Chatbot V3 - VPS Deployment Script
# ============================================

set -e

echo "🚗 SmartLine AI Chatbot V3 Deployment"
echo "======================================"

# Configuration
CHATBOT_DIR="/var/www/smartline/ai-chat-bot-v3"
REPO_DIR="/var/www/smartline"  # Adjust to your repo location

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# Step 1: Navigate to chatbot directory
echo -e "${YELLOW}Step 1: Navigating to chatbot directory...${NC}"
cd $CHATBOT_DIR

# Step 2: Install dependencies
echo -e "${YELLOW}Step 2: Installing dependencies...${NC}"
npm install --production

# Step 3: Create logs directory
echo -e "${YELLOW}Step 3: Creating logs directory...${NC}"
mkdir -p logs

# Step 4: Check if .env exists
if [ ! -f ".env" ]; then
    echo -e "${YELLOW}Step 4: Creating .env from production template...${NC}"
    cp .env.production .env
    echo -e "${RED}⚠️  IMPORTANT: Edit .env with your actual credentials!${NC}"
    echo -e "${RED}   - Set GROQ_API_KEY${NC}"
    echo -e "${RED}   - Set DB_PASSWORD${NC}"
    echo -e "${RED}   - Set ADMIN_API_KEY${NC}"
    exit 1
else
    echo -e "${GREEN}Step 4: .env file exists ✓${NC}"
fi

# Step 5: Check PM2
echo -e "${YELLOW}Step 5: Checking PM2 installation...${NC}"
if ! command -v pm2 &> /dev/null; then
    echo "Installing PM2..."
    npm install -g pm2
fi

# Step 6: Stop existing chatbot if running
echo -e "${YELLOW}Step 6: Stopping existing chatbot (if any)...${NC}"
pm2 stop smartline-chatbot 2>/dev/null || true
pm2 delete smartline-chatbot 2>/dev/null || true

# Step 7: Start with PM2
echo -e "${YELLOW}Step 7: Starting chatbot with PM2...${NC}"
pm2 start ecosystem.config.js --env production

# Step 8: Save PM2 process list
echo -e "${YELLOW}Step 8: Saving PM2 process list...${NC}"
pm2 save

# Step 9: Health check
echo -e "${YELLOW}Step 9: Running health check...${NC}"
sleep 3
HEALTH_CHECK=$(curl -s http://localhost:3001/health || echo "failed")

if [[ $HEALTH_CHECK == *"ok"* ]]; then
    echo -e "${GREEN}✅ Chatbot is running and healthy!${NC}"
else
    echo -e "${RED}❌ Health check failed. Check logs with: pm2 logs smartline-chatbot${NC}"
    exit 1
fi

# Step 10: Show status
echo -e "${YELLOW}Step 10: PM2 Status:${NC}"
pm2 list

echo ""
echo -e "${GREEN}======================================"
echo "🎉 Deployment Complete!"
echo "======================================"
echo ""
echo "Chatbot is running on: http://localhost:3001"
echo ""
echo "Useful commands:"
echo "  pm2 logs smartline-chatbot    # View logs"
echo "  pm2 restart smartline-chatbot # Restart"
echo "  pm2 stop smartline-chatbot    # Stop"
echo "  pm2 monit                     # Monitor"
echo ""
echo "Don't forget to:"
echo "1. Add nginx config (see nginx-chatbot.conf)"
echo "2. Reload nginx: sudo nginx -t && sudo systemctl reload nginx"
echo "======================================${NC}"
