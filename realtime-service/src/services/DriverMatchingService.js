/**
 * Driver Matching Service
 * Handles ride dispatch and driver matching logic
 */

const axios = require('axios');
const logger = require('../utils/logger');

class DriverMatchingService {
  constructor(redisClient, io, locationService) {
    this.redis = redisClient;
    this.io = io;
    this.locationService = locationService;
    this.LARAVEL_API_URL = process.env.LARAVEL_API_URL;
    this.LARAVEL_API_KEY = process.env.LARAVEL_API_KEY;
    this.MAX_DRIVERS_TO_NOTIFY = parseInt(process.env.MAX_DRIVERS_TO_NOTIFY) || 10;
    this.MATCH_TIMEOUT = parseInt(process.env.DRIVER_MATCH_TIMEOUT_SECONDS) || 120;
  }

  /**
   * Dispatch a new ride to nearby drivers
   * Called from Redis pub/sub when Laravel creates a ride
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
      estimatedFare
    } = rideData;

    logger.info('Dispatching ride', { rideId, customerId });

    // Find nearby available drivers
    const nearbyDrivers = await this.locationService.findNearbyDrivers(
      pickupLatitude,
      pickupLongitude,
      parseInt(process.env.DRIVER_SEARCH_RADIUS_KM) || 10,
      vehicleCategoryId
    );

    if (nearbyDrivers.length === 0) {
      logger.warn('No drivers available for ride', { rideId });

      // Notify customer
      this.io.to(`user:${customerId}`).emit('ride:no_drivers', {
        rideId,
        message: 'No drivers available nearby'
      });

      // Notify Laravel
      await this._notifyLaravelOnce(`ride.no_drivers:${rideId}`, 'ride.no_drivers', { rideId });

      return;
    }

    // Notify up to MAX_DRIVERS_TO_NOTIFY drivers
    const driversToNotify = nearbyDrivers.slice(0, this.MAX_DRIVERS_TO_NOTIFY);

    logger.info('Notifying drivers', {
      rideId,
      driverCount: driversToNotify.length
    });

    // Store ride info in Redis
    await this.redis.setex(
      `ride:pending:${rideId}`,
      this.MATCH_TIMEOUT,
      JSON.stringify({
        ...rideData,
        notifiedDrivers: driversToNotify.map(d => d.driverId),
        status: 'pending',
        dispatchedAt: Date.now()
      })
    );

    // Store ride->customer mapping for secure room subscription (longer TTL)
    await this.locationService.setRideCustomer(rideId, customerId);

    // Notify each driver
    const notifiedSetKey = `ride:notified:${rideId}`;
    const notifyPipeline = this.redis.pipeline();

    for (const driver of driversToNotify) {
      this.io.to(`user:${driver.driverId}`).emit('ride:new', {
        rideId,
        pickupLocation: {
          latitude: pickupLatitude,
          longitude: pickupLongitude
        },
        destinationLocation: {
          latitude: destinationLatitude,
          longitude: destinationLongitude
        },
        estimatedFare,
        distance: driver.distance,
        expiresAt: Date.now() + (this.MATCH_TIMEOUT * 1000)
      });

      // Track notification
      notifyPipeline.sadd(notifiedSetKey, driver.driverId);
    }

    // Expire notified set alongside pending ride
    notifyPipeline.expire(notifiedSetKey, this.MATCH_TIMEOUT);
    await notifyPipeline.exec();

    // Set timeout for auto-cancel if no driver accepts
    setTimeout(async () => {
      await this._checkRideTimeout(rideId);
    }, this.MATCH_TIMEOUT * 1000);
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
