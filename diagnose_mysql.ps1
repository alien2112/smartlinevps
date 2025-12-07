# MySQL Diagnostic Script for Windows
# Run this script to diagnose MySQL shutdown issues

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "MySQL Diagnostic Tool" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if running as Administrator
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host "[WARNING] Not running as Administrator" -ForegroundColor Yellow
    Write-Host "   Some checks may require admin privileges" -ForegroundColor Yellow
    Write-Host ""
}

# 1. Check Port 3306
Write-Host "1. Checking Port 3306..." -ForegroundColor Green
$port3306 = netstat -ano | findstr :3306
if ($port3306) {
    Write-Host "   [X] Port 3306 is in use!" -ForegroundColor Red
    Write-Host "   Details:" -ForegroundColor Yellow
    $port3306 | ForEach-Object { Write-Host "   $_" -ForegroundColor Gray }
    Write-Host ""
    Write-Host "   To find the process:" -ForegroundColor Yellow
    $port3306 | ForEach-Object {
        $pid = ($_ -split '\s+')[-1]
        if ($pid -match '^\d+$') {
            $process = Get-Process -Id $pid -ErrorAction SilentlyContinue
            if ($process) {
                Write-Host "   PID $pid : $($process.ProcessName) ($($process.Path))" -ForegroundColor Cyan
            }
        }
    }
} else {
    Write-Host "   [OK] Port 3306 is available" -ForegroundColor Green
}
Write-Host ""

# 2. Check MySQL Processes
Write-Host "2. Checking MySQL Processes..." -ForegroundColor Green
$mysqlProcesses = Get-Process | Where-Object {$_.ProcessName -like "*mysql*"}
if ($mysqlProcesses) {
    Write-Host "   Found MySQL processes:" -ForegroundColor Yellow
    $mysqlProcesses | ForEach-Object {
        Write-Host "   - $($_.ProcessName) (PID: $($_.Id))" -ForegroundColor Cyan
    }
} else {
    Write-Host "   [OK] No MySQL processes running" -ForegroundColor Green
}
Write-Host ""

# 3. Check MySQL Services
Write-Host "3. Checking MySQL Services..." -ForegroundColor Green
$mysqlServices = Get-Service | Where-Object {$_.Name -like "*mysql*"}
if ($mysqlServices) {
    Write-Host "   Found MySQL services:" -ForegroundColor Yellow
    $mysqlServices | ForEach-Object {
        $status = if ($_.Status -eq 'Running') { "[OK]" } else { "[X]" }
        Write-Host "   $status $($_.Name) - Status: $($_.Status)" -ForegroundColor $(if ($_.Status -eq 'Running') { "Green" } else { "Red" })
    }
} else {
    Write-Host "   [INFO] No MySQL Windows services found (may be using XAMPP)" -ForegroundColor Yellow
}
Write-Host ""

# 4. Check XAMPP Installation
Write-Host "4. Checking XAMPP Installation..." -ForegroundColor Green
$xamppPaths = @(
    "C:\xampp",
    "D:\xampp",
    "E:\xampp"
)

$foundXampp = $false
foreach ($path in $xamppPaths) {
    if (Test-Path $path) {
        $foundXampp = $true
        Write-Host "   [OK] Found XAMPP at: $path" -ForegroundColor Green
        
        # Check MySQL data folder
        $mysqlData = Join-Path $path "mysql\data"
        if (Test-Path $mysqlData) {
            Write-Host "   [OK] MySQL data folder exists" -ForegroundColor Green
            
            # Check for error logs
            $errorLogs = Get-ChildItem -Path $mysqlData -Filter "*.err" -ErrorAction SilentlyContinue
            if ($errorLogs) {
                Write-Host "   [LOG] Found error log files:" -ForegroundColor Yellow
                $errorLogs | ForEach-Object {
                    Write-Host "      - $($_.Name)" -ForegroundColor Cyan
                }
                Write-Host ""
                Write-Host "   Last 10 lines of error log:" -ForegroundColor Yellow
                $latestLog = $errorLogs | Sort-Object LastWriteTime -Descending | Select-Object -First 1
                if ($latestLog) {
                    Get-Content $latestLog.FullName -Tail 10 | ForEach-Object {
                        Write-Host "      $_" -ForegroundColor Gray
                    }
                }
            }
        }
        break
    }
}

if (-not $foundXampp) {
    Write-Host "   [INFO] XAMPP not found in common locations" -ForegroundColor Yellow
}
Write-Host ""

# 5. Check Standalone MySQL Installation
Write-Host "5. Checking Standalone MySQL Installation..." -ForegroundColor Green
$mysqlPaths = @(
    "C:\Program Files\MySQL",
    "C:\Program Files (x86)\MySQL"
)

$foundMySQL = $false
foreach ($basePath in $mysqlPaths) {
    if (Test-Path $basePath) {
        $foundMySQL = $true
        Write-Host "   [OK] Found MySQL at: $basePath" -ForegroundColor Green
        
        $mysqlServers = Get-ChildItem -Path $basePath -Directory -Filter "MySQL Server *" -ErrorAction SilentlyContinue
        if ($mysqlServers) {
            $mysqlServers | ForEach-Object {
                Write-Host "   - $($_.Name)" -ForegroundColor Cyan
            }
        }
        break
    }
}

if (-not $foundMySQL) {
    Write-Host "   [INFO] Standalone MySQL not found in common locations" -ForegroundColor Yellow
}
Write-Host ""

# 6. Check Disk Space
Write-Host "6. Checking Disk Space..." -ForegroundColor Green
$drive = Get-PSDrive C
$freeGB = [math]::Round($drive.Free / 1GB, 2)
$usedGB = [math]::Round($drive.Used / 1GB, 2)
$totalGB = [math]::Round(($drive.Free + $drive.Used) / 1GB, 2)
$freePercent = [math]::Round(($drive.Free / ($drive.Free + $drive.Used)) * 100, 2)

Write-Host "   C: Drive - Free: ${freeGB}GB / Total: ${totalGB}GB (${freePercent}% free)" -ForegroundColor $(if ($freePercent -lt 10) { "Red" } elseif ($freePercent -lt 20) { "Yellow" } else { "Green" })

if ($freePercent -lt 10) {
    Write-Host "   [WARNING] Low disk space may cause MySQL issues!" -ForegroundColor Red
}
Write-Host ""

# 7. Check Windows Event Viewer (if admin)
if ($isAdmin) {
    Write-Host "7. Checking Windows Event Viewer for MySQL errors..." -ForegroundColor Green
    try {
        $events = Get-WinEvent -LogName Application -MaxEvents 20 -ErrorAction SilentlyContinue | 
            Where-Object { $_.Message -like "*mysql*" -or $_.ProviderName -like "*mysql*" }
        
        if ($events) {
            Write-Host "   Found MySQL-related events:" -ForegroundColor Yellow
            $events | Select-Object -First 5 | ForEach-Object {
                Write-Host "   - [$($_.TimeCreated)] $($_.LevelDisplayName): $($_.Message.Substring(0, [Math]::Min(100, $_.Message.Length)))..." -ForegroundColor Cyan
            }
        } else {
            Write-Host "   [OK] No recent MySQL errors in Event Viewer" -ForegroundColor Green
        }
    } catch {
        Write-Host "   [INFO] Could not access Event Viewer" -ForegroundColor Yellow
    }
} else {
    Write-Host "7. Skipping Event Viewer check (requires Administrator)" -ForegroundColor Yellow
}
Write-Host ""

# Summary
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Diagnostic Summary" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "1. Check the error logs shown above" -ForegroundColor White
Write-Host "2. If port 3306 is in use, stop the conflicting process" -ForegroundColor White
Write-Host "3. Review MYSQL_TROUBLESHOOTING.md for detailed solutions" -ForegroundColor White
Write-Host "4. Try changing MySQL port if port conflict exists" -ForegroundColor White
Write-Host ""
Write-Host "For detailed troubleshooting, see: MYSQL_TROUBLESHOOTING.md" -ForegroundColor Cyan
Write-Host ""

