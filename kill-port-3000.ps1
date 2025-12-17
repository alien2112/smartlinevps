# Kill Process on Port 3000
Write-Host "Checking for processes using port 3000..." -ForegroundColor Yellow

$connections = netstat -ano | findstr :3000
if ($connections) {
    Write-Host "Found processes on port 3000:" -ForegroundColor Cyan
    Write-Host $connections

    # Extract unique PIDs
    $pids = $connections | ForEach-Object {
        if ($_ -match '\s+(\d+)\s*$') {
            $matches[1]
        }
    } | Select-Object -Unique

    foreach ($pid in $pids) {
        if ($pid) {
            Write-Host "`nKilling process $pid..." -ForegroundColor Yellow
            try {
                Stop-Process -Id $pid -Force -ErrorAction Stop
                Write-Host "✓ Process $pid killed successfully" -ForegroundColor Green
            } catch {
                Write-Host "✗ Could not kill process $pid (may already be closed)" -ForegroundColor Gray
            }
        }
    }

    Start-Sleep -Seconds 1

    # Verify
    $stillRunning = netstat -ano | findstr :3000
    if ($stillRunning) {
        Write-Host "`n⚠ Warning: Some processes may still be running on port 3000" -ForegroundColor Yellow
        Write-Host $stillRunning
    } else {
        Write-Host "`n✓ Port 3000 is now free!" -ForegroundColor Green
    }
} else {
    Write-Host "✓ Port 3000 is already free" -ForegroundColor Green
}

Write-Host "`nYou can now start the Node.js service with: npm run dev" -ForegroundColor Cyan
