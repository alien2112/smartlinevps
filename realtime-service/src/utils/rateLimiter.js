/**
 * Simple in-memory per-socket rate limiter (fixed window).
 * Best-effort only; enforce at the edge (NGINX/LB) for real protection.
 */

function rateLimit(socket, key, { windowMs, max }) {
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
}

module.exports = { rateLimit };

