/**
 * Redis Event Bus
 * Listens to events published by Laravel via Redis pub/sub
 * Bridges Laravel (HTTP/background jobs) with Node (WebSocket real-time)
 */

const Redis = require('ioredis');
const logger = require('../utils/logger');

class RedisEventBus {
  constructor(redisClient, io, locationService, driverMatchingService) {
    this.redis = redisClient;
    // Create separate Redis client for pub/sub (ioredis requirement)
    // Use duplicate() to respect REDIS_ENABLED flag and create mock if needed
    this.subscriber = redisClient.duplicate();

    this.io = io;
    this.locationService = locationService;
    this.driverMatchingService = driverMatchingService;

    // Laravel event channels
    this.CHANNELS = {
      RIDE_CREATED: 'laravel:ride.created',
      RIDE_CANCELLED: 'laravel:ride.cancelled',
      RIDE_COMPLETED: 'laravel:ride.completed',
      RIDE_STARTED: 'laravel:ride.started',
      DRIVER_ASSIGNED: 'laravel:driver.assigned',
      PAYMENT_COMPLETED: 'laravel:payment.completed'
    };
  }

  /**
   * Start listening to Laravel events
   */
  start() {
    logger.info('Starting Redis Event Bus');

    // Subscribe to all channels
    Object.values(this.CHANNELS).forEach((channel) => {
      this.subscriber.subscribe(channel, (err) => {
        if (err) {
          logger.error(`Failed to subscribe to ${channel}`, { error: err.message });
        } else {
          logger.info(`Subscribed to ${channel}`);
        }
      });
    });

    // Handle incoming messages
    this.subscriber.on('message', async (channel, message) => {
      try {
        const data = JSON.parse(message);
        await this.handleEvent(channel, data);
      } catch (error) {
        logger.error('Error handling Redis event', {
          channel,
          error: error.message,
          message
        });
      }
    });

    // Handle subscriber errors
    this.subscriber.on('error', (err) => {
      logger.error('Redis subscriber error', { error: err.message });
    });

    this.subscriber.on('connect', () => {
      logger.info('Redis subscriber connected');
    });

    this.subscriber.on('ready', () => {
      logger.info('Redis subscriber ready');
    });
  }

  /**
   * Route events to appropriate handlers
   */
  async handleEvent(channel, data) {
    logger.info('Received event', { channel, data });

    switch (channel) {
      case this.CHANNELS.RIDE_CREATED:
        await this.handleRideCreated(data);
        break;

      case this.CHANNELS.RIDE_CANCELLED:
        await this.handleRideCancelled(data);
        break;

      case this.CHANNELS.RIDE_COMPLETED:
        await this.handleRideCompleted(data);
        break;

      case this.CHANNELS.RIDE_STARTED:
        await this.handleRideStarted(data);
        break;

      case this.CHANNELS.DRIVER_ASSIGNED:
        await this.handleDriverAssigned(data);
        break;

      case this.CHANNELS.PAYMENT_COMPLETED:
        await this.handlePaymentCompleted(data);
        break;

      default:
        logger.warn('Unknown event channel', { channel });
    }
  }

  /**
   * Handle ride created event - dispatch to drivers
   */
  async handleRideCreated(data) {
    logger.info('Handling ride created', { rideId: data.ride_id });

    try {
      await this.driverMatchingService.dispatchRide({
        rideId: data.ride_id,
        pickupLatitude: data.pickup_latitude,
        pickupLongitude: data.pickup_longitude,
        destinationLatitude: data.destination_latitude,
        destinationLongitude: data.destination_longitude,
        vehicleCategoryId: data.vehicle_category_id,
        customerId: data.customer_id,
        estimatedFare: data.estimated_fare
      });
    } catch (error) {
      logger.error('Error dispatching ride', {
        rideId: data.ride_id,
        error: error.message
      });
    }
  }

  /**
   * Handle ride cancelled event
   */
  async handleRideCancelled(data) {
    logger.info('Handling ride cancelled', { rideId: data.ride_id });

    const { ride_id, cancelled_by, driver_id, customer_id } = data;

    // Notify customer
    if (customer_id) {
      this.io.to(`user:${customer_id}`).emit('ride:cancelled', {
        rideId: ride_id,
        cancelledBy: cancelled_by,
        message: 'Your ride has been cancelled'
      });
    }

    // Notify driver
    if (driver_id) {
      this.io.to(`user:${driver_id}`).emit('ride:cancelled', {
        rideId: ride_id,
        cancelledBy: cancelled_by,
        message: 'Ride has been cancelled'
      });

      // Free up driver
      await this.locationService.completeRide(ride_id);
    }

    // Cleanup pending ride data (never use subscriber client for commands beyond pub/sub)
    await this.redis.del(`ride:pending:${ride_id}`);
    await this.redis.del(`ride:notified:${ride_id}`);
    await this.redis.del(`ride:customer:${ride_id}`);
  }

  /**
   * Handle ride completed event
   */
  async handleRideCompleted(data) {
    logger.info('Handling ride completed', { rideId: data.ride_id });

    const { ride_id, driver_id, customer_id, final_fare } = data;

    // Notify customer
    if (customer_id) {
      this.io.to(`user:${customer_id}`).emit('ride:completed', {
        rideId: ride_id,
        finalFare: final_fare,
        message: 'Your ride is complete'
      });
    }

    // Notify driver
    if (driver_id) {
      this.io.to(`user:${driver_id}`).emit('ride:completed', {
        rideId: ride_id,
        finalFare: final_fare,
        message: 'Ride completed successfully'
      });

      // Free up driver
      await this.locationService.completeRide(ride_id);
    }
  }

  /**
   * Handle ride started event
   */
  async handleRideStarted(data) {
    logger.info('Handling ride started', { rideId: data.ride_id });

    const { ride_id, driver_id, customer_id } = data;

    // Notify customer
    if (customer_id) {
      this.io.to(`user:${customer_id}`).emit('ride:started', {
        rideId: ride_id,
        message: 'Your ride has started'
      });
    }

    // Notify driver
    if (driver_id) {
      this.io.to(`user:${driver_id}`).emit('ride:started', {
        rideId: ride_id,
        message: 'Ride started'
      });
    }
  }

  /**
   * Handle driver assigned event
   */
  async handleDriverAssigned(data) {
    logger.info('Handling driver assigned', { rideId: data.ride_id, driverId: data.driver_id });

    const { ride_id, driver_id } = data;

    // Mark driver as busy in location service
    await this.locationService.assignDriverToRide(driver_id, ride_id);
  }

  /**
   * Handle payment completed event
   */
  async handlePaymentCompleted(data) {
    logger.info('Handling payment completed', { rideId: data.ride_id });

    const { ride_id, customer_id, driver_id, amount } = data;

    // Notify customer
    if (customer_id) {
      this.io.to(`user:${customer_id}`).emit('payment:completed', {
        rideId: ride_id,
        amount,
        message: 'Payment processed successfully'
      });
    }

    // Notify driver
    if (driver_id) {
      this.io.to(`user:${driver_id}`).emit('payment:completed', {
        rideId: ride_id,
        amount,
        message: 'Payment received'
      });
    }
  }

  /**
   * Stop listening to events
   */
  stop() {
    logger.info('Stopping Redis Event Bus');

    Object.values(this.CHANNELS).forEach((channel) => {
      this.subscriber.unsubscribe(channel);
    });

    this.subscriber.quit();
  }
}

module.exports = RedisEventBus;
