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
const Redis = require('ioredis');

const logger = require('./utils/logger');
const config = require('./config/config');
const redisClient = require('./config/redis');
const { authenticateSocket } = require('./middleware/auth');
const { createRateLimiter } = require('./utils/rateLimiter');
const LocationService = require('./services/LocationService');
const DriverMatchingService = require('./services/DriverMatchingService');
const RedisEventBus = require('./services/RedisEventBus');
const RideTimeoutService = require('./services/RideTimeoutService');

// OBSERVABILITY: Import deep logging utilities
const {
  observeConnection,
  observeEventHandler,
  observeBroadcast,
  generateTraceId,
  createLogEntry,
  getMetrics
} = require('./utils/observability');

const app = express();
const httpServer = createServer(app);

// Middleware
app.use(helmet());
app.use(cors());
app.use(compression());
app.use(express.json());

// Optional metrics/health authentication
const metricsApiKey = config.metrics?.apiKey;
const requireMetricsAuth = (req, res) => {
  if (!metricsApiKey) return true;
  const provided = req.headers['x-api-key'] || req.query.api_key;
  if (provided !== metricsApiKey) {
    res.status(401).json({ error: 'Unauthorized' });
    return false;
  }
  return true;
};

// Health check endpoint
app.get('/health', (req, res) => {
  if (!requireMetricsAuth(req, res)) return;

  // Get Redis health status if using resilient client
  const redisHealth = redisClient.getHealthStatus ? redisClient.getHealthStatus() : {
    mode: config.redis.enabled ? 'redis' : 'in-memory',
    redisHealthy: true,
    isUsingFallback: false
  };

  res.json({
    status: 'ok',
    service: 'smartline-realtime',
    uptime: process.uptime(),
    timestamp: new Date().toISOString(),
    connections: io.engine.clientsCount,
    redisAdapter: config.redis.enabled,
    redis: redisHealth
  });
});

// Metrics endpoint
app.get('/metrics', async (req, res) => {
  if (!requireMetricsAuth(req, res)) return;
  const [activeDrivers, activeRides] = await Promise.all([
    locationService.getActiveDriversCount(),
    locationService.getActiveRidesCount()
  ]);

  // Get Redis health status
  const redisHealth = redisClient.getHealthStatus ? redisClient.getHealthStatus() : {
    mode: config.redis.enabled ? 'redis' : 'in-memory',
    redisHealthy: true,
    isUsingFallback: false
  };

  res.json({
    connections: io.engine.clientsCount,
    activeDrivers,
    activeRides,
    memory: process.memoryUsage(),
    uptime: process.uptime(),
    redisAdapter: config.redis.enabled,
    redis: redisHealth
  });
});

// Socket.IO server with configuration (loaded from config)
const io = new Server(httpServer, {
  cors: {
    origin: config.websocket.corsOrigin,
    methods: ['GET', 'POST']
  },
  pingTimeout: config.websocket.pingTimeout,
  pingInterval: config.websocket.pingInterval,
  maxHttpBufferSize: config.websocket.maxHttpBufferSize,
  transports: config.websocket.transports
});

const rateLimit = createRateLimiter(redisClient, config.redis.enabled);

// Attach Redis adapter for horizontal scaling (cross-instance room broadcasts)
if (config.redis.enabled) {
  const { createAdapter } = require('@socket.io/redis-adapter');

  const redisConfig = {
    host: config.redis.host,
    port: config.redis.port,
    password: config.redis.password || undefined,
    db: config.redis.db
  };

  const pubClient = new Redis(redisConfig);
  const subClient = pubClient.duplicate();

  Promise.all([
    new Promise((resolve) => pubClient.on('ready', resolve)),
    new Promise((resolve) => subClient.on('ready', resolve))
  ]).then(() => {
    io.adapter(createAdapter(pubClient, subClient));
    logger.info('Socket.IO Redis adapter attached for horizontal scaling');
  }).catch((err) => {
    logger.error('Failed to attach Redis adapter', { error: err.message });
  });
} else {
  logger.warn('Socket.IO running in single-instance mode (Redis disabled)');
}

// Initialize services
const locationService = new LocationService(redisClient, io);
const driverMatchingService = new DriverMatchingService(redisClient, io, locationService);
const redisEventBus = new RedisEventBus(redisClient, io, locationService, driverMatchingService);
const rideTimeoutService = new RideTimeoutService(redisClient, io, driverMatchingService);

// Socket.IO middleware for authentication
io.use((socket, next) => {
  if (io.engine.clientsCount >= config.performance.maxConnectionsPerInstance) {
    return next(new Error('Server overloaded'));
  }
  next();
});
io.use(authenticateSocket);

// Socket.IO connection handler
io.on('connection', (socket) => {
  const connectionStartTime = Date.now();
  const userId = socket.user.id;
  const userType = socket.user.type; // 'driver' or 'customer'

  // OBSERVABILITY: Wrap socket with logging
  const observeDisconnect = observeConnection(socket, logger);

  // Load security settings from config
  const enforceRideRoomAuth = config.security.enforceRideSubscriptionAuth;
  const disconnectOfflineGraceMs = config.security.disconnectOfflineGraceMs;

  // OBSERVABILITY: Log connection with full context
  logger.info('OBSERVE: Client connection established', createLogEntry({
    event_type: 'connection_established',
    source: 'socket',
    status: 'connected',
    socket_id: socket.id,
    user_id: userId,
    user_type: userType,
    auth_method: socket.handshake?.auth ? 'token' : 'unknown',
    transport: socket.conn?.transport?.name
  }));

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
      const handlerStart = Date.now();
      const traceId = generateTraceId();

      // OBSERVABILITY: Log event received
      logger.info('OBSERVE: driver:online received', createLogEntry({
        event_type: 'socket_event',
        source: 'socket',
        status: 'started',
        trace_id: traceId,
        socket_id: socket.id,
        user_id: userId,
        event_name: 'driver:online',
        payload: data ? JSON.stringify(data).substring(0, 200) : null
      }));

      if (!(await rateLimit(socket, 'driver:online', config.rateLimiting.driverOnline))) {
        logger.warn('OBSERVE: driver:online rate limited', createLogEntry({
          event_type: 'rate_limited',
          source: 'socket',
          trace_id: traceId,
          socket_id: socket.id,
          user_id: userId,
          event_name: 'driver:online'
        }));
        return;
      }

      try {
        await locationService.setDriverOnline(userId, data);
        socket.emit('driver:online:success', { message: 'You are now online', trace_id: traceId });

        // OBSERVABILITY: Log success
        logger.info('OBSERVE: driver:online completed', createLogEntry({
          event_type: 'socket_event',
          source: 'socket',
          status: 'success',
          trace_id: traceId,
          socket_id: socket.id,
          user_id: userId,
          event_name: 'driver:online',
          duration_ms: Date.now() - handlerStart
        }));
      } catch (error) {
        logger.error('OBSERVE: driver:online error', createLogEntry({
          event_type: 'socket_event',
          source: 'socket',
          status: 'error',
          trace_id: traceId,
          socket_id: socket.id,
          user_id: userId,
          event_name: 'driver:online',
          error: error.message,
          duration_ms: Date.now() - handlerStart
        }));
        socket.emit('error', { message: 'Failed to go online', trace_id: traceId });
      }
    });

    // Driver goes offline
    socket.on('driver:offline', async () => {
      const handlerStart = Date.now();
      const traceId = generateTraceId();

      logger.info('OBSERVE: driver:offline received', createLogEntry({
        event_type: 'socket_event',
        source: 'socket',
        status: 'started',
        trace_id: traceId,
        socket_id: socket.id,
        user_id: userId,
        event_name: 'driver:offline'
      }));

      if (!(await rateLimit(socket, 'driver:offline', config.rateLimiting.driverOffline))) {
        return;
      }

      try {
        await locationService.setDriverOffline(userId);
        socket.emit('driver:offline:success', { message: 'You are now offline', trace_id: traceId });

        logger.info('OBSERVE: driver:offline completed', createLogEntry({
          event_type: 'socket_event',
          source: 'socket',
          status: 'success',
          trace_id: traceId,
          socket_id: socket.id,
          user_id: userId,
          event_name: 'driver:offline',
          duration_ms: Date.now() - handlerStart
        }));
      } catch (error) {
        logger.error('OBSERVE: driver:offline error', createLogEntry({
          event_type: 'socket_event',
          source: 'socket',
          status: 'error',
          trace_id: traceId,
          socket_id: socket.id,
          user_id: userId,
          event_name: 'driver:offline',
          error: error.message,
          duration_ms: Date.now() - handlerStart
        }));
      }
    });

    // Driver location update (high frequency - minimal logging)
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

    // Driver accepts ride - CRITICAL EVENT FOR OBSERVABILITY
    socket.on('driver:accept:ride', async (data) => {
      const handlerStart = Date.now();
      const traceId = data?.trace_id || generateTraceId();
      const rideId = data?.rideId;

      // OBSERVABILITY: Log this critical event with full detail
      logger.info('OBSERVE: driver:accept:ride received', createLogEntry({
        event_type: 'socket_event',
        source: 'socket',
        status: 'started',
        trace_id: traceId,
        socket_id: socket.id,
        user_id: userId,
        driver_id: userId,
        event_name: 'driver:accept:ride',
        ride_id: rideId,
        payload: data ? JSON.stringify(data).substring(0, 500) : null,
        timestamp_received: new Date().toISOString()
      }));

      if (!(await rateLimit(socket, 'driver:accept:ride', config.rateLimiting.driverAcceptRide))) {
        logger.warn('OBSERVE: driver:accept:ride rate limited', createLogEntry({
          event_type: 'rate_limited',
          source: 'socket',
          trace_id: traceId,
          socket_id: socket.id,
          user_id: userId,
          driver_id: userId,
          ride_id: rideId,
          event_name: 'driver:accept:ride'
        }));
        socket.emit('ride:accept:failed', { rideId: data?.rideId, message: 'Too many attempts, slow down', trace_id: traceId });
        return;
      }

      try {
        if (!rideId) {
          logger.warn('OBSERVE: driver:accept:ride missing rideId', createLogEntry({
            event_type: 'validation_error',
            source: 'socket',
            trace_id: traceId,
            socket_id: socket.id,
            user_id: userId,
            event_name: 'driver:accept:ride'
          }));
          socket.emit('ride:accept:failed', { rideId: null, message: 'Missing rideId', trace_id: traceId });
          return;
        }

        logger.info('OBSERVE: Calling handleDriverAcceptRide', createLogEntry({
          event_type: 'service_call',
          source: 'socket',
          status: 'calling',
          trace_id: traceId,
          driver_id: userId,
          ride_id: rideId,
          service: 'DriverMatchingService',
          method: 'handleDriverAcceptRide'
        }));

        await driverMatchingService.handleDriverAcceptRide(userId, rideId);

        logger.info('OBSERVE: driver:accept:ride completed', createLogEntry({
          event_type: 'socket_event',
          source: 'socket',
          status: 'success',
          trace_id: traceId,
          socket_id: socket.id,
          user_id: userId,
          driver_id: userId,
          ride_id: rideId,
          event_name: 'driver:accept:ride',
          duration_ms: Date.now() - handlerStart
        }));
      } catch (error) {
        logger.error('OBSERVE: driver:accept:ride error', createLogEntry({
          event_type: 'socket_event',
          source: 'socket',
          status: 'error',
          trace_id: traceId,
          socket_id: socket.id,
          user_id: userId,
          driver_id: userId,
          ride_id: rideId,
          event_name: 'driver:accept:ride',
          error: error.message,
          error_stack: error.stack?.substring(0, 500),
          duration_ms: Date.now() - handlerStart
        }));
        socket.emit('error', { message: 'Failed to accept ride', trace_id: traceId });
      }
    });
  }

  // CUSTOMER EVENTS
  if (userType === 'customer') {
    // Subscribe to ride updates
    socket.on('customer:subscribe:ride', async (data, ack) => {
      if (!(await rateLimit(socket, 'customer:subscribe:ride', config.rateLimiting.customerSubscribeRide))) {
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
    socket.on('customer:unsubscribe:ride', async (data) => {
      if (!(await rateLimit(socket, 'customer:unsubscribe:ride', config.rateLimiting.customerUnsubscribeRide))) {
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
  socket.on('ping', async () => {
    if (!(await rateLimit(socket, 'ping', config.rateLimiting.ping))) {
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

// Start Redis event listener (only on worker 0 to avoid duplicate event processing)
// PM2 cluster mode sets NODE_APP_INSTANCE (0, 1, 2, etc.)
const workerId = process.env.NODE_APP_INSTANCE || '0';
if (workerId === '0') {
  logger.info('Starting Redis Event Bus on worker 0 (single subscriber pattern)');
  redisEventBus.start();
  rideTimeoutService.start();
} else {
  logger.info(`Worker ${workerId} - Skipping Redis Event Bus (handled by worker 0 to prevent duplicates)`);
}

// Graceful shutdown
process.on('SIGTERM', async () => {
  logger.info('SIGTERM received, shutting down gracefully');

  // Only stop Redis services on worker 0 (they only run there)
  if (workerId === '0') {
    rideTimeoutService.stop();
  }

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

  // Only stop Redis services on worker 0 (they only run there)
  if (workerId === '0') {
    rideTimeoutService.stop();
  }

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
const PORT = config.server.port;
const HOST = config.server.host;

httpServer.listen(PORT, HOST, () => {
  logger.info(`SmartLine Real-time Service started`, {
    port: PORT,
    host: HOST,
    environment: config.server.nodeEnv
  });
});

// Export for testing
module.exports = { app, io, httpServer };
