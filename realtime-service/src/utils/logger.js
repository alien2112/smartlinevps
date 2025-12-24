/**
 * Winston Logger Configuration
 * Centralized logging for the real-time service with trace ID support
 */

const winston = require('winston');
const path = require('path');
const fs = require('fs');

// Create logs directory if it doesn't exist
const logDir = process.env.LOG_DIR || './logs';
if (!fs.existsSync(logDir)) {
  fs.mkdirSync(logDir, { recursive: true });
}

// Custom format that ensures trace_id is always visible
const traceFormat = winston.format((info) => {
  // Ensure trace_id is at top level for easy grep
  if (info.trace_id) {
    info.trace_id = info.trace_id;
  }

  // Add service identifier
  info.service = 'smartline-realtime';
  info.node_env = process.env.NODE_ENV || 'development';

  return info;
});

// Define log format for files (JSON for parsing)
const fileFormat = winston.format.combine(
  winston.format.timestamp({ format: 'YYYY-MM-DDTHH:mm:ss.SSSZ' }),
  winston.format.errors({ stack: true }),
  traceFormat(),
  winston.format.json()
);

// Console format for development (readable)
const consoleFormat = winston.format.combine(
  winston.format.colorize(),
  winston.format.timestamp({ format: 'HH:mm:ss.SSS' }),
  winston.format.printf(({ timestamp, level, message, trace_id, ...meta }) => {
    // Highlight trace_id for easy visual tracking
    const traceStr = trace_id ? `[${trace_id}]` : '';
    let msg = `${timestamp} ${level} ${traceStr}: ${message}`;

    // Add important metadata inline
    const importantKeys = ['ride_id', 'trip_id', 'driver_id', 'customer_id', 'user_id', 'event', 'duration_ms', 'error'];
    const important = {};
    const rest = {};

    for (const [key, value] of Object.entries(meta)) {
      if (importantKeys.includes(key) && value !== undefined) {
        important[key] = value;
      } else if (!['service', 'node_env', 'socket_id', 'timestamp'].includes(key) && value !== undefined) {
        rest[key] = value;
      }
    }

    if (Object.keys(important).length > 0) {
      msg += ` ${JSON.stringify(important)}`;
    }
    if (Object.keys(rest).length > 0 && process.env.LOG_LEVEL === 'debug') {
      msg += ` | ${JSON.stringify(rest)}`;
    }

    return msg;
  })
);

// Create logger
const logger = winston.createLogger({
  level: process.env.LOG_LEVEL || 'info',
  format: fileFormat,
  defaultMeta: { service: 'smartline-realtime' },
  transports: [
    // Error log file - only errors
    new winston.transports.File({
      filename: path.join(logDir, 'error.log'),
      level: 'error',
      maxsize: 10485760, // 10MB
      maxFiles: 5,
      tailable: true
    }),
    // Combined log file - all levels
    new winston.transports.File({
      filename: path.join(logDir, 'combined.log'),
      maxsize: 10485760, // 10MB
      maxFiles: 10,
      tailable: true
    }),
    // Ride-specific log file for debugging ride issues
    new winston.transports.File({
      filename: path.join(logDir, 'rides.log'),
      level: 'info',
      maxsize: 10485760, // 10MB
      maxFiles: 5,
      tailable: true,
      // Only log ride-related events
      format: winston.format.combine(
        winston.format((info) => {
          // Only include logs that have ride_id or trip_id or are ride events
          if (info.ride_id || info.trip_id ||
            info.event?.includes('ride') ||
            info.message?.includes('ride') ||
            info.message?.includes('Ride')) {
            return info;
          }
          return false;
        })(),
        fileFormat
      )
    })
  ]
});

// Add console transport in development
if (process.env.NODE_ENV !== 'production') {
  logger.add(
    new winston.transports.Console({
      format: consoleFormat
    })
  );
} else {
  // In production, still log to console but with less verbosity
  logger.add(
    new winston.transports.Console({
      level: 'warn',
      format: winston.format.combine(
        winston.format.timestamp(),
        winston.format.json()
      )
    })
  );
}

/**
 * Create a child logger with ride context
 * @param {Object} context - Context object with ride_id, driver_id, etc.
 * @returns {Object} - Child logger with context
 */
logger.withRideContext = function (context) {
  return {
    info: (msg, extra = {}) => logger.info(msg, { ...context, ...extra }),
    warn: (msg, extra = {}) => logger.warn(msg, { ...context, ...extra }),
    error: (msg, extra = {}) => logger.error(msg, { ...context, ...extra }),
    debug: (msg, extra = {}) => logger.debug(msg, { ...context, ...extra })
  };
};

/**
 * Quick ride event logger
 * @param {string} event - Event name
 * @param {Object} data - Event data
 */
logger.rideEvent = function (event, data = {}) {
  const { ride_id, trip_id, driver_id, customer_id, trace_id, ...rest } = data;

  logger.info(`Ride Event: ${event}`, {
    event,
    ride_id: ride_id || trip_id,
    trip_id: trip_id || ride_id,
    driver_id,
    customer_id,
    trace_id,
    state_before: rest.state_before,
    state_after: rest.state_after,
    ...rest
  });
};

module.exports = logger;
