/**
 * WebSocket Authentication Middleware
 * Authenticates Socket.IO connections using JWT tokens
 *
 * Issue #25 FIX: Added session caching to avoid repeated Laravel API calls
 * - Validated sessions are cached in Redis for 5 minutes
 * - Reduces authentication latency from ~100ms to ~1ms for cached sessions
 */

const jwt = require('jsonwebtoken');
const axios = require('axios');
const logger = require('../utils/logger');
const config = require('../config/config');

// Load from centralized config
const JWT_SECRET = config.jwt.secret;
const LARAVEL_API_URL = config.laravel.apiUrl;
const LARAVEL_API_TIMEOUT = config.laravel.timeout;

// Session cache settings
const SESSION_CACHE_TTL = 300; // 5 minutes
const SESSION_CACHE_PREFIX = 'ws:session:';

// Redis client (will be set by setRedisClient)
let redisClient = null;

/**
 * Set Redis client for session caching
 */
function setRedisClient(client) {
    redisClient = client;
    logger.info('Auth middleware: Redis client configured for session caching');
}

/**
 * Get cached session from Redis
 */
async function getCachedSession(tokenHash) {
    if (!redisClient) return null;

    try {
        const cached = await redisClient.get(`${SESSION_CACHE_PREFIX}${tokenHash}`);
        if (cached) {
            return JSON.parse(cached);
        }
    } catch (err) {
        logger.debug('Session cache read error', { error: err.message });
    }
    return null;
}

/**
 * Cache session in Redis
 */
async function cacheSession(tokenHash, userData) {
    if (!redisClient) return;

    try {
        await redisClient.setex(
            `${SESSION_CACHE_PREFIX}${tokenHash}`,
            SESSION_CACHE_TTL,
            JSON.stringify(userData)
        );
    } catch (err) {
        logger.debug('Session cache write error', { error: err.message });
    }
}

/**
 * Create a hash of the token for cache key (don't store full token)
 */
function hashToken(token) {
    const crypto = require('crypto');
    return crypto.createHash('sha256').update(token).digest('hex').substring(0, 32);
}

/**
 * Authenticate Socket.IO connection
 * Expects JWT token in auth.token or query.token
 */
async function authenticateSocket(socket, next) {
  try {
    // Get token from handshake
    const token = socket.handshake.auth.token || socket.handshake.query.token;

    if (!token) {
      return next(new Error('Authentication error: No token provided'));
    }

    // DEVELOPMENT MODE: Allow test tokens
    if (config.server.nodeEnv === 'development') {
      if (token === 'test-driver-token') {
        socket.user = {
          id: 'test-driver-id',
          type: 'driver',
          name: 'Test Driver',
          email: 'driver@test.com'
        };
        logger.info('Socket authenticated (test mode - driver)', {
          userId: socket.user.id,
          socketId: socket.id
        });
        return next();
      } else if (token === 'test-customer-token') {
        socket.user = {
          id: 'test-customer-id',
          type: 'customer',
          name: 'Test Customer',
          email: 'customer@test.com'
        };
        logger.info('Socket authenticated (test mode - customer)', {
          userId: socket.user.id,
          socketId: socket.id
        });
        return next();
      } else if (token === 'test-jwt-token') {
        // Legacy token - default to driver
        socket.user = {
          id: 'test-user-id',
          type: 'driver',
          name: 'Test User',
          email: 'test@example.com'
        };
        logger.info('Socket authenticated (test mode - legacy)', {
          userId: socket.user.id,
          socketId: socket.id
        });
        return next();
      }
    }

    // Verify JWT token
    let decoded;
    try {
      decoded = jwt.verify(token, JWT_SECRET);
    } catch (err) {
      return next(new Error('Authentication error: Invalid token'));
    }

    // Validate with Laravel API (optional, can be disabled for performance)
    if (config.laravel.validateWithLaravel) {
      // Issue #25 FIX: Check session cache first
      const tokenHash = hashToken(token);
      const cachedSession = await getCachedSession(tokenHash);

      if (cachedSession) {
        socket.user = cachedSession;
        logger.debug('Socket authenticated (cached)', {
          userId: socket.user.id,
          socketId: socket.id
        });
        return next();
      }

      // Cache miss - validate with Laravel API
      try {
        const response = await axios.get(`${LARAVEL_API_URL}/api/auth/verify`, {
          headers: {
            Authorization: `Bearer ${token}`
          },
          timeout: LARAVEL_API_TIMEOUT
        });

        if (response.data.response_code !== 'default_200') {
          return next(new Error('Authentication error: User not found'));
        }

        // Attach user info to socket
        socket.user = {
          id: response.data.content.id,
          type: response.data.content.user_type, // 'driver' or 'customer'
          name: response.data.content.name,
          email: response.data.content.email
        };

        // Issue #25 FIX: Cache the validated session
        await cacheSession(tokenHash, socket.user);
      } catch (err) {
        logger.error('Laravel API validation failed', { error: err.message });
        return next(new Error('Authentication error: Validation failed'));
      }
    } else {
      // Use decoded JWT data directly (faster, but less secure)
      socket.user = {
        id: decoded.sub || decoded.user_id,
        type: decoded.user_type || 'customer',
        name: decoded.name,
        email: decoded.email
      };
    }

    logger.info('Socket authenticated', {
      userId: socket.user.id,
      userType: socket.user.type,
      socketId: socket.id
    });

    next();
  } catch (error) {
    logger.error('Socket authentication error', { error: error.message });
    next(new Error('Authentication error'));
  }
}

module.exports = { authenticateSocket, setRedisClient };
