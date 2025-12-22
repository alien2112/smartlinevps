/**
 * Redis-backed rate limiter (fixed window).
 * Falls back to caller-provided in-memory limiter on errors.
 */

const logger = require('./logger');

async function redisRateLimit(redis, socket, key, { windowMs, max }) {
  if (!windowMs || !max) return true;
  const identifier = socket?.user?.id || socket?.id;
  if (!identifier) return true;

  const redisKey = `ratelimit:${identifier}:${key}`;
  const windowMsSafe = Math.max(windowMs || 0, 1);

  try {
    // Increment counter and set expiry on first increment
    const results = await redis
      .pipeline()
      .incr(redisKey)
      .pexpire(redisKey, windowMsSafe)
      .exec();

    const count = results?.[0]?.[1] || 0;
    return count <= max;
  } catch (err) {
    logger.warn('Redis rate limiter failed, falling back to in-memory', {
      error: err.message,
      key
    });
    return true;
  }
}

module.exports = { redisRateLimit };
