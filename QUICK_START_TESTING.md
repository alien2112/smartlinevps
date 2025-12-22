# SmartLine - Quick Start Testing Guide

## 🚀 Fastest Way to Start Testing

### Step 1: Start All Services (Automated)

```bash
# Windows PowerShell
.\start-all-services.ps1
```

This will automatically:
- Check Redis and MySQL
- Start Laravel (port 8000)
- Start Node.js (port 3000)
- Open Redis monitor

---

## Step 2: Import Postman Collection

1. Open Postman
2. Click **Import**
3. Select: `SmartLine_Laravel_NodeJS_Testing.postman_collection.json`
4. Go to collection **Variables** tab
5. Verify these values:
   - `base_url`: `http://localhost:8000`
   - `nodejs_url`: `http://localhost:3000`
   - `zone_id`: `778d28d6-1193-436d-9d2b-6c2c31185c8a`

---

## Step 3: Run First Tests

### Test Laravel API

1. Run: **"1. Driver Login"** (saves token automatically)
2. Run: **"3. Get Pending Rides"**

Expected: See list of pending rides

### Test Node.js Service

1. Run: **"1. Health Check"** (Node.js → Health & Monitoring)
2. Run: **"2. Get Metrics"** (Node.js → Health & Monitoring)

Expected: See service health and metrics

---

## Step 4: Test WebSocket (Optional)

### Method 1: Quick Test Script

```bash
cd realtime-service

# Edit test-websocket.js and add your JWT token from Step 3
# Replace: const JWT_TOKEN = 'YOUR_JWT_TOKEN_HERE';

node test-websocket.js
```

### Method 2: Browser Console

```javascript
const socket = io('http://localhost:3000', {
  auth: { token: 'YOUR_JWT_TOKEN' }
});

socket.on('connect', () => {
  console.log('Connected!');
  socket.emit('driver:online', {
    location: { latitude: 30.0444, longitude: 31.2357 },
    vehicle_category_id: 'uuid',
    vehicle_id: 'uuid',
    name: 'Test Driver'
  });
});

socket.on('ride:new', (data) => {
  console.log('New ride:', data);
});
```

---

## 📚 Full Documentation

For comprehensive testing guide, see:
**[TESTING_LARAVEL_NODEJS_TOGETHER.md](TESTING_LARAVEL_NODEJS_TOGETHER.md)**

---

## ⚡ Common Commands

### Start Services Manually

```bash
# Terminal 1: Laravel
php artisan serve

# Terminal 2: Node.js
cd realtime-service
npm run dev

# Terminal 3: Redis Monitor (optional)
redis-cli MONITOR
```

### Health Checks

```bash
# Laravel
curl http://localhost:8000/api/health

# Node.js
curl http://localhost:3000/health

# Redis
redis-cli ping
```

### Check Redis Data

```bash
# Active drivers count
redis-cli ZCARD drivers:locations

# Find nearby drivers
redis-cli GEORADIUS drivers:locations 30.0444 31.2357 10 km WITHDIST

# Check driver status
redis-cli HGETALL driver:status:YOUR_DRIVER_ID
```

---

## 🔍 Troubleshooting

### Laravel won't start
- Check MySQL is running: `mysql -u root -proot -e "SELECT 1"`
- Check port 8000 is free: `netstat -ano | findstr :8000`

### Node.js won't start
- Check Redis is running: `redis-cli ping`
- Check port 3000 is free: `netstat -ano | findstr :3000`
- Check dependencies installed: `cd realtime-service && npm install`

### WebSocket connection fails
- Verify JWT token is valid (not expired)
- Check Node.js is running: `curl http://localhost:3000/health`
- Check token format: Should start with "eyJ..."

### Rides not appearing
- Verify driver zone matches ride zone
- Check `zoneId` header is set in Postman
- Check Laravel logs: `tail -f storage/logs/laravel.log`

---

## 📊 Testing Flow

```
1. Driver Login → Get Token
2. Driver Go Online (WebSocket)
3. Customer Login → Get Token
4. Customer Create Ride → Publishes to Redis
5. Node.js Receives Event → Notifies Driver
6. Driver Accepts Ride (WebSocket)
7. Node.js Calls Laravel → Assigns Driver
8. Driver Sends Location Updates
9. Customer Receives Real-time Updates
10. Driver Completes Ride
11. Customer Rates Driver
```

---

## 🎯 What to Test

### Critical Paths
- [ ] Driver can login and get token
- [ ] Customer can login and get token
- [ ] Customer can create ride request
- [ ] Driver receives ride notification
- [ ] Driver can accept ride
- [ ] Customer receives driver assignment
- [ ] Real-time location updates work
- [ ] Ride status updates work (started, completed)
- [ ] Customer can rate driver

### Redis Integration
- [ ] Laravel publishes events to Redis
- [ ] Node.js receives events from Redis
- [ ] Driver locations stored in Redis GEO
- [ ] Driver status tracked in Redis

### WebSocket Functionality
- [ ] Driver can connect via WebSocket
- [ ] Driver receives real-time notifications
- [ ] Customer receives real-time updates
- [ ] Location updates broadcast correctly

---

## 📞 Need Help?

1. **Full Documentation**: `TESTING_LARAVEL_NODEJS_TOGETHER.md`
2. **Node.js Service Docs**: `realtime-service/README.md`
3. **Deployment Guide**: `NODEJS_REALTIME_DEPLOYMENT.md`
4. **Production Checklist**: `PRODUCTION_READINESS_AUDIT_2025-12-16.md`

---

**Happy Testing! 🚀**
