# Add New Vehicle as Primary - API Endpoint

## 📍 NEW ENDPOINT

```
POST /api/driver/vehicle/store-as-primary
```

## 📝 Full URL

```
https://smartline-it.com/api/driver/vehicle/store-as-primary
```

## ✨ What This Does

This endpoint allows you to **add a new vehicle AND request it as primary in ONE step**. The request requires admin approval before the vehicle becomes active and primary.

---

## 🔄 Workflow

1. **Driver:** Calls `POST /api/driver/vehicle/store-as-primary` with vehicle details
2. **System:** 
   - Creates the new vehicle with `vehicle_request_status = PENDING`
   - Marks it with `has_pending_primary_request = true`
   - Clears any other pending primary requests
3. **Driver:** Receives confirmation that request is pending
4. **Admin:** Reviews in admin panel and sees:
   - Yellow badge: "Driver requested to set this vehicle as primary"
   - Can approve vehicle (which also sets it as primary)
   - OR reject the vehicle
5. **System:** When admin approves:
   - Vehicle becomes `APPROVED` and `active`
   - Vehicle becomes `is_primary = true`
   - Old primary vehicle becomes `is_primary = false`

---

## 🔑 Authentication

**Required** - Bearer token in Authorization header

---

## 📥 Request Example

```bash
curl -X POST "https://smartline-it.com/api/driver/vehicle/store-as-primary" \
  -H "Authorization: Bearer {your_access_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "driver_id": "c4bcf628-64cc-4a8e-83e8-10ea42376a0d",
    "brand_id": "84ba8b83-6a64-4cbc-8244-2194c3c8c495",
    "model_id": "dd7e3365-5f46-4dec-b828-21fbd5568503",
    "category_id": "d4d1e8f1-c716-4cff-96e1-c0b312a1a58b",
    "licence_plate_number": "XYZ-5678",
    "licence_expire_date": "2027-12-31",
    "vin_number": "2HGES16535H567890",
    "transmission": "automatic",
    "fuel_type": "diesel",
    "ownership": "owned"
  }'
```

---

## 📋 Required Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `driver_id` | UUID | ✅ Yes | Your driver ID |
| `brand_id` | UUID | ✅ Yes | Vehicle brand ID |
| `model_id` | UUID | ✅ Yes | Vehicle model ID |
| `category_id` | UUID | ✅ Yes | Vehicle category ID |
| `licence_plate_number` | string | ✅ Yes | License plate number |

---

## 📋 Optional Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `licence_expire_date` | date | ❌ No | License expiration (YYYY-MM-DD) |
| `vin_number` | string | ❌ No | Vehicle Identification Number |
| `transmission` | string | ❌ No | `manual` or `automatic` |
| `fuel_type` | string | ❌ No | `petrol`, `diesel`, `electric`, `hybrid` |
| `ownership` | string | ❌ No | `owned`, `rented`, `leased` |

---

## 📤 Success Response (200)

```json
{
  "response_code": "vehicle_primary_request_pending",
  "message": "Vehicle added successfully. Your request to set it as primary is pending admin approval."
}
```

---

## ⚠️ Error Responses

**Not Authenticated (403):**
```json
{
  "response_code": "default_403",
  "message": "Forbidden"
}
```

**Driver ID Mismatch (403):**
```json
{
  "response_code": "default_403",
  "message": "Forbidden"
}
```

---

## 🆚 Comparison with Other Endpoints

### 1. `/api/driver/vehicle/store` (Regular Add)
- Adds vehicle as **secondary** (or primary if first vehicle)
- Requires admin approval for vehicle only
- Does NOT request primary status for existing drivers

### 2. `/api/driver/vehicle/store-as-primary` (NEW - This Endpoint)
- Adds vehicle AND requests it as **primary**
- Requires admin approval for both vehicle and primary status
- Automatically becomes primary after admin approval

### 3. `/api/driver/vehicle/set-primary/{id}` (Change Primary)
- Vehicle must **already exist**
- Just changes primary status
- Requires admin approval

---

## 🔍 How Admin Sees It

When admin views driver approval page:

```
┌─────────────────────────────────────────────────────────────┐
│ Vehicle: Honda Civic                                        │
│ Status: 🟡 PENDING                                          │
│                                                             │
│ ⚠️  Driver requested to set this vehicle as primary        │
│                                                             │
│ [✅ Approve Vehicle]  [❌ Reject Vehicle]                   │
│                                                             │
│ When you approve this vehicle, it will also become the     │
│ driver's primary vehicle automatically.                    │
└─────────────────────────────────────────────────────────────┘
```

---

## 💡 Use Cases

### Use Case 1: First Vehicle (Driver has no vehicles)
```bash
# Driver adds first vehicle as primary
POST /api/driver/vehicle/store-as-primary
# Result: After approval, this becomes primary automatically
```

### Use Case 2: Additional Vehicle (Driver has existing vehicles)
```bash
# Driver wants to add NEW vehicle and make it primary
POST /api/driver/vehicle/store-as-primary
# Result: After approval:
# - New vehicle becomes primary
# - Old primary becomes secondary
```

### Use Case 3: Just Add Secondary Vehicle
```bash
# Driver wants to add vehicle but NOT as primary
POST /api/driver/vehicle/store
# Result: Vehicle added as secondary after approval
```

---

## 🧪 Testing

### Quick Test Script:

```bash
# Set your credentials
DRIVER_TOKEN="your-driver-token"
DRIVER_ID="your-driver-id"
BRAND_ID="84ba8b83-6a64-4cbc-8244-2194c3c8c495"
MODEL_ID="dd7e3365-5f46-4dec-b828-21fbd5568503"
CATEGORY_ID="d4d1e8f1-c716-4cff-96e1-c0b312a1a58b"

# Add vehicle as primary
curl -X POST "https://smartline-it.com/api/driver/vehicle/store-as-primary" \
  -H "Authorization: Bearer $DRIVER_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"driver_id\": \"$DRIVER_ID\",
    \"brand_id\": \"$BRAND_ID\",
    \"model_id\": \"$MODEL_ID\",
    \"category_id\": \"$CATEGORY_ID\",
    \"licence_plate_number\": \"TEST-123\",
    \"transmission\": \"automatic\",
    \"fuel_type\": \"petrol\",
    \"ownership\": \"owned\"
  }"

# Check status
curl -X GET "https://smartline-it.com/api/driver/vehicle/list" \
  -H "Authorization: Bearer $DRIVER_TOKEN" | jq '.'
```

---

## 📊 Database State

After calling this endpoint:

```sql
-- Vehicle record created:
INSERT INTO vehicles (
  driver_id,
  brand_id,
  model_id,
  category_id,
  licence_plate_number,
  vehicle_request_status,  -- 'pending'
  is_primary,              -- false (will be true after approval)
  has_pending_primary_request, -- true
  is_active                -- false (will be true after approval)
)
```

After admin approval:

```sql
-- Previous primary vehicle (if exists):
UPDATE vehicles 
SET is_primary = false 
WHERE driver_id = ? AND is_primary = true

-- New vehicle:
UPDATE vehicles 
SET 
  vehicle_request_status = 'approved',
  is_primary = true,
  has_pending_primary_request = false,
  is_active = true
WHERE id = ?
```

---

## ✅ Benefits

1. **One-step process** - Add and request as primary in single API call
2. **Admin oversight** - Admin reviews both vehicle and primary status
3. **No disruption** - Current primary stays active until approval
4. **Clear intent** - Admin knows driver wants this as primary vehicle
5. **Automatic approval** - When admin approves vehicle, primary status is handled automatically

---

## 🔐 Security

- ✅ Driver must be authenticated
- ✅ driver_id must match authenticated user
- ✅ Admin approval required
- ✅ Only one pending primary request at a time
- ✅ Activity logged for audit trail

---

## 📚 Related Endpoints

- `GET /api/driver/vehicle/list` - List all vehicles
- `POST /api/driver/vehicle/store` - Add vehicle (not as primary)
- `POST /api/driver/vehicle/store-as-primary` - **This endpoint**
- `POST /api/driver/vehicle/set-primary/{id}` - Change existing vehicle to primary
- `POST /api/driver/vehicle/update/{id}` - Update vehicle details
- `DELETE /api/driver/vehicle/delete/{id}` - Delete vehicle

---

## 🌐 Admin Panel

After adding vehicle with this endpoint, admin can review at:

```
https://smartline-it.com/admin/driver/approvals/{driver_id}
```

**Example:**
```
https://smartline-it.com/admin/driver/approvals/81623a02-d44b-4130-a4a9-dcf962b1a8a0
```

---

**Created:** 2026-01-08  
**Status:** ✅ Production Ready  
**Requires Admin Approval:** Yes  
**Sets as Primary:** Yes (after approval)
