# Flutter Socket.IO Connection Guide - Complete

## 📋 Overview

This guide shows you how to connect your Flutter app to the SmartLine real-time socket service for live updates, ride notifications, and real-time features.

---

## 🔌 Connection Details

### Socket Server URL

The socket URL is available from the config endpoint:

**Endpoint:** `GET /api/customer/config/core` or `GET /api/driver/config/core`

**Response includes:**
```json
{
  "websocket_url": "https://smartline-it.com"
}
```

**Use this URL** - It's automatically configured and may change based on environment.

### Socket.IO Path
- **Path:** `/socket.io/`
- **Full URL:** `https://smartline-it.com/socket.io/`

---

## 📦 Flutter Setup

### 1. Add Dependencies

Add to your `pubspec.yaml`:

```yaml
dependencies:
  socket_io_client: ^2.0.3+1
  # Optional: For state management
  provider: ^6.0.5
  # Optional: For secure storage
  flutter_secure_storage: ^9.0.0
```

Then run:
```bash
flutter pub get
```

---

## 🔐 Authentication

### Getting the Token

After successful login via:
- `/api/customer/auth/login` 
- `/api/driver/auth/login`

You receive a JWT token:
```json
{
  "response_code": "auth_login_200",
  "content": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
    "user": {...}
  }
}
```

**Store this token securely** (use `flutter_secure_storage`).

### Token Placement

The socket accepts tokens in **3 ways** (in order of preference):

1. **Auth object (Recommended)**
2. **Query parameter**
3. **Authorization header**

---

## 💻 Complete Flutter Implementation

### 1. Socket Service Class

Create `lib/services/socket_service.dart`:

```dart
import 'package:socket_io_client/socket_io_client.dart' as IO;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class SocketService {
  static final SocketService _instance = SocketService._internal();
  factory SocketService() => _instance;
  SocketService._internal();

  IO.Socket? _socket;
  String? _socketUrl;
  String? _token;
  final FlutterSecureStorage _storage = const FlutterSecureStorage();
  
  bool get isConnected => _socket?.connected ?? false;
  String? get socketId => _socket?.id;

  /// Initialize socket URL from config
  Future<void> initializeSocketUrl() async {
    try {
      // Get socket URL from config endpoint
      final response = await http.get(
        Uri.parse('https://smartline-it.com/api/customer/config/core'),
        headers: {'Accept': 'application/json'},
      );
      
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        _socketUrl = data['content']['websocket_url'] ?? 'https://smartline-it.com';
      } else {
        _socketUrl = 'https://smartline-it.com'; // Fallback
      }
    } catch (e) {
      _socketUrl = 'https://smartline-it.com'; // Fallback
    }
  }

  /// Connect to socket with token
  Future<void> connect(String token) async {
    if (_socket?.connected ?? false) {
      print('Socket already connected');
      return;
    }

    // Initialize URL if not set
    if (_socketUrl == null) {
      await initializeSocketUrl();
    }

    _token = token;
    await _storage.write(key: 'socket_token', value: token);

    try {
      _socket = IO.io(
        _socketUrl!,
        IO.OptionBuilder()
            .setPath('/socket.io/')
            .setTransports(['websocket', 'polling'])
            .setAuth({'token': token}) // Primary method
            .setQuery({'token': token}) // Fallback method
            .setExtraHeaders({'Authorization': 'Bearer $token'}) // Header fallback
            .enableAutoConnect()
            .enableReconnection()
            .setReconnectionDelay(1000)
            .setReconnectionDelayMax(5000)
            .setReconnectionAttempts(5)
            .setTimeout(20000)
            .build(),
      );

      _setupEventListeners();
    } catch (e) {
      print('Socket connection error: $e');
      rethrow;
    }
  }

  /// Setup connection event listeners
  void _setupEventListeners() {
    _socket?.onConnect((_) {
      print('✅ Socket connected: ${_socket?.id}');
      _onConnected();
    });

    _socket?.onConnectError((error) {
      print('❌ Connection error: $error');
      _onConnectionError(error);
    });

    _socket?.onError((error) {
      print('❌ Socket error: $error');
      _onError(error);
    });

    _socket?.onDisconnect((reason) {
      print('⚠️ Socket disconnected: $reason');
      _onDisconnected(reason);
    });

    _socket?.onReconnect((attemptNumber) {
      print('🔄 Socket reconnected after $attemptNumber attempts');
      _onReconnected(attemptNumber);
    });

    _socket?.onReconnectAttempt((attemptNumber) {
      print('🔄 Reconnection attempt $attemptNumber');
    });

    _socket?.onReconnectError((error) {
      print('❌ Reconnection error: $error');
    });
  }

  /// Connect from stored token
  Future<void> connectFromStorage() async {
    final token = await _storage.read(key: 'socket_token');
    if (token != null) {
      await connect(token);
    }
  }

  /// Disconnect socket
  void disconnect() {
    _socket?.disconnect();
    _socket?.dispose();
    _socket = null;
  }

  /// Listen to server events
  void on(String event, Function(dynamic) callback) {
    _socket?.on(event, callback);
  }

  /// Remove event listener
  void off(String event) {
    _socket?.off(event);
  }

  /// Emit event to server
  void emit(String event, [dynamic data]) {
    if (!isConnected) {
      print('⚠️ Cannot emit: Socket not connected');
      return;
    }
    _socket?.emit(event, data);
  }

  /// Callbacks (override in your app)
  void _onConnected() {
    // Override this in your app
  }

  void _onConnectionError(dynamic error) {
    // Override this in your app
  }

  void _onError(dynamic error) {
    // Override this in your app
  }

  void _onDisconnected(String? reason) {
    // Override this in your app
  }

  void _onReconnected(int attemptNumber) {
    // Override this in your app
  }
}
```

---

### 2. Usage Example - Customer App

```dart
import 'package:flutter/material.dart';
import 'services/socket_service.dart';

class RideScreen extends StatefulWidget {
  @override
  _RideScreenState createState() => _RideScreenState();
}

class _RideScreenState extends State<RideScreen> {
  final SocketService _socketService = SocketService();
  String? _rideStatus;

  @override
  void initState() {
    super.initState();
    _initializeSocket();
  }

  Future<void> _initializeSocket() async {
    // Get token from your auth service
    final token = await _getAuthToken();
    
    // Connect to socket
    await _socketService.connect(token);

    // Listen for ride events
    _socketService.on('ride:driver_assigned', (data) {
      setState(() {
        _rideStatus = 'Driver assigned';
      });
      print('Driver assigned: $data');
    });

    _socketService.on('ride:started', (data) {
      setState(() {
        _rideStatus = 'Ride started';
      });
      print('Ride started: $data');
    });

    _socketService.on('driver:location:update', (data) {
      print('Driver location: ${data['lat']}, ${data['lng']}');
      // Update map with driver location
    });

    _socketService.on('safety_alert:sent', (data) {
      print('Safety alert sent: $data');
    });
  }

  @override
  void dispose() {
    _socketService.disconnect();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Ride Status')),
      body: Center(
        child: Text(_rideStatus ?? 'Waiting for driver...'),
      ),
    );
  }
}
```

---

### 3. Usage Example - Driver App

```dart
import 'package:flutter/material.dart';
import 'services/socket_service.dart';

class DriverHomeScreen extends StatefulWidget {
  @override
  _DriverHomeScreenState createState() => _DriverHomeScreenState();
}

class _DriverHomeScreenState extends State<DriverHomeScreen> {
  final SocketService _socketService = SocketService();
  List<Map<String, dynamic>> _availableRides = [];

  @override
  void initState() {
    super.initState();
    _initializeSocket();
  }

  Future<void> _initializeSocket() async {
    final token = await _getAuthToken();
    await _socketService.connect(token);

    // Listen for new ride requests
    _socketService.on('ride:new', (data) {
      setState(() {
        _availableRides.add(data);
      });
      print('New ride available: $data');
      _showRideNotification(data);
    });

    _socketService.on('ride:accept:success', (data) {
      print('Ride accepted: $data');
      _navigateToRideDetails(data['ride_id']);
    });

    _socketService.on('ride:started', (data) {
      print('Ride started: $data');
    });

    // Go online
    _socketService.emit('driver:online', {});
  }

  void _sendLocationUpdate(double lat, double lng) {
    _socketService.emit('location:update', {
      'lat': lat,
      'lng': lng,
      'bearing': 0.0,
      'speed': 0.0,
      'timestamp': DateTime.now().millisecondsSinceEpoch,
    });
  }

  void _acceptRide(String rideId) {
    _socketService.emit('ride:accept', {
      'rideId': rideId,
    });
  }

  @override
  void dispose() {
    _socketService.emit('driver:offline', {});
    _socketService.disconnect();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Driver Home')),
      body: ListView.builder(
        itemCount: _availableRides.length,
        itemBuilder: (context, index) {
          final ride = _availableRides[index];
          return ListTile(
            title: Text('Ride ${ride['ride_id']}'),
            subtitle: Text('Fare: ${ride['estimated_fare']}'),
            onTap: () => _acceptRide(ride['ride_id']),
          );
        },
      ),
    );
  }
}
```

---

## 📡 Available Events

### Events You Can Listen To (from server)

#### For Customers:
- `ride:driver_assigned` - Driver assigned to your ride
- `ride:started` - Ride has started
- `ride:completed` - Ride completed
- `ride:cancelled` - Ride was cancelled
- `driver:location:update` - Real-time driver location
- `driver:arrived` - Driver arrived at pickup
- `payment:completed` - Payment processed
- `safety_alert:sent` - Safety alert sent confirmation

#### For Drivers:
- `ride:new` - New ride request available
- `ride:accept:success` - Your ride acceptance was successful
- `ride:accept:failed` - Ride acceptance failed
- `ride:started` - Ride has started
- `ride:completed` - Ride completed
- `ride:cancelled` - Ride was cancelled
- `payment:completed` - Payment processed
- `driver:online:success` - Successfully went online
- `driver:offline:success` - Successfully went offline

### Events You Can Emit (to server)

#### For Drivers:
```dart
// Go online
socketService.emit('driver:online', {});

// Go offline
socketService.emit('driver:offline', {});

// Update location
socketService.emit('location:update', {
  'lat': 30.0444,
  'lng': 31.2357,
  'bearing': 45.0,
  'speed': 60.0,
  'timestamp': DateTime.now().millisecondsSinceEpoch,
});

// Accept ride
socketService.emit('ride:accept', {
  'rideId': 'ride-uuid-here',
});

// Update ride status
socketService.emit('ride:status', {
  'ride_id': 'ride-uuid',
  'status': 'arrived', // 'arrived', 'started', 'completed'
});
```

#### For Customers:
```dart
// Subscribe to ride updates
socketService.emit('customer:subscribe:ride', {
  'ride_id': 'ride-uuid-here',
});

// Unsubscribe from ride updates
socketService.emit('customer:unsubscribe:ride', {
  'ride_id': 'ride-uuid-here',
});
```

---

## 🔧 Troubleshooting

### Connection Issues

**1. Authentication Error**
```
Error: Authentication error: No token provided
```
**Solution:**
- Verify token is passed in `auth.token`
- Check token hasn't expired
- Re-login to get fresh token

**2. Cannot Connect**
```
Connection timeout / Connection refused
```
**Solution:**
- Check internet connection
- Verify socket URL from config endpoint
- Ensure path is `/socket.io/`
- Check if server is running: `curl https://smartline-it.com/api/realtime/health`

**3. Immediate Disconnect**
```
Connected then immediately disconnected
```
**Solution:**
- Usually authentication issue
- Verify token format (should be JWT)
- Check server logs for details

**4. Events Not Received**
```
Socket connected but no events coming through
```
**Solution:**
- Verify you're listening to correct event names
- Check if you're subscribed to the right rooms
- Ensure you're emitting the right events to join rooms

---

## 🧪 Testing

### Test Connection

```dart
void testSocketConnection() async {
  final socketService = SocketService();
  
  // Use test token (development only)
  await socketService.connect('test-customer-token');
  
  socketService.on('connect', (_) {
    print('✅ Connected successfully');
  });
  
  socketService.on('error', (error) {
    print('❌ Error: $error');
  });
}
```

### Check Connection Status

```dart
print('Connected: ${socketService.isConnected}');
print('Socket ID: ${socketService.socketId}');
```

---

## 📝 Best Practices

1. **Store token securely** - Use `flutter_secure_storage`
2. **Reconnect automatically** - Socket.IO handles this, but monitor connection state
3. **Clean up listeners** - Remove listeners in `dispose()`
4. **Handle errors gracefully** - Show user-friendly messages
5. **Get socket URL from config** - Don't hardcode URLs
6. **Monitor connection state** - Show connection status to user
7. **Emit events only when connected** - Check `isConnected` before emitting

---

## 🔗 Related Endpoints

- **Config:** `GET /api/customer/config/core` - Get socket URL
- **Health Check:** `GET /api/realtime/health` - Check server status
- **Auth Verify:** `GET /api/auth/verify` - Verify token validity

---

## 📚 Additional Resources

- [Socket.IO Client Documentation](https://pub.dev/packages/socket_io_client)
- [Flutter Secure Storage](https://pub.dev/packages/flutter_secure_storage)
- Server logs: `/var/www/laravel/smartlinevps/realtime-service/logs/pm2-out.log`

---

**Need Help?** Check server logs or contact the development team.
