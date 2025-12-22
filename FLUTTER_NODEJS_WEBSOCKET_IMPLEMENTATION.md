# Flutter Developer - Node.js WebSocket Implementation Guide

**For Flutter Developer**: This document shows what to add for real-time features using Node.js WebSocket.

**Note**: Keep all existing Laravel API calls. This is ADDITIONAL functionality.

---

## 📦 Step 1: Add Dependency

Add to `pubspec.yaml`:

```yaml
dependencies:
  socket_io_client: ^2.0.3  # Add this line
```

Run:
```bash
flutter pub get
```

---

## ⚙️ Step 2: Configuration

Create `lib/config/websocket_config.dart`:

```dart
class WebSocketConfig {
  // Development
  static const String nodeJsUrl = 'http://YOUR_SERVER_IP:3000';

  // Production (update when deploying)
  // static const String nodeJsUrl = 'https://realtime.yourdomain.com';
}
```

---

## 🔌 Step 3: Create WebSocket Service

Create `lib/services/websocket_service.dart`:

```dart
import 'package:socket_io_client/socket_io_client.dart' as IO;
import 'package:logger/logger.dart';
import '../config/websocket_config.dart';

class WebSocketService {
  static final WebSocketService _instance = WebSocketService._internal();
  factory WebSocketService() => _instance;
  WebSocketService._internal();

  IO.Socket? _socket;
  final _logger = Logger();
  bool _isConnected = false;
  final Map<String, List<Function(dynamic)>> _listeners = {};

  bool get isConnected => _isConnected;

  /// Connect to WebSocket server
  /// Call this after user logs in and you have the JWT token
  Future<void> connect(String jwtToken) async {
    if (_socket != null && _isConnected) {
      _logger.w('Already connected');
      return;
    }

    _logger.i('Connecting to WebSocket...');

    _socket = IO.io(
      WebSocketConfig.nodeJsUrl,
      IO.OptionBuilder()
          .setTransports(['websocket', 'polling'])
          .setAuth({'token': jwtToken})
          .enableAutoConnect()
          .setReconnectionDelay(1000)
          .setReconnectionDelayMax(5000)
          .setReconnectionAttempts(5)
          .build(),
    );

    _setupListeners();
  }

  void _setupListeners() {
    // Connection events
    _socket?.onConnect((_) {
      _logger.i('✅ WebSocket connected');
      _isConnected = true;
      _emit('ws:connected', null);
    });

    _socket?.onDisconnect((reason) {
      _logger.w('❌ Disconnected: $reason');
      _isConnected = false;
      _emit('ws:disconnected', reason);
    });

    _socket?.onConnectError((error) {
      _logger.e('Connection error: $error');
      _emit('ws:error', error);
    });

    // Driver events
    _socket?.on('ride:new', (data) {
      _logger.i('🔔 New ride request');
      _emit('ride:new', data);
    });

    _socket?.on('ride:accept:success', (data) {
      _logger.i('✅ Ride accepted');
      _emit('ride:accept:success', data);
    });

    _socket?.on('ride:accept:failed', (data) {
      _logger.w('❌ Ride accept failed');
      _emit('ride:accept:failed', data);
    });

    _socket?.on('ride:taken', (data) {
      _logger.w('⚠️ Ride taken by another driver');
      _emit('ride:taken', data);
    });

    // Customer events
    _socket?.on('driver:location:update', (data) {
      _emit('driver:location:update', data);
    });

    _socket?.on('ride:driver_assigned', (data) {
      _logger.i('✅ Driver assigned');
      _emit('ride:driver_assigned', data);
    });

    _socket?.on('ride:no_drivers', (data) {
      _logger.w('⚠️ No drivers available');
      _emit('ride:no_drivers', data);
    });

    // Common events
    _socket?.on('ride:started', (data) {
      _logger.i('🚗 Ride started');
      _emit('ride:started', data);
    });

    _socket?.on('ride:completed', (data) {
      _logger.i('✅ Ride completed');
      _emit('ride:completed', data);
    });

    _socket?.on('ride:cancelled', (data) {
      _logger.w('❌ Ride cancelled');
      _emit('ride:cancelled', data);
    });
  }

  // ==========================================
  // DRIVER ACTIONS - Use these in driver app
  // ==========================================

  /// Call when driver toggles "Go Online"
  void goOnline({
    required double latitude,
    required double longitude,
    required String vehicleCategoryId,
    required String vehicleId,
    required String name,
  }) {
    _socket?.emit('driver:online', {
      'location': {
        'latitude': latitude,
        'longitude': longitude,
      },
      'vehicle_category_id': vehicleCategoryId,
      'vehicle_id': vehicleId,
      'name': name,
    });
  }

  /// Call when driver toggles "Go Offline"
  void goOffline() {
    _socket?.emit('driver:offline');
  }

  /// Call every 2-3 seconds while driver is online
  void updateLocation({
    required double latitude,
    required double longitude,
    double? speed,
    double? heading,
    double? accuracy,
  }) {
    _socket?.emit('driver:location', {
      'latitude': latitude,
      'longitude': longitude,
      'speed': speed ?? 0,
      'heading': heading ?? 0,
      'accuracy': accuracy ?? 10,
    });
  }

  /// Call when driver clicks "Accept" on a ride request
  void acceptRide(String rideId) {
    _socket?.emit('driver:accept:ride', {
      'rideId': rideId,
    });
  }

  // ==========================================
  // CUSTOMER ACTIONS - Use these in customer app
  // ==========================================

  /// Call after customer creates a ride to start receiving updates
  void subscribeToRide(String rideId) {
    _socket?.emit('customer:subscribe:ride', {
      'rideId': rideId,
    });
  }

  /// Call when customer leaves ride tracking screen
  void unsubscribeFromRide(String rideId) {
    _socket?.emit('customer:unsubscribe:ride', {
      'rideId': rideId,
    });
  }

  // ==========================================
  // EVENT LISTENERS - Use to react to events
  // ==========================================

  /// Listen to events
  /// Example: websocket.on('ride:new', (data) { print(data); });
  void on(String event, Function(dynamic) callback) {
    if (!_listeners.containsKey(event)) {
      _listeners[event] = [];
    }
    _listeners[event]!.add(callback);
  }

  /// Stop listening to an event
  void off(String event, [Function(dynamic)? callback]) {
    if (callback != null) {
      _listeners[event]?.remove(callback);
    } else {
      _listeners.remove(event);
    }
  }

  void _emit(String event, dynamic data) {
    if (_listeners.containsKey(event)) {
      for (var callback in _listeners[event]!) {
        callback(data);
      }
    }
  }

  /// Disconnect from WebSocket
  /// Call when user logs out
  void disconnect() {
    _socket?.disconnect();
    _socket = null;
    _isConnected = false;
    _listeners.clear();
  }
}
```

---

## 📱 Step 4: Usage in Driver App

### 4.1 Connect on Login

```dart
// After successful login in your existing login code
final response = await apiService.login(phone, password);
final token = response.data['token'];

// ADD THIS: Connect to WebSocket
await WebSocketService().connect(token);
```

### 4.2 Driver Home Screen - Go Online/Offline

```dart
import '../../services/websocket_service.dart';
import 'package:geolocator/geolocator.dart';

class DriverHomeScreen extends StatefulWidget {
  @override
  State<DriverHomeScreen> createState() => _DriverHomeScreenState();
}

class _DriverHomeScreenState extends State<DriverHomeScreen> {
  final _ws = WebSocketService();
  bool _isOnline = false;

  @override
  void initState() {
    super.initState();
    _setupListeners();
  }

  void _setupListeners() {
    // Listen for new rides
    _ws.on('ride:new', (data) {
      // Show notification
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('🔔 New Ride Request!')),
      );

      // Reload pending rides list
      _loadPendingRides();
    });

    // Listen for acceptance result
    _ws.on('ride:accept:success', (data) {
      Navigator.pushNamed(context, '/driver/ride-active', arguments: data);
    });

    _ws.on('ride:accept:failed', (data) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('❌ ${data['message']}')),
      );
    });
  }

  Future<void> _toggleOnline() async {
    if (!_isOnline) {
      // Get current location
      final position = await Geolocator.getCurrentPosition();

      // Go online via WebSocket
      _ws.goOnline(
        latitude: position.latitude,
        longitude: position.longitude,
        vehicleCategoryId: 'get-from-storage', // Your vehicle category ID
        vehicleId: 'get-from-storage',         // Your vehicle ID
        name: 'get-from-storage',              // Driver name
      );

      // Start sending location updates every 3 seconds
      _startLocationUpdates();

      setState(() => _isOnline = true);
    } else {
      // Go offline
      _ws.goOffline();
      _stopLocationUpdates();
      setState(() => _isOnline = false);
    }
  }

  Timer? _locationTimer;

  void _startLocationUpdates() {
    _locationTimer = Timer.periodic(Duration(seconds: 3), (timer) async {
      final position = await Geolocator.getCurrentPosition();
      _ws.updateLocation(
        latitude: position.latitude,
        longitude: position.longitude,
        speed: position.speed,
        heading: position.heading,
        accuracy: position.accuracy,
      );
    });
  }

  void _stopLocationUpdates() {
    _locationTimer?.cancel();
  }

  void _acceptRide(String rideId) {
    // Accept ride via WebSocket (instant)
    _ws.acceptRide(rideId);
  }

  @override
  void dispose() {
    _ws.off('ride:new');
    _ws.off('ride:accept:success');
    _ws.off('ride:accept:failed');
    _stopLocationUpdates();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Driver Home')),
      body: Column(
        children: [
          // Online/Offline Toggle
          SwitchListTile(
            title: Text(_isOnline ? 'Online' : 'Offline'),
            value: _isOnline,
            onChanged: (_) => _toggleOnline(),
          ),

          // Pending rides list (from Laravel API - keep existing code)
          // When driver clicks "Accept", call: _acceptRide(ride.id)
        ],
      ),
    );
  }
}
```

---

## 👥 Step 5: Usage in Customer App

### 5.1 Customer Ride Tracking Screen

```dart
import '../../services/websocket_service.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';

class RideTrackingScreen extends StatefulWidget {
  final String rideId;

  const RideTrackingScreen({required this.rideId});

  @override
  State<RideTrackingScreen> createState() => _RideTrackingScreenState();
}

class _RideTrackingScreenState extends State<RideTrackingScreen> {
  final _ws = WebSocketService();
  GoogleMapController? _mapController;
  LatLng? _driverLocation;
  String _rideStatus = 'pending';

  @override
  void initState() {
    super.initState();
    _setupListeners();

    // Subscribe to this ride's updates
    _ws.subscribeToRide(widget.rideId);
  }

  void _setupListeners() {
    // Listen for driver location updates (every 2-3 seconds)
    _ws.on('driver:location:update', (data) {
      setState(() {
        _driverLocation = LatLng(
          data['location']['latitude'],
          data['location']['longitude'],
        );
      });

      // Update map camera to show driver
      _updateCamera();
    });

    // Listen for driver assigned
    _ws.on('ride:driver_assigned', (data) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('✅ Driver assigned!')),
      );
      // Update UI with driver info
    });

    // Listen for ride started
    _ws.on('ride:started', (data) {
      setState(() => _rideStatus = 'started');
    });

    // Listen for ride completed
    _ws.on('ride:completed', (data) {
      Navigator.pushReplacementNamed(context, '/customer/ride-completed');
    });

    // Listen for no drivers available
    _ws.on('ride:no_drivers', (data) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('⚠️ No drivers available')),
      );
    });
  }

  void _updateCamera() {
    if (_mapController != null && _driverLocation != null) {
      _mapController!.animateCamera(
        CameraUpdate.newLatLng(_driverLocation!),
      );
    }
  }

  @override
  void dispose() {
    // Unsubscribe from ride updates
    _ws.unsubscribeFromRide(widget.rideId);

    // Remove listeners
    _ws.off('driver:location:update');
    _ws.off('ride:driver_assigned');
    _ws.off('ride:started');
    _ws.off('ride:completed');
    _ws.off('ride:no_drivers');

    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Tracking Ride')),
      body: Stack(
        children: [
          GoogleMap(
            initialCameraPosition: CameraPosition(
              target: LatLng(30.0444, 31.2357), // Pickup location
              zoom: 14,
            ),
            onMapCreated: (controller) => _mapController = controller,
            markers: {
              // Driver marker (updates in real-time)
              if (_driverLocation != null)
                Marker(
                  markerId: MarkerId('driver'),
                  position: _driverLocation!,
                  icon: BitmapDescriptor.defaultMarkerWithHue(
                    BitmapDescriptor.hueBlue,
                  ),
                ),
            },
          ),

          // Status at bottom
          Positioned(
            bottom: 0,
            left: 0,
            right: 0,
            child: Container(
              padding: EdgeInsets.all(16),
              color: Colors.white,
              child: Text(
                'Status: ${_rideStatus.toUpperCase()}',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
```

---

## 📋 Step 6: Event Reference

### Events to EMIT (Send to Server)

**Driver:**
```dart
// When going online
websocket.goOnline(latitude, longitude, vehicleCategoryId, vehicleId, name);

// When going offline
websocket.goOffline();

// Every 2-3 seconds while online
websocket.updateLocation(latitude, longitude, speed, heading, accuracy);

// When accepting a ride
websocket.acceptRide(rideId);
```

**Customer:**
```dart
// After creating a ride
websocket.subscribeToRide(rideId);

// When leaving tracking screen
websocket.unsubscribeFromRide(rideId);
```

### Events to LISTEN (Receive from Server)

**Driver:**
```dart
websocket.on('ride:new', (data) {});              // New ride available
websocket.on('ride:accept:success', (data) {});   // Ride accepted successfully
websocket.on('ride:accept:failed', (data) {});    // Failed to accept
websocket.on('ride:taken', (data) {});            // Another driver took it
websocket.on('ride:started', (data) {});          // Ride started
websocket.on('ride:completed', (data) {});        // Ride completed
websocket.on('ride:cancelled', (data) {});        // Ride cancelled
```

**Customer:**
```dart
websocket.on('driver:location:update', (data) {});  // Driver's location (every 2-3 sec)
websocket.on('ride:driver_assigned', (data) {});    // Driver assigned to your ride
websocket.on('ride:no_drivers', (data) {});         // No drivers available
websocket.on('ride:started', (data) {});            // Ride started
websocket.on('ride:completed', (data) {});          // Ride completed
websocket.on('ride:cancelled', (data) {});          // Ride cancelled
```

---

## 🔄 When to Use WebSocket vs Laravel API

### Use Laravel API (Keep existing code):
- ✅ Login/Register
- ✅ Create ride request
- ✅ Get ride history
- ✅ Update profile
- ✅ Get pending rides list
- ✅ Payments
- ✅ All CRUD operations

### Use WebSocket (NEW - Add this):
- ✅ Driver go online/offline
- ✅ Real-time location updates
- ✅ Accept ride (instant)
- ✅ Receive ride notifications
- ✅ Track driver location (live)
- ✅ Ride status updates (live)

---

## 🚀 Step 7: Initialize on App Start

In your `main.dart` or app initialization:

```dart
void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Your existing initialization
  await StorageService().init();
  ApiService().init();

  runApp(MyApp());
}

// After login:
final token = await StorageService().getToken();
if (token != null) {
  await WebSocketService().connect(token);
}

// On logout:
WebSocketService().disconnect();
await StorageService().deleteToken();
```

---

## 📝 Summary for Flutter Developer

**What to do:**

1. ✅ Add `socket_io_client` dependency
2. ✅ Copy `WebSocketService` class
3. ✅ Connect WebSocket after login: `WebSocketService().connect(token)`
4. ✅ **Driver App:**
   - Add online/offline toggle calling `goOnline()` / `goOffline()`
   - Start location updates timer calling `updateLocation()` every 3 seconds
   - Accept rides using `acceptRide(rideId)` instead of Laravel API
   - Listen for `ride:new` events to show notifications
5. ✅ **Customer App:**
   - After creating ride, call `subscribeToRide(rideId)`
   - Listen for `driver:location:update` to update map
   - Listen for `ride:driver_assigned` to show driver info
6. ✅ Disconnect on logout: `WebSocketService().disconnect()`

**What stays the same:**
- All existing Laravel API calls
- Login/register flows
- Profile management
- Payment processing
- Ride history

**What's new:**
- Real-time location tracking
- Instant ride notifications
- Live driver updates on map

---

## 🌐 Server URLs

Update in `lib/config/websocket_config.dart`:

**Development:**
```dart
static const String nodeJsUrl = 'http://YOUR_SERVER_IP:3000';
```

**Production:**
```dart
static const String nodeJsUrl = 'https://realtime.yourdomain.com';
// or
static const String nodeJsUrl = 'wss://realtime.yourdomain.com';
```

---

**That's it! Give this document to your Flutter developer. All WebSocket code is here.** ✅
