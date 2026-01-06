#!/bin/bash
# KYC Verification Service Launcher
# This script starts the FastAPI KYC service if not already running
# The service auto-shuts down after 30 minutes of inactivity

SERVICE_DIR="/var/www/laravel/smartlinevps/smartline-ai"
PID_FILE="$SERVICE_DIR/kyc_service.pid"
LOG_FILE="$SERVICE_DIR/logs/kyc_service.log"
PORT=8100

# Create logs directory if not exists
mkdir -p "$SERVICE_DIR/logs"

# Check if service is already running
is_running() {
    if [ -f "$PID_FILE" ]; then
        PID=$(cat "$PID_FILE")
        if ps -p "$PID" > /dev/null 2>&1; then
            return 0  # Running
        fi
    fi
    
    # Also check if port is in use
    if lsof -i:$PORT > /dev/null 2>&1; then
        return 0  # Running (port in use)
    fi
    
    return 1  # Not running
}

# Start the service
start_service() {
    echo "[$(date)] Starting KYC Verification Service..." >> "$LOG_FILE"
    
    cd "$SERVICE_DIR"
    
    # Activate virtual environment if exists
    if [ -f "venv/bin/activate" ]; then
        source venv/bin/activate
    fi
    
    # Start uvicorn in background
    nohup python -m uvicorn verification_service_main:app --host 0.0.0.0 --port $PORT >> "$LOG_FILE" 2>&1 &
    
    # Save PID
    echo $! > "$PID_FILE"
    
    echo "[$(date)] Service started with PID $(cat $PID_FILE)" >> "$LOG_FILE"
    echo "started"
}

# Main logic
if is_running; then
    # Service already running - just ping it to reset timer
    curl -s "http://localhost:$PORT/health" > /dev/null 2>&1
    echo "already_running"
else
    start_service
fi
