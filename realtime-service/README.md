# SmartLine Real-time Service (Node.js)

## Overview

Production-grade Node.js WebSocket service handling real-time features for SmartLine ride-hailing platform.

**Why Node.js?**
- Laravel handles business logic (APIs, database, payments)
- Node.js handles real-time (WebSocket, live tracking, instant dispatch)
- Redis bridges them via pub/sub

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        FRONTEND APPS                         │
│  (Driver App WebSocket + Customer App WebSocket)             │
└────────────────┬────────────────────────────────────────────┘
                 │ WebSocket Connection
                 ▼
┌─────────────────────────────────────────────────────────────┐
│               NODE.JS REALTIME SERVICE (Port 3000)           │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  Socket.IO Server (WebSocket + Polling Fallback)     │   │
│  └──────────────────────────────────────────────────────┘   │
│                         │                                    │
│       ┌─────────────────┼─────────────────┐                 │
│       ▼                 ▼                 ▼                  │
│  ┌─────────┐    ┌─────────────┐   ┌──────────────┐         │
│  │Location │    │Driver Match │   │Redis Event   │         │
│  │Service  │    │Service      │   │Bus (Listener)│         │
│  └─────────┘    └─────────────┘   └──────────────┘         │
└───────┬──────────────────┬────────────────┬─────────────────┘
        │                  │                │
        ▼                  ▼                ▼
┌────────────────────────────────────────────────────────────┐
│                      REDIS                                  │
│  • GEO Index (driver locations)                            │
│  • Pub/Sub (Laravel ←→ Node events)                        │
│  • Cache (ride data, driver status)                        │
└────────────────┬───────────────────────────────────────────┘
                 │
                 ▼ HTTP Callbacks
┌────────────────────────────────────────────────────────────┐
│              LARAVEL API (Port 8000)                        │
│  • Business Logic                                           │
│  • Database (MySQL)                                         │
│  • Payments                                                 │
│  • Publishes events to Redis                               │
└────────────────────────────────────────────────────────────┘
```

---

## Features

### ✅ Real-time Location Tracking
- High-frequency GPS updates (1-3 seconds)
- Redis GEO for spatial queries
- Auto-throttling to prevent Redis overload
- Location broadcast to riders

### ✅ Driver Matching & Dispatch
- Find nearby drivers using GEORADIUS
- Notify multiple drivers simultaneously
- Race-condition-free ride assignment (Redis locks)
- Auto-timeout if no driver accepts

### ✅ WebSocket Management
- Socket.IO with fallback to long-polling
- JWT authentication
- Auto-reconnection handling
- Heartbeat/ping mechanism
- Graceful shutdown

### ✅ Laravel Integration
- Redis pub/sub event bridge
- HTTP callbacks for critical actions
- Shared state via Redis

---

## Installation

### Prerequisites
- Node.js >= 18.0.0
- npm >= 9.0.0
- Redis >= 6.0

### Step 1: Install Dependencies
```bash
cd realtime-service
npm install
```

### Step 2: Configure Environment
```bash
cp .env.example .env
# Edit .env with your configuration
```

### Step 3: Run in Development
```bash
npm run dev
```

### Step 4: Run in Production
```bash
# Using PM2
npm run pm2:start

# View logs
npm run pm2:logs

# Restart
npm run pm2:restart

# Stop
npm run pm2:stop
```

---

## Configuration (.env)

```env
# Server
NODE_ENV=production
PORT=3000
HOST=0.0.0.0

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DB=0

# Laravel API
LARAVEL_API_URL=http://localhost:8000
LARAVEL_API_KEY=your-secret-key

# JWT
JWT_SECRET=same-secret-as-laravel

# WebSocket
WS_CORS_ORIGIN=*
WS_PING_TIMEOUT=60000
WS_PING_INTERVAL=25000

# Performance
MAX_CONNECTIONS_PER_INSTANCE=10000
WORKER_PROCESSES=2
```

---

## Laravel Integration

### Step 1: Add Event Publisher to Laravel

Use `app/Services/RealtimeEventPublisher.php` (already created) in your controllers:

```php
use App\Services\RealtimeEventPublisher;

class TripRequestController extends Controller
{
    protected $realtimePublisher;

    public function __construct(RealtimeEventPublisher $realtimePublisher)
    {
        $this->realtimePublisher = $realtimePublisher;
    }

    public function createRideRequest(Request $request)
    {
        // ... create trip logic ...

        // Publish to Node.js for real-time dispatch
        $this->realtimePublisher->publishRideCreated($trip);

        return response()->json(...);
    }

    public function rideStatusUpdate(Request $request)
    {
        // ... update status logic ...

        if ($trip->current_status === 'started') {
            $this->realtimePublisher->publishRideStarted($trip);
        }

        if ($trip->current_status === 'completed') {
            $this->realtimePublisher->publishRideCompleted($trip);
        }

        if ($trip->current_status === 'cancelled') {
            $this->realtimePublisher->publishRideCancelled($trip, $cancelledBy);
        }

        return response()->json(...);
    }
}
```

### Step 2: Create Internal API Endpoint for Node.js Callbacks

Create `app/Http/Controllers/Api/Internal/RealtimeController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\TripLockingService;

class RealtimeController extends Controller
{
    public function __construct(
        protected TripLockingService $lockingService
    ) {
        // Verify API key from Node.js
        $apiKey = request()->header('X-API-Key');
        if ($apiKey !== config('app.internal_api_key')) {
            abort(403, 'Invalid API key');
        }
    }

    /**
     * Node.js calls this when driver accepts a ride
     */
    public function assignDriver(Request $request)
    {
        $validated = $request->validate([
            'ride_id' => 'required|uuid',
            'driver_id' => 'required|uuid',
        ]);

        $result = $this->lockingService->lockAndAssignTrip(
            $validated['ride_id'],
            $validated['driver_id']
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'ride' => $result['trip'],
                'driver' => $result['trip']->driver,
                'estimatedArrival' => 5 // Calculate ETA
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message']
        ], 409);
    }

    /**
     * Handle events from Node.js
     */
    public function handleEvent(Request $request, string $event)
    {
        $data = $request->all();

        switch ($event) {
            case 'ride.no_drivers':
                // Update ride status to "no drivers available"
                TripRequest::where('id', $data['ride_id'])->update([
                    'current_status' => 'no_drivers_available'
                ]);
                break;

            case 'ride.timeout':
                // Mark ride as timed out
                TripRequest::where('id', $data['ride_id'])->update([
                    'current_status' => 'timeout'
                ]);
                break;
        }

        return response()->json(['success' => true]);
    }
}
```

Add routes in `routes/api.php`:

```php
Route::prefix('internal')->group(function () {
    Route::post('ride/assign-driver', [RealtimeController::class, 'assignDriver']);
    Route::post('events/{event}', [RealtimeController::class, 'handleEvent']);
});
```

---

## Mobile App Integration

### Driver App (React Native / Flutter)

```javascript
import io from 'socket.io-client';

const socket = io('http://your-server:3000', {
  auth: {
    token: userJWTToken // From Laravel login
  },
  transports: ['websocket', 'polling']
});

// Connect
socket.on('connect', () => {
  console.log('Connected to realtime service');

  // Go online
  socket.emit('driver:online', {
    location: {
      latitude: 30.0444,
      longitude: 31.2357
    },
    vehicle_category_id: 'uuid',
    vehicle_id: 'uuid',
    name: 'Driver Name'
  });
});

// Send location updates (every 2-3 seconds while driving)
setInterval(() => {
  socket.emit('driver:location', {
    latitude: currentPosition.latitude,
    longitude: currentPosition.longitude,
    speed: currentPosition.speed,
    heading: currentPosition.heading,
    accuracy: currentPosition.accuracy
  });
}, 2000);

// Listen for new ride requests
socket.on('ride:new', (data) => {
  console.log('New ride request', data);
  // Show notification to driver
  showRideNotification(data);
});

// Accept a ride
function acceptRide(rideId) {
  socket.emit('driver:accept:ride', { rideId });
}

// Listen for acceptance result
socket.on('ride:accept:success', (data) => {
  console.log('Ride accepted', data);
  // Navigate to ride screen
});

socket.on('ride:accept:failed', (data) => {
  console.log('Failed to accept ride', data.message);
  // Show error
});

// Go offline
function goOffline() {
  socket.emit('driver:offline');
}

// Disconnect
socket.on('disconnect', () => {
  console.log('Disconnected from realtime service');
});
```

### Customer App (React Native / Flutter)

```javascript
const socket = io('http://your-server:3000', {
  auth: {
    token: userJWTToken
  }
});

// Subscribe to ride updates
socket.emit('customer:subscribe:ride', { rideId: 'your-ride-id' });

// Listen for driver location updates
socket.on('driver:location:update', (data) => {
  console.log('Driver location', data);
  // Update map marker
  updateDriverMarker(data.location);
});

// Listen for driver assignment
socket.on('ride:driver_assigned', (data) => {
  console.log('Driver assigned', data);
  // Show driver info
});

// Listen for ride started
socket.on('ride:started', (data) => {
  console.log('Ride started');
});

// Listen for ride completed
socket.on('ride:completed', (data) => {
  console.log('Ride completed', data);
  // Navigate to payment screen
});

// Unsubscribe from ride updates
socket.emit('customer:unsubscribe:ride', { rideId: 'your-ride-id' });
```

---

## API Reference

### WebSocket Events

#### Driver Events (Emit)

| Event | Data | Description |
|-------|------|-------------|
| `driver:online` | `{ location, vehicle_category_id, vehicle_id, name }` | Go online |
| `driver:offline` | - | Go offline |
| `driver:location` | `{ latitude, longitude, speed, heading, accuracy }` | Update location (high frequency) |
| `driver:accept:ride` | `{ rideId }` | Accept a ride |

#### Driver Events (Listen)

| Event | Data | Description |
|-------|------|-------------|
| `ride:new` | `{ rideId, pickupLocation, destinationLocation, estimatedFare, distance, expiresAt }` | New ride available |
| `ride:accept:success` | `{ rideId, message, rideDetails }` | Ride accepted successfully |
| `ride:accept:failed` | `{ rideId, message }` | Failed to accept ride |
| `ride:taken` | `{ rideId, message }` | Ride accepted by another driver |
| `ride:started` | `{ rideId, message }` | Ride started |
| `ride:completed` | `{ rideId, finalFare, message }` | Ride completed |
| `ride:cancelled` | `{ rideId, cancelledBy, message }` | Ride cancelled |

#### Customer Events (Emit)

| Event | Data | Description |
|-------|------|-------------|
| `customer:subscribe:ride` | `{ rideId }` | Subscribe to ride updates |
| `customer:unsubscribe:ride` | `{ rideId }` | Unsubscribe from ride updates |

#### Customer Events (Listen)

| Event | Data | Description |
|-------|------|-------------|
| `driver:location:update` | `{ rideId, driverId, location, timestamp }` | Driver location updated |
| `ride:driver_assigned` | `{ rideId, driver, estimatedArrival }` | Driver assigned to ride |
| `ride:no_drivers` | `{ rideId, message }` | No drivers available |
| `ride:timeout` | `{ rideId, message }` | No driver accepted |
| `ride:started` | `{ rideId, message }` | Ride started |
| `ride:completed` | `{ rideId, finalFare, message }` | Ride completed |
| `ride:cancelled` | `{ rideId, cancelledBy, message }` | Ride cancelled |
| `payment:completed` | `{ rideId, amount, message }` | Payment completed |

---

## Deployment

### Using PM2 (Recommended)

```bash
# Install PM2 globally
npm install -g pm2

# Start service
pm2 start ecosystem.config.js --env production

# View status
pm2 status

# View logs
pm2 logs smartline-realtime

# Restart
pm2 restart smartline-realtime

# Stop
pm2 stop smartline-realtime

# Save PM2 configuration
pm2 save

# Setup PM2 startup script
pm2 startup
```

### Using Docker

Create `Dockerfile`:

```dockerfile
FROM node:18-alpine

WORKDIR /app

COPY package*.json ./
RUN npm ci --only=production

COPY . .

EXPOSE 3000

CMD ["node", "src/server.js"]
```

Build and run:

```bash
docker build -t smartline-realtime .
docker run -d -p 3000:3000 --env-file .env smartline-realtime
```

### Using Systemd (Alternative)

Create `/etc/systemd/system/smartline-realtime.service`:

```ini
[Unit]
Description=SmartLine Realtime Service
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/smartline/realtime-service
ExecStart=/usr/bin/node /var/www/smartline/realtime-service/src/server.js
Restart=on-failure
Environment=NODE_ENV=production

[Install]
WantedBy=multi-user.target
```

Start service:

```bash
sudo systemctl enable smartline-realtime
sudo systemctl start smartline-realtime
sudo systemctl status smartline-realtime
```

---

## Monitoring

### Health Check

```bash
curl http://localhost:3000/health
```

Response:

```json
{
  "status": "ok",
  "service": "smartline-realtime",
  "uptime": 12345.67,
  "timestamp": "2025-12-16T14:30:00.000Z",
  "connections": 150
}
```

### Metrics

```bash
curl http://localhost:3000/metrics
```

Response:

```json
{
  "connections": 150,
  "activeDrivers": 45,
  "activeRides": 12,
  "memory": {
    "rss": 123456789,
    "heapTotal": 87654321,
    "heapUsed": 65432109
  },
  "uptime": 12345.67
}
```

### Logs

```bash
# View all logs
tail -f logs/combined.log

# View errors only
tail -f logs/error.log

# PM2 logs
pm2 logs smartline-realtime
```

---

## Troubleshooting

### Issue: Cannot connect to WebSocket

**Check:**
1. Node.js service is running: `pm2 status`
2. Port 3000 is accessible: `curl http://localhost:3000/health`
3. Firewall allows port 3000
4. CORS configured correctly in `.env`

### Issue: Drivers not receiving ride notifications

**Check:**
1. Redis is running: `redis-cli ping`
2. Laravel is publishing events: Check Laravel logs
3. Node.js is subscribed to channels: Check Node logs for "Subscribed to laravel:ride.created"
4. Driver is online: Check Redis `HGETALL driver:status:{driver_id}`

### Issue: High memory usage

**Solution:**
1. Reduce `MAX_CONNECTIONS_PER_INSTANCE`
2. Increase `WORKER_PROCESSES` and use cluster mode
3. Check for memory leaks in logs
4. Restart service: `pm2 restart smartline-realtime`

---

## Performance Tuning

### Redis Optimization

```bash
# In redis.conf
maxmemory 1gb
maxmemory-policy allkeys-lru
```

### Node.js Cluster Mode

Set in `.env`:

```env
CLUSTER_MODE=true
WORKER_PROCESSES=4  # Number of CPU cores
```

### Load Balancing

Use Nginx to load balance multiple Node.js instances:

```nginx
upstream nodejs_backend {
    ip_hash;  # Sticky sessions for WebSocket
    server 127.0.0.1:3000;
    server 127.0.0.1:3001;
    server 127.0.0.1:3002;
}

server {
    listen 80;

    location / {
        proxy_pass http://nodejs_backend;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
    }
}
```

---

## Security

1. **Use HTTPS/WSS in production**
2. **Validate JWT tokens** (set `VALIDATE_WITH_LARAVEL=true`)
3. **Set strong API key** for internal endpoints
4. **Enable rate limiting** (can be added via middleware)
5. **Sanitize all inputs**
6. **Use environment variables** for secrets

---

## Testing

```bash
# Run tests
npm test

# Lint code
npm run lint
```

---

## License

MIT

---

## Support

For issues or questions, contact the development team.
