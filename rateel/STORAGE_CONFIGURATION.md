# Storage Configuration Guide

## Current Configuration

### ✅ Local Storage (Active)
- **Storage Location:** `/root/new/`
- **Disk Name:** `secure_local`
- **Status:** ENABLED
- **Owner:** www-data:www-data
- **Permissions:** 755

New uploaded images are stored locally in `/root/new/` with the following directory structure:
```
/root/new/
├── business/
├── conversation/
├── customer/
│   ├── identity/
│   ├── level/
│   └── profile/
├── driver/
│   ├── car/
│   ├── document/
│   ├── identity/
│   ├── level/
│   ├── license/
│   ├── profile/
│   ├── record/
│   └── vehicle/
├── employee/
├── landing-page/
├── notification/
├── parcel/
│   └── category/
├── payment_modules/
├── promotion/
│   ├── banner/
│   └── discount/
├── trip/
└── vehicle/
    ├── brand/
    ├── category/
    ├── document/
    └── model/
```

### 🔒 Cloudflare R2 (Configured but Disabled)
- **Status:** DISABLED (configuration preserved)
- **Bucket:** smartline
- **Endpoint:** https://13fb48032cb714c5b997b0cc1d6c7361.r2.cloudflarestorage.com
- **Credentials:** Stored in .env (kept intact)

## How Image Upload Works

### SecureMediaUploader Service

The application uses `App\Services\Media\SecureMediaUploader` for all file uploads with:

1. **Security Features:**
   - MIME type validation per category
   - File size restrictions per category
   - Path traversal protection
   - UUID-based filenames (non-guessable)
   - Secure extension handling

2. **Upload Path Format:**
   ```
   {category}/{ownerId}/{subCategory}/{uuid}.{extension}

   Example:
   driver/550e8400-e29b-41d4-a716-446655440000/profile/a3bb2e9d-8f12-4c7e-b5d1-9c3a4f5e6d7c.jpg
   ```

3. **Validation Rules:**
   - **Profile Images:** 5MB max, JPEG/PNG/WEBP only
   - **KYC Documents:** 10MB max, JPEG/PNG/WEBP/PDF
   - **Vehicle Documents:** 10MB max, JPEG/PNG/WEBP/PDF
   - **Evidence (Video):** 50MB max, JPEG/PNG/WEBP/MP4

## Switching Between Storage Backends

### To Enable R2 Cloud Storage

1. Edit `.env`:
   ```bash
   # Change this line:
   MEDIA_DISK=secure_local

   # To:
   MEDIA_DISK=r2
   ```

2. Clear config cache:
   ```bash
   php artisan config:clear
   ```

3. All new uploads will go to Cloudflare R2

### To Return to Local Storage

1. Edit `.env`:
   ```bash
   # Change back to:
   MEDIA_DISK=secure_local
   ```

2. Clear config cache:
   ```bash
   php artisan config:clear
   ```

## File Locations

### Configuration Files
- **Filesystem config:** `/var/www/laravel/smartlinevps/rateel/config/filesystems.php`
- **Media config:** `/var/www/laravel/smartlinevps/rateel/config/media.php`
- **Environment:** `/var/www/laravel/smartlinevps/rateel/.env`

### Service Files
- **SecureMediaUploader:** `/var/www/laravel/smartlinevps/rateel/app/Services/Media/SecureMediaUploader.php`

## Testing Storage Configuration

Run the test script to verify storage is working:

```bash
cd /var/www/laravel/smartlinevps/rateel
php test_media_storage.php
```

Expected output:
```
✓ Directory exists
✓ Directory is writable
✓ Test write successful
```

## Important Notes

1. **R2 Configuration Preserved:** All R2 credentials and configuration are kept intact in `.env` and `config/filesystems.php`. You can switch back anytime.

2. **Security Restrictions Maintained:** All MIME type validation, file size limits, and security features work the same on both local and R2 storage.

3. **No Code Changes Required:** Switching between storage backends only requires changing the `MEDIA_DISK` environment variable.

4. **Permissions:** The `/root/new/` directory is owned by `www-data:www-data` to allow the web server to write files.

## Migration Notes

Existing images in the database have been updated to use full paths like:
```
/root/new/customer/profile/2025-06-14-684cdf8935386.webp
```

New images uploaded through the application will automatically go to `/root/new/` with the proper directory structure based on:
- Category (driver, customer, vehicle, etc.)
- Owner ID
- Sub-category (profile, identity, document, etc.)

## Troubleshooting

### "Permission denied" errors
Check directory ownership and permissions:
```bash
ls -ld /root/new/
chown -R www-data:www-data /root/new/
chmod -R 755 /root/new/
```

### Images not uploading
1. Check `MEDIA_DISK` setting in `.env`
2. Run `php artisan config:clear`
3. Verify `/root/new/` is writable: `php test_media_storage.php`

### Switch to R2 not working
1. Verify R2 credentials in `.env`
2. Clear config cache: `php artisan config:clear`
3. Check Laravel logs: `tail -f storage/logs/laravel.log`
