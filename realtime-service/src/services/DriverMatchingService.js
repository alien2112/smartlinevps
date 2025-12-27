/**
 * Driver Matching Service
 * Handles ride dispatch and driver matching logic
 */

const axios = require('axios');
const logger = require('../utils/logger');
const config = require('../config/config');

class DriverMatchingService {
  constructor(redisClient, io, locationService, settingsManager = null) {
    this.redis = redisClient;
    this.io = io;
    this.locationService = locationService;
    this.settingsManager = settingsManager;
    // Load from centralized config (static values)
    this.LARAVEL_API_URL = config.laravel.apiUrl;
    this.LARAVEL_API_KEY = config.laravel.apiKey;
    this.LARAVEL_API_TIMEOUT = config.laravel.timeout;
  }

  /**
   * Get dynamic settings with fallback to config
   */
  getMaxDriversToNotify() {
    if (this.settingsManager?.isInitialized()) {
      return this.settingsManager.getMaxDriversToNotify();
    }
    return config.driverMatching.maxDriversToNotify;
  }

  getMatchTimeout() {
    if (this.settingsManager?.isInitialized()) {
      return this.settingsManager.getMatchTimeout();
    }
    return config.driverMatching.matchTimeoutSeconds * 1000;
  }

  getSearchRadius() {
    if (this.settingsManager?.isInitialized()) {
      return this.settingsManager.getSearchRadius();
    }
    return config.driverMatching.searchRadiusKm;
  }

  getTravelSearchRadius() {
    if (this.settingsManager?.isInitialized()) {
      return this.settingsManager.getTravelSearchRadius();
    }
    return 30; // default travel radius
  }

  /**
   * Dispatch a new ride to nearby drivers
   * Called from Redis pub/sub when Laravel creates a ride
   *
   * Category Level Dispatch:
   * - Normal rides: drivers with category_level >= requested can accept
   * - Travel rides: VIP only (level 3), extended radius
   */
  async dispatchRide(rideData) {
    const {
      rideId,
      pickupLatitude,
      pickupLongitude,
      destinationLatitude,
      destinationLongitude,
      vehicleCategoryId,
      customerId,
      estimatedFare,
      // Category dispatch fields
      categoryLevel = 1,
      isTravel = false,
      travelRadius = 30,
      fixedPrice = null
    } = rideData;

    logger.info('Dispatching ride', {
      rideId,
      customerId,
      categoryLevel,
      isTravel
    });

    // Determine search radius (extended for travel)
    const searchRadius = isTravel ? (travelRadius || this.getTravelSearchRadius()) : this.getSearchRadius();

    // Find nearby available drivers with category filtering
    const nearbyDrivers = await this.locationService.findNearbyDrivers(
      pickupLatitude,
      pickupLongitude,
      searchRadius,
      vehicleCategoryId,
      { categoryLevel, isTravel }
    );

    if (nearbyDrivers.length === 0) {
      logger.warn('No drivers available for ride', { rideId, isTravel });

      // Notify customer
      this.io.to(`user:${customerId}`).emit('ride:no_drivers', {
        rideId,
        message: isTravel
          ? 'No VIP drivers available for travel request'
          : 'No drivers available nearby'
      });

      // Notify Laravel (different event for travel)
      const event = isTravel ? 'ride.travel.no_drivers' : 'ride.no_drivers';
      await this._notifyLaravelOnce(`${event}:${rideId}`, event, { rideId, isTravel });

      return;
    }

    // Notify up to MAX_DRIVERS_TO_NOTIFY drivers
    const driversToNotify = nearbyDrivers.slice(0, this.getMaxDriversToNotify());

    logger.info('Notifying drivers', {
      rideId,
      driverCount: driversToNotify.length
    });

    // Calculate expiration time for timeout handler
    const dispatchedAt = Date.now();
    const expiresAt = dispatchedAt + this.getMatchTimeout();

    // Store ride info in Redis with expiresAt for RideTimeoutService
    await this.redis.setex(
      `ride:pending:${rideId}`,
      Math.floor(this.getMatchTimeout() / 1000) + 60, // Extra 60s buffer for timeout handler to process
      JSON.stringify({
        ...rideData,
        notifiedDrivers: driversToNotify.map(d => d.driverId),
        status: 'pending',
        dispatchedAt,
        expiresAt
      })
    );

    // Store ride->customer mapping for secure room subscription (longer TTL)
    await this.locationService.setRideCustomer(rideId, customerId);

    // Notify each driver
    const notifiedSetKey = `ride:notified:${rideId}`;
    const notifyPipeline = this.redis.pipeline();

    // Use appropriate event name for travel vs normal rides
    const eventName = isTravel ? 'ride:travel:new' : 'ride:new';

    for (const driver of driversToNotify) {
      const rideNotification = {
        rideId,
        pickupLocation: {
          latitude: pickupLatitude,
          longitude: pickupLongitude
        },
        destinationLocation: {
          latitude: destinationLatitude,
          longitude: destinationLongitude
        },
        estimatedFare: isTravel ? fixedPrice : estimatedFare,
        distance: driver.distance,
        expiresAt,
        // Category information
        categoryLevel,
        isTravel
      };

      // Add travel-specific fields
      if (isTravel) {
        rideNotification.fixedPrice = fixedPrice;
        rideNotification.travelDate = rideData.travelDate || null;
        rideNotification.travelPassengers = rideData.travelPassengers || null;
        rideNotification.travelLuggage = rideData.travelLuggage || null;
        rideNotification.travelNotes = rideData.travelNotes || null;
      }

      this.io.to(`user:${driver.driverId}`).emit(eventName, rideNotification);

      // Track notification
      notifyPipeline.sadd(notifiedSetKey, driver.driverId);
    }

    // Expire notified set alongside pending ride
    notifyPipeline.expire(notifiedSetKey, Math.floor(this.getMatchTimeout() / 1000) + 60);
    await notifyPipeline.exec();

    // NOTE: Timeout is now handled by RideTimeoutService polling
    // instead of setTimeout to prevent timer heap bloat
  }

  /**
   * Handle driver accepting a ride
   */
  async handleDriverAcceptRide(driverId, rideId) {
    logger.info('Driver attempting to accept ride', { driverId, rideId });

    // Check if ride still pending
    const rideData = await this.redis.get(`ride:pending:${rideId}`);

    if (!rideData) {
      logger.warn('Ride no longer available', { rideId, driverId });

      this.io.to(`user:${driverId}`).emit('ride:accept:failed', {
        rideId,
        message: 'Ride is no longer available'
      });

      return;
    }

    // Use Redis lock to prevent race condition
    const lock = await this._acquireLock(`ride:lock:${rideId}`, 5);

    if (!lock) {
      logger.warn('Failed to acquire lock for ride', { rideId, driverId });

      this.io.to(`user:${driverId}`).emit('ride:accept:failed', {
        rideId,
        message: 'Another driver is accepting this ride'
      });

      return;
    }

    try {
      // Double-check ride is still available
      const stillAvailable = await this.redis.exists(`ride:pending:${rideId}`);

      if (!stillAvailable) {
        this.io.to(`user:${driverId}`).emit('ride:accept:failed', {
          rideId,
          message: 'Ride already accepted by another driver'
        });

        return;
      }

      // Call Laravel API to assign driver
      try {
        const response = await axios.post(
          `${this.LARAVEL_API_URL}/api/internal/ride/assign-driver`,
          {
            ride_id: rideId,
            driver_id: driverId
          },
          {
            headers: {
              'X-API-Key': this.LARAVEL_API_KEY,
              'Content-Type': 'application/json'
            },
            timeout: 5000
          }
        );

        if (response.data.success) {
          // Assignment successful
          const ride = JSON.parse(rideData);

          // Remove from pending
          await this.redis.del(`ride:pending:${rideId}`);

          // Keep ride->customer mapping for room authorization
          if (ride?.customerId) {
            await this.locationService.setRideCustomer(rideId, ride.customerId);
          }

          // Notify driver
          this.io.to(`user:${driverId}`).emit('ride:accept:success', {
            rideId,
            message: 'Ride accepted successfully',
            rideDetails: response.data.ride
          });

          // Notify customer
          this.io.to(`user:${ride.customerId}`).emit('ride:driver_assigned', {
            rideId,
            driver: response.data.driver,
            estimatedArrival: response.data.estimatedArrival
          });

          // Notify other drivers that ride is no longer available
          const notifiedDrivers = await this.redis.smembers(`ride:notified:${rideId}`);

          for (const otherDriverId of notifiedDrivers) {
            if (otherDriverId !== driverId) {
              this.io.to(`user:${otherDriverId}`).emit('ride:taken', {
                rideId,
                message: 'This ride has been accepted by another driver'
              });
            }
          }

          // Cleanup
          await this.redis.del(`ride:notified:${rideId}`);

          logger.info('Ride accepted successfully', { rideId, driverId });
        } else {
          throw new Error(response.data.message || 'Assignment failed');
        }
      } catch (apiError) {
        logger.error('Laravel API error during ride assignment', {
          error: apiError.message,
          rideId,
          driverId
        });

        this.io.to(`user:${driverId}`).emit('ride:accept:failed', {
          rideId,
          message: 'Failed to assign ride, please try again'
        });
      }
    } finally {
      // Release lock
      await this._releaseLock(`ride:lock:${rideId}`);
    }
  }

  /**
   * Check if ride timed out (no driver accepted)
   */
  async _checkRideTimeout(rideId) {
    // In multi-instance deployments, multiple timers may fire; ensure single handler.
    const timeoutLockKey = `ride:timeout:lock:${rideId}`;
    const lock = await this._acquireLock(timeoutLockKey, 30);
    if (!lock) return;

    const rideData = await this.redis.get(`ride:pending:${rideId}`);

    if (rideData) {
      // Ride still pending, timeout occurred
      const ride = JSON.parse(rideData);

      logger.warn('Ride timed out, no driver accepted', { rideId });

      // Notify customer
      this.io.to(`user:${ride.customerId}`).emit('ride:timeout', {
        rideId,
        message: 'No driver accepted your ride'
      });

      // Notify Laravel
      await this._notifyLaravelOnce(`ride.timeout:${rideId}`, 'ride.timeout', { rideId });

      // Cleanup
      await this.redis.del(`ride:pending:${rideId}`);
      await this.redis.del(`ride:notified:${rideId}`);
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
   * Notify Laravel via HTTP callback
   */
  async _notifyLaravel(event, data) {
    try {
      await axios.post(
        `${this.LARAVEL_API_URL}/api/internal/events/${event}`,
        data,
        {
          headers: {
            'X-API-Key': this.LARAVEL_API_KEY,
            'Content-Type': 'application/json'
          },
          timeout: 5000
        }
      );
    } catch (error) {
      logger.error('Failed to notify Laravel', {
        event,
        error: error.message
      });
    }
  }

  /**
   * Notify Laravel once per key (best-effort dedupe across instances)
   */
  async _notifyLaravelOnce(dedupeKey, event, data) {
    const lock = await this._acquireLock(`laravel:notify:${dedupeKey}`, 60);
    if (!lock) return;
    await this._notifyLaravel(event, data);
  }
}

module.exports = DriverMatchingService;
