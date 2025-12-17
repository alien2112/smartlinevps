/**
 * SmartLine Real-time Service
 * Handles WebSocket connections, live location tracking, and driver matching
 */

require('dotenv').config();
const express = require('express');
const { createServer } = require('http');
const { Server } = require('socket.io');
const helmet = require('helmet');
const cors = require('cors');
const compression = require('compression');

const logger = require('./utils/logger');
const redisClient = require('./config/redis');
const { authenticateSocket } = require('./middleware/auth');
const { rateLimit } = require('./utils/rateLimiter');
const LocationService = require('./services/LocationService');
const DriverMatchingService = require('./services/DriverMatchingService');
const RedisEventBus = require('./services/RedisEventBus');

const app = express();
const httpServer = createServer(app);

// Middleware
app.use(helmet());
app.use(cors());
app.use(compression());
app.use(express.json());

// Health check endpoint
app.get('/health', (req, res) => {
  res.json({
    status: 'ok',
    service: 'smartline-realtime',
    uptime: process.uptime(),
    timestamp: new Date().toISOString(),
    connections: io.engine.clientsCount
  });
});

// Metrics endpoint
app.get('/metrics', async (req, res) => {
  const [activeDrivers, activeRides] = await Promise.all([
    locationService.getActiveDriversCount(),
    locationService.getActiveRidesCount()
  ]);

  res.json({
    connections: io.engine.clientsCount,
    activeDrivers,
    activeRides,
    memory: process.memoryUsage(),
    uptime: process.uptime()
  });
});

// Socket.IO server with configuration
const io = new Server(httpServer, {
  cors: {
    origin: process.env.WS_CORS_ORIGIN || '*',
    methods: ['GET', 'POST']
  },
  pingTimeout: parseInt(process.env.WS_PING_TIMEOUT) || 60000,
  pingInterval: parseInt(process.env.WS_PING_INTERVAL) || 25000,
  maxHttpBufferSize: 1e6, // 1 MB
  transports: ['websocket', 'polling']
});

// Initialize services
const locationService = new LocationService(redisClient, io);
const driverMatchingService = new DriverMatchingService(redisClient, io, locationService);
const redisEventBus = new RedisEventBus(redisClient, io, locationService, driverMatchingService);

// Socket.IO middleware for authentication
io.use((socket, next) => {
  const maxConnections = parseInt(process.env.MAX_CONNECTIONS_PER_INSTANCE) || 10000;
  if (io.engine.clientsCount >= maxConnections) {
    return next(new Error('Server overloaded'));
  }
  next();
});
io.use(authenticateSocket);

// Socket.IO connection handler
io.on('connection', (socket) => {
  const userId = socket.user.id;
  const userType = socket.user.type; // 'driver' or 'customer'
  const enforceRideRoomAuth = process.env.ENFORCE_RIDE_SUBSCRIPTION_AUTH !== 'false';
  const disconnectOfflineGraceMs = parseInt(process.env.DISCONNECT_OFFLINE_GRACE_MS) || 30000;

  logger.info(`Client connected`, {
    socketId: socket.id,
    userId,
    userType
  });

  // Join user-specific room
  socket.join(`user:${userId}`);
  if (userType === 'driver') {
    socket.join(`drivers`);
  } else if (userType === 'customer') {
    socket.join(`customers`);
  }

  // DRIVER EVENTS
  if (userType === 'driver') {
    // Driver goes online
    socket.on('driver:online', async (data) => {
      if (!rateLimit(socket, 'driver:online', { windowMs: 60_000, max: 10 })) {
        return;
      }

      try {
        await locationService.setDriverOnline(userId, data);
        socket.emit('driver:online:success', { message: 'You are now online' });
        logger.info(`Driver went online`, { driverId: userId });
      } catch (error) {
        logger.error('Error setting driver online', { error: error.message, userId });
        socket.emit('error', { message: 'Failed to go online' });
      }
    });

    // Driver goes offline
    socket.on('driver:offline', async () => {
      if (!rateLimit(socket, 'driver:offline', { windowMs: 60_000, max: 10 })) {
        return;
      }

      try {
        await locationService.setDriverOffline(userId);
        socket.emit('driver:offline:success', { message: 'You are now offline' });
        logger.info(`Driver went offline`, { driverId: userId });
      } catch (error) {
        logger.error('Error setting driver offline', { error: error.message, userId });
      }
    });

    // Driver location update (high frequency)
    socket.on('driver:location', async (data) => {
      try {
        const locationData = {
          latitude: data?.latitude,
          longitude: data?.longitude,
          speed: data?.speed,
          heading: data?.heading,
          accuracy: data?.accuracy
        };

        await locationService.updateDriverLocation(userId, locationData);
        // No acknowledgment to reduce overhead
      } catch (error) {
        logger.error('Error updating driver location', { error: error.message, userId });
      }
    });

    // Driver accepts ride
    socket.on('driver:accept:ride', async (data) => {
      if (!rateLimit(socket, 'driver:accept:ride', { windowMs: 10_000, max: 5 })) {
        socket.emit('ride:accept:failed', { rideId: data?.rideId, message: 'Too many attempts, slow down' });
        return;
      }

      try {
        const rideId = data?.rideId;
        if (!rideId) {
          socket.emit('ride:accept:failed', { rideId: null, message: 'Missing rideId' });
          return;
        }

        await driverMatchingService.handleDriverAcceptRide(userId, rideId);
      } catch (error) {
        logger.error('Error accepting ride', { error: error.message, userId, rideId: data?.rideId });
        socket.emit('error', { message: 'Failed to accept ride' });
      }
    });
  }

  // CUSTOMER EVENTS
  if (userType === 'customer') {
    // Subscribe to ride updates
    socket.on('customer:subscribe:ride', async (data, ack) => {
      if (!rateLimit(socket, 'customer:subscribe:ride', { windowMs: 60_000, max: 30 })) {
        ack?.({ success: false, message: 'Rate limited' });
        return;
      }

      const rideId = data?.rideId;
      if (!rideId) {
        ack?.({ success: false, message: 'Missing rideId' });
        return;
      }

      if (enforceRideRoomAuth) {
        const allowed = await locationService.canUserAccessRide(userId, userType, rideId);
        if (!allowed) {
          ack?.({ success: false, message: 'Not authorized for this ride' });
          return;
        }
      }

      socket.join(`ride:${rideId}`);
      logger.info(`Customer subscribed to ride`, { customerId: userId, rideId });
      ack?.({ success: true });
    });

    // Unsubscribe from ride updates
    socket.on('customer:unsubscribe:ride', (data) => {
      if (!rateLimit(socket, 'customer:unsubscribe:ride', { windowMs: 60_000, max: 60 })) {
        return;
      }

      const rideId = data?.rideId;
      if (!rideId) return;

      socket.leave(`ride:${rideId}`);
      logger.info(`Customer unsubscribed from ride`, { customerId: userId, rideId });
    });
  }

  // COMMON EVENTS
  // Heartbeat/ping
  socket.on('ping', () => {
    if (!rateLimit(socket, 'ping', { windowMs: 10_000, max: 50 })) {
      return;
    }
    socket.emit('pong', { timestamp: Date.now() });
  });

  // Disconnection
  socket.on('disconnect', async (reason) => {
    logger.info(`Client disconnected`, {
      socketId: socket.id,
      userId,
      userType,
      reason
    });

    if (userType === 'driver') {
      // Don't immediately set driver offline, they might reconnect
      // Set a grace period
      await locationService.setDriverDisconnected(userId);

      // Best-effort cleanup for dead connections.
      setTimeout(async () => {
        try {
          const status = await redisClient.hget(`driver:status:${userId}`, 'status');
          if (!status || status === 'disconnected') {
            await locationService.setDriverOffline(userId);
          }
        } catch (error) {
          logger.error('Disconnect cleanup error', { error: error.message, userId });
        }
      }, disconnectOfflineGraceMs);
    }
  });

  // Error handling
  socket.on('error', (error) => {
    logger.error('Socket error', { error: error.message, userId });
  });
});

// Start Redis event listener
redisEventBus.start();

// Graceful shutdown
process.on('SIGTERM', async () => {
  logger.info('SIGTERM received, shutting down gracefully');

  io.close(() => {
    logger.info('All Socket.IO connections closed');
  });

  await redisClient.quit();
  httpServer.close(() => {
    logger.info('HTTP server closed');
    process.exit(0);
  });
});

process.on('SIGINT', async () => {
  logger.info('SIGINT received, shutting down gracefully');

  io.close(() => {
    logger.info('All Socket.IO connections closed');
  });

  await redisClient.quit();
  httpServer.close(() => {
    logger.info('HTTP server closed');
    process.exit(0);
  });
});

// Start server
const PORT = process.env.PORT || 3000;
const HOST = process.env.HOST || '0.0.0.0';

httpServer.listen(PORT, HOST, () => {
  logger.info(`SmartLine Real-time Service started`, {
    port: PORT,
    host: HOST,
    environment: process.env.NODE_ENV
  });
});

// Export for testing
module.exports = { app, io, httpServer };
