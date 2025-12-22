# VPS Deploy Steps (Critical + High Fixes)
Use these steps on your VPS to apply both the **critical** fixes (Redis queue/cache, workers) and the **high** fixes (FCM batching, response caching, category caching).

## 0) Pre-checks
- SSH to the project root (e.g., `/var/www/smartline`).
- Ensure Redis is installed and running: `redis-cli ping` → `PONG`.

## 1) Update Code & Dependencies
- `git pull`
- `composer install --optimize-autoloader --no-dev`

## 2) .env Required Settings (Critical)
- `QUEUE_CONNECTION=redis`
- `CACHE_DRIVER=redis`
- `REDIS_HOST=127.0.0.1`
- `REDIS_PORT=6379`
- Set `REDIS_PASSWORD` if required in production.
- (Optional) `REDIS_QUEUE=smartline`

## 3) Rebuild Laravel Caches
- `php artisan optimize:clear`
- `php artisan config:cache`
- `php artisan route:cache`
- `php artisan view:cache`

## 4) Queue Workers (Critical)
- If using Supervisor (recommended):
  - Copy/update config from `performance fixes after 22/supervisor-smartline-workers.conf` to `/etc/supervisor/conf.d/` with correct paths.
  - `sudo supervisorctl reread && sudo supervisorctl update`
  - `sudo supervisorctl restart smartline-worker:*`
- If running manually (not recommended for prod):
  - Stop any old workers.
  - `php artisan queue:work redis --queue=high,broadcasting,default --tries=3 --timeout=90 --sleep=3`

## 5) Warm Hot Endpoints (High)
- (Replace domain and headers as needed)
  - `curl -I https://your-domain/api/version`
  - `curl -I https://your-domain/api/config/location`
  - `curl -I https://your-domain/api/customer/vehicle/category` (include auth/zoneId headers)

## 6) Verify Queues & Cache
- Queue depth: `php artisan queue:monitor redis:high,redis:default --max=100`
- Failed jobs: `php artisan queue:failed`
- Redis ping: `redis-cli ping`

## 7) Logs to Watch
- Laravel: `tail -f storage/logs/laravel.log`
- Workers (Supervisor): `tail -f storage/logs/worker-high.log` and `worker-default.log`

## 8) What to Expect After Deploy
- Push notifications are batched (fewer FCM calls; retries with HTTP fallback).
- Repeated GETs (version/config/vehicle lists) come from cache after first hit.
- Category lists are cached with automatic invalidation on category/fare changes.
- Critical fixes: queues/caches on Redis, workers managed by Supervisor.
