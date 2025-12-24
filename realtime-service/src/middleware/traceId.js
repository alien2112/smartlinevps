/**
 * Trace ID Middleware for Socket.IO
 * Adds trace_id to socket context for end-to-end debugging
 */

const logger = require('../utils/logger');

/**
 * Generate a trace ID
 * @returns {string} Trace ID in format: trace-{timestamp}-{random}
 */
function generateTraceId() {
    const timestamp = Date.now();
    const random = Math.random().toString(36).substring(2, 10);
    return `trace-${timestamp}-${random}`;
}

/**
 * Socket.IO middleware to add trace ID to each connection
 * @param {Socket} socket 
 * @param {Function} next 
 */
function traceIdMiddleware(socket, next) {
    // Get trace ID from handshake or generate new one
    const traceId = socket.handshake.auth?.traceId
        || socket.handshake.headers?.['x-trace-id']
        || generateTraceId();

    // Attach to socket for use in handlers
    socket.traceId = traceId;

    // Add to logger context for this socket
    socket.log = (level, message, data = {}) => {
        logger[level](message, {
            ...data,
            traceId,
            socketId: socket.id,
            userId: socket.user?.id
        });
    };

    next();
}

/**
 * Wrap socket event handlers with trace logging
 * @param {Socket} socket 
 * @param {string} event 
 * @param {Function} handler 
 */
function wrapHandler(socket, event, handler) {
    return async (data) => {
        const traceId = data?.traceId || socket.traceId || generateTraceId();
        const startTime = Date.now();

        logger.info(`Socket event started: ${event}`, {
            traceId,
            socketId: socket.id,
            userId: socket.user?.id,
            event,
            data: typeof data === 'object' ? JSON.stringify(data).substring(0, 200) : data
        });

        try {
            await handler(data);

            const duration = Date.now() - startTime;
            logger.info(`Socket event completed: ${event}`, {
                traceId,
                socketId: socket.id,
                event,
                durationMs: duration
            });
        } catch (error) {
            const duration = Date.now() - startTime;
            logger.error(`Socket event failed: ${event}`, {
                traceId,
                socketId: socket.id,
                event,
                durationMs: duration,
                error: error.message
            });

            // Emit error to client
            socket.emit('error', {
                message: error.message,
                event,
                traceId
            });
        }
    };
}

module.exports = {
    generateTraceId,
    traceIdMiddleware,
    wrapHandler
};
