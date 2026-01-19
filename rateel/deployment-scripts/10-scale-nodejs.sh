#!/bin/bash
# =============================================================================
# SmartLine Node.js Scaling Script
# =============================================================================
# Quick script to scale Node.js instances on VPS2
#
# USAGE:
#   sudo ./10-scale-nodejs.sh [number_of_instances]
#
# EXAMPLES:
#   sudo ./10-scale-nodejs.sh 4    # Scale to 4 instances
#   sudo ./10-scale-nodejs.sh 2    # Scale back to 2 instances
#
# =============================================================================

set -e

# Configuration
NODEJS_PATH="${NODEJS_PATH:-/var/www/realtime-service}"
ECOSYSTEM_FILE="$NODEJS_PATH/ecosystem.config.js"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_header() {
    echo ""
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}"
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_info() {
    echo -e "${YELLOW}ℹ${NC} $1"
}

confirm_action() {
    read -p "$(echo -e ${YELLOW}$1 [y/N]: ${NC})" response
    case "$response" in
        [yY][eE][sS]|[yY]) return 0 ;;
        *) return 1 ;;
    esac
}

# =============================================================================
print_header "NODE.JS SCALING SCRIPT"
# =============================================================================

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    print_error "Please run as root or with sudo"
    exit 1
fi

# Get target instance count
TARGET_INSTANCES="$1"

if [ -z "$TARGET_INSTANCES" ]; then
    print_error "Usage: $0 <number_of_instances>"
    echo ""
    echo "Examples:"
    echo "  $0 2    # Scale to 2 instances"
    echo "  $0 4    # Scale to 4 instances"
    echo "  $0 6    # Scale to 6 instances (max recommended)"
    exit 1
fi

# Validate instance count
if ! [[ "$TARGET_INSTANCES" =~ ^[1-9][0-9]?$ ]] || [ "$TARGET_INSTANCES" -gt 8 ]; then
    print_error "Invalid instance count. Please use 1-8."
    exit 1
fi

# Check if Node.js directory exists
if [ ! -d "$NODEJS_PATH" ]; then
    print_error "Node.js directory not found at $NODEJS_PATH"
    exit 1
fi

if [ ! -f "$ECOSYSTEM_FILE" ]; then
    print_error "PM2 ecosystem file not found at $ECOSYSTEM_FILE"
    exit 1
fi

# =============================================================================
print_header "PRE-SCALING CHECKS"
# =============================================================================

# Get current instance count
CURRENT_INSTANCES=$(pm2 jlist 2>/dev/null | jq -r '.[] | select(.name=="smartline-realtime") | .pm2_env.instances // 0' | head -1)

if [ -z "$CURRENT_INSTANCES" ] || [ "$CURRENT_INSTANCES" = "null" ]; then
    # Fallback: count running instances
    CURRENT_INSTANCES=$(pm2 status 2>/dev/null | grep "smartline-realtime" | grep "online" | wc -l)
fi

print_info "Current instances: $CURRENT_INSTANCES"
print_info "Target instances: $TARGET_INSTANCES"

if [ "$CURRENT_INSTANCES" = "$TARGET_INSTANCES" ]; then
    print_warning "Already running $TARGET_INSTANCES instances. No changes needed."
    exit 0
fi

# Check available memory
TOTAL_MEM=$(free -g | awk '/^Mem:/{print $2}')
AVAILABLE_MEM=$(free -g | awk '/^Mem:/{print $7}')

print_info "Total RAM: ${TOTAL_MEM}GB"
print_info "Available RAM: ${AVAILABLE_MEM}GB"

# Calculate required memory
REQUIRED_MEM=$(echo "scale=1; $TARGET_INSTANCES * 0.6" | bc)
print_info "Required for Node.js: ${REQUIRED_MEM}GB"

if (( $(echo "$AVAILABLE_MEM < $REQUIRED_MEM" | bc -l) )); then
    print_warning "Low available memory!"
    print_warning "Available: ${AVAILABLE_MEM}GB, Required: ${REQUIRED_MEM}GB"
    if ! confirm_action "Continue anyway?"; then
        exit 0
    fi
fi

# Recommendations
echo ""
if [ "$TARGET_INSTANCES" -le 2 ]; then
    print_info "2 instances: Good for 400-600 connections"
elif [ "$TARGET_INSTANCES" -le 4 ]; then
    print_success "4 instances: Optimal (1 per CPU core) - 800-1,200 connections"
elif [ "$TARGET_INSTANCES" -le 6 ]; then
    print_warning "5-6 instances: High load - CPU oversubscription expected"
else
    print_error "7+ instances: Not recommended - consider dedicated VPS"
    if ! confirm_action "Continue anyway?"; then
        exit 0
    fi
fi

echo ""
if ! confirm_action "Scale from $CURRENT_INSTANCES to $TARGET_INSTANCES instances?"; then
    print_info "Scaling cancelled"
    exit 0
fi

# =============================================================================
print_header "BACKUP CURRENT CONFIGURATION"
# =============================================================================

cd "$NODEJS_PATH"

BACKUP_FILE="ecosystem.config.js.backup.$(date +%Y%m%d_%H%M%S)"
cp ecosystem.config.js "$BACKUP_FILE"
print_success "Backed up to: $BACKUP_FILE"

# =============================================================================
print_header "UPDATE PM2 CONFIGURATION"
# =============================================================================

# Update instances count in ecosystem file
sed -i "s/instances: [0-9]\+/instances: $TARGET_INSTANCES/" ecosystem.config.js

# Verify the change
UPDATED_COUNT=$(grep "instances:" ecosystem.config.js | grep -oP '\d+')
if [ "$UPDATED_COUNT" = "$TARGET_INSTANCES" ]; then
    print_success "Updated ecosystem.config.js: instances = $TARGET_INSTANCES"
else
    print_error "Failed to update ecosystem.config.js"
    print_info "Restoring backup..."
    cp "$BACKUP_FILE" ecosystem.config.js
    exit 1
fi

# =============================================================================
print_header "APPLYING CHANGES"
# =============================================================================

print_info "Reloading PM2 with new configuration (zero-downtime)..."

# Reload with new configuration
if pm2 reload ecosystem.config.js 2>&1; then
    print_success "PM2 reload initiated"
else
    print_error "PM2 reload failed"
    print_info "Restoring backup..."
    cp "$BACKUP_FILE" ecosystem.config.js
    pm2 reload ecosystem.config.js
    exit 1
fi

# Wait for instances to stabilize
print_info "Waiting for instances to stabilize..."
sleep 10

# =============================================================================
print_header "VERIFICATION"
# =============================================================================

# Check instance count
RUNNING_INSTANCES=$(pm2 status 2>/dev/null | grep "smartline-realtime" | grep "online" | wc -l)

if [ "$RUNNING_INSTANCES" = "$TARGET_INSTANCES" ]; then
    print_success "All $TARGET_INSTANCES instances are running"
else
    print_error "Expected $TARGET_INSTANCES instances, but found $RUNNING_INSTANCES running"
    print_info "Check status with: pm2 status"
fi

# Show PM2 status
echo ""
pm2 status

# Check for errors
echo ""
print_info "Checking for recent errors..."
ERROR_COUNT=$(pm2 logs smartline-realtime --nostream --lines 50 --err 2>/dev/null | grep -i "error" | wc -l)

if [ "$ERROR_COUNT" -eq 0 ]; then
    print_success "No errors found in recent logs"
else
    print_warning "Found $ERROR_COUNT errors in recent logs"
    print_info "View with: pm2 logs smartline-realtime --err"
fi

# Save PM2 configuration
print_info "Saving PM2 configuration..."
pm2 save
print_success "PM2 configuration saved"

# =============================================================================
print_header "SCALING COMPLETE"
# =============================================================================

echo ""
echo "Summary:"
echo "  Before: $CURRENT_INSTANCES instances"
echo "  After:  $RUNNING_INSTANCES instances"
echo "  Backup: $BACKUP_FILE"
echo ""

# Calculate new capacity
MIN_CONNECTIONS=$((TARGET_INSTANCES * 200))
MAX_CONNECTIONS=$((TARGET_INSTANCES * 300))

echo "New Capacity:"
echo "  Concurrent connections: $MIN_CONNECTIONS - $MAX_CONNECTIONS"
echo "  Estimated RAM usage: ~${REQUIRED_MEM}GB"
echo ""

print_info "Monitor performance with:"
echo "  pm2 monit              # Real-time monitoring"
echo "  pm2 logs smartline-realtime  # View logs"
echo "  curl http://localhost:3002/health | jq  # Health check"
echo ""

if [ "$RUNNING_INSTANCES" != "$TARGET_INSTANCES" ]; then
    print_warning "Scaling completed but instance count doesn't match target"
    print_info "To rollback:"
    echo "  cp $BACKUP_FILE ecosystem.config.js"
    echo "  pm2 reload ecosystem.config.js"
    exit 1
fi

print_success "Scaling successful! 🚀"
echo ""
