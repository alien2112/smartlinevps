# 🚀 Route Caching Guide - SmartLine Backend

## Overview

Laravel route caching dramatically improves performance by serializing all route definitions into a single cached file. This is **essential for production** environments.

**Performance Impact:**
- Route registration: **~5-10ms → ~1ms** (5-10x faster)
- Memory usage: **Reduced**
- API response time: **Improved**

---

## ✅ Quick Commands

### Production (Enable Caching)
```bash
php artisan route:clear
php artisan route:cache
```

### Development (Disable Caching)
```bash
php artisan route:clear
```

### Validate Routes
```bash
php artisan route:list
```

---

## 🚫 What NOT to Do

### ❌ Never Use Closure Routes

**BAD - Will break route caching:**
```php
Route::get('/test', function () {
    return 'Hello';
});
```

**GOOD - Use controller methods:**
```php
Route::get('/test', [TestController::class, 'index']);
```

### ❌ Don't Edit Routes Without Clearing Cache

If you modify routes in production, you MUST run:
```bash
php artisan route:clear
php artisan route:cache
```

Otherwise, the old cached routes will still be used.

---

## 📁 Files Changed for Route Caching

The following closure routes were converted to controller methods:

### `routes/web.php`

| Original Closure | New Controller Method |
|------------------|----------------------|
| `Route::get('/sender', function() {...})` | `DemoController::sender()` |
| `Route::get('/test-connection', function() {...})` | `DemoController::testConnection()` |
| `Route::get('trigger', function() {...})` | `DemoController::trigger()` |
| `Route::get('test', function() {...})` | `DemoController::testNotification()` |

### `routes/api.php`

| Original Closure | New Controller Method |
|------------------|----------------------|
| `Route::get('/user', function($request) {...})` | `AppConfigController::user()` |
| `Route::get('/version', function() {...})` | `AppConfigController::version()` |
| `Route::get('/internal/settings', function() {...})` | `AppConfigController::internalSettings()` |

### Controllers Updated

- `app/Http/Controllers/DemoController.php` - Added: `sender()`, `testConnection()`, `trigger()`, `testNotification()`
- `app/Http/Controllers/Api/AppConfigController.php` - Added: `user()`, `version()`, `internalSettings()`

---

## 🛠️ Deployment Script

Use the automated deployment script:

```bash
chmod +x scripts/deploy-routes.sh
./scripts/deploy-routes.sh
```

This script:
1. ✅ Checks for closure routes (fails if any exist)
2. ✅ Clears existing caches
3. ✅ Validates all routes work
4. ✅ Builds route cache
5. ✅ Verifies cache file exists

---

## 🔍 CI/Pre-Deploy Safety Check

Run the closure detection script before deployment:

```bash
php scripts/check-route-closures.php
```

**Add to CI pipeline:**

```yaml
# GitHub Actions example
- name: Check Route Closures
  run: php scripts/check-route-closures.php
```

```yaml
# GitLab CI example
route_check:
  script:
    - php scripts/check-route-closures.php
  stage: test
```

---

## 📋 VPS Deployment Checklist

```bash
# 1. Pull latest code
git pull origin main

# 2. Install dependencies
composer install --no-dev --optimize-autoloader

# 3. Run migrations
php artisan migrate --force

# 4. Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear

# 5. Rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Restart queue workers
php artisan queue:restart

# 7. Restart PHP-FPM (if applicable)
sudo systemctl restart php8.2-fpm
```

---

## 🔙 Rollback Procedure

If routes are broken after caching:

```bash
# 1. Clear the route cache immediately
php artisan route:clear

# 2. Check routes work without cache
php artisan route:list

# 3. Fix any issues in route files

# 4. Re-enable caching when ready
php artisan route:cache
```

---

## 🧪 Testing Routes

### List all routes:
```bash
php artisan route:list
```

### Filter routes:
```bash
php artisan route:list --path=api
php artisan route:list --name=admin
```

### JSON output (for CI):
```bash
php artisan route:list --json
```

---

## ⚙️ Configuration

### Environment Variables

```env
# Optional: Set in .env for different cache behavior
ROUTE_CACHE=true
```

### Config Values (config/app.php)

```php
// Already configured
'max_heatmap_points' => env('MAX_HEATMAP_POINTS', 5000),
'max_heatmap_points_per_period' => env('MAX_HEATMAP_POINTS_PER_PERIOD', 1000),
```

---

## 🔗 Related Documentation

- [Laravel Route Caching](https://laravel.com/docs/10.x/routing#route-caching)
- [Deployment Optimization](https://laravel.com/docs/10.x/deployment#optimization)

---

## 📝 Change Log

| Date | Change |
|------|--------|
| 2025-12-29 | Converted 7 closure routes to controller methods |
| 2025-12-29 | Added deployment script and CI checks |
| 2025-12-29 | Created this documentation |

---

*Last updated: December 29, 2025*
