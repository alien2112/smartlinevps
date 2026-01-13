# Flutter App: Use Config Core for WebSocket Configuration

## 📋 Implementation Prompt

**Task:** Update the Flutter app to fetch WebSocket configuration from the `/api/driver/config/core` endpoint on app startup, and use these dynamic values instead of hardcoded WebSocket URLs, ports, paths, and keys.

---

## 🎯 Requirements

### 1. **Fetch Config on App Startup**
- Call `/api/driver/config/core` endpoint immediately after successful authentication
- Store the WebSocket configuration values securely
- Use these values for all WebSocket connections (Socket.IO and Reverb)

### 2. **Configuration Fields to Use**

#### Socket.IO Configuration (Node.js Service)
```dart
{
  "socketIOUrl": "https://smartline-it.com",  // Use this instead of hardcoded URL
  "socketIOPath": "/socket.io/"                // Use this instead of hardcoded path
}
```

#### Reverb WebSocket Configuration (Laravel Service)
```dart
{
  "webSocketUrl": "smartline-it.com",          // Use this instead of hardcoded host
  "webSocketPort": "443",                      // Use this instead of hardcoded port
  "websocketScheme": "https",                  // Use this instead of hardcoded scheme
  "webSocketKey": "drivemond"                  // Use this instead of hardcoded key
}
```

### 3. **Fallback Strategy**
- If config endpoint fails or returns null values, fall back to hardcoded defaults
- Log warnings when using fallback values
- Retry config fetch on next app launch

---

## 💻 Implementation Steps

### Step 1: Create Config Service

Create `lib/services/config_service.dart`:

```dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class ConfigService {
  static final ConfigService _instance = ConfigService._internal();
  factory ConfigService() => _instance;
  ConfigService._internal();

  final FlutterSecureStorage _storage = const FlutterSecureStorage();
  
  // Socket.IO Configuration
  String? _socketIOUrl;
  String? _socketIOPath;
  
  // Reverb Configuration
  String? _webSocketUrl;
  String? _webSocketPort;
  String? _websocketScheme;
  String? _webSocketKey;
  
  // Getters with fallback defaults
  String get socketIOUrl => _socketIOUrl ?? 'https://smartline-it.com';
  String get socketIOPath => _socketIOPath ?? '/socket.io/';
  String get webSocketUrl => _webSocketUrl ?? 'smartline-it.com';
  String get webSocketPort => _webSocketPort ?? '443';
  String get websocketScheme => _websocketScheme ?? 'https';
  String get webSocketKey => _webSocketKey ?? 'drivemond';
  
  bool get isConfigured => _socketIOUrl != null && _webSocketUrl != null;

  /// Fetch configuration from API
  Future<bool> fetchConfig(String baseUrl, String authToken) async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/driver/config/core'),
        headers: {
          'Authorization': 'Bearer $authToken',
          'Accept': 'application/json',
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        final content = data['content'] ?? {};
        
        // Extract Socket.IO config
        _socketIOUrl = content['socketIOUrl'] ?? content['socketio_url'];
        _socketIOPath = content['socketIOPath'] ?? content['socketio_path'];
        
        // Extract Reverb config
        _webSocketUrl = content['webSocketUrl'] ?? content['websocket_url'];
        _webSocketPort = content['webSocketPort'] ?? content['websocket_port'];
        _websocketScheme = content['websocketScheme'] ?? content['websocket_scheme'];
        _webSocketKey = content['webSocketKey'] ?? content['websocket_key'];
        
        // Store in secure storage for offline use
        await _saveConfig();
        
        print('✅ Config loaded from API');
        return true;
      } else {
        print('⚠️ Config fetch failed: ${response.statusCode}');
        await _loadCachedConfig();
        return false;
      }
    } catch (e) {
      print('❌ Config fetch error: $e');
      await _loadCachedConfig();
      return false;
    }
  }

  /// Save config to secure storage
  Future<void> _saveConfig() async {
    await _storage.write(key: 'socketIOUrl', value: _socketIOUrl);
    await _storage.write(key: 'socketIOPath', value: _socketIOPath);
    await _storage.write(key: 'webSocketUrl', value: _webSocketUrl);
    await _storage.write(key: 'webSocketPort', value: _webSocketPort);
    await _storage.write(key: 'websocketScheme', value: _websocketScheme);
    await _storage.write(key: 'webSocketKey', value: _webSocketKey);
  }

  /// Load cached config from storage
  Future<void> _loadCachedConfig() async {
    _socketIOUrl = await _storage.read(key: 'socketIOUrl');
    _socketIOPath = await _storage.read(key: 'socketIOPath');
    _webSocketUrl = await _storage.read(key: 'webSocketUrl');
    _webSocketPort = await _storage.read(key: 'webSocketPort');
    _websocketScheme = await _storage.read(key: 'websocketScheme');
    _webSocketKey = await _storage.read(key: 'webSocketKey');
    
    if (_socketIOUrl != null) {
      print('✅ Loaded cached config');
    } else {
      print('⚠️ Using default hardcoded values');
    }
  }

  /// Initialize config (call on app startup)
  Future<void> initialize(String baseUrl, String authToken) async {
    // Try to load cached config first (for faster startup)
    await _loadCachedConfig();
    
    // Then fetch fresh config from API
    await fetchConfig(baseUrl, authToken);
  }
}
```

---

### Step 2: Update Socket.IO Service

Update `lib/services/socket_service.dart`:

```dart
import 'package:socket_io_client/socket_io_client.dart' as IO;
import 'services/config_service.dart';

class SocketService {
  static final SocketService _instance = SocketService._internal();
  factory SocketService() => _instance;
  SocketService._internal();

  IO.Socket? _socket;
  final ConfigService _config = ConfigService();

  /// Connect using config from API (not hardcoded values)
  Future<void> connect(String token) async {
    if (_socket?.connected ?? false) {
      return;
    }

    // Use config values instead of hardcoded URL
    final socketUrl = _config.socketIOUrl;  // From config, not hardcoded
    final socketPath = _config.socketIOPath; // From config, not hardcoded

    print('🔌 Connecting to Socket.IO: $socketUrl$socketPath');

    try {
      _socket = IO.io(
        socketUrl,
        IO.OptionBuilder()
            .setPath(socketPath)  // Use from config
            .setTransports(['websocket', 'polling'])
            .setAuth({'token': token})
            .enableAutoConnect()
            .enableReconnection()
            .build(),
      );

      _setupEventListeners();
    } catch (e) {
      print('Socket connection error: $e');
      rethrow;
    }
  }

  // ... rest of socket service code ...
}
```

---

### Step 3: Update Reverb/Pusher Service

Update `lib/services/reverb_service.dart` (or wherever Reverb is used):

```dart
import 'package:laravel_echo/laravel_echo.dart';
import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';
import 'services/config_service.dart';

class ReverbService {
  static final ReverbService _instance = ReverbService._internal();
  factory ReverbService() => _instance;
  ReverbService._internal();

  LaravelEcho? _echo;
  final ConfigService _config = ConfigService();

  /// Connect using config from API (not hardcoded values)
  Future<void> connect() async {
    // Use config values instead of hardcoded values
    final host = _config.webSocketUrl;        // From config
    final port = int.parse(_config.webSocketPort); // From config
    final scheme = _config.websocketScheme;   // From config
    final key = _config.webSocketKey;        // From config

    print('🔌 Connecting to Reverb: $scheme://$host:$port');

    _echo = LaravelEcho(
      PusherChannelsFlutter.getInstance(),
      options: PusherChannelsOptions(
        host: host,              // Use from config
        wsPort: port,            // Use from config
        wssPort: port,           // Use from config
        encrypted: scheme == 'https', // Use from config
        authEndpoint: 'https://$host/broadcasting/auth',
        auth: {
          'headers': {
            'Authorization': 'Bearer YOUR_TOKEN',
            'Accept': 'application/json',
          }
        },
      ),
    );

    await _echo!.connect();
  }
}
```

---

### Step 4: Initialize on App Startup

Update `lib/main.dart` or your authentication handler:

```dart
import 'services/config_service.dart';

class AuthService {
  final ConfigService _configService = ConfigService();

  Future<void> onLoginSuccess(String token, String baseUrl) async {
    // 1. FIRST: Fetch config from API
    await _configService.initialize(baseUrl, token);
    
    // 2. THEN: Initialize WebSocket services using config values
    final socketService = SocketService();
    await socketService.connect(token);
    
    // 3. Initialize Reverb if needed
    final reverbService = ReverbService();
    await reverbService.connect();
    
    print('✅ All services initialized with config from API');
  }
}
```

---

## ✅ Checklist

- [ ] Create `ConfigService` class
- [ ] Implement `fetchConfig()` method to call `/api/driver/config/core`
- [ ] Store config values in secure storage
- [ ] Add fallback to cached config if API fails
- [ ] Add fallback to hardcoded defaults if cache is empty
- [ ] Update `SocketService` to use `ConfigService.socketIOUrl` and `ConfigService.socketIOPath`
- [ ] Update `ReverbService` to use `ConfigService.webSocketUrl`, `ConfigService.webSocketPort`, etc.
- [ ] Call `ConfigService.initialize()` on app startup (after login)
- [ ] Remove all hardcoded WebSocket URLs, ports, paths, and keys
- [ ] Add logging to show when using config vs fallback values
- [ ] Test with config values from API
- [ ] Test fallback when API is unavailable
- [ ] Test offline mode with cached config

---

## 🔍 Testing

### Test 1: Normal Flow
1. Login to app
2. Verify config is fetched from `/api/driver/config/core`
3. Verify Socket.IO connects using `socketIOUrl` and `socketIOPath` from config
4. Verify Reverb connects using `webSocketUrl`, `webSocketPort`, etc. from config

### Test 2: API Failure
1. Disable network or make API return error
2. Verify app uses cached config from previous session
3. Verify WebSocket still connects successfully

### Test 3: First Launch (No Cache)
1. Clear app data
2. Login (API should work)
3. Verify config is fetched and stored
4. Verify WebSocket connects

### Test 4: Offline Mode
1. Login with network (config cached)
2. Go offline
3. Restart app
4. Verify app uses cached config
5. Verify WebSocket connects (if server is still accessible)

---

## 📝 Code Changes Summary

### Before (Hardcoded):
```dart
// ❌ DON'T DO THIS
final socket = IO.io(
  'https://smartline-it.com',  // Hardcoded
  IO.OptionBuilder()
    .setPath('/socket.io/')    // Hardcoded
    .build(),
);
```

### After (From Config):
```dart
// ✅ DO THIS
final config = ConfigService();
await config.initialize(baseUrl, token);

final socket = IO.io(
  config.socketIOUrl,    // From API config
  IO.OptionBuilder()
    .setPath(config.socketIOPath)  // From API config
    .build(),
);
```

---

## 🎯 Key Points

1. **Always fetch config first** - Call `/api/driver/config/core` before initializing WebSocket connections
2. **Use config values** - Replace all hardcoded URLs, ports, paths, and keys with values from `ConfigService`
3. **Implement fallbacks** - Cache config for offline use, fallback to defaults if needed
4. **Log everything** - Show when using config vs fallback values for debugging
5. **Test thoroughly** - Test with API available, API unavailable, and offline scenarios

---

## 📚 Related Files

- API Endpoint: `/api/driver/config/core`
- Config Controller: `Modules/BusinessManagement/Http/Controllers/Api/New/Driver/ConfigController.php`
- Environment Variables: `.env` (SOCKETIO_URL, SOCKETIO_PATH, REVERB_*)

---

**Implementation Priority: HIGH** - This ensures the app can adapt to configuration changes without requiring app updates.
