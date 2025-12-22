# Installing Redis on Windows

Redis is required for Laravel and Node.js to communicate with each other.

---

## Method 1: Redis for Windows (Easiest)

### Step 1: Download
1. Go to: https://github.com/microsoftarchive/redis/releases
2. Download: `Redis-x64-3.0.504.msi` (or latest .msi file)

### Step 2: Install
1. Run the installer
2. Keep default settings
3. Check "Add the Redis installation folder to the PATH"
4. Complete installation

### Step 3: Start Redis
Redis starts automatically as a Windows service after installation.

### Step 4: Verify
```bash
redis-cli ping
```
Expected output: `PONG`

---

## Method 2: Memurai (Redis Alternative for Windows)

### Step 1: Download
1. Go to: https://www.memurai.com/get-memurai
2. Download the installer

### Step 2: Install
1. Run the installer
2. Follow installation wizard
3. Memurai runs as a Windows service

### Step 3: Verify
```bash
memurai-cli ping
```
Expected output: `PONG`

---

## Method 3: Docker (If You Have Docker Desktop)

### Step 1: Run Redis Container
```bash
docker run -d --name redis -p 6379:6379 redis:latest
```

### Step 2: Verify
```bash
docker ps
redis-cli ping
```

---

## Method 4: WSL (Windows Subsystem for Linux)

### Step 1: Enable WSL
```powershell
# Run PowerShell as Administrator
wsl --install
```

### Step 2: Install Redis in WSL
```bash
wsl
sudo apt-get update
sudo apt-get install redis-server
```

### Step 3: Start Redis
```bash
sudo service redis-server start
```

### Step 4: Verify
```bash
redis-cli ping
```

---

## After Installing Redis

### Step 1: Enable Redis in Node.js

Edit `realtime-service/.env`:
```env
REDIS_ENABLED=true
```

### Step 2: Restart Node.js Service
```bash
cd realtime-service
npm run dev
```

### Step 3: Verify Connection
You should see in Node.js logs:
```
[info]: Connected to Redis successfully
[info]: Subscribed to Laravel events
```

### Step 4: Test Redis
```bash
# Terminal 1: Start monitoring
redis-cli MONITOR

# Terminal 2: Test publish
redis-cli
PUBLISH test "Hello World"

# You should see the message in Terminal 1
```

---

## Troubleshooting

### Redis won't start
**Windows Service:**
```powershell
# Check service status
Get-Service -Name Redis

# Start service
Start-Service -Name Redis
```

### Can't connect to Redis
**Check if Redis is listening:**
```bash
netstat -ano | findstr :6379
```

**Check Redis config:**
```bash
redis-cli config get bind
```

Should show: `127.0.0.1` or `0.0.0.0`

### Redis installed but CLI not found
**Add to PATH:**
1. Search for "Environment Variables" in Windows
2. Edit "Path" variable
3. Add Redis installation directory (usually `C:\Program Files\Redis`)
4. Restart terminal

---

## Testing Redis After Installation

### Basic Commands
```bash
# Ping
redis-cli ping

# Set a value
redis-cli SET mykey "Hello"

# Get a value
redis-cli GET mykey

# List all keys
redis-cli KEYS *

# Monitor all commands
redis-cli MONITOR
```

### Test with Laravel
```bash
# Start Laravel
php artisan serve

# In another terminal, monitor Redis
redis-cli MONITOR

# Create a ride request in Postman
# You should see PUBLISH commands in Redis monitor
```

---

## Recommended: Redis for Windows

**Pros:**
- Easy installation
- Runs as Windows service
- Compatible with all Redis features

**Download Link:**
https://github.com/microsoftarchive/redis/releases/download/win-3.0.504/Redis-x64-3.0.504.msi

**Installation is 2 clicks and takes 30 seconds!**

---

## What to Do Now

1. **Choose a method** (Method 1 recommended)
2. **Install Redis**
3. **Verify it works**: `redis-cli ping`
4. **Enable in Node.js**: Set `REDIS_ENABLED=true` in `realtime-service/.env`
5. **Restart Node.js**: `npm run dev`
6. **Start testing!**

---

**Once Redis is installed, you'll have full Laravel ↔ Node.js integration!**
