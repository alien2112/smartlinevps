# Quick Reference: Vehicle Approval System

## Admin Actions

### View Driver with Vehicles
**URL:** `https://smartline-it.com/admin/driver/approvals/{driver_id}`

**What You'll See:**
- Driver information
- All uploaded documents
- **All vehicles** (primary and secondary) with:
  - Vehicle details (brand, model, category)
  - Status badge (pending/approved/rejected)
  - Primary indicator
  - Approval/rejection buttons (for pending vehicles)

### Approve a Vehicle
1. Go to driver approval page
2. Find the vehicle with "Pending" status
3. Click "Approve Vehicle" button
4. Vehicle status changes to "approved"
5. Driver can now use this vehicle

### Reject a Vehicle
1. Go to driver approval page
2. Find the vehicle with "Pending" status
3. Click "Reject Vehicle" button
4. Enter rejection reason in modal
5. Click "Reject Vehicle" to confirm
6. Driver will see rejection reason

## Driver Actions (via API)

### Add Secondary Vehicle
```bash
POST /api/driver/vehicle/store
Authorization: Bearer {token}
Content-Type: application/json

{
  "driver_id": "uuid",
  "brand_id": "uuid",
  "model_id": "uuid",
  "category_id": "uuid",
  "licence_plate_number": "ABC-123",
  "licence_expire_date": "2027-12-31",
  "transmission": "automatic",
  "fuel_type": "petrol",
  "ownership": "owned",
  "is_primary": false
}
```

**Response:**
```json
{
  "response_code": "vehicle_request_200",
  "message": "Vehicle information submitted successfully. Your vehicle is pending approval."
}
```

### List All Vehicles
```bash
GET /api/driver/vehicle/list
Authorization: Bearer {token}
```

**Response:**
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "vehicles": [
      {
        "id": "uuid",
        "brand": "Toyota",
        "model": "Camry",
        "category": "Taxi",
        "licence_plate_number": "ABC-1234",
        "is_primary": true,
        "vehicle_request_status": "approved",
        "has_pending_update": false
      }
    ],
    "total": 1
  }
}
```

### Switch Primary Vehicle
```bash
POST /api/driver/vehicle/set-primary/{vehicle_id}
Authorization: Bearer {token}
```

**Requirements:**
- Vehicle must be approved
- Cannot set pending/rejected vehicle as primary

### Delete Vehicle
```bash
DELETE /api/driver/vehicle/delete/{vehicle_id}
Authorization: Bearer {token}
```

**Requirements:**
- Cannot delete primary vehicle if other vehicles exist
- Must set another vehicle as primary first

## Vehicle Status Meanings

| Status | Can Use? | Can Set Primary? | Action Needed |
|--------|----------|------------------|---------------|
| `pending` | ❌ No | ❌ No | Wait for admin approval |
| `approved` | ✅ Yes | ✅ Yes | Ready to use |
| `rejected` | ❌ No | ❌ No | Fix issues and resubmit |

## Approval Workflow

```
Driver adds vehicle → Status: PENDING
                              ↓
                      Admin reviews
                              ↓
                    ┌─────────┴─────────┐
                    ↓                   ↓
            Status: APPROVED    Status: REJECTED
                    ↓                   ↓
            Can use vehicle      Cannot use
            Can set primary      See rejection reason
```

## Common Scenarios

### Scenario 1: Driver with one vehicle wants to add another
1. Driver calls `POST /api/driver/vehicle/store` with `is_primary: false`
2. Vehicle created with status `pending`
3. Admin reviews and approves
4. Driver can now switch between vehicles using `set-primary`

### Scenario 2: Driver wants to change primary vehicle
1. Driver calls `GET /api/driver/vehicle/list` to see all vehicles
2. Driver calls `POST /api/driver/vehicle/set-primary/{id}` with approved vehicle ID
3. Old primary becomes secondary
4. New vehicle becomes primary

### Scenario 3: Admin rejects a vehicle
1. Admin clicks "Reject Vehicle" on approval page
2. Enters reason (e.g., "Invalid license plate")
3. Vehicle status changes to `rejected`
4. Driver sees rejection in app
5. Driver can submit new vehicle or fix issues

## Database Queries (for debugging)

### Check driver's vehicles
```sql
SELECT id, driver_id, licence_plate_number, is_primary, 
       vehicle_request_status, is_active, created_at
FROM vehicles 
WHERE driver_id = 'driver-uuid' 
  AND deleted_at IS NULL
ORDER BY is_primary DESC, created_at DESC;
```

### Check pending vehicles
```sql
SELECT v.id, u.first_name, u.last_name, v.licence_plate_number,
       v.vehicle_request_status, v.created_at
FROM vehicles v
JOIN users u ON v.driver_id = u.id
WHERE v.vehicle_request_status = 'pending'
  AND v.deleted_at IS NULL
ORDER BY v.created_at DESC;
```

### Check vehicles needing approval
```sql
SELECT v.id, u.first_name, u.last_name, u.phone,
       v.licence_plate_number, v.vehicle_request_status,
       v.draft IS NOT NULL as has_draft
FROM vehicles v
JOIN users u ON v.driver_id = u.id
WHERE v.vehicle_request_status = 'pending'
   OR v.draft IS NOT NULL
ORDER BY v.created_at DESC;
```

## Troubleshooting

### Vehicle not showing in admin panel
- Check if driver has `onboarding_state = 'pending_approval'` or `'approved'`
- Verify vehicle exists: `SELECT * FROM vehicles WHERE id = 'vehicle-uuid'`
- Check if vehicle is soft-deleted: `deleted_at IS NULL`

### Cannot set vehicle as primary
- Verify vehicle status is `approved`
- Check if vehicle belongs to driver
- Ensure vehicle is active (`is_active = true`)

### Vehicle stuck in pending
- Admin needs to manually approve via `/admin/driver/approvals/{driver_id}`
- Check logs for approval errors: `tail -f storage/logs/laravel.log`

## Support URLs

- Driver Approval Page: `https://smartline-it.com/admin/driver/approvals/{driver_id}`
- API Documentation: `SECONDARY_VEHICLE_UPLOAD_GUIDE.md`
- Implementation Details: `SECONDARY_VEHICLE_IMPLEMENTATION.md`

---

**Last Updated:** 2026-01-08
