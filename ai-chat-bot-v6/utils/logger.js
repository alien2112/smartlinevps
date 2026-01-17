// ============================================
// 📝 STRUCTURED LOGGING (Production-Ready)
// ============================================

const winston = require('winston');
const path = require('path');
const fs = require('fs');

// Ensure logs directory exists
const logsDir = path.join(__dirname, '..', 'logs');
if (!fs.existsSync(logsDir)) {
    fs.mkdirSync(logsDir, { recursive: true });
}

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

// Build transports array
const transports = [
    // Console transport (always enabled)
    new winston.transports.Console({
        format: process.env.NODE_ENV === 'production' ? logFormat : consoleFormat
    })
];

// Add file transports in production
if (process.env.NODE_ENV === 'production') {
    // Error log - only errors
    transports.push(
        new winston.transports.File({
            filename: path.join(logsDir, 'error.log'),
            level: 'error',
            format: logFormat,
            maxsize: 10 * 1024 * 1024, // 10MB
            maxFiles: 5,
            tailable: true
        })
    );

    // Combined log - all levels
    transports.push(
        new winston.transports.File({
            filename: path.join(logsDir, 'combined.log'),
            format: logFormat,
            maxsize: 10 * 1024 * 1024, // 10MB
            maxFiles: 5,
            tailable: true
        })
    );

    // Access log - HTTP requests only
    transports.push(
        new winston.transports.File({
            filename: path.join(logsDir, 'access.log'),
            level: 'info',
            format: logFormat,
            maxsize: 10 * 1024 * 1024, // 10MB
            maxFiles: 3,
            tailable: true
        })
    );
}

// Create logger
const logger = winston.createLogger({
    level: process.env.LOG_LEVEL || (process.env.NODE_ENV === 'production' ? 'warn' : 'info'),
    format: logFormat,
    defaultMeta: { service: 'smartline-chatbot' },
    transports,
    // Don't exit on handled exceptions
    exitOnError: false
});

// Request logging helper (sanitizes sensitive data)
function logRequest(req, res, responseTime) {
    const logData = {
        method: req.method,
        path: req.path,
        status: res.statusCode,
        duration: `${responseTime}ms`,
        ip: req.ip || req.connection.remoteAddress,
        userAgent: req.get('user-agent')?.substring(0, 100) // Truncate user agent
    };

    // Never log message content or user data
    if (req.path === '/chat' && req.body?.user_id) {
        logData.hasUserId = true;
        logData.messageLength = req.body.message?.length || 0;
    }

    if (res.statusCode >= 500) {
        logger.error('HTTP Request Error', logData);
    } else if (res.statusCode >= 400) {
        logger.warn('HTTP Request Warning', logData);
    } else if (process.env.NODE_ENV !== 'production' || responseTime > 1000) {
        // In production, only log slow requests (>1s) or errors
        logger.info('HTTP Request', logData);
    }
}

// Error logging helper (sanitizes stack traces in production)
function logError(error, context = {}) {
    const errorData = {
        message: error.message,
        code: error.code,
        ...context
    };

    // Only include stack trace in development or for critical errors
    if (process.env.NODE_ENV !== 'production' || context.critical) {
        errorData.stack = error.stack;
    }

    logger.error('Error occurred', errorData);
}

// Security event logging
function logSecurityEvent(event, details = {}) {
    logger.warn('Security Event', {
        event,
        ...details,
        timestamp: new Date().toISOString()
    });
}

module.exports = { logger, logRequest, logError, logSecurityEvent };


