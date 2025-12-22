# Flutter Integration Guide - SmartLine

Complete guide for integrating your Flutter app with Laravel API and Node.js WebSocket service.

---

## 📦 Installation

Add these dependencies to your `pubspec.yaml`:

```yaml
dependencies:
  flutter:
    sdk: flutter

  # HTTP Client for Laravel API
  dio: ^5.4.0

  # WebSocket for Node.js
  socket_io_client: ^2.0.3

  # State Management (choose one)
  provider: ^6.1.1
  # OR
  riverpod: ^2.4.9
  # OR
  bloc: ^8.1.3

  # Storage
  shared_preferences: ^2.2.2
  flutter_secure_storage: ^9.0.0

  # Location
  geolocator: ^11.0.0
  permission_handler: ^11.2.0

  # Maps
  google_maps_flutter: ^2.5.3

  # Utils
  logger: ^2.0.2
```

Run:
```bash
flutter pub get
```

---

## 🏗️ Project Structure

```
lib/
├── main.dart
├── config/
│   ├── api_config.dart
│   └── websocket_config.dart
├── services/
│   ├── api_service.dart           # Laravel HTTP API
│   ├── websocket_service.dart     # Node.js WebSocket
│   ├── auth_service.dart
│   ├── location_service.dart
│   └── storage_service.dart
├── models/
│   ├── user.dart
│   ├── ride.dart
│   └── driver.dart
├── providers/
│   ├── auth_provider.dart
│   ├── ride_provider.dart
│   └── location_provider.dart
├── screens/
│   ├── driver/
│   │   ├── driver_home_screen.dart
│   │   ├── ride_in_progress_screen.dart
│   │   └── driver_earnings_screen.dart
│   └── customer/
│       ├── customer_home_screen.dart
│       ├── ride_tracking_screen.dart
│       └── ride_history_screen.dart
└── widgets/
    ├── map_widget.dart
    └── ride_card.dart
```

---

## ⚙️ Configuration

### `lib/config/api_config.dart`

```dart
class ApiConfig {
  // Development URLs
  static const String laravelBaseUrl = 'http://YOUR_SERVER_IP:8000/api';
  static const String nodeJsUrl = 'http://YOUR_SERVER_IP:3000';

  // Production URLs (uncomment for production)
  // static const String laravelBaseUrl = 'https://api.yourdomain.com/api';
  // static const String nodeJsUrl = 'https://realtime.yourdomain.com';

  static const int connectTimeout = 30000; // 30 seconds
  static const int receiveTimeout = 30000;
}
```

---

## 🔐 Storage Service

### `lib/services/storage_service.dart`

```dart
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class StorageService {
  static final StorageService _instance = StorageService._internal();
  factory StorageService() => _instance;
  StorageService._internal();

  final _secureStorage = const FlutterSecureStorage();
  SharedPreferences? _prefs;

  Future<void> init() async {
    _prefs = await SharedPreferences.getInstance();
  }

  // Token management (secure)
  Future<void> saveToken(String token) async {
    await _secureStorage.write(key: 'auth_token', value: token);
  }

  Future<String?> getToken() async {
    return await _secureStorage.read(key: 'auth_token');
  }

  Future<void> deleteToken() async {
    await _secureStorage.delete(key: 'auth_token');
  }

  // User data (regular storage)
  Future<void> saveUserId(String userId) async {
    await _prefs?.setString('user_id', userId);
  }

  Future<String?> getUserId() async {
    return _prefs?.getString('user_id');
  }

  Future<void> saveUserType(String userType) async {
    await _prefs?.setString('user_type', userType);
  }

  Future<String?> getUserType() async {
    return _prefs?.getString('user_type');
  }

  Future<void> saveZoneId(String zoneId) async {
    await _prefs?.setString('zone_id', zoneId);
  }

  Future<String?> getZoneId() async {
    return _prefs?.getString('zone_id');
  }

  Future<void> clear() async {
    await _secureStorage.deleteAll();
    await _prefs?.clear();
  }
}
```

---

## 🌐 Laravel API Service

### `lib/services/api_service.dart`

```dart
import 'package:dio/dio.dart';
import 'package:logger/logger.dart';
import '../config/api_config.dart';
import 'storage_service.dart';

class ApiService {
  static final ApiService _instance = ApiService._internal();
  factory ApiService() => _instance;
  ApiService._internal();

  late Dio _dio;
  final _storage = StorageService();
  final _logger = Logger();

  void init() {
    _dio = Dio(BaseOptions(
      baseUrl: ApiConfig.laravelBaseUrl,
      connectTimeout: const Duration(milliseconds: ApiConfig.connectTimeout),
      receiveTimeout: const Duration(milliseconds: ApiConfig.receiveTimeout),
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    ));

    // Request interceptor - Add auth token
    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await _storage.getToken();
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }

        _logger.d('Request: ${options.method} ${options.path}');
        return handler.next(options);
      },
      onResponse: (response, handler) {
        _logger.d('Response: ${response.statusCode} ${response.requestOptions.path}');
        return handler.next(response);
      },
      onError: (error, handler) {
        _logger.e('Error: ${error.response?.statusCode} ${error.message}');
        return handler.next(error);
      },
    ));
  }

  // Auth APIs
  Future<Response> login(String phone, String password) async {
    return await _dio.post('/customer/auth/login', data: {
      'phone': phone,
      'password': password,
    });
  }

  Future<Response> register(Map<String, dynamic> data) async {
    return await _dio.post('/customer/auth/register', data: data);
  }

  Future<Response> logout() async {
    return await _dio.post('/customer/auth/logout');
  }

  Future<Response> getProfile() async {
    return await _dio.get('/customer/profile');
  }

  // Ride APIs - Customer
  Future<Response> createRide(Map<String, dynamic> data) async {
    return await _dio.post('/customer/ride/request', data: data);
  }

  Future<Response> getRideDetails(String rideId) async {
    return await _dio.get('/customer/ride/$rideId');
  }

  Future<Response> getRideHistory({int limit = 20, int offset = 1}) async {
    return await _dio.get('/customer/ride/history', queryParameters: {
      'limit': limit,
      'offset': offset,
    });
  }

  Future<Response> cancelRide(String rideId, String reason) async {
    return await _dio.post('/customer/ride/cancel', data: {
      'trip_request_id': rideId,
      'reason': reason,
    });
  }

  Future<Response> rateDriver({
    required String tripRequestId,
    required int rating,
    String? comment,
  }) async {
    return await _dio.post('/customer/ride/rate', data: {
      'trip_request_id': tripRequestId,
      'rating': rating,
      'comment': comment,
    });
  }

  // Ride APIs - Driver
  Future<Response> getPendingRides({
    required String zoneId,
    int limit = 10,
    int offset = 1,
  }) async {
    return await _dio.get(
      '/driver/ride/pending-ride-list',
      queryParameters: {
        'limit': limit,
        'offset': offset,
      },
      options: Options(headers: {
        'zoneId': zoneId,
      }),
    );
  }

  Future<Response> updateRideStatus({
    required String tripRequestId,
    required String status, // 'accepted', 'started', 'completed'
  }) async {
    return await _dio.post('/driver/ride/status-update', data: {
      'trip_request_id': tripRequestId,
      'current_status': status,
    });
  }

  Future<Response> getDriverEarnings({
    String? startDate,
    String? endDate,
  }) async {
    return await _dio.get('/driver/earnings', queryParameters: {
      if (startDate != null) 'start_date': startDate,
      if (endDate != null) 'end_date': endDate,
    });
  }

  // Config
  Future<Response> getDriverConfig() async {
    return await _dio.get('/driver/config');
  }

  Future<Response> getCustomerConfig() async {
    return await _dio.get('/customer/config');
  }
}
```

---

## 🔌 WebSocket Service (Node.js)

### `lib/services/websocket_service.dart`

```dart
import 'package:socket_io_client/socket_io_client.dart' as IO;
import 'package:logger/logger.dart';
import '../config/api_config.dart';
import 'storage_service.dart';

class WebSocketService {
  static final WebSocketService _instance = WebSocketService._internal();
  factory WebSocketService() => _instance;
  WebSocketService._internal();

  IO.Socket? _socket;
  final _storage = StorageService();
  final _logger = Logger();
  bool _isConnected = false;

  // Event callbacks
  final Map<String, List<Function(dynamic)>> _listeners = {};

  bool get isConnected => _isConnected;

  Future<void> connect() async {
    if (_socket != null && _isConnected) {
      _logger.w('WebSocket already connected');
      return;
    }

    final token = await _storage.getToken();
    if (token == null) {
      _logger.e('No auth token, cannot connect to WebSocket');
      return;
    }

    _logger.i('Connecting to WebSocket: ${ApiConfig.nodeJsUrl}');

    _socket = IO.io(
      ApiConfig.nodeJsUrl,
      IO.OptionBuilder()
          .setTransports(['websocket', 'polling'])
          .setAuth({'token': token})
          .enableAutoConnect()
          .setReconnectionDelay(1000)
          .setReconnectionDelayMax(5000)
          .setReconnectionAttempts(5)
          .build(),
    );

    _setupSocketListeners();
  }

  void _setupSocketListeners() {
    _socket?.onConnect((_) {
      _logger.i('✅ WebSocket connected: ${_socket?.id}');
      _isConnected = true;
      _emit('ws:connected', null);
    });

    _socket?.onDisconnect((reason) {
      _logger.w('❌ WebSocket disconnected: $reason');
      _isConnected = false;
      _emit('ws:disconnected', reason);
    });

    _socket?.onConnectError((error) {
      _logger.e('WebSocket connection error: $error');
      _emit('ws:error', error);
    });

    _socket?.onError((error) {
      _logger.e('WebSocket error: $error');
      _emit('ws:error', error);
    });

    // Driver events
    _socket?.on('ride:new', (data) {
      _logger.i('🔔 New ride request: $data');
      _emit('ride:new', data);
    });

    _socket?.on('ride:accept:success', (data) {
      _logger.i('✅ Ride accepted successfully: $data');
      _emit('ride:accept:success', data);
    });

    _socket?.on('ride:accept:failed', (data) {
      _logger.w('❌ Ride accept failed: $data');
      _emit('ride:accept:failed', data);
    });

    _socket?.on('ride:taken', (data) {
      _logger.w('⚠️ Ride taken by another driver: $data');
      _emit('ride:taken', data);
    });

    _socket?.on('ride:started', (data) {
      _logger.i('🚗 Ride started: $data');
      _emit('ride:started', data);
    });

    _socket?.on('ride:completed', (data) {
      _logger.i('✅ Ride completed: $data');
      _emit('ride:completed', data);
    });

    _socket?.on('ride:cancelled', (data) {
      _logger.w('❌ Ride cancelled: $data');
      _emit('ride:cancelled', data);
    });

    // Customer events
    _socket?.on('driver:location:update', (data) {
      _emit('driver:location:update', data);
    });

    _socket?.on('ride:driver_assigned', (data) {
      _logger.i('✅ Driver assigned: $data');
      _emit('ride:driver_assigned', data);
    });

    _socket?.on('ride:no_drivers', (data) {
      _logger.w('⚠️ No drivers available: $data');
      _emit('ride:no_drivers', data);
    });

    _socket?.on('payment:completed', (data) {
      _logger.i('💳 Payment completed: $data');
      _emit('payment:completed', data);
    });
  }

  // Driver Actions
  void goOnline({
    required double latitude,
    required double longitude,
    required String vehicleCategoryId,
    required String vehicleId,
    required String name,
    String? availability,
  }) {
    _logger.i('Driver going online');
    _socket?.emit('driver:online', {
      'location': {
        'latitude': latitude,
        'longitude': longitude,
      },
      'vehicle_category_id': vehicleCategoryId,
      'vehicle_id': vehicleId,
      'name': name,
      'availability': availability ?? 'available',
    });
  }

  void goOffline() {
    _logger.i('Driver going offline');
    _socket?.emit('driver:offline');
  }

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

  void acceptRide(String rideId) {
    _logger.i('Driver accepting ride: $rideId');
    _socket?.emit('driver:accept:ride', {
      'rideId': rideId,
    });
  }

  // Customer Actions
  void subscribeToRide(String rideId) {
    _logger.i('Customer subscribing to ride: $rideId');
    _socket?.emit('customer:subscribe:ride', {
      'rideId': rideId,
    });
  }

  void unsubscribeFromRide(String rideId) {
    _logger.i('Customer unsubscribing from ride: $rideId');
    _socket?.emit('customer:unsubscribe:ride', {
      'rideId': rideId,
    });
  }

  // Event Listeners
  void on(String event, Function(dynamic) callback) {
    if (!_listeners.containsKey(event)) {
      _listeners[event] = [];
    }
    _listeners[event]!.add(callback);
  }

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

  void disconnect() {
    _logger.i('Disconnecting WebSocket');
    _socket?.disconnect();
    _socket = null;
    _isConnected = false;
    _listeners.clear();
  }
}
```

---

## 📍 Location Service

### `lib/services/location_service.dart`

```dart
import 'dart:async';
import 'package:geolocator/geolocator.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:logger/logger.dart';

class LocationService {
  static final LocationService _instance = LocationService._internal();
  factory LocationService() => _instance;
  LocationService._internal();

  final _logger = Logger();
  StreamSubscription<Position>? _positionStreamSubscription;

  Future<bool> requestPermission() async {
    final status = await Permission.location.request();
    return status.isGranted;
  }

  Future<bool> checkPermission() async {
    final status = await Permission.location.status;
    return status.isGranted;
  }

  Future<Position?> getCurrentLocation() async {
    try {
      final hasPermission = await checkPermission();
      if (!hasPermission) {
        final granted = await requestPermission();
        if (!granted) {
          _logger.e('Location permission denied');
          return null;
        }
      }

      final position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
      );

      return position;
    } catch (e) {
      _logger.e('Error getting location: $e');
      return null;
    }
  }

  void startLocationUpdates({
    required Function(Position) onLocationUpdate,
    int intervalSeconds = 3,
  }) {
    const locationSettings = LocationSettings(
      accuracy: LocationAccuracy.high,
      distanceFilter: 10, // Update every 10 meters
    );

    _positionStreamSubscription = Geolocator.getPositionStream(
      locationSettings: locationSettings,
    ).listen(
      (Position position) {
        onLocationUpdate(position);
      },
      onError: (error) {
        _logger.e('Location stream error: $error');
      },
    );
  }

  void stopLocationUpdates() {
    _positionStreamSubscription?.cancel();
    _positionStreamSubscription = null;
  }
}
```

---

## 👤 Models

### `lib/models/user.dart`

```dart
class User {
  final String id;
  final String firstName;
  final String lastName;
  final String phone;
  final String email;
  final String userType; // 'customer' or 'driver'
  final String? profileImage;

  User({
    required this.id,
    required this.firstName,
    required this.lastName,
    required this.phone,
    required this.email,
    required this.userType,
    this.profileImage,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: json['id'],
      firstName: json['first_name'] ?? '',
      lastName: json['last_name'] ?? '',
      phone: json['phone'] ?? '',
      email: json['email'] ?? '',
      userType: json['user_type'] ?? 'customer',
      profileImage: json['profile_image'],
    );
  }

  String get fullName => '$firstName $lastName';
}
```

### `lib/models/ride.dart`

```dart
class Ride {
  final String id;
  final String refId;
  final String customerId;
  final String? driverId;
  final String currentStatus;
  final double pickupLatitude;
  final double pickupLongitude;
  final String pickupAddress;
  final double destinationLatitude;
  final double destinationLongitude;
  final String destinationAddress;
  final double estimatedFare;
  final double? actualFare;
  final String vehicleCategoryId;
  final String paymentMethod;
  final DateTime createdAt;

  Ride({
    required this.id,
    required this.refId,
    required this.customerId,
    this.driverId,
    required this.currentStatus,
    required this.pickupLatitude,
    required this.pickupLongitude,
    required this.pickupAddress,
    required this.destinationLatitude,
    required this.destinationLongitude,
    required this.destinationAddress,
    required this.estimatedFare,
    this.actualFare,
    required this.vehicleCategoryId,
    required this.paymentMethod,
    required this.createdAt,
  });

  factory Ride.fromJson(Map<String, dynamic> json) {
    return Ride(
      id: json['id'],
      refId: json['ref_id'] ?? '',
      customerId: json['customer_id'] ?? '',
      driverId: json['driver_id'],
      currentStatus: json['current_status'] ?? 'pending',
      pickupLatitude: double.parse(json['pickup_latitude'].toString()),
      pickupLongitude: double.parse(json['pickup_longitude'].toString()),
      pickupAddress: json['pickup_address'] ?? '',
      destinationLatitude: double.parse(json['destination_latitude'].toString()),
      destinationLongitude: double.parse(json['destination_longitude'].toString()),
      destinationAddress: json['destination_address'] ?? '',
      estimatedFare: double.parse(json['estimated_fare'].toString()),
      actualFare: json['actual_fare'] != null ? double.parse(json['actual_fare'].toString()) : null,
      vehicleCategoryId: json['vehicle_category_id'] ?? '',
      paymentMethod: json['payment_method'] ?? 'cash',
      createdAt: DateTime.parse(json['created_at']),
    );
  }
}
```

---

## 🚗 Driver App Example

### `lib/screens/driver/driver_home_screen.dart`

```dart
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import '../../services/api_service.dart';
import '../../services/websocket_service.dart';
import '../../services/location_service.dart';
import '../../services/storage_service.dart';
import '../../models/ride.dart';

class DriverHomeScreen extends StatefulWidget {
  const DriverHomeScreen({Key? key}) : super(key: key);

  @override
  State<DriverHomeScreen> createState() => _DriverHomeScreenState();
}

class _DriverHomeScreenState extends State<DriverHomeScreen> {
  final _api = ApiService();
  final _ws = WebSocketService();
  final _location = LocationService();
  final _storage = StorageService();

  bool _isOnline = false;
  List<Ride> _pendingRides = [];
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    _initializeServices();
  }

  Future<void> _initializeServices() async {
    // Connect to WebSocket
    await _ws.connect();

    // Listen for new rides
    _ws.on('ride:new', (data) {
      print('New ride received: $data');
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('🔔 New Ride Request!')),
      );
      _loadPendingRides();
    });

    // Listen for acceptance results
    _ws.on('ride:accept:success', (data) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('✅ Ride accepted!')),
      );
      // Navigate to ride in progress screen
      Navigator.pushNamed(context, '/driver/ride-in-progress', arguments: data);
    });

    _ws.on('ride:accept:failed', (data) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('❌ ${data['message']}')),
      );
    });
  }

  Future<void> _toggleOnline() async {
    if (!_isOnline) {
      // Go online
      final hasPermission = await _location.checkPermission();
      if (!hasPermission) {
        final granted = await _location.requestPermission();
        if (!granted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Location permission required')),
          );
          return;
        }
      }

      final position = await _location.getCurrentLocation();
      if (position == null) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Could not get location')),
        );
        return;
      }

      _ws.goOnline(
        latitude: position.latitude,
        longitude: position.longitude,
        vehicleCategoryId: 'your-vehicle-category-id', // Get from storage
        vehicleId: 'your-vehicle-id', // Get from storage
        name: 'Driver Name', // Get from storage
      );

      // Start sending location updates
      _location.startLocationUpdates(
        onLocationUpdate: (Position position) {
          _ws.updateLocation(
            latitude: position.latitude,
            longitude: position.longitude,
            speed: position.speed,
            heading: position.heading,
            accuracy: position.accuracy,
          );
        },
      );

      setState(() => _isOnline = true);
    } else {
      // Go offline
      _ws.goOffline();
      _location.stopLocationUpdates();
      setState(() => _isOnline = false);
    }
  }

  Future<void> _loadPendingRides() async {
    setState(() => _isLoading = true);

    try {
      final zoneId = await _storage.getZoneId();
      if (zoneId == null) {
        throw Exception('Zone ID not found');
      }

      final response = await _api.getPendingRides(zoneId: zoneId);
      final rides = (response.data['data'] as List)
          .map((json) => Ride.fromJson(json))
          .toList();

      setState(() {
        _pendingRides = rides;
        _isLoading = false;
      });
    } catch (e) {
      print('Error loading rides: $e');
      setState(() => _isLoading = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error loading rides: $e')),
      );
    }
  }

  void _acceptRide(Ride ride) {
    _ws.acceptRide(ride.id);
  }

  @override
  void dispose() {
    _location.stopLocationUpdates();
    _ws.off('ride:new');
    _ws.off('ride:accept:success');
    _ws.off('ride:accept:failed');
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Driver Home'),
        actions: [
          IconButton(
            icon: Icon(_isOnline ? Icons.online_prediction : Icons.offline_bolt),
            onPressed: _toggleOnline,
            color: _isOnline ? Colors.green : Colors.grey,
          ),
        ],
      ),
      body: Column(
        children: [
          // Status Card
          Card(
            margin: const EdgeInsets.all(16),
            color: _isOnline ? Colors.green[50] : Colors.grey[200],
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _isOnline ? 'You are Online' : 'You are Offline',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                          color: _isOnline ? Colors.green[800] : Colors.grey[800],
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        _isOnline
                            ? 'Waiting for ride requests...'
                            : 'Tap to go online',
                        style: TextStyle(color: Colors.grey[600]),
                      ),
                    ],
                  ),
                  Switch(
                    value: _isOnline,
                    onChanged: (_) => _toggleOnline(),
                  ),
                ],
              ),
            ),
          ),

          // Load Rides Button
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: ElevatedButton.icon(
              onPressed: _isLoading ? null : _loadPendingRides,
              icon: const Icon(Icons.refresh),
              label: const Text('Load Pending Rides'),
              style: ElevatedButton.styleFrom(
                minimumSize: const Size(double.infinity, 48),
              ),
            ),
          ),

          const SizedBox(height: 16),

          // Pending Rides List
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
                : _pendingRides.isEmpty
                    ? const Center(child: Text('No pending rides'))
                    : ListView.builder(
                        padding: const EdgeInsets.all(16),
                        itemCount: _pendingRides.length,
                        itemBuilder: (context, index) {
                          final ride = _pendingRides[index];
                          return Card(
                            margin: const EdgeInsets.only(bottom: 12),
                            child: ListTile(
                              title: Text('Ride #${ride.refId}'),
                              subtitle: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text('Pickup: ${ride.pickupAddress}'),
                                  Text('Destination: ${ride.destinationAddress}'),
                                  Text('Fare: ${ride.estimatedFare} EGP'),
                                ],
                              ),
                              trailing: ElevatedButton(
                                onPressed: () => _acceptRide(ride),
                                child: const Text('Accept'),
                              ),
                            ),
                          );
                        },
                      ),
          ),
        ],
      ),
    );
  }
}
```

---

## 👥 Customer App Example

### `lib/screens/customer/ride_tracking_screen.dart`

```dart
import 'package:flutter/material.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import '../../services/api_service.dart';
import '../../services/websocket_service.dart';
import '../../models/ride.dart';

class RideTrackingScreen extends StatefulWidget {
  final String rideId;

  const RideTrackingScreen({Key? key, required this.rideId}) : super(key: key);

  @override
  State<RideTrackingScreen> createState() => _RideTrackingScreenState();
}

class _RideTrackingScreenState extends State<RideTrackingScreen> {
  final _api = ApiService();
  final _ws = WebSocketService();

  GoogleMapController? _mapController;
  Ride? _ride;
  LatLng? _driverLocation;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _initializeRide();
  }

  Future<void> _initializeRide() async {
    // Load ride details from Laravel
    await _loadRideDetails();

    // Connect to WebSocket
    await _ws.connect();

    // Subscribe to ride updates
    _ws.subscribeToRide(widget.rideId);

    // Listen for driver location updates
    _ws.on('driver:location:update', (data) {
      setState(() {
        _driverLocation = LatLng(
          data['location']['latitude'],
          data['location']['longitude'],
        );
      });
      _updateCamera();
    });

    // Listen for driver assigned
    _ws.on('ride:driver_assigned', (data) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Driver assigned: ${data['driver']['name']}')),
      );
      _loadRideDetails(); // Reload to get driver info
    });

    // Listen for ride started
    _ws.on('ride:started', (data) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('🚗 Ride started!')),
      );
      setState(() {
        if (_ride != null) {
          _ride = Ride(
            id: _ride!.id,
            refId: _ride!.refId,
            customerId: _ride!.customerId,
            driverId: _ride!.driverId,
            currentStatus: 'started',
            pickupLatitude: _ride!.pickupLatitude,
            pickupLongitude: _ride!.pickupLongitude,
            pickupAddress: _ride!.pickupAddress,
            destinationLatitude: _ride!.destinationLatitude,
            destinationLongitude: _ride!.destinationLongitude,
            destinationAddress: _ride!.destinationAddress,
            estimatedFare: _ride!.estimatedFare,
            actualFare: _ride!.actualFare,
            vehicleCategoryId: _ride!.vehicleCategoryId,
            paymentMethod: _ride!.paymentMethod,
            createdAt: _ride!.createdAt,
          );
        }
      });
    });

    // Listen for ride completed
    _ws.on('ride:completed', (data) {
      Navigator.pushReplacementNamed(
        context,
        '/customer/ride-completed',
        arguments: widget.rideId,
      );
    });
  }

  Future<void> _loadRideDetails() async {
    try {
      final response = await _api.getRideDetails(widget.rideId);
      setState(() {
        _ride = Ride.fromJson(response.data['data']);
        _isLoading = false;
      });
    } catch (e) {
      print('Error loading ride: $e');
      setState(() => _isLoading = false);
    }
  }

  void _updateCamera() {
    if (_mapController != null && _ride != null && _driverLocation != null) {
      final bounds = LatLngBounds(
        southwest: LatLng(
          _ride!.pickupLatitude < _driverLocation!.latitude
              ? _ride!.pickupLatitude
              : _driverLocation!.latitude,
          _ride!.pickupLongitude < _driverLocation!.longitude
              ? _ride!.pickupLongitude
              : _driverLocation!.longitude,
        ),
        northeast: LatLng(
          _ride!.pickupLatitude > _driverLocation!.latitude
              ? _ride!.pickupLatitude
              : _driverLocation!.latitude,
          _ride!.pickupLongitude > _driverLocation!.longitude
              ? _ride!.pickupLongitude
              : _driverLocation!.longitude,
        ),
      );

      _mapController!.animateCamera(CameraUpdate.newLatLngBounds(bounds, 100));
    }
  }

  @override
  void dispose() {
    _ws.unsubscribeFromRide(widget.rideId);
    _ws.off('driver:location:update');
    _ws.off('ride:driver_assigned');
    _ws.off('ride:started');
    _ws.off('ride:completed');
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading || _ride == null) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: Text('Ride #${_ride!.refId}'),
      ),
      body: Stack(
        children: [
          // Map
          GoogleMap(
            initialCameraPosition: CameraPosition(
              target: LatLng(_ride!.pickupLatitude, _ride!.pickupLongitude),
              zoom: 14,
            ),
            onMapCreated: (controller) {
              _mapController = controller;
            },
            markers: {
              Marker(
                markerId: const MarkerId('pickup'),
                position: LatLng(_ride!.pickupLatitude, _ride!.pickupLongitude),
                icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueGreen),
                infoWindow: const InfoWindow(title: 'Pickup'),
              ),
              Marker(
                markerId: const MarkerId('destination'),
                position: LatLng(_ride!.destinationLatitude, _ride!.destinationLongitude),
                icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueRed),
                infoWindow: const InfoWindow(title: 'Destination'),
              ),
              if (_driverLocation != null)
                Marker(
                  markerId: const MarkerId('driver'),
                  position: _driverLocation!,
                  icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueBlue),
                  infoWindow: const InfoWindow(title: 'Driver'),
                ),
            },
          ),

          // Bottom Sheet
          Positioned(
            left: 0,
            right: 0,
            bottom: 0,
            child: Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: const BorderRadius.only(
                  topLeft: Radius.circular(16),
                  topRight: Radius.circular(16),
                ),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.1),
                    blurRadius: 10,
                  ),
                ],
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    'Status: ${_ride!.currentStatus.toUpperCase()}',
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text('Pickup: ${_ride!.pickupAddress}'),
                  Text('Destination: ${_ride!.destinationAddress}'),
                  Text('Fare: ${_ride!.estimatedFare} EGP'),
                  if (_ride!.driverId != null) ...[
                    const Divider(height: 24),
                    const Text('Driver Information'),
                    // Add driver info here
                  ],
                ],
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

## 🚀 Initialize in main.dart

### `lib/main.dart`

```dart
import 'package:flutter/material.dart';
import 'services/api_service.dart';
import 'services/storage_service.dart';
import 'screens/driver/driver_home_screen.dart';
import 'screens/customer/ride_tracking_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Initialize services
  await StorageService().init();
  ApiService().init();

  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'SmartLine',
      theme: ThemeData(
        primarySwatch: Colors.blue,
      ),
      home: const DriverHomeScreen(),
      routes: {
        '/driver/home': (context) => const DriverHomeScreen(),
        // Add more routes
      },
    );
  }
}
```

---

## ✅ Summary

| Component | Purpose | Key Methods |
|-----------|---------|-------------|
| **ApiService** | Laravel HTTP API | `login()`, `createRide()`, `getPendingRides()` |
| **WebSocketService** | Node.js WebSocket | `connect()`, `goOnline()`, `acceptRide()` |
| **LocationService** | GPS tracking | `getCurrentLocation()`, `startLocationUpdates()` |
| **StorageService** | Local storage | `saveToken()`, `getToken()` |

---

## 🔥 Production Checklist

- [ ] Update URLs in `api_config.dart` for production
- [ ] Add proper error handling
- [ ] Implement retry logic
- [ ] Add loading states
- [ ] Handle network connectivity
- [ ] Test on real devices
- [ ] Add crashlytics/error reporting
- [ ] Implement proper state management (Provider/Riverpod/Bloc)
- [ ] Add offline support
- [ ] Test WebSocket reconnection

---

**Your Flutter app now connects to both Laravel (HTTP) and Node.js (WebSocket)!** 🎉
