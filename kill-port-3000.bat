@echo off
echo Checking for processes using port 3000...
echo.

netstat -ano | findstr :3000 > temp_port.txt

if %errorlevel% == 0 (
    echo Found processes on port 3000:
    type temp_port.txt
    echo.

    for /f "tokens=5" %%a in (temp_port.txt) do (
        echo Killing process %%a...
        taskkill /PID %%a /F >nul 2>&1
        if %errorlevel% == 0 (
            echo [OK] Process %%a killed
        ) else (
            echo [SKIP] Process %%a already closed
        )
    )

    del temp_port.txt

    echo.
    echo Verifying port 3000 is free...
    netstat -ano | findstr :3000 >nul 2>&1
    if %errorlevel% == 0 (
        echo [WARNING] Some processes may still be on port 3000
        netstat -ano | findstr :3000
    ) else (
        echo [OK] Port 3000 is now free!
    )
) else (
    del temp_port.txt
    echo [OK] Port 3000 is already free
)

echo.
echo You can now start the Node.js service:
echo   cd realtime-service
echo   npm run dev
echo.
pause
