# PRODUCTION-GRADE CENTRALIZED LOGGING SYSTEM
## SmartLine Ride-Hailing Platform - Multi-VPS Deployment

**Date:** December 16, 2025
**Purpose:** Replace basic file logging with enterprise-grade centralized logging for 1M+ users

---

## 📋 TABLE OF CONTENTS

1. [System Overview](#system-overview)
2. [Architecture](#architecture)
3. [Step-by-Step Implementation](#implementation)
4. [Configuration](#configuration)
5. [Usage Guide](#usage-guide)
6. [Monitoring & Alerts](#monitoring)
7. [Troubleshooting](#troubleshooting)

---

## 1. SYSTEM OVERVIEW

### Current vs. New System

| Feature | Current (Basic) | New (Production) |
|---------|----------------|------------------|
| **Format** | Plain text | Structured JSON |
| **Storage** | Local files | Centralized server |
| **Search** | grep/tail | Full-text search with filters |
| **Correlation** | ❌ None | ✅ Request IDs across all services |
| **Retention** | 14 days | 30-180 days by type |
| **Performance** | File I/O bottleneck | Async shipping, no perf impact |
| **Multi-VPS** | ❌ Scattered | ✅ Unified view |
| **Alerts** | ❌ None | ✅ Real-time alerts |
| **Cost** | Free | ~$50-200/month |

### What This Solves

✅ **Debug production incidents** across multiple VPS
✅ **Trace user journeys** end-to-end (trip request → matching → completion)
✅ **Root-cause failures** (payment, matching, geolocation)
✅ **Security monitoring** (failed logins, suspicious patterns)
✅ **Compliance** (payment audit trails, data access logs)
✅ **Performance analysis** (slow queries, API latency)

---

## 2. ARCHITECTURE

### Recommended Stack (Cost-Effective)

```
┌─────────────────────────────────────────────────────────┐
│                    Application Layer                     │
│  (Laravel + Custom Logging Middleware)                   │
└────────────┬────────────────────────────────────────────┘
             │
             │ Structured JSON logs
             ▼
┌─────────────────────────────────────────────────────────┐
│         Log Files (JSON format, buffered)                │
│  /var/log/smartline/{api,websocket,worker,security}.log │
└────────────┬────────────────────────────────────────────┘
             │
             │ Vector/Fluent Bit (Log Shipper)
             ▼
┌─────────────────────────────────────────────────────────┐
│              Central Log Server (Standalone VPS)         │
│                                                          │
│  ┌────────────────┐        ┌──────────────────┐        │
│  │  OpenSearch    │───────▶│  Dashboards      │        │
│  │  (Logs storage)│        │  (Kibana-like)   │        │
│  └────────────────┘        └──────────────────┘        │
│                                                          │
│  ┌────────────────────────────────────────────┐        │
│  │  Alerting (ElastAlert 2)                   │        │
│  │  - Slack notifications                     │        │
│  │  - Email alerts                            │        │
│  └────────────────────────────────────────────┘        │
└─────────────────────────────────────────────────────────┘
```

### Component Choices

**Option A: Self-Hosted (Best for Cost)**

| Component | Technology | Why |
|-----------|-----------|-----|
| Log Shipping | **Vector** | Lightweight, fast, Rust-based |
| Storage | **OpenSearch** | Elasticsearch fork, free & open |
| Visualization | **OpenSearch Dashboards** | Kibana alternative, built-in |
| Alerting | **ElastAlert 2** | Rule-based alerts |

**Monthly Cost:** ~$50-100 (1x 4GB VPS for OpenSearch)

**Option B: Managed SaaS (Easier Setup)**

| Service | Monthly Cost | Pros | Cons |
|---------|--------------|------|------|
| **Better Stack** | $25-100 | Easy, great UI | $$$$ at scale |
| **Logtail** | $50-200 | Laravel integration | Expensive |
| **Datadog** | $100+ | Full observability | Very expensive |

**Recommendation:** Start with **self-hosted OpenSearch** for cost control. Switch to SaaS if team lacks DevOps skills.

---

## 3. STEP-BY-STEP IMPLEMENTATION

### Phase 1: Update Laravel Logging (Week 1)

#### Step 1.1: Update `config/logging.php`

Create specialized log channels with JSON formatting:

```php
<?php

use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Formatter\JsonFormatter;

return [
    'default' => env('LOG_CHANNEL', 'stack'),

    'channels' => [
        // Production stack (JSON formatted)
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily_json', 'stderr'],
            'ignore_exceptions' => false,
        ],

        // Primary application logs (JSON)
        'daily_json' => [
            'driver' => 'daily',
            'path' => storage_path('logs/api.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 7,  // Keep local for 7 days (shipped to central)
            'formatter' => JsonFormatter::class,
        ],

        // Security events (separate file, longer retention)
        'security' => [
            'driver' => 'daily',
            'path' => storage_path('logs/security.log'),
            'level' => 'info',
            'days' => 90,  // Keep longer for audit
            'formatter' => JsonFormatter::class,
        ],

        // Financial transactions (compliance)
        'finance' => [
            'driver' => 'daily',
            'path' => storage_path('logs/finance.log'),
            'level' => 'info',
            'days' => 365,  // 1 year retention
            'formatter' => JsonFormatter::class,
        ],

        // WebSocket events
        'websocket' => [
            'driver' => 'daily',
            'path' => storage_path('logs/websocket.log'),
            'level' => 'info',
            'days' => 7,
            'formatter' => JsonFormatter::class,
        ],

        // Queue workers
        'queue' => [
            'driver' => 'daily',
            'path' => storage_path('logs/queue.log'),
            'level' => 'info',
            'days' => 7,
            'formatter' => JsonFormatter::class,
        ],

        // Slack for critical errors (real-time)
        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'SmartLine Alert',
            'emoji' => ':fire:',
            'level' => 'critical',
        ],

        // Stderr for Docker/systemd
        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'info'),
            'handler' => StreamHandler::class,
            'formatter' => JsonFormatter::class,
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],
    ],
];
```

#### Step 1.2: Create Request Correlation Middleware

Create `app/Http/Middleware/LogContext.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogContext
{
    /**
     * Add correlation ID and context to all logs
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Generate or use existing correlation ID
        $correlationId = $request->header('X-Correlation-ID') ?? (string) Str::uuid();

        // Add to request for propagation
        $request->headers->set('X-Correlation-ID', $correlationId);

        // Set log context for ALL subsequent logs in this request
        Log::withContext([
            'correlation_id' => $correlationId,
            'request_id' => $correlationId,  // Alias for compatibility
            'vps_id' => gethostname(),  // Which server handled this
            'environment' => app()->environment(),
            'user_id' => auth()->id(),
            'user_type' => auth()->user()?->user_type,
            'ip' => $request->ip(),
            'method' => $request->method(),
            'uri' => $request->getRequestUri(),
            'user_agent' => $request->userAgent(),
        ]);

        $startTime = microtime(true);

        $response = $next($request);

        // Log request completion
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('request_completed', [
            'status' => $response->status(),
            'duration_ms' => $duration,
            'memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ]);

        // Add correlation ID to response headers
        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }
}
```

#### Step 1.3: Register Middleware

Update `app/Http/Kernel.php`:

```php
protected $middleware = [
    // ... existing middleware
    \App\Http\Middleware\LogContext::class,  // Add this
];
```

#### Step 1.4: Create Logging Service Helper

Create `app/Services/LogService.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class LogService
{
    /**
     * Log trip lifecycle events
     */
    public static function tripEvent(string $event, $trip, array $extra = []): void
    {
        Log::channel('daily_json')->info('trip_event', array_merge([
            'event' => $event,
            'trip_id' => $trip->id,
            'customer_id' => $trip->customer_id,
            'driver_id' => $trip->driver_id,
            'zone_id' => $trip->zone_id,
            'status' => $trip->current_status,
            'fare' => $trip->estimated_fare,
        ], $extra));
    }

    /**
     * Log authentication events
     */
    public static function authEvent(string $event, $user = null, array $extra = []): void
    {
        Log::channel('security')->info('auth_event', array_merge([
            'event' => $event,
            'user_id' => $user?->id,
            'user_type' => $user?->user_type,
            'ip' => request()->ip(),
        ], $extra));
    }

    /**
     * Log payment events
     */
    public static function paymentEvent(string $event, $trip, array $extra = []): void
    {
        Log::channel('finance')->info('payment_event', array_merge([
            'event' => $event,
            'trip_id' => $trip->id,
            'customer_id' => $trip->customer_id,
            'amount' => $trip->actual_fare ?? $trip->estimated_fare,
            'payment_method' => $trip->payment_method,
            'payment_status' => $trip->payment_status,
        ], $extra));
    }

    /**
     * Log driver actions
     */
    public static function driverAction(string $action, $driver, $trip = null, array $extra = []): void
    {
        Log::info('driver_action', array_merge([
            'action' => $action,
            'driver_id' => $driver->id,
            'trip_id' => $trip?->id,
            'availability' => $driver->driverDetails?->availability_status,
        ], $extra));
    }

    /**
     * Log external API calls
     */
    public static function externalApiCall(string $service, string $endpoint, int $statusCode, float $duration, array $extra = []): void
    {
        $level = $statusCode >= 500 ? 'error' : ($statusCode >= 400 ? 'warning' : 'info');

        Log::$level('external_api_call', array_merge([
            'service' => $service,
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'duration_ms' => round($duration * 1000, 2),
            'success' => $statusCode < 400,
        ], $extra));
    }

    /**
     * Log WebSocket events
     */
    public static function websocketEvent(string $event, array $data = []): void
    {
        Log::channel('websocket')->info('websocket_event', array_merge([
            'event' => $event,
        ], $data));
    }

    /**
     * Log security events
     */
    public static function securityEvent(string $event, string $severity, array $data = []): void
    {
        $level = match($severity) {
            'critical' => 'critical',
            'high' => 'error',
            'medium' => 'warning',
            default => 'info',
        };

        Log::channel('security')->$level('security_event', array_merge([
            'event' => $event,
            'severity' => $severity,
            'ip' => request()->ip(),
        ], $data));
    }
}
```

#### Step 1.5: Update `.env`

```env
# Logging Configuration
LOG_CHANNEL=stack
LOG_LEVEL=info  # debug, info, warning, error, critical

# Slack Alerts (optional)
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
```

---

### Phase 2: Add Logging to Critical Code Paths (Week 1)

#### Step 2.1: Trip Request Controller

Update `Modules/TripManagement/Http/Controllers/Api/New/Customer/TripRequestController.php`:

```php
use App\Services\LogService;

public function createRideRequest(Request $request)
{
    // Existing validation...

    DB::beginTransaction();
    try {
        // Create trip
        $trip = $this->tripRequestService->create($request->all());

        LogService::tripEvent('trip_created', $trip, [
            'pickup_coordinates' => $request->pickup_coordinates,
            'destination_coordinates' => $request->destination_coordinates,
            'vehicle_category' => $request->vehicle_category_id,
        ]);

        DB::commit();

        return response()->json(['data' => $trip]);
    } catch (\Exception $e) {
        DB::rollBack();

        Log::error('trip_creation_failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'request_data' => $request->except(['password', 'token']),
        ]);

        throw $e;
    }
}
```

#### Step 2.2: Driver Trip Acceptance

Update `Modules/TripManagement/Http/Controllers/Api/Driver/TripRequestController.php`:

```php
use App\Services\LogService;

public function requestAction(Request $request)
{
    $action = $request->action;  // 'accepted' or 'rejected'
    $trip = TripRequest::find($request->trip_request_id);
    $driver = auth()->user();

    LogService::driverAction("trip_{$action}", $driver, $trip, [
        'action_time_ms' => round((microtime(true) - LARAVEL_START) * 1000, 2),
    ]);

    // Existing logic...

    if ($action === 'accepted') {
        LogService::tripEvent('trip_accepted', $trip, [
            'driver_id' => $driver->id,
            'driver_arrival_time' => $trip->driver_arrival_time,
        ]);
    }

    return response()->json(['message' => 'Trip ' . $action]);
}
```

#### Step 2.3: Authentication Events

Update `Modules/AuthManagement/Http/Controllers/Api/New/AuthController.php`:

```php
use App\Services\LogService;

public function login(Request $request)
{
    $credentials = $request->only(['phone', 'password']);

    if (Auth::attempt($credentials)) {
        $user = Auth::user();

        LogService::authEvent('login_success', $user, [
            'login_method' => 'phone',
        ]);

        return response()->json([
            'token' => $user->createToken('auth')->plainTextToken,
        ]);
    }

    LogService::authEvent('login_failed', null, [
        'phone' => $request->phone,
        'reason' => 'invalid_credentials',
    ]);

    return response()->json(['error' => 'Invalid credentials'], 401);
}

public function logout(Request $request)
{
    $user = auth()->user();

    LogService::authEvent('logout', $user);

    $request->user()->currentAccessToken()->delete();

    return response()->json(['message' => 'Logged out']);
}
```

#### Step 2.4: Payment Events

Add to payment processing code:

```php
use App\Services\LogService;

// When payment initiated
LogService::paymentEvent('payment_initiated', $trip, [
    'gateway' => 'stripe',
    'amount' => $amount,
]);

// When payment succeeds
LogService::paymentEvent('payment_succeeded', $trip, [
    'transaction_id' => $paymentIntent->id,
    'amount_charged' => $paymentIntent->amount / 100,
]);

// When payment fails
LogService::paymentEvent('payment_failed', $trip, [
    'gateway' => 'stripe',
    'error_code' => $exception->getCode(),
    'error_message' => $exception->getMessage(),
]);
```

#### Step 2.5: External API Calls (GeoLink)

Update `Modules/TripManagement/Service/GeoLinkService.php`:

```php
use App\Services\LogService;

public function getRoutes(...)
{
    $startTime = microtime(true);

    try {
        $response = Http::timeout(10)->get($url, $params);
        $duration = microtime(true) - $startTime;

        LogService::externalApiCall(
            'geolink',
            '/route',
            $response->status(),
            $duration,
            ['waypoints_count' => count($intermediateCoordinates)]
        );

        if ($response->successful()) {
            return $response->json();
        }

        Log::warning('geolink_api_error', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return null;
    } catch (\Exception $e) {
        $duration = microtime(true) - $startTime;

        LogService::externalApiCall(
            'geolink',
            '/route',
            0,
            $duration,
            ['error' => $e->getMessage()]
        );

        Log::error('geolink_api_exception', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return null;
    }
}
```

#### Step 2.6: WebSocket Events

Update `app/WebSockets/Handler/UserLocationSocketHandler.php`:

```php
use App\Services\LogService;

public function onOpen(ConnectionInterface $conn)
{
    LogService::websocketEvent('connection_opened', [
        'connection_id' => $conn->resourceId,
        'ip' => $conn->remoteAddress,
    ]);

    $this->clients->attach($conn);
}

public function onClose(ConnectionInterface $conn)
{
    $userId = $this->getUserId($conn);

    LogService::websocketEvent('connection_closed', [
        'connection_id' => $conn->resourceId,
        'user_id' => $userId,
        'duration_seconds' => time() - ($this->connectionTimes[$conn->resourceId] ?? time()),
    ]);

    $this->clients->detach($conn);

    // Mark driver offline
    if ($userId) {
        DB::table('driver_details')
            ->where('user_id', $userId)
            ->update(['is_online' => false]);

        LogService::driverAction('went_offline', User::find($userId));
    }
}

public function onError(ConnectionInterface $conn, \Exception $e)
{
    LogService::websocketEvent('connection_error', [
        'connection_id' => $conn->resourceId,
        'error' => $e->getMessage(),
    ]);

    $conn->close();
}
```

#### Step 2.7: Queue Job Events

Update `app/Jobs/SendPushNotificationJob.php`:

```php
use Illuminate\Support\Facades\Log;

public function handle()
{
    Log::channel('queue')->info('job_started', [
        'job' => 'SendPushNotificationJob',
        'notification_count' => count($this->data),
    ]);

    try {
        // Send notifications...

        Log::channel('queue')->info('job_completed', [
            'job' => 'SendPushNotificationJob',
            'sent_count' => $sentCount,
        ]);
    } catch (\Exception $e) {
        Log::channel('queue')->error('job_failed', [
            'job' => 'SendPushNotificationJob',
            'error' => $e->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        throw $e;
    }
}

public function failed(\Throwable $exception)
{
    Log::channel('queue')->critical('job_failed_permanently', [
        'job' => 'SendPushNotificationJob',
        'error' => $exception->getMessage(),
        'max_attempts' => $this->tries,
    ]);
}
```

---

### Phase 3: Install Log Shipping (Week 2)

#### Step 3.1: Install Vector on Each VPS

**On each application server (Ubuntu/Debian):**

```bash
# Download Vector
curl --proto '=https' --tlsv1.2 -sSf https://sh.vector.dev | bash -s -- -y

# Or using package manager
wget -O- https://repositories.timber.io/public/vector/gpg.3543DB2D0A2BC4B8.key | gpg --dearmor | sudo tee /etc/apt/keyrings/vector.gpg
echo "deb [signed-by=/etc/apt/keyrings/vector.gpg] https://repositories.timber.io/public/vector/deb/debian $(lsb_release -cs) main" | sudo tee /etc/apt/sources.list.d/vector.list
sudo apt update
sudo apt install vector
```

#### Step 3.2: Configure Vector

Create `/etc/vector/vector.toml`:

```toml
# Vector configuration for SmartLine logging

# Data directory
data_dir = "/var/lib/vector"

# Source: Read application logs
[sources.api_logs]
type = "file"
include = ["/var/www/smartline/storage/logs/api-*.log"]
read_from = "beginning"
fingerprint.strategy = "device_and_inode"

[sources.security_logs]
type = "file"
include = ["/var/www/smartline/storage/logs/security-*.log"]
read_from = "beginning"

[sources.finance_logs]
type = "file"
include = ["/var/www/smartline/storage/logs/finance-*.log"]
read_from = "beginning"

[sources.websocket_logs]
type = "file"
include = ["/var/www/smartline/storage/logs/websocket-*.log"]
read_from = "beginning"

[sources.queue_logs]
type = "file"
include = ["/var/www/smartline/storage/logs/queue-*.log"]
read_from = "beginning"

# Transform: Parse JSON logs
[transforms.parse_json_api]
type = "remap"
inputs = ["api_logs"]
source = '''
. = parse_json!(.message)
.source_vps = "${HOSTNAME}"
.log_type = "api"
'''

[transforms.parse_json_security]
type = "remap"
inputs = ["security_logs"]
source = '''
. = parse_json!(.message)
.source_vps = "${HOSTNAME}"
.log_type = "security"
'''

[transforms.parse_json_finance]
type = "remap"
inputs = ["finance_logs"]
source = '''
. = parse_json!(.message)
.source_vps = "${HOSTNAME}"
.log_type = "finance"
'''

[transforms.parse_json_websocket]
type = "remap"
inputs = ["websocket_logs"]
source = '''
. = parse_json!(.message)
.source_vps = "${HOSTNAME}"
.log_type = "websocket"
'''

[transforms.parse_json_queue]
type = "remap"
inputs = ["queue_logs"]
source = '''
. = parse_json!(.message)
.source_vps = "${HOSTNAME}"
.log_type = "queue"
'''

# Sink: Send to central OpenSearch
[sinks.opensearch]
type = "elasticsearch"
inputs = [
  "parse_json_api",
  "parse_json_security",
  "parse_json_finance",
  "parse_json_websocket",
  "parse_json_queue"
]
endpoints = ["https://logs.smartline.internal:9200"]  # Your central log server
mode = "bulk"
bulk.index = "smartline-logs-%Y.%m.%d"
bulk.action = "create"

# Authentication (if using security)
auth.strategy = "basic"
auth.user = "vector"
auth.password = "${OPENSEARCH_PASSWORD}"

# TLS (if using HTTPS)
tls.verify_certificate = true
tls.ca_file = "/etc/vector/ca.crt"

# Buffering (handles network issues)
[sinks.opensearch.buffer]
type = "disk"
max_size = 1073741824  # 1 GB buffer
when_full = "block"

# Health check
[sinks.opensearch.healthcheck]
enabled = true
```

#### Step 3.3: Start Vector

```bash
# Enable and start Vector
sudo systemctl enable vector
sudo systemctl start vector

# Check status
sudo systemctl status vector

# View Vector logs
sudo journalctl -u vector -f
```

---

### Phase 4: Setup Central Log Server (Week 2)

#### Step 4.1: Provision Log Server VPS

**Requirements:**
- **CPU:** 4 vCPU
- **RAM:** 8 GB minimum (16 GB recommended)
- **Disk:** 200 GB SSD (grows with retention)
- **OS:** Ubuntu 22.04 LTS

**Monthly Cost:** ~$50-80 (DigitalOcean, Vultr, Hetzner)

#### Step 4.2: Install OpenSearch

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Java (required for OpenSearch)
sudo apt install -y openjdk-11-jdk

# Download OpenSearch
wget https://artifacts.opensearch.org/releases/bundle/opensearch/2.11.1/opensearch-2.11.1-linux-x64.tar.gz
tar -xzf opensearch-2.11.1-linux-x64.tar.gz
sudo mv opensearch-2.11.1 /opt/opensearch

# Create opensearch user
sudo useradd -M -r -s /bin/bash opensearch
sudo chown -R opensearch:opensearch /opt/opensearch

# Configure OpenSearch
sudo nano /opt/opensearch/config/opensearch.yml
```

**OpenSearch Configuration** (`/opt/opensearch/config/opensearch.yml`):

```yaml
cluster.name: smartline-logs
node.name: log-server-01

# Network
network.host: 0.0.0.0
http.port: 9200

# Paths
path.data: /opt/opensearch/data
path.logs: /opt/opensearch/logs

# Discovery (single node for now)
discovery.type: single-node

# Security (disable for simplicity, enable in production)
plugins.security.disabled: true

# Performance
bootstrap.memory_lock: true

# Index settings
action.auto_create_index: true
```

**Set JVM heap size** (`/opt/opensearch/config/jvm.options`):

```
# Set to 50% of available RAM (4GB if 8GB total)
-Xms4g
-Xmx4g
```

#### Step 4.3: Create systemd Service

Create `/etc/systemd/system/opensearch.service`:

```ini
[Unit]
Description=OpenSearch
Documentation=https://opensearch.org/
Wants=network-online.target
After=network-online.target

[Service]
Type=notify
RuntimeDirectory=opensearch
PrivateTmp=true
Environment=OPENSEARCH_HOME=/opt/opensearch
Environment=OPENSEARCH_PATH_CONF=/opt/opensearch/config
WorkingDirectory=/opt/opensearch
User=opensearch
Group=opensearch

ExecStart=/opt/opensearch/bin/opensearch

# Restart policy
Restart=on-failure
RestartSec=10s

# Limits
LimitNOFILE=65536
LimitNPROC=4096
LimitMEMLOCK=infinity

[Install]
WantedBy=multi-user.target
```

**Start OpenSearch:**

```bash
sudo systemctl daemon-reload
sudo systemctl enable opensearch
sudo systemctl start opensearch

# Check status
sudo systemctl status opensearch

# Verify it's running
curl http://localhost:9200
```

#### Step 4.4: Install OpenSearch Dashboards

```bash
# Download Dashboards
wget https://artifacts.opensearch.org/releases/bundle/opensearch-dashboards/2.11.1/opensearch-dashboards-2.11.1-linux-x64.tar.gz
tar -xzf opensearch-dashboards-2.11.1-linux-x64.tar.gz
sudo mv opensearch-dashboards-2.11.1 /opt/opensearch-dashboards
sudo chown -R opensearch:opensearch /opt/opensearch-dashboards

# Configure
sudo nano /opt/opensearch-dashboards/config/opensearch_dashboards.yml
```

**Dashboards Configuration:**

```yaml
server.port: 5601
server.host: "0.0.0.0"
opensearch.hosts: ["http://localhost:9200"]

# Security (disable for simplicity)
opensearch.username: ""
opensearch.password: ""
opensearch_security.multitenancy.enabled: false
opensearch_security.readonly_mode.roles: []
```

**Create systemd service** (`/etc/systemd/system/opensearch-dashboards.service`):

```ini
[Unit]
Description=OpenSearch Dashboards
After=network.target opensearch.service

[Service]
Type=simple
User=opensearch
Group=opensearch
WorkingDirectory=/opt/opensearch-dashboards
ExecStart=/opt/opensearch-dashboards/bin/opensearch-dashboards
Restart=on-failure
RestartSec=10s

[Install]
WantedBy=multi-user.target
```

**Start Dashboards:**

```bash
sudo systemctl daemon-reload
sudo systemctl enable opensearch-dashboards
sudo systemctl start opensearch-dashboards

# Access at: http://YOUR_LOG_SERVER_IP:5601
```

#### Step 4.5: Setup Nginx Reverse Proxy (Optional but Recommended)

```bash
sudo apt install -y nginx certbot python3-certbot-nginx

# Create Nginx config
sudo nano /etc/nginx/sites-available/logs
```

**Nginx Configuration:**

```nginx
server {
    listen 80;
    server_name logs.smartline.com;  # Your domain

    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name logs.smartline.com;

    ssl_certificate /etc/letsencrypt/live/logs.smartline.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/logs.smartline.com/privkey.pem;

    # OpenSearch Dashboards
    location / {
        proxy_pass http://localhost:5601;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }

    # OpenSearch API
    location /api/ {
        proxy_pass http://localhost:9200/;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
    }
}
```

**Enable and get SSL:**

```bash
sudo ln -s /etc/nginx/sites-available/logs /etc/nginx/sites-enabled/
sudo nginx -t
sudo certbot --nginx -d logs.smartline.com
sudo systemctl reload nginx
```

---

### Phase 5: Setup Alerting (Week 3)

#### Step 5.1: Install ElastAlert 2

```bash
# Install dependencies
sudo apt install -y python3-pip python3-venv

# Create elastalert user
sudo useradd -M -r -s /bin/bash elastalert

# Install ElastAlert 2
sudo mkdir /opt/elastalert
sudo chown elastalert:elastalert /opt/elastalert
cd /opt/elastalert
sudo -u elastalert python3 -m venv venv
sudo -u elastalert venv/bin/pip install elastalert2

# Create directories
sudo -u elastalert mkdir -p /opt/elastalert/rules
sudo -u elastalert mkdir -p /opt/elastalert/logs
```

#### Step 5.2: Configure ElastAlert

Create `/opt/elastalert/config.yaml`:

```yaml
# ElastAlert configuration
rules_folder: /opt/elastalert/rules
run_every:
  minutes: 1

buffer_time:
  minutes: 15

es_host: localhost
es_port: 9200

# Index to query
writeback_index: elastalert_status
alert_time_limit:
  days: 2
```

#### Step 5.3: Create Alert Rules

**High Error Rate Alert** (`/opt/elastalert/rules/high_error_rate.yaml`):

```yaml
name: High Error Rate
type: frequency
index: smartline-logs-*
num_events: 50
timeframe:
  minutes: 5

filter:
- term:
    level: "error"

alert:
- slack
slack_webhook_url: "https://hooks.slack.com/services/YOUR/WEBHOOK"
slack_username_override: "SmartLine Alerts"
slack_emoji_override: ":fire:"
slack_msg_color: "danger"

alert_text: |
  **High Error Rate Detected**

  Over 50 errors in the last 5 minutes.

  Check logs: https://logs.smartline.com
```

**Payment Failure Alert** (`/opt/elastalert/rules/payment_failures.yaml`):

```yaml
name: Payment Failures
type: frequency
index: smartline-logs-*
num_events: 10
timeframe:
  minutes: 10

filter:
- term:
    event: "payment_failed"

alert:
- slack
- email

# Slack
slack_webhook_url: "https://hooks.slack.com/services/YOUR/WEBHOOK"

# Email
email:
- finance@smartline.com
smtp_host: smtp.gmail.com
smtp_port: 587
smtp_ssl: false
from_addr: alerts@smartline.com
email_reply_to: noreply@smartline.com

alert_text: |
  **Payment Failures Detected**

  {num_hits} payments failed in the last 10 minutes.

  Investigate immediately.
```

**Driver Availability Low** (`/opt/elastalert/rules/low_driver_availability.yaml`):

```yaml
name: Low Driver Availability
type: flatline
index: smartline-logs-*
threshold: 5
timeframe:
  minutes: 30

filter:
- term:
    event: "driver_went_online"

alert:
- slack

slack_webhook_url: "https://hooks.slack.com/services/YOUR/WEBHOOK"

alert_text: |
  **Low Driver Activity**

  Less than 5 drivers went online in the last 30 minutes.

  Check driver app status.
```

**Security: Failed Login Attempts** (`/opt/elastalert/rules/failed_logins.yaml`):

```yaml
name: Multiple Failed Login Attempts
type: frequency
index: smartline-logs-*
num_events: 5
timeframe:
  minutes: 5

filter:
- term:
    event: "login_failed"
- query:
    query_string:
      query: "ip:*"

aggregation:
  key: 'ip'

alert:
- slack

slack_webhook_url: "https://hooks.slack.com/services/YOUR/WEBHOOK"
slack_emoji_override: ":rotating_light:"

alert_text: |
  **Brute Force Attack Detected**

  IP {ip} failed login {num_hits} times in 5 minutes.

  Consider blocking this IP.
```

#### Step 5.4: Create systemd Service for ElastAlert

Create `/etc/systemd/system/elastalert.service`:

```ini
[Unit]
Description=ElastAlert 2
After=network.target opensearch.service

[Service]
Type=simple
User=elastalert
Group=elastalert
WorkingDirectory=/opt/elastalert
ExecStart=/opt/elastalert/venv/bin/elastalert --config /opt/elastalert/config.yaml --verbose
Restart=on-failure
RestartSec=10s

[Install]
WantedBy=multi-user.target
```

**Start ElastAlert:**

```bash
sudo systemctl daemon-reload
sudo systemctl enable elastalert
sudo systemctl start elastalert
sudo systemctl status elastalert
```

---

## 4. CONFIGURATION

### Environment Variables

Add to `.env` on all application servers:

```env
# Logging
LOG_CHANNEL=stack
LOG_LEVEL=info  # production: info, staging: debug

# Slack Alerts
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL

# Vector (if using auth)
OPENSEARCH_PASSWORD=your-secure-password
```

### Log Retention Policy

Configure in OpenSearch index lifecycle management:

```bash
# Create retention policy
curl -X PUT "localhost:9200/_plugins/_ism/policies/smartline_retention" -H 'Content-Type: application/json' -d'
{
  "policy": {
    "description": "SmartLine log retention policy",
    "default_state": "hot",
    "states": [
      {
        "name": "hot",
        "actions": [],
        "transitions": [
          {
            "state_name": "warm",
            "conditions": {
              "min_index_age": "7d"
            }
          }
        ]
      },
      {
        "name": "warm",
        "actions": [
          {
            "replica_count": {
              "number_of_replicas": 0
            }
          }
        ],
        "transitions": [
          {
            "state_name": "delete",
            "conditions": {
              "min_index_age": "30d"
            }
          }
        ]
      },
      {
        "name": "delete",
        "actions": [
          {
            "delete": {}
          }
        ]
      }
    ]
  }
}
'
```

**Retention by Log Type:**

| Log Type | Retention | Reason |
|----------|-----------|--------|
| API logs | 30 days | Debugging recent issues |
| Security logs | 90 days | Security audit |
| Finance logs | 180 days | Compliance |
| WebSocket logs | 14 days | Volume optimization |
| Queue logs | 14 days | Volume optimization |

---

## 5. USAGE GUIDE

### Searching Logs in OpenSearch Dashboards

1. **Access Dashboards:** `https://logs.smartline.com`

2. **Create Index Pattern:**
   - Go to: Management → Stack Management → Index Patterns
   - Pattern: `smartline-logs-*`
   - Time field: `@timestamp`
   - Click "Create"

3. **Search Examples:**

**Find all logs for a specific trip:**
```
trip_id:"t_55219"
```

**Find payment failures:**
```
event:"payment_failed" AND log_type:"finance"
```

**Find logs from specific VPS:**
```
source_vps:"api-03"
```

**Find slow API requests (>2s):**
```
event:"request_completed" AND duration_ms:>2000
```

**Trace a request across all services:**
```
correlation_id:"req_92f8a"
```

**Find errors in the last hour:**
```
level:"error" AND @timestamp:[now-1h TO now]
```

### Creating Dashboards

**Example: API Performance Dashboard**

1. Go to: Dashboards → Create Dashboard
2. Add visualizations:
   - **Request Rate:** Line chart of `event:request_completed` over time
   - **Error Rate:** Line chart of `level:error` over time
   - **Latency (p95):** Percentile chart of `duration_ms`
   - **Top Endpoints:** Pie chart of `uri` field
   - **Geographic Distribution:** Map of `ip` field

**Example: Trip Monitoring Dashboard**

1. Create visualizations:
   - **Trips Created:** Counter of `event:trip_created`
   - **Trips by Status:** Pie chart of `status` field
   - **Average Matching Time:** Metric of `matching_duration_ms`
   - **Payment Success Rate:** (success / total) × 100

### Correlation ID Tracing

**Example: Trace a user's trip from request to completion**

1. Get correlation ID from any log entry (e.g., customer complaint)
2. Search: `correlation_id:"abc-123-def"`
3. View timeline:
   ```
   2025-01-15 10:00:00 - trip_created (customer_id: u_123)
   2025-01-15 10:00:05 - trip_matched (driver_id: d_456)
   2025-01-15 10:00:12 - trip_accepted (driver_id: d_456)
   2025-01-15 10:00:15 - payment_initiated (amount: 50.00)
   2025-01-15 10:00:18 - payment_failed (error: card_declined)
   2025-01-15 10:00:20 - trip_cancelled (reason: payment_failed)
   ```

---

## 6. MONITORING & ALERTS

### Critical Alerts to Configure

1. **High Error Rate** (>50 errors/5min) → Slack + Email
2. **Payment Failures** (>10/10min) → Email to finance
3. **API Latency** (p95 >2s) → Slack
4. **Database Connection Errors** → PagerDuty
5. **Failed Logins** (>5 from same IP/5min) → Security team
6. **Low Driver Availability** (<5 online drivers) → Operations
7. **WebSocket Disconnects** (>100/min) → Engineering

### Monitoring OpenSearch Health

**Check cluster health:**
```bash
curl http://localhost:9200/_cluster/health?pretty
```

**Check index sizes:**
```bash
curl http://localhost:9200/_cat/indices?v
```

**Monitor disk usage:**
```bash
df -h /opt/opensearch/data
```

**Set up disk cleanup cron:**
```bash
# /etc/cron.daily/opensearch-cleanup
#!/bin/bash
# Delete indices older than 30 days
curator_cli --host localhost delete_indices --filter_list '[{"filtertype":"age","source":"name","direction":"older","timestring":"%Y.%m.%d","unit":"days","unit_count":30}]'
```

---

## 7. TROUBLESHOOTING

### Common Issues

**Issue 1: Vector not shipping logs**

```bash
# Check Vector status
sudo systemctl status vector

# Check Vector logs
sudo journalctl -u vector -n 100

# Test connection to OpenSearch
curl -X POST http://YOUR_LOG_SERVER:9200/test/_doc -H 'Content-Type: application/json' -d '{"test":"data"}'

# Verify log files exist and are readable
ls -la /var/www/smartline/storage/logs/
sudo chmod 644 /var/www/smartline/storage/logs/*.log
```

**Issue 2: OpenSearch out of disk space**

```bash
# Check disk usage
df -h

# Delete old indices manually
curl -X DELETE "localhost:9200/smartline-logs-2025.01.01"

# Enable automatic cleanup (see retention policy above)
```

**Issue 3: High memory usage**

```bash
# Check OpenSearch heap usage
curl http://localhost:9200/_nodes/stats/jvm?pretty

# Adjust heap size in /opt/opensearch/config/jvm.options
# Set to 50% of available RAM
```

**Issue 4: Logs not appearing in Dashboards**

```bash
# Refresh index pattern
curl -X POST "localhost:9200/smartline-logs-*/_refresh"

# Check if logs are being indexed
curl http://localhost:9200/smartline-logs-*/_count

# Verify time range in Dashboards UI (top right)
```

**Issue 5: ElastAlert not sending alerts**

```bash
# Check ElastAlert logs
sudo journalctl -u elastalert -n 100

# Test rule manually
cd /opt/elastalert
venv/bin/elastalert-test-rule --config config.yaml rules/high_error_rate.yaml

# Verify Slack webhook
curl -X POST https://hooks.slack.com/services/YOUR/WEBHOOK -H 'Content-Type: application/json' -d '{"text":"Test alert"}'
```

---

## COST BREAKDOWN

### Self-Hosted Setup (Recommended)

| Component | Resource | Monthly Cost |
|-----------|----------|--------------|
| Central Log Server | 4 vCPU, 8 GB RAM, 200 GB SSD | $50-80 |
| Vector on app servers | Included (uses existing servers) | $0 |
| Domain + SSL | logs.smartline.com | $1-5 |
| **Total** | | **~$50-85/month** |

### Managed SaaS Alternative

| Service | Cost at 100GB/month | Notes |
|---------|---------------------|-------|
| Better Stack | $100-200 | Easy, good UI |
| Logtail | $150-300 | Laravel focused |
| Datadog | $200+ | Full observability |

---

## NEXT STEPS

### Week 1: Laravel Logging Updates
- [ ] Update `config/logging.php`
- [ ] Create `LogContext` middleware
- [ ] Create `LogService` helper
- [ ] Add logging to 10 critical code paths
- [ ] Test structured JSON output
- [ ] Update `.env` configuration

### Week 2: Log Shipping + Central Server
- [ ] Provision central log server VPS
- [ ] Install OpenSearch + Dashboards
- [ ] Install Vector on each app server
- [ ] Configure Vector to ship logs
- [ ] Test end-to-end log flow
- [ ] Create index patterns in Dashboards

### Week 3: Alerting + Monitoring
- [ ] Install ElastAlert 2
- [ ] Configure 5-7 critical alert rules
- [ ] Test Slack/email notifications
- [ ] Create 3-5 monitoring dashboards
- [ ] Document runbooks for common issues
- [ ] Train team on log search

### Week 4: Production Rollout
- [ ] Deploy to staging first
- [ ] Monitor for 1 week
- [ ] Fix any issues found
- [ ] Deploy to production
- [ ] Monitor closely for 2 weeks
- [ ] Iterate and improve

---

## CONCLUSION

This centralized logging system provides:

✅ **Unified log view** across all VPS servers
✅ **Structured JSON** for queryability
✅ **Correlation IDs** for request tracing
✅ **Real-time alerts** for critical issues
✅ **Cost-effective** (~$50-85/month)
✅ **Production-proven** architecture

**Estimated Setup Time:** 3-4 weeks
**Ongoing Maintenance:** 2-4 hours/month
**Team Training:** 4-8 hours

---

**Questions?** Check troubleshooting section or contact DevOps team.

**End of Guide**
