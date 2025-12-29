#!/bin/bash
# ============================================================================
# Route Caching Deployment Script for Production
# SmartLine Ride-Hailing Backend
# ============================================================================
#
# This script safely enables Laravel route caching on production.
# Run this after each deployment or when routes change.
#
# Usage:
#   chmod +x scripts/deploy-routes.sh
#   ./scripts/deploy-routes.sh
#
# ============================================================================

set -e  # Exit on any error

echo "=================================================="
echo "🚀 SmartLine Route Cache Deployment"
echo "=================================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Change to project directory (adjust if needed)
cd "$(dirname "$0")/.."

echo ""
echo "📁 Working directory: $(pwd)"

# Step 1: Check for closure routes (safety check)
echo ""
echo "🔍 Step 1: Checking for closure routes..."

CLOSURE_COUNT=$(grep -r "Route::\(get\|post\|put\|patch\|delete\|any\|match\)\s*([^,]*,\s*function\s*(" routes/ Modules/*/Routes/ 2>/dev/null | wc -l || true)

if [ "$CLOSURE_COUNT" -gt 0 ]; then
    echo -e "${RED}❌ ERROR: Found $CLOSURE_COUNT closure route(s)!${NC}"
    echo ""
    echo "Closure routes will break route caching. Convert them to controller methods."
    echo ""
    echo "Problematic routes:"
    grep -rn "Route::\(get\|post\|put\|patch\|delete\|any\|match\)\s*([^,]*,\s*function\s*(" routes/ Modules/*/Routes/ 2>/dev/null || true
    echo ""
    echo -e "${RED}Aborting deployment. Fix closures before enabling route cache.${NC}"
    exit 1
fi

echo -e "${GREEN}✅ No closure routes found. Safe to proceed.${NC}"

# Step 2: Clear existing route cache
echo ""
echo "🧹 Step 2: Clearing existing route cache..."
php artisan route:clear
echo -e "${GREEN}✅ Route cache cleared.${NC}"

# Step 3: Clear config cache (routes may depend on config)
echo ""
echo "🧹 Step 3: Clearing config cache..."
php artisan config:clear
echo -e "${GREEN}✅ Config cache cleared.${NC}"

# Step 4: Rebuild config cache
echo ""
echo "📦 Step 4: Rebuilding config cache..."
php artisan config:cache
echo -e "${GREEN}✅ Config cache rebuilt.${NC}"

# Step 5: Test route listing (validates all routes work)
echo ""
echo "🧪 Step 5: Validating routes..."
if php artisan route:list --json > /dev/null 2>&1; then
    ROUTE_COUNT=$(php artisan route:list --json | grep -c '"uri"' || echo "0")
    echo -e "${GREEN}✅ Route validation passed. Found $ROUTE_COUNT routes.${NC}"
else
    echo -e "${RED}❌ Route validation failed! Check route definitions.${NC}"
    exit 1
fi

# Step 6: Enable route caching
echo ""
echo "🚀 Step 6: Building route cache..."
php artisan route:cache

# Step 7: Verify cache file exists
echo ""
echo "✅ Step 7: Verifying cache file..."
if [ -f "bootstrap/cache/routes-v7.php" ]; then
    echo -e "${GREEN}✅ Route cache file created: bootstrap/cache/routes-v7.php${NC}"
    ls -la bootstrap/cache/routes-v7.php
else
    echo -e "${YELLOW}⚠️  Legacy route cache location - checking routes.php...${NC}"
    if [ -f "bootstrap/cache/routes.php" ]; then
        echo -e "${GREEN}✅ Route cache file created: bootstrap/cache/routes.php${NC}"
        ls -la bootstrap/cache/routes.php
    else
        echo -e "${RED}❌ Route cache file not found!${NC}"
        exit 1
    fi
fi

echo ""
echo "=================================================="
echo -e "${GREEN}🎉 Route caching enabled successfully!${NC}"
echo "=================================================="
echo ""
echo "Performance benefits:"
echo "  • Route registration: ~5-10ms → ~1ms"
echo "  • Memory usage: Reduced"
echo "  • API response time: Improved"
echo ""
echo "To disable for development:"
echo "  php artisan route:clear"
echo ""
