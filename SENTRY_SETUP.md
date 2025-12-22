# Sentry Error Monitoring Setup Instructions

## Prerequisites

Before installing Sentry, ensure these PHP extensions are installed:

```bash
# Enable in php.ini:
extension=gd
extension=sodium
```

## Installation Steps

1. **Install Sentry Package**
   ```bash
   composer require sentry/sentry-laravel
   ```

2. **Publish Configuration**
   ```bash
   php artisan vendor:publish --provider="Sentry\Laravel\ServiceProvider"
   ```

3. **Configure Environment Variables**

   Add to your `.env` file:
   ```env
   SENTRY_LARAVEL_DSN=https://your-dsn@sentry.io/project-id
   SENTRY_TRACES_SAMPLE_RATE=0.2
   SENTRY_PROFILES_SAMPLE_RATE=0.2
   ```

4. **Get Your DSN**
   - Sign up at https://sentry.io
   - Create a new project
   - Copy the DSN from project settings

5. **Test Configuration**
   ```bash
   php artisan sentry:test
   ```

## Configuration Options

Edit `config/sentry.php` for advanced options:

```php
'dsn' => env('SENTRY_LARAVEL_DSN'),

'environment' => env('APP_ENV', 'production'),

'sample_rate' => (float) env('SENTRY_SAMPLE_RATE', 1.0),

'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.0),

'send_default_pii' => false, // Don't send user IP, cookies, etc.

'breadcrumbs' => [
    'logs' => true,
    'sql_queries' => true,
    'sql_bindings' => true,
    'queue_info' => true,
    'command_info' => true,
],
```

## Benefits

Once installed, Sentry will automatically:
- Capture all exceptions and errors
- Track performance issues
- Provide stack traces and context
- Alert you when errors occur
- Show error trends and patterns

## Cost

- Free tier: 5,000 events/month
- Paid plans start at $26/month

## Alternative: Manual Installation

If you cannot install Sentry now, error logging is already configured in:
- `app/Exceptions/Handler.php` - Logs all exceptions
- `app/Services/LogService.php` - Structured logging
- Multiple log channels in `config/logging.php`

You can monitor errors by checking:
```bash
tail -f storage/logs/laravel.log
```
