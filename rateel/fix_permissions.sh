#!/bin/bash

# Laravel Application Permissions Fix Script
# Fixes all permission issues for Laravel application

set -e

APP_PATH="/var/www/laravel/smartlinevps/rateel"
WEB_USER="www-data"
WEB_GROUP="www-data"

echo "=========================================="
echo "Laravel Permissions Fix Script"
echo "=========================================="
echo ""
echo "Application Path: $APP_PATH"
echo "Web User: $WEB_USER"
echo "Web Group: $WEB_GROUP"
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

cd "$APP_PATH"

# Step 1: Fix file permissions (644 for files, 755 for directories)
echo -e "${BLUE}Step 1: Fixing file permissions...${NC}"
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
echo -e "${GREEN}✓ File permissions fixed${NC}"
echo ""

# Step 2: Fix ownership (keep root ownership but ensure group is www-data)
echo -e "${BLUE}Step 2: Fixing ownership...${NC}"
# Set group to www-data for all files
chgrp -R "$WEB_GROUP" .
echo -e "${GREEN}✓ Ownership fixed (group set to $WEB_GROUP)${NC}"
echo ""

# Step 3: Make specific directories writable by web server
echo -e "${BLUE}Step 3: Making storage and cache writable...${NC}"
if [ -d "storage" ]; then
    chmod -R 775 storage
    chmod -R 775 bootstrap/cache 2>/dev/null || true
    echo -e "${GREEN}✓ Storage and cache directories made writable${NC}"
else
    echo -e "${YELLOW}⚠ Storage directory not found${NC}"
fi
echo ""

# Step 4: Fix specific problematic files
echo -e "${BLUE}Step 4: Fixing specific problematic files...${NC}"
PROBLEMATIC_FILES=(
    "app/Http/Controllers/Api/V2/Driver/DriverOnboardingController.php"
    "app/Services/Driver/DriverOnboardingService.php"
    "app/Services/Driver/OnboardingRateLimiter.php"
    "app/Enums/DriverOnboardingState.php"
)

for file in "${PROBLEMATIC_FILES[@]}"; do
    if [ -f "$file" ]; then
        chmod 644 "$file"
        chgrp "$WEB_GROUP" "$file"
        echo -e "${GREEN}✓ Fixed: $file${NC}"
    fi
done
echo ""

# Step 5: Ensure all PHP files are readable
echo -e "${BLUE}Step 5: Ensuring all PHP files are readable...${NC}"
find . -type f -name "*.php" -exec chmod 644 {} \;
PHP_COUNT=$(find . -type f -name "*.php" | wc -l)
echo -e "${GREEN}✓ Fixed permissions for $PHP_COUNT PHP files${NC}"
echo ""

# Step 6: Fix vendor directory (if exists)
if [ -d "vendor" ]; then
    echo -e "${BLUE}Step 6: Fixing vendor directory...${NC}"
    find vendor -type f -exec chmod 644 {} \;
    find vendor -type d -exec chmod 755 {} \;
    echo -e "${GREEN}✓ Vendor directory fixed${NC}"
    echo ""
fi

# Step 7: Fix public directory
if [ -d "public" ]; then
    echo -e "${BLUE}Step 7: Fixing public directory...${NC}"
    find public -type f -exec chmod 644 {} \;
    find public -type d -exec chmod 755 {} \;
    echo -e "${GREEN}✓ Public directory fixed${NC}"
    echo ""
fi

# Step 8: Verify critical files
echo -e "${BLUE}Step 8: Verifying critical files...${NC}"
CRITICAL_FILE="/var/www/laravel/smartlinevps/rateel/app/Http/Controllers/Api/V2/Driver/DriverOnboardingController.php"
if [ -f "$CRITICAL_FILE" ]; then
    PERMS=$(stat -c "%a" "$CRITICAL_FILE")
    OWNER=$(stat -c "%U:%G" "$CRITICAL_FILE")
    echo "  File: $CRITICAL_FILE"
    echo "  Permissions: $PERMS"
    echo "  Owner: $OWNER"
    
    if [ "$PERMS" = "644" ] || [ "$PERMS" = "755" ]; then
        echo -e "${GREEN}✓ Critical file has correct permissions${NC}"
    else
        echo -e "${RED}✗ Critical file still has wrong permissions${NC}"
    fi
fi
echo ""

# Summary
echo -e "${GREEN}=========================================="
echo "Permissions Fix Complete"
echo "==========================================${NC}"
echo ""
echo "Summary:"
echo "  - All files: 644 (readable by all)"
echo "  - All directories: 755 (executable by all)"
echo "  - Storage/Cache: 775 (writable by $WEB_GROUP)"
echo "  - Group ownership: $WEB_GROUP"
echo ""
echo -e "${YELLOW}Note: Files are owned by root:$WEB_GROUP for security${NC}"
echo -e "${YELLOW}The web server ($WEB_USER) can read all files through group permissions${NC}"
echo ""
