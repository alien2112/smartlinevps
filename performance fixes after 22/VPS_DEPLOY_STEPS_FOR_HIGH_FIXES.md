# How to Deploy the High-Priority Fixes to Your VPS

Use these steps after pulling the latest code that includes the FCM batching, response caching middleware, and category caching changes.

## 1) Pull Code and Install Dependencies
- SSH to the VPS and go to your project directory (e.g., `/var/www/smartline`).
- Pull updates: `git pull`
- Install production deps: `composer install --optimize-autoloader --no-dev`

## 2) Update and Warm Laravel Caches
- Clear old caches: `php artisan optimize:clear`
- Rebuild config/routes/views: `php artisan config:cache && php artisan route:cache && php artisan view:cache`

## 3) Ensure Queue Is Asynchronous
- `.env` should have `QUEUE_CONNECTION=redis` and `CACHE_DRIVER=redis`.
- Restart workers (Supervisor): `sudo supervisorctl restart smartline-worker:*`
  - If running manually: stop old worker, then `php artisan queue:work redis --queue=high,broadcasting,default --tries=3 --timeout=90 --sleep=3`

## 4) Verify Redis/Queues
- `redis-cli ping` should return `PONG`.
- Check queue depth: `php artisan queue:monitor redis:high,redis:default --max=100`

## 5) Optional: Warm Hot Endpoints
- Hit once to warm caches (adjust domain as needed):
  - `curl -I https://your-domain/api/version`
  - `curl -I https://your-domain/api/config/location`
  - `curl -I https://your-domain/api/customer/vehicle/category` (with auth header/zoneId as your app requires)

## 6) Logs to Watch
- Laravel: `tail -f storage/logs/laravel.log`
- Queue workers (if Supervisor): `tail -f storage/logs/worker-high.log` and `worker-default.log`

## 7) What to Expect
- Push notification jobs send in batches (fewer FCM calls).
- Repeated GETs for version/config/vehicle metadata should be cache hits (sub-ms after first request).
- Category list updates stay fresh because cache version bumps on category/fare save/delete.
