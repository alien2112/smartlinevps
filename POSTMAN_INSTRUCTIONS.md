# How to Use the Postman Collection

## NEW: Complete Laravel + Node.js Testing

For comprehensive testing of both Laravel API and Node.js real-time service together, see:
**[TESTING_LARAVEL_NODEJS_TOGETHER.md](TESTING_LARAVEL_NODEJS_TOGETHER.md)**

And use the enhanced collection:
**`SmartLine_Laravel_NodeJS_Testing.postman_collection.json`**

---

## Legacy: Basic Driver Pending Rides Test

### Step 1: Import the Collection

1. Open Postman
2. Click **Import** button (top left)
3. Select the file: `Pending_Rides_Test.postman_collection.json`
4. Click **Import**

### Step 2: Set Base URL

1. In Postman, click on the collection name "Driver Pending Rides - Fixed"
2. Go to the **Variables** tab
3. Set `base_url` to your server URL (e.g., `http://localhost:8000` or `https://your-domain.com`)
4. Click **Save**

## Step 3: Run the Requests

### Request 1: Login Driver
1. Click on "1. Login Driver"
2. Click **Send**
3. **The token will be automatically saved** for the next request
4. You should see a success response with the driver data
### Request 2: Get Pending Rides
1. Click on "2. Get Pending Rides (With zoneId Header)"
2. **Important**: Check that the Headers tab has:
   - `Authorization: Bearer {{auth_token}}` (auto-filled from step 1)
   - `zoneId: 778d28d6-1193-436d-9d2b-6c2c31185c8a` (REQUIRED!)
3. Click **Send**
4. **You should see Trip #102363** in the response!

## Expected Response

```json
{
    "response_code": "default_200",
    "message": "Successfully loaded",
    "total_size": 1,
    "limit": 10,
    "offset": 1,
    "data": [
        {
            "ref_id": "102363",
            "customer": {
                "first_name": "mahmoud",
                "phone": "+201012748258"
            },
            "type": "ride_request",
            "current_status": "pending",
            "estimated_fare": 34,
            ...
        }
    ]
}
```

## Troubleshooting

### If you get "Unauthenticated" error:
- Run request #1 (Login) again to get a fresh token

### If you get empty array:
- Make sure the `zoneId` header is set: `778d28d6-1193-436d-9d2b-6c2c31185c8a`
- Check that the driver is online in the app/database

### If you get "Zone not found" error:
- The `zoneId` header is missing - add it in the Headers tab

## Driver Credentials

- **Phone**: +201208673028
- **Password**: password123
- **Zone**: 778d28d6-1193-436d-9d2b-6c2c31185c8a (الجيزة)

## Notes

- The driver MUST be online and available
- The `zoneId` header is **REQUIRED** for the pending rides endpoint
- All backend fixes have been applied:
  - ✓ SRID fix for spatial queries
  - ✓ whereIn fix for vehicle categories
  - ✓ Driver and trip zones match
