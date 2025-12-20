# ====================================================================
# DATABASE INDEXING SCRIPT - FIXED VERSION
# ====================================================================

$DB_USER = "root"
$DB_NAME = "smartline_new2"
$DB_COPY_NAME = "smartline_indexed_copy"

Write-Host "=====================================================================" -ForegroundColor Cyan
Write-Host "DATABASE INDEXING PROCESS" -ForegroundColor Cyan
Write-Host "=====================================================================" -ForegroundColor Cyan
Write-Host ""

# Prompt for password
$DB_PASSWORD = Read-Host "Enter MySQL password for user '$DB_USER'" -AsSecureString
$BSTR = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($DB_PASSWORD)
$PlainPassword = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto($BSTR)

Write-Host ""
Write-Host "Step 1: Creating database copy..." -ForegroundColor Green
Write-Host "This may take several minutes..." -ForegroundColor Gray

# Create the copy database
$createDbCmd = "CREATE DATABASE IF NOT EXISTS $DB_COPY_NAME;"
mysql -u $DB_USER -p$PlainPassword -e $createDbCmd 2>&1 | Out-Null

if ($LASTEXITCODE -eq 0) {
    Write-Host "✓ Database $DB_COPY_NAME created successfully" -ForegroundColor Green
} else {
    Write-Host "✗ Failed to create database" -ForegroundColor Red
    exit 1
}

# Copy schema and data
Write-Host "Copying database structure and data..." -ForegroundColor Gray
mysqldump -u $DB_USER -p$PlainPassword $DB_NAME | mysql -u $DB_USER -p$PlainPassword $DB_COPY_NAME 2>&1 | Out-Null

if ($LASTEXITCODE -eq 0) {
    Write-Host "✓ Database copied successfully" -ForegroundColor Green
} else {
    Write-Host "✗ Failed to copy database" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "Step 2: Applying Priority 1 indexes..." -ForegroundColor Green

# Read and update Priority 1 SQL
$priority1Content = Get-Content "indexes_priority1.sql" -Raw
$priority1Updated = $priority1Content -replace "USE smartline_indexed_copy;", "USE $DB_COPY_NAME;"
Set-Content -Path "indexes_priority1_temp.sql" -Value $priority1Updated

# Apply Priority 1 indexes
$sqlContent1 = Get-Content "indexes_priority1_temp.sql" -Raw
$result1 = $sqlContent1 | mysql -u $DB_USER -p$PlainPassword 2>&1

if ($result1 -match "ERROR") {
    Write-Host "⚠ Some errors occurred:" -ForegroundColor Yellow
    Write-Host $result1 -ForegroundColor Red
} else {
    Write-Host "✓ Priority 1 indexes applied successfully" -ForegroundColor Green
}

Remove-Item "indexes_priority1_temp.sql" -ErrorAction SilentlyContinue

Write-Host ""
Write-Host "Step 3: Applying Priority 2 indexes..." -ForegroundColor Green

# Read and update Priority 2 SQL
$priority2Content = Get-Content "indexes_priority2.sql" -Raw
$priority2Updated = $priority2Content -replace "USE smartline_indexed_copy;", "USE $DB_COPY_NAME;"
Set-Content -Path "indexes_priority2_temp.sql" -Value $priority2Updated

# Apply Priority 2 indexes
$sqlContent2 = Get-Content "indexes_priority2_temp.sql" -Raw
$result2 = $sqlContent2 | mysql -u $DB_USER -p$PlainPassword 2>&1

if ($result2 -match "ERROR") {
    Write-Host "⚠ Some errors occurred:" -ForegroundColor Yellow
    Write-Host $result2 -ForegroundColor Red
} else {
    Write-Host "✓ Priority 2 indexes applied successfully" -ForegroundColor Green
}

Remove-Item "indexes_priority2_temp.sql" -ErrorAction SilentlyContinue

Write-Host ""
Write-Host "Step 4: Verifying indexes..." -ForegroundColor Green

$indexQuery = "SELECT TABLE_NAME, INDEX_NAME, INDEX_TYPE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as COLUMNS, CARDINALITY FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = '$DB_COPY_NAME' AND INDEX_NAME LIKE 'idx_%' GROUP BY TABLE_NAME, INDEX_NAME, INDEX_TYPE, CARDINALITY ORDER BY TABLE_NAME, INDEX_NAME;"

mysql -u $DB_USER -p$PlainPassword -e $indexQuery 2>&1

Write-Host ""
Write-Host "=====================================================================" -ForegroundColor Cyan
Write-Host "INDEXING COMPLETE!" -ForegroundColor Green
Write-Host "=====================================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "To switch your app to use the indexed copy, update .env:" -ForegroundColor Yellow
Write-Host "DB_DATABASE=$DB_COPY_NAME" -ForegroundColor Cyan
Write-Host ""

# Clear password from memory
$PlainPassword = $null
[System.GC]::Collect()
