/**
 * Key Cleanup Service
 * Periodic cleanup of stale Redis keys and in-memory caches
 *
 * CRITICAL FOR SCALE: Prevents memory leaks and Redis key explosion
 * Runs every 5 minutes to clean up:
 * - Orphaned ride locks
 * - Stale notification dedupe keys
 * - Stale honeycomb cell data
 * - In-memory cache pruning
 */

const logger = require('../utils/logger');

class KeyCleanupService {
    constructor(redisClient, locationService = null) {
        this.redis = redisClient;
        this.locationService = locationService;

        // Cleanup interval (5 minutes)
        this.cleanupIntervalMs = 5 * 60 * 1000;
        this.intervalId = null;
        this.isRunning = false;

        // Key patterns to clean up
        this.ORPHAN_PATTERNS = [
            'ride:lock:*',           // Ride acceptance locks
            'ride:timeout:lock:*',   // Timeout processing locks
            'ride:timeout:processing:*',
            'laravel:notify:*',      // Laravel notification dedupe
        ];

        // Maximum age for keys without TTL (10 minutes)
        this.MAX_KEY_AGE_SECONDS = 600;
    }

    /**
     * Start the cleanup service
     */
    start() {
        if (this.isRunning) {
            logger.warn('KeyCleanupService already running');
            return;
        }

        this.isRunning = true;
        logger.info('KeyCleanupService started', {
            intervalMs: this.cleanupIntervalMs
        });

        // Run first cleanup after 1 minute (let system stabilize)
        setTimeout(() => this._runCleanup(), 60000);

        // Then run periodically
        this.intervalId = setInterval(
            () => this._runCleanup(),
            this.cleanupIntervalMs
        );
    }

    /**
     * Stop the cleanup service
     */
    stop() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
        this.isRunning = false;
        logger.info('KeyCleanupService stopped');
    }

    /**
     * Run all cleanup tasks
     */
    async _runCleanup() {
        try {
            const startTime = Date.now();
            let totalCleaned = 0;

            // 1. Clean up orphaned lock keys
            for (const pattern of this.ORPHAN_PATTERNS) {
                const cleaned = await this._cleanupOrphanedKeys(pattern);
                totalCleaned += cleaned;
            }

            // 2. Clean up LocationService in-memory cache
            if (this.locationService) {
                const pruned = this._pruneLocationServiceCache();
                totalCleaned += pruned;
            }

            // 3. Clean up stale honeycomb cell keys
            const honeycombCleaned = await this._cleanupHoneycombKeys();
            totalCleaned += honeycombCleaned;

            const duration = Date.now() - startTime;

            if (totalCleaned > 0) {
                logger.info('KeyCleanupService completed', {
                    totalCleaned,
                    durationMs: duration
                });
            }
        } catch (error) {
            logger.error('KeyCleanupService error', { error: error.message });
        }
    }

    /**
     * Clean up keys matching pattern that have no TTL
     */
    async _cleanupOrphanedKeys(pattern) {
        let cleaned = 0;
        let cursor = '0';

        do {
            const [nextCursor, keys] = await this.redis.scan(
                cursor,
                'MATCH', pattern,
                'COUNT', 100
            );
            cursor = nextCursor;

            for (const key of keys) {
                try {
                    const ttl = await this.redis.ttl(key);

                    // TTL = -1 means no expiry set (orphaned)
                    // TTL = -2 means key doesn't exist
                    if (ttl === -1) {
                        // Set a short TTL to expire it
                        await this.redis.expire(key, 60);
                        cleaned++;
                        logger.debug('Set expiry on orphaned key', { key });
                    }
                } catch (keyError) {
                    // Ignore individual key errors
                }
            }
        } while (cursor !== '0');

        return cleaned;
    }

    /**
     * Prune stale entries from LocationService.lastUpdateTime Map
     */
    _pruneLocationServiceCache() {
        if (!this.locationService?.lastUpdateTime) {
            return 0;
        }

        const map = this.locationService.lastUpdateTime;
        const now = Date.now();
        const staleThreshold = 10 * 60 * 1000; // 10 minutes
        let pruned = 0;

        for (const [driverId, lastUpdate] of map.entries()) {
            if (now - lastUpdate > staleThreshold) {
                map.delete(driverId);
                pruned++;
            }
        }

        if (pruned > 0) {
            logger.debug('Pruned stale LocationService cache entries', { pruned });
        }

        return pruned;
    }

    /**
     * Issue #17 FIX: Clean up stale honeycomb cell keys
     * These should have TTLs but may accumulate in edge cases
     * Added missing patterns and more aggressive cleanup
     */
    async _cleanupHoneycombKeys() {
        let cleaned = 0;
        const patterns = [
            'hc:cell:*',
            'hc:demand:*',
            'hc:supply:*',
            'hc:driver:cell:*',
            'hc:drivers:*',      // Issue #17: Added missing pattern
            'hc:settings:*',     // Issue #17: Added missing pattern
        ];

        for (const pattern of patterns) {
            let cursor = '0';

            do {
                const [nextCursor, keys] = await this.redis.scan(
                    cursor,
                    'MATCH', pattern,
                    'COUNT', 100
                );
                cursor = nextCursor;

                for (const key of keys) {
                    try {
                        const ttl = await this.redis.ttl(key);

                        if (ttl === -1) {
                            // Set 1 hour expiry on orphaned honeycomb keys
                            await this.redis.expire(key, 3600);
                            cleaned++;
                        }
                    } catch (keyError) {
                        // Ignore individual key errors
                    }
                }
            } while (cursor !== '0');
        }

        return cleaned;
    }

    /**
     * Get cleanup stats (for monitoring)
     */
    async getStats() {
        const stats = {
            isRunning: this.isRunning,
            locationServiceCacheSize: this.locationService?.lastUpdateTime?.size || 0,
            orphanedKeys: {}
        };

        // Count keys matching each pattern
        for (const pattern of this.ORPHAN_PATTERNS) {
            let count = 0;
            let cursor = '0';

            do {
                const [nextCursor, keys] = await this.redis.scan(
                    cursor,
                    'MATCH', pattern,
                    'COUNT', 100
                );
                cursor = nextCursor;
                count += keys.length;
            } while (cursor !== '0');

            stats.orphanedKeys[pattern] = count;
        }

        return stats;
    }
}

module.exports = KeyCleanupService;
