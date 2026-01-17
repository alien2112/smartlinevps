# Complete Flutter Socket Events Reference

## 📋 Overview

This document lists **ALL** socket events that should be implemented in the Flutter app for both:
1. **Node.js Socket.IO Service** (`https://smartline-it.com/socket.io/`) - Real-time ride matching, location tracking
2. **Laravel Reverb** (`https://smartline-it.com/app`) - Laravel broadcasting channels

---

## 🔌 Node.js Socket.IO Events

### **Events You Can EMIT (Client → Server)**

#### For Drivers:
```dart
// 1. Go online
socket.emit('driver:online', {
  'latitude': 30.0444,
  'longitude': 31.2357,
  'zoneId': 'zone-uuid',
  'category': 'vehicle-category-id'
});

// 2. Go offline
socket.emit('driver:offline', {});

// 3. Update location (high frequency - send every 1-5 seconds)
socket.emit('driver:location', {
  'latitude': 30.0444,
  'longitude': 31.2357,
  'speed': 60.0,           // Optional: km/h
  'heading': 45.0,         // Optional: degrees (0-360)
  'accuracy': 10.0,        // Optional: meters
  'zoneId': 'zone-uuid',   // Optional
  'category': 'category-id' // Optional
});

// 4. Accept ride request
socket.emit('driver:accept:ride', {
  'rideId': 'ride-uuid-here',
  'trace_id': 'optional-trace-id' // Optional
});
```

#### For Customers:
```dart
// 1. Subscribe to ride updates
socket.emit('customer:subscribe:ride', {
  'rideId': 'ride-uuid-here'
}, (ack) {
  // Acknowledgment callback
  if (ack['success']) {
    print('Subscribed to ride updates');
  }
});

// 2. Unsubscribe from ride updates
socket.emit('customer:unsubscribe:ride', {
  'rideId': 'ride-uuid-here'
});
```

#### For Both:
```dart
// Heartbeat/ping
socket.emit('ping');
```

---

### **Events You Can LISTEN TO (Server → Client)**

#### For Drivers:

##### Ride Notifications:
```dart
// New ride request available (most important for drivers)
socket.on('ride:new', (data) {
  // data contains:
  // - rideId, tripId
  // - customerId
  // - pickupLocation: {latitude, longitude}
  // - destinationLocation: {latitude, longitude}
  // - estimatedFare
  // - distance (km)
  // - expiresAt (timestamp)
  // - categoryLevel
  // - isTravel (boolean)
  // - fixedPrice (if travel)
  // - travelDate, travelPassengers, etc. (if travel)
});

// Ride acceptance successful
socket.on('ride:accept:success', (data) {
  // data: {rideId, tripId, status: 'accepted', otp, trip, message, timestamp}
  // CRITICAL: This confirms your acceptance was successful
});

// Ride acceptance failed
socket.on('ride:accept:failed', (data) {
  // data: {rideId, message}
  // Reasons: 'Ride no longer available', 'Another driver is accepting', etc.
});

// Ride was taken by another driver
socket.on('ride:taken', (data) {
  // data: {rideId, message}
  // You were notified but another driver accepted first
});

// No drivers available for customer
socket.on('ride:no_drivers', (data) {
  // data: {rideId, message}
  // Customer's ride request timed out (no drivers accepted)
});
```

##### Ride Status Updates:
```dart
// Ride has started (OTP verified)
socket.on('ride:started', (data) {
  // data: {rideId, tripId, status: 'ongoing', message, timestamp}
  // CRITICAL: Trip is now ongoing, start navigation
});

// Ride completed
socket.on('ride:completed', (data) {
  // data: {rideId, finalFare, message}
});

// Ride cancelled
socket.on('ride:cancelled', (data) {
  // data: {rideId, cancelledBy: 'driver'|'customer', message}
});

// Driver arrival confirmed
socket.on('trip:arrival:confirmed', (data) {
  // data: {rideId, message, timestamp}
  // Confirmation that you've arrived at pickup
});
```

##### Payment & Other:
```dart
// Payment completed
socket.on('payment:completed', (data) {
  // data: {rideId, amount, message}
});

// Lost item notifications
socket.on('lost_item:new', (data) {
  // data: {id, tripId, customerId, driverId, itemDescription, ...}
});

socket.on('lost_item:updated', (data) {
  // data: {id, status, driverResponse, ...}
});

// Safety alert received
socket.on('safety_alert:received', (data) {
  // data: {safety_alert_id, trip_request_id, alert_type, ...}
});
```

##### Driver Status:
```dart
// Successfully went online
socket.on('driver:online:success', (data) {
  // data: {message, trace_id}
});

// Successfully went offline
socket.on('driver:offline:success', (data) {
  // data: {message, trace_id}
});
```

##### Location Updates:
```dart
// Real-time driver location (for customers in ride)
socket.on('driver:location:update', (data) {
  // data: {lat, lng, bearing, speed, timestamp}
  // Only received when subscribed to ride room
});
```

#### For Customers:

##### Ride Status Updates:
```dart
// Driver assigned to your ride
socket.on('ride:driver_assigned', (data) {
  // data: {rideId, driver: {...}, status: 'accepted', message, timestamp}
  // CRITICAL: A driver has accepted your ride
});

// Ride has started (OTP verified)
socket.on('ride:started', (data) {
  // data: {rideId, status: 'ongoing', message, timestamp}
});

// Driver arrived at pickup
socket.on('ride:driver_arrived', (data) {
  // data: {rideId, message, arrivedAt, timestamp}
});

// Ride completed
socket.on('ride:completed', (data) {
  // data: {rideId, finalFare, message}
});

// Ride cancelled
socket.on('ride:cancelled', (data) {
  // data: {rideId, cancelledBy: 'driver'|'customer', message}
});

// Ride request timed out (no drivers accepted)
socket.on('ride:timeout', (data) {
  // data: {rideId, message}
});
```

##### Payment & Other:
```dart
// Payment completed
socket.on('payment:completed', (data) {
  // data: {rideId, amount, message}
});

// Lost item created (confirmation)
socket.on('lost_item:created', (data) {
  // data: {id, message, ...}
});

socket.on('lost_item:updated', (data) {
  // data: {id, status, driverResponse, message, ...}
});

// Safety alert sent confirmation
socket.on('safety_alert:sent', (data) {
  // data: {safety_alert_id, trip_request_id, status: 'sent', ...}
});
```

##### Location Updates:
```dart
// Real-time driver location (when subscribed to ride)
socket.on('driver:location:update', (data) {
  // data: {lat, lng, bearing, speed, timestamp}
  // Update map with driver's current location
});
```

#### For Both:

##### System Events:
```dart
// Heartbeat response
socket.on('pong', (data) {
  // data: {timestamp}
});

// Error events
socket.on('error', (data) {
  // data: {message, trace_id}
  // Handle connection/auth errors
});

// Connection events (built-in Socket.IO)
socket.on('connect', () {
  print('Connected to Socket.IO');
});

socket.on('disconnect', (reason) {
  print('Disconnected: $reason');
});

socket.on('connect_error', (error) {
  print('Connection error: $error');
});

socket.on('reconnect', (attemptNumber) {
  print('Reconnected after $attemptNumber attempts');
});
```

---

## 📡 Laravel Reverb Events (Broadcasting Channels)

### **Channels to Subscribe To**

#### For Customers:

```dart
// 1. Customer ride chat
echo.private('customer-ride-chat.{rideId}')
    .listen('MessageSent', (e) {
      // Chat message received
    });

// 2. General ride chat
echo.private('ride-chat.{rideId}')
    .listen('MessageSent', (e) {
      // Chat message received
    });

// 3. Driver trip accepted
echo.private('driver-trip-accepted.{tripId}')
    .listen('DriverTripAccepted', (e) {
      // Driver accepted your trip
    });

// 4. Driver trip started
echo.private('driver-trip-started.{tripId}')
    .listen('DriverTripStarted', (e) {
      // Trip has started
    });

// 5. Driver trip cancelled
echo.private('driver-trip-cancelled.{tripId}')
    .listen('DriverTripCancelled', (e) {
      // Driver cancelled trip
    });

// 6. Driver trip completed
echo.private('driver-trip-completed.{tripId}')
    .listen('DriverTripCompleted', (e) {
      // Trip completed
    });

// 7. Driver payment received
echo.private('driver-payment-received.{tripId}')
    .listen('DriverPaymentReceived', (e) {
      // Payment received notification
    });

// 8. Customer trip cancelled (after ongoing)
echo.private('customer-trip-cancelled-after-ongoing.{tripId}')
    .listen('CustomerTripCancelledAfterOngoing', (e) {
      // Customer cancelled after trip started
    });

// 9. Customer trip cancelled
echo.private('customer-trip-cancelled.{tripId}.{userId}')
    .listen('CustomerTripCancelled', (e) {
      // Customer cancelled trip
    });

// 10. Customer coupon applied
echo.private('customer-coupon-applied.{tripId}')
    .listen('CustomerCouponApplied', (e) {
      // Coupon applied to trip
    });

// 11. Customer coupon removed
echo.private('customer-coupon-removed.{tripId}')
    .listen('CustomerCouponRemoved', (e) {
      // Coupon removed from trip
    });

// 12. Customer trip request
echo.private('customer-trip-request.{tripId}')
    .listen('CustomerTripRequest', (e) {
      // Trip request created
    });

// 13. Customer trip payment successful
echo.private('customer-trip-payment-successful.{tripId}')
    .listen('CustomerTripPaymentSuccessful', (e) {
      // Payment successful
    });
```

#### For Drivers:

```dart
// 1. Driver ride chat
echo.private('driver-ride-chat.{rideId}')
    .listen('MessageSent', (e) {
      // Chat message received
    });

// 2. Another driver accepted trip
echo.private('another-driver-trip-accepted.{tripId}.{userId}')
    .listen('AnotherDriverTripAccepted', (e) {
      // Another driver accepted a trip you were notified about
    });

// 3. Customer trip cancelled after ongoing
echo.private('customer-trip-cancelled-after-ongoing.{tripId}')
    .listen('CustomerTripCancelledAfterOngoing', (e) {
      // Customer cancelled after trip started
    });

// 4. Customer trip cancelled
echo.private('customer-trip-cancelled.{tripId}.{userId}')
    .listen('CustomerTripCancelled', (e) {
      // Customer cancelled trip
    });
```

#### For Both:

```dart
// 1. User-specific channel
echo.private('App.Models.User.{userId}')
    .listen('UserUpdated', (e) {
      // User profile updated
    });

// 2. Ride request channel
echo.private('ride-request.{rideId}')
    .listen('RideRequestUpdated', (e) {
      // Ride request status changed
    });

// 3. General message channel
echo.channel('message')
    .listen('MessageSent', (e) {
      // General message notification
    });

// 4. Driver location storage (public)
echo.channel('store-driver-last-location')
    .listen('DriverLocationStored', (e) {
      // Driver location stored
    });
```

---

## 📝 Complete Event List Summary

### Node.js Socket.IO Events

| Event | Direction | User Type | Purpose |
|-------|-----------|-----------|---------|
| `driver:online` | Emit | Driver | Go online |
| `driver:offline` | Emit | Driver | Go offline |
| `driver:location` | Emit | Driver | Update location |
| `driver:accept:ride` | Emit | Driver | Accept ride request |
| `customer:subscribe:ride` | Emit | Customer | Subscribe to ride updates |
| `customer:unsubscribe:ride` | Emit | Customer | Unsubscribe from ride |
| `ping` | Emit | Both | Heartbeat |
| `ride:new` | Listen | Driver | New ride available |
| `ride:accept:success` | Listen | Driver | Acceptance confirmed |
| `ride:accept:failed` | Listen | Driver | Acceptance failed |
| `ride:taken` | Listen | Driver | Taken by another driver |
| `ride:driver_assigned` | Listen | Customer | Driver assigned |
| `ride:started` | Listen | Both | Trip started |
| `ride:completed` | Listen | Both | Trip completed |
| `ride:cancelled` | Listen | Both | Trip cancelled |
| `ride:timeout` | Listen | Customer | No drivers accepted |
| `ride:no_drivers` | Listen | Driver | Customer's request timed out |
| `ride:driver_arrived` | Listen | Customer | Driver at pickup |
| `trip:arrival:confirmed` | Listen | Driver | Arrival confirmed |
| `driver:location:update` | Listen | Customer | Real-time driver location |
| `driver:online:success` | Listen | Driver | Online status confirmed |
| `driver:offline:success` | Listen | Driver | Offline status confirmed |
| `payment:completed` | Listen | Both | Payment processed |
| `lost_item:new` | Listen | Driver | New lost item report |
| `lost_item:created` | Listen | Customer | Lost item report created |
| `lost_item:updated` | Listen | Both | Lost item status updated |
| `safety_alert:new` | Listen | Admin | New safety alert |
| `safety_alert:sent` | Listen | Customer | Safety alert sent |
| `safety_alert:received` | Listen | Driver | Safety alert received |
| `pong` | Listen | Both | Heartbeat response |
| `error` | Listen | Both | Error occurred |
| `connect` | Listen | Both | Connected (built-in) |
| `disconnect` | Listen | Both | Disconnected (built-in) |

### Laravel Reverb Channels

| Channel | Type | User Type | Events |
|--------|------|-----------|--------|
| `customer-ride-chat.{id}` | Private | Customer | `MessageSent` |
| `ride-chat.{id}` | Private | Both | `MessageSent` |
| `driver-ride-chat.{id}` | Private | Driver | `MessageSent` |
| `driver-trip-accepted.{id}` | Private | Customer | `DriverTripAccepted` |
| `driver-trip-started.{id}` | Private | Customer | `DriverTripStarted` |
| `driver-trip-cancelled.{id}` | Private | Customer | `DriverTripCancelled` |
| `driver-trip-completed.{id}` | Private | Customer | `DriverTripCompleted` |
| `driver-payment-received.{id}` | Private | Customer | `DriverPaymentReceived` |
| `another-driver-trip-accepted.{id}.{userId}` | Private | Driver | `AnotherDriverTripAccepted` |
| `customer-trip-cancelled-after-ongoing.{id}` | Private | Driver | `CustomerTripCancelledAfterOngoing` |
| `customer-trip-cancelled.{id}.{userId}` | Private | Driver | `CustomerTripCancelled` |
| `customer-coupon-applied.{id}` | Private | Driver | `CustomerCouponApplied` |
| `customer-coupon-removed.{id}` | Private | Driver | `CustomerCouponRemoved` |
| `customer-trip-request.{id}` | Private | Driver | `CustomerTripRequest` |
| `customer-trip-payment-successful.{id}` | Private | Driver | `CustomerTripPaymentSuccessful` |
| `App.Models.User.{id}` | Private | Both | `UserUpdated` |
| `ride-request.{id}` | Private | Both | `RideRequestUpdated` |
| `message` | Public | Both | `MessageSent` |
| `store-driver-last-location` | Public | Both | `DriverLocationStored` |

---

## 💻 Flutter Implementation Example

### Complete Socket Service

```dart
import 'package:socket_io_client/socket_io_client.dart' as IO;
import 'package:laravel_echo/laravel_echo.dart';
import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';

class SocketService {
  IO.Socket? socketIO;
  LaravelEcho? echo;
  final ConfigService config = ConfigService();

  // Initialize both socket services
  Future<void> initialize(String token) async {
    await _connectSocketIO(token);
    await _connectReverb(token);
  }

  // Connect to Node.js Socket.IO
  Future<void> _connectSocketIO(String token) async {
    socketIO = IO.io(
      config.socketIOUrl,
      IO.OptionBuilder()
        .setPath(config.socketIOPath)
        .setTransports(['websocket', 'polling'])
        .setAuth({'token': token})
        .enableAutoConnect()
        .enableReconnection()
        .build(),
    );

    _setupSocketIOListeners();
  }

  // Connect to Laravel Reverb
  Future<void> _connectReverb(String token) async {
    echo = LaravelEcho(
      PusherChannelsFlutter.getInstance(),
      options: PusherChannelsOptions(
        host: config.webSocketUrl,
        wsPort: int.parse(config.webSocketPort),
        wssPort: int.parse(config.webSocketPort),
        encrypted: config.websocketScheme == 'https',
        authEndpoint: 'https://${config.webSocketUrl}/broadcasting/auth',
        auth: {
          'headers': {
            'Authorization': 'Bearer $token',
            'Accept': 'application/json',
          }
        },
      ),
    );

    await echo!.connect();
    _setupReverbListeners();
  }

  // Setup Socket.IO listeners
  void _setupSocketIOListeners() {
    // Connection events
    socketIO!.onConnect((_) {
      print('✅ Socket.IO connected');
    });

    socketIO!.onDisconnect((reason) {
      print('⚠️ Socket.IO disconnected: $reason');
    });

    // DRIVER EVENTS
    if (userType == 'driver') {
      _setupDriverSocketIOListeners();
    }

    // CUSTOMER EVENTS
    if (userType == 'customer') {
      _setupCustomerSocketIOListeners();
    }
  }

  void _setupDriverSocketIOListeners() {
    // New ride available
    socketIO!.on('ride:new', (data) {
      print('🚗 New ride: $data');
      // Show ride notification to driver
    });

    // Ride acceptance
    socketIO!.on('ride:accept:success', (data) {
      print('✅ Ride accepted: $data');
      // Navigate to ride details
    });

    socketIO!.on('ride:accept:failed', (data) {
      print('❌ Acceptance failed: ${data['message']}');
      // Show error message
    });

    socketIO!.on('ride:taken', (data) {
      print('⚠️ Ride taken by another driver');
      // Remove from available rides
    });

    // Ride status
    socketIO!.on('ride:started', (data) {
      print('🚀 Ride started: $data');
      // Start navigation
    });

    socketIO!.on('ride:completed', (data) {
      print('✅ Ride completed: $data');
      // Show completion screen
    });

    socketIO!.on('ride:cancelled', (data) {
      print('❌ Ride cancelled: $data');
      // Handle cancellation
    });

    // Driver status
    socketIO!.on('driver:online:success', (data) {
      print('✅ Online: $data');
    });

    socketIO!.on('driver:offline:success', (data) {
      print('✅ Offline: $data');
    });

    // Payment
    socketIO!.on('payment:completed', (data) {
      print('💰 Payment: $data');
    });

    // Lost items
    socketIO!.on('lost_item:new', (data) {
      print('📦 Lost item: $data');
    });

    // Safety alerts
    socketIO!.on('safety_alert:received', (data) {
      print('🚨 Safety alert: $data');
    });
  }

  void _setupCustomerSocketIOListeners() {
    // Driver assigned
    socketIO!.on('ride:driver_assigned', (data) {
      print('👤 Driver assigned: $data');
      // Show driver info
    });

    // Ride status
    socketIO!.on('ride:started', (data) {
      print('🚀 Ride started: $data');
    });

    socketIO!.on('ride:completed', (data) {
      print('✅ Ride completed: $data');
    });

    socketIO!.on('ride:cancelled', (data) {
      print('❌ Ride cancelled: $data');
    });

    socketIO!.on('ride:timeout', (data) {
      print('⏱️ Ride timeout: $data');
    });

    // Driver location
    socketIO!.on('driver:location:update', (data) {
      print('📍 Driver location: $data');
      // Update map marker
    });

    // Driver arrived
    socketIO!.on('ride:driver_arrived', (data) {
      print('🚗 Driver arrived: $data');
    });

    // Payment
    socketIO!.on('payment:completed', (data) {
      print('💰 Payment: $data');
    });

    // Safety alert
    socketIO!.on('safety_alert:sent', (data) {
      print('🚨 Safety alert sent: $data');
    });
  }

  // Setup Reverb listeners
  void _setupReverbListeners() {
    if (userType == 'driver') {
      _setupDriverReverbListeners();
    } else {
      _setupCustomerReverbListeners();
    }
  }

  void _setupDriverReverbListeners() {
    // Chat
    echo!.private('driver-ride-chat.{rideId}')
        .listen('MessageSent', (e) {
          print('💬 Chat message: $e');
        });

    // Trip events
    echo!.private('customer-trip-cancelled.{tripId}.{userId}')
        .listen('CustomerTripCancelled', (e) {
          print('❌ Customer cancelled: $e');
        });
  }

  void _setupCustomerReverbListeners() {
    // Chat
    echo!.private('customer-ride-chat.{rideId}')
        .listen('MessageSent', (e) {
          print('💬 Chat message: $e');
        });

    // Trip events
    echo!.private('driver-trip-accepted.{tripId}')
        .listen('DriverTripAccepted', (e) {
          print('✅ Driver accepted: $e');
        });

    echo!.private('driver-trip-started.{tripId}')
        .listen('DriverTripStarted', (e) {
          print('🚀 Trip started: $e');
        });
  }

  // Driver actions
  void goOnline(double lat, double lng, String zoneId) {
    socketIO!.emit('driver:online', {
      'latitude': lat,
      'longitude': lng,
      'zoneId': zoneId,
    });
  }

  void goOffline() {
    socketIO!.emit('driver:offline', {});
  }

  void updateLocation(double lat, double lng, {double? speed, double? heading}) {
    socketIO!.emit('driver:location', {
      'latitude': lat,
      'longitude': lng,
      'speed': speed,
      'heading': heading,
    });
  }

  void acceptRide(String rideId) {
    socketIO!.emit('driver:accept:ride', {
      'rideId': rideId,
    });
  }

  // Customer actions
  void subscribeToRide(String rideId) {
    socketIO!.emit('customer:subscribe:ride', {
      'rideId': rideId,
    }, (ack) {
      if (ack['success']) {
        print('Subscribed to ride: $rideId');
      }
    });
  }

  void unsubscribeFromRide(String rideId) {
    socketIO!.emit('customer:unsubscribe:ride', {
      'rideId': rideId,
    });
  }

  // Cleanup
  void disconnect() {
    socketIO?.disconnect();
    echo?.disconnect();
  }
}
```

---

## 🎯 Priority Events (Must Implement)

### For Drivers (High Priority):
1. ✅ `ride:new` - **CRITICAL** - New ride requests
2. ✅ `ride:accept:success` - **CRITICAL** - Acceptance confirmation
3. ✅ `ride:started` - **CRITICAL** - Trip started
4. ✅ `driver:location` - **CRITICAL** - Send location updates
5. ✅ `driver:online` / `driver:offline` - Go online/offline
6. ✅ `ride:accept:failed` - Handle failures
7. ✅ `ride:completed` - Trip completion
8. ✅ `payment:completed` - Payment received

### For Customers (High Priority):
1. ✅ `ride:driver_assigned` - **CRITICAL** - Driver accepted
2. ✅ `ride:started` - **CRITICAL** - Trip started
3. ✅ `driver:location:update` - **CRITICAL** - Real-time tracking
4. ✅ `ride:driver_arrived` - Driver at pickup
5. ✅ `ride:completed` - Trip completion
6. ✅ `ride:cancelled` - Handle cancellations
7. ✅ `customer:subscribe:ride` - Subscribe to updates
8. ✅ `payment:completed` - Payment confirmation

---

## 📚 Additional Resources

- Socket.IO Client: https://pub.dev/packages/socket_io_client
- Laravel Echo: https://pub.dev/packages/laravel_echo
- Pusher Channels: https://pub.dev/packages/pusher_channels_flutter

---

**Total Events:**
- **Node.js Socket.IO**: 30+ events
- **Laravel Reverb**: 15+ channels with multiple events

**All events are now documented and ready for Flutter implementation!** ✅
