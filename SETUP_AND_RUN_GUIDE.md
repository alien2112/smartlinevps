# 🚀 Application Setup and Run Guide

## Prerequisites

Before running this Laravel application, ensure you have:

### Required Software:
- ✅ **PHP 8.1 or higher** (Required: `^8.1`)
- ✅ **Composer** (PHP dependency manager)
- ✅ **Node.js & NPM** (for frontend assets)
- ✅ **MySQL/MariaDB** (Database)
- ✅ **Web Server** (Apache/Nginx) OR use PHP built-in server

---

## 📋 Step-by-Step Setup Instructions

### Step 1: Check PHP Version

```bash
php -v
```

You should see PHP 8.1 or higher. If not, install/upgrade PHP.

---

### Step 2: Install Composer Dependencies

Navigate to the project root directory:

```bash
cd G:\smart-line-backup\smart-line.space
```

Install PHP dependencies:

```bash
composer install
```

**If you encounter memory issues:**
```bash
composer install --no-dev --optimize-autoloader
```

---

### Step 3: Environment Configuration

Copy the environment file:

```bash
# Check if .env exists
dir .env

# If not, copy from example (if available)
copy .env.example .env
```

**If no .env.example exists, create a .env file** with these basic settings:

```env
APP_NAME=SmartLine
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=smartline_db
DB_USERNAME=root
DB_PASSWORD=

BROADCAST_DRIVER=pusher
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

# Geoapify API Key (NEW)
GEOAPIFY_API_KEY=5809f244-50ca-4ecb-a738-1f0fd9ee9132
```

---

### Step 4: Generate Application Key

```bash
php artisan key:generate
```

This will add `APP_KEY` to your `.env` file.

---

### Step 5: Database Setup

1. **Create a MySQL database:**

```sql
CREATE DATABASE smartline_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. **Update .env with your database credentials:**

```env
DB_DATABASE=smartline_db
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

3. **Run migrations:**

```bash
php artisan migrate
```

**If you need to seed the database with sample data:**

```bash
php artisan migrate --seed
```

---

### Step 6: Install Frontend Dependencies

Install Node.js dependencies:

```bash
npm install
```

**Note:** Make sure you have Node.js installed. Check with:
```bash
node -v
npm -v
```

---

### Step 7: Build Frontend Assets

For development:
```bash
npm run dev
```

For production:
```bash
npm run production
```

---

### Step 8: Clear and Optimize Cache

After all migrations and changes, clear all caches:

```bash
# Clear configuration cache
php artisan config:clear

# Clear application cache
php artisan cache:clear

# Clear route cache
php artisan route:clear

# Clear view cache
php artisan view:clear

# Optimize for production (optional)
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

### Step 9: Set Storage Permissions

```bash
# Windows PowerShell (as Administrator)
icacls storage /grant Users:F /T
icacls bootstrap/cache /grant Users:F /T

# Or manually:
# Right-click storage folder → Properties → Security → Give full control
# Right-click bootstrap/cache folder → Properties → Security → Give full control
```

---

### Step 10: Run the Application

#### Option A: Using PHP Built-in Server (Development)

```bash
php artisan serve
```

The application will be available at: **http://localhost:8000**

#### Option B: Using Custom Host and Port

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

#### Option C: Using XAMPP/WAMP/MAMP

1. Copy your project to `htdocs` or `www` folder
2. Configure virtual host (optional)
3. Access via: `http://localhost/smart-line.space/public`

---

## 🔧 Important Configuration After Migration

### Update Geoapify API Key in Admin Panel

After the application is running:

1. **Login to Admin Panel**
   - Usually at: `http://localhost:8000/admin` or `http://localhost:8000/login`

2. **Navigate to Business Settings**
   - Go to: **Business Settings → Third Party → Google Map API**

3. **Update API Key**
   - Set API key to: `5809f244-50ca-4ecb-a738-1f0fd9ee9132`
   - Save the changes

4. **Clear Cache Again**
   ```bash
   php artisan config:cache
   php artisan cache:clear
   php artisan view:clear
   ```

---

## 🗄️ Database Configuration

### If Database Already Exists

If you have an existing database backup:

1. **Import Database:**
   ```bash
   mysql -u username -p database_name < backup.sql
   ```

2. **Or use phpMyAdmin:**
   - Login to phpMyAdmin
   - Select your database
   - Go to Import tab
   - Choose your SQL file
   - Click Go

### Run Fresh Migrations

If starting fresh:
```bash
php artisan migrate:fresh --seed
```

**⚠️ Warning:** This will drop all tables and recreate them!

---

## 📁 Understanding Project Structure

This project appears to have:

1. **Root Directory**: Main Laravel application
2. **rateel/ Directory**: Possibly a duplicate or sub-application

**For running the application:**
- If `artisan` is in root → Run commands from root
- If `artisan` is in `rateel/` → Run commands from `rateel/` directory

**Check which one to use:**
```bash
# Check root
dir artisan

# Check rateel
dir rateel\artisan
```

---

## 🔍 Troubleshooting Common Issues

### Issue 1: "Class not found" or Autoload errors

**Solution:**
```bash
composer dump-autoload
```

### Issue 2: "Permission denied" on storage

**Solution (Windows):**
- Right-click `storage` folder
- Properties → Security → Edit
- Give "Users" full control
- Apply to all subfolders

### Issue 3: "500 Internal Server Error"

**Solutions:**
1. Check `.env` file exists and is configured
2. Clear cache: `php artisan config:clear`
3. Check `storage/logs/laravel.log` for errors
4. Ensure `APP_DEBUG=true` in `.env` for development

### Issue 4: "SQLSTATE[HY000] [2002] No connection"

**Solution:**
- Check database credentials in `.env`
- Ensure MySQL service is running
- Test connection: `mysql -u username -p`

### Issue 5: Module errors

**Solution:**
```bash
php artisan module:publish-migrations
php artisan migrate
```

### Issue 6: "Route not found" or 404 errors

**Solution:**
```bash
php artisan route:clear
php artisan route:cache
php artisan config:cache
```

---

## 🚀 Quick Start Commands (All in One)

Copy and paste this entire block in PowerShell (run as Administrator):

```powershell
# Navigate to project directory
cd G:\smart-line-backup\smart-line.space

# Install dependencies
composer install
npm install

# Create .env if doesn't exist (you'll need to configure it manually)
if (-not (Test-Path .env)) {
    Copy-Item .env.example .env -ErrorAction SilentlyContinue
    if (-not (Test-Path .env)) {
        Write-Host "Please create .env file manually"
    }
}

# Generate app key
php artisan key:generate

# Set permissions (if needed)
icacls storage /grant Users:F /T /C
icacls bootstrap\cache /grant Users:F /T /C

# Build assets
npm run dev

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Run migrations (make sure database is configured first)
# php artisan migrate

# Start server
php artisan serve
```

---

## 📝 Post-Setup Checklist

After successful installation:

- [ ] Application runs without errors
- [ ] Can access login/admin page
- [ ] Database connection works
- [ ] Storage permissions are set
- [ ] Frontend assets load correctly
- [ ] Geoapify API key is configured in admin panel
- [ ] Maps load (test in admin panel)
- [ ] No JavaScript console errors

---

## 🌐 Production Deployment

For production deployment:

1. **Update .env:**
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://yourdomain.com
   ```

2. **Optimize:**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   composer install --optimize-autoloader --no-dev
   npm run production
   ```

3. **Set proper file permissions**

4. **Configure web server (Apache/Nginx)**

---

## 📞 Need Help?

- Check Laravel logs: `storage/logs/laravel.log`
- Check PHP error logs
- Enable debug mode: `APP_DEBUG=true` in `.env`
- Review migration guide: See `GEOLINK_MIGRATION_GUIDE.md`

---

**Current Migration Status:**
- ✅ Backend API: Migrated to Geoapify
- ✅ Frontend Libraries: Updated to Leaflet.js
- ⚠️ JavaScript Code: Needs conversion (see `JAVASCRIPT_CONVERSION_GUIDE.md`)

---

**Ready to run!** Start with Step 1 and follow the guide. 🎉

