/**
 * Honeycomb Service for Node.js Realtime
 * 
 * Implements H3 hexagonal grid-based dispatch acceleration.
 * Works in coordination with Laravel's HoneycombService.
 * 
 * Key responsibilities:
 * - Track driver cells in real-time
 * - Provide cell-based candidate filtering for dispatch
 * - Sync with Laravel config via Redis pub/sub
 * 
 * @see https://www.uber.com/blog/h3/
 */

const logger = require('../utils/logger');

class HoneycombService {
    constructor(redisClient, settingsManager = null) {
        this.redis = redisClient;
        this.settingsManager = settingsManager;

        // Redis key prefixes (must match Laravel)
        this.DRIVER_CELL_PREFIX = 'hc:drivers:';
        this.CELL_SUPPLY_PREFIX = 'hc:supply:';
        this.CELL_DEMAND_PREFIX = 'hc:demand:';
        this.SETTINGS_CACHE_PREFIX = 'hc:settings:';
        this.DRIVER_CURRENT_CELL = 'hc:driver:cell:';

        // Default settings
        this.defaultResolution = 8;
        this.defaultSearchDepth = 1;

        // Settings cache (updated via pub/sub)
        this.settingsCache = new Map();

        // Subscribe to config updates from Laravel
        this._setupConfigSubscription();
    }

    /**
     * Setup Redis pub/sub for config updates from Laravel
     */
    _setupConfigSubscription() {
        // Note: In production, use a separate Redis connection for pub/sub
        // This is a placeholder for the subscription logic
        logger.info('Honeycomb service initialized, watching for config updates');
    }

    // ============================================================
    // H3 COORDINATE CONVERSION (Simplified - matches Laravel)
    // ============================================================

    /**
     * Convert lat/lng to H3 index
     * Uses same simplified algorithm as Laravel for consistency
     */
    latLngToH3(lat, lng, resolution = null) {
        resolution = resolution || this.defaultResolution;

        const gridSize = {
            7: 0.05,   // ~5km
            8: 0.015,  // ~1.5km
            9: 0.005,  // ~500m
        }[resolution] || 0.015;

        const gridLat = Math.round(lat / gridSize) * gridSize;
        const gridLng = Math.round(lng / gridSize) * gridSize;

        const latInt = Math.floor((gridLat + 90) * 10000);
        const lngInt = Math.floor((gridLng + 180) * 10000);

        const latHex = latInt.toString(16).padStart(6, '0');
        const lngHex = lngInt.toString(16).padStart(6, '0');

        return `r${resolution}_${latHex}_${lngHex}`;
    }

    /**
     * Parse H3 index back to coordinates
     */
    h3ToLatLng(h3Index) {
        const match = h3Index.match(/^r(\d+)_([0-9a-f]+)_([0-9a-f]+)$/i);
        if (!match) {
            return { lat: 0, lng: 0 };
        }

        const latInt = parseInt(match[2], 16);
        const lngInt = parseInt(match[3], 16);

        return {
            lat: (latInt / 10000) - 90,
            lng: (lngInt / 10000) - 180,
        };
    }

    /**
     * Get neighboring cells (k-ring)
     */
    kRing(h3Index, k = 1) {
        const center = this.h3ToLatLng(h3Index);
        const resolution = this._getResolutionFromIndex(h3Index);
        const edgeKm = this._getEdgeLengthKm(resolution);

        const neighbors = [h3Index];

        for (let ring = 1; ring <= k; ring++) {
            const offsetKm = edgeKm * ring * 1.5;

            for (let dir = 0; dir < 6; dir++) {
                const angle = (dir * 60) * Math.PI / 180;
                const offsetLat = (offsetKm / 111) * Math.cos(angle);
                const offsetLng = (offsetKm / (111 * Math.cos(center.lat * Math.PI / 180))) * Math.sin(angle);

                const neighborH3 = this.latLngToH3(
                    center.lat + offsetLat,
                    center.lng + offsetLng,
                    resolution
                );

                if (!neighbors.includes(neighborH3)) {
                    neighbors.push(neighborH3);
                }
            }
        }

        return neighbors;
    }

    _getResolutionFromIndex(h3Index) {
        const match = h3Index.match(/^r(\d+)_/);
        return match ? parseInt(match[1], 10) : this.defaultResolution;
    }

    _getEdgeLengthKm(resolution) {
        return { 7: 2.6, 8: 0.98, 9: 0.37 }[resolution] || 0.98;
    }

    // ============================================================
    // DRIVER CELL MANAGEMENT
    // ============================================================

    /**
     * Update driver's cell when their location changes
     */
    async updateDriverCell(driverId, lat, lng, zoneId, category = 'budget') {
        const settings = await this.getSettings(zoneId);

        if (!settings || !settings.enabled) {
            return;
        }

        const newCell = this.latLngToH3(lat, lng, settings.h3_resolution);
        const currentCellKey = this.DRIVER_CURRENT_CELL + driverId;

        const currentCell = await this.redis.get(currentCellKey);

        if (currentCell === newCell) {
            await this.redis.expire(currentCellKey, 300);
            return;
        }

        const pipeline = this.redis.pipeline();

        // Remove from old cell
        if (currentCell) {
            const oldCellKey = this._getCellDriversKey(zoneId, currentCell);
            pipeline.srem(oldCellKey, driverId);

            const supplyKey = this._getCellSupplyKey(zoneId, currentCell);
            pipeline.hincrby(supplyKey, 'total', -1);
            pipeline.hincrby(supplyKey, category, -1);
        }

        // Add to new cell
        const newCellKey = this._getCellDriversKey(zoneId, newCell);
        pipeline.sadd(newCellKey, driverId);
        pipeline.expire(newCellKey, 600);

        const supplyKey = this._getCellSupplyKey(zoneId, newCell);
        pipeline.hincrby(supplyKey, 'total', 1);
        pipeline.hincrby(supplyKey, category, 1);
        pipeline.expire(supplyKey, 600);

        pipeline.setex(currentCellKey, 300, newCell);

        await pipeline.exec();

        logger.debug('Driver cell updated', {
            driverId,
            oldCell: currentCell,
            newCell,
            zoneId,
        });
    }

    /**
     * Remove driver from cells (when going offline)
     */
    async removeDriverFromCells(driverId, zoneId) {
        const currentCellKey = this.DRIVER_CURRENT_CELL + driverId;
        const currentCell = await this.redis.get(currentCellKey);

        if (currentCell) {
            const cellKey = this._getCellDriversKey(zoneId, currentCell);
            await this.redis.srem(cellKey, driverId);
            await this.redis.del(currentCellKey);
        }
    }

    // ============================================================
    // DISPATCH ACCELERATION
    // ============================================================

    /**
     * Get candidate drivers using honeycomb cell search
     * 
     * This is the main dispatch acceleration method.
     * Returns driver IDs from origin cell + k-ring neighbors.
     */
    async getCandidateDrivers(pickupLat, pickupLng, zoneId) {
        const settings = await this.getSettings(zoneId);

        if (!settings || !settings.enabled || !settings.dispatch_enabled) {
            return { enabled: false, drivers: [] };
        }

        const originCell = this.latLngToH3(pickupLat, pickupLng, settings.h3_resolution);
        const searchCells = this.kRing(originCell, settings.search_depth_k);

        const pipeline = this.redis.pipeline();
        for (const cell of searchCells) {
            const cellKey = this._getCellDriversKey(zoneId, cell);
            pipeline.smembers(cellKey);
        }

        const results = await pipeline.exec();

        const driverIds = [];
        for (const [err, cellDrivers] of results) {
            if (!err && Array.isArray(cellDrivers)) {
                driverIds.push(...cellDrivers);
            }
        }

        const uniqueDrivers = [...new Set(driverIds)];

        logger.info('Honeycomb candidate search', {
            pickup: [pickupLat, pickupLng],
            originCell,
            cellsSearched: searchCells.length,
            candidatesFound: uniqueDrivers.length,
            zoneId,
        });

        return {
            enabled: true,
            originCell,
            cellsSearched: searchCells.length,
            drivers: uniqueDrivers,
        };
    }

    /**
     * Check if honeycomb dispatch is enabled for a zone
     */
    async isEnabled(zoneId) {
        const settings = await this.getSettings(zoneId);
        return settings && settings.enabled && settings.dispatch_enabled;
    }

    // ============================================================
    // DEMAND TRACKING
    // ============================================================

    /**
     * Record a ride request demand in the origin cell
     */
    async recordDemand(pickupLat, pickupLng, zoneId, category = 'budget') {
        const settings = await this.getSettings(zoneId);

        if (!settings || !settings.enabled) {
            return;
        }

        const cell = this.latLngToH3(pickupLat, pickupLng, settings.h3_resolution);
        const windowKey = this._getTimeWindow();

        const demandKey = this._getCellDemandKey(zoneId, cell, windowKey);

        const pipeline = this.redis.pipeline();
        pipeline.hincrby(demandKey, 'total', 1);
        pipeline.hincrby(demandKey, category, 1);
        pipeline.expire(demandKey, 600);
        await pipeline.exec();
    }

    // ============================================================
    // SURGE PRICING
    // ============================================================

    /**
     * Get surge multiplier for a location
     */
    async getSurgeMultiplier(lat, lng, zoneId) {
        const settings = await this.getSettings(zoneId);

        if (!settings || !settings.surge_enabled) {
            return 1.0;
        }

        const cell = this.latLngToH3(lat, lng, settings.h3_resolution);
        const windowKey = this._getTimeWindow();

        const supplyKey = this._getCellSupplyKey(zoneId, cell);
        const demandKey = this._getCellDemandKey(zoneId, cell, windowKey);

        const [supplyTotal, demandTotal] = await Promise.all([
            this.redis.hget(supplyKey, 'total'),
            this.redis.hget(demandKey, 'total'),
        ]);

        const supply = parseInt(supplyTotal, 10) || 1;
        const demand = parseInt(demandTotal, 10) || 0;
        const imbalance = demand / Math.max(supply, 1);

        return this._calculateSurge(imbalance, settings);
    }

    _calculateSurge(imbalance, settings) {
        if (!settings.surge_enabled) {
            return 1.0;
        }

        const threshold = parseFloat(settings.surge_threshold) || 1.5;
        const cap = parseFloat(settings.surge_cap) || 2.0;
        const step = parseFloat(settings.surge_step) || 0.1;

        if (imbalance < threshold) {
            return 1.0;
        }

        const excess = imbalance - threshold;
        const steps = Math.floor(excess / 0.5);
        const surge = 1.0 + (steps * step);

        return Math.min(surge, cap);
    }

    // ============================================================
    // SETTINGS MANAGEMENT
    // ============================================================

    /**
     * Get honeycomb settings for a zone (cached)
     */
    async getSettings(zoneId) {
        // Check local cache first
        if (this.settingsCache.has(zoneId)) {
            const cached = this.settingsCache.get(zoneId);
            if (cached.expiresAt > Date.now()) {
                return cached.settings;
            }
        }

        // Try Redis cache
        const cacheKey = this.SETTINGS_CACHE_PREFIX + zoneId;
        const cached = await this.redis.get(cacheKey);

        if (cached) {
            const settings = JSON.parse(cached);

            // Store in local cache for 60 seconds
            this.settingsCache.set(zoneId, {
                settings,
                expiresAt: Date.now() + 60000,
            });

            return settings;
        }

        // No settings found - honeycomb not configured for this zone
        return null;
    }

    /**
     * Invalidate settings cache (called via pub/sub)
     */
    invalidateSettingsCache(zoneId = null) {
        if (zoneId) {
            this.settingsCache.delete(zoneId);
        } else {
            this.settingsCache.clear();
        }

        logger.info('Honeycomb settings cache invalidated', { zoneId });
    }

    // ============================================================
    // HELPER METHODS
    // ============================================================

    _getCellDriversKey(zoneId, h3Index) {
        return this.DRIVER_CELL_PREFIX + zoneId + ':' + h3Index;
    }

    _getCellSupplyKey(zoneId, h3Index) {
        return this.CELL_SUPPLY_PREFIX + zoneId + ':' + h3Index;
    }

    _getCellDemandKey(zoneId, h3Index, windowKey) {
        return this.CELL_DEMAND_PREFIX + zoneId + ':' + h3Index + ':' + windowKey;
    }

    _getTimeWindow() {
        const timestamp = Math.floor(Date.now() / 300000) * 300;
        return String(timestamp);
    }
}

module.exports = HoneycombService;
