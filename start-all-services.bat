@echo off
echo =====================================================================
echo   SmartLine Platform - Starting All Services
echo =====================================================================
echo.

:: Check Redis
echo [1/4] Checking Redis...
redis-cli ping >nul 2>&1
if %errorlevel% == 0 (
    echo    [OK] Redis is running
) else (
    echo    [WARNING] Redis is NOT running
    echo    Please start Redis: redis-server
    echo.
    pause
)

:: Check MySQL
echo.
echo [2/4] Checking MySQL...
mysql -u root -proot -e "SELECT 1" >nul 2>&1
if %errorlevel% == 0 (
    echo    [OK] MySQL is running
) else (
    echo    [WARNING] Could not verify MySQL status
)

:: Start Laravel
echo.
echo [3/4] Starting Laravel API (Port 8000)...
start "Laravel API - Port 8000" cmd /k "php artisan serve"
echo    [OK] Laravel started in new window
timeout /t 2 >nul

:: Start Node.js
echo.
echo [4/4] Starting Node.js Real-time Service (Port 3000)...
start "Node.js Realtime - Port 3000" cmd /k "cd realtime-service && npm run dev"
echo    [OK] Node.js started in new window
timeout /t 3 >nul

:: Display status
echo.
echo =====================================================================
echo   All Services Started!
echo =====================================================================
echo.
echo Service Status:
echo   * Laravel API:        http://localhost:8000
echo   * Node.js Realtime:   http://localhost:3000
echo   * Redis:              Check above
echo.
echo Quick Health Checks:
echo   Laravel:  curl http://localhost:8000/api/health
echo   Node.js:  curl http://localhost:3000/health
echo   Redis:    redis-cli ping
echo.
echo Next Steps:
echo   1. Import Postman collection: SmartLine_Laravel_NodeJS_Testing.postman_collection.json
echo   2. Read the guide: TESTING_LARAVEL_NODEJS_TOGETHER.md
echo   3. Test WebSocket: cd realtime-service ^&^& node test-websocket.js
echo.
echo To stop services, close the command prompt windows
echo.
echo Press any key to open Redis monitor...
pause >nul

:: Open Redis monitor
echo Opening Redis monitor...
start "Redis Monitor" cmd /k "echo Redis Monitor - All commands will appear here && echo Press Ctrl+C to stop && echo. && redis-cli MONITOR"

echo.
echo All done! Happy testing!
echo.
pause
