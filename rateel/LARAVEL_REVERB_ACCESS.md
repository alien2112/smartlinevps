# Laravel Reverb - Public Access Configuration

## ✅ Status: PUBLICLY ACCESSIBLE

Laravel Reverb is now publicly accessible through nginx reverse proxy.

---

## 🔌 Connection Details

### Public URL
```
https://smartline-it.com/app
```

### Configuration
- **Internal Port:** 8080
- **Public Path:** `/app`
- **Scheme:** HTTPS
- **Host:** smartline-it.com

---

## 📋 Current Setup

### Nginx Configuration
```nginx
location /app {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_cache_bypass $http_upgrade;
    proxy_read_timeout 86400;
}
```

### Environment Variables (.env)
```env
BROADCAST_DRIVER=redis
REVERB_APP_ID=10000000
REVERB_APP_KEY=drivemond
REVERB_APP_SECRET=drivemond
REVERB_HOST=smartline-it.com
REVERB_PORT=443
REVERB_SCHEME="https"
```

---

## 🔗 How to Connect

### For JavaScript/Web Clients

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: 'drivemond',
    wsHost: 'smartline-it.com',
    wsPort: 443,
    wssPort: 443,
    forceTLS: true,
    enabledTransports: ['ws', 'wss'],
});
```

### For Flutter Apps

Use the `laravel_echo` package:

```dart
import 'package:laravel_echo/laravel_echo.dart';
import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';

final echo = LaravelEcho(
  PusherChannelsFlutter.getInstance(),
  options: PusherChannelsOptions(
    cluster: null,
    host: 'smartline-it.com',
    wsPort: 443,
    wssPort: 443,
    encrypted: true,
    authEndpoint: 'https://smartline-it.com/broadcasting/auth',
    auth: {
      'headers': {
        'Authorization': 'Bearer YOUR_JWT_TOKEN',
        'Accept': 'application/json',
      }
    },
  ),
);

// Connect
await echo.connect();

// Listen to channels
echo.channel('ride-request.123')
    .listen('RideRequestCreated', (e) {
      print('Ride request created: ${e}');
    });
```

---

## 📡 Available Channels

Based on `routes/channels.php`, you can subscribe to:

### Customer Channels
- `customer-ride-chat.{id}`
- `ride-chat.{id}`
- `driver-trip-accepted.{id}`
- `driver-trip-started.{id}`
- `driver-trip-cancelled.{id}`
- `driver-trip-completed.{id}`
- `driver-payment-received.{id}`

### Driver Channels
- `driver-ride-chat.{id}`
- `another-driver-trip-accepted.{id}.{userId}`
- `customer-trip-cancelled-after-ongoing.{id}`
- `customer-trip-cancelled.{id}.{userId}`
- `customer-coupon-applied.{id}`
- `customer-coupon-removed.{id}`
- `customer-trip-request.{id}`
- `customer-trip-payment-successful.{id}`

### Public Channels
- `App.Models.User.{id}`
- `ride-request.{id}`
- `message`
- `store-driver-last-location`

---

## 🔐 Authentication

### Broadcasting Auth Endpoint
```
POST https://smartline-it.com/broadcasting/auth
```

**Headers:**
```
Authorization: Bearer YOUR_JWT_TOKEN
Accept: application/json
```

**Body:**
```json
{
  "socket_id": "123.456",
  "channel_name": "private-ride-request.123"
}
```

---

## 🧪 Testing

### Test WebSocket Connection
```bash
# Test if Reverb is accessible (will return 404 for HTTP GET - this is normal)
curl -I https://smartline-it.com/app

# Test broadcasting auth
curl -X POST https://smartline-it.com/broadcasting/auth \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  -d '{"socket_id":"123.456","channel_name":"private-test"}'
```

### Check Reverb Status
```bash
# Check if Reverb is running
ps aux | grep "reverb:start"

# Check port
netstat -tlnp | grep 8080
```

---

## 🔄 Service Management

### Start Reverb
```bash
cd /var/www/laravel/smartlinevps/rateel
php artisan reverb:start
```

### Restart Reverb
```bash
cd /var/www/laravel/smartlinevps/rateel
php artisan reverb:restart
```

### Stop Reverb
```bash
# Find and kill the process
pkill -f "reverb:start"
```

---

## ⚠️ Important Notes

1. **404 Response is Normal**: HTTP GET requests to `/app` will return 404. This is expected - Reverb only handles WebSocket connections.

2. **Broadcasting Driver**: Currently set to `redis`. To use Reverb, change to:
   ```env
   BROADCAST_DRIVER=reverb
   ```

3. **Two Socket Services**: You now have:
   - **Node.js Socket.IO**: `https://smartline-it.com/socket.io/` (for real-time ride matching)
   - **Laravel Reverb**: `https://smartline-it.com/app` (for Laravel broadcasting channels)

4. **Choose Based on Use Case**:
   - Use **Node.js Socket.IO** for: Real-time location tracking, ride matching, driver dispatch
   - Use **Laravel Reverb** for: Laravel broadcasting events, channel-based messaging

---

## 📝 Configuration Files Modified

1. **Nginx**: `/etc/nginx/sites-enabled/smartline`
   - Added `/app` location block for Reverb proxy

2. **Laravel .env**: `/var/www/laravel/smartlinevps/rateel/.env`
   - Updated `REVERB_PORT=443`
   - Updated `REVERB_SCHEME="https"`

---

## ✅ Verification Checklist

- [x] Nginx configuration added
- [x] Nginx reloaded successfully
- [x] Reverb service running on port 8080
- [x] Environment variables updated
- [x] Public URL accessible: `https://smartline-it.com/app`

---

**Laravel Reverb is now publicly accessible!** 🎉
