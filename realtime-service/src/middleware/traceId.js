/**
 * Trace ID Middleware for Socket.IO
 * Propagates trace_id from Laravel through socket events
 */

const { v4: uuidv4 } = require('uuid');
const logger = require('../utils/logger');

/**
 * Generate a trace ID in the same format as Laravel
 */
function generateTraceId() {
    const timestamp = Math.floor(Date.now() / 1000);
    const random = Math.random().toString(36).substring(2, 10);
    return `trc_${timestamp}_${random}`;
}

/**
 * Socket.IO middleware to add trace context to every event
 */
function traceMiddleware(socket, next) {
    // Store trace context on socket for use in event handlers
    socket.traceContext = {
        socketId: socket.id,
        userId: socket.user?.id,
        userType: socket.user?.type,
        connectedAt: new Date().toISOString()
    };

    // Override socket.emit to add trace_id to all outgoing events
    const originalEmit = socket.emit.bind(socket);
    socket.emit = function (event, ...args) {
        // Add trace context to data if it's an object
        if (args.length > 0 && typeof args[0] === 'object' && args[0] !== null) {
            args[0] = {
                ...args[0],
                _trace: {
                    trace_id: args[0].trace_id || generateTraceId(),
                    socket_id: socket.id,
                    user_id: socket.user?.id,
                    timestamp: new Date().toISOString()
                }
            };
        }
        return originalEmit(event, ...args);
    };

    next();
}

/**
 * Create a trace context for an event
 * Use this in event handlers to create consistent logging
 */
function createEventTraceContext(socket, eventName, data = {}) {
    const traceId = data?.trace_id || data?._trace?.trace_id || generateTraceId();

    return {
        trace_id: traceId,
        event: eventName,
        socket_id: socket.id,
        user_id: socket.user?.id,
        user_type: socket.user?.type,
        timestamp: new Date().toISOString(),
        // Include ride/trip info if present
        ride_id: data?.rideId || data?.ride_id,
        trip_id: data?.tripId || data?.trip_id
    };
}

/**
 * Log helper that includes trace context
 */
function logWithTrace(level, message, traceContext, additionalData = {}) {
    const logData = {
        ...traceContext,
        ...additionalData
    };

    logger[level](message, logData);
}

/**
 * Wrap an async event handler with trace logging
 */
function traceEventHandler(eventName, handler) {
    return async function (data, ack) {
        const socket = this;
        const traceContext = createEventTraceContext(socket, eventName, data);
        const startTime = Date.now();

        logWithTrace('info', `Event received: ${eventName}`, traceContext, {
            state_before: 'processing'
        });

        try {
            await handler.call(socket, data, ack, traceContext);

            const duration = Date.now() - startTime;
            logWithTrace('info', `Event completed: ${eventName}`, traceContext, {
                state_after: 'success',
                duration_ms: duration
            });
        } catch (error) {
            const duration = Date.now() - startTime;
            logWithTrace('error', `Event failed: ${eventName}`, traceContext, {
                state_after: 'error',
                error: error.message,
                stack: error.stack,
                duration_ms: duration
            });

            // Emit error to client
            socket.emit('error', {
                message: error.message,
                event: eventName,
                trace_id: traceContext.trace_id
            });
        }
    };
}

module.exports = {
    traceMiddleware,
    createEventTraceContext,
    logWithTrace,
    traceEventHandler,
    generateTraceId
};
