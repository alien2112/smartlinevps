# Database Indexing Implementation Guide

## Overview

This guide walks you through implementing database indexes on a **copy** of your database, testing them, and then applying them to production using Laravel migrations.

## Prerequisites

- MySQL 5.7 or higher
- PowerShell (Windows) or Bash (Linux/Mac)
- Laravel application configured
- Access to MySQL database

---

## Step 1: Create Database Copy & Apply Indexes

### Option A: Using PowerShell Script (Recommended)

```powershell
# Run the automated script
.\apply_database_indexes.ps1
```

The script will:
1. Prompt for your MySQL password
2. Create a copy of your database (e.g., `smartline_indexed_copy`)
3. Apply Priority 1 indexes (trips, spatial, locations)
4. Apply Priority 2 indexes (users, vehicles, transactions)
5. Display all created indexes

### Option B: Manual Steps

#### 1.1 Create Database Copy

```bash
# Export your current database
mysqldump -u root -p smartline_db > smartline_backup.sql

# Create copy database
mysql -u root -p -e "CREATE DATABASE smartline_indexed_copy;"

# Import to copy
mysql -u root -p smartline_indexed_copy < smartline_backup.sql
```

#### 1.2 Apply Priority 1 Indexes

```bash
# Edit indexes_priority1.sql - replace database name
# Change: USE smartline_indexed_copy;

mysql -u root -p < indexes_priority1.sql
```

#### 1.3 Apply Priority 2 Indexes

```bash
mysql -u root -p < indexes_priority2.sql
```

---

## Step 2: Test the Indexed Database

### 2.1 Update .env to Use Copy Database

```env
# In .env file
DB_DATABASE=smartline_indexed_copy
```

### 2.2 Restart Laravel

```bash
php artisan config:clear
php artisan cache:clear
```

### 2.3 Test Critical Queries

Run these tests to verify indexes are working:

#### Test 1: Trip Status Query
```bash
php artisan tinker
```

```php
// Should use idx_trips_status_created
DB::enableQueryLog();
\Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')
    ->orderBy('created_at', 'desc')
    ->limit(20)
    ->get();
dd(DB::getQueryLog());
```

#### Test 2: Driver Pending Rides
```php
// Should use idx_trips_zone_status
DB::enableQueryLog();
\Modules\TripManagement\Entities\TripRequest::where('zone_id', 'your-zone-id')
    ->where('current_status', 'pending')
    ->get();
dd(DB::getQueryLog());
```

#### Test 3: Spatial Query (Nearest Drivers)
```php
// Should use idx_location_point spatial index
DB::enableQueryLog();
DB::select("
    SELECT
        user_id,
        ST_Distance_Sphere(location_point, ST_SRID(POINT(31.2357, 30.0444), 4326)) AS distance_meters
    FROM user_last_locations
    WHERE type = 'driver'
    HAVING distance_meters <= 5000
    ORDER BY distance_meters
    LIMIT 10
");
dd(DB::getQueryLog());
```

### 2.4 Verify with EXPLAIN

```sql
-- Connect to MySQL
mysql -u root -p smartline_indexed_copy

-- Check index usage
EXPLAIN SELECT * FROM trip_requests
WHERE current_status = 'pending'
ORDER BY created_at DESC
LIMIT 20;
```

**Look for:**
- `type`: Should be "ref" or "range" (NOT "ALL")
- `possible_keys`: Should show your index name
- `key`: Should show the index being used
- `Extra`: Should include "Using index" (good!)

---

## Step 3: Performance Comparison

### 3.1 Before/After Query Time

Create a test script `test_performance.php`:

```php
<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Test trip status query
$start = microtime(true);
\Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')
    ->orderBy('created_at', 'desc')
    ->limit(20)
    ->get();
$end = microtime(true);

echo "Trip status query: " . round(($end - $start) * 1000, 2) . "ms\n";

// Test driver location query
$start = microtime(true);
DB::select("
    SELECT user_id
    FROM user_last_locations
    WHERE zone_id = ? AND type = 'driver'
    LIMIT 10
", ['some-zone-id']);
$end = microtime(true);

echo "Driver location query: " . round(($end - $start) * 1000, 2) . "ms\n";
```

Run:
```bash
php test_performance.php
```

**Expected Results:**
- Trip queries: <50ms (was 1000-5000ms)
- Location queries: <20ms (was 500-2000ms)
- Spatial queries: <30ms (was 2000-5000ms)

---

## Step 4: Apply to Production Using Laravel Migrations

### 4.1 Review Migrations

Check the migration files in `database/migrations/`:

```
2025_12_17_000001_add_priority1_indexes_to_trip_requests.php
2025_12_17_000002_add_spatial_indexes_to_trip_request_coordinates.php
2025_12_17_000003_add_spatial_column_to_user_last_locations.php
2025_12_17_000004_add_spatial_index_to_zones.php
2025_12_17_000005_add_priority2_indexes_to_users_and_auth.php
2025_12_17_000006_add_priority2_indexes_to_vehicles.php
2025_12_17_000007_add_priority2_indexes_to_transactions_and_payments.php
2025_12_17_000008_add_priority2_indexes_to_promotions_and_misc.php
2025_12_17_000009_add_composite_covering_indexes.php
```

### 4.2 Update .env to Use Production Database

```env
# Change back to production database
DB_DATABASE=smartline_db
```

### 4.3 Run Migrations on Production

**IMPORTANT: Do this during low traffic period**

```bash
# Backup production database first!
mysqldump -u root -p smartline_db > smartline_production_backup_$(date +%Y%m%d).sql

# Run migrations
php artisan migrate

# Verify
php artisan migrate:status
```

### 4.4 Monitor Performance

After migration, monitor:

```bash
# Check slow query log
tail -f /var/log/mysql/slow-query.log

# Check Laravel logs
tail -f storage/logs/laravel.log

# Monitor database
mysql -u root -p -e "SHOW PROCESSLIST;"
```

---

## Step 5: Verify Production Indexes

### 5.1 Check All Indexes Created

```sql
mysql -u root -p smartline_db

SELECT
    TABLE_NAME,
    INDEX_NAME,
    INDEX_TYPE,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as COLUMNS
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'smartline_db'
    AND INDEX_NAME LIKE 'idx_%'
GROUP BY TABLE_NAME, INDEX_NAME, INDEX_TYPE
ORDER BY TABLE_NAME, INDEX_NAME;
```

### 5.2 Verify Spatial Indexes

```sql
SHOW INDEX FROM user_last_locations WHERE Key_name = 'idx_location_point';
SHOW INDEX FROM zones WHERE Key_name = 'idx_zone_coordinates';
```

### 5.3 Test Production Queries

Use the same test scripts from Step 2.3, but with production database.

---

## Rollback Plan

If something goes wrong:

### Option 1: Rollback Migrations

```bash
# Rollback last batch of migrations
php artisan migrate:rollback --step=9

# Verify rollback
php artisan migrate:status
```

### Option 2: Restore from Backup

```bash
# Drop current database
mysql -u root -p -e "DROP DATABASE smartline_db;"

# Recreate
mysql -u root -p -e "CREATE DATABASE smartline_db;"

# Restore backup
mysql -u root -p smartline_db < smartline_production_backup_YYYYMMDD.sql
```

---

## Maintenance

### Regular Index Maintenance

```sql
-- Analyze tables to update index statistics
ANALYZE TABLE trip_requests;
ANALYZE TABLE user_last_locations;
ANALYZE TABLE zones;

-- Optimize tables to defragment indexes
OPTIMIZE TABLE trip_requests;
OPTIMIZE TABLE user_last_locations;
```

### Monitor Index Usage

```sql
-- Check unused indexes (MySQL 8.0+)
SELECT * FROM sys.schema_unused_indexes
WHERE object_schema = 'smartline_db';

-- Check index size
SELECT
    TABLE_NAME,
    INDEX_NAME,
    ROUND(STAT_VALUE * @@innodb_page_size / 1024 / 1024, 2) AS size_mb
FROM mysql.innodb_index_stats
WHERE database_name = 'smartline_db'
    AND stat_name = 'size'
ORDER BY size_mb DESC;
```

---

## Troubleshooting

### Issue: Migration Fails with "Duplicate key name"

**Solution:**
```bash
# Check if index already exists
mysql -u root -p -e "SHOW INDEX FROM trip_requests WHERE Key_name = 'idx_trips_status_created';"

# If exists, skip that migration or drop index first
```

### Issue: Spatial Index Creation Fails

**Cause:** Column is not POINT type

**Solution:**
```sql
-- Check column type
DESCRIBE user_last_locations;

-- If not POINT, the migration will add it
-- Ensure MatanYadaev/Laravel-Eloquent-Spatial is installed
```

### Issue: Query Still Slow After Indexing

**Check:**
1. Verify index is being used: `EXPLAIN SELECT ...`
2. Check index cardinality: `SHOW INDEX FROM table_name;`
3. Analyze table: `ANALYZE TABLE table_name;`
4. Check for table locks: `SHOW OPEN TABLES WHERE In_use > 0;`

---

## Success Criteria

After successful indexing, you should see:

- ✅ All 9 migrations completed
- ✅ Trip status queries <50ms
- ✅ Location queries <20ms
- ✅ Spatial queries <30ms
- ✅ No slow query log entries for indexed tables
- ✅ EXPLAIN shows index usage (not full table scan)
- ✅ Application performance improved
- ✅ No errors in Laravel logs

---

## Next Steps

After database indexing is complete:

1. **Monitor for 48 hours** - Watch slow query logs and application performance
2. **Update PRODUCTION_READINESS_AUDIT_2025-12-16.md** - Mark database indexing as complete
3. **Implement Node.js migration** - See NODEJS_MIGRATION_PLAN.md
4. **Load testing** - Test with expected production load
5. **Documentation** - Update team documentation with new indexes

---

## Support

If you encounter issues:

1. Check MySQL error log: `/var/log/mysql/error.log`
2. Check Laravel logs: `storage/logs/laravel.log`
3. Review migration files for syntax errors
4. Test on copy database first, never on production directly
5. Contact development team if stuck

---

## Summary

**What We Did:**
1. ✅ Created SQL scripts for Priority 1 & 2 indexes
2. ✅ Created PowerShell automation script
3. ✅ Created Laravel migrations for all indexes
4. ✅ Provided testing and verification procedures
5. ✅ Documented rollback and maintenance procedures

**Expected Impact:**
- Trip queries: 5-10s → <50ms (100-200x faster)
- Location queries: 2-5s → <20ms (100-250x faster)
- Spatial queries: 2-3s → <30ms (60-100x faster)

**Total Time to Implement:** 2-4 hours (including testing)
