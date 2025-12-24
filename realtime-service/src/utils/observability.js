/**
 * Deep Observability Service for Socket.IO
 * 
 * PURPOSE: OBSERVE ONLY - NO FIXES, NO LOGIC CHANGES
 * 
 * This service wraps socket event handlers with comprehensive logging
 * to help diagnose timing, ordering, and delivery issues.
 */

const { v4: uuidv4 } = require('uuid');

// In-memory metrics for this instance (not for production scaling)
const metrics = {
    eventsReceived: {},
    eventsEmitted: {},
    handlerDurations: [],
    connectionCount: 0,
    errors: []
};

/**
 * Generate a trace ID that can be correlated across systems
 */
function generateTraceId() {
    const ts = Date.now();
    const rand = Math.random().toString(36).substring(2, 8);
    return `trc_${ts}_${rand}`;
}

/**
 * Create a structured log entry
 */
function createLogEntry(context) {
    return {
        timestamp: new Date().toISOString(),
        timestamp_ms: Date.now(),
        service: 'node-realtime',
        ...context
    };
}

/**
 * Observability wrapper for socket connection
 */
function observeConnection(socket, logger) {
    const connectionTime = Date.now();
    const userId = socket.user?.id || 'unknown';
    const userType = socket.user?.type || 'unknown';
    const socketId = socket.id;

    // Log connection
    logger.info('OBSERVE: Socket connected', createLogEntry({
        event_type: 'socket_connect',
        source: 'socket',
        status: 'connected',
        socket_id: socketId,
        user_id: userId,
        user_type: userType,
        rooms_joined: Array.from(socket.rooms || []),
        transport: socket.conn?.transport?.name || 'unknown',
        remote_address: socket.handshake?.address || 'unknown'
    }));

    metrics.connectionCount++;

    // Track room joins
    const originalJoin = socket.join.bind(socket);
    socket.join = function (room) {
        logger.info('OBSERVE: Socket joining room', createLogEntry({
            event_type: 'room_join',
            source: 'socket',
            socket_id: socketId,
            user_id: userId,
            room: room
        }));
        return originalJoin(room);
    };

    // Track emits FROM server TO client
    const originalEmit = socket.emit.bind(socket);
    socket.emit = function (event, ...args) {
        const emitTime = Date.now();
        const traceId = args[0]?.trace_id || args[0]?._trace?.trace_id || generateTraceId();

        // Don't log high-frequency events
        if (!['pong', 'driver:location:ack'].includes(event)) {
            logger.info('OBSERVE: Emitting to client', createLogEntry({
                event_type: 'socket_emit',
                source: 'socket',
                direction: 'outbound',
                status: 'emitting',
                trace_id: traceId,
                socket_id: socketId,
                user_id: userId,
                user_type: userType,
                event_name: event,
                payload_keys: args[0] ? Object.keys(args[0]) : [],
                has_ride_id: !!(args[0]?.rideId || args[0]?.ride_id),
                ride_id: args[0]?.rideId || args[0]?.ride_id || null
            }));
        }

        metrics.eventsEmitted[event] = (metrics.eventsEmitted[event] || 0) + 1;

        return originalEmit(event, ...args);
    };

    // Return disconnect observer
    return function observeDisconnect(reason) {
        const disconnectTime = Date.now();
        const sessionDuration = disconnectTime - connectionTime;

        logger.info('OBSERVE: Socket disconnected', createLogEntry({
            event_type: 'socket_disconnect',
            source: 'socket',
            status: 'disconnected',
            socket_id: socketId,
            user_id: userId,
            user_type: userType,
            reason: reason,
            session_duration_ms: sessionDuration
        }));

        metrics.connectionCount--;
    };
}

/**
 * Wrap a socket event handler with observability
 * 
 * DOES NOT CHANGE LOGIC - only adds logging around the handler
 */
function observeEventHandler(eventName, handler, logger) {
    return async function observedHandler(data, ack) {
        const socket = this;
        const startTime = Date.now();
        const traceId = data?.trace_id || generateTraceId();
        const userId = socket.user?.id || 'unknown';
        const userType = socket.user?.type || 'unknown';
        const socketId = socket.id;

        // Extract ride/trip info if present
        const rideId = data?.rideId || data?.ride_id || data?.trip_request_id || null;
        const driverId = userType === 'driver' ? userId : (data?.driver_id || null);
        const customerId = userType === 'customer' ? userId : (data?.customer_id || null);

        // Log event received
        logger.info('OBSERVE: Event received', createLogEntry({
            event_type: 'socket_event_received',
            source: 'socket',
            direction: 'inbound',
            status: 'started',
            trace_id: traceId,
            socket_id: socketId,
            user_id: userId,
            user_type: userType,
            event_name: eventName,
            ride_id: rideId,
            driver_id: driverId,
            customer_id: customerId,
            payload_keys: data ? Object.keys(data) : [],
            has_ack: typeof ack === 'function'
        }));

        metrics.eventsReceived[eventName] = (metrics.eventsReceived[eventName] || 0) + 1;

        let result = null;
        let error = null;
        let status = 'success';

        try {
            // Call the original handler
            result = await handler.call(socket, data, ack);
        } catch (err) {
            error = err;
            status = 'error';

            logger.error('OBSERVE: Event handler error', createLogEntry({
                event_type: 'socket_event_error',
                source: 'socket',
                status: 'error',
                trace_id: traceId,
                socket_id: socketId,
                user_id: userId,
                event_name: eventName,
                ride_id: rideId,
                error_message: err.message,
                error_stack: err.stack?.substring(0, 500)
            }));

            metrics.errors.push({
                timestamp: Date.now(),
                event: eventName,
                error: err.message
            });

            // Re-throw to preserve original behavior
            throw err;
        } finally {
            const endTime = Date.now();
            const duration = endTime - startTime;

            // Log event completed
            logger.info('OBSERVE: Event handler completed', createLogEntry({
                event_type: 'socket_event_completed',
                source: 'socket',
                status: status,
                trace_id: traceId,
                socket_id: socketId,
                user_id: userId,
                user_type: userType,
                event_name: eventName,
                ride_id: rideId,
                duration_ms: duration,
                slow_handler: duration > 1000
            }));

            metrics.handlerDurations.push({
                event: eventName,
                duration,
                timestamp: endTime
            });

            // Keep only last 100 durations
            if (metrics.handlerDurations.length > 100) {
                metrics.handlerDurations.shift();
            }
        }

        return result;
    };
}

/**
 * Observe Redis pub/sub events
 */
function observeRedisEvent(channel, data, logger) {
    const traceId = data?.trace_id || generateTraceId();

    logger.info('OBSERVE: Redis event received', createLogEntry({
        event_type: 'redis_pubsub_received',
        source: 'redis',
        status: 'received',
        trace_id: traceId,
        channel: channel,
        ride_id: data?.ride_id || data?.trip_id || null,
        driver_id: data?.driver_id || null,
        customer_id: data?.customer_id || null,
        payload_keys: data ? Object.keys(data) : [],
        published_at: data?.published_at || null
    }));

    return traceId;
}

/**
 * Observe Redis event handling completion
 */
function observeRedisEventComplete(channel, traceId, startTime, status, logger, error = null) {
    const duration = Date.now() - startTime;

    logger.info('OBSERVE: Redis event handled', createLogEntry({
        event_type: 'redis_pubsub_handled',
        source: 'redis',
        status: status,
        trace_id: traceId,
        channel: channel,
        duration_ms: duration,
        error: error?.message || null
    }));
}

/**
 * Observe io.to().emit() calls
 */
function observeBroadcast(io, logger) {
    const originalTo = io.to.bind(io);

    io.to = function (room) {
        const roomManager = originalTo(room);
        const originalRoomEmit = roomManager.emit.bind(roomManager);

        roomManager.emit = function (event, ...args) {
            const traceId = args[0]?.trace_id || generateTraceId();
            const rideId = args[0]?.rideId || args[0]?.ride_id || null;

            logger.info('OBSERVE: Broadcasting to room', createLogEntry({
                event_type: 'socket_broadcast',
                source: 'socket',
                direction: 'outbound',
                status: 'broadcasting',
                trace_id: traceId,
                room: room,
                event_name: event,
                ride_id: rideId,
                payload_keys: args[0] ? Object.keys(args[0]) : []
            }));

            return originalRoomEmit(event, ...args);
        };

        return roomManager;
    };
}

/**
 * Get current metrics (for health endpoint)
 */
function getMetrics() {
    return {
        ...metrics,
        uptime_ms: process.uptime() * 1000,
        memory: process.memoryUsage(),
        timestamp: new Date().toISOString()
    };
}

module.exports = {
    generateTraceId,
    createLogEntry,
    observeConnection,
    observeEventHandler,
    observeRedisEvent,
    observeRedisEventComplete,
    observeBroadcast,
    getMetrics
};
