// ============================================
// 🔐 ADMIN AUTHENTICATION MIDDLEWARE (Production-Ready)
// ============================================

const crypto = require('crypto');

// Rate limiting for auth failures
const authFailures = new Map();
const MAX_AUTH_FAILURES = 5;
const AUTH_BLOCK_DURATION = 15 * 60 * 1000; // 15 minutes

/**
 * Clean up expired auth failure records
 */
setInterval(() => {
    const now = Date.now();
    for (const [ip, data] of authFailures.entries()) {
        if (now - data.lastAttempt > AUTH_BLOCK_DURATION) {
            authFailures.delete(ip);
        }
    }
}, 60000); // Every minute

/**
 * Check if IP is blocked due to too many auth failures
 */
function isIpBlocked(ip) {
    const record = authFailures.get(ip);
    if (!record) return false;

    if (record.failures >= MAX_AUTH_FAILURES) {
        if (Date.now() - record.lastAttempt < AUTH_BLOCK_DURATION) {
            return true;
        }
        // Block expired, reset
        authFailures.delete(ip);
    }
    return false;
}

/**
 * Record an auth failure
 */
function recordAuthFailure(ip) {
    const record = authFailures.get(ip) || { failures: 0, lastAttempt: 0 };
    record.failures++;
    record.lastAttempt = Date.now();
    authFailures.set(ip, record);
}

/**
 * Timing-safe string comparison to prevent timing attacks
 */
function safeCompare(a, b) {
    if (typeof a !== 'string' || typeof b !== 'string') {
        return false;
    }
    if (a.length !== b.length) {
        // Still do a comparison to maintain consistent timing
        crypto.timingSafeEqual(Buffer.from(a), Buffer.from(a));
        return false;
    }
    return crypto.timingSafeEqual(Buffer.from(a), Buffer.from(b));
}

/**
 * Admin API Key Authentication Middleware
 * Protects all /admin/* endpoints
 */
function adminAuth(req, res, next) {
    const clientIp = req.ip || req.connection.remoteAddress || 'unknown';

    // Check if IP is blocked
    if (isIpBlocked(clientIp)) {
        return res.status(429).json({
            success: false,
            error: 'Too many authentication failures. Try again later.',
            code: 'AUTH_BLOCKED',
            retryAfter: Math.ceil(AUTH_BLOCK_DURATION / 1000)
        });
    }

    const validApiKey = process.env.ADMIN_API_KEY;

    // In production, API key MUST be set
    if (!validApiKey) {
        if (process.env.NODE_ENV === 'production') {
            console.error('❌ CRITICAL: ADMIN_API_KEY not set in production environment!');
            return res.status(500).json({
                success: false,
                error: 'Server configuration error.',
                code: 'CONFIG_ERROR'
            });
        }
        // Development mode warning
        console.warn('⚠️  WARNING: ADMIN_API_KEY not set. Admin endpoints are unprotected!');
        return next();
    }

    // Get API key from headers
    const apiKey = req.headers['x-api-key'] ||
        req.headers['authorization']?.replace(/^Bearer\s+/i, '');

    if (!apiKey) {
        recordAuthFailure(clientIp);
        return res.status(401).json({
            success: false,
            error: 'API key required. Provide via X-API-Key header.',
            code: 'MISSING_API_KEY'
        });
    }

    // Use timing-safe comparison
    if (!safeCompare(apiKey, validApiKey)) {
        recordAuthFailure(clientIp);
        return res.status(401).json({
            success: false,
            error: 'Invalid API key.',
            code: 'INVALID_API_KEY'
        });
    }

    // Auth successful - clear any failure records
    authFailures.delete(clientIp);
    next();
}

/**
 * Get auth failure stats (for monitoring)
 */
function getAuthStats() {
    return {
        blockedIps: Array.from(authFailures.entries())
            .filter(([_, data]) => data.failures >= MAX_AUTH_FAILURES)
            .length,
        totalTrackedIps: authFailures.size
    };
}

module.exports = { adminAuth, getAuthStats };


