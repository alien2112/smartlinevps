# Legacy Driver Onboarding State Migration

## Overview
This script migrates legacy driver accounts (created before 2026-01-03) to their proper onboarding states based on their actual data completion.

## Command Usage

### Basic Usage
```bash
php artisan drivers:migrate-legacy-states
```

### Options

#### Dry Run (Preview without changes)
```bash
php artisan drivers:migrate-legacy-states --dry-run
```

#### Process Specific Driver
```bash
php artisan drivers:migrate-legacy-states --driver-id=000302ca-4065-463a-9e3f-4e281eba7fb0
```

#### Custom Batch Size
```bash
php artisan drivers:migrate-legacy-states --batch-size=500
```

#### Verbose Output
```bash
php artisan drivers:migrate-legacy-states -v
```

#### Combined Options
```bash
php artisan drivers:migrate-legacy-states --dry-run --batch-size=50 -v
```

## State Determination Logic

The script analyzes each driver's data and assigns them to the appropriate onboarding state:

### 1. **approved**
- ✅ Active account (`is_active = 1`)
- ✅ Complete profile (first_name, last_name, phone)
- ✅ Has at least one vehicle
- ✅ Has documents uploaded
- ✅ All documents verified

### 2. **pending_approval**
- ✅ Complete profile
- ✅ Has vehicle
- ✅ Has documents uploaded
- ❌ Not all documents verified yet

### 3. **documents**
- ✅ Complete profile
- ✅ Has vehicle
- ❌ No documents uploaded OR some documents not verified

### 4. **vehicle_type**
- ✅ Complete profile
- ❌ No vehicle registered

### 5. **register_info**
- ❌ Incomplete profile (missing name or phone)

## Example Scenarios

### Scenario 1: Fully Complete Legacy Driver
**Data:**
- Created: 2025-06-17
- Active: Yes
- Profile: Complete
- Vehicle: Yes
- Documents: 3 uploaded, 3 verified

**Result:** `approved` ✅

### Scenario 2: Driver Waiting for Document Approval
**Data:**
- Created: 2025-05-22
- Active: Yes
- Profile: Complete
- Vehicle: Yes
- Documents: 3 uploaded, 1 verified

**Result:** `pending_approval` ⏳

### Scenario 3: Driver with Vehicle, No Documents
**Data:**
- Created: 2025-04-15
- Active: Yes
- Profile: Complete
- Vehicle: Yes
- Documents: 0 uploaded

**Result:** `documents` 📄

## Statistics Output

After running, you'll see:
```
=== Migration Statistics ===
+---------------------------+-------+
| Status                    | Count |
+---------------------------+-------+
| Total Processed           | 5401  |
| Approved                  | 3200  |
| Pending Approval          | 150   |
| Documents                 | 1800  |
| Vehicle Type              | 200   |
| Register Info             | 50    |
| Skipped (already correct) | 1     |
| Errors                    | 0     |
+---------------------------+-------+
```

## Safety Features

1. **Dry Run Mode**: Preview all changes before applying
2. **Transaction Support**: Database changes are logged
3. **Error Handling**: Failed drivers are logged and skipped
4. **Progress Bar**: Visual feedback for large batches
5. **Detailed Logging**: All changes logged to Laravel logs

## Logs

Check logs at:
```bash
tail -f storage/logs/laravel.log | grep "Legacy driver"
```

## Before Running

1. **Backup the database:**
   ```bash
   mysqldump -u root merged2 users > users_backup_$(date +%Y%m%d).sql
   ```

2. **Test with dry run:**
   ```bash
   php artisan drivers:migrate-legacy-states --dry-run -v
   ```

3. **Test on single driver:**
   ```bash
   php artisan drivers:migrate-legacy-states --driver-id=YOUR_DRIVER_ID
   ```

## Rollback

If needed, restore from backup:
```bash
mysql -u root merged2 < users_backup_YYYYMMDD.sql
```

## Support

For issues or questions, check the logs at `storage/logs/laravel.log`
