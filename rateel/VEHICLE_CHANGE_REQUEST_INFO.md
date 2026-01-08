# Vehicle Change Request Information

**Date:** 2026-01-09
**Driver Phone:** +201208673028

---

## Driver Information

- **Name:** سلمي سامي (Selma Samy)
- **Phone:** +201208673028
- **Driver ID:** `000302ca-4065-463a-9e3f-4e281eba7fb0`

---

## Vehicle Change Request Status

### ✅ REQUEST FOUND

**Location:** `vehicles` table  
**Status:** `pending` (awaiting admin approval)

---

## Current Primary Vehicle

| Field | Value |
|-------|-------|
| Vehicle ID | `4e34ffdf-91dd-43db-87b3-d89eb2b7f2ef` |
| License Plate | `5747` |
| Is Primary | ✅ Yes |
| Created | 2025-06-17 08:43:09 |
| Status | Active |

---

## New Vehicle (Pending Approval)

| Field | Value |
|-------|-------|
| Vehicle ID | `f592d439-378e-4fd0-9e88-845094091e54` |
| License Plate | `ا ب ج-5747` |
| Is Primary | ❌ No (requesting) |
| Status | **pending** |
| Created | 2026-01-09 00:19:55 |

### Vehicle Details:
- **Brand:** رينو (Renault)
- **Model:** داستر 2025
- **Category:** سياره التوفير (Economy Car)
- **Transmission:** مانيوال (Manual)
- **Fuel Type:** petrol
- **Ownership:** driver

---

## Database Location

```sql
SELECT * FROM vehicles 
WHERE id = 'f592d439-378e-4fd0-9e88-845094091e54';
```

**Key Fields:**
- `vehicle_request_status` = `'pending'`
- `is_primary` = `0` (will become `1` after approval)
- `has_pending_primary_request` = `0`

---

## Valid Field Values

### Transmission Options:
1. `automatic` - Automatic transmission
2. `manual` or `مانيوال` - Manual transmission  
3. `amt` - Automated Manual Transmission

### Fuel Type Options:
1. `petrol` - Petrol/Gasoline
2. `diesel` - Diesel
3. `cng` - Compressed Natural Gas
4. `lpg` - Liquefied Petroleum Gas
5. `electric` - Electric (if supported)

### Ownership Options:
1. `owned` - Driver owns the vehicle
2. `driver` - Driver vehicle
3. `self` - Self owned
4. `company` - Company vehicle (if supported)
5. `rental` - Rental/Leased (if supported)

---

## How to Approve This Request

### Option 1: Direct Database Update
```sql
-- Approve the new vehicle as primary
UPDATE vehicles 
SET 
  is_primary = 1,
  vehicle_request_status = 'approved'
WHERE id = 'f592d439-378e-4fd0-9e88-845094091e54';

-- Remove primary status from old vehicle
UPDATE vehicles 
SET is_primary = 0
WHERE id = '4e34ffdf-91dd-43db-87b3-d89eb2b7f2ef';
```

### Option 2: Use Admin Dashboard
Look for pending vehicle approval requests in the admin panel under:
- **Drivers** → **Vehicle Approvals**
- **Vehicles** → **Pending Primary Changes**

### Option 3: Use API Endpoint
If there's an admin approval endpoint:
```bash
curl -X POST "https://smartline-it.com/api/admin/vehicles/approve-primary-change" \
  -H "Authorization: Bearer {admin_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "vehicle_id": "f592d439-378e-4fd0-9e88-845094091e54",
    "action": "approve"
  }'
```

---

## PHP Artisan Command to Check Status

```bash
php artisan tinker --execute="
\$vehicle = DB::table('vehicles')
  ->where('id', 'f592d439-378e-4fd0-9e88-845094091e54')
  ->first();
echo 'Status: ' . \$vehicle->vehicle_request_status . PHP_EOL;
echo 'Is Primary: ' . (\$vehicle->is_primary ? 'Yes' : 'No') . PHP_EOL;
"
```

---

## API Endpoint Reference

Based on your curl example, the endpoint should be:

```bash
POST /driver/vehicle/store-as-primary
```

**Parameters:**
- `driver_id` (UUID)
- `brand_id` (UUID)
- `model_id` (UUID)
- `category_id` (UUID)
- `licence_plate_number` (string)
- `licence_expire_date` (date: YYYY-MM-DD)
- `transmission` (enum: automatic|manual|مانيوال|amt)
- `fuel_type` (enum: petrol|diesel|cng|lpg|electric)
- `ownership` (enum: owned|driver|self|company|rental)
- `car_front` (file: image)
- `car_back` (file: image)
- `car_left` (file: image - optional)
- `car_right` (file: image - optional)

---

## Summary

✅ **Vehicle change request EXISTS in the system**
- **Table:** `vehicles`
- **Vehicle ID:** `f592d439-378e-4fd0-9e88-845094091e54`
- **Status:** `pending` (needs admin approval)
- **Driver:** +201208673028 (سلمي سامي)
- **Action Required:** Admin needs to approve the request

The driver has submitted a request to change their primary vehicle from plate `5747` to `ا ب ج-5747` (Renault Duster 2025).

---

**Generated:** 2026-01-09  
**Database:** merged2
