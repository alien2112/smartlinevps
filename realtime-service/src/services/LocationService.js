/**
 * Location Service
 * Handles real-time driver location tracking with Redis GEO
 */

const { getDistance } = require('geolib');
const logger = require('../utils/logger');
const { objectToHsetArgs } = require('../utils/redisHelpers');
const config = require('../config/config');

class LocationService {
  constructor(redisClient, io) {
    this.redis = redisClient;
    this.io = io;
    this.DRIVERS_GEO_KEY = 'drivers:locations';
    this.DRIVER_STATUS_PREFIX = 'driver:status:';
    this.DRIVER_INFO_PREFIX = 'driver:info:';
    this.RIDE_DRIVER_PREFIX = 'ride:driver:';
    this.RIDE_CUSTOMER_PREFIX = 'ride:customer:';
    this.ACTIVE_RIDES_SET = 'rides:active';

    // Load from centralized config
    this.LOCATION_EXPIRY = config.location.expirySeconds;
    this.UPDATE_THROTTLE = config.location.updateThrottleMs;
    this.RIDE_CONTEXT_EXPIRY = config.location.rideContextExpirySeconds;

    // Throttle map to prevent excessive updates
    this.lastUpdateTime = new Map();
  }

  /**
   * Set driver as online
   */
  async setDriverOnline(driverId, data = {}) {
    const multi = this.redis.multi();

    // Set driver status
    multi.hset(
      `${this.DRIVER_STATUS_PREFIX}${driverId}`,
      ...objectToHsetArgs({
        status: 'online',
        availability: data.availability || 'available',
        last_seen: Date.now()
      })
    );

    // Store driver info
    if (data.location) {
      multi.hset(
        `${this.DRIVER_INFO_PREFIX}${driverId}`,
        ...objectToHsetArgs({
          vehicle_category_id: data.vehicle_category_id || '',
          vehicle_id: data.vehicle_id || '',
          name: data.name || ''
        })
      );

      // Add to geo index
      multi.geoadd(
        this.DRIVERS_GEO_KEY,
        data.location.longitude,
        data.location.latitude,
        driverId
      );
    }

    // Set expiry
    multi.expire(`${this.DRIVER_STATUS_PREFIX}${driverId}`, this.LOCATION_EXPIRY);
    multi.expire(`${this.DRIVER_INFO_PREFIX}${driverId}`, this.LOCATION_EXPIRY);

    await multi.exec();

    logger.info('Driver went online', { driverId });
  }

  /**
   * Set driver as offline
   */
  async setDriverOffline(driverId) {
    const multi = this.redis.multi();

    // Update status to offline
    multi.hset(
      `${this.DRIVER_STATUS_PREFIX}${driverId}`,
      ...objectToHsetArgs({
        status: 'offline',
        last_seen: Date.now()
      })
    );

    // Remove from geo index
    multi.zrem(this.DRIVERS_GEO_KEY, driverId);

    await multi.exec();
    this.lastUpdateTime.delete(driverId);

    logger.info('Driver went offline', { driverId });
  }

  /**
   * Set driver as disconnected (grace period before offline)
   */
  async setDriverDisconnected(driverId) {
    await this.redis.hset(
      `${this.DRIVER_STATUS_PREFIX}${driverId}`,
      ...objectToHsetArgs({
        status: 'disconnected',
        last_seen: Date.now()
      })
    );

    // Set a 30-second grace period
    // If no reconnect, a cleanup job will mark them offline
    await this.redis.expire(`${this.DRIVER_STATUS_PREFIX}${driverId}`, 30);
    this.lastUpdateTime.delete(driverId);

    logger.info('Driver disconnected, grace period started', { driverId });
  }

  /**
   * Update driver location (high-frequency updates)
   * OPTIMIZED: Uses Redis pipeline to reduce 4 round-trips to 1
   */
  async updateDriverLocation(driverId, locationData) {
    // Throttle updates to prevent Redis overload
    const now = Date.now();
    const lastUpdate = this.lastUpdateTime.get(driverId);

    if (lastUpdate && (now - lastUpdate) < this.UPDATE_THROTTLE) {
      // Skip update, too frequent
      return;
    }

    this.lastUpdateTime.set(driverId, now);

    const { latitude, longitude, speed, heading, accuracy } = locationData;

    // Validate coordinates
    if (!latitude || !longitude || latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) {
      logger.warn('Invalid coordinates', { driverId, latitude, longitude });
      return;
    }

    // Use pipeline to batch all Redis commands into a single round-trip
    const pipeline = this.redis.pipeline();

    // Update geo location
    pipeline.geoadd(this.DRIVERS_GEO_KEY, longitude, latitude, driverId);

    // Update driver status with last_seen
    pipeline.hset(
      `${this.DRIVER_STATUS_PREFIX}${driverId}`,
      ...objectToHsetArgs({
        last_seen: now,
        last_latitude: latitude,
        last_longitude: longitude,
        speed: speed || 0,
        heading: heading || 0,
        accuracy: accuracy || 0
      })
    );

    // Reset expiry
    pipeline.expire(`${this.DRIVER_STATUS_PREFIX}${driverId}`, this.LOCATION_EXPIRY);

    // Check if driver is on an active ride
    pipeline.get(`driver:active_ride:${driverId}`);

    // Execute all commands in single round-trip
    const results = await pipeline.exec();

    // Get rideId from the 4th command result (index 3)
    const rideId = results?.[3]?.[1];

    if (rideId) {
      // Broadcast location to customer in this ride
      this.io.to(`ride:${rideId}`).emit('driver:location:update', {
        rideId,
        driverId,
        location: {
          latitude,
          longitude,
          speed,
          heading
        },
        timestamp: now
      });
    }
  }

  /**
   * Find nearest available drivers
   * Uses Redis GEORADIUS for efficient spatial queries
   */
  async findNearbyDrivers(latitude, longitude, radiusKm = 10, vehicleCategoryId = null) {
    // Get drivers within radius
    const drivers = await this.redis.georadius(
      this.DRIVERS_GEO_KEY,
      longitude,
      latitude,
      radiusKm,
      'km',
      'WITHDIST',
      'ASC',
      'COUNT',
      50
    );

    if (!drivers.length) {
      return [];
    }

    // Batch fetch statuses to avoid N+1 Redis calls
    const statusPipeline = this.redis.pipeline();
    for (const [driverId] of drivers) {
      statusPipeline.hgetall(`${this.DRIVER_STATUS_PREFIX}${driverId}`);
    }

    const statusResults = await statusPipeline.exec();

    const availableCandidates = [];
    const staleDriverIds = [];

    for (let i = 0; i < drivers.length; i++) {
      const [driverId, distance] = drivers[i];
      const status = statusResults?.[i]?.[1] || {};

      if (!status.status) {
        staleDriverIds.push(driverId);
        continue;
      }

      if (status.status !== 'online' || status.availability !== 'available') {
        // Opportunistic cleanup: prevent unbounded stale GEO members over time
        if (status.status !== 'online') {
          staleDriverIds.push(driverId);
        }
        continue;
      }

      availableCandidates.push({
        driverId,
        distance: parseFloat(distance),
        status
      });
    }

    if (staleDriverIds.length) {
      await this.redis.zrem(this.DRIVERS_GEO_KEY, ...staleDriverIds);
    }

    // Optional vehicle-category filter (batch fetch infos only for candidates)
    let allowedCandidates = availableCandidates;

    if (vehicleCategoryId) {
      const infoPipeline = this.redis.pipeline();
      for (const c of availableCandidates) {
        infoPipeline.hgetall(`${this.DRIVER_INFO_PREFIX}${c.driverId}`);
      }

      const infoResults = await infoPipeline.exec();

      allowedCandidates = availableCandidates.filter((c, idx) => {
        const info = infoResults?.[idx]?.[1] || {};
        return info.vehicle_category_id === vehicleCategoryId;
      });
    }

    const availableDrivers = allowedCandidates.map((c) => ({
      driverId: c.driverId,
      distance: c.distance,
      latitude: parseFloat(c.status.last_latitude),
      longitude: parseFloat(c.status.last_longitude),
      speed: parseFloat(c.status.speed || 0)
    }));

    logger.info('Found nearby drivers', {
      count: availableDrivers.length,
      radiusKm,
      location: { latitude, longitude }
    });

    return availableDrivers;
  }

  /**
   * Assign driver to ride
   */
  async assignDriverToRide(driverId, rideId) {
    const multi = this.redis.multi();

    // Mark driver as busy
    multi.hset(
      `${this.DRIVER_STATUS_PREFIX}${driverId}`,
      ...objectToHsetArgs({
        availability: 'busy',
        active_ride_id: rideId
      })
    );

    // Store active ride mapping
    multi.setex(`driver:active_ride:${driverId}`, this.RIDE_CONTEXT_EXPIRY, rideId);
    multi.setex(`${this.RIDE_DRIVER_PREFIX}${rideId}`, this.RIDE_CONTEXT_EXPIRY, driverId);
    multi.sadd(this.ACTIVE_RIDES_SET, rideId);

    await multi.exec();

    logger.info('Driver assigned to ride', { driverId, rideId });
  }

  /**
   * Complete ride (free up driver)
   */
  async completeRide(rideId) {
    const driverId = await this.redis.get(`${this.RIDE_DRIVER_PREFIX}${rideId}`);

    if (!driverId) {
      // Best-effort cleanup even if we can't resolve the driver mapping
      await this.redis.del(`${this.RIDE_CUSTOMER_PREFIX}${rideId}`);
      await this.redis.srem(this.ACTIVE_RIDES_SET, rideId);
      return;
    }

    {
      const multi = this.redis.multi();

      // Mark driver as available again
      multi.hset(
        `${this.DRIVER_STATUS_PREFIX}${driverId}`,
        ...objectToHsetArgs({
          availability: 'available',
          active_ride_id: ''
        })
      );

      // Remove active ride mapping
      multi.del(`driver:active_ride:${driverId}`);
      multi.del(`${this.RIDE_DRIVER_PREFIX}${rideId}`);
      multi.del(`${this.RIDE_CUSTOMER_PREFIX}${rideId}`);
      multi.srem(this.ACTIVE_RIDES_SET, rideId);

      await multi.exec();

      logger.info('Ride completed, driver available', { driverId, rideId });
    }
  }

  /**
   * Get active drivers count
   */
  async getActiveDriversCount() {
    return await this.redis.zcard(this.DRIVERS_GEO_KEY);
  }

  /**
   * Get active rides count
   */
  async getActiveRidesCount() {
    return await this.redis.scard(this.ACTIVE_RIDES_SET);
  }

  /**
   * Store ride->customer mapping for room authorization
   */
  async setRideCustomer(rideId, customerId, ttlSeconds = this.RIDE_CONTEXT_EXPIRY) {
    if (!rideId || !customerId) return;
    await this.redis.setex(`${this.RIDE_CUSTOMER_PREFIX}${rideId}`, ttlSeconds, customerId);
  }

  async getRideCustomer(rideId) {
    return await this.redis.get(`${this.RIDE_CUSTOMER_PREFIX}${rideId}`);
  }

  /**
   * Best-effort authorization for joining `ride:{rideId}` rooms.
   */
  async canUserAccessRide(userId, userType, rideId) {
    if (!userId || !rideId) return false;

    if (userType === 'customer') {
      const customerId = await this.getRideCustomer(rideId);
      return !!customerId && customerId === userId;
    }

    if (userType === 'driver') {
      const driverId = await this.redis.get(`${this.RIDE_DRIVER_PREFIX}${rideId}`);
      return !!driverId && driverId === userId;
    }

    return false;
  }

  /**
   * Get driver current location
   */
  async getDriverLocation(driverId) {
    const position = await this.redis.geopos(this.DRIVERS_GEO_KEY, driverId);

    if (!position || !position[0]) {
      return null;
    }

    const [longitude, latitude] = position[0];
    const status = await this.redis.hgetall(`${this.DRIVER_STATUS_PREFIX}${driverId}`);

    return {
      latitude: parseFloat(latitude),
      longitude: parseFloat(longitude),
      speed: parseFloat(status.speed || 0),
      heading: parseFloat(status.heading || 0),
      accuracy: parseFloat(status.accuracy || 0),
      last_seen: parseInt(status.last_seen || 0)
    };
  }
}

module.exports = LocationService;
