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
      PAYMENT_COMPLETED: 'laravel:payment.completed',
      LOST_ITEM_CREATED: 'lost_item:created',
      LOST_ITEM_UPDATED: 'lost_item:updated',
      // NEW: Critical channels for fixing race conditions
      TRIP_ACCEPTED: 'laravel:trip.accepted',     // Driver accepted - notify BOTH parties immediately
      OTP_VERIFIED: 'laravel:otp.verified',       // OTP verified - trip is now ongoing
      DRIVER_ARRIVED: 'laravel:driver.arrived',   // Driver arrived at pickup
      // Batch notification channel for multi-driver dispatch
      BATCH_NOTIFICATION: 'laravel:batch.notification'
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
    const traceId = data.trace_id || 'no-trace';
    const startTime = Date.now();

    logger.info('Received event from Laravel', {
      channel,
      trace_id: traceId,
      ride_id: data.ride_id || data.trip_id,
      driver_id: data.driver_id,
      customer_id: data.customer_id
    });

    try {
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

        case this.CHANNELS.LOST_ITEM_CREATED:
          await this.handleLostItemCreated(data);
          break;

        case this.CHANNELS.LOST_ITEM_UPDATED:
          await this.handleLostItemUpdated(data);
          break;

        // NEW: Critical handlers for race condition fix
        case this.CHANNELS.TRIP_ACCEPTED:
          await this.handleTripAccepted(data);
          break;

        case this.CHANNELS.OTP_VERIFIED:
          await this.handleOtpVerified(data);
          break;

        case this.CHANNELS.DRIVER_ARRIVED:
          await this.handleDriverArrived(data);
          break;

        case this.CHANNELS.BATCH_NOTIFICATION:
          await this.handleBatchNotification(data);
          break;

        default:
          logger.warn('Unknown event channel', { channel, trace_id: traceId });
      }

      const duration = Date.now() - startTime;
      logger.info('Event handled successfully', {
        channel,
        trace_id: traceId,
        duration_ms: duration
      });
    } catch (error) {
      logger.error('Event handler failed', {
        channel,
        trace_id: traceId,
        error: error.message,
        stack: error.stack
      });
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

    const { ride_id, driver_id, customer_id } = data;

    // Mark driver as busy in location service
    await this.locationService.assignDriverToRide(driver_id, ride_id);

    // CRITICAL: Notify driver that trip was accepted successfully
    // This updates the driver's Flutter UI
    if (driver_id) {
      this.io.to(`user:${driver_id}`).emit('ride:accept:success', {
        rideId: ride_id,
        ride_id: ride_id,
        tripId: ride_id,
        status: 'accepted',
        message: 'Trip accepted successfully',
        timestamp: Date.now()
      });
      logger.info('Emitted ride:accept:success to driver', { driver_id, ride_id });
    }

    // Notify customer that driver is assigned
    if (customer_id) {
      this.io.to(`user:${customer_id}`).emit('ride:driver_assigned', {
        rideId: ride_id,
        status: 'accepted',
        message: 'A driver has accepted your ride',
        timestamp: Date.now()
      });
      logger.info('Emitted ride:driver_assigned to customer', { customer_id, ride_id });
    }
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

  /**
   * Handle lost item created event
   */
  async handleLostItemCreated(data) {
    const payload = this.normalizeLostItemPayload(data);
    logger.info('Handling lost item created', {
      lostItemId: payload.id,
      driverId: payload.driverId,
      customerId: payload.customerId
    });

    // Notify driver
    if (payload.driverId) {
      this.io.to(`user:${payload.driverId}`).emit('lost_item:new', {
        ...payload,
        message: 'A customer reported a lost item'
      });
    }

    // Notify customer (confirmation)
    if (payload.customerId) {
      this.io.to(`user:${payload.customerId}`).emit('lost_item:created', {
        ...payload,
        message: 'Lost item report submitted successfully'
      });
    }
  }

  /**
   * Handle lost item updated event
   */
  async handleLostItemUpdated(data) {
    const payload = this.normalizeLostItemPayload(data);
    logger.info('Handling lost item updated', {
      lostItemId: payload.id,
      driverId: payload.driverId,
      customerId: payload.customerId,
      status: payload.status,
      driverResponse: payload.driverResponse
    });

    const updateMessage = payload.driverResponse
      ? `Driver marked item as ${payload.driverResponse}`
      : `Lost item report status updated to ${payload.status}`;

    // Notify customer
    if (payload.customerId) {
      this.io.to(`user:${payload.customerId}`).emit('lost_item:updated', {
        ...payload,
        message: updateMessage
      });
    }

    // Notify driver
    if (payload.driverId) {
      this.io.to(`user:${payload.driverId}`).emit('lost_item:updated', {
        ...payload,
        message: updateMessage
      });
    }
  }

  /**
   * ========================================================================
   * NEW: Critical handler for fixing "second press works" bug
   * ========================================================================
   * Handle trip accepted event - IMMEDIATELY notify driver and customer
   * 
   * This is called BEFORE the Laravel API returns, ensuring the driver app
   * receives socket notification in parallel with the HTTP response.
   */
  async handleTripAccepted(data) {
    const {
      ride_id,
      trip_id,
      driver_id,
      customer_id,
      status,
      otp,
      driver,
      trip,
      trace_id,
      accepted_at
    } = data;

    const rideId = ride_id || trip_id;

    logger.rideEvent('trip_accepted', {
      ride_id: rideId,
      driver_id,
      customer_id,
      trace_id,
      state_before: 'pending',
      state_after: 'accepted'
    });

    // 1. NOTIFY DRIVER FIRST (this fixes the "loading" issue)
    // CRITICAL: Emit 'ride:accept:success' which Flutter driver app listens for
    // (see socket_io_helper.dart line 78)
    if (driver_id) {
      this.io.to(`user:${driver_id}`).emit('ride:accept:success', {
        rideId,
        ride_id: rideId,  // Flutter also checks for ride_id
        tripId: rideId,
        status: 'accepted',
        otp,
        trip,
        message: 'Trip accepted successfully',
        trace_id,
        timestamp: Date.now()
      });

      logger.info('OBSERVE: Emitted ride:accept:success to driver (Flutter compatible)', {
        driver_id,
        ride_id: rideId,
        trace_id
      });
    }

    // 2. Notify customer that driver is assigned
    if (customer_id) {
      this.io.to(`user:${customer_id}`).emit('ride:driver_assigned', {
        rideId,
        driver,
        status: 'accepted',
        message: 'A driver has accepted your ride',
        trace_id,
        timestamp: Date.now()
      });

      logger.info('Emitted ride:driver_assigned to customer', {
        customer_id,
        ride_id: rideId,
        trace_id
      });
    }

    // 3. Notify other drivers that this ride is taken
    // Remove from pending and notify all previously notified drivers
    const notifiedDrivers = await this.redis.smembers(`ride:notified:${rideId}`);

    for (const otherDriverId of notifiedDrivers) {
      if (otherDriverId !== driver_id) {
        this.io.to(`user:${otherDriverId}`).emit('ride:taken', {
          rideId,
          message: 'This ride has been accepted by another driver',
          trace_id
        });
      }
    }

    // 4. Cleanup pending ride data
    await this.redis.del(`ride:pending:${rideId}`);
    await this.redis.del(`ride:notified:${rideId}`);

    // 5. Mark driver as busy
    if (driver_id) {
      await this.locationService.assignDriverToRide(driver_id, rideId);
    }
  }

  /**
   * Handle OTP verified event - Trip is now ONGOING
   * 
   * This is called BEFORE the Laravel API returns, ensuring both apps
   * update their state immediately.
   */
  async handleOtpVerified(data) {
    const { ride_id, trip_id, driver_id, customer_id, status, trace_id, verified_at } = data;
    const rideId = ride_id || trip_id;

    logger.rideEvent('otp_verified', {
      ride_id: rideId,
      driver_id,
      customer_id,
      trace_id,
      state_before: 'accepted',
      state_after: 'ongoing'
    });

    // Notify driver that OTP was verified and trip is starting
    // CRITICAL: Emit 'ride:started' which Flutter driver app listens for
    // (see socket_io_helper.dart line 93)
    if (driver_id) {
      this.io.to(`user:${driver_id}`).emit('ride:started', {
        rideId,
        ride_id: rideId,  // Flutter also checks for ride_id
        tripId: rideId,
        status: 'ongoing',
        message: 'OTP verified - Trip started',
        trace_id,
        timestamp: Date.now()
      });

      logger.info('OBSERVE: Emitted ride:started to driver (Flutter compatible)', {
        driver_id,
        ride_id: rideId,
        trace_id
      });
    }

    // Notify customer that trip has started
    if (customer_id) {
      this.io.to(`user:${customer_id}`).emit('ride:started', {
        rideId,
        ride_id: rideId,
        status: 'ongoing',
        message: 'Your trip has started',
        trace_id,
        timestamp: Date.now()
      });

      logger.info('OBSERVE: Emitted ride:started to customer', {
        customer_id,
        ride_id: rideId,
        trace_id
      });
    }
  }

  /**
   * Handle driver arrived at pickup event
   */
  async handleDriverArrived(data) {
    const { ride_id, trip_id, driver_id, customer_id, trace_id, arrived_at } = data;
    const rideId = ride_id || trip_id;

    logger.rideEvent('driver_arrived', {
      ride_id: rideId,
      driver_id,
      customer_id,
      trace_id
    });

    // Notify customer that driver has arrived
    if (customer_id) {
      this.io.to(`user:${customer_id}`).emit('ride:driver_arrived', {
        rideId,
        message: 'Your driver has arrived at the pickup location',
        arrivedAt: arrived_at,
        trace_id,
        timestamp: Date.now()
      });

      logger.info('Emitted ride:driver_arrived to customer', {
        customer_id,
        ride_id: rideId,
        trace_id
      });
    }

    // Confirm to driver
    if (driver_id) {
      this.io.to(`user:${driver_id}`).emit('trip:arrival:confirmed', {
        rideId,
        message: 'Arrival confirmed',
        trace_id,
        timestamp: Date.now()
      });
    }
  }

  /**
   * Handle batch notification event - Fan out ride:new to multiple drivers
   * 
   * This is the key optimization: Laravel sends ONE Redis message with array of driver_ids,
   * and Node.js fans out to individual socket connections.
   * 
   * This replaces the old pattern of Laravel publishing N individual messages.
   */
  async handleBatchNotification(data) {
    const {
      driver_ids,
      ride_id,
      trip_id,
      customer_id,
      event_type,
      pickup_latitude,
      pickup_longitude,
      destination_latitude,
      destination_longitude,
      vehicle_category_id,
      estimated_fare,
      type,
      created_at,
      trace_id
    } = data;

    const rideId = ride_id || trip_id;
    const driverIds = driver_ids || [];

    if (driverIds.length === 0) {
      logger.warn('Batch notification received with empty driver_ids', { trace_id, ride_id: rideId });
      return;
    }

    logger.info('Handling batch notification - fanning out to drivers', {
      ride_id: rideId,
      driver_count: driverIds.length,
      event_type,
      trace_id
    });

    // Build the ride payload once
    const ridePayload = {
      rideId,
      ride_id: rideId,
      tripId: rideId,
      trip_id: rideId,
      customerId: customer_id,
      customer_id,
      pickupLatitude: pickup_latitude,
      pickupLongitude: pickup_longitude,
      destinationLatitude: destination_latitude,
      destinationLongitude: destination_longitude,
      vehicleCategoryId: vehicle_category_id,
      estimatedFare: estimated_fare,
      type,
      createdAt: created_at,
      trace_id,
      timestamp: Date.now()
    };

    // Fan out to each driver's socket room
    let sentCount = 0;
    for (const driverId of driverIds) {
      const room = `user:${driverId}`;
      
      // Emit ride:new event (this is what Flutter driver app listens for)
      this.io.to(room).emit('ride:new', ridePayload);
      
      // Track which drivers were notified for this ride
      await this.redis.sadd(`ride:notified:${rideId}`, String(driverId));
      
      sentCount++;
    }

    // Set expiry on the notified set (30 minutes - ride request timeout)
    await this.redis.expire(`ride:notified:${rideId}`, 1800);

    logger.info('Batch notification fan-out complete', {
      ride_id: rideId,
      drivers_notified: sentCount,
      event_type,
      trace_id
    });
  }

  /**
   * Normalize payload from Laravel (supports legacy flat payload and new rich resource)
   */
  normalizeLostItemPayload(data) {
    const body = data?.lost_item || data || {};

    const tripRequestId = body.trip_request_id || data?.trip_request_id || body.trip?.id;
    const driverId = body.driver_id || data?.driver_id || body.driver?.id;
    const customerId = body.customer_id || data?.customer_id || body.customer?.id;

    return {
      id: body.id || data?.id,
      tripRequestId,
      trip_request_id: tripRequestId, // keep snake_case for existing clients
      customerId,
      driverId,
      category: body.category || data?.category,
      description: body.description || data?.description,
      imageUrl: body.image_url || data?.image_url,
      status: body.status || data?.status,
      driverResponse: body.driver_response || data?.driver_response,
      driverNotes: body.driver_notes || data?.driver_notes,
      adminNotes: body.admin_notes || data?.admin_notes,
      contactPreference: body.contact_preference || data?.contact_preference,
      itemLostAt: body.item_lost_at || data?.item_lost_at,
      createdAt: body.created_at || data?.created_at,
      updatedAt: body.updated_at || data?.updated_at,
      trip: body.trip || data?.trip,
      customer: body.customer || data?.customer,
      driver: body.driver || data?.driver,
      statusLogs: body.status_logs || data?.status_logs || []
    };
  }
}

module.exports = RedisEventBus;

