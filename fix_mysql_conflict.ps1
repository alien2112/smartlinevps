# MySQL Conflict Resolution Script
# This script helps resolve conflicts between multiple MySQL instances

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "MySQL Conflict Resolution" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if running as Administrator
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host "[ERROR] This script must be run as Administrator!" -ForegroundColor Red
    Write-Host "Right-click PowerShell and select 'Run as Administrator'" -ForegroundColor Yellow
    Write-Host ""
    exit 1
}

# Find all MySQL processes
Write-Host "Finding MySQL processes..." -ForegroundColor Green
$mysqlProcesses = Get-Process -Name "mysqld" -ErrorAction SilentlyContinue

if ($mysqlProcesses) {
    Write-Host "Found $($mysqlProcesses.Count) MySQL process(es):" -ForegroundColor Yellow
    $mysqlProcesses | ForEach-Object {
        Write-Host "  - PID $($_.Id) - Memory: $([math]::Round($_.WorkingSet64 / 1MB, 2)) MB" -ForegroundColor Cyan
    }
    Write-Host ""
    
    # Check which one is using port 3306
    Write-Host "Checking which process is using port 3306..." -ForegroundColor Green
    $port3306 = netstat -ano | findstr ":3306.*LISTENING"
    if ($port3306) {
        $portPid = ($port3306 -split '\s+')[-1]
        Write-Host "  Port 3306 is used by PID: $portPid" -ForegroundColor Yellow
    }
    Write-Host ""
    
    # Check MySQL services
    Write-Host "Checking MySQL Windows services..." -ForegroundColor Green
    $mysqlServices = Get-Service | Where-Object {$_.Name -like "*mysql*"}
    if ($mysqlServices) {
        Write-Host "  Found MySQL service(s):" -ForegroundColor Yellow
        $mysqlServices | ForEach-Object {
            Write-Host "    - $($_.Name): $($_.Status)" -ForegroundColor Cyan
        }
    } else {
        Write-Host "  No MySQL Windows services found" -ForegroundColor Yellow
    }
    Write-Host ""
    
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "Resolution Options:" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Option 1: Stop XAMPP MySQL (Recommended if using standalone MySQL)" -ForegroundColor Yellow
    Write-Host "  - Open XAMPP Control Panel" -ForegroundColor White
    Write-Host "  - Click 'Stop' next to MySQL" -ForegroundColor White
    Write-Host ""
    Write-Host "Option 2: Stop Standalone MySQL Service" -ForegroundColor Yellow
    Write-Host "  - Run: Stop-Service MySQL94" -ForegroundColor White
    Write-Host "  - Or use Services.msc to stop MySQL service" -ForegroundColor White
    Write-Host ""
    Write-Host "Option 3: Stop all MySQL processes (Use with caution!)" -ForegroundColor Yellow
    Write-Host "  - This will stop ALL MySQL instances" -ForegroundColor Red
    Write-Host ""
    
    $choice = Read-Host "Do you want to stop all MySQL processes? (y/n)"
    if ($choice -eq 'y' -or $choice -eq 'Y') {
        Write-Host ""
        Write-Host "Stopping all MySQL processes..." -ForegroundColor Yellow
        $mysqlProcesses | ForEach-Object {
            Write-Host "  Stopping PID $($_.Id)..." -ForegroundColor Cyan
            Stop-Process -Id $_.Id -Force -ErrorAction SilentlyContinue
        }
        Start-Sleep -Seconds 2
        
        # Verify they're stopped
        $remaining = Get-Process -Name "mysqld" -ErrorAction SilentlyContinue
        if ($remaining) {
            Write-Host "  [WARNING] Some processes are still running!" -ForegroundColor Red
        } else {
            Write-Host "  [OK] All MySQL processes stopped" -ForegroundColor Green
        }
    } else {
        Write-Host ""
        Write-Host "No processes were stopped." -ForegroundColor Yellow
        Write-Host "Please manually stop one of the MySQL instances:" -ForegroundColor Yellow
        Write-Host "  1. XAMPP Control Panel -> Stop MySQL" -ForegroundColor White
        Write-Host "  2. Or stop MySQL service: Stop-Service MySQL94" -ForegroundColor White
    }
} else {
    Write-Host "[OK] No MySQL processes found running" -ForegroundColor Green
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Additional Recommendations" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "1. Low Disk Space Warning!" -ForegroundColor Red
Write-Host "   Your C: drive has only 8.65% free space." -ForegroundColor Yellow
Write-Host "   MySQL may crash due to insufficient disk space." -ForegroundColor Yellow
Write-Host "   Recommendation: Free up at least 10-15% disk space." -ForegroundColor White
Write-Host ""
Write-Host "2. Choose ONE MySQL Installation:" -ForegroundColor Yellow
Write-Host "   - Either use XAMPP MySQL (D:\xampp)" -ForegroundColor White
Write-Host "   - Or use Standalone MySQL Server 9.4" -ForegroundColor White
Write-Host "   - Do NOT run both at the same time!" -ForegroundColor Red
Write-Host ""
Write-Host "3. After stopping one instance, restart the other:" -ForegroundColor Yellow
Write-Host "   - XAMPP: Use XAMPP Control Panel" -ForegroundColor White
Write-Host "   - Standalone: Start-Service MySQL94" -ForegroundColor White
Write-Host ""





