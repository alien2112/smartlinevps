// ============================================
// 💾 RESPONSE CACHING
// ============================================

const NodeCache = require('node-cache');

// Cache configuration
const cache = new NodeCache({
    stdTTL: 300,        // 5 minutes default TTL
    checkperiod: 60,    // Check for expired keys every 60s
    maxKeys: 1000,      // Limit cache size to prevent memory issues
    useClones: false    // Better performance
});

// Common query patterns to cache
const CACHEABLE_PATTERNS = [
    /^(hi|hello|hey|مرحبا|السلام|اهلا)/i,
    /^(thanks|thank you|شكرا|مشكور)/i,
    /^(help|مساعدة|عايز مساعدة)/i,
    /^(cancel|الغاء|إلغاء)/i,
    /^(bye|goodbye|مع السلامة)/i
];

/**
 * Check if a message should be cached
 */
function shouldCache(message) {
    if (!message || typeof message !== 'string') return false;
    const normalized = message.trim().toLowerCase();

    // Cache only short, common queries
    if (normalized.length > 50) return false;

    // Check against cacheable patterns
    return CACHEABLE_PATTERNS.some(pattern => pattern.test(normalized));
}

/**
 * Generate cache key from user message
 */
function getCacheKey(message, userId = '') {
    const normalized = message.trim().toLowerCase();
    // Simple hash (not cryptographic, just for cache keys)
    let hash = 0;
    for (let i = 0; i < normalized.length; i++) {
        const char = normalized.charCodeAt(i);
        hash = ((hash << 5) - hash) + char;
        hash = hash & hash;
    }
    return `chat:${userId}:${hash}`;
}

/**
 * Get cached response
 */
function get(message, userId = '') {
    if (!shouldCache(message)) return null;

    const key = getCacheKey(message, userId);
    const cached = cache.get(key);

    if (cached) {
        return cached;
    }

    return null;
}

/**
 * Set cached response
 */
function set(message, userId = '', response) {
    if (!shouldCache(message)) return;

    const key = getCacheKey(message, userId);
    // Cache for 5 minutes
    cache.set(key, response, 300);
}

/**
 * Clear cache (for testing/admin)
 */
function clear() {
    cache.flushAll();
}

/**
 * Get cache stats
 */
function getStats() {
    const keys = cache.keys();
    return {
        size: keys.length,
        hits: cache.getStats().hits,
        misses: cache.getStats().misses
    };
}

module.exports = { get, set, clear, getStats, shouldCache };


