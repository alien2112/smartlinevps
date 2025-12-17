# SmartLine - Start All Services
# This script starts Laravel, Node.js, and checks Redis

Write-Host "=====================================================================" -ForegroundColor Cyan
Write-Host "  SmartLine Platform - Starting All Services" -ForegroundColor Cyan
Write-Host "=====================================================================" -ForegroundColor Cyan
Write-Host ""

# Check if Redis is running
Write-Host "[1/4] Checking Redis..." -ForegroundColor Yellow
$redisRunning = $false
try {
    $redisCheck = redis-cli ping 2>&1
    if ($redisCheck -eq "PONG") {
        Write-Host "   ✓ Redis is running" -ForegroundColor Green
        $redisRunning = $true
    }
} catch {
    Write-Host "   ✗ Redis is NOT running" -ForegroundColor Red
    Write-Host "   Please start Redis first:" -ForegroundColor Yellow
    Write-Host "   - Windows: redis-server" -ForegroundColor Yellow
    Write-Host "   - Linux/Mac: sudo systemctl start redis" -ForegroundColor Yellow
    Write-Host ""
    Read-Host "Press Enter to continue anyway or Ctrl+C to exit"
}

# Check if MySQL is running
Write-Host ""
Write-Host "[2/4] Checking MySQL..." -ForegroundColor Yellow
try {
    $mysqlCheck = mysql -u root -proot -e "SELECT 1" 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "   ✓ MySQL is running" -ForegroundColor Green
    } else {
        Write-Host "   ⚠ MySQL check uncertain - continuing anyway" -ForegroundColor Yellow
    }
} catch {
    Write-Host "   ⚠ Could not verify MySQL status - continuing anyway" -ForegroundColor Yellow
}

# Start Laravel in a new window
Write-Host ""
Write-Host "[3/4] Starting Laravel API (Port 8000)..." -ForegroundColor Yellow
$laravelPath = Get-Location
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$laravelPath'; Write-Host '🚀 Starting Laravel API...' -ForegroundColor Cyan; php artisan serve; Read-Host 'Press Enter to close'"
Write-Host "   ✓ Laravel started in new window" -ForegroundColor Green
Start-Sleep -Seconds 2

# Start Node.js in a new window
Write-Host ""
Write-Host "[4/4] Starting Node.js Real-time Service (Port 3000)..." -ForegroundColor Yellow
$nodejsPath = Join-Path $laravelPath "realtime-service"
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$nodejsPath'; Write-Host '🚀 Starting Node.js Real-time Service...' -ForegroundColor Cyan; npm run dev; Read-Host 'Press Enter to close'"
Write-Host "   ✓ Node.js started in new window" -ForegroundColor Green
Start-Sleep -Seconds 3

# Display status
Write-Host ""
Write-Host "=====================================================================" -ForegroundColor Cyan
Write-Host "  All Services Started!" -ForegroundColor Green
Write-Host "=====================================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Service Status:" -ForegroundColor White
Write-Host "  • Laravel API:        http://localhost:8000" -ForegroundColor White
Write-Host "  • Node.js Realtime:   http://localhost:3000" -ForegroundColor White
Write-Host "  • Redis:              $(if($redisRunning){'Running ✓'}else{'Not Running ✗'})" -ForegroundColor White
Write-Host ""
Write-Host "Quick Health Checks:" -ForegroundColor Yellow
Write-Host "  Laravel:  curl http://localhost:8000/api/health" -ForegroundColor Gray
Write-Host "  Node.js:  curl http://localhost:3000/health" -ForegroundColor Gray
Write-Host "  Redis:    redis-cli ping" -ForegroundColor Gray
Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "  1. Import Postman collection: SmartLine_Laravel_NodeJS_Testing.postman_collection.json" -ForegroundColor White
Write-Host "  2. Read the guide: TESTING_LARAVEL_NODEJS_TOGETHER.md" -ForegroundColor White
Write-Host "  3. Test WebSocket: cd realtime-service && node test-websocket.js" -ForegroundColor White
Write-Host ""
Write-Host "To stop services, close the PowerShell windows or press Ctrl+C in each" -ForegroundColor Gray
Write-Host ""
Write-Host "Press Enter to open monitoring view..." -ForegroundColor Yellow
Read-Host

# Open Redis monitor in a new window
Write-Host "Opening Redis monitor..." -ForegroundColor Yellow
Start-Process powershell -ArgumentList "-NoExit", "-Command", "Write-Host '📊 Redis Monitor - All commands will appear here' -ForegroundColor Cyan; Write-Host 'Press Ctrl+C to stop' -ForegroundColor Yellow; Write-Host ''; redis-cli MONITOR"

Write-Host ""
Write-Host "✓ All done! Happy testing!" -ForegroundColor Green
Write-Host ""
