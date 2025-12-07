# ⚡ Quick Start - Run Your Application NOW

## Fast Track Setup (Copy & Paste These Commands)

### Step 1: Open Terminal/PowerShell

Press `Win + X` and select "Windows PowerShell" or "Terminal"

### Step 2: Navigate to Project Directory

```powershell
cd G:\smart-line-backup\smart-line.space
```

### Step 3: Install Dependencies

```powershell
# Install PHP dependencies
composer install

# Install Node.js dependencies  
npm install
```

### Step 4: Configure Environment

**Check if .env file exists:**
```powershell
Test-Path .env
```

**If .env doesn't exist, create it:**
```powershell
# Create basic .env file
@"
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
QUEUE_CONNECTION=sync
SESSION_DRIVER=file

# Geoapify API Key
GEOAPIFY_API_KEY=5809f244-50ca-4ecb-a738-1f0fd9ee9132
"@ | Out-File -FilePath .env -Encoding UTF8
```

### Step 5: Generate Application Key

```powershell
php artisan key:generate
```

### Step 6: Set Up Database

**Option A: If you have an existing database backup**
```powershell
# Import your database backup
# mysql -u root -p smartline_db < backup.sql
```

**Option B: Create fresh database**
```sql
-- Run in MySQL console or phpMyAdmin:
CREATE DATABASE smartline_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then update `.env` with your database credentials and run:
```powershell
php artisan migrate
```

### Step 7: Build Frontend Assets

```powershell
npm run dev
```

### Step 8: Clear Cache

```powershell
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### Step 9: Start the Server

```powershell
php artisan serve
```

**🎉 Your application is now running at: http://localhost:8000**

---

## 🚨 Troubleshooting Quick Fixes

### If composer install fails:
```powershell
composer install --ignore-platform-reqs
```

### If npm install fails:
```powershell
npm install --legacy-peer-deps
```

### If permission errors occur:
```powershell
# Run PowerShell as Administrator, then:
icacls storage /grant Users:F /T /C
icacls bootstrap\cache /grant Users:F /T /C
```

### If you get "Class not found":
```powershell
composer dump-autoload
php artisan config:clear
php artisan cache:clear
```

### If database connection fails:
1. Check MySQL service is running
2. Verify credentials in `.env`
3. Test connection: `mysql -u root -p`

---

## 📋 After First Run

Once the application is running:

1. **Access Admin Panel:**
   - Usually: `http://localhost:8000/admin/login`

2. **Configure Geoapify API Key:**
   - Login to admin
   - Go to: Business Settings → Third Party → Google Map API
   - Enter: `5809f244-50ca-4ecb-a738-1f0fd9ee9132`
   - Save

3. **Clear cache again:**
   ```powershell
   php artisan config:cache
   php artisan cache:clear
   ```

---

## 🎯 One-Command Quick Test

Want to test if everything is working? Run this:

```powershell
cd G:\smart-line-backup\smart-line.space; php artisan --version; php -v; composer --version; node -v
```

This will show you versions of:
- ✅ Laravel
- ✅ PHP  
- ✅ Composer
- ✅ Node.js

---

## 💡 Need More Details?

See **SETUP_AND_RUN_GUIDE.md** for comprehensive setup instructions.

---

**Ready? Start with Step 1! 🚀**

