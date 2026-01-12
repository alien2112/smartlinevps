# Flutter WebSocket Connection Guide

## ✅ WebSocket Server Status
- **Status:** Online and Ready
- **Service:** SmartLine Real-time Service v1.0.0
- **Uptime:** Running in cluster mode (2 workers)
- **Redis:** Connected and healthy
- **Connections:** Ready to accept clients

---

## Connection Details

### Production URL
```
https://smartline-it.com
```

### Socket.IO Configuration
```dart
Socket socket = io('https://smartline-it.com', <String, dynamic>{
  'path': '/socket.io/',
  'transports': ['websocket', 'polling'],
  'autoConnect': false,
  'auth': {
    'token': 'YOUR_JWT_TOKEN_HERE'
  }
});
```

---

## Authentication

### Required Token
The WebSocket requires a valid JWT token from Laravel authentication.

**Where to get the token:**
After successful login via `/api/driver/auth/login` or `/api/customer/auth/login`, you receive:
```json
{
  "response_code": "auth_login_200",
  "content": {
    "token": "eyJ0eXAiOiJKV1QiLCJ...",
    ...
  }
}
```

### Token Placement
You can pass the token in TWO ways:

**Option 1: In auth object (Recommended)**
```dart
Socket socket = io('https://smartline-it.com', <String, dynamic>{
  'auth': {
    'token': userToken
  }
});
```

**Option 2: In query parameters**
```dart
Socket socket = io('https://smartline-it.com', <String, dynamic>{
  'query': {
    'token': userToken
  }
});
```

### Development/Test Tokens
For testing, you can use these tokens (only in development mode):
- `test-driver-token` - Simulates a driver
- `test-customer-token` - Simulates a customer

---

## Flutter Implementation Example

### 1. Add Dependencies
```yaml
# pubspec.yaml
dependencies:
  socket_io_client: ^2.0.3+1
```

### 2. WebSocket Service Class
```dart
import 'package:socket_io_client/socket_io_client.dart' as IO;

class WebSocketService {
  IO.Socket? socket;
  String? userToken;
  
  // Initialize and connect
  void connect(String token) {
    userToken = token;
    
    socket = IO.io('https://smartline-it.com', <String, dynamic>{
      'path': '/socket.io/',
      'transports': ['websocket', 'polling'],
      'autoConnect': false,
      'auth': {
        'token': token
      },
      'reconnection': true,
      'reconnectionDelay': 1000,
      'reconnectionDelayMax': 5000,
      'reconnectionAttempts': 5,
    });
    
    // Connection events
    socket!.onConnect((_) {
      print('✅ Socket connected: ${socket!.id}');
    });
    
    socket!.onConnectError((error) {
      print('❌ Connection error: $error');
    });
    
    socket!.onError((error) {
      print('❌ Socket error: $error');
    });
    
    socket!.onDisconnect((_) {
      print('⚠️ Socket disconnected');
    });
    
    socket!.onReconnect((data) {
      print('🔄 Socket reconnected');
    });
    
    // Start connection
    socket!.connect();
  }
  
  // Disconnect
  void disconnect() {
    socket?.disconnect();
    socket?.dispose();
  }
  
  // Listen to events
  void on(String event, Function(dynamic) callback) {
    socket?.on(event, callback);
  }
  
  // Emit events
  void emit(String event, dynamic data) {
    socket?.emit(event, data);
  }
}
```

### 3. Usage in Your App
```dart
// Initialize WebSocket
final wsService = WebSocketService();

// After successful login
void onLoginSuccess(String token) {
  wsService.connect(token);
  
  // Listen for ride updates
  wsService.on('ride:created', (data) {
    print('New ride created: $data');
  });
  
  wsService.on('ride:cancelled', (data) {
    print('Ride cancelled: $data');
  });
  
  wsService.on('driver:assigned', (data) {
    print('Driver assigned: $data');
  });
}

// Send location update (for drivers)
void sendLocationUpdate(double lat, double lng) {
  wsService.emit('location:update', {
    'lat': lat,
    'lng': lng,
    'timestamp': DateTime.now().millisecondsSinceEpoch
  });
}

// Disconnect on logout
void onLogout() {
  wsService.disconnect();
}
```

---

## Available Events

### Events You Can Listen To (from server)

#### For Drivers:
- `ride:created` - New ride request available
- `ride:cancelled` - Ride was cancelled
- `ride:accepted` - Your bid was accepted
- `ride:started` - Ride has started
- `ride:completed` - Ride completed
- `payment:completed` - Payment processed
- `otp:verified` - Customer verified OTP

#### For Customers:
- `driver:assigned` - Driver assigned to your ride
- `driver:arrived` - Driver arrived at pickup location
- `ride:started` - Ride started
- `ride:completed` - Ride completed
- `payment:completed` - Payment processed

#### For Both:
- `location:update` - Real-time location updates
- `chat:message` - New chat message
- `notification` - General notifications

### Events You Can Emit (to server)

#### For Drivers:
```dart
// Update location
socket.emit('location:update', {
  'lat': 30.0444,
  'lng': 31.2357,
  'bearing': 45.0,
  'speed': 60.0
});

// Accept ride
socket.emit('ride:accept', {
  'ride_id': 'ride-uuid'
});

// Update ride status
socket.emit('ride:status', {
  'ride_id': 'ride-uuid',
  'status': 'arrived' // 'arrived', 'started', 'completed'
});
```

#### For Customers:
```dart
// Request location update
socket.emit('driver:location:request', {
  'ride_id': 'ride-uuid'
});

// Send chat message
socket.emit('chat:message', {
  'ride_id': 'ride-uuid',
  'message': 'Hello'
});
```

---

## Troubleshooting

### Connection Issues

**1. Authentication Error**
```
Error: Authentication error: No token provided
```
**Solution:** Make sure you're passing the token in `auth.token` or `query.token`

**2. Invalid Token**
```
Error: Authentication error: Invalid token
```
**Solution:** 
- Check that the token is valid JWT from Laravel
- Verify the token hasn't expired
- Ensure JWT_SECRET matches between Laravel and Node.js

**3. Cannot Connect**
```
Connection timeout / Connection refused
```
**Solution:**
- Check internet connection
- Verify URL is correct: `https://smartline-it.com`
- Check that path is `/socket.io/`

**4. Immediate Disconnect**
```
Connected then immediately disconnected
```
**Solution:**
- Usually an authentication issue
- Check server logs for error details
- Verify token format and validity

### Debugging

**Enable Socket.IO Debug Logs:**
```dart
Socket socket = IO.io('https://smartline-it.com', <String, dynamic>{
  // ... other options
  'debug': true, // Enable debug logging
});
```

**Check Connection State:**
```dart
print('Socket connected: ${socket.connected}');
print('Socket ID: ${socket.id}');
```

---

## Server Configuration

### Current Settings
- **Environment:** Development (`NODE_ENV=development`)
- **Port:** 3002 (internal), proxied via nginx
- **CORS:** Enabled for all origins (`*`)
- **Ping Timeout:** 60 seconds
- **Ping Interval:** 25 seconds
- **Max Connections:** 10,000 per instance
- **Workers:** 2 (cluster mode)
- **Redis:** Enabled for horizontal scaling
- **Session Cache:** 5 minutes (reduces auth latency)

### Health Check
You can check if the server is running:
```bash
curl https://smartline-it.com/api/realtime/health
```

Expected response:
```json
{
  "status": "ok",
  "service": "smartline-realtime",
  "uptime": 123.45,
  "connections": 0,
  "redisAdapter": true
}
```

---

## Recent Fixes Applied

✅ **Fixed nginx proxy configuration** (Jan 12, 2026)
- Changed proxy from port 3000 → 3002
- WebSocket upgrade headers properly configured
- Path `/socket.io/` correctly proxied

✅ **Service restarted and verified**
- Both worker instances online
- Redis connected and healthy
- All event subscriptions active

---

## Testing Checklist

Before deploying to Flutter app:

- [ ] Can connect to `https://smartline-it.com/socket.io/`
- [ ] Token authentication works
- [ ] Can emit events to server
- [ ] Can receive events from server
- [ ] Connection survives network interruption (reconnection works)
- [ ] Location updates being sent (for drivers)
- [ ] Ride events being received

---

## Support

If you continue to have connection issues:

1. Check the server logs:
   ```bash
   pm2 logs smartline-realtime --lines 50
   ```

2. Test the WebSocket endpoint:
   ```bash
   curl -I https://smartline-it.com/socket.io/
   ```
   Should return `HTTP/1.1 400 Bad Request` (normal for Socket.IO without proper handshake)

3. Verify token is valid:
   ```bash
   curl -H "Authorization: Bearer YOUR_TOKEN" \
        https://smartline-it.com/api/auth/verify
   ```

---

**Server is ready and waiting for Flutter connections!** 🚀
