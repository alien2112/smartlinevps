// ============================================
// 📝 STRUCTURED LOGGING
// ============================================

const winston = require('winston');
const path = require('path');

// Define log format (no PII, no secrets)
const logFormat = winston.format.combine(
    winston.format.timestamp({ format: 'YYYY-MM-DD HH:mm:ss' }),
    winston.format.errors({ stack: true }),
    winston.format.splat(),
    winston.format.json()
);

// Console format for development
const consoleFormat = winston.format.combine(
    winston.format.colorize(),
    winston.format.timestamp({ format: 'HH:mm:ss' }),
    winston.format.printf(({ level, message, timestamp, ...meta }) => {
        let msg = `${timestamp} [${level}]: ${message}`;
        if (Object.keys(meta).length > 0) {
            msg += ` ${JSON.stringify(meta)}`;
        }
        return msg;
    })
);

// Create logger
const logger = winston.createLogger({
    level: process.env.LOG_LEVEL || 'info',
    format: logFormat,
    defaultMeta: { service: 'ride-support-bot' },
    transports: [
        // Write all logs to console
        new winston.transports.Console({
            format: process.env.NODE_ENV === 'production' ? logFormat : consoleFormat
        })
        // In production, add file transports here:
        // new winston.transports.File({ filename: 'error.log', level: 'error' }),
        // new winston.transports.File({ filename: 'combined.log' })
    ],
    // Don't exit on handled exceptions
    exitOnError: false
});

// Request logging helper
function logRequest(req, res, responseTime) {
    const logData = {
        method: req.method,
        path: req.path,
        status: res.statusCode,
        duration: `${responseTime}ms`,
        ip: req.ip || req.connection.remoteAddress,
        userAgent: req.get('user-agent')
    };
    
    // Don't log sensitive data
    if (req.path !== '/chat' && req.body) {
        // For admin endpoints, only log that request was made
        logData.endpoint = req.path;
    }
    
    if (res.statusCode >= 400) {
        logger.warn('HTTP Request', logData);
    } else {
        logger.info('HTTP Request', logData);
    }
}

// Error logging helper
function logError(error, context = {}) {
    logger.error('Error occurred', {
        message: error.message,
        stack: error.stack,
        ...context
    });
}

module.exports = { logger, logRequest, logError };


