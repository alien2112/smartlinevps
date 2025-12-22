/**
 * SmartLine Real-time Service Configuration
 * 
 * All configuration values are loaded from environment variables.
 * Default values are provided for development, but should be
 * explicitly set in production via .env file.
 * 
 * HOW TO USE:
 * const config = require('./config/config');
 * console.log(config.driverMatching.searchRadiusKm);
 */

const config = {
    // Server Configuration
    server: {
        nodeEnv: process.env.NODE_ENV || 'development',
        port: parseInt(process.env.PORT) || 3000,
        host: process.env.HOST || '0.0.0.0',
    },

    // Redis Configuration
    redis: {
        enabled: process.env.REDIS_ENABLED === 'true',
        host: process.env.REDIS_HOST || '127.0.0.1',
        port: parseInt(process.env.REDIS_PORT) || 6379,
        password: process.env.REDIS_PASSWORD || undefined,
        db: parseInt(process.env.REDIS_DB) || 0,
        maxRetriesPerRequest: parseInt(process.env.REDIS_MAX_RETRIES) || 3,
    },

    // Laravel API Configuration
    laravel: {
        apiUrl: process.env.LARAVEL_API_URL || 'http://localhost:8000',
        apiKey: process.env.LARAVEL_API_KEY || '',
        timeout: parseInt(process.env.LARAVEL_API_TIMEOUT_MS) || 5000,
        validateWithLaravel: process.env.VALIDATE_WITH_LARAVEL === 'true',
    },

    // JWT Configuration
    jwt: {
        secret: process.env.JWT_SECRET || '',
    },

    // WebSocket Configuration
    websocket: {
        corsOrigin: process.env.WS_CORS_ORIGIN || '*',
        pingTimeout: parseInt(process.env.WS_PING_TIMEOUT) || 60000,
        pingInterval: parseInt(process.env.WS_PING_INTERVAL) || 25000,
        maxHttpBufferSize: parseInt(process.env.WS_MAX_BUFFER_SIZE) || 1e6, // 1 MB
        transports: (process.env.WS_TRANSPORTS || 'websocket,polling').split(','),
    },

    // Location Tracking Configuration
    location: {
        updateThrottleMs: parseInt(process.env.LOCATION_UPDATE_THROTTLE_MS) || 1000,
        maxHistory: parseInt(process.env.MAX_LOCATION_HISTORY) || 100,
        expirySeconds: parseInt(process.env.LOCATION_EXPIRY_SECONDS) || 3600,
        rideContextExpirySeconds: parseInt(process.env.RIDE_CONTEXT_EXPIRY_SECONDS) || 21600, // 6 hours
    },

    // Driver Matching Configuration
    driverMatching: {
        searchRadiusKm: parseInt(process.env.DRIVER_SEARCH_RADIUS_KM) || 10,
        maxDriversToNotify: parseInt(process.env.MAX_DRIVERS_TO_NOTIFY) || 10,
        matchTimeoutSeconds: parseInt(process.env.DRIVER_MATCH_TIMEOUT_SECONDS) || 120,
        rideDispatchRetries: parseInt(process.env.RIDE_DISPATCH_RETRIES) || 3,
        notificationExpirySeconds: parseInt(process.env.RIDE_NOTIFICATION_EXPIRY_SECONDS) || 30,
    },

    // Performance Configuration
    performance: {
        maxConnectionsPerInstance: parseInt(process.env.MAX_CONNECTIONS_PER_INSTANCE) || 10000,
        clusterMode: process.env.CLUSTER_MODE === 'true',
        workerProcesses: parseInt(process.env.WORKER_PROCESSES) || 2,
    },

    // Ride timeout handling
    rideTimeout: {
        checkIntervalMs: parseInt(process.env.RIDE_TIMEOUT_CHECK_INTERVAL_MS) || 5000,
    },

    // Security Configuration
    security: {
        enforceRideSubscriptionAuth: process.env.ENFORCE_RIDE_SUBSCRIPTION_AUTH !== 'false',
        disconnectOfflineGraceMs: parseInt(process.env.DISCONNECT_OFFLINE_GRACE_MS) || 30000,
    },

    // Metrics/Health authentication
    metrics: {
        apiKey: process.env.METRICS_API_KEY || '',
    },

    // Rate Limiting Configuration (per socket, per event)
    rateLimiting: {
        driverOnline: {
            windowMs: parseInt(process.env.RATE_LIMIT_DRIVER_ONLINE_WINDOW_MS) || 60000,
            max: parseInt(process.env.RATE_LIMIT_DRIVER_ONLINE_MAX) || 10,
        },
        driverOffline: {
            windowMs: parseInt(process.env.RATE_LIMIT_DRIVER_OFFLINE_WINDOW_MS) || 60000,
            max: parseInt(process.env.RATE_LIMIT_DRIVER_OFFLINE_MAX) || 10,
        },
        driverAcceptRide: {
            windowMs: parseInt(process.env.RATE_LIMIT_ACCEPT_RIDE_WINDOW_MS) || 10000,
            max: parseInt(process.env.RATE_LIMIT_ACCEPT_RIDE_MAX) || 5,
        },
        customerSubscribeRide: {
            windowMs: parseInt(process.env.RATE_LIMIT_SUBSCRIBE_WINDOW_MS) || 60000,
            max: parseInt(process.env.RATE_LIMIT_SUBSCRIBE_MAX) || 30,
        },
        customerUnsubscribeRide: {
            windowMs: parseInt(process.env.RATE_LIMIT_UNSUBSCRIBE_WINDOW_MS) || 60000,
            max: parseInt(process.env.RATE_LIMIT_UNSUBSCRIBE_MAX) || 60,
        },
        ping: {
            windowMs: parseInt(process.env.RATE_LIMIT_PING_WINDOW_MS) || 10000,
            max: parseInt(process.env.RATE_LIMIT_PING_MAX) || 50,
        },
    },

    // Logging Configuration
    logging: {
        level: process.env.LOG_LEVEL || 'info',
        dir: process.env.LOG_DIR || './logs',
    },
};

// Validate critical configuration
const validateConfig = () => {
    const errors = [];

    if (config.server.nodeEnv === 'production') {
        if (!config.jwt.secret) {
            errors.push('JWT_SECRET is required in production');
        }
        if (!config.laravel.apiKey) {
            errors.push('LARAVEL_API_KEY is required in production');
        }
        if (!config.redis.enabled) {
            errors.push('REDIS_ENABLED should be true in production for clustering support');
        }
    }

    if (errors.length > 0) {
        console.error('Configuration validation errors:');
        errors.forEach(err => console.error(`  - ${err}`));
        if (config.server.nodeEnv === 'production') {
            process.exit(1);
        }
    }
};

validateConfig();

module.exports = config;
