# ====================================================================
# DATABASE INDEXING SCRIPT
# ====================================================================
# This script:
# 1. Creates a copy of your database
# 2. Applies Priority 1 indexes to the copy
# 3. Applies Priority 2 indexes to the copy
# 4. Tests the indexes with EXPLAIN queries
# ====================================================================

# Configuration - UPDATE THESE VALUES
$DB_USER = "root"
$DB_NAME = "smartline_db"  # Your current database name
$DB_COPY_NAME = "smartline_indexed_copy"

Write-Host "=====================================================================" -ForegroundColor Cyan
Write-Host "DATABASE INDEXING PROCESS" -ForegroundColor Cyan
Write-Host "=====================================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "This script will:" -ForegroundColor Yellow
Write-Host "1. Create a copy of database: $DB_NAME" -ForegroundColor Yellow
Write-Host "2. Apply all indexes to: $DB_COPY_NAME" -ForegroundColor Yellow
Write-Host "3. Test index performance" -ForegroundColor Yellow
Write-Host ""
Write-Host "WARNING: This will create a new database. Ensure you have enough disk space." -ForegroundColor Red
Write-Host ""

# Prompt for password
$DB_PASSWORD = Read-Host "Enter MySQL password for user '$DB_USER'" -AsSecureString
$BSTR = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($DB_PASSWORD)
$PlainPassword = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto($BSTR)

Write-Host ""
Write-Host "Step 1: Creating database copy..." -ForegroundColor Green
Write-Host "This may take several minutes depending on database size..." -ForegroundColor Gray

# Create the copy database
mysql -u $DB_USER -p$PlainPassword -e "CREATE DATABASE IF NOT EXISTS $DB_COPY_NAME;" 2>&1 | Out-Null

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
Write-Host "These are critical indexes for trip queries and spatial searches..." -ForegroundColor Gray

# Update the SQL file to use the correct database name
(Get-Content "indexes_priority1.sql") -replace "USE smartline_indexed_copy;", "USE $DB_COPY_NAME;" | Set-Content "indexes_priority1_temp.sql"

# Apply Priority 1 indexes
mysql -u $DB_USER -p$PlainPassword < indexes_priority1_temp.sql 2>&1 | Tee-Object -Variable output | Out-Null

if ($output -match "ERROR") {
    Write-Host "⚠ Some errors occurred while applying Priority 1 indexes:" -ForegroundColor Yellow
    Write-Host $output -ForegroundColor Red
} else {
    Write-Host "✓ Priority 1 indexes applied successfully" -ForegroundColor Green
}

Remove-Item "indexes_priority1_temp.sql"

Write-Host ""
Write-Host "Step 3: Applying Priority 2 indexes..." -ForegroundColor Green
Write-Host "These are performance optimization indexes..." -ForegroundColor Gray

# Update the SQL file to use the correct database name
(Get-Content "indexes_priority2.sql") -replace "USE smartline_indexed_copy;", "USE $DB_COPY_NAME;" | Set-Content "indexes_priority2_temp.sql"

# Apply Priority 2 indexes
mysql -u $DB_USER -p$PlainPassword < indexes_priority2_temp.sql 2>&1 | Tee-Object -Variable output | Out-Null

if ($output -match "ERROR") {
    Write-Host "⚠ Some errors occurred while applying Priority 2 indexes:" -ForegroundColor Yellow
    Write-Host $output -ForegroundColor Red
} else {
    Write-Host "✓ Priority 2 indexes applied successfully" -ForegroundColor Green
}

Remove-Item "indexes_priority2_temp.sql"

Write-Host ""
Write-Host "Step 4: Verifying indexes..." -ForegroundColor Green

# Query to show all indexes
$indexQuery = @"
SELECT
    TABLE_NAME,
    INDEX_NAME,
    INDEX_TYPE,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as COLUMNS,
    CARDINALITY
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = '$DB_COPY_NAME'
    AND INDEX_NAME LIKE 'idx_%'
GROUP BY TABLE_NAME, INDEX_NAME, INDEX_TYPE, CARDINALITY
ORDER BY TABLE_NAME, INDEX_NAME;
"@

mysql -u $DB_USER -p$PlainPassword -e $indexQuery 2>&1

Write-Host ""
Write-Host "=====================================================================" -ForegroundColor Cyan
Write-Host "INDEXING COMPLETE!" -ForegroundColor Green
Write-Host "=====================================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. Test your application against database: $DB_COPY_NAME" -ForegroundColor White
Write-Host "2. Run performance tests and compare query times" -ForegroundColor White
Write-Host "3. If tests pass, apply Laravel migrations to production" -ForegroundColor White
Write-Host "4. Monitor slow query log after deployment" -ForegroundColor White
Write-Host ""
Write-Host "To switch your app to use the indexed copy, update .env:" -ForegroundColor Yellow
Write-Host "DB_DATABASE=$DB_COPY_NAME" -ForegroundColor Cyan
Write-Host ""

# Clear password from memory
$PlainPassword = $null
[System.GC]::Collect()
