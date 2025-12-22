# Update GeoLink API Key

This guide explains how to update the GeoLink API key in your system.

## API Key Provided
**GeoLink API Key**: `4a3eb528-befa-4300-860d-9442ae141310`

## Method 1: Using Artisan Command (Recommended)

If your Laravel dependencies are installed, use the Artisan command:

```bash
php artisan geolink:update-api-key "4a3eb528-befa-4300-860d-9442ae141310"
```

### Options:
- Update both client and server keys (default):
  ```bash
  php artisan geolink:update-api-key "4a3eb528-befa-4300-860d-9442ae141310"
  ```

- Update only client key:
  ```bash
  php artisan geolink:update-api-key "4a3eb528-befa-4300-860d-9442ae141310" --client-only
  ```

- Update only server key:
  ```bash
  php artisan geolink:update-api-key "4a3eb528-befa-4300-860d-9442ae141310" --server-only
  ```

## Method 2: Via Admin Panel

1. Log in to your admin panel
2. Navigate to: **Business Settings → Third Party → Map API**
3. Enter the API key in both fields:
   - **Map API Key (Client)**: `4a3eb528-befa-4300-860d-9442ae141310`
   - **Map API Key (Server)**: `4a3eb528-befa-4300-860d-9442ae141310`
4. Click **Save**

## Method 3: Direct Database Update (SQL)

If you need to update directly in the database, run this SQL query:

### For MySQL/MariaDB:

```sql
-- Update existing record
UPDATE business_settings 
SET value = JSON_SET(
    COALESCE(value, '{}'),
    '$.map_api_key', '4a3eb528-befa-4300-860d-9442ae141310',
    '$.map_api_key_server', '4a3eb528-befa-4300-860d-9442ae141310'
)
WHERE key_name = 'google_map_api' 
  AND settings_type = 'google_map_api';

-- If the record doesn't exist, insert it
INSERT INTO business_settings (id, key_name, settings_type, value, created_at, updated_at)
SELECT 
    UUID() as id,
    'google_map_api' as key_name,
    'google_map_api' as settings_type,
    JSON_OBJECT(
        'map_api_key', '4a3eb528-befa-4300-860d-9442ae141310',
        'map_api_key_server', '4a3eb528-befa-4300-860d-9442ae141310'
    ) as value,
    NOW() as created_at,
    NOW() as updated_at
WHERE NOT EXISTS (
    SELECT 1 FROM business_settings 
    WHERE key_name = 'google_map_api' 
      AND settings_type = 'google_map_api'
);
```

### For PostgreSQL:

```sql
-- Update existing record
UPDATE business_settings 
SET value = jsonb_set(
    jsonb_set(
        COALESCE(value::jsonb, '{}'::jsonb),
        '{map_api_key}',
        '"4a3eb528-befa-4300-860d-9442ae141310"'
    ),
    '{map_api_key_server}',
    '"4a3eb528-befa-4300-860d-9442ae141310"'
)
WHERE key_name = 'google_map_api' 
  AND settings_type = 'google_map_api';

-- If the record doesn't exist, insert it
INSERT INTO business_settings (id, key_name, settings_type, value, created_at, updated_at)
SELECT 
    gen_random_uuid() as id,
    'google_map_api' as key_name,
    'google_map_api' as settings_type,
    jsonb_build_object(
        'map_api_key', '4a3eb528-befa-4300-860d-9442ae141310',
        'map_api_key_server', '4a3eb528-befa-4300-860d-9442ae141310'
    ) as value,
    NOW() as created_at,
    NOW() as updated_at
WHERE NOT EXISTS (
    SELECT 1 FROM business_settings 
    WHERE key_name = 'google_map_api' 
      AND settings_type = 'google_map_api'
);
```

## Method 4: Using Standalone PHP Script

If Laravel is set up but you can't use Artisan, run:

```bash
php update_geolink_api_key.php
```

**Note**: This requires the `vendor` directory to be present and dependencies installed.

## After Updating

After updating the API key, make sure to:

1. **Clear the cache**:
   ```bash
   php artisan cache:clear
   ```

2. **Test the API** by:
   - Making a geocoding request
   - Testing place autocomplete
   - Verifying reverse geocoding

## Verification

To verify the API key was set correctly, you can:

1. Check in the admin panel (Business Settings → Third Party → Map API)
2. Query the database:
   ```sql
   SELECT key_name, settings_type, value 
   FROM business_settings 
   WHERE key_name = 'google_map_api';
   ```
3. Test an API endpoint that uses the GeoLink API

## Important Notes

- The **client key** (`map_api_key`) is used for frontend JavaScript operations
- The **server key** (`map_api_key_server`) is used for backend API calls
- Both keys can be the same if your GeoLink API key supports both use cases
- The API key is stored in the `business_settings` table with:
  - `key_name` = `'google_map_api'`
  - `settings_type` = `'google_map_api'`
  - `value` = JSON object with `map_api_key` and `map_api_key_server`




