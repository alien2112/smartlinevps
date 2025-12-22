/**
 * Rate limiter factory with Redis-backed option and in-memory fallback.
 * Returns an async limiter function: (socket, key, config) => Promise<boolean>
 */

const logger = require('./logger');
const { redisRateLimit } = require('./redisRateLimiter');

function createInMemoryRateLimiter() {
  return async (socket, key, { windowMs, max }) => {
    if (!windowMs || !max) return true;

    if (!socket.data) socket.data = {};
    if (!socket.data._rateLimits) socket.data._rateLimits = new Map();

    const now = Date.now();
    const entry = socket.data._rateLimits.get(key);

    if (!entry || now >= entry.resetAt) {
      const nextEntry = { count: 1, resetAt: now + windowMs };
      socket.data._rateLimits.set(key, nextEntry);
      return true;
    }

    if (entry.count >= max) {
      return false;
    }

    entry.count += 1;
    return true;
  };
}

function createRateLimiter(redisClient, useRedis) {
  const memoryLimiter = createInMemoryRateLimiter();

  if (useRedis && redisClient) {
    return async (socket, key, opts) => {
      try {
        const allowed = await redisRateLimit(redisClient, socket, key, opts);
        return allowed;
      } catch (err) {
        logger.warn('Falling back to in-memory limiter', { error: err.message, key });
        return memoryLimiter(socket, key, opts);
      }
    };
  }

  return memoryLimiter;
}

module.exports = { createRateLimiter };
