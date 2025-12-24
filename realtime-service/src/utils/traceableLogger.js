/**
 * TraceableLogger - Deep observability logging for Node.js Socket.IO service
 * 
 * Provides structured logging for debugging delays, duplicates, and missing updates.
 * All logs include trace_id for correlation across HTTP, Socket, Queue, and FCM.
 * 
 * Log Format:
 * {
 *   "trace_id": "abc123",
 *   "user_id": 1,
 *   "driver_id": 2,
 *   "ride_id": 100,
 *   "event_name": "ride_accept",
 *   "source": "socket",
 *   "timestamp": "2024-01-01T12:00:00.000Z",
 *   "duration_ms": 150,
 *   "status": "started|success|failed|timeout",
 *   "socket_id": "abc123"
 * }
 */

const crypto = require('crypto');
const logger = require('./logger');

class TraceableLogger {
  constructor() {
    // In-memory timing tracker for measuring durations
    this.timings = new Map();
    
    // Cleanup old timings every 5 minutes
    setInterval(() => {
      const now = Date.now();
      for (const [key, value] of this.timings) {
        if (now - value.startTime > 300000) { // 5 minutes
          this.timings.delete(key);
        }
      }
    }, 60000);
  }

  /**
   * Generate a new trace ID (UUID v4 compatible)
   */
  generateTraceId() {
    return crypto.randomUUID();
  }

  /**
   * Build base context for all logs
   */
  _baseContext(eventName, source, status, extra = {}) {
    return {
      trace_id: extra.trace_id || this.generateTraceId(),
      event_name: eventName,
      source: source,
      status: status,
      timestamp: new Date().toISOString(),
      timestamp_ms: Date.now(),
      ...extra
    };
  }

  /**
   * Start timing for an operation
   * @returns {string} timing key for stopTiming
   */
  startTiming(eventName, context = {}) {
    const key = `${eventName}:${context.ride_id || context.socket_id || Date.now()}`;
    this.timings.set(key, {
      startTime: Date.now(),
      eventName,
      context
    });
    return key;
  }

  /**
   * Stop timing and return duration in ms
   */
  stopTiming(key) {
    const timing = this.timings.get(key);
    if (!timing) return null;
    
    const durationMs = Date.now() - timing.startTime;
    this.timings.delete(key);
    return durationMs;
  }

  // ========================================
  // SOCKET CONNECTION LIFECYCLE
  // ========================================

  /**
   * Log client connection
   */
  socketConnected(socketId, userId, userType, extra = {}) {
    const context = this._baseContext('socket_connected', 'socket', 'success', {
      socket_id: socketId,
      user_id: userId,
      user_type: userType,
      ...extra
    });

    logger.info('socket_connected', context);
    return context.trace_id;
  }

  /**
   * Log client disconnection
   */
  socketDisconnected(socketId, userId, userType, reason, extra = {}) {
    const context = this._baseContext('socket_disconnected', 'socket', 'success', {
      socket_id: socketId,
      user_id: userId,
      user_type: userType,
      disconnect_reason: reason,
      ...extra
    });

    logger.info('socket_disconnected', context);
  }

  /**
   * Log socket authentication
   */
  socketAuthStarted(socketId, extra = {}) {
    const timingKey = this.startTiming('socket_auth', { socket_id: socketId });
    
    const context = this._baseContext('socket_auth', 'socket', 'started', {
      socket_id: socketId,
      ...extra
    });

    logger.info('socket_auth_started', context);
    return { traceId: context.trace_id, timingKey };
  }

  socketAuthCompleted(socketId, success, timingKey, userId = null, userType = null, extra = {}) {
    const durationMs = this.stopTiming(timingKey);
    
    const context = this._baseContext('socket_auth', 'socket', success ? 'success' : 'failed', {
      socket_id: socketId,
      user_id: userId,
      user_type: userType,
      duration_ms: durationMs,
      ...extra
    });

    if (success) {
      logger.info('socket_auth_completed', context);
    } else {
      logger.warn('socket_auth_failed', context);
    }
  }

  // ========================================
  // SOCKET EVENT HANDLER LIFECYCLE
  // ========================================

  /**
   * Log socket event handler started
   */
  eventHandlerStarted(eventName, socketId, userId, data = {}, extra = {}) {
    const timingKey = this.startTiming(eventName, { socket_id: socketId, ...data });
    
    const context = this._baseContext(eventName, 'socket', 'started', {
      socket_id: socketId,
      user_id: userId,
      ride_id: data.rideId || data.ride_id,
      driver_id: data.driverId || data.driver_id,
      data_keys: Object.keys(data),
      ...extra
    });

    logger.info(`event_handler_started:${eventName}`, context);
    return { traceId: context.trace_id, timingKey };
  }

  /**
   * Log socket event handler completed
   */
  eventHandlerCompleted(eventName, success, timingKey, extra = {}) {
    const durationMs = this.stopTiming(timingKey);
    
    const context = this._baseContext(eventName, 'socket', success ? 'success' : 'failed', {
      duration_ms: durationMs,
      ...extra
    });

    // Flag slow handlers
    if (durationMs && durationMs > 1000) {
      context.slow_handler = true;
      logger.warn(`event_handler_slow:${eventName}`, context);
    } else {
      logger.info(`event_handler_completed:${eventName}`, context);
    }
  }

  // ========================================
  // SOCKET EMIT LIFECYCLE
  // ========================================

  /**
   * Log event emission (before emit)
   */
  emitStarted(eventName, room, data = {}, extra = {}) {
    const context = this._baseContext(`emit:${eventName}`, 'socket', 'started', {
      room: room,
      ride_id: data.rideId || data.ride_id,
      driver_id: data.driverId || data.driver_id,
      customer_id: data.customerId || data.customer_id,
      data_keys: Object.keys(data),
      ...extra
    });

    logger.info('socket_emit_started', context);
    return context.trace_id;
  }

  /**
   * Log event emission completed (after emit)
   */
  emitCompleted(eventName, room, success, extra = {}) {
    const context = this._baseContext(`emit:${eventName}`, 'socket', success ? 'success' : 'failed', {
      room: room,
      ...extra
    });

    logger.info('socket_emit_completed', context);
  }

  // ========================================
  // RIDE LIFECYCLE EVENTS
  // ========================================

  /**
   * Log ride dispatch started
   */
  rideDispatchStarted(rideId, customerId, extra = {}) {
    const timingKey = this.startTiming('ride_dispatch', { ride_id: rideId });
    
    const context = this._baseContext('ride_dispatch', 'socket', 'started', {
      ride_id: rideId,
      customer_id: customerId,
      ...extra
    });

    logger.info('ride_dispatch_started', context);
    return { traceId: context.trace_id, timingKey };
  }

  /**
   * Log ride dispatch completed
   */
  rideDispatchCompleted(rideId, driverCount, timingKey, extra = {}) {
    const durationMs = this.stopTiming(timingKey);
    
    const context = this._baseContext('ride_dispatch', 'socket', 'success', {
      ride_id: rideId,
      drivers_notified: driverCount,
      duration_ms: durationMs,
      ...extra
    });

    logger.info('ride_dispatch_completed', context);
  }

  /**
   * Log driver accept ride started
   */
  driverAcceptStarted(driverId, rideId, extra = {}) {
    const timingKey = this.startTiming('driver_accept', { ride_id: rideId, driver_id: driverId });
    
    const context = this._baseContext('driver_accept_ride', 'socket', 'started', {
      driver_id: driverId,
      ride_id: rideId,
      ...extra
    });

    logger.info('driver_accept_started', context);
    return { traceId: context.trace_id, timingKey };
  }

  /**
   * Log driver accept ride result
   */
  driverAcceptCompleted(driverId, rideId, success, timingKey, reason = null, extra = {}) {
    const durationMs = this.stopTiming(timingKey);
    
    const context = this._baseContext('driver_accept_ride', 'socket', success ? 'success' : 'failed', {
      driver_id: driverId,
      ride_id: rideId,
      duration_ms: durationMs,
      ...extra
    });

    if (!success && reason) {
      context.failure_reason = reason;
    }

    if (success) {
      logger.info('driver_accept_completed', context);
    } else {
      logger.warn('driver_accept_failed', context);
    }
  }

  /**
   * Log ride status change
   */
  rideStatusChanged(rideId, fromStatus, toStatus, triggeredBy, extra = {}) {
    const context = this._baseContext('ride_status_change', 'socket', 'success', {
      ride_id: rideId,
      from_status: fromStatus,
      to_status: toStatus,
      triggered_by: triggeredBy,
      ...extra
    });

    logger.info('ride_status_changed', context);
  }

  // ========================================
  // REDIS EVENT BUS
  // ========================================

  /**
   * Log Redis event received
   */
  redisEventReceived(channel, data, extra = {}) {
    const context = this._baseContext(`redis:${channel}`, 'socket', 'started', {
      channel: channel,
      ride_id: data.ride_id || data.rideId,
      driver_id: data.driver_id || data.driverId,
      customer_id: data.customer_id || data.customerId,
      data_keys: Object.keys(data),
      ...extra
    });

    logger.info('redis_event_received', context);
    return context.trace_id;
  }

  /**
   * Log Redis event processed
   */
  redisEventProcessed(channel, success, durationMs = null, extra = {}) {
    const context = this._baseContext(`redis:${channel}`, 'socket', success ? 'success' : 'failed', {
      channel: channel,
      duration_ms: durationMs,
      ...extra
    });

    logger.info('redis_event_processed', context);
  }

  // ========================================
  // LARAVEL API CALLS
  // ========================================

  /**
   * Log Laravel API call started
   */
  laravelApiStarted(endpoint, method, data = {}, extra = {}) {
    const timingKey = this.startTiming('laravel_api', { endpoint });
    
    const context = this._baseContext('laravel_api_call', 'socket', 'started', {
      endpoint: endpoint,
      method: method,
      ride_id: data.ride_id || data.rideId,
      driver_id: data.driver_id || data.driverId,
      ...extra
    });

    logger.info('laravel_api_started', context);
    return { traceId: context.trace_id, timingKey };
  }

  /**
   * Log Laravel API call completed
   */
  laravelApiCompleted(endpoint, success, statusCode, timingKey, extra = {}) {
    const durationMs = this.stopTiming(timingKey);
    
    const context = this._baseContext('laravel_api_call', 'socket', success ? 'success' : 'failed', {
      endpoint: endpoint,
      status_code: statusCode,
      duration_ms: durationMs,
      ...extra
    });

    // Flag slow API calls
    if (durationMs && durationMs > 2000) {
      context.slow_api_call = true;
      logger.warn('laravel_api_slow', context);
    } else if (success) {
      logger.info('laravel_api_completed', context);
    } else {
      logger.error('laravel_api_failed', context);
    }
  }

  // ========================================
  // ACKNOWLEDGMENT TRACKING
  // ========================================

  /**
   * Log acknowledgment received
   */
  ackReceived(eventName, socketId, data = {}, extra = {}) {
    const context = this._baseContext(`ack:${eventName}`, 'socket', 'success', {
      socket_id: socketId,
      ride_id: data.rideId || data.ride_id,
      ...extra
    });

    logger.info('acknowledgment_received', context);
  }

  /**
   * Log acknowledgment timeout
   */
  ackTimeout(eventName, socketId, timeoutMs, extra = {}) {
    const context = this._baseContext(`ack:${eventName}`, 'socket', 'timeout', {
      socket_id: socketId,
      timeout_ms: timeoutMs,
      ...extra
    });

    logger.warn('acknowledgment_timeout', context);
  }

  // ========================================
  // TIMING MEASUREMENTS
  // ========================================

  /**
   * Measure and log time between two events
   */
  measureTimeBetween(fromEvent, toEvent, startTimeMs, rideId = null, extra = {}) {
    const durationMs = Date.now() - startTimeMs;
    
    const context = this._baseContext('timing_measurement', 'socket', 'success', {
      from_event: fromEvent,
      to_event: toEvent,
      duration_ms: durationMs,
      ride_id: rideId,
      ...extra
    });

    // Flag concerning delays
    if (durationMs > 2000) {
      context.delay_warning = true;
      logger.warn('timing_delay_detected', context);
    } else {
      logger.info('timing_measurement', context);
    }
  }

  // ========================================
  // DUPLICATE/ANOMALY DETECTION
  // ========================================

  /**
   * Log duplicate action detected
   */
  duplicateActionDetected(actionType, rideId, userId, extra = {}) {
    const context = this._baseContext('duplicate_action', 'socket', 'warning', {
      action_type: actionType,
      ride_id: rideId,
      user_id: userId,
      ...extra
    });

    logger.warn('duplicate_action_detected', context);
  }

  /**
   * Log out-of-order event
   */
  outOfOrderEventDetected(expectedStatus, actualStatus, rideId, extra = {}) {
    const context = this._baseContext('out_of_order_event', 'socket', 'warning', {
      expected_status: expectedStatus,
      actual_status: actualStatus,
      ride_id: rideId,
      ...extra
    });

    logger.warn('out_of_order_event_detected', context);
  }

  // ========================================
  // DRIVER LOCATION UPDATES
  // ========================================

  /**
   * Log location update (sampled - don't log every update)
   */
  locationUpdateReceived(driverId, rideId = null, extra = {}) {
    // Only log every 10th update or if there's a ride
    const shouldLog = rideId || Math.random() < 0.1;
    
    if (shouldLog) {
      const context = this._baseContext('driver_location_update', 'socket', 'success', {
        driver_id: driverId,
        ride_id: rideId,
        ...extra
      });

      logger.debug('location_update_received', context);
    }
  }
}

// Singleton instance
const traceableLogger = new TraceableLogger();

module.exports = traceableLogger;
