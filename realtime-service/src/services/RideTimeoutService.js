/**
 * Ride Timeout Service
 * Polling-based handler for expired ride requests
 * 
 * PERFORMANCE OPTIMIZATION: Replaces per-ride setTimeout timers with a single
 * polling loop that scans for expired rides. This prevents timer heap bloat
 * at high concurrency and works correctly across multiple instances.
 */

const logger = require('../utils/logger');
const config = require('../config/config');

class RideTimeoutService {
    constructor(redisClient, io, driverMatchingService) {
        this.redis = redisClient;
        this.io = io;
        this.driverMatchingService = driverMatchingService;

        // Polling interval (default: 5 seconds)
        this.checkIntervalMs = config.rideTimeout.checkIntervalMs;
        this.isRunning = false;
        this.intervalId = null;

        // Lock key prefix for distributed coordination
        this.TIMEOUT_LOCK_PREFIX = 'ride:timeout:processing:';
    }

    /**
     * Start the timeout checking service
     */
    start() {
        if (this.isRunning) {
            logger.warn('RideTimeoutService already running');
            return;
        }

        this.isRunning = true;
        logger.info('RideTimeoutService started', { checkIntervalMs: this.checkIntervalMs });

        // Run immediately, then on interval
        this._checkExpiredRides();
        this.intervalId = setInterval(() => this._checkExpiredRides(), this.checkIntervalMs);
    }

    /**
     * Stop the timeout checking service
     */
    stop() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
        this.isRunning = false;
        logger.info('RideTimeoutService stopped');
    }

    /**
     * Check for expired rides and process them
     * Uses SCAN to iterate through ride:pending:* keys
     */
    async _checkExpiredRides() {
        try {
            const now = Date.now();
            let cursor = '0';
            let processedCount = 0;

            do {
                // SCAN for ride:pending:* keys
                const [nextCursor, keys] = await this.redis.scan(
                    cursor,
                    'MATCH', 'ride:pending:*',
                    'COUNT', 100
                );
                cursor = nextCursor;

                for (const key of keys) {
                    const rideId = key.replace('ride:pending:', '');
                    const processed = await this._processExpiredRide(rideId, now);
                    if (processed) processedCount++;
                }
            } while (cursor !== '0');

            if (processedCount > 0) {
                logger.info('Processed expired rides', { count: processedCount });
            }
        } catch (error) {
            logger.error('Error checking expired rides', { error: error.message });
        }
    }

    /**
     * Process a single ride if it has expired
     * Uses distributed lock to prevent duplicate processing across instances
     */
    async _processExpiredRide(rideId, now) {
        try {
            // Get ride data
            const rideDataStr = await this.redis.get(`ride:pending:${rideId}`);
            if (!rideDataStr) return false;

            const rideData = JSON.parse(rideDataStr);

            // Check if expired
            const expiresAt = rideData.expiresAt || (rideData.dispatchedAt + (config.driverMatching.matchTimeoutSeconds * 1000));
            if (now < expiresAt) {
                // Not expired yet
                return false;
            }

            // Try to acquire lock for this ride
            const lockKey = `${this.TIMEOUT_LOCK_PREFIX}${rideId}`;
            const lock = await this._acquireLock(lockKey, 30);
            if (!lock) {
                // Another instance is handling this
                return false;
            }

            try {
                // Double-check ride still exists (might have been accepted)
                const stillExists = await this.redis.exists(`ride:pending:${rideId}`);
                if (!stillExists) return false;

                logger.warn('Ride timed out, no driver accepted', { rideId });

                // Notify customer
                this.io.to(`user:${rideData.customerId}`).emit('ride:timeout', {
                    rideId,
                    message: 'No driver accepted your ride'
                });

                // Notify Laravel (best-effort, with dedupe lock)
                await this._notifyLaravelOnce(rideId);

                // Cleanup
                await this.redis.del(`ride:pending:${rideId}`);
                await this.redis.del(`ride:notified:${rideId}`);

                return true;
            } finally {
                // Release lock
                await this._releaseLock(lockKey);
            }
        } catch (error) {
            logger.error('Error processing expired ride', { rideId, error: error.message });
            return false;
        }
    }

    /**
     * Acquire Redis lock using SET NX EX
     */
    async _acquireLock(key, ttlSeconds) {
        const result = await this.redis.set(key, '1', 'EX', ttlSeconds, 'NX');
        return result === 'OK';
    }

    /**
     * Release Redis lock
     */
    async _releaseLock(key) {
        await this.redis.del(key);
    }

    /**
     * Notify Laravel once per ride (best-effort dedupe)
     */
    async _notifyLaravelOnce(rideId) {
        const dedupeKey = `laravel:notify:ride.timeout:${rideId}`;
        const lock = await this._acquireLock(dedupeKey, 60);
        if (!lock) return;

        try {
            const axios = require('axios');
            await axios.post(
                `${config.laravel.apiUrl}/api/internal/events/ride.timeout`,
                { rideId },
                {
                    headers: {
                        'X-API-Key': config.laravel.apiKey,
                        'Content-Type': 'application/json'
                    },
                    timeout: 5000
                }
            );
        } catch (error) {
            logger.error('Failed to notify Laravel of ride timeout', {
                rideId,
                error: error.message
            });
        }
    }
}

module.exports = RideTimeoutService;
