# 🚀 START HERE - How to Run Your Application

## ⚠️ Important: Read This First!

Based on your project structure:
- ✅ Your main application is in the **`rateel`** directory
- ⚠️ PHP and Composer are not currently installed or not in your system PATH

---

## 📋 Prerequisites - Install These First

### 1. Install PHP 8.1+ (Required)

**Option A: Download PHP for Windows**
1. Go to: https://windows.php.net/download/
2. Download PHP 8.1+ (Thread Safe version)
3. Extract to `C:\php`
4. Add to PATH:
   - Right-click "This PC" → Properties → Advanced System Settings
   - Click "Environment Variables"
   - Under "System Variables", find "Path" and click "Edit"
   - Click "New" and add: `C:\php`
   - Click OK on all dialogs

**Option B: Use XAMPP (Easier)**
1. Download XAMPP: https://www.apachefriends.org/
2. Install it (includes PHP, MySQL, Apache)
3. PHP will be at: `C:\xampp\php`
4. Add `C:\xampp\php` to your system PATH (see Option A steps)

**Verify PHP Installation:**
```powershell
php -v
```

---

### 2. Install Composer (Required)

1. Download Composer: https://getcomposer.org/download/
2. Run the installer: `Composer-Setup.exe`
3. It will automatically find your PHP installation
4. Complete the installation

**Verify Composer:**
```powershell
composer --version
```

---

### 3. Install Node.js (For Frontend Assets)

1. Download Node.js: https://nodejs.org/
2. Install the LTS version
3. It will add npm to your PATH automatically

**Verify Node.js:**
```powershell
node -v
npm -v
```

---

### 4. Install MySQL (For Database)

**Option A: Use XAMPP (Includes MySQL)**
- If you installed XAMPP, MySQL is already included

**Option B: Install MySQL Separately**
1. Download MySQL: https://dev.mysql.com/downloads/mysql/
2. Install MySQL Server
3. Remember your root password!

**Verify MySQL:**
```powershell
mysql --version
```

---

## 🎯 Quick Setup Steps (After Installing Prerequisites)

### Step 1: Navigate to the Correct Directory

```powershell
cd G:\smart-line-backup\smart-line.space\rateel
```

**⚠️ IMPORTANT:** All commands must be run from the `rateel` directory, not the root!

---

### Step 2: Install PHP Dependencies

```powershell
composer install
```

If you get memory errors:
```powershell
composer install --no-dev --optimize-autoloader
```

---

### Step 3: Install Node.js Dependencies

```powershell
npm install
```

---

### Step 4: Configure Environment

**Check if .env exists:**
```powershell
Test-Path .env
```

**Create .env file if it doesn't exist:**
```powershell
# Copy from example (if available)
Copy-Item .env.example .env

# OR create manually - see environment setup below
```

**Basic .env Configuration:**
Create a file named `.env` in the `rateel` directory with:

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
DB_PASSWORD=your_mysql_password

BROADCAST_DRIVER=pusher
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file

# Geoapify API Key (Important for Maps!)
GEOAPIFY_API_KEY=5809f244-50ca-4ecb-a738-1f0fd9ee9132
```

---

### Step 5: Generate Application Key

```powershell
php artisan key:generate
```

---

### Step 6: Set Up Database

**Create Database:**
1. Open MySQL command line or phpMyAdmin
2. Run:
```sql
CREATE DATABASE smartline_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**Or if you have an existing database backup:**
```powershell
mysql -u root -p smartline_db < your_backup.sql
```

**Update .env with your database password, then run migrations:**
```powershell
php artisan migrate
```

**If you want sample data:**
```powershell
php artisan migrate --seed
```

---

### Step 7: Build Frontend Assets

```powershell
npm run dev
```

For production:
```powershell
npm run production
```

---

### Step 8: Set Storage Permissions (Important!)

**Run PowerShell as Administrator, then:**
```powershell
cd G:\smart-line-backup\smart-line.space\rateel

# Set permissions
icacls storage /grant Users:F /T /C
icacls bootstrap\cache /grant Users:F /T /C
```

**Or manually:**
1. Right-click `rateel\storage` folder
2. Properties → Security → Edit
3. Give "Users" full control
4. Apply to all subfolders
5. Repeat for `rateel\bootstrap\cache`

---

### Step 9: Clear All Caches

```powershell
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

---

### Step 10: Start the Application! 🎉

```powershell
php artisan serve
```

**Your application is now running at:**
👉 **http://localhost:8000**

---

## 🎯 All-in-One Setup Script

**After installing PHP, Composer, and Node.js**, you can run this complete setup:

```powershell
# Navigate to rateel directory
cd G:\smart-line-backup\smart-line.space\rateel

# Install dependencies
Write-Host "Installing PHP dependencies..." -ForegroundColor Green
composer install

Write-Host "Installing Node.js dependencies..." -ForegroundColor Green
npm install

# Create .env if doesn't exist
if (-not (Test-Path .env)) {
    Write-Host "Creating .env file..." -ForegroundColor Yellow
    if (Test-Path .env.example) {
        Copy-Item .env.example .env
    } else {
        Write-Host "Please create .env file manually!" -ForegroundColor Red
    }
}

# Generate app key
Write-Host "Generating application key..." -ForegroundColor Green
php artisan key:generate

# Build assets
Write-Host "Building frontend assets..." -ForegroundColor Green
npm run dev

# Clear caches
Write-Host "Clearing caches..." -ForegroundColor Green
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

Write-Host "`n✅ Setup Complete!`n" -ForegroundColor Green
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. Configure database in .env file" -ForegroundColor Yellow
Write-Host "2. Run: php artisan migrate" -ForegroundColor Yellow
Write-Host "3. Run: php artisan serve" -ForegroundColor Yellow
```

---

## 🔧 After Application Starts

### 1. Access Admin Panel

- URL: `http://localhost:8000/admin/login`
- Check your database for admin credentials (or see documentation)

### 2. Configure Geoapify API Key

After logging in:
1. Go to: **Business Settings → Third Party → Google Map API**
2. Enter API Key: `5809f244-50ca-4ecb-a738-1f0fd9ee9132`
3. Save

### 3. Clear Cache Again

```powershell
php artisan config:cache
php artisan cache:clear
```

---

## 🚨 Troubleshooting

### "php is not recognized"
- PHP is not installed or not in PATH
- Solution: Install PHP and add to PATH (see Prerequisites #1)

### "composer is not recognized"
- Composer is not installed
- Solution: Install Composer (see Prerequisites #2)

### "Cannot find artisan"
- You're in the wrong directory
- Solution: Make sure you're in the `rateel` directory

### "Permission denied" on storage
- Run PowerShell as Administrator
- Run: `icacls storage /grant Users:F /T /C`

### Database connection errors
- Check MySQL service is running
- Verify credentials in `.env`
- Test: `mysql -u root -p`

### Module errors
```powershell
php artisan module:publish-migrations
php artisan migrate
```

---

## 📚 Additional Resources

- **Full Setup Guide**: See `SETUP_AND_RUN_GUIDE.md`
- **Quick Start**: See `QUICK_START.md`
- **Migration Guide**: See `GEOLINK_MIGRATION_GUIDE.md`

---

## ✅ Checklist

Before running, ensure:

- [ ] PHP 8.1+ installed and in PATH
- [ ] Composer installed
- [ ] Node.js installed
- [ ] MySQL installed and running
- [ ] In the `rateel` directory for all commands
- [ ] `.env` file created and configured
- [ ] Database created
- [ ] Storage permissions set

---

## 🎉 Ready to Start!

**Once all prerequisites are installed:**

1. Open PowerShell
2. Run: `cd G:\smart-line-backup\smart-line.space\rateel`
3. Follow steps 2-10 above
4. Access: http://localhost:8000

**Good luck! 🚀**

