# Driver Config Core - Reverb WebSocket Fields

## ✅ Configuration Added

The `/api/driver/config/core` endpoint now includes Reverb WebSocket configuration fields.

---

## 📋 Response Fields

### Reverb WebSocket (Snake Case - Legacy)
```json
{
  "websocket_url": "smartline-it.com",
  "websocket_port": "443",
  "websocket_key": "drivemond",
  "websocket_scheme": "https"
}
```

### Reverb WebSocket (Camel Case - Flutter-friendly)
```json
{
  "webSocketUrl": "smartline-it.com",
  "webSocketPort": "443",
  "websocketScheme": "https",
  "webSocketKey": "drivemond"
}
```

### Socket.IO Configuration (Snake Case)
```json
{
  "socketio_url": "https://smartline-it.com",
  "socketio_path": "/socket.io/"
}
```

### Socket.IO Configuration (Camel Case - Flutter-friendly)
```json
{
  "socketIOUrl": "https://smartline-it.com",
  "socketIOPath": "/socket.io/"
}
```

---

## 🔍 Current Values

Based on `.env` configuration:

### Reverb WebSocket
| Field | Value | Source |
|-------|-------|--------|
| `webSocketUrl` | `smartline-it.com` | `REVERB_HOST` env var |
| `webSocketPort` | `443` | `REVERB_PORT` env var |
| `websocketScheme` | `https` | `REVERB_SCHEME` env var |
| `webSocketKey` | `drivemond` | `REVERB_APP_KEY` env var |

### Socket.IO
| Field | Value | Source |
|-------|-------|--------|
| `socketIOUrl` | `https://smartline-it.com` | `SOCKETIO_URL` env var |
| `socketIOPath` | `/socket.io/` | `SOCKETIO_PATH` env var |

---

## 📝 Implementation Details

### Code Location
`Modules/BusinessManagement/Http/Controllers/Api/New/Driver/ConfigController.php`

### Lines 223-238
```php
// WebSocket - Laravel Reverb Configuration
'websocket_url' => $info->firstWhere('key_name', 'websocket_url')?->value ?? env('REVERB_HOST', 'smartline-it.com'),
'websocket_port' => (string)($info->firstWhere('key_name', 'websocket_port')?->value ?? env('REVERB_PORT', '443')),
'websocket_key' => env('REVERB_APP_KEY', env('PUSHER_APP_KEY')),
'websocket_scheme' => env('REVERB_SCHEME', env('PUSHER_SCHEME', 'https')),
// Reverb WebSocket Configuration (camelCase for Flutter)
'webSocketUrl' => $info->firstWhere('key_name', 'websocket_url')?->value ?? env('REVERB_HOST', 'smartline-it.com'),
'webSocketPort' => (string)($info->firstWhere('key_name', 'websocket_port')?->value ?? env('REVERB_PORT', '443')),
'websocketScheme' => env('REVERB_SCHEME', env('PUSHER_SCHEME', 'https')),
'webSocketKey' => env('REVERB_APP_KEY', env('PUSHER_APP_KEY')),
// Socket.IO Configuration (Node.js real-time service)
'socketio_url' => env('SOCKETIO_URL', $info->firstWhere('key_name', 'websocket_url')?->value ?? 'smartline-it.com'),
'socketio_path' => env('SOCKETIO_PATH', '/socket.io/'),
// Socket.IO Configuration (camelCase for Flutter)
'socketIOUrl' => env('SOCKETIO_URL', $info->firstWhere('key_name', 'websocket_url')?->value ?? 'smartline-it.com'),
'socketIOPath' => env('SOCKETIO_PATH', '/socket.io/'),
```

---

## 🧪 Testing

### Test with Authenticated Request
```bash
curl -X GET "https://smartline-it.com/api/driver/config/core" \
  -H "Authorization: Bearer YOUR_DRIVER_TOKEN" \
  -H "Accept: application/json" \
  | jq '.content | {webSocketUrl, webSocketPort, websocketScheme, webSocketKey}'
```

### Expected Response
```json
{
  "webSocketUrl": "smartline-it.com",
  "webSocketPort": "443",
  "websocketScheme": "https",
  "webSocketKey": "drivemond",
  "socketIOUrl": "https://smartline-it.com",
  "socketIOPath": "/socket.io/"
}
```

---

## 🔄 Cache

The config is cached per user for 5 minutes:
- Cache key: `driver_config_core_{user_id}`
- TTL: 300 seconds

To clear cache after changes:
```bash
php artisan cache:clear
```

---

## ✅ Verification Checklist

### Reverb WebSocket
- [x] Added `webSocketUrl` field (camelCase)
- [x] Added `webSocketPort` field (camelCase) - returns "443" (nginx port)
- [x] Added `websocketScheme` field (camelCase) - returns "https"
- [x] Added `webSocketKey` field (camelCase) - returns Reverb app key

### Socket.IO
- [x] Added `socketIOUrl` field (camelCase) - returns "https://smartline-it.com"
- [x] Added `socketIOPath` field (camelCase) - returns "/socket.io/"
- [x] Added `socketio_url` field (snake_case) - backward compatibility
- [x] Added `socketio_path` field (snake_case) - backward compatibility

### General
- [x] Fallback to database settings if available
- [x] Fallback to environment variables if database is null
- [x] Maintains backward compatibility with snake_case fields

---

## 📱 Flutter Usage

### Reverb Connection
```dart
// Get config from API
final response = await http.get(
  Uri.parse('https://smartline-it.com/api/driver/config/core'),
  headers: {
    'Authorization': 'Bearer $token',
    'Accept': 'application/json',
  },
);

final config = json.decode(response.body)['content'];

// Use Reverb configuration
final pusher = Pusher(
  config['webSocketKey'], // "drivemond"
  PusherOptions(
    host: config['webSocketUrl'], // "smartline-it.com"
    wsPort: int.parse(config['webSocketPort']), // 443
    wssPort: int.parse(config['webSocketPort']), // 443
    encrypted: config['websocketScheme'] == 'https', // true
  ),
);
```

### Socket.IO Connection
```dart
// Use Socket.IO configuration
import 'package:socket_io_client/socket_io_client.dart' as IO;

final socketIOUrl = config['socketIOUrl']; // "https://smartline-it.com"
final socketIOPath = config['socketIOPath']; // "/socket.io/"

final socket = IO.io(
  socketIOUrl,
  IO.OptionBuilder()
    .setPath(socketIOPath)
    .setTransports(['websocket', 'polling'])
    .setAuth({'token': userToken})
    .build(),
);
```

---

**All Reverb WebSocket fields are now included in the driver config core response!** ✅
