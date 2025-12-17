/**
 * WebSocket Authentication Middleware
 * Authenticates Socket.IO connections using JWT tokens
 */

const jwt = require('jsonwebtoken');
const axios = require('axios');
const logger = require('../utils/logger');

const JWT_SECRET = process.env.JWT_SECRET;
const LARAVEL_API_URL = process.env.LARAVEL_API_URL;

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
    if (process.env.NODE_ENV === 'development') {
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
    if (process.env.VALIDATE_WITH_LARAVEL === 'true') {
      try {
        const response = await axios.get(`${LARAVEL_API_URL}/api/auth/verify`, {
          headers: {
            Authorization: `Bearer ${token}`
          },
          timeout: 5000
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

module.exports = { authenticateSocket };
