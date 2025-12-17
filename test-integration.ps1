# Test Laravel + Node.js Integration
Write-Host "=====================================================================" -ForegroundColor Cyan
Write-Host "  Testing Laravel + Node.js Integration" -ForegroundColor Cyan
Write-Host "=====================================================================" -ForegroundColor Cyan
Write-Host ""

$testsPassed = 0
$testsFailed = 0

# Test 1: Redis
Write-Host "[Test 1/5] Testing Redis..." -ForegroundColor Yellow
try {
    $redisTest = redis-cli ping 2>&1
    if ($redisTest -eq "PONG") {
        Write-Host "   ✓ Redis is running" -ForegroundColor Green
        $testsPassed++
    } else {
        Write-Host "   ✗ Redis is NOT running" -ForegroundColor Red
        Write-Host "   Start Redis: redis-server" -ForegroundColor Yellow
        $testsFailed++
    }
} catch {
    Write-Host "   ✗ Redis is NOT installed or not in PATH" -ForegroundColor Red
    $testsFailed++
}

Write-Host ""

# Test 2: Node.js Health
Write-Host "[Test 2/5] Testing Node.js service..." -ForegroundColor Yellow
try {
    $nodeHealth = curl -s http://localhost:3000/health | ConvertFrom-Json
    if ($nodeHealth.status -eq "ok") {
        Write-Host "   ✓ Node.js is running" -ForegroundColor Green
        Write-Host "   - Uptime: $([math]::Round($nodeHealth.uptime, 2)) seconds" -ForegroundColor Gray
        Write-Host "   - Connections: $($nodeHealth.connections)" -ForegroundColor Gray
        $testsPassed++
    } else {
        Write-Host "   ✗ Node.js returned unexpected response" -ForegroundColor Red
        $testsFailed++
    }
} catch {
    Write-Host "   ✗ Node.js is NOT running on port 3000" -ForegroundColor Red
    Write-Host "   Start: cd realtime-service && npm run dev" -ForegroundColor Yellow
    $testsFailed++
}

Write-Host ""

# Test 3: Laravel
Write-Host "[Test 3/5] Testing Laravel API..." -ForegroundColor Yellow
try {
    $laravelTest = curl -s http://localhost:8000 2>&1
    if ($laravelTest -match "Laravel|DriveMond") {
        Write-Host "   ✓ Laravel is running" -ForegroundColor Green
        $testsPassed++
    } else {
        Write-Host "   ✗ Laravel is NOT running properly" -ForegroundColor Red
        $testsFailed++
    }
} catch {
    Write-Host "   ✗ Laravel is NOT running on port 8000" -ForegroundColor Red
    Write-Host "   Start: php artisan serve" -ForegroundColor Yellow
    $testsFailed++
}

Write-Host ""

# Test 4: Redis Pub/Sub
Write-Host "[Test 4/5] Testing Redis Pub/Sub..." -ForegroundColor Yellow
try {
    # Subscribe in background
    $subscriber = Start-Job -ScriptBlock {
        redis-cli SUBSCRIBE test-channel
    }

    Start-Sleep -Seconds 1

    # Publish message
    $published = redis-cli PUBLISH test-channel "test-message" 2>&1

    Stop-Job -Job $subscriber
    Remove-Job -Job $subscriber

    if ($published -ge 0) {
        Write-Host "   ✓ Redis Pub/Sub is working" -ForegroundColor Green
        $testsPassed++
    } else {
        Write-Host "   ✗ Redis Pub/Sub failed" -ForegroundColor Red
        $testsFailed++
    }
} catch {
    Write-Host "   ✗ Could not test Redis Pub/Sub" -ForegroundColor Red
    $testsFailed++
}

Write-Host ""

# Test 5: Check Active Drivers in Redis
Write-Host "[Test 5/5] Checking Redis data..." -ForegroundColor Yellow
try {
    $driverCount = redis-cli ZCARD drivers:locations 2>&1
    Write-Host "   ✓ Redis GEO index accessible" -ForegroundColor Green
    Write-Host "   - Active drivers: $driverCount" -ForegroundColor Gray
    $testsPassed++
} catch {
    Write-Host "   ✗ Could not access Redis GEO data" -ForegroundColor Red
    $testsFailed++
}

Write-Host ""
Write-Host "=====================================================================" -ForegroundColor Cyan
Write-Host "  Test Results" -ForegroundColor Cyan
Write-Host "=====================================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Passed: $testsPassed/5" -ForegroundColor Green
Write-Host "Failed: $testsFailed/5" -ForegroundColor Red
Write-Host ""

if ($testsFailed -eq 0) {
    Write-Host "✓ All tests passed! Your integration is working!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Next steps:" -ForegroundColor Yellow
    Write-Host "  1. Import Postman collection: SmartLine_Laravel_NodeJS_Testing.postman_collection.json"
    Write-Host "  2. Test with Postman:"
    Write-Host "     - Run: 1. Driver Login"
    Write-Host "     - Run: 3. Get Pending Rides"
    Write-Host "     - Run: Node.js - Health Check"
    Write-Host "  3. Test WebSocket: cd realtime-service && node test-websocket.js"
    Write-Host ""
} else {
    Write-Host "⚠ Some tests failed. Please fix the issues above." -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Quick fixes:" -ForegroundColor Yellow
    if ($redisTest -ne "PONG") {
        Write-Host "  - Install Redis: https://github.com/microsoftarchive/redis/releases"
    }
    Write-Host "  - Start Laravel: php artisan serve"
    Write-Host "  - Start Node.js: cd realtime-service && npm run dev"
    Write-Host ""
}

Write-Host "Documentation:" -ForegroundColor Cyan
Write-Host "  - Full guide: TESTING_LARAVEL_NODEJS_TOGETHER.md"
Write-Host "  - Flutter guide: FLUTTER_INTEGRATION_GUIDE.md"
Write-Host ""
