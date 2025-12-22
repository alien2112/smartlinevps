# Node.js Real-time Service Migration Plan

## Overview

This document outlines the migration plan to integrate the existing Node.js real-time service with your Laravel application. The Node.js service already exists at `realtime-service/` and handles WebSocket connections, location tracking, and driver matching.

## Current State

### ✅ Already Implemented in Node.js Service
- WebSocket infrastructure (Socket.IO)
- Driver location tracking with Redis GEO
- Real-time driver matching
- Connection management with heartbeat/ping
- Disconnect cleanup with grace period (30s)
- Rate limiting on location updates
- JWT authentication
- Event-driven architecture with Redis pub/sub

### ⚠️ Needs Migration from Laravel
- Laravel UserLocationSocketHandler (currently using Ratchet)
- Trip creation events (need to publish to Redis)
- Driver acceptance logic (need to integrate with Node.js)
- Location broadcast logic (move to Node.js)

---

## Migration Steps

### Phase 1: Laravel Integration (Week 1)

#### Step 1.1: Create RealtimeEventPublisher Service

Create `app/Services/RealtimeEventPublisher.php`:

**Purpose:** Publish events from Laravel to Redis for Node.js consumption

**Events to Publish:**
- `ride.created` - New ride request
- `ride.assigned` - Driver assigned to ride
- `ride.started` - Ride started
- `ride.completed` - Ride completed
- `ride.cancelled` - Ride cancelled
- `payment.completed` - Payment processed

#### Step 1.2: Create Internal API Controller for Node.js Callbacks

Create `app/Http/Controllers/Api/Internal/RealtimeController.php`:

**Purpose:** Allow Node.js to call Laravel for database operations

**Endpoints:**
- `POST /api/internal/ride/assign-driver` - Assign driver to ride (with locking)
- `POST /api/internal/events/{event}` - Handle events from Node.js

**Security:** Validate API key from Node.js using `X-API-Key` header

#### Step 1.3: Update TripRequestController

**Changes:**
1. Inject `RealtimeEventPublisher` service
2. After creating trip, publish `ride.created` event to Redis
3. Remove direct driver notification logic (Node.js will handle)
4. Remove WebSocket broadcast logic (Node.js will handle)

#### Step 1.4: Add Configuration

Add to `.env`:
```env
# Node.js Real-time Service
NODEJS_REALTIME_URL=http://localhost:3000
NODEJS_REALTIME_API_KEY=your-secret-key-here

# Redis (for event publishing)
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
```

Add to `config/services.php`:
```php
'realtime' => [
    'url' => env('NODEJS_REALTIME_URL', 'http://localhost:3000'),
    'api_key' => env('NODEJS_REALTIME_API_KEY'),
],
```

---

### Phase 2: Deploy Node.js Service (Week 1-2)

#### Step 2.1: Install Dependencies

```bash
cd realtime-service
npm install
```

#### Step 2.2: Configure Environment

```bash
cp .env.example .env
# Edit .env with production values
```

**Critical .env values:**
- `LARAVEL_API_URL` - URL to Laravel API
- `LARAVEL_API_KEY` - Shared secret for callbacks
- `JWT_SECRET` - Same as Laravel JWT secret
- `REDIS_HOST` - Redis server
- `REDIS_PORT` - Redis port

#### Step 2.3: Test Locally

```bash
npm run dev
```

Visit `http://localhost:3000/health` to verify service is running

#### Step 2.4: Deploy to Production

**Option A: PM2 (Recommended)**
```bash
npm install -g pm2
pm2 start ecosystem.config.js --env production
pm2 save
pm2 startup  # Configure auto-start
```

**Option B: Docker**
```bash
docker build -t smartline-realtime .
docker run -d -p 3000:3000 --env-file .env smartline-realtime
```

**Option C: Systemd**
- See `realtime-service/README.md` for systemd configuration

---

### Phase 3: Frontend Integration (Week 2-3)

#### Step 3.1: Update Driver App

**Changes:**
1. Connect to Node.js WebSocket instead of Laravel Reverb
2. Implement location tracking every 2-3 seconds
3. Listen for `ride:new` events
4. Emit `driver:accept:ride` when accepting rides

**Example:**
```javascript
import io from 'socket.io-client';

const socket = io('http://your-server:3000', {
  auth: { token: userJWTToken }
});

// Go online
socket.emit('driver:online', {
  location: { latitude: 30.0444, longitude: 31.2357 },
  vehicle_category_id: 'uuid',
  vehicle_id: 'uuid'
});

// Send location updates
setInterval(() => {
  socket.emit('driver:location', {
    latitude: currentPosition.latitude,
    longitude: currentPosition.longitude,
    speed: currentPosition.speed,
    heading: currentPosition.heading
  });
}, 2000);

// Listen for rides
socket.on('ride:new', (data) => {
  showRideNotification(data);
});

// Accept ride
socket.emit('driver:accept:ride', { rideId });
```

#### Step 3.2: Update Customer App

**Changes:**
1. Connect to Node.js WebSocket
2. Subscribe to ride updates
3. Listen for driver location updates
4. Listen for ride status changes

**Example:**
```javascript
// Subscribe to ride
socket.emit('customer:subscribe:ride', { rideId: 'your-ride-id' });

// Listen for driver location
socket.on('driver:location:update', (data) => {
  updateDriverMarker(data.location);
});

// Listen for ride updates
socket.on('ride:driver_assigned', (data) => {
  showDriverInfo(data.driver);
});
```

---

### Phase 4: Testing & Validation (Week 3-4)

#### Step 4.1: Integration Testing

**Test Scenarios:**
1. ✅ Driver goes online → Location stored in Redis GEO
2. ✅ Customer creates ride → Node.js receives event via Redis
3. ✅ Node.js finds nearby drivers → Drivers receive notification
4. ✅ Driver accepts ride → Laravel API updates database
5. ✅ Driver location updates → Customer receives real-time updates
6. ✅ Ride completed → Both apps receive confirmation

#### Step 4.2: Load Testing

**Tools:**
- Artillery.io for WebSocket load testing
- Apache JMeter for API load testing

**Test Cases:**
1. 100 concurrent drivers sending location updates
2. 50 concurrent ride creations
3. 1000 WebSocket connections
4. Redis pub/sub throughput

#### Step 4.3: Failover Testing

**Test Scenarios:**
1. Node.js service crashes → Check Laravel graceful degradation
2. Redis goes down → Check fallback behavior
3. Laravel API slow → Check Node.js timeout handling
4. Network partition → Check reconnection logic

---

### Phase 5: Monitoring & Deployment (Week 4+)

#### Step 5.1: Setup Monitoring

**Metrics to Monitor:**
- WebSocket connection count
- Active driver count
- Redis memory usage
- Node.js CPU & memory
- Event processing latency

**Tools:**
- PM2 monitoring dashboard
- Redis CLI: `INFO stats`
- Custom `/metrics` endpoint

#### Step 5.2: Setup Logging

**Configure Winston logging:**
- `logs/combined.log` - All logs
- `logs/error.log` - Errors only

**Centralized logging (optional):**
- Send logs to ELK stack or CloudWatch

#### Step 5.3: Production Deployment Checklist

- [ ] Node.js service running with PM2
- [ ] Redis server configured and running
- [ ] Firewall allows port 3000 (WebSocket)
- [ ] SSL/TLS configured (use HTTPS/WSS)
- [ ] Environment variables secured
- [ ] Monitoring dashboard accessible
- [ ] Backup & disaster recovery plan
- [ ] Load balancer configured (if using multiple instances)
- [ ] Sticky sessions enabled (for WebSocket)

---

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    FRONTEND APPS                             │
│   Driver App (WebSocket) + Customer App (WebSocket)          │
└────────────────┬────────────────────────────────────────────┘
                 │ WebSocket (Port 3000)
                 ▼
┌─────────────────────────────────────────────────────────────┐
│           NODE.JS REALTIME SERVICE                           │
│  • Socket.IO WebSocket Server                                │
│  • Location Tracking (Redis GEO)                             │
│  • Driver Matching                                           │
│  • Event Listener (Redis Pub/Sub)                            │
└───┬─────────────────────────┬───────────────────────────────┘
    │                         │
    │ HTTP Callbacks          │ Redis Pub/Sub
    │                         │
    ▼                         ▼
┌──────────────┐         ┌────────────────┐
│  LARAVEL API │◀───────▶│     REDIS      │
│  (Port 8000) │         │  • Pub/Sub     │
│              │         │  • GEO Index   │
│  • Database  │         │  • Cache       │
│  • Business  │         └────────────────┘
│    Logic     │
│  • Payments  │
└──────────────┘
```

---

## Migration Checklist

### Laravel Changes
- [ ] Create `RealtimeEventPublisher` service
- [ ] Create `RealtimeController` for internal API
- [ ] Update `TripRequestController` to publish events
- [ ] Add Redis configuration to `.env`
- [ ] Add internal API routes
- [ ] Remove old Ratchet WebSocket handler (if exists)

### Node.js Service
- [ ] Install dependencies (`npm install`)
- [ ] Configure `.env` file
- [ ] Test locally (`npm run dev`)
- [ ] Deploy to production (PM2/Docker/Systemd)
- [ ] Configure firewall for port 3000
- [ ] Setup monitoring

### Frontend Apps
- [ ] Update Driver App to connect to Node.js
- [ ] Update Customer App to connect to Node.js
- [ ] Test WebSocket connection
- [ ] Test location tracking
- [ ] Test ride notifications
- [ ] Test ride acceptance flow

### Testing
- [ ] Integration tests pass
- [ ] Load tests pass (1000+ concurrent connections)
- [ ] Failover tests pass
- [ ] Performance benchmarks met

### Production
- [ ] Node.js service deployed
- [ ] SSL/TLS certificates configured
- [ ] Load balancer configured (if needed)
- [ ] Monitoring dashboards accessible
- [ ] Logs centralized
- [ ] Backup procedures documented
- [ ] Rollback plan prepared

---

## Rollback Plan

If issues occur in production:

1. **Immediate Rollback:**
   - Stop Node.js service: `pm2 stop smartline-realtime`
   - Revert frontend apps to use Laravel Reverb
   - Revert Laravel controllers to old logic

2. **Partial Rollback:**
   - Keep Node.js running for location tracking only
   - Use Laravel for ride matching temporarily
   - Gradually migrate features back

3. **Data Consistency:**
   - Check for any pending rides in Redis
   - Sync driver statuses from Redis to MySQL
   - Verify no lost ride requests

---

## Performance Benchmarks

### Expected Performance

| Metric | Target | Max Acceptable |
|--------|--------|----------------|
| WebSocket connection time | <100ms | <500ms |
| Location update processing | <10ms | <50ms |
| Driver matching query | <20ms | <100ms |
| Event publish latency | <5ms | <20ms |
| Concurrent connections/server | 10,000 | 50,000 |

### Scaling Strategy

| Users | Connections | Action Required |
|-------|------------|-----------------|
| 0-10K | <1000 | Single Node.js instance OK |
| 10K-50K | 1000-5000 | Add PM2 cluster mode (2-4 workers) |
| 50K-100K | 5000-10K | Multiple Node.js servers + load balancer |
| 100K+ | 10K+ | Redis Cluster + Node.js cluster + CDN |

---

## Success Metrics

After migration, you should see:

- ✅ WebSocket message latency <200ms (p95)
- ✅ Zero blocking database writes in WebSocket handler
- ✅ Driver location updates 1/sec without Redis overload
- ✅ Race conditions eliminated (Redis locks)
- ✅ Connection cleanup working (drivers go offline on disconnect)
- ✅ Rate limiting preventing abuse
- ✅ 99.9% uptime for real-time service

---

## Support & Troubleshooting

### Common Issues

**Issue:** Cannot connect to WebSocket
- Check Node.js service: `pm2 status`
- Check firewall: `curl http://localhost:3000/health`
- Check CORS settings in `.env`

**Issue:** Drivers not receiving notifications
- Check Redis: `redis-cli ping`
- Check Laravel event publishing: Review logs
- Check Node.js subscriptions: Review logs

**Issue:** High memory usage
- Reduce `MAX_CONNECTIONS_PER_INSTANCE`
- Enable PM2 cluster mode
- Check for memory leaks: `pm2 monit`

### Getting Help

1. Check logs: `pm2 logs smartline-realtime`
2. Check metrics: `curl http://localhost:3000/metrics`
3. Review README: `realtime-service/README.md`
4. Contact development team

---

## Timeline Summary

| Week | Phase | Key Deliverables |
|------|-------|------------------|
| 1 | Laravel Integration | Event publisher, Internal API |
| 1-2 | Node.js Deployment | Service running in production |
| 2-3 | Frontend Integration | Apps using WebSocket |
| 3-4 | Testing | Load tests, Integration tests |
| 4+ | Production | Full deployment, monitoring |

**Total Estimated Time:** 4-6 weeks

---

## Next Steps

1. **Immediate:**
   - Create Laravel integration files (see Phase 1)
   - Test Node.js service locally
   - Review existing Redis configuration

2. **This Week:**
   - Deploy Node.js service to staging
   - Integrate Laravel event publisher
   - Update one frontend app for testing

3. **Next Week:**
   - Complete frontend integration
   - Run integration tests
   - Prepare production deployment plan

4. **Following Weeks:**
   - Deploy to production
   - Monitor performance
   - Optimize based on metrics
