# Driver License Upload with Optional Vehicle Information

## Endpoint
`POST /api/driver/auth/upload/license`

## Description
Upload driver's license documents (front and back) with optional vehicle information. This endpoint allows drivers to submit their vehicle details at the same time as uploading their license.

## Request Parameters

### Required Fields
- `phone` (string, required): Driver's phone number (min: 10, max: 15)
- `license_front` (file, required): Front image of driver's license (jpg, jpeg, png, pdf, max: 5MB)
- `license_back` (file, required): Back image of driver's license (jpg, jpeg, png, pdf, max: 5MB)

### Optional Vehicle Information
- `brand_id` (UUID, optional): Vehicle brand ID (must exist in `vehicle_brands` table)
- `model_id` (UUID, optional): Vehicle model ID (must exist in `vehicle_models` table)
- `category_id` (UUID, optional): Vehicle category ID (must exist in `vehicle_categories` table)
  - If not provided, uses the category selected during vehicle type step
- `licence_plate_number` (string, optional): Vehicle license plate number (max: 255 chars)
- `licence_expire_date` (date, optional): License expiration date (format: YYYY-MM-DD)
- `ownership` (string, optional): Vehicle ownership type
  - Options: `owned`, `rented`, `leased`
  - Default: `owned`
- `fuel_type` (string, optional): Vehicle fuel type
  - Options: `petrol`, `diesel`, `electric`, `hybrid`
  - Default: `petrol`
- `vin_number` (string, optional): Vehicle Identification Number (max: 255 chars)
- `transmission` (string, optional): Transmission type
  - Options: `manual`, `automatic`
- `parcel_weight_capacity` (numeric, optional): Parcel weight capacity in kg
- `year_id` (integer, optional): Vehicle year ID (must exist in `vehicle_years` table)

## Example Request

### Without Vehicle Information (License Only)
```bash
curl -X POST "https://smartline-it.com/api/driver/auth/upload/license" \
  -H "Content-Type: multipart/form-data" \
  -F "phone=+201234567890" \
  -F "license_front=@/path/to/license_front.jpg" \
  -F "license_back=@/path/to/license_back.jpg"
```

### With Vehicle Information
```bash
curl -X POST "https://smartline-it.com/api/driver/auth/upload/license" \
  -H "Content-Type: multipart/form-data" \
  -F "phone=+201234567890" \
  -F "license_front=@/path/to/license_front.jpg" \
  -F "license_back=@/path/to/license_back.jpg" \
  -F "brand_id=550e8400-e29b-41d4-a716-446655440000" \
  -F "model_id=660e8400-e29b-41d4-a716-446655440001" \
  -F "category_id=d4d1e8f1-c716-4cff-96e1-c0b312a1a58b" \
  -F "licence_plate_number=ABC-1234" \
  -F "licence_expire_date=2027-12-31" \
  -F "ownership=owned" \
  -F "fuel_type=petrol" \
  -F "transmission=automatic" \
  -F "vin_number=1HGBH41JXMN109186"
```

## Response Examples

### Success Response (Documents Only)
```json
{
  "status": "success",
  "message": "Documents uploaded successfully",
  "data": {
    "next_step": "documents",
    "uploaded_documents": [
      {
        "type": "license_front",
        "id": "7284f85b-5d16-47ba-a848-560a0744f883",
        "file_url": "https://smartline-it.com/storage/driver-documents/c4bcf628-64cc-4a8e-83e8-10ea42376a0d/582b7cdf-8a78-4a9f-946c-4c8a9a5a89c3.png",
        "original_name": "license_front.jpg"
      },
      {
        "type": "license_back",
        "id": "8395g96c-6e27-58cb-b959-671b1855g994",
        "file_url": "https://smartline-it.com/storage/driver-documents/c4bcf628-64cc-4a8e-83e8-10ea42376a0d/ad2f8be8-6d43-4fc4-a763-7bfdf10eb627.png",
        "original_name": "license_back.jpg"
      }
    ],
    "vehicle_created": false,
    "all_uploaded_types": ["id_front", "id_back", "license_front", "license_back"],
    "missing_documents": ["car_front", "car_back", "selfie"]
  }
}
```

### Success Response (Documents + Vehicle)
```json
{
  "status": "success",
  "message": "Documents uploaded successfully and vehicle information saved",
  "data": {
    "next_step": "documents",
    "uploaded_documents": [
      {
        "type": "license_front",
        "id": "7284f85b-5d16-47ba-a848-560a0744f883"
      },
      {
        "type": "license_back",
        "id": "8395g96c-6e27-58cb-b959-671b1855g994"
      }
    ],
    "vehicle_created": true,
    "all_uploaded_types": ["id_front", "id_back", "license_front", "license_back"],
    "missing_documents": ["car_front", "car_back", "selfie"]
  }
}
```

### Success Response (All Documents Complete)
```json
{
  "status": "success",
  "message": "All documents and vehicle information submitted successfully. Your account requires admin approval due to your selected vehicle category.",
  "data": {
    "next_step": "pending_approval",
    "uploaded_documents": [
      {
        "type": "license_front",
        "id": "7284f85b-5d16-47ba-a848-560a0744f883",
        "file_url": "https://smartline-it.com/storage/driver-documents/c4bcf628-64cc-4a8e-83e8-10ea42376a0d/582b7cdf-8a78-4a9f-946c-4c8a9a5a89c3.png",
        "original_name": "license_front.jpg"
      },
      {
        "type": "license_back",
        "id": "8395g96c-6e27-58cb-b959-671b1855g994",
        "file_url": "https://smartline-it.com/storage/driver-documents/c4bcf628-64cc-4a8e-83e8-10ea42376a0d/ad2f8be8-6d43-4fc4-a763-7bfdf10eb627.png",
        "original_name": "license_back.jpg"
      }
    ],
    "vehicle_created": true,
    "requires_admin_approval": true
  }
}
```

### Error Response (Validation Failed)
```json
{
  "status": "error",
  "message": "Validation failed",
  "errors": {
    "license_front": ["The license front field is required."],
    "brand_id": ["The selected brand id is invalid."]
  }
}
```

## Required API Endpoints for Fetching Vehicle Data

To get the list of available vehicle brands, models, and categories, use these endpoints. **No authentication required** - these are public endpoints for the onboarding process:

### Get Vehicle Categories
```
GET /api/driver/vehicle/category/list

Response:
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": [
    {
      "id": "d4d1e8f1-c716-4cff-96e1-c0b312a1a58b",
      "name": "Taxi",
      "image": "/root/new/vehicle/category/2025-07-01-68630eef0ad2b.webp",
      "type": "car",
      "self_selectable": true,
      "requires_admin_assignment": false
    },
    ...
  ]
}
```

### Get Vehicle Brands
```
GET /api/driver/vehicle/brand/list

Response:
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": [
    {
      "id": "84ba8b83-6a64-4cbc-8244-2194c3c8c495",
      "name": "Unknown",
      "description": "Default brand for vehicles without a specific brand",
      "image": null,
      "is_active": true,
      "vehicle_models": [...],
      "created_at": "2025-12-29T15:46:36.000000Z"
    },
    ...
  ]
}
```

### Get Vehicle Models
```
GET /api/driver/vehicle/model/list
Query Parameters:
  - brand_id (optional): Filter models by brand ID
  - limit (optional): Number of results per page
  - offset (optional): Pagination offset

Response:
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": [
    {
      "id": "dd7e3365-5f46-4dec-b828-21fbd5568503",
      "name": "Unknown",
      "seat_capacity": 4,
      "maximum_weight": 0,
      "hatch_bag_capacity": 0,
      "engine": "0",
      "description": "Default model for vehicles without a specific model",
      "image": null,
      "is_active": true,
      "created_at": "2025-12-29T15:46:36.000000Z"
    },
    ...
  ]
}
```

**Example with brand filter:**
```bash
curl "https://smartline-it.com/api/driver/vehicle/model/list?brand_id=84ba8b83-6a64-4cbc-8244-2194c3c8c495"
```

## Notes

1. **Vehicle Information is Optional**: The endpoint works with or without vehicle information. If vehicle info is not provided, only the license documents will be uploaded.

2. **Partial Vehicle Info**: To create a vehicle record, you MUST provide at minimum:
   - `brand_id`
   - `model_id`
   - `licence_plate_number`

   If these three fields are not all present, no vehicle record will be created.

3. **Vehicle Updates**: If the driver already has a primary vehicle, the vehicle information will be updated. Otherwise, a new vehicle record is created.

4. **Vehicle Status**: Created vehicles are set to `vehicle_request_status = PENDING` and require admin approval.

5. **Document Types Fixed**: The ENUM issue for document types has been resolved. The following types are now supported:
   - `id_front`, `id_back`
   - `license_front`, `license_back`
   - `car_front`, `car_back`
   - `selfie`

## Migration Applied

A migration has been applied to fix the ENUM column issue:
- Migration file: `2026_01_07_142000_update_driver_documents_type_enum.php`
- Updated `driver_documents.type` ENUM to include new document types
