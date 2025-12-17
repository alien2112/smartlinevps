# Frontend Integration Guide - Laravel + Node.js

## Overview

Your frontend app needs to connect to **BOTH** services:
- **Laravel API (Port 8000)**: HTTP REST for business logic
- **Node.js WebSocket (Port 3000)**: Socket.IO for real-time features

---

## React Native / Expo Integration

### Installation

```bash
npm install axios socket.io-client
# or
yarn add axios socket.io-client
```

### 1. Create API Service (Laravel)

**`src/services/api.js`**

```javascript
import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';

const LARAVEL_API_URL = 'http://YOUR_SERVER_IP:8000/api';

const api = axios.create({
  baseURL: LARAVEL_API_URL,
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Add auth token to requests
api.interceptors.request.use(async (config) => {
  const token = await AsyncStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Auth APIs
export const authAPI = {
  login: (phone, password) =>
    api.post('/customer/auth/login', { phone, password }),

  register: (data) =>
    api.post('/customer/auth/register', data),

  logout: () =>
    api.post('/customer/auth/logout'),
};

// Ride APIs
export const rideAPI = {
  createRide: (data) =>
    api.post('/customer/ride/request', data),

  getRideDetails: (rideId) =>
    api.get(`/customer/ride/${rideId}`),

  getPendingRides: (zoneId, limit = 10) =>
    api.get('/driver/ride/pending-ride-list', {
      params: { limit, offset: 1 },
      headers: { zoneId },
    }),

  updateRideStatus: (tripRequestId, status) =>
    api.post('/driver/ride/status-update', {
      trip_request_id: tripRequestId,
      current_status: status,
    }),

  rateDriver: (tripRequestId, rating, comment) =>
    api.post('/customer/ride/rate', {
      trip_request_id: tripRequestId,
      rating,
      comment,
    }),
};

// Driver APIs
export const driverAPI = {
  getProfile: () =>
    api.get('/driver/profile'),

  updateAvailability: (available) =>
    api.post('/driver/availability', { available }),
};

export default api;
```

---

### 2. Create WebSocket Service (Node.js)

**`src/services/websocket.js`**

```javascript
import io from 'socket.io-client';
import AsyncStorage from '@react-native-async-storage/async-storage';

const NODEJS_URL = 'http://YOUR_SERVER_IP:3000';

class WebSocketService {
  constructor() {
    this.socket = null;
    this.connected = false;
    this.listeners = new Map();
  }

  async connect() {
    const token = await AsyncStorage.getItem('auth_token');

    if (!token) {
      console.warn('No auth token, cannot connect to WebSocket');
      return;
    }

    this.socket = io(NODEJS_URL, {
      auth: { token },
      transports: ['websocket', 'polling'],
      reconnection: true,
      reconnectionDelay: 1000,
      reconnectionDelayMax: 5000,
      reconnectionAttempts: 5,
    });

    this.socket.on('connect', () => {
      console.log('✅ WebSocket connected:', this.socket.id);
      this.connected = true;
    });

    this.socket.on('disconnect', (reason) => {
      console.log('❌ WebSocket disconnected:', reason);
      this.connected = false;
    });

    this.socket.on('connect_error', (error) => {
      console.error('WebSocket connection error:', error.message);
    });

    // Setup default listeners
    this.setupDefaultListeners();
  }

  setupDefaultListeners() {
    // Driver events
    this.socket.on('ride:new', (data) => {
      console.log('🔔 New ride request:', data);
      this.emit('ride:new', data);
    });

    this.socket.on('ride:accept:success', (data) => {
      console.log('✅ Ride accepted:', data);
      this.emit('ride:accept:success', data);
    });

    this.socket.on('ride:accept:failed', (data) => {
      console.log('❌ Ride accept failed:', data);
      this.emit('ride:accept:failed', data);
    });

    this.socket.on('ride:taken', (data) => {
      console.log('⚠️ Ride taken by another driver:', data);
      this.emit('ride:taken', data);
    });

    // Customer events
    this.socket.on('driver:location:update', (data) => {
      this.emit('driver:location:update', data);
    });

    this.socket.on('ride:driver_assigned', (data) => {
      console.log('✅ Driver assigned:', data);
      this.emit('ride:driver_assigned', data);
    });

    this.socket.on('ride:started', (data) => {
      console.log('🚗 Ride started:', data);
      this.emit('ride:started', data);
    });

    this.socket.on('ride:completed', (data) => {
      console.log('✅ Ride completed:', data);
      this.emit('ride:completed', data);
    });

    this.socket.on('ride:cancelled', (data) => {
      console.log('❌ Ride cancelled:', data);
      this.emit('ride:cancelled', data);
    });
  }

  // Driver actions
  goOnline(data) {
    if (!this.socket) return;
    this.socket.emit('driver:online', data);
  }

  goOffline() {
    if (!this.socket) return;
    this.socket.emit('driver:offline');
  }

  updateLocation(location) {
    if (!this.socket) return;
    this.socket.emit('driver:location', location);
  }

  acceptRide(rideId) {
    if (!this.socket) return;
    this.socket.emit('driver:accept:ride', { rideId });
  }

  // Customer actions
  subscribeToRide(rideId) {
    if (!this.socket) return;
    this.socket.emit('customer:subscribe:ride', { rideId });
  }

  unsubscribeFromRide(rideId) {
    if (!this.socket) return;
    this.socket.emit('customer:unsubscribe:ride', { rideId });
  }

  // Event listeners
  on(event, callback) {
    if (!this.listeners.has(event)) {
      this.listeners.set(event, []);
    }
    this.listeners.get(event).push(callback);
  }

  off(event, callback) {
    if (!this.listeners.has(event)) return;
    const callbacks = this.listeners.get(event);
    const index = callbacks.indexOf(callback);
    if (index > -1) {
      callbacks.splice(index, 1);
    }
  }

  emit(event, data) {
    if (!this.listeners.has(event)) return;
    this.listeners.get(event).forEach(callback => callback(data));
  }

  disconnect() {
    if (this.socket) {
      this.socket.disconnect();
      this.socket = null;
      this.connected = false;
    }
  }
}

export default new WebSocketService();
```

---

### 3. Driver App Example

**`src/screens/DriverHomeScreen.js`**

```javascript
import React, { useEffect, useState } from 'react';
import { View, Text, Button, Switch } from 'react-native';
import * as Location from 'expo-location';
import { rideAPI } from '../services/api';
import websocket from '../services/websocket';

export default function DriverHomeScreen({ navigation }) {
  const [online, setOnline] = useState(false);
  const [pendingRides, setPendingRides] = useState([]);

  useEffect(() => {
    // Connect to WebSocket
    websocket.connect();

    // Listen for new rides
    const handleNewRide = (data) => {
      console.log('New ride:', data);
      // Show notification
      Alert.alert('New Ride Request!', `${data.distance}km away`);
      loadPendingRides();
    };

    websocket.on('ride:new', handleNewRide);

    return () => {
      websocket.off('ride:new', handleNewRide);
      if (online) {
        websocket.goOffline();
      }
    };
  }, []);

  const toggleOnline = async () => {
    if (!online) {
      // Go online
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status !== 'granted') {
        alert('Location permission required');
        return;
      }

      const location = await Location.getCurrentPositionAsync({});

      websocket.goOnline({
        location: {
          latitude: location.coords.latitude,
          longitude: location.coords.longitude,
        },
        vehicle_category_id: 'your-vehicle-category-id',
        vehicle_id: 'your-vehicle-id',
        name: 'Driver Name',
      });

      // Start sending location updates
      startLocationUpdates();
      setOnline(true);
    } else {
      // Go offline
      websocket.goOffline();
      stopLocationUpdates();
      setOnline(false);
    }
  };

  let locationInterval;

  const startLocationUpdates = () => {
    locationInterval = setInterval(async () => {
      const location = await Location.getCurrentPositionAsync({});
      websocket.updateLocation({
        latitude: location.coords.latitude,
        longitude: location.coords.longitude,
        speed: location.coords.speed || 0,
        heading: location.coords.heading || 0,
        accuracy: location.coords.accuracy || 0,
      });
    }, 3000); // Every 3 seconds
  };

  const stopLocationUpdates = () => {
    if (locationInterval) {
      clearInterval(locationInterval);
    }
  };

  const loadPendingRides = async () => {
    try {
      const response = await rideAPI.getPendingRides('your-zone-id');
      setPendingRides(response.data.data);
    } catch (error) {
      console.error('Error loading rides:', error);
    }
  };

  const acceptRide = (rideId) => {
    websocket.acceptRide(rideId);

    // Listen for acceptance result
    websocket.on('ride:accept:success', (data) => {
      navigation.navigate('RideInProgress', { ride: data });
    });

    websocket.on('ride:accept:failed', (data) => {
      alert(data.message);
    });
  };

  return (
    <View>
      <Text>Driver Status</Text>
      <Switch value={online} onValueChange={toggleOnline} />
      <Text>{online ? 'Online' : 'Offline'}</Text>

      <Button title="Load Pending Rides" onPress={loadPendingRides} />

      {pendingRides.map(ride => (
        <View key={ride.id}>
          <Text>Ride #{ride.ref_id}</Text>
          <Text>{ride.estimated_fare} EGP</Text>
          <Button title="Accept" onPress={() => acceptRide(ride.id)} />
        </View>
      ))}
    </View>
  );
}
```

---

### 4. Customer App Example

**`src/screens/CustomerRideScreen.js`**

```javascript
import React, { useEffect, useState } from 'react';
import { View, Text, Button } from 'react-native';
import MapView, { Marker } from 'react-native-maps';
import { rideAPI } from '../services/api';
import websocket from '../services/websocket';

export default function CustomerRideScreen({ route }) {
  const { rideId } = route.params;
  const [ride, setRide] = useState(null);
  const [driverLocation, setDriverLocation] = useState(null);

  useEffect(() => {
    // Connect to WebSocket
    websocket.connect();

    // Load ride details from Laravel
    loadRideDetails();

    // Subscribe to real-time updates
    websocket.subscribeToRide(rideId);

    // Listen for driver location updates
    const handleDriverLocation = (data) => {
      console.log('Driver location:', data);
      setDriverLocation(data.location);
    };

    const handleDriverAssigned = (data) => {
      console.log('Driver assigned:', data);
      setRide(prev => ({ ...prev, driver: data.driver }));
    };

    const handleRideStarted = (data) => {
      console.log('Ride started');
      setRide(prev => ({ ...prev, status: 'started' }));
    };

    const handleRideCompleted = (data) => {
      console.log('Ride completed');
      setRide(prev => ({ ...prev, status: 'completed' }));
      // Navigate to payment screen
    };

    websocket.on('driver:location:update', handleDriverLocation);
    websocket.on('ride:driver_assigned', handleDriverAssigned);
    websocket.on('ride:started', handleRideStarted);
    websocket.on('ride:completed', handleRideCompleted);

    return () => {
      websocket.off('driver:location:update', handleDriverLocation);
      websocket.off('ride:driver_assigned', handleDriverAssigned);
      websocket.off('ride:started', handleRideStarted);
      websocket.off('ride:completed', handleRideCompleted);
      websocket.unsubscribeFromRide(rideId);
    };
  }, [rideId]);

  const loadRideDetails = async () => {
    try {
      const response = await rideAPI.getRideDetails(rideId);
      setRide(response.data.data);
    } catch (error) {
      console.error('Error loading ride:', error);
    }
  };

  const createRide = async () => {
    try {
      const response = await rideAPI.createRide({
        pickup_latitude: 30.0444,
        pickup_longitude: 31.2357,
        pickup_address: 'Cairo, Egypt',
        destination_latitude: 30.0626,
        destination_longitude: 31.2497,
        destination_address: 'Heliopolis, Cairo',
        vehicle_category_id: 'your-category-id',
        payment_method: 'cash',
      });

      console.log('Ride created:', response.data.data);
      setRide(response.data.data);
      websocket.subscribeToRide(response.data.data.id);
    } catch (error) {
      console.error('Error creating ride:', error);
    }
  };

  return (
    <View style={{ flex: 1 }}>
      <MapView style={{ flex: 1 }}>
        {ride && (
          <Marker
            coordinate={{
              latitude: ride.pickup_latitude,
              longitude: ride.pickup_longitude,
            }}
            title="Pickup"
          />
        )}

        {driverLocation && (
          <Marker
            coordinate={{
              latitude: driverLocation.latitude,
              longitude: driverLocation.longitude,
            }}
            title="Driver"
            image={require('../assets/car-icon.png')}
          />
        )}
      </MapView>

      <View style={{ padding: 20 }}>
        {ride ? (
          <>
            <Text>Ride #{ride.ref_id}</Text>
            <Text>Status: {ride.status}</Text>
            {ride.driver && <Text>Driver: {ride.driver.name}</Text>}
          </>
        ) : (
          <Button title="Request Ride" onPress={createRide} />
        )}
      </View>
    </View>
  );
}
```

---

## Flutter Integration

### Installation

```yaml
dependencies:
  dio: ^5.0.0
  socket_io_client: ^2.0.0
```

### API Service (Laravel)

```dart
import 'package:dio/dio.dart';
import 'package:shared_preferences/shared_preferences.dart';

class ApiService {
  static const String baseUrl = 'http://YOUR_SERVER_IP:8000/api';
  final Dio _dio = Dio();

  ApiService() {
    _dio.options.baseUrl = baseUrl;
    _dio.options.connectTimeout = const Duration(seconds: 30);
    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        final prefs = await SharedPreferences.getInstance();
        final token = prefs.getString('auth_token');
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        return handler.next(options);
      },
    ));
  }

  Future<Response> login(String phone, String password) {
    return _dio.post('/customer/auth/login', data: {
      'phone': phone,
      'password': password,
    });
  }

  Future<Response> createRide(Map<String, dynamic> data) {
    return _dio.post('/customer/ride/request', data: data);
  }

  Future<Response> getPendingRides(String zoneId) {
    return _dio.get('/driver/ride/pending-ride-list',
      queryParameters: {'limit': 10, 'offset': 1},
      options: Options(headers: {'zoneId': zoneId}),
    );
  }
}
```

### WebSocket Service (Node.js)

```dart
import 'package:socket_io_client/socket_io_client.dart' as IO;

class WebSocketService {
  static const String nodeUrl = 'http://YOUR_SERVER_IP:3000';
  IO.Socket? socket;

  void connect(String token) {
    socket = IO.io(nodeUrl, <String, dynamic>{
      'auth': {'token': token},
      'transports': ['websocket', 'polling'],
      'autoConnect': true,
    });

    socket!.onConnect((_) {
      print('✅ WebSocket connected');
    });

    socket!.onDisconnect((_) {
      print('❌ WebSocket disconnected');
    });

    socket!.on('ride:new', (data) {
      print('🔔 New ride: $data');
    });
  }

  void goOnline(Map<String, dynamic> data) {
    socket?.emit('driver:online', data);
  }

  void acceptRide(String rideId) {
    socket?.emit('driver:accept:ride', {'rideId': rideId});
  }

  void disconnect() {
    socket?.disconnect();
  }
}
```

---

## Production URLs

When deploying to production, update these URLs:

**Development:**
```javascript
const LARAVEL_API_URL = 'http://localhost:8000/api';
const NODEJS_URL = 'http://localhost:3000';
```

**Production:**
```javascript
const LARAVEL_API_URL = 'https://api.yourdomain.com/api';
const NODEJS_URL = 'https://realtime.yourdomain.com'; // or wss://
```

---

## Summary

| What | Where | Why |
|------|-------|-----|
| **Login** | Laravel | Get JWT token |
| **Create Ride** | Laravel | Store in database |
| **Connect WebSocket** | Node.js | Real-time updates |
| **Send Location** | Node.js | Fast updates |
| **Accept Ride** | Node.js | Instant response |
| **Get History** | Laravel | Database query |

**Both services work together to provide a complete experience!**
