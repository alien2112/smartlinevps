// ============================================
// 🚗 SMARTLINE AI CHATBOT V3.4.1
// Hybrid Approach: Smart + Fast + Human Oversight
// Quick replies everywhere, minimal typing
// ============================================

const express = require('express');
const cors = require('cors');
const mysql = require('mysql2/promise');
const path = require('path');
const rateLimit = require('express-rate-limit');
const { body, param, validationResult } = require('express-validator');
const morgan = require('morgan');
const compression = require('compression');
const { v4: uuidv4 } = require('uuid');
require('dotenv').config();

// ============================================
// 📦 UTILITY IMPORTS
// ============================================

const { logger, logRequest, logError, logSecurityEvent } = require('./utils/logger');
const { adminAuth, getAuthStats } = require('./utils/auth');
const responseCache = require('./utils/cache');
const { escalationReply, languageGuardReply, deEscalationReply } = require('./utils/escalationMessages');

// Flutter Actions
const { ACTION_TYPES, UI_HINTS, ActionBuilders } = require('./actions');

// Core Modules (V3.2 Enhancements)
const LanguageManager = require('./utils/language');
const StateGuard = require('./utils/stateGuard');

// V3.3 Enhancements
const { isFeatureEnabled, getAllFlagsStatus } = require('./utils/featureFlags');
const { queryTracker, applyDegradation, PERFORMANCE_BUDGET } = require('./utils/degradation');
const IntentClassifier = require('./classifier');
const { verifyCaptainAccess } = require('./utils/captainVerification');
const mlModerationCollector = require('./utils/mlModeration');
const { getCaptainRegistrationResponse, getCaptainRegistrationStatus } = require('./utils/captainRegistrationBot');

// V3.4.1 Enhancements - Hybrid Approach
const { getQuickReplies, getSmartSuggestions } = require('./utils/quickReplies');
const { getResponse, getBookingConfirmation, getCaptainInfo } = require('./utils/smartResponses');
const { IssueReportingService, ISSUE_CATEGORIES, ISSUE_STATES } = require('./utils/issueReporting');
const { getNotifier, initNotifier } = require('./utils/backofficeNotifier');
const { EvidenceHandler } = require('./utils/evidenceHandler');
const { AntiFraudService } = require('./utils/antiFraud');
const { StrikeService } = require('./services/strikeSystem');
const { buildTripSummary, buildVehicleSelection, buildRatingUI } = require('./utils/uiComponents');

// ============================================
// 📊 APPLICATION METRICS
// ============================================

const appMetrics = {
    requestsTotal: 0,
    requestsSuccess: 0,
    requestsFailed: 0,
    avgResponseTime: 0,
    peakResponseTime: 0,
    llmCalls: 0,
    llmErrors: 0,
    llmAvgLatency: 0,
    dbQueries: 0,
    dbErrors: 0,
    cacheHits: 0,
    cacheMisses: 0,
    startTime: Date.now(),
    lastReset: Date.now()
};

function updateMetrics(responseTime, success) {
    appMetrics.requestsTotal++;
    if (success) {
        appMetrics.requestsSuccess++;
    } else {
        appMetrics.requestsFailed++;
    }

    // Running average
    const total = appMetrics.requestsTotal;
    appMetrics.avgResponseTime = ((appMetrics.avgResponseTime * (total - 1)) + responseTime) / total;
    appMetrics.peakResponseTime = Math.max(appMetrics.peakResponseTime, responseTime);
}

function updateLLMMetrics(latency, success) {
    appMetrics.llmCalls++;
    if (!success) {
        appMetrics.llmErrors++;
    }
    const total = appMetrics.llmCalls;
    appMetrics.llmAvgLatency = ((appMetrics.llmAvgLatency * (total - 1)) + latency) / total;
}

// ============================================
// 🔧 EXPRESS APP SETUP
// ============================================

const app = express();

// ============================================
// 🛡️ SECURITY MIDDLEWARE
// ============================================

// Trust proxy for accurate IP detection behind reverse proxy
if (process.env.NODE_ENV === 'production') {
    app.set('trust proxy', ['loopback', 'linklocal', 'uniquelocal']);
}

// Compression middleware
app.use(compression({
    filter: (req, res) => {
        if (req.headers['x-no-compression']) return false;
        return compression.filter(req, res);
    },
    level: 6
}));

// Security headers (production-ready)
app.use((req, res, next) => {
    // Prevent clickjacking
    res.setHeader('X-Frame-Options', 'DENY');
    // Prevent MIME sniffing
    res.setHeader('X-Content-Type-Options', 'nosniff');
    // XSS protection
    res.setHeader('X-XSS-Protection', '1; mode=block');
    // Referrer policy
    res.setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    // Content Security Policy (relaxed for API)
    res.setHeader('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'");
    // Remove X-Powered-By header
    res.removeHeader('X-Powered-By');
    // HSTS for production
    if (process.env.NODE_ENV === 'production') {
        res.setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
    next();
});

// Request ID middleware
app.use((req, res, next) => {
    req.requestId = `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    res.setHeader('X-Request-ID', req.requestId);
    next();
});

// Request body size limit
app.use(express.json({
    limit: '100kb',
    verify: (req, res, buf) => {
        req.rawBody = buf;
    }
}));
app.use(express.urlencoded({ extended: true, limit: '100kb' }));

// Enhanced Input sanitization middleware (Security hardened)
app.use((req, res, next) => {
    if (req.body) {
        for (const [key, value] of Object.entries(req.body)) {
            if (typeof value === 'string') {
                // Comprehensive sanitization
                req.body[key] = value
                    // Remove script tags
                    .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
                    // Remove HTML tags
                    .replace(/[<>]/g, char => char === '<' ? '&lt;' : '&gt;')
                    // Remove SQL injection attempts
                    .replace(/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|UNION|ALTER|CREATE|TRUNCATE)\b)/gi, '')
                    // Remove common injection patterns
                    .replace(/(['";])\s*(OR|AND)\s*\1/gi, '')
                    // Remove null bytes
                    .replace(/\x00/g, '')
                    // Remove potential command injection
                    .replace(/[`$]/g, '')
                    // Limit excessive whitespace
                    .replace(/\s{10,}/g, ' ')
                    // Remove control characters (except newlines)
                    .replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '');
            }
        }
    }
    next();
});

// CORS configuration (restrict in production)
const corsOptions = {
    origin: process.env.NODE_ENV === 'production'
        ? (process.env.ALLOWED_ORIGINS?.split(',') || ['https://smartline-it.com'])
        : '*',
    methods: ['GET', 'POST'],
    allowedHeaders: ['Content-Type', 'X-API-Key', 'Authorization', 'zoneId', 'X-Request-ID'],
    exposedHeaders: ['X-Request-ID'],
    maxAge: 86400, // 24 hours
    credentials: true
};
app.use(cors(corsOptions));

// Static files with caching headers
app.use(express.static(path.join(__dirname, 'public'), {
    maxAge: process.env.NODE_ENV === 'production' ? '1d' : 0,
    etag: true
}));

// Request timeout middleware
const REQUEST_TIMEOUT = parseInt(process.env.REQUEST_TIMEOUT) || 30000;
app.use((req, res, next) => {
    req.setTimeout(REQUEST_TIMEOUT, () => {
        logError(new Error('Request timeout'), {
            path: req.path,
            method: req.method,
            requestId: req.requestId
        });
        if (!res.headersSent) {
            res.status(408).json({
                message: 'Request timeout. Please try again.',
                message_ar: 'انتهت مهلة الطلب. حاول مرة أخرى.',
                action: ACTION_TYPES.NONE,
                error: 'REQUEST_TIMEOUT'
            });
        }
    });
    next();
});

// HTTP logging
morgan.token('request-id', (req) => req.requestId || '-');
morgan.token('user-id', (req) => req.body?.user_id || '-');
const morganFormat = process.env.NODE_ENV === 'production'
    ? ':request-id :method :url :status :response-time ms - :user-id'
    : ':method :url :status :response-time ms';
app.use(morgan(morganFormat, {
    stream: { write: (message) => logger.info(message.trim()) },
    skip: (req) => req.path === '/health' // Skip health checks in logs
}));

// Response time tracking
app.use((req, res, next) => {
    const start = Date.now();
    res.on('finish', () => {
        const duration = Date.now() - start;
        logRequest(req, res, duration);
    });
    next();
});

// ============================================
// 🛡️ RATE LIMITING
// ============================================

const rateLimitConfig = {
    windowMs: parseInt(process.env.RATE_LIMIT_WINDOW_MS) || 60000,
    max: parseInt(process.env.RATE_LIMIT_MAX) || (process.env.NODE_ENV === 'production' ? 10 : 50)
};

// Main chat rate limiter
const chatRateLimiter = rateLimit({
    windowMs: rateLimitConfig.windowMs,
    max: rateLimitConfig.max,
    message: (req) => {
        const lang = detectLanguageSimple(req.body?.message || '');
        logSecurityEvent('rate_limit_exceeded', {
            ip: req.ip,
            userId: req.body?.user_id,
            requestId: req.requestId
        });
        return {
            message: lang === 'ar'
                ? '⏳ طلبات كتير. استنى دقيقة وحاول تاني.'
                : '⏳ Too many requests. Please wait a minute.',
            action: ACTION_TYPES.NONE,
            error: 'RATE_LIMIT_EXCEEDED',
            retryAfter: Math.ceil(rateLimitConfig.windowMs / 1000)
        };
    },
    keyGenerator: (req) => {
        // Use user_id if available, otherwise use IP address
        const userId = req.body?.user_id;
        if (userId) return `user:${userId}`;
        // Normalize IP address for consistent rate limiting
        return `ip:${req.ip?.replace(/^::ffff:/, '') || 'unknown'}`;
    },
    skip: (req) => req.path.startsWith('/admin') || req.path === '/health',
    standardHeaders: true,
    legacyHeaders: false,
    validate: { xForwardedForHeader: false }, // Disable IPv6 validation warning
    handler: (req, res, next, options) => {
        res.status(429).json(options.message(req));
    }
});

// Burst protection - very short window
const burstLimiter = rateLimit({
    windowMs: 1000, // 1 second
    max: 5, // Max 5 requests per second per user
    keyGenerator: (req) => {
        const userId = req.body?.user_id;
        if (userId) return `user:${userId}`;
        return `ip:${req.ip?.replace(/^::ffff:/, '') || 'unknown'}`;
    },
    validate: { xForwardedForHeader: false }, // Disable IPv6 validation warning
    handler: (req, res) => {
        res.status(429).json({
            message: 'Please slow down',
            message_ar: 'من فضلك استنى شوية',
            action: ACTION_TYPES.NONE,
            error: 'BURST_LIMIT'
        });
    }
});

// Admin rate limiter
const adminRateLimiter = rateLimit({
    windowMs: 60000,
    max: 30,
    message: { success: false, error: 'Too many admin requests' }
});

// ============================================
// 🗄️ DATABASE (Resilient Connection Pool)
// ============================================

const DB_CONFIG = {
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'merged2',
    waitForConnections: true,
    connectionLimit: parseInt(process.env.DB_POOL_SIZE) || 20,
    queueLimit: 50,
    enableKeepAlive: true,
    keepAliveInitialDelay: 10000,
    connectTimeout: 10000,
    acquireTimeout: 10000,
    timeout: 60000,
    charset: 'utf8mb4'
};

let pool = null;
let dbRetryCount = 0;
let dbConnected = false;
const MAX_DB_RETRIES = 5;

/**
 * Execute database query with error handling
 */
async function dbQuery(sql, params = []) {
    if (!pool || !dbConnected) {
        throw new Error('Database not connected');
    }
    appMetrics.dbQueries++;
    try {
        const [rows] = await pool.execute(sql, params);
        return rows;
    } catch (error) {
        appMetrics.dbErrors++;
        throw error;
    }
}

/**
 * Execute database command (INSERT, UPDATE, DELETE)
 */
async function dbExecute(sql, params = []) {
    if (!pool || !dbConnected) {
        throw new Error('Database not connected');
    }
    appMetrics.dbQueries++;
    try {
        const [result] = await pool.execute(sql, params);
        return result;
    } catch (error) {
        appMetrics.dbErrors++;
        throw error;
    }
}

/**
 * Initialize database connection pool
 */
async function initDatabase() {
    try {
        pool = mysql.createPool(DB_CONFIG);

        // Test connection
        const connection = await pool.getConnection();
        await connection.execute('SELECT 1');
        connection.release();

        pool.on('error', (err) => {
            logger.error('Database pool error', { error: err.message, code: err.code });
            if (err.code === 'PROTOCOL_CONNECTION_LOST' || err.code === 'ECONNRESET') {
                dbConnected = false;
                reconnectDatabase();
            }
        });

        // Create tables
        await createTables();

        dbRetryCount = 0;
        dbConnected = true;
        logger.info('✅ Database connected & initialized');
        
        // Initialize V3.4.1 Services
        initV341Services();
    } catch (err) {
        dbConnected = false;
        logger.error('❌ Database initialization failed', { error: err.message });

        if (dbRetryCount < MAX_DB_RETRIES) {
            dbRetryCount++;
            const delay = Math.min(Math.pow(2, dbRetryCount) * 1000, 30000);
            logger.info(`Retrying database connection in ${delay}ms (attempt ${dbRetryCount})`);
            setTimeout(initDatabase, delay);
        } else {
            logger.error('Max database retries reached. Running in degraded mode.');
        }
    }
}

// ============================================
// 🚀 V3.4.1 SERVICE INSTANCES
// ============================================

let backofficeNotifier = null;
let issueReportingService = null;
let evidenceHandler = null;
let antiFraudService = null;
let strikeService = null;

/**
 * Initialize V3.4.1 Services
 */
function initV341Services() {
    try {
        // Initialize back-office notifier
        backofficeNotifier = initNotifier({
            webhookUrl: process.env.BACKOFFICE_WEBHOOK_URL,
            enabled: true,
            logOnly: !process.env.BACKOFFICE_WEBHOOK_URL
        });
        
        // Initialize issue reporting service
        issueReportingService = new IssueReportingService(dbQuery, dbExecute, backofficeNotifier);
        
        // Initialize evidence handler
        evidenceHandler = new EvidenceHandler(dbQuery, dbExecute, backofficeNotifier);
        
        // Initialize anti-fraud service (warnings only, no blocking)
        antiFraudService = new AntiFraudService(dbQuery, dbExecute, backofficeNotifier);
        
        // Initialize strike service (advisory only, human decides)
        strikeService = new StrikeService(dbQuery, dbExecute, backofficeNotifier);
        
        logger.info('✅ V3.4.1 Services initialized');
    } catch (error) {
        logger.error('❌ V3.4.1 Services initialization failed:', error.message);
    }
}

/**
 * Create required database tables
 */
async function createTables() {
    // Chat history table
    await pool.execute(`
        CREATE TABLE IF NOT EXISTS ai_chat_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(50) NOT NULL,
            role VARCHAR(20) NOT NULL,
            content TEXT NOT NULL,
            action_type VARCHAR(50) NULL,
            action_data JSON NULL,
            language VARCHAR(10) NULL,
            intent VARCHAR(50) NULL,
            confidence DECIMAL(3,2) NULL,
            response_time_ms INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_created (user_id, created_at DESC),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `);

    // Conversation state table with version support
    await pool.execute(`
        CREATE TABLE IF NOT EXISTS ai_conversation_state (
            user_id VARCHAR(50) PRIMARY KEY,
            current_state VARCHAR(50) NOT NULL DEFAULT 'START',
            state_version INT NOT NULL DEFAULT ${StateGuard.getCurrentVersion()},
            flow_data JSON NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_state (current_state),
            INDEX idx_updated (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `);

    // User preferences table (enhanced)
    await pool.execute(`
        CREATE TABLE IF NOT EXISTS ai_user_preferences (
            user_id VARCHAR(50) PRIMARY KEY,
            preferred_language VARCHAR(10) NULL,
            user_type VARCHAR(20) NULL,
            favorite_locations JSON NULL,
            arabizi_preference VARCHAR(10) NULL,
            preferred_vehicle_category_id INT NULL,
            frequent_destinations JSON NULL,
            booking_patterns JSON NULL,
            personalization_score DECIMAL(3,2) DEFAULT 0.5,
            language_lock_until DATETIME NULL,
            language_switch_count INT DEFAULT 0,
            last_language_switch DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `);

    logger.info('✅ Database tables verified/created');
}

/**
 * Reconnect to database
 */
async function reconnectDatabase() {
    logger.info('Attempting database reconnection...');
    if (pool) {
        try { await pool.end(); } catch (e) { }
    }
    dbRetryCount = 0;
    await initDatabase();
}

// ============================================
// 🌍 LANGUAGE DETECTION (Inline for reliability)
// ============================================

/**
 * Simple language detection (fallback)
 */
function detectLanguageSimple(message) {
    if (!message || typeof message !== 'string') return 'en';

    const arabicChars = (message.match(/[\u0600-\u06FF]/g) || []).length;
    const totalChars = message.replace(/\s/g, '').length;

    if (totalChars === 0) return 'en';
    return arabicChars / totalChars > 0.3 ? 'ar' : 'en';
}

/**
 * Detect user language with confidence
 */
function detectUserLanguage(message) {
    if (!message || typeof message !== 'string') {
        return { primary: 'unknown', confidence: 0 };
    }

    const text = message.trim();

    // Count character types
    const arabicChars = (text.match(/[\u0600-\u06FF]/g) || []).length;
    const englishChars = (text.match(/[a-zA-Z]/g) || []).length;
    const totalChars = text.length;

    // Arabizi detection patterns
    const arabiziPatterns = [
        /\b(3|7|5|2|8)\w+/i,
        /\b\w+(3|7|5|2|8)\b/i,
        /\b(el|al|wel|wl)\s?\w+/i,
        /\b(ana|enta|enty|howa|heya|ehna|ento|homa)\b/i,
        /\b(keda|kda|ba2a|b2a|3ala|3la|fi|fih)\b/i,
        /\b(mesh|msh|3ayez|3ayz|awza|3wz)\b/i
    ];

    const arabiziScore = arabiziPatterns.reduce((score, pattern) => {
        return score + (pattern.test(text) ? 1 : 0);
    }, 0);

    // Calculate ratios
    const arabicRatio = arabicChars / totalChars;
    const englishRatio = englishChars / totalChars;
    const arabiziRatio = arabiziScore / arabiziPatterns.length;

    // Determine primary language
    if (arabicRatio > 0.5) {
        return { primary: 'ar', confidence: Math.min(0.95, arabicRatio + 0.2) };
    }

    if (arabiziRatio > 0.3 || (arabiziScore >= 2 && englishRatio > 0.3)) {
        return { primary: 'arabizi', confidence: Math.min(0.9, arabiziRatio + 0.3) };
    }

    if (englishRatio > 0.5) {
        return { primary: 'en', confidence: Math.min(0.95, englishRatio + 0.2) };
    }

    // Mixed or unknown
    if (arabicRatio > englishRatio) {
        return { primary: 'mixed', confidence: 0.5, hint: 'ar' };
    }

    return { primary: 'mixed', confidence: 0.5, hint: 'en' };
}

// ============================================
// 🛡️ CONTENT MODERATION
// ============================================

// Profanity patterns
const PROFANITY_PATTERNS = {
    en: [
        /\b(fuck|shit|ass|bitch|damn|crap|dick|cock|pussy|whore|slut)\b/i,
        /\b(f+u+c+k+|s+h+i+t+|a+s+s+)\b/i
    ],
    ar: [
        /\b(كس|طيز|زب|شرموط|عرص|متناك)\b/i,
        /\b(يلعن|ابن الكلب)\b/i
    ],
    arabizi: [
        /\b(kos|teez|zeb|sharmota|3ars|metnayak)\b/i,
        /\b(yl3n|ebn el kalb|5ara)\b/i
    ]
};

// Threat patterns (higher severity)
const THREAT_PATTERNS = [
    /\b(kill|murder|hurt|attack|bomb|gun|weapon)\b/i,
    /\b(a2tlak|amawtak|adrabak|sla7)\b/i,
    /\b(اقتلك|اموتك|اضربك|سلاح)\b/i
];

// Prompt injection detection patterns (ENHANCED SECURITY V2)
const PROMPT_INJECTION_PATTERNS = [
    // English - Instruction override attempts
    /ignore\s*(all\s*)?(previous|above|prior|earlier|original)\s*(instructions?|prompts?|rules?|context)/i,
    /forget\s*(all\s*)?(previous|above|prior|your|everything)\s*(instructions?|rules?|training|context)/i,
    /disregard\s*(all\s*)?(previous|prior|your|above)/i,
    /override\s*(all\s*)?(previous|your|system)/i,
    /bypass\s*(all\s*)?(filters?|restrictions?|rules?|safety)/i,
    /disable\s*(all\s*)?(filters?|restrictions?|safety|moderation)/i,

    // Role play / Identity change
    /you\s*are\s*(now|no longer|actually|really)\s*(a|an|the)?/i,
    /pretend\s*(to be|you are|that you|like you)/i,
    /act\s*as\s*(if|a|an|though|like)/i,
    /roleplay\s*(as|like|being)/i,
    /imagine\s*you\s*(are|were|can)/i,
    /from\s*now\s*on\s*(you|act|be|respond)/i,
    /switch\s*(to|into)\s*(a|another|different)\s*(mode|persona|role)/i,

    // System/Technical probing
    /new\s*instructions?:/i,
    /system\s*(prompt|message|instruction)/i,
    /\bDAN\b/i, // Do Anything Now
    /\bAIM\b/i, // Always Intelligent and Machiavellian
    /jailbreak/i,
    /developer\s*mode/i,
    /maintenance\s*mode/i,
    /debug\s*mode/i,
    /admin\s*(mode|access|override)/i,
    /sudo\s/i,
    /root\s*access/i,
    /reveal\s*(your|the)\s*(prompt|instructions|system|training)/i,
    /show\s*(me|your)\s*(prompt|instructions|rules|system)/i,
    /what\s*(are|is)\s*your\s*(instructions?|rules?|prompt|system)/i,
    /print\s*(your|the)\s*(prompt|instructions|system)/i,
    /output\s*(your|the)\s*(prompt|instructions|previous)/i,

    // Encoding/Evasion attempts
    /base64|encode|decode|hex\s*string/i,
    /\[system\]|\[assistant\]|\[user\]/i,
    /\<\|im_start\|\>|\<\|im_end\|\>/i,
    /\<system\>|\<\/system\>/i,
    /###\s*(system|instruction|human|assistant)/i,

    // Arabic - Instruction override
    /تجاهل\s*(كل|جميع)?\s*(التعليمات|الاوامر|القواعد|السابق)/i,
    /انسى\s*(كل|جميع)?\s*(التعليمات|السابق|القواعد|الاوامر)/i,
    /اهمل\s*(كل|جميع)?\s*(التعليمات|القواعد)/i,
    /تخطى\s*(كل|جميع)?\s*(القيود|الفلاتر|القواعد)/i,

    // Arabic - Role change
    /تصرف\s*(ك|على انك|كأنك|زي)/i,
    /أنت\s*(الآن|دلوقتي|هتكون|هتبقى)/i,
    /تخيل\s*(إنك|انك|نفسك)/i,
    /غير\s*(شخصيتك|دورك|طريقتك)/i,

    // Arabic - System probing
    /اظهر\s*(لي)?\s*(التعليمات|القواعد|البرومبت)/i,
    /ايه\s*(هي)?\s*(تعليماتك|قواعدك)/i,
    /وريني\s*(التعليمات|القواعد|البرومبت)/i,

    // Arabizi variations
    /t8ahl\s*el\s*ta3limat/i,
    /ensa\s*el\s*2bl/i,
    /ignore\s*el\s*rules/i
];

/**
 * Suspicious patterns that warrant extra scrutiny (not immediate block)
 */
const SUSPICIOUS_PATTERNS = [
    /tell\s*me\s*(about|how)\s*(you|your)\s*(work|function|operate)/i,
    /how\s*(do|are)\s*you\s*(programmed|trained|built)/i,
    /what\s*(model|ai|llm)\s*are\s*you/i,
    /are\s*you\s*(chatgpt|gpt|claude|llama|gemini)/i
];

/**
 * Check for prompt injection attempts (ENHANCED)
 * @param {string} message - User message
 * @returns {{isInjection: boolean, severity: string, pattern: string|null}}
 */
function checkPromptInjection(message) {
    if (!message || typeof message !== 'string') {
        return { isInjection: false, severity: 'none', pattern: null };
    }

    // Normalize message for better detection
    const normalizedMessage = message
        .toLowerCase()
        .replace(/[_\-\.]/g, ' ')  // Normalize separators
        .replace(/\s+/g, ' ')      // Normalize whitespace
        .trim();

    // Check critical injection patterns
    for (const pattern of PROMPT_INJECTION_PATTERNS) {
        if (pattern.test(normalizedMessage) || pattern.test(message)) {
            return {
                isInjection: true,
                severity: 'critical',
                pattern: pattern.toString()
            };
        }
    }

    // Check suspicious patterns (log but don't block)
    for (const pattern of SUSPICIOUS_PATTERNS) {
        if (pattern.test(normalizedMessage)) {
            // Log for monitoring but don't block
            console.log('[Security] Suspicious pattern detected:', pattern.toString());
            return {
                isInjection: false,
                severity: 'suspicious',
                pattern: pattern.toString(),
                shouldLog: true
            };
        }
    }

    return { isInjection: false, severity: 'none', pattern: null };
}

const SEVERITY = {
    CLEAN: 'none',
    LOW: 'low',
    MEDIUM: 'medium',
    HIGH: 'high',
    CRITICAL: 'critical'
};

/**
 * Check message for profanity
 */
function checkProfanity(message) {
    if (!message || typeof message !== 'string') {
        return { flagged: false, severity: SEVERITY.CLEAN };
    }

    const text = message.toLowerCase();
    let maxSeverity = SEVERITY.CLEAN;
    let flagged = false;

    // Check threats first (highest severity)
    for (const pattern of THREAT_PATTERNS) {
        if (pattern.test(text)) {
            return { flagged: true, severity: SEVERITY.CRITICAL };
        }
    }

    // Check profanity in all languages
    for (const [lang, patterns] of Object.entries(PROFANITY_PATTERNS)) {
        for (const pattern of patterns) {
            if (pattern.test(text)) {
                flagged = true;
                maxSeverity = SEVERITY.MEDIUM;
            }
        }
    }

    return { flagged, severity: maxSeverity };
}

// ============================================
// 🧠 INTENT CLASSIFICATION
// ============================================

const INTENTS = {
    BOOK_TRIP: {
        patterns: [
            // Arabic patterns (more comprehensive)
            /(?:^|\s)(?:اريد|عايز|محتاج|ابي|أبي)\s*(?:حجز|احجز|أحجز|حجزت|حجز رحلة|رحلة|توصيل|توصيلة|عربية|سيارة)/i,
            /(?:حجز|احجز|أحجز|حجزت)\s*(?:رحلة|رحله|رحلة من|رحله من)/i,
            /(?:رحلة|رحله|توصيل|توصيلة|عربية|سيارة)\s*(?:من|ل|الى|إلى|لل)/i,
            /(?:وصلني|وصلني|خدني|خذني|خذني|take me|pickup)/i,
            // English patterns
            /\b(book|booking|ride|trip|need a car|want a ride|pickup|take me)\b/i,
            // Arabizi patterns
            /\b(ahjez|awsal|wadini|khodni|3ayez ra7la|7agz)\b/i
        ],
        priority: 1
    },
    TRIP_STATUS: {
        patterns: [
            /\b(وين|أين|فين|الكابتن|تتبع|وصل فين)\b/i,
            /\b(status|where|driver|track|eta|how long)\b/i,
            /\b(feen|fen|wa9al|wassal)\b/i
        ],
        priority: 2
    },
    CANCEL_TRIP: {
        patterns: [
            /\b(إلغاء|الغاء|ألغي|الغي|مش عايز)\b/i,
            /\b(cancel|stop|abort|dont want)\b/i,
            /\b(elghy|msh 3ayez|khalas)\b/i
        ],
        priority: 2
    },
    CONTACT_DRIVER: {
        patterns: [
            /\b(اتصل|رقم|تواصل|كلم الكابتن)\b/i,
            /\b(call|contact|phone|message driver)\b/i
        ],
        priority: 3
    },
    PAYMENT: {
        patterns: [
            /\b(سعر|دفع|فلوس|مبلغ|كام)\b/i,
            /\b(price|fare|payment|cost|how much)\b/i,
            /\b(kam|floos|daf3)\b/i
        ],
        priority: 3
    },
    SAFETY: {
        patterns: [
            /\b(خطر|تحرش|حادث|شرطة|طوارئ|النجدة)\b/i,
            /\b(danger|emergency|accident|police|help me|sos)\b/i,
            /\b(taware2|khatar|7adsa)\b/i
        ],
        priority: 0 // Highest priority
    },
    SUPPORT: {
        patterns: [
            /\b(موظف|بشري|إنسان|كلمني حد|مساعدة)\b/i,
            /\b(agent|human|support|help|speak to someone)\b/i,
            /\b(mosa3da|agent)\b/i
        ],
        priority: 2
    },
    GREETING: {
        patterns: [
            /^(مرحبا|هلا|السلام|صباح|مساء|اهلا)/i,
            /^(hi|hello|hey|good morning|good evening)/i,
            /^(ahlan|salam)/i
        ],
        priority: 10
    },
    FAREWELL: {
        patterns: [
            /\b(مع السلامة|باي|شكرا)\b/i,
            /\b(bye|goodbye|thanks|thank you)\b/i,
            /\b(shokran|ma3 elsalama)\b/i
        ],
        priority: 10
    },

    // NEW INTENTS
    PROMO_CODE: {
        patterns: [
            /\b(كود|برومو|خصم|عرض|كوبون|promo|code|discount|coupon|offer)\b/i,
            /\b(kod|5asm|3ard)\b/i
        ],
        priority: 4
    },
    SCHEDULE_RIDE: {
        patterns: [
            /\b(حجز مسبق|جدول|لاحقا|بكرة|غدا|الساعة|schedule|later|tomorrow|advance|book for)\b/i,
            /\b(ba3deen|bokra|ghadan)\b/i,
            /(?:حجز|book)\s*(?:رحلة|ride)?\s*(?:ل|for|at)?\s*(?:الساعة|بكرة|غدا|\d{1,2})/i
        ],
        priority: 3
    },
    CHANGE_DESTINATION: {
        patterns: [
            /\b(غير|تغيير|عدل|بدل)\s*(?:الوجهة|المكان|destination)/i,
            /\b(change|update|modify)\s*(?:destination|drop|dropoff)/i,
            /\b(عايز اروح|خدني)\s*(?:مكان تاني|somewhere else)/i
        ],
        priority: 3
    },
    ADD_STOP: {
        patterns: [
            /\b(محطة|وقفة|stop|إضاف)\s*(?:تانية|اخرى|another|extra)/i,
            /\b(add|اضف|ضيف)\s*(?:stop|محطة|وقفة)/i,
            /\b(عايز|محتاج)\s*(?:اعدي|امر)\s*(?:على|ب)/i
        ],
        priority: 3
    },
    TRIP_HISTORY: {
        patterns: [
            /\b(رحلاتي|السابقة|التاريخ|history|previous|past)\s*(?:trips?|رحلات)?/i,
            /\b(my trips|رحلاتي السابقة|آخر رحلة)\b/i
        ],
        priority: 4
    },
    RECEIPT: {
        patterns: [
            /\b(إيصال|فاتورة|receipt|invoice|bill)\b/i,
            /\b(ابعتلي|send me|اريد)\s*(?:الفاتورة|الإيصال|receipt)/i
        ],
        priority: 4
    },
    COMPLAINT: {
        patterns: [
            /\b(شكوى|مشكلة|complaint|problem|issue)\b/i,
            /\b(عايز اشتكي|want to complain|report)\b/i,
            /\b(الكابتن|السواق|driver)\s*(?:وحش|سيء|bad|rude)/i
        ],
        priority: 2
    },
    RATE_DRIVER: {
        patterns: [
            /\b(تقييم|قيم|rate|rating|review)\b/i,
            /\b(نجوم|stars|feedback)\b/i
        ],
        priority: 5
    },
    WALLET: {
        patterns: [
            /\b(محفظة|رصيد|wallet|balance|credit)\b/i,
            /\b(فلوسي|my money|شحن)\b/i
        ],
        priority: 4
    },
    FAVORITE_LOCATIONS: {
        patterns: [
            /\b(المفضلة|مفضل|favorites?|saved|حفظ)\s*(?:locations?|أماكن|مكان)?/i,
            /\b(البيت|الشغل|home|work|office)\b/i
        ],
        priority: 5
    },
    ETA: {
        patterns: [
            /\b(هيوصل|متى|كام دقيقة|how long|when|eta|minutes)\b/i,
            /\b(فاضل كام|باقي|remaining|left)\b/i
        ],
        priority: 3
    }
};

/**
 * OUT-OF-CONTEXT DETECTION PATTERNS (ENHANCED V2)
 * Comprehensive patterns to detect questions NOT related to ride-hailing
 */
const OUT_OF_CONTEXT_PATTERNS = {
    // Company/Business questions
    company_info: [
        /\b(مين|من)\s*(مالك|صاحب|مدير|رئيس|owner|مؤسس)/i,
        /\b(who)\s*(owns?|is the owner|founded|is the ceo|runs|started)\b/i,
        /\b(الشركة|الشركه)\s*(دي|دى|ملك|بتاعت|تبع|اسسها)/i,
        /\b(company|business|corporation)\s*(owner|ceo|founder|shareholders?|investors?)\b/i,
        /\b(اوراسكوم|orascom|سويدي|sawiris|نجيب)\b/i,
        /\b(stock|اسهم|market|بورصة|investment|استثمار)\b/i,
        /\b(revenue|ارباح|profit|ايرادات|valuation)\b/i,
        /\b(headquarters|مقر|office location|عنوان الشركة)\b/i
    ],

    // General knowledge / Education
    general_knowledge: [
        /\b(ما هي|ما هو|what is|who is|when did|where is|how does)\b(?!.*(رحلة|trip|driver|سواق|كابتن|pickup|توصيل|fare|سعر))/i,
        /\b(explain|شرح|فسر|اشرح|define|عرف)\b(?!.*(رحلة|trip|booking|حجز|cancel|الغاء))/i,
        /\b(history|تاريخ|politics|سياسة|religion|دين|philosophy|فلسفة)\b/i,
        /\b(weather|طقس|news|اخبار|sports|رياضة|match|مباراة)\b/i,
        /\b(science|علم|physics|فيزياء|chemistry|كيمياء|biology|احياء)\b/i,
        /\b(geography|جغرافيا|capital of|عاصمة|population|سكان)\b/i,
        /\b(president|رئيس الجمهورية|prime minister|رئيس الوزراء|king|ملك)\b/i,
        /\b(war|حرب|election|انتخابات|government|حكومة)\b/i,
        /\b(movie|فيلم|song|اغنية|book|كتاب|actor|ممثل|singer|مطرب)\b/i,
        /\b(football|كرة|goal|هدف|player|لاعب|team|فريق)\b(?!.*(driver|captain|كابتن))/i
    ],

    // Personal advice / Life questions
    personal: [
        /\b(relationship|علاقة|love|حب|marriage|زواج|divorce|طلاق)\b/i,
        /\b(health|صحة|medical|طبي|doctor|دكتور|medicine|دواء|hospital|مستشفى)\b/i,
        /\b(advice|نصيحة|should i|هل انا|life|حياة|career|مستقبل)\b(?!.*(رحلة|trip|ride|book))/i,
        /\b(depression|اكتئاب|anxiety|قلق|stress|ضغط|mental|نفسي)\b/i,
        /\b(diet|رجيم|weight|وزن|gym|جيم|exercise|رياضة)\b/i,
        /\b(sleep|نوم|dream|حلم|nightmare|كابوس)\b/i,
        /\b(money problem|مشكلة فلوس|debt|دين|loan|قرض)\b(?!.*(fare|payment|دفع))/i
    ],

    // Technical/Hacking/Security threats
    technical: [
        /\b(sql|database|server|api|code|برمج|hack|اختراق|exploit)\b/i,
        /\b(password|كلمة سر|admin|token|secret|key|مفتاح)\b/i,
        /\b(system prompt|ignore previous|forget instructions|override)\b/i,
        /\b(تجاهل|انسى|اهمل)\s*(التعليمات|الاوامر|السابق|القواعد)/i,
        /\b(vulnerability|ثغرة|injection|xss|csrf)\b/i,
        /\b(root|sudo|shell|terminal|command line)\b/i,
        /\b(encrypt|decrypt|تشفير|فك التشفير)\b/i,
        /\b(backend|frontend|framework|library)\b/i
    ],

    // Math/Programming/Homework
    math_programming: [
        /\b(\d+\s*[\+\-\*\/\^]\s*\d+)\b/,
        /\b(calculate|حساب|compute|solve|حل)\b(?!.*(fare|سعر|cost|تكلفة))/i,
        /\b(program|برنامج|algorithm|خوارزمية)\b/i,
        /\b(write.*code|اكتب.*كود|python|javascript|java|c\+\+|html|css)\b/i,
        /\b(equation|معادلة|formula|صيغة|integral|تكامل|derivative|مشتقة)\b/i,
        /\b(homework|واجب|assignment|تكليف|exam|امتحان|test|اختبار)\b/i,
        /\b(essay|مقال|thesis|رسالة|research|بحث)\b/i
    ],

    // Food/Shopping/Entertainment (not ride related)
    lifestyle: [
        /\b(recipe|وصفة|cook|طبخ|restaurant|مطعم|food|اكل)\b(?!.*(delivery|توصيل|driver))/i,
        /\b(shop|تسوق|buy|اشتري|price of|سعر)\b(?!.*(ride|رحلة|trip|fare))/i,
        /\b(game|لعبة|play|العب|xbox|playstation|mobile game)\b/i,
        /\b(joke|نكتة|funny|مضحك|humor|هزار)\b/i,
        /\b(story|قصة|tell me about|احكيلي عن)\b(?!.*(trip|رحلة|ride|driver))/i
    ],

    // Religious/Spiritual
    religious: [
        /\b(pray|صلاة|mosque|مسجد|church|كنيسة|god|الله|allah)\b/i,
        /\b(quran|قرآن|bible|انجيل|hadith|حديث|verse|آية)\b/i,
        /\b(halal|حلال|haram|حرام|sin|ذنب|heaven|جنة|hell|نار)\b/i,
        /\b(ramadan|رمضان|eid|عيد|hajj|حج|fasting|صيام)\b/i
    ],

    // Conspiracy/Controversial
    controversial: [
        /\b(conspiracy|مؤامرة|illuminati|ماسونية|flat earth|الارض مسطحة)\b/i,
        /\b(aliens|فضائيين|ufo|secret society|جماعة سرية)\b/i,
        /\b(covid fake|كورونا كذب|vaccine danger|لقاح خطر)\b/i
    ],

    // Inappropriate/Adult content
    inappropriate: [
        /\b(dating|تعارف|girlfriend|صاحبة|boyfriend|صاحب)\b(?!.*(driver|captain))/i,
        /\b(flirt|غزل|sexy|attractive|جميلة)\b/i
    ]
};

/**
 * Keywords that indicate the message IS about ride-hailing (whitelist)
 * If these are present, DON'T mark as out-of-context
 */
const RIDE_CONTEXT_KEYWORDS = [
    /\b(رحلة|رحلتي|trip|ride|book|حجز|احجز|booking)\b/i,
    /\b(driver|سائق|سواق|كابتن|captain)\b/i,
    /\b(pickup|توصيل|وصلني|خدني|take me)\b/i,
    /\b(destination|الوجهة|المكان|location|موقع)\b/i,
    /\b(cancel|الغاء|الغي|stop trip)\b/i,
    /\b(fare|سعر الرحلة|cost|تكلفة|payment|دفع)\b/i,
    /\b(track|تتبع|where is|فين|eta|وصل فين)\b/i,
    /\b(rating|تقييم|review|rate driver)\b/i,
    /\b(support|دعم|help|مساعدة|complaint|شكوى)\b/i,
    /\b(promo|كود|discount|خصم|offer|عرض)\b/i,
    /\b(wallet|محفظة|balance|رصيد|credit)\b/i,
    /\b(vehicle|عربية|car|سيارة|economy|vip|comfort)\b/i,
    /\b(smartline|سمارت لاين)\b/i
];

/**
 * Check if message contains ride-hailing context keywords
 * @param {string} message - User message
 * @returns {boolean}
 */
function hasRideContext(message) {
    if (!message) return false;
    return RIDE_CONTEXT_KEYWORDS.some(pattern => pattern.test(message));
}

/**
 * Check if message is out of context (not related to ride-hailing)
 * Enhanced with whitelist checking and confidence scoring
 * @param {string} message - User message
 * @param {Object} conversationState - Current conversation state (optional)
 * @returns {{outOfContext: boolean, category: string|null, confidence: number}}
 */
function checkOutOfContext(message, conversationState = null) {
    if (!message || typeof message !== 'string') {
        return { outOfContext: false, category: null, confidence: 0 };
    }

    const normalizedMessage = message.toLowerCase().trim();

    // Skip very short messages (likely quick replies or confirmations)
    if (normalizedMessage.length < 5) {
        return { outOfContext: false, category: null, confidence: 0 };
    }

    // WHITELIST CHECK: If message contains ride-hailing keywords, it's in-context
    if (hasRideContext(normalizedMessage)) {
        return { outOfContext: false, category: null, confidence: 0, reason: 'has_ride_context' };
    }

    // STATE CONTEXT CHECK: If user is in a booking flow, be more lenient
    if (conversationState) {
        const activeStates = ['AWAITING_PICKUP', 'AWAITING_DESTINATION', 'AWAITING_VEHICLE',
            'AWAITING_CONFIRMATION', 'TRIP_ACTIVE', 'AWAITING_CANCEL_CONFIRM'];
        if (activeStates.includes(conversationState.state)) {
            // In active flow - only block obvious off-topic
            const obviousOffTopic = [
                OUT_OF_CONTEXT_PATTERNS.technical,
                OUT_OF_CONTEXT_PATTERNS.religious,
                OUT_OF_CONTEXT_PATTERNS.controversial,
                OUT_OF_CONTEXT_PATTERNS.math_programming
            ].flat();

            for (const pattern of obviousOffTopic) {
                if (pattern.test(normalizedMessage)) {
                    return {
                        outOfContext: true,
                        category: 'off_topic_during_flow',
                        confidence: 0.85,
                        matchedPattern: pattern.toString()
                    };
                }
            }
            // Otherwise, assume it's related to the current flow
            return { outOfContext: false, category: null, confidence: 0, reason: 'in_active_flow' };
        }
    }

    // BLACKLIST CHECK: Check all out-of-context patterns
    let matchedCategories = [];

    for (const [category, patterns] of Object.entries(OUT_OF_CONTEXT_PATTERNS)) {
        for (const pattern of patterns) {
            if (pattern.test(normalizedMessage)) {
                matchedCategories.push({
                    category,
                    pattern: pattern.toString(),
                    // Higher confidence for certain categories
                    confidence: ['technical', 'controversial', 'religious', 'inappropriate'].includes(category)
                        ? 0.95
                        : 0.85
                });
            }
        }
    }

    if (matchedCategories.length > 0) {
        // Return the highest confidence match
        const bestMatch = matchedCategories.sort((a, b) => b.confidence - a.confidence)[0];
        return {
            outOfContext: true,
            category: bestMatch.category,
            confidence: bestMatch.confidence,
            matchedPattern: bestMatch.pattern,
            allMatches: matchedCategories.length
        };
    }

    return { outOfContext: false, category: null, confidence: 0 };
}

/**
 * Get out-of-context response (ENHANCED with variety and category-specific responses)
 * @param {string} lang - Language code
 * @param {string} category - Out of context category
 * @returns {string}
 */
function getOutOfContextResponse(lang, category = 'default') {
    const responses = {
        ar: {
            default: '🚗 أنا مساعد سمارت لاين للتوصيل فقط.\n\nأقدر أساعدك في:\n• حجز رحلة 🚕\n• تتبع رحلتك 📍\n• إلغاء رحلة ❌\n• التواصل مع الكابتن 📞\n\nإزاي أقدر أساعدك النهارده؟',

            company_info: '🏢 للأسف مش بقدر أجاوب على أسئلة عن الشركة أو الإدارة.\n\nأنا هنا بس عشان أساعدك في رحلتك!\n\n🚗 عايز تحجز رحلة؟',

            general_knowledge: '📚 أنا مش موسوعة للأسف! أنا متخصص بس في خدمة التوصيل.\n\n🚗 محتاج رحلة؟ أنا جاهز أساعدك!',

            personal: '💭 أقدر أفهم إنك محتاج نصيحة، بس أنا متخصص في التوصيل بس.\n\n🚗 لو محتاج رحلة، أنا هنا!',

            technical: '⚠️ ده سؤال تقني مش في تخصصي.\n\nأنا بساعد في حجز وتتبع الرحلات بس.\n\n🚗 تحب تحجز رحلة؟',

            math_programming: '🔢 للأسف مش بقدر أحل مسائل أو أكتب أكواد.\n\n🚗 بس أقدر أوصلك لأي مكان! عايز رحلة؟',

            lifestyle: '🍽️ أنا مش خبير في الموضوع ده!\n\nتخصصي هو التوصيل والرحلات.\n\n🚗 محتاج توصيلة؟',

            religious: '🤲 ده موضوع مهم بس مش تخصصي.\n\nأنا هنا بس عشان رحلتك!\n\n🚗 عايز تروح فين؟',

            controversial: '⚠️ مش هقدر أتكلم في الموضوع ده.\n\n🚗 خلينا في التوصيل! عايز رحلة؟',

            inappropriate: '⚠️ ده مش نوع المحادثات اللي أقدر أساعد فيها.\n\n🚗 لو محتاج رحلة، أنا جاهز!',

            off_topic_during_flow: '🤔 خلينا نكمل الرحلة الأول!\n\nفين عايز تروح؟'
        },
        en: {
            default: '🚗 I\'m SmartLine\'s ride assistant only.\n\nI can help with:\n• Booking a ride 🚕\n• Tracking your trip 📍\n• Cancelling a trip ❌\n• Contacting your driver 📞\n\nHow can I help you today?',

            company_info: '🏢 I\'m not able to answer questions about the company or management.\n\nI\'m here just to help with your ride!\n\n🚗 Would you like to book a trip?',

            general_knowledge: '📚 I\'m not an encyclopedia, unfortunately! I specialize only in ride services.\n\n🚗 Need a ride? I\'m ready to help!',

            personal: '💭 I understand you might need advice, but I specialize only in transportation.\n\n🚗 If you need a ride, I\'m here!',

            technical: '⚠️ That\'s a technical question outside my expertise.\n\nI only help with booking and tracking rides.\n\n🚗 Want to book a trip?',

            math_programming: '🔢 Sorry, I can\'t solve problems or write code.\n\n🚗 But I can take you anywhere! Need a ride?',

            lifestyle: '🍽️ I\'m not an expert on that topic!\n\nMy specialty is rides and transportation.\n\n🚗 Need a lift?',

            religious: '🤲 That\'s an important topic but not my specialty.\n\nI\'m here just for your ride!\n\n🚗 Where would you like to go?',

            controversial: '⚠️ I\'m not able to discuss that topic.\n\n🚗 Let\'s stick to rides! Need one?',

            inappropriate: '⚠️ That\'s not the kind of conversation I can help with.\n\n🚗 If you need a ride, I\'m ready!',

            off_topic_during_flow: '🤔 Let\'s finish booking your ride first!\n\nWhere would you like to go?'
        }
    };

    const langResponses = responses[lang] || responses.en;
    return langResponses[category] || langResponses.default;
}

/**
 * Classify intent from message (ENHANCED with context awareness)
 * @param {string} message - User message
 * @param {string} userType - 'customer' or 'captain'
 * @param {Object} conversationState - Current conversation state (optional)
 */
function classifyIntent(message, userType = 'customer', conversationState = null) {
    if (!message || typeof message !== 'string') {
        return { intent: 'UNKNOWN', confidence: 0, source: 'none' };
    }

    const normalizedMessage = message.toLowerCase().trim();

    // FIRST: Check for out-of-context questions (with state awareness)
    const outOfContextCheck = checkOutOfContext(message, conversationState);
    if (outOfContextCheck.outOfContext) {
        return {
            intent: 'OUT_OF_CONTEXT',
            confidence: outOfContextCheck.confidence,
            source: 'out_of_context_filter',
            category: outOfContextCheck.category,
            matchedPattern: outOfContextCheck.matchedPattern
        };
    }

    // Sort by priority (lower = higher priority)
    const sortedIntents = Object.entries(INTENTS)
        .sort((a, b) => a[1].priority - b[1].priority);

    for (const [intentName, config] of sortedIntents) {
        for (const pattern of config.patterns) {
            if (pattern.test(normalizedMessage)) {
                return {
                    intent: intentName,
                    confidence: 0.9,
                    source: 'regex',
                    matchedPattern: pattern.toString()
                };
            }
        }
    }

    return { intent: 'UNKNOWN', confidence: 0, source: 'none' };
}

// ============================================
// 🧠 USER TYPE DETECTION (Captain/Customer)
// ============================================

const userTypes = new Map();
const MAX_USER_TYPES = 50000;
const USER_TYPE_TTL = 24 * 60 * 60 * 1000; // 24 hours

const USER_TYPE_KEYWORDS = {
    captain: {
        strong: ['driver', 'captain', 'كابتن', 'سائق', 'earnings', 'acceptance rate', 'my vehicle', 'الأرباح', 'معدل القبول', 'كسبت كام'],
        weak: ['trip request', 'passenger', 'pickup customer', 'راكب', 'طلب رحلة']
    },
    customer: {
        strong: ['rider', 'customer', 'راكب', 'عميل', 'book a ride', 'driver is late', 'أحجز رحلة', 'السواق متأخر', 'وصلني'],
        weak: ['my ride', 'trip', 'fare', 'رحلتي', 'السعر']
    }
};

function detectUserType(message, currentType = null) {
    if (currentType) return currentType;
    const lowerMsg = message.toLowerCase();

    for (const keyword of USER_TYPE_KEYWORDS.captain.strong) {
        if (lowerMsg.includes(keyword.toLowerCase())) return 'captain';
    }
    for (const keyword of USER_TYPE_KEYWORDS.customer.strong) {
        if (lowerMsg.includes(keyword.toLowerCase())) return 'customer';
    }
    return null;
}

function getUserType(userId) {
    const data = userTypes.get(userId);
    if (!data) return null;

    // Check TTL
    if (Date.now() - data.timestamp > USER_TYPE_TTL) {
        userTypes.delete(userId);
        return null;
    }
    return data.type;
}

function setUserType(userId, type) {
    if (userTypes.size >= MAX_USER_TYPES) {
        // Remove oldest entry
        const oldest = userTypes.keys().next().value;
        userTypes.delete(oldest);
    }
    userTypes.set(userId, { type, timestamp: Date.now() });
}

// ============================================
// 🔄 MEMORY MANAGEMENT
// ============================================

const lastMessages = new Map();
const MAX_LAST_MESSAGES = 50000;
const REPEATED_MSG_WINDOW = 30000; // 30 seconds
const MEMORY_CLEANUP_INTERVAL = 10 * 60 * 1000; // 10 minutes

// Periodic cleanup
setInterval(() => {
    const now = Date.now();
    let cleanedUsers = 0;
    let cleanedMessages = 0;

    for (const [userId, data] of userTypes.entries()) {
        if (now - data.timestamp > USER_TYPE_TTL) {
            userTypes.delete(userId);
            cleanedUsers++;
        }
    }

    for (const [userId, data] of lastMessages.entries()) {
        if (now - data.timestamp > 5 * 60 * 1000) { // 5 minutes
            lastMessages.delete(userId);
            cleanedMessages++;
        }
    }

    if (cleanedUsers > 0 || cleanedMessages > 0) {
        logger.info('Memory cleanup completed', { cleanedUsers, cleanedMessages });
    }
}, MEMORY_CLEANUP_INTERVAL);

function isRepeatedMessage(userId, message) {
    const last = lastMessages.get(userId);
    const normalizedMessage = message.trim().toLowerCase();

    if (last &&
        last.message === normalizedMessage &&
        (Date.now() - last.timestamp) < REPEATED_MSG_WINDOW) {
        last.count = (last.count || 1) + 1;
        return true;
    }

    if (lastMessages.size >= MAX_LAST_MESSAGES) {
        const oldest = lastMessages.keys().next().value;
        lastMessages.delete(oldest);
    }

    lastMessages.set(userId, {
        message: normalizedMessage,
        timestamp: Date.now(),
        count: 1
    });
    return false;
}

// ============================================
// 🔄 CONVERSATION STATE MACHINE
// ============================================

const STATES = {
    START: 'START',
    AWAITING_PICKUP: 'AWAITING_PICKUP',
    AWAITING_PICKUP_SELECTION: 'AWAITING_PICKUP_SELECTION',
    AWAITING_DESTINATION: 'AWAITING_DESTINATION',
    AWAITING_DESTINATION_SELECTION: 'AWAITING_DESTINATION_SELECTION',
    AWAITING_RIDE_TYPE: 'AWAITING_RIDE_TYPE',
    AWAITING_CONFIRMATION: 'AWAITING_CONFIRMATION',
    TRIP_ACTIVE: 'TRIP_ACTIVE',
    AWAITING_CANCEL_CONFIRM: 'AWAITING_CANCEL_CONFIRM',
    COMPLAINT_FLOW: 'COMPLAINT_FLOW',
    RESOLVED: 'RESOLVED'
};

/**
 * Get conversation state with version checking
 */
async function getConversationState(userId) {
    try {
        const rows = await dbQuery(
            'SELECT current_state, state_version, flow_data, updated_at FROM ai_conversation_state WHERE user_id = ?',
            [userId]
        );

        if (rows.length === 0) {
            // New user - create fresh state
            const freshState = StateGuard.createFreshState('START');
            return {
                state: freshState.state,
                data: freshState.data,
                version: freshState.version
            };
        }

        // Parse flow_data
        let flowData = rows[0].flow_data || {};
        if (typeof flowData === 'string') {
            try {
                flowData = JSON.parse(flowData);
            } catch (e) {
                flowData = {};
            }
        }

        const currentState = {
            state: rows[0].current_state,
            version: rows[0].state_version || 1,
            data: flowData,
            updatedAt: rows[0].updated_at ? new Date(rows[0].updated_at).getTime() : null
        };

        // Process through StateGuard
        const { state: processedState, wasModified, actions } = await StateGuard.processState(
            userId,
            currentState
        );

        // Log any state modifications
        if (actions.length > 0) {
            logger.info('State processed', { userId, actions });
        }

        // Save if modified
        if (wasModified) {
            await setConversationState(userId, processedState.state, processedState.data);
        }

        return {
            state: processedState.state,
            data: processedState.data,
            version: processedState.version
        };

    } catch (e) {
        logger.error('Error getting conversation state', { error: e.message, userId });
        return {
            state: STATES.START,
            data: { version: StateGuard.getCurrentVersion() },
            version: StateGuard.getCurrentVersion()
        };
    }
}

/**
 * Set conversation state
 */
async function setConversationState(userId, state, data = {}) {
    try {
        // Validate state
        if (!StateGuard.isValidState(state)) {
            logger.warn('Invalid state attempted', { userId, state });
            state = STATES.START;
        }

        // Prepare state for save
        const preparedData = {
            ...data,
            version: StateGuard.getCurrentVersion(),
            updatedAt: Date.now()
        };

        await dbExecute(`
            INSERT INTO ai_conversation_state (user_id, current_state, state_version, flow_data)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                current_state = VALUES(current_state), 
                state_version = VALUES(state_version),
                flow_data = VALUES(flow_data),
                updated_at = CURRENT_TIMESTAMP
        `, [userId, state, StateGuard.getCurrentVersion(), JSON.stringify(preparedData)]);

    } catch (e) {
        logger.error('Error setting state', { error: e.message, userId, state });
    }
}

// ============================================
// 🗄️ DATABASE HELPERS
// ============================================

async function getActiveRide(userId) {
    try {
        const rows = await dbQuery(`
            SELECT tr.id, tr.ref_id, tr.current_status as status, tr.driver_id, tr.estimated_fare,
                COALESCE(trc.pickup_address, 'نقطة الانطلاق') as pickup,
                COALESCE(trc.destination_address, 'الوجهة') as destination,
                COALESCE(CONCAT(d.first_name, ' ', d.last_name), 'جاري البحث...') as driver_name,
                d.phone as driver_phone
            FROM trip_requests tr
            LEFT JOIN trip_request_coordinates trc ON tr.id = trc.trip_request_id
            LEFT JOIN users d ON tr.driver_id = d.id
            WHERE tr.customer_id = ? AND tr.current_status IN ('pending', 'accepted', 'ongoing', 'arrived')
            ORDER BY tr.created_at DESC LIMIT 1
        `, [userId]);
        return rows[0] || null;
    } catch (e) {
        logger.error('Error getting active ride', { error: e.message, userId });
        return null;
    }
}

async function getLastTrip(userId) {
    try {
        const rows = await dbQuery(`
            SELECT tr.id, tr.ref_id, tr.current_status as status, tr.estimated_fare, tr.created_at,
                COALESCE(trc.pickup_address, 'نقطة الانطلاق') as pickup,
                COALESCE(trc.destination_address, 'الوجهة') as destination,
                COALESCE(CONCAT(d.first_name, ' ', d.last_name), 'غير معروف') as driver_name
            FROM trip_requests tr
            LEFT JOIN trip_request_coordinates trc ON tr.id = trc.trip_request_id
            LEFT JOIN users d ON tr.driver_id = d.id
            WHERE tr.customer_id = ? ORDER BY tr.created_at DESC LIMIT 1
        `, [userId]);
        return rows[0] || null;
    } catch (e) { return null; }
}

async function getChatHistory(userId, limit = 6) {
    try {
        const rows = await dbQuery(
            'SELECT role, content FROM ai_chat_history WHERE user_id = ? ORDER BY created_at DESC LIMIT ?',
            [userId, limit]
        );
        return rows.reverse();
    } catch (e) { return []; }
}

async function saveChat(userId, role, content, actionType = null, actionData = null, metadata = {}) {
    try {
        await dbExecute(
            `INSERT INTO ai_chat_history 
             (user_id, role, content, action_type, action_data, language, intent, confidence, response_time_ms) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
            [
                userId,
                role,
                content,
                actionType,
                actionData ? JSON.stringify(actionData) : null,
                metadata.language || null,
                metadata.intent || null,
                metadata.confidence || null,
                metadata.responseTime || null
            ]
        );
    } catch (e) {
        logger.error('Error saving chat', { error: e.message, userId });
    }
}

async function getUserPreferences(userId) {
    try {
        const rows = await dbQuery(
            `SELECT preferred_language, user_type, favorite_locations, 
                    arabizi_preference, preferred_vehicle_category_id, 
                    frequent_destinations, booking_patterns, personalization_score
             FROM ai_user_preferences WHERE user_id = ?`,
            [userId]
        );

        if (rows.length === 0) return {};

        let favorites = rows[0].favorite_locations;
        if (typeof favorites === 'string') {
            try { favorites = JSON.parse(favorites); } catch (e) { favorites = []; }
        }

        let frequentDestinations = rows[0].frequent_destinations;
        if (typeof frequentDestinations === 'string') {
            try { frequentDestinations = JSON.parse(frequentDestinations); } catch (e) { frequentDestinations = []; }
        }

        let bookingPatterns = rows[0].booking_patterns;
        if (typeof bookingPatterns === 'string') {
            try { bookingPatterns = JSON.parse(bookingPatterns); } catch (e) { bookingPatterns = {}; }
        }

        return {
            preferred_language: rows[0].preferred_language,
            user_type: rows[0].user_type,
            favorites: favorites || [],
            arabizi_preference: rows[0].arabizi_preference,
            preferred_vehicle_category_id: rows[0].preferred_vehicle_category_id,
            frequent_destinations: frequentDestinations || [],
            booking_patterns: bookingPatterns || {},
            personalization_score: rows[0].personalization_score || 0.5
        };
    } catch (e) {
        logger.warn('Error getting user preferences', { userId, error: e.message });
        return {};
    }
}

// ============================================
// 🚗 VEHICLE CATEGORIES
// ============================================

let cachedVehicleCategories = null;
let vehicleCategoriesCacheTime = 0;
const VEHICLE_CACHE_TTL = 5 * 60 * 1000; // 5 minutes

async function getVehicleCategories() {
    try {
        if (cachedVehicleCategories && (Date.now() - vehicleCategoriesCacheTime) < VEHICLE_CACHE_TTL) {
            return cachedVehicleCategories;
        }

        const rows = await dbQuery(`
            SELECT id, name, description, type FROM vehicle_categories
            WHERE is_active = 1 AND deleted_at IS NULL ORDER BY name ASC
        `);

        if (rows.length > 0) {
            cachedVehicleCategories = rows;
            vehicleCategoriesCacheTime = Date.now();
            return rows;
        }

        // Default categories
        return [
            { id: '1', name: 'توفير' },
            { id: '2', name: 'سمارت برو' },
            { id: '3', name: 'في اي بي' }
        ];
    } catch (e) {
        return [
            { id: '1', name: 'توفير' },
            { id: '2', name: 'سمارت برو' },
            { id: '3', name: 'في اي بي' }
        ];
    }
}

function formatVehicleCategoriesMessage(categories, lang) {
    let msg = lang === 'ar'
        ? '✅ تم تحديد الوجهة.\n🚗 اختر نوع الرحلة:\n\n'
        : '✅ Destination set.\n🚗 Choose ride type:\n\n';

    categories.forEach((cat, i) => {
        msg += `${i + 1}. ${cat.name}\n`;
    });

    return msg.trim();
}

// ============================================
// 🎯 SYSTEM PROMPT
// ============================================

let cachedSystemPrompt = null;
let promptCacheTime = 0;
const PROMPT_CACHE_TTL = 60000;

const DEFAULT_SYSTEM_PROMPT = `You are "Smart" - SmartLine's AI assistant for RIDE-HAILING ONLY (like Uber/Careem) in Egypt.

<WHAT IS SMARTLINE>
- SmartLine is a RIDE-HAILING service (تطبيق توصيل) - books CAR rides only
- Users book CAR/TAXI rides from one location to another
- When users say "رحلة" (trip), they mean a CAR RIDE with SmartLine
</WHAT IS SMARTLINE>

<ABSOLUTELY_FORBIDDEN - NEVER SAY THESE>
❌ "يمكنك ركوب المترو" / "You can take the metro"
❌ "يمكنك ركوب الأتوبيس" / "You can take the bus"  
❌ "يمكنك النزول في محطة" / "Get off at station"
❌ "هناك خطوط مواصلات" / "There are transport lines"
❌ "يمكنك ركوب التاكسي من" / "You can take a taxi from"
❌ "المسافة حوالي" / "The distance is about"
❌ ANY mention of metro, bus, microbus, train, or external taxi
❌ ANY travel directions or public transport advice
</ABSOLUTELY_FORBIDDEN>

<CRITICAL_RESTRICTIONS>
- You are ONLY a ride-hailing assistant. You can ONLY help with:
  1. Booking SmartLine CAR RIDES (pickup, destination, vehicle type)
  2. Trip status and tracking
  3. Cancelling rides
  4. Contacting drivers
  5. Safety during rides
  6. Complaints about rides/drivers

- You MUST NEVER:
  * Ask for flight details (airplane type, domestic/international, departure date)
  * Ask for travel dates or number of passengers (we only need pickup/destination)
  * Provide public transportation directions (metro, bus routes)
  * Suggest alternative travel methods (trains, buses, flights)
  * Act as a travel planner or travel agency
  * Answer questions about general knowledge, news, politics, religion
  * Answer questions about company ownership, shareholders, business structure
  * Give personal advice, health advice, relationship advice
  * Answer technical questions about how the app works internally

- If asked about ANYTHING outside ride-hailing services, respond ONLY with:
  English: "I can only help with ride-hailing services like booking car rides, tracking your driver, or contacting support. How can I help you with your ride today?"
  Arabic: "أنا أقدر أساعدك فقط في خدمات التوصيل زي حجز رحلة بالعربية أو تتبع السواق أو التواصل مع الدعم. إزاي أقدر أساعدك في رحلتك النهارده؟"
</CRITICAL_RESTRICTIONS>

<CORRECT BOOKING FLOW>
When user wants to book a ride:
1. Ask ONLY for: "من فين عايز تروح؟" (Where do you want to go from?) - PICKUP LOCATION
2. Then ask: "عايز تروح فين؟" (Where do you want to go to?) - DESTINATION
3. Then show vehicle options (Economy, Comfort, VIP)
4. Confirm the booking

DO NOT ask for:
- Trip date (rides are immediate/on-demand)
- Number of passengers (we only need pickup/destination)
- Flight type (domestic/international) - WE DON'T DO FLIGHTS
- Travel planning or route suggestions
</CORRECT BOOKING FLOW>

<EXAMPLES>
❌ WRONG (Travel Agency):
User: "اريد حجز رحلة"
AI: "ممكن تعطيني تاريخ الرحلة وعدد الركاب ونوع الطائرة؟"
This is WRONG - we don't book flights!

✅ CORRECT (Ride-Hailing):
User: "اريد حجز رحلة"
AI: "طيب هحجزلك رحلة دلوقتي! من فين عايز تروح؟"
This is CORRECT - we book car rides!

❌ WRONG (Public Transport):
User: "من العجمي ل الجيزة"
AI: "يمكنك ركوب المترو من محطة رمسيس..."
This is WRONG - we book car rides, not give directions!

✅ CORRECT (Ride-Hailing):
User: "من العجمي ل الجيزة"
AI: "تمام! هحجزلك رحلة من العجمي للجيزة. اختار نوع العربية: Economy, Comfort, أو VIP"
This is CORRECT - we book the ride!
</EXAMPLES>

<LANGUAGE_RULES>
- STRICTLY respond in ONE language only (the user's language)
- If user writes in Arabic: respond ONLY in Arabic
- If user writes in English: respond ONLY in English
- NEVER mix Arabic and English in the same response
- NEVER use Arabizi in responses
- Use Egyptian dialect for Arabic responses (طيب، عايز، فين، ازاي)
</LANGUAGE_RULES>

<ALLOWED_ACTIONS>
BOOKING: request_pickup_location, request_destination, show_ride_options, confirm_booking
TRACKING: show_trip_tracking, show_driver_info
TRIP: cancel_trip, confirm_cancel_trip, contact_driver
SAFETY: trigger_emergency, share_live_location
SUPPORT: connect_support, call_support
</ALLOWED_ACTIONS>

<STYLE>
- Be warm but concise (Egyptian dialect OK for Arabic responses)
- Use emojis sparingly: 🚗 📍 ✅ ❌ 🎧 💰
- Always end with a clear next step or question about rides
- Never make up information about fares or ETAs
- Keep responses to max 3 sentences
- Remember: You book CAR RIDES, not flights or travel plans
</STYLE>`;

async function getSystemPrompt() {
    try {
        if (cachedSystemPrompt && (Date.now() - promptCacheTime) < PROMPT_CACHE_TTL) {
            return cachedSystemPrompt;
        }

        const rows = await dbQuery(
            "SELECT value FROM business_settings WHERE key_name = 'ai_chatbot_prompt' AND settings_type = 'ai_config' LIMIT 1"
        );

        if (rows.length > 0) {
            cachedSystemPrompt = rows[0].value.replace(/^"|"$/g, '');
            promptCacheTime = Date.now();
            return cachedSystemPrompt;
        }

        cachedSystemPrompt = DEFAULT_SYSTEM_PROMPT;
        promptCacheTime = Date.now();
        return DEFAULT_SYSTEM_PROMPT;
    } catch (e) {
        return DEFAULT_SYSTEM_PROMPT;
    }
}

// ============================================
// 🎫 TRIP CREATION SYSTEM
// ============================================

/**
 * Find zone based on pickup coordinates
 */
async function findZoneByCoordinates(lat, lng) {
    try {
        const zones = await dbQuery(`
            SELECT id, name, coordinates FROM zones
            WHERE is_active = 1 AND deleted_at IS NULL
        `);

        if (zones.length === 0) {
            const defaultZone = await dbQuery(`SELECT id FROM zones LIMIT 1`);
            return defaultZone[0]?.id || null;
        }

        // TODO: Implement proper point-in-polygon check
        return zones[0].id;
    } catch (e) {
        logger.error('Error finding zone', { error: e.message });
        return null;
    }
}

/**
 * Get next ref_id for trip
 */
async function getNextRefId() {
    try {
        const result = await dbQuery(`
            SELECT COALESCE(MAX(ref_id), 99999) + 1 as next_ref_id FROM trip_requests
        `);
        return result[0].next_ref_id;
    } catch (e) {
        return 100000 + Math.floor(Math.random() * 10000);
    }
}

/**
 * Calculate estimated fare based on distance and vehicle category
 */
async function calculateEstimatedFare(vehicleCategoryId, distanceKm = 5) {
    try {
        const fares = await dbQuery(`
            SELECT base_fare, base_fare_per_km, waiting_fee_per_min, cancellation_fee_percent, min_price
            FROM trip_fares
            WHERE vehicle_category_id = ? AND zone_id IS NOT NULL
            LIMIT 1
        `, [vehicleCategoryId]);

        if (fares.length > 0) {
            const fare = fares[0];
            let estimated = parseFloat(fare.base_fare) + (parseFloat(fare.base_fare_per_km) * distanceKm);
            if (fare.min_price && estimated < parseFloat(fare.min_price)) {
                estimated = parseFloat(fare.min_price);
            }
            return Math.round(estimated * 100) / 100;
        }

        // Default fare calculation
        return Math.round((15 + (distanceKm * 3)) * 100) / 100;
    } catch (e) {
        logger.error('Error calculating fare', { error: e.message });
        return 25.00;
    }
}

/**
 * Create a new trip in the database
 */
async function createTrip(tripData) {
    const connection = await pool.getConnection();

    try {
        await connection.beginTransaction();

        const tripId = uuidv4();
        const refId = await getNextRefId();
        const now = new Date().toISOString().slice(0, 19).replace('T', ' ');

        // Parse coordinates
        let pickupLat, pickupLng, destLat, destLng;

        if (tripData.pickup && typeof tripData.pickup === 'object') {
            pickupLat = tripData.pickup.lat;
            pickupLng = tripData.pickup.lng;
        } else if (tripData.pickup && typeof tripData.pickup === 'string') {
            const pickupMatch = tripData.pickup.match(/location:([\d.-]+),([\d.-]+)/);
            if (pickupMatch) {
                pickupLat = parseFloat(pickupMatch[1]);
                pickupLng = parseFloat(pickupMatch[2]);
            }
        }

        if (tripData.destination && typeof tripData.destination === 'object') {
            destLat = tripData.destination.lat;
            destLng = tripData.destination.lng;
        } else if (tripData.destination && typeof tripData.destination === 'string') {
            const destMatch = tripData.destination.match(/location:([\d.-]+),([\d.-]+)/);
            if (destMatch) {
                destLat = parseFloat(destMatch[1]);
                destLng = parseFloat(destMatch[2]);
            }
        }

        // Default coordinates (Cairo)
        if (!pickupLat || !pickupLng) {
            pickupLat = 30.0444;
            pickupLng = 31.2357;
        }
        if (!destLat || !destLng) {
            destLat = pickupLat + 0.01;
            destLng = pickupLng + 0.01;
        }

        // Find zone
        const zoneId = await findZoneByCoordinates(pickupLat, pickupLng);

        // Calculate estimated fare
        const estimatedFare = await calculateEstimatedFare(tripData.ride_type, 5);

        // Get addresses
        const pickupAddress = tripData.pickup_address ||
            (typeof tripData.pickup === 'object' ? tripData.pickup.address : null) ||
            'نقطة الانطلاق';
        const destAddress = tripData.destination_address ||
            (typeof tripData.destination === 'object' ? tripData.destination.address : null) ||
            'الوجهة';

        // 1. Insert into trip_requests
        await connection.execute(`
            INSERT INTO trip_requests (
                id, ref_id, customer_id, vehicle_category_id, zone_id,
                estimated_fare, actual_fare, estimated_distance,
                payment_method, type, current_status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        `, [
            tripId, refId, tripData.customer_id, tripData.ride_type, zoneId,
            estimatedFare, estimatedFare, 5.0,
            'cash', 'ride_request', 'pending', now, now
        ]);

        // 2. Insert into trip_status
        await connection.execute(`
            INSERT INTO trip_status (trip_request_id, customer_id, pending, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?)
        `, [tripId, tripData.customer_id, now, now, now]);

        // 3. Insert into trip_request_coordinates
        await connection.execute(`
            INSERT INTO trip_request_coordinates (
                trip_request_id,
                pickup_coordinates, destination_coordinates,
                start_coordinates, customer_request_coordinates,
                pickup_address, destination_address,
                created_at, updated_at
            ) VALUES (?, ST_GeomFromText(?), ST_GeomFromText(?), ST_GeomFromText(?), ST_GeomFromText(?), ?, ?, ?, ?)
        `, [
            tripId,
            `POINT(${pickupLat} ${pickupLng})`,
            `POINT(${destLat} ${destLng})`,
            `POINT(${pickupLat} ${pickupLng})`,
            `POINT(${pickupLat} ${pickupLng})`,
            pickupAddress, destAddress, now, now
        ]);

        // 4. Insert into trip_request_fees
        await connection.execute(`
            INSERT INTO trip_request_fees (trip_request_id, created_at, updated_at)
            VALUES (?, ?, ?)
        `, [tripId, now, now]);

        // 5. Insert into trip_request_times
        await connection.execute(`
            INSERT INTO trip_request_times (trip_request_id, estimated_time, created_at, updated_at)
            VALUES (?, ?, ?, ?)
        `, [tripId, 15, now, now]);

        await connection.commit();

        logger.info('Trip created successfully', { tripId, refId, customerId: tripData.customer_id });

        return {
            success: true,
            trip_id: tripId,
            ref_id: refId,
            estimated_fare: estimatedFare,
            pickup_address: pickupAddress,
            destination_address: destAddress,
            status: 'pending'
        };

    } catch (error) {
        await connection.rollback();
        logger.error('Failed to create trip', { error: error.message, stack: error.stack });
        return { success: false, error: error.message };
    } finally {
        connection.release();
    }
}

/**
 * Cancel a trip
 */
async function cancelTrip(tripId) {
    try {
        const now = new Date().toISOString().slice(0, 19).replace('T', ' ');

        await dbExecute(`
            UPDATE trip_requests SET current_status = 'cancelled', updated_at = ? WHERE id = ?
        `, [now, tripId]);

        await dbExecute(`
            UPDATE trip_request_fees SET cancelled_by = 'customer', updated_at = ? WHERE trip_request_id = ?
        `, [now, tripId]);

        return { success: true };
    } catch (e) {
        logger.error('Failed to cancel trip', { error: e.message });
        return { success: false, error: e.message };
    }
}

// ============================================
// 💰 WALLET & HISTORY FUNCTIONS
// ============================================

/**
 * Get user's wallet balance
 */
async function getWalletBalance(userId) {
    try {
        const result = await dbQuery(`
            SELECT wallet_balance FROM users WHERE id = ?
        `, [userId]);
        return result[0]?.wallet_balance || 0;
    } catch (e) {
        logger.error('Failed to get wallet balance', { error: e.message });
        return 0;
    }
}

/**
 * Get user's trip history
 */
async function getTripHistory(userId, limit = 5) {
    try {
        const trips = await dbQuery(`
            SELECT 
                tr.id,
                tr.ref_id,
                tr.pickup_address,
                tr.destination_address,
                tr.estimated_fare,
                trf.total_amount,
                tr.current_status,
                tr.created_at,
                CONCAT(u.first_name, ' ', u.last_name) as driver_name
            FROM trip_requests tr
            LEFT JOIN trip_request_fees trf ON tr.id = trf.trip_request_id
            LEFT JOIN drivers d ON tr.driver_id = d.id
            LEFT JOIN users u ON d.user_id = u.id
            WHERE tr.customer_id = ?
            ORDER BY tr.created_at DESC
            LIMIT ?
        `, [userId, limit]);
        return trips;
    } catch (e) {
        logger.error('Failed to get trip history', { error: e.message });
        return [];
    }
}

/**
 * Apply promo code
 */
async function applyPromoCode(userId, promoCode) {
    try {
        const promo = await dbQuery(`
            SELECT * FROM promo_codes 
            WHERE code = ? 
            AND is_active = 1 
            AND (expiry_date IS NULL OR expiry_date > NOW())
            AND (usage_limit IS NULL OR usage_count < usage_limit)
        `, [promoCode.toUpperCase()]);

        if (promo.length === 0) {
            return { success: false, error: 'invalid_code' };
        }

        // Check if user already used this promo
        const used = await dbQuery(`
            SELECT id FROM promo_code_usage 
            WHERE user_id = ? AND promo_code_id = ?
        `, [userId, promo[0].id]);

        if (used.length > 0 && !promo[0].allow_multiple_use) {
            return { success: false, error: 'already_used' };
        }

        return {
            success: true,
            discount: promo[0].discount_amount || 0,
            discount_type: promo[0].discount_type || 'fixed', // 'fixed' or 'percentage'
            max_discount: promo[0].max_discount || null,
            promo_id: promo[0].id
        };
    } catch (e) {
        logger.error('Failed to apply promo code', { error: e.message });
        return { success: false, error: 'system_error' };
    }
}

/**
 * Submit a complaint
 */
async function submitComplaint(userId, tripId, complaintType, description) {
    try {
        const complaintId = uuidv4();
        await dbExecute(`
            INSERT INTO complaints (id, user_id, trip_id, type, description, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        `, [complaintId, userId, tripId, complaintType, description]);

        return { success: true, complaint_id: complaintId };
    } catch (e) {
        logger.error('Failed to submit complaint', { error: e.message });
        return { success: false };
    }
}

/**
 * Submit trip rating
 */
async function submitRating(userId, tripId, rating, feedback = null) {
    try {
        await dbExecute(`
            UPDATE trip_requests 
            SET customer_rating = ?, customer_feedback = ?, updated_at = NOW()
            WHERE id = ? AND customer_id = ?
        `, [rating, feedback, tripId, userId]);

        // Also update driver's average rating
        const trip = await dbQuery(`SELECT driver_id FROM trip_requests WHERE id = ?`, [tripId]);
        if (trip[0]?.driver_id) {
            await dbExecute(`
                UPDATE drivers 
                SET rating = (
                    SELECT AVG(customer_rating) 
                    FROM trip_requests 
                    WHERE driver_id = ? AND customer_rating IS NOT NULL
                )
                WHERE id = ?
            `, [trip[0].driver_id, trip[0].driver_id]);
        }

        return { success: true };
    } catch (e) {
        logger.error('Failed to submit rating', { error: e.message });
        return { success: false };
    }
}

// ============================================
// 📍 AUTOCOMPLETE API INTEGRATION
// ============================================

const AUTOCOMPLETE_TIMEOUT = 10000; // 10 seconds

async function searchPlaces(searchText, latitude, longitude, zoneId, language = 'ar') {
    try {
        const baseUrl = process.env.LARAVEL_BASE_URL || 'https://smartline-it.com';
        const url = `${baseUrl}/api/customer/config/place-api-autocomplete?` +
            `search_text=${encodeURIComponent(searchText)}` +
            `&latitude=${latitude}` +
            `&longitude=${longitude}` +
            `&language=${language}` +
            `&country=eg` +
            `&zoneId=${zoneId}`;

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), AUTOCOMPLETE_TIMEOUT);

        const response = await fetch(url, { signal: controller.signal });
        clearTimeout(timeoutId);

        const data = await response.json();

        if (data.response_code === 'default_200' && data.data && data.data.predictions) {
            return {
                success: true,
                predictions: data.data.predictions.slice(0, 5)
            };
        }

        return { success: false, predictions: [] };
    } catch (error) {
        if (error.name === 'AbortError') {
            logger.warn('Autocomplete API timeout');
        } else {
            logger.error('Autocomplete API failed', { error: error.message });
        }
        return { success: false, predictions: [] };
    }
}

function formatPredictions(predictions, lang) {
    if (predictions.length === 0) {
        return lang === 'ar'
            ? '❌ لم يتم العثور على نتائج. حاول مرة أخرى.'
            : '❌ No results found. Try again.';
    }

    let message = lang === 'ar' ? '📍 اختر الموقع:\n\n' : '📍 Choose location:\n\n';
    predictions.forEach((pred, index) => {
        const mainText = pred.structured_formatting?.main_text || pred.description;
        message += `${index + 1}. ${mainText}\n`;
    });
    message += '\n' + (lang === 'ar' ? '👆 أرسل رقم الخيار' : '👆 Send the number');

    return message;
}

// ============================================
// 🤖 GROQ LLM API
// ============================================

const LLM_TIMEOUT = 25000; // 25 seconds
const LLM_MAX_RETRIES = 2;

async function callLLM(messages, options = {}) {
    const {
        temperature = 0.4,
        maxTokens = 300,
        timeout = LLM_TIMEOUT,
        targetLanguage = null // NEW: for language enforcement
    } = options;

    // Add language instruction if target language specified
    if (targetLanguage && messages.length > 0 && messages[0].role === 'system') {
        const langInstruction = LanguageManager.getLanguageInstruction(targetLanguage);
        const promptValidation = LanguageManager.validateLLMPrompt(messages[0].content, targetLanguage);
        if (!promptValidation.valid) {
            messages[0].content = promptValidation.suggestedPrompt;
        }
    }

    const apiKey = process.env.GROQ_API_KEY;
    if (!apiKey) {
        throw new Error("GROQ_API_KEY not set");
    }

    const startTime = Date.now();
    let lastError = null;

    for (let attempt = 0; attempt < LLM_MAX_RETRIES; attempt++) {
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), timeout);

            const response = await fetch('https://api.groq.com/openai/v1/chat/completions', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${apiKey}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    model: 'llama-3.3-70b-versatile',
                    messages,
                    temperature,
                    max_tokens: maxTokens
                }),
                signal: controller.signal
            });

            clearTimeout(timeoutId);

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`Groq API error: ${response.status} - ${errorText}`);
            }

            const data = await response.json();
            const latency = Date.now() - startTime;
            updateLLMMetrics(latency, true);

            return data.choices[0].message.content;

        } catch (error) {
            lastError = error;

            if (error.name === 'AbortError') {
                logger.warn('LLM request timeout', { attempt: attempt + 1 });
            } else {
                logger.error('LLM API error', { error: error.message, attempt: attempt + 1 });
            }

            // Wait before retry (exponential backoff)
            if (attempt < LLM_MAX_RETRIES - 1) {
                await new Promise(resolve => setTimeout(resolve, Math.pow(2, attempt) * 1000));
            }
        }
    }

    updateLLMMetrics(Date.now() - startTime, false);
    throw lastError || new Error('LLM call failed after retries');
}

// ============================================
// 🎬 MAIN CONVERSATION PROCESSOR
// ============================================

async function processConversation(userId, message, lang, userType, langResult) {
    const startTime = Date.now();

    // 1. Get and validate state
    const convState = await getConversationState(userId);

    // 2. Get user preferences
    let userPrefs = {};
    try {
        userPrefs = await getUserPreferences(userId);
    } catch (e) {
        logger.warn('Failed to get user preferences', { userId, error: e.message });
    }

    // 3. Handle language clarification if needed
    if (langResult.shouldAskClarification) {
        const clarification = LanguageManager.generateClarificationMessage(lang);
        return {
            message: clarification.message,
            action: ACTION_TYPES.NONE,
            quick_replies: clarification.quick_replies,
            language: lang,
            userType,
            confidence: 0.5
        };
    }

    // 4. Captain Flow (REGISTRATION STATUS ONLY) - with database verification
    if (userType === 'captain') {
        // Captains should ONLY get registration status, not ride booking
        return handleCaptainRegistrationFlow(userId, message, lang);
    }

    // 5. Get active ride
    const activeRide = await getActiveRide(userId);

    // 6. Intent Classification (Hybrid if enabled)
    let classification;
    const useHybridClassifier = isFeatureEnabled('HYBRID_CLASSIFIER', userId);

    if (useHybridClassifier) {
        try {
            const history = await getChatHistory(userId, 4);
            const classifierConfig = require('./utils/featureFlags').getFeatureConfig('HYBRID_CLASSIFIER');

            classification = await IntentClassifier.classify(message, {
                userType,
                language: lang,
                conversationContext: history.map(h => ({ role: h.role, content: h.content })),
                skipL3: !classifierConfig?.l3Enabled
            });
        } catch (error) {
            logger.error('Intent classifier failed, using regex fallback', {
                error: error.message,
                userId
            });
            // Fallback to regex
            const degradation = applyDegradation('classifier_fail', error, {});
            classification = classifyIntent(message, userType);
            classification.fallback = true;
        }
    } else {
        // Use regex-only (original behavior)
        classification = classifyIntent(message, userType);
    }

    // Handle ambiguous intent
    if (classification.intent === 'AMBIGUOUS') {
        return {
            message: classification.message || (lang === 'ar'
                ? 'مش فاهم. ممكن توضح أكتر؟'
                : 'I\'m not sure what you mean. Could you clarify?'),
            action: ACTION_TYPES.NONE,
            quick_replies: classification.quick_replies || [],
            language: lang,
            userType,
            confidence: classification.confidence,
            ambiguous: true,
            candidates: classification.candidates
        };
    }

    // 7. Initialize response
    let response = {
        message: '',
        action: ACTION_TYPES.NONE,
        data: {},
        quick_replies: [],
        ui_hint: null,
        confidence: classification.confidence,
        handoff: false,
        language: lang,
        userType
    };

    // 8. SAFETY CHECK (HIGHEST PRIORITY)
    if (classification.intent === 'SAFETY' || /\b(طوارئ|emergency|sos|خطر|danger|help me)\b/i.test(message)) {
        response.message = lang === 'ar'
            ? '🚨 سلامتك أهم حاجة! هل أنت بأمان دلوقتي؟\n\nلو محتاج مساعدة فورية، اتصل بـ 122 (الشرطة) أو 123 (الإسعاف)'
            : '🚨 Your safety comes first! Are you safe right now?\n\nFor immediate help, call 122 (Police) or 123 (Ambulance)';

        const emergencyAction = ActionBuilders.triggerEmergency(activeRide?.id);
        response.action = emergencyAction.action;
        response.data = { ...emergencyAction.data, trip_id: activeRide?.id };
        response.handoff = true;
        response.quick_replies = lang === 'ar'
            ? ['نعم، أنا بأمان', 'محتاج مساعدة فورية', 'اتصل بالدعم']
            : ['Yes, I\'m safe', 'Need immediate help', 'Call support'];

        await setConversationState(userId, STATES.RESOLVED, { emergency: true });
        logSecurityEvent('emergency_triggered', { userId });

        return response;
    }

    // 9. HUMAN HANDOFF
    if (classification.intent === 'SUPPORT' || /\b(agent|human|موظف|بشري|كلمني حد)\b/i.test(message)) {
        response.message = lang === 'ar'
            ? '🎧 جاري تحويلك لفريق الدعم. حد هيرد عليك في أقرب وقت.'
            : '🎧 Connecting you to our support team. Someone will assist you shortly.';

        const supportAction = ActionBuilders.connectSupport('user_request', activeRide?.id);
        response.action = supportAction.action;
        response.data = supportAction.data;
        response.handoff = true;

        await setConversationState(userId, STATES.RESOLVED, { handoff: true });
        return response;
    }

    // 10. Global cancel command (except during active trip)
    if (classification.intent === 'CANCEL_TRIP' &&
        convState.state !== STATES.TRIP_ACTIVE &&
        convState.state !== STATES.AWAITING_CANCEL_CONFIRM) {

        await setConversationState(userId, STATES.START, {});
        response.message = lang === 'ar'
            ? 'تم الإلغاء. كيف أقدر أساعدك؟'
            : 'Cancelled. How can I help you?';
        response.quick_replies = getDefaultQuickReplies(lang);
        return response;
    }

    // 11. ⚡ CRITICAL: Dual-location detection BEFORE state processing
    // This MUST happen before LLM is called to prevent travel advice
    const dualLocationResult = detectDualLocation(message, lang);
    if (dualLocationResult.found && 
        ['START', 'AWAITING_PICKUP', 'AWAITING_DESTINATION'].includes(convState.state)) {
        
        // Save both locations and skip to vehicle selection - NO LLM CALL
        await setConversationState(userId, STATES.AWAITING_RIDE_TYPE, {
            pickup_location: dualLocationResult.pickup,
            destination: dualLocationResult.destination
        });
        
        const templates = require('./utils/prompts').getBookingTemplates(lang);
        response.message = templates.locationsSet
            .replace('{{pickup}}', dualLocationResult.pickup)
            .replace('{{destination}}', dualLocationResult.destination);
        response.quick_replies = templates.vehicleOptions;
        response.action = ACTION_TYPES.SELECT_VEHICLE;
        response.data = {
            pickup: dualLocationResult.pickup,
            destination: dualLocationResult.destination
        };
        
        logger.info('Dual location detected - bypassing LLM', {
            userId,
            pickup: dualLocationResult.pickup,
            destination: dualLocationResult.destination
        });
        
        return response;
    }

    // 12. STATE-BASED PROCESSING
    response = await processStateBasedFlow(
        userId, message, lang, classification, convState, activeRide, userPrefs, response
    );

    return response;
}

/**
 * ⚡ CRITICAL: Detect dual location pattern "من X ل Y"
 * This MUST be called BEFORE any LLM call to prevent travel advice
 */
function detectDualLocation(message, lang) {
    // Arabic patterns - comprehensive
    const arabicPatterns = [
        /من\s+(.+?)\s+(?:ل|إلى|الى|لـ|ل‎)\s+(.+?)(?:\s*$|[،,.])/i,
        /من\s+(.+?)\s+(?:ل|إلى|الى)\s+(.+)/i,
        /(?:اريد|عايز|محتاج)\s+(?:رحلة\s+)?من\s+(.+?)\s+(?:ل|إلى|الى)\s+(.+)/i,
        /(?:وصلني|خدني|خذني)\s+من\s+(.+?)\s+(?:ل|إلى|الى)\s+(.+)/i,
        /من\s+عند\s+(.+?)\s+(?:ل|إلى|الى)\s+(.+)/i
    ];
    
    // English patterns
    const englishPatterns = [
        /from\s+(.+?)\s+to\s+(.+?)(?:\s*$|[,.])/i,
        /pickup\s+(?:at|from)\s+(.+?)\s+(?:to|destination)\s+(.+)/i,
        /(.+?)\s+to\s+(.+)/i
    ];
    
    const patterns = lang === 'en' ? englishPatterns : arabicPatterns;
    
    for (const pattern of patterns) {
        const match = message.match(pattern);
        if (match) {
            const pickup = match[1]?.trim();
            const destination = match[2]?.trim();
            
            // Validate: both must exist and be different
            if (pickup && destination && 
                pickup.length >= 2 && destination.length >= 2 &&
                pickup.toLowerCase() !== destination.toLowerCase()) {
                
                return {
                    found: true,
                    pickup,
                    destination
                };
            }
        }
    }
    
    return { found: false };
}

/**
 * Handle captain registration status flow (REGISTRATION ONLY)
 * Captains are NOT allowed to book rides through chatbot
 * They should use the Captain Flutter app for ride operations
 */
async function handleCaptainRegistrationFlow(userId, message, lang) {
    try {
        // Get captain info and registration status from database
        const statusInfo = await getCaptainRegistrationStatus(userId, dbQuery);

        if (!statusInfo.found) {
            // User claims to be captain but not in database
            logSecurityEvent('captain_impersonation_attempt', {
                userId,
                reason: statusInfo.status
            });

            return {
                message: lang === 'ar'
                    ? '⚠️ لم نتمكن من العثور على حساب كابتن مرتبط بهذا المستخدم.\n\nإذا كنت ترغب في التسجيل ككابتن، يرجى التواصل مع فريق الدعم.'
                    : '⚠️ We couldn\'t find a captain account linked to this user.\n\nIf you want to register as a captain, please contact our support team.',
                action: ACTION_TYPES.CONNECT_SUPPORT,
                data: { reason: 'captain_account_not_found' },
                quick_replies: lang === 'ar'
                    ? ['📞 التواصل مع الدعم', '🏠 العودة للقائمة الرئيسية']
                    : ['📞 Contact Support', '🏠 Back to Main Menu'],
                userType: 'customer', // Treat as customer
                language: lang
            };
        }

        // Get captain name
        const captainName = statusInfo.captain?.name || 'Captain';
        const registrationStatus = statusInfo.status;

        // Generate response based on registration status
        const response = getCaptainRegistrationResponse(captainName, lang, registrationStatus);

        // Add informational message about using Captain app for operations
        const appNotice = lang === 'ar'
            ? '\n\n📱 للعمليات اليومية (قبول الرحلات، الأرباح، المواقع)، يرجى استخدام تطبيق الكابتن.'
            : '\n\n📱 For daily operations (accepting rides, earnings, locations), please use the Captain app.';

        if (registrationStatus === 'approved') {
            response.message += appNotice;
        }

        // Log captain inquiry
        logger.info('Captain registration inquiry', {
            userId,
            captainName,
            status: registrationStatus,
            language: lang
        });

        return response;

    } catch (error) {
        logger.error('Captain registration flow error', {
            error: error.message,
            userId
        });

        return {
            message: lang === 'ar'
                ? '❌ عذراً، حدث خطأ أثناء التحقق من حالة التسجيل. يرجى المحاولة لاحقاً أو التواصل مع الدعم الفني.'
                : '❌ Sorry, an error occurred while checking your registration status. Please try again later or contact support.',
            action: ACTION_TYPES.CONNECT_SUPPORT,
            data: { error: 'registration_check_failed' },
            quick_replies: lang === 'ar'
                ? ['📞 التواصل مع الدعم', '🔄 إعادة المحاولة']
                : ['📞 Contact Support', '🔄 Try Again'],
            userType: 'captain',
            language: lang
        };
    }
}

/**
 * Process state-based conversation flow
 */
async function processStateBasedFlow(userId, message, lang, classification, convState, activeRide, userPrefs, response) {

    switch (convState.state) {
        case STATES.START:
            return await handleStartState(userId, message, lang, classification, activeRide, userPrefs, response);

        case STATES.AWAITING_PICKUP:
            return await handleAwaitingPickupState(userId, message, lang, convState, response);

        case STATES.AWAITING_PICKUP_SELECTION:
            return await handleAwaitingPickupSelectionState(userId, message, lang, convState, response);

        case STATES.AWAITING_DESTINATION:
            return await handleAwaitingDestinationState(userId, message, lang, convState, response);

        case STATES.AWAITING_DESTINATION_SELECTION:
            return await handleAwaitingDestinationSelectionState(userId, message, lang, convState, response);

        case STATES.AWAITING_RIDE_TYPE:
            return await handleAwaitingRideTypeState(userId, message, lang, convState, response);

        case STATES.AWAITING_CONFIRMATION:
            return await handleAwaitingConfirmationState(userId, message, lang, convState, response);

        case STATES.TRIP_ACTIVE:
            return await handleTripActiveState(userId, message, lang, classification, convState, activeRide, response);

        case STATES.AWAITING_CANCEL_CONFIRM:
            return await handleAwaitingCancelConfirmState(userId, message, lang, convState, response);

        // NEW STATE HANDLERS
        case 'AWAITING_PROMO_CODE':
            return await handleAwaitingPromoCodeState(userId, message, lang, convState, response);

        case 'AWAITING_SCHEDULE_TIME':
            return await handleAwaitingScheduleTimeState(userId, message, lang, convState, response);

        case 'AWAITING_COMPLAINT_TYPE':
            return await handleAwaitingComplaintTypeState(userId, message, lang, convState, response);

        case 'AWAITING_COMPLAINT_DETAILS':
            return await handleAwaitingComplaintDetailsState(userId, message, lang, convState, response);

        case 'AWAITING_RATING':
            return await handleAwaitingRatingState(userId, message, lang, convState, response);

        default:
            await setConversationState(userId, STATES.START, {});
            response.message = lang === 'ar' ? 'كيف أقدر أساعدك؟' : 'How can I help you?';
            response.quick_replies = getDefaultQuickReplies(lang);
            return response;
    }
}

// ============================================
// 📍 STATE HANDLERS
// ============================================

async function handleStartState(userId, message, lang, classification, activeRide, userPrefs, response) {
    // FIRST: Handle out-of-context questions
    if (classification.intent === 'OUT_OF_CONTEXT') {
        const outOfContextResponse = getResponse('OUT_OF_CONTEXT', lang);
        response.message = outOfContextResponse.message;
        response.action = ACTION_TYPES.NONE;
        response.quick_replies = outOfContextResponse.quick_replies;
        response.outOfContext = true;

        // Log for monitoring
        logger.info('Out-of-context question blocked', {
            userId,
            category: classification.category,
            lang
        });

        return response;
    }

    // If user has an active ride, show it with smart quick replies
    if (activeRide) {
        response.message = lang === 'ar'
            ? `🚗 رحلتك الحالية:\n👨‍✈️ ${activeRide.driver_name || 'الكابتن'}\n📍 ${activeRide.pickup} → ${activeRide.destination}`
            : `🚗 Your current trip:\n👨‍✈️ ${activeRide.driver_name || 'Captain'}\n📍 ${activeRide.pickup} → ${activeRide.destination}`;

        const trackingAction = ActionBuilders.showTripTracking(activeRide.id);
        response.action = trackingAction.action;
        response.data = { ...trackingAction.data, ride: activeRide };
        // V3.4.1: Better quick replies for active trip
        response.quick_replies = getQuickReplies('ACTIVE_TRIP', lang);

        await setConversationState(userId, STATES.TRIP_ACTIVE, { trip_id: activeRide.id });
        return response;
    }

    // Book trip intent - V3.4.1: Quick replies for pickup
    if (classification.intent === 'BOOK_TRIP' || /^1$/.test(message.trim())) {
        const pickupResponse = getResponse('ASK_PICKUP', lang);
        response.message = pickupResponse.message;
        
        // Add favorites to quick replies if available
        if (userPrefs.favorites && userPrefs.favorites.length > 0) {
            const favOptions = userPrefs.favorites.slice(0, 2).map(f => `⭐ ${f.name}`);
            response.quick_replies = [
                pickupResponse.quick_replies[0], // Current location
                ...favOptions,
                ...pickupResponse.quick_replies.slice(1)
            ].slice(0, 6);
        } else {
            response.quick_replies = pickupResponse.quick_replies;
        }

        const pickupAction = ActionBuilders.requestPickup();
        response.action = pickupAction.action;
        response.data = pickupAction.data;

        await setConversationState(userId, STATES.AWAITING_PICKUP, {});
        return response;
    }

    // Trip status intent
    if (classification.intent === 'TRIP_STATUS' || /^2$/.test(message.trim())) {
        const lastTrip = await getLastTrip(userId);

        if (lastTrip) {
            response.message = lang === 'ar'
                ? `📋 آخر رحلة:\n📍 ${lastTrip.pickup} → ${lastTrip.destination}\n💰 ${lastTrip.estimated_fare} ج.م\n📊 الحالة: ${lastTrip.status}`
                : `📋 Last trip:\n📍 ${lastTrip.pickup} → ${lastTrip.destination}\n💰 ${lastTrip.estimated_fare} EGP\nStatus: ${lastTrip.status}`;
        } else {
            response.message = lang === 'ar'
                ? '📭 مفيش رحلات سابقة. عايز تحجز رحلة جديدة؟'
                : '📭 No previous trips. Would you like to book a ride?';
        }

        response.quick_replies = getDefaultQuickReplies(lang);
        return response;
    }

    // ========== NEW SCENARIO HANDLERS ==========

    // 🎟️ PROMO CODE
    if (classification.intent === 'PROMO_CODE') {
        response.message = lang === 'ar'
            ? '🎟️ عندك كود خصم؟\n\nاكتب الكود وهطبقه على رحلتك الجاية.\n\n💡 مثال: SMART50'
            : '🎟️ Have a promo code?\n\nType the code and I\'ll apply it to your next ride.\n\n💡 Example: SMART50';
        response.action = 'request_promo_code';
        response.quick_replies = lang === 'ar'
            ? ['مفيش كود', 'احجز رحلة']
            : ['No code', 'Book ride'];

        await setConversationState(userId, 'AWAITING_PROMO_CODE', {});
        return response;
    }

    // 📅 SCHEDULE RIDE
    if (classification.intent === 'SCHEDULE_RIDE') {
        response.message = lang === 'ar'
            ? '📅 حجز مسبق\n\nامتى عايز الرحلة؟\n\n• اكتب الوقت (مثال: بكرة الساعة 8 الصبح)\n• أو اختار من القائمة:'
            : '📅 Schedule a ride\n\nWhen do you need the ride?\n\n• Type the time (e.g., tomorrow at 8 AM)\n• Or choose from the list:';
        response.action = 'request_schedule_time';
        response.quick_replies = lang === 'ar'
            ? ['بعد ساعة', 'بكرة الصبح', 'بكرة بالليل', 'اختار تاريخ']
            : ['In 1 hour', 'Tomorrow morning', 'Tomorrow evening', 'Pick date'];

        await setConversationState(userId, 'AWAITING_SCHEDULE_TIME', {});
        return response;
    }

    // 📜 TRIP HISTORY
    if (classification.intent === 'TRIP_HISTORY') {
        const trips = await getTripHistory(userId, 5);

        if (trips && trips.length > 0) {
            let historyMsg = lang === 'ar' ? '📜 آخر رحلاتك:\n\n' : '📜 Your recent trips:\n\n';
            trips.forEach((trip, i) => {
                const date = new Date(trip.created_at).toLocaleDateString(lang === 'ar' ? 'ar-EG' : 'en-US');
                historyMsg += `${i + 1}. ${trip.pickup_address?.split(',')[0] || 'N/A'} → ${trip.destination_address?.split(',')[0] || 'N/A'}\n   📅 ${date} | 💰 ${trip.total_amount || trip.estimated_fare || 'N/A'} ج.م\n\n`;
            });
            response.message = historyMsg;
            response.action = ACTION_TYPES.SHOW_TRIP_HISTORY;
        } else {
            response.message = lang === 'ar'
                ? '📭 مفيش رحلات سابقة.\n\nعايز تحجز رحلة جديدة؟'
                : '📭 No trip history found.\n\nWould you like to book a ride?';
        }

        response.quick_replies = lang === 'ar'
            ? ['احجز رحلة', 'إيصال آخر رحلة']
            : ['Book ride', 'Last trip receipt'];
        return response;
    }

    // 🧾 RECEIPT REQUEST
    if (classification.intent === 'RECEIPT') {
        const lastTrip = await getLastTrip(userId);

        if (lastTrip) {
            response.message = lang === 'ar'
                ? `🧾 إيصال رحلتك:\n\n📋 رقم الرحلة: ${lastTrip.ref_id || lastTrip.id}\n📍 من: ${lastTrip.pickup}\n📍 إلى: ${lastTrip.destination}\n💰 المبلغ: ${lastTrip.total_amount || lastTrip.estimated_fare} ج.م\n📅 التاريخ: ${new Date(lastTrip.created_at).toLocaleDateString('ar-EG')}\n\n✉️ هل تريد إرسال الإيصال لإيميلك؟`
                : `🧾 Trip Receipt:\n\n📋 Trip #${lastTrip.ref_id || lastTrip.id}\n📍 From: ${lastTrip.pickup}\n📍 To: ${lastTrip.destination}\n💰 Amount: ${lastTrip.total_amount || lastTrip.estimated_fare} EGP\n📅 Date: ${new Date(lastTrip.created_at).toLocaleDateString('en-US')}\n\n✉️ Would you like the receipt emailed to you?`;
            response.action = 'show_receipt';
            response.data = { trip_id: lastTrip.id };
            response.quick_replies = lang === 'ar'
                ? ['ابعت لإيميلي', 'لا شكرا']
                : ['Email me', 'No thanks'];
        } else {
            response.message = lang === 'ar'
                ? '📭 مفيش رحلات سابقة عشان نعرض الإيصال.'
                : '📭 No previous trips to show receipt for.';
            response.quick_replies = getDefaultQuickReplies(lang);
        }
        return response;
    }

    // 😤 COMPLAINT
    if (classification.intent === 'COMPLAINT') {
        response.message = lang === 'ar'
            ? '😔 آسفين على أي مشكلة واجهتك.\n\nممكن تقولي إيه اللي حصل؟\n\n• مشكلة مع الكابتن\n• مشكلة في السعر\n• مشكلة في التطبيق\n• حاجة تانية'
            : '😔 Sorry for any issue you experienced.\n\nCan you tell me what happened?\n\n• Issue with driver\n• Pricing issue\n• App problem\n• Something else';
        response.action = 'start_complaint';
        response.quick_replies = lang === 'ar'
            ? ['مشكلة مع الكابتن', 'مشكلة في السعر', 'كلمني موظف']
            : ['Driver issue', 'Pricing issue', 'Talk to agent'];

        await setConversationState(userId, 'AWAITING_COMPLAINT_TYPE', {});
        return response;
    }

    // ⭐ RATE DRIVER
    if (classification.intent === 'RATE_DRIVER') {
        const lastTrip = await getLastTrip(userId);

        if (lastTrip && !lastTrip.rating) {
            response.message = lang === 'ar'
                ? `⭐ قيم رحلتك مع ${lastTrip.driver_name || 'الكابتن'}\n\nمن 1 لـ 5 نجوم، إيه تقييمك؟`
                : `⭐ Rate your trip with ${lastTrip.driver_name || 'your driver'}\n\nFrom 1 to 5 stars, what's your rating?`;
            response.action = ACTION_TYPES.RATE_TRIP;
            response.data = { trip_id: lastTrip.id };
            response.quick_replies = ['⭐', '⭐⭐', '⭐⭐⭐', '⭐⭐⭐⭐', '⭐⭐⭐⭐⭐'];

            await setConversationState(userId, 'AWAITING_RATING', { trip_id: lastTrip.id });
        } else if (lastTrip?.rating) {
            response.message = lang === 'ar'
                ? `✅ أنت قيمت الرحلة دي قبل كده (${lastTrip.rating} نجوم)`
                : `✅ You already rated this trip (${lastTrip.rating} stars)`;
            response.quick_replies = getDefaultQuickReplies(lang);
        } else {
            response.message = lang === 'ar'
                ? '📭 مفيش رحلات محتاجة تقييم.'
                : '📭 No trips to rate.';
            response.quick_replies = getDefaultQuickReplies(lang);
        }
        return response;
    }

    // 💰 WALLET
    if (classification.intent === 'WALLET') {
        const walletBalance = await getWalletBalance(userId);

        response.message = lang === 'ar'
            ? `💰 محفظتك:\n\n💵 الرصيد: ${walletBalance || 0} ج.م\n\n• شحن المحفظة\n• سجل المعاملات`
            : `💰 Your Wallet:\n\n💵 Balance: ${walletBalance || 0} EGP\n\n• Top up wallet\n• Transaction history`;
        response.action = ACTION_TYPES.SHOW_WALLET;
        response.quick_replies = lang === 'ar'
            ? ['شحن المحفظة', 'سجل المعاملات', 'احجز رحلة']
            : ['Top up', 'Transactions', 'Book ride'];
        return response;
    }

    // ⭐ FAVORITE LOCATIONS
    if (classification.intent === 'FAVORITE_LOCATIONS') {
        if (userPrefs.favorites && userPrefs.favorites.length > 0) {
            let favMsg = lang === 'ar' ? '⭐ أماكنك المفضلة:\n\n' : '⭐ Your saved locations:\n\n';
            userPrefs.favorites.forEach((fav, i) => {
                favMsg += `${i + 1}. ${fav.name} - ${fav.address?.split(',')[0] || ''}\n`;
            });
            favMsg += lang === 'ar'
                ? '\n💡 اكتب رقم المكان عشان تحجز منه'
                : '\n💡 Type a number to book from that location';
            response.message = favMsg;
            response.quick_replies = userPrefs.favorites.map((f, i) => `${i + 1}. ${f.name}`);
        } else {
            response.message = lang === 'ar'
                ? '⭐ مفيش أماكن مفضلة محفوظة.\n\nبعد ما تحجز رحلات، هتقدر تحفظ الأماكن المفضلة.'
                : '⭐ No saved locations yet.\n\nAfter booking trips, you can save your favorite places.';
            response.quick_replies = lang === 'ar'
                ? ['احجز رحلة', 'إضافة مكان']
                : ['Book ride', 'Add location'];
        }
        return response;
    }

    // ⏱️ ETA (when no active trip)
    if (classification.intent === 'ETA' && !activeRide) {
        response.message = lang === 'ar'
            ? '🚗 مفيش رحلة نشطة دلوقتي.\n\nعايز تحجز رحلة؟'
            : '🚗 No active trip right now.\n\nWould you like to book a ride?';
        response.quick_replies = getDefaultQuickReplies(lang);
        return response;
    }

    // SAFEGUARD: Check if message contains booking keywords but wasn't classified as BOOK_TRIP
    // This prevents LLM from handling booking requests incorrectly
    const bookingKeywords = [
        /(?:اريد|عايز|محتاج|ابي|أبي)\s*(?:حجز|احجز|رحلة|توصيل)/i,
        /(?:حجز|احجز)\s*(?:رحلة|رحله)/i,
        /\b(book|booking|ride|trip)\b/i
    ];

    const hasBookingIntent = bookingKeywords.some(pattern => pattern.test(message));
    if (hasBookingIntent && classification.intent !== 'BOOK_TRIP') {
        // Force booking flow even if not classified correctly
        logger.warn('Booking intent detected but not classified, forcing booking flow', {
            userId,
            message,
            classifiedIntent: classification.intent
        });

        let msg = lang === 'ar'
            ? '🚗 من فين تحب نوصلك؟\n\n📍 اكتب اسم المكان (مثال: مدينة نصر)'
            : '🚗 Where would you like to be picked up?\n\n📍 Type location name (e.g., Nasr City)';

        if (userPrefs.favorites && userPrefs.favorites.length > 0) {
            msg += lang === 'ar'
                ? `\n\n⭐ أماكنك المفضلة:\n${userPrefs.favorites.map((f, i) => `${i + 1}. ${f.name}`).join('\n')}`
                : `\n\n⭐ Your favorites:\n${userPrefs.favorites.map((f, i) => `${i + 1}. ${f.name}`).join('\n')}`;
        }

        response.message = msg;
        const pickupAction = ActionBuilders.requestPickup();
        response.action = pickupAction.action;
        response.data = pickupAction.data;

        await setConversationState(userId, STATES.AWAITING_PICKUP, {});
        return response;
    }

    // Greeting or unknown - use LLM
    if (classification.intent === 'GREETING' || classification.intent === 'UNKNOWN' || classification.intent === 'FAREWELL') {
        try {
            const systemPrompt = await getSystemPrompt();
            const langInstruction = LanguageManager.getLanguageInstruction(lang);
            const enhancedPrompt = `${systemPrompt}\n\n${langInstruction}`;

            const history = await getChatHistory(userId, 4);
            const messages = [
                { role: 'system', content: enhancedPrompt },
                ...history.map(h => ({ role: h.role, content: h.content })),
                { role: 'user', content: message }
            ];

            response.message = await callLLM(messages, { targetLanguage: lang });
            response.quick_replies = getDefaultQuickReplies(lang);

        } catch (e) {
            logger.error('LLM call failed', { error: e.message, userId });
            response.message = lang === 'ar'
                ? '👋 أهلاً بيك في سمارت لاين!\n\nإزاي أقدر أساعدك النهارده؟'
                : '👋 Welcome to SmartLine!\n\nHow can I help you today?';
            response.quick_replies = getDefaultQuickReplies(lang);
        }

        return response;
    }

    // Default response
    response.message = lang === 'ar' ? 'كيف أقدر أساعدك؟' : 'How can I help you?';
    response.quick_replies = getDefaultQuickReplies(lang);
    return response;
}

async function handleAwaitingPickupState(userId, message, lang, convState, response) {
    if (message.length < 3) {
        response.message = lang === 'ar'
            ? '📍 اكتب اسم موقع الانطلاق (مثال: مدينة نصر، التجمع الخامس)'
            : '📍 Type pickup location name (e.g., Nasr City, Fifth Settlement)';
        return response;
    }

    // SMART DETECTION: Check if user provided both pickup and destination in one message
    // Patterns: "من X ل Y", "من X إلى Y", "from X to Y", "X to Y"
    const bothLocationsPattern = lang === 'ar'
        ? /(?:من|من عند|من عندي|من عندنا)\s+(.+?)\s+(?:ل|الى|إلى|لل|لـ)\s+(.+)/i
        : /(?:from|pickup)\s+(.+?)\s+(?:to|towards|destination)\s+(.+)/i;

    const bothLocationsMatch = message.match(bothLocationsPattern);

    if (bothLocationsMatch) {
        // User provided both locations - extract them
        const pickupText = bothLocationsMatch[1].trim();
        const destinationText = bothLocationsMatch[2].trim();

        logger.info('Detected both pickup and destination in one message', {
            userId,
            pickup: pickupText,
            destination: destinationText
        });

        // Search for pickup location
        const userLat = convState.data.user_lat || 30.0444;
        const userLng = convState.data.user_lng || 31.2357;
        const zoneId = convState.data.zone_id || process.env.DEFAULT_ZONE_ID || '182440b2-da90-11f0-bfad-581122408b4d';

        const pickupSearch = await searchPlaces(pickupText, userLat, userLng, zoneId, lang);
        const destSearch = await searchPlaces(destinationText, userLat, userLng, zoneId, lang);

        if (pickupSearch.success && pickupSearch.predictions.length > 0 &&
            destSearch.success && destSearch.predictions.length > 0) {
            // Both locations found - show pickup options first
            response.message = formatPredictions(pickupSearch.predictions, lang) +
                (lang === 'ar'
                    ? '\n\n✅ تم تحديد الوجهة: ' + destinationText
                    : '\n\n✅ Destination set: ' + destinationText);
            response.action = 'show_location_options';
            response.data = {
                predictions: pickupSearch.predictions,
                type: 'pickup',
                destination_text: destinationText,
                destination_predictions: destSearch.predictions
            };
            response.quick_replies = pickupSearch.predictions.map((_, i) => `${i + 1}`);

            await setConversationState(userId, STATES.AWAITING_PICKUP_SELECTION, {
                ...convState.data,
                pickup_predictions: pickupSearch.predictions,
                pickup_search: pickupText,
                destination_text: destinationText,
                destination_predictions: destSearch.predictions
            });
            return response;
        }
    }

    // Single location search (original behavior)
    const userLat = convState.data.user_lat || 30.0444;
    const userLng = convState.data.user_lng || 31.2357;
    const zoneId = convState.data.zone_id || process.env.DEFAULT_ZONE_ID || '182440b2-da90-11f0-bfad-581122408b4d';

    const searchResult = await searchPlaces(message, userLat, userLng, zoneId, lang);

    if (searchResult.success && searchResult.predictions.length > 0) {
        response.message = formatPredictions(searchResult.predictions, lang);
        response.action = 'show_location_options';
        response.data = { predictions: searchResult.predictions, type: 'pickup' };
        response.quick_replies = searchResult.predictions.map((_, i) => `${i + 1}`);

        await setConversationState(userId, STATES.AWAITING_PICKUP_SELECTION, {
            ...convState.data,
            pickup_predictions: searchResult.predictions,
            pickup_search: message
        });
    } else {
        response.message = lang === 'ar'
            ? '❌ لم يتم العثور على نتائج. جرب اسم تاني أو اكتب العنوان بشكل مختلف:'
            : '❌ No results found. Try a different name or write the address differently:';
    }

    return response;
}

async function handleAwaitingPickupSelectionState(userId, message, lang, convState, response) {
    const pickupIndex = parseInt(message.trim()) - 1;
    const pickupPredictions = convState.data.pickup_predictions || [];

    if (pickupIndex >= 0 && pickupIndex < pickupPredictions.length) {
        const selected = pickupPredictions[pickupIndex];

        // CHECK: If we already have destination from "من X ل Y" pattern, use it automatically
        const destinationPredictions = convState.data.destination_predictions || [];
        const destinationText = convState.data.destination_text;

        if (destinationPredictions.length > 0 && destinationText) {
            // Auto-select first destination prediction (or could show options)
            const destSelected = destinationPredictions[0];

            response.message = lang === 'ar'
                ? `✅ تم اختيار:\n📍 الانطلاق: ${selected.structured_formatting?.main_text || selected.description}\n📍 الوجهة: ${destSelected.structured_formatting?.main_text || destSelected.description}\n\n🚗 اختار نوع العربية:`
                : `✅ Selected:\n📍 Pickup: ${selected.structured_formatting?.main_text || selected.description}\n📍 Destination: ${destSelected.structured_formatting?.main_text || destSelected.description}\n\n🚗 Choose vehicle type:`;

            const vehicleCategories = await getVehicleCategories();
            const vehicleMsg = formatVehicleCategoriesMessage(vehicleCategories, lang);
            response.message += '\n\n' + vehicleMsg;

            await setConversationState(userId, STATES.AWAITING_RIDE_TYPE, {
                ...convState.data,
                pickup: selected,
                pickup_place_id: selected.place_id,
                pickup_lat: selected.geometry?.location?.lat,
                pickup_lng: selected.geometry?.location?.lng,
                pickup_address: selected.description,
                destination: destSelected,
                destination_place_id: destSelected.place_id,
                destination_lat: destSelected.geometry?.location?.lat,
                destination_lng: destSelected.geometry?.location?.lng,
                destination_address: destSelected.description
            });

            response.action = 'show_ride_options';
            response.data = {
                pickup: selected,
                destination: destSelected,
                vehicle_categories: vehicleCategories
            };
            response.quick_replies = vehicleCategories.map((cat, i) => `${i + 1}. ${cat.name}`);

            return response;
        }

        // No destination stored - ask for it (original behavior)
        response.message = lang === 'ar'
            ? `✅ تم اختيار: ${selected.structured_formatting?.main_text || selected.description}\n\n📍 إلى أين تريد الذهاب؟ (اكتب اسم الوجهة)`
            : `✅ Selected: ${selected.structured_formatting?.main_text || selected.description}\n\n📍 Where to? (Type destination name)`;

        await setConversationState(userId, STATES.AWAITING_DESTINATION, {
            ...convState.data,
            pickup: selected,
            pickup_place_id: selected.place_id,
            pickup_lat: selected.geometry?.location?.lat,
            pickup_lng: selected.geometry?.location?.lng,
            pickup_address: selected.description
        });
    } else {
        response.message = lang === 'ar'
            ? '❌ خيار غير صحيح. اختر رقم من القائمة:'
            : '❌ Invalid option. Choose a number from the list:';
        response.message += '\n\n' + formatPredictions(pickupPredictions, lang);
        response.quick_replies = pickupPredictions.map((_, i) => `${i + 1}`);
    }

    return response;
}

async function handleAwaitingDestinationState(userId, message, lang, convState, response) {
    if (message.length < 3) {
        response.message = lang === 'ar'
            ? '📍 اكتب اسم الوجهة (مثال: التجمع الخامس، مصر الجديدة)'
            : '📍 Type destination name (e.g., Fifth Settlement, Heliopolis)';
        return response;
    }

    const userLat = convState.data.pickup_lat || 30.0444;
    const userLng = convState.data.pickup_lng || 31.2357;
    const zoneId = convState.data.zone_id || process.env.DEFAULT_ZONE_ID || '182440b2-da90-11f0-bfad-581122408b4d';

    const searchResult = await searchPlaces(message, userLat, userLng, zoneId, lang);

    if (searchResult.success && searchResult.predictions.length > 0) {
        response.message = formatPredictions(searchResult.predictions, lang);
        response.action = 'show_location_options';
        response.data = { predictions: searchResult.predictions, type: 'destination' };
        response.quick_replies = searchResult.predictions.map((_, i) => `${i + 1}`);

        await setConversationState(userId, STATES.AWAITING_DESTINATION_SELECTION, {
            ...convState.data,
            destination_predictions: searchResult.predictions,
            destination_search: message
        });
    } else {
        response.message = lang === 'ar'
            ? '❌ لم يتم العثور على نتائج. جرب اسم تاني:'
            : '❌ No results found. Try a different name:';
    }

    return response;
}

async function handleAwaitingDestinationSelectionState(userId, message, lang, convState, response) {
    const destIndex = parseInt(message.trim()) - 1;
    const destPredictions = convState.data.destination_predictions || [];

    if (destIndex >= 0 && destIndex < destPredictions.length) {
        const selected = destPredictions[destIndex];
        const categories = await getVehicleCategories();

        response.message = lang === 'ar'
            ? `✅ تم اختيار: ${selected.structured_formatting?.main_text || selected.description}\n\n${formatVehicleCategoriesMessage(categories, lang)}`
            : `✅ Selected: ${selected.structured_formatting?.main_text || selected.description}\n\n${formatVehicleCategoriesMessage(categories, lang)}`;

        response.action = 'show_ride_options';
        response.data = { categories };
        response.quick_replies = categories.map(c => c.name);

        await setConversationState(userId, STATES.AWAITING_RIDE_TYPE, {
            ...convState.data,
            destination: selected,
            destination_place_id: selected.place_id,
            destination_lat: selected.geometry?.location?.lat,
            destination_lng: selected.geometry?.location?.lng,
            destination_address: selected.description,
            vehicle_categories: categories
        });
    } else {
        response.message = lang === 'ar'
            ? '❌ خيار غير صحيح. اختر رقم من القائمة:'
            : '❌ Invalid option. Choose a number from the list:';
        response.message += '\n\n' + formatPredictions(destPredictions, lang);
        response.quick_replies = destPredictions.map((_, i) => `${i + 1}`);
    }

    return response;
}

async function handleAwaitingRideTypeState(userId, message, lang, convState, response) {
    const categories = convState.data.vehicle_categories || await getVehicleCategories();
    let selectedCat = categories[0];

    // Find selected category
    for (let i = 0; i < categories.length; i++) {
        if (message.includes(String(i + 1)) ||
            message.toLowerCase().includes(categories[i].name.toLowerCase())) {
            selectedCat = categories[i];
            break;
        }
    }

    const pickupName = (convState.data.pickup_address || 'نقطة الانطلاق').split(',')[0];
    const destName = (convState.data.destination_address || 'الوجهة').split(',')[0];

    response.message = lang === 'ar'
        ? `📋 تأكيد الحجز:\n\n📍 من: ${pickupName}\n📍 إلى: ${destName}\n🚗 نوع السيارة: ${selectedCat.name}\n\n✅ هل تريد تأكيد الحجز؟`
        : `📋 Confirm booking:\n\n📍 From: ${pickupName}\n📍 To: ${destName}\n🚗 Vehicle: ${selectedCat.name}\n\n✅ Confirm booking?`;

    response.quick_replies = lang === 'ar'
        ? ['✅ تأكيد الحجز', '❌ إلغاء']
        : ['✅ Confirm', '❌ Cancel'];

    await setConversationState(userId, STATES.AWAITING_CONFIRMATION, {
        ...convState.data,
        ride_type: selectedCat.id,
        ride_type_name: selectedCat.name
    });

    return response;
}

async function handleAwaitingConfirmationState(userId, message, lang, convState, response) {
    const confirmPatterns = /\b(تأكيد|نعم|اه|أيوه|موافق|confirm|yes|ok|okay)\b/i;
    const cancelPatterns = /\b(لا|إلغاء|الغاء|مش عايز|cancel|no)\b/i;

    if (confirmPatterns.test(message)) {
        // Create the trip
        const tripResult = await createTrip({
            customer_id: userId,
            pickup: {
                lat: convState.data.pickup_lat,
                lng: convState.data.pickup_lng,
                address: convState.data.pickup_address
            },
            destination: {
                lat: convState.data.destination_lat,
                lng: convState.data.destination_lng,
                address: convState.data.destination_address
            },
            ride_type: convState.data.ride_type,
            ride_type_name: convState.data.ride_type_name,
            pickup_address: convState.data.pickup_address,
            destination_address: convState.data.destination_address
        });

        if (tripResult.success) {
            const pickupShort = (convState.data.pickup_address || '').split(',')[0];
            const destShort = (convState.data.destination_address || '').split(',')[0];

            response.message = lang === 'ar'
                ? `🎉 تم تأكيد الحجز!\n\n📋 رقم الرحلة: ${tripResult.ref_id}\n💰 السعر المتوقع: ${tripResult.estimated_fare} ج.م\n📍 من: ${pickupShort}\n📍 إلى: ${destShort}\n\n🔍 جاري البحث عن كابتن...`
                : `🎉 Booking confirmed!\n\n📋 Trip #${tripResult.ref_id}\n💰 Estimated fare: ${tripResult.estimated_fare} EGP\n📍 From: ${pickupShort}\n📍 To: ${destShort}\n\n🔍 Searching for driver...`;

            const confirmAction = ActionBuilders.confirmBooking({
                ...convState.data,
                trip_id: tripResult.trip_id,
                ref_id: tripResult.ref_id,
                estimated_fare: tripResult.estimated_fare
            });

            response.action = confirmAction.action;
            response.data = {
                ...confirmAction.data,
                trip_id: tripResult.trip_id,
                ref_id: tripResult.ref_id,
                estimated_fare: tripResult.estimated_fare
            };
            response.ui_hint = confirmAction.ui_hint;
            response.quick_replies = lang === 'ar'
                ? ['أين الكابتن؟', 'إلغاء الرحلة']
                : ['Where\'s driver?', 'Cancel trip'];

            await setConversationState(userId, STATES.TRIP_ACTIVE, {
                ...convState.data,
                trip_id: tripResult.trip_id,
                ref_id: tripResult.ref_id
            });
        } else {
            response.message = lang === 'ar'
                ? '❌ عذراً، حدث خطأ أثناء إنشاء الرحلة. حاول مرة أخرى.'
                : '❌ Sorry, an error occurred while creating the trip. Please try again.';
            response.quick_replies = lang === 'ar'
                ? ['حاول مرة أخرى', 'مساعدة']
                : ['Try again', 'Help'];

            logger.error('Trip creation failed', { error: tripResult.error, userId });
        }
    } else if (cancelPatterns.test(message)) {
        response.message = lang === 'ar'
            ? '❌ تم إلغاء الحجز. كيف أقدر أساعدك؟'
            : '❌ Booking cancelled. How can I help?';
        response.quick_replies = getDefaultQuickReplies(lang);
        await setConversationState(userId, STATES.START, {});
    } else {
        response.message = lang === 'ar'
            ? '🤔 مش فاهم. عايز تأكد الحجز ولا تلغي؟'
            : '🤔 Not sure I understand. Would you like to confirm or cancel?';
        response.quick_replies = lang === 'ar'
            ? ['✅ تأكيد', '❌ إلغاء']
            : ['✅ Confirm', '❌ Cancel'];
    }

    return response;
}

async function handleTripActiveState(userId, message, lang, classification, convState, activeRide, response) {
    // Check if trip is still active
    if (!activeRide) {
        await setConversationState(userId, STATES.START, {});
        response.message = lang === 'ar'
            ? '✅ الرحلة السابقة انتهت. كيف أقدر أساعدك؟'
            : '✅ Previous trip ended. How can I help you?';
        response.quick_replies = getDefaultQuickReplies(lang);
        return response;
    }

    // Cancel request
    if (classification.intent === 'CANCEL_TRIP' || /\b(إلغاء|الغاء|cancel)\b/i.test(message)) {
        response.message = lang === 'ar'
            ? '⚠️ هل أنت متأكد من إلغاء الرحلة؟'
            : '⚠️ Are you sure you want to cancel?';

        const cancelAction = ActionBuilders.confirmCancelTrip(activeRide.id, 5);
        response.action = cancelAction.action;
        response.data = cancelAction.data;
        response.quick_replies = lang === 'ar'
            ? ['نعم، إلغاء', 'لا، استمرار']
            : ['Yes, cancel', 'No, continue'];

        await setConversationState(userId, STATES.AWAITING_CANCEL_CONFIRM, { trip_id: activeRide.id });
        return response;
    }

    // Contact driver
    if (classification.intent === 'CONTACT_DRIVER' || /\b(اتصل|كلم|call|contact)\b/i.test(message)) {
        response.message = lang === 'ar'
            ? '📞 جاري الاتصال بالكابتن...'
            : '📞 Connecting to driver...';

        const contactAction = ActionBuilders.contactDriver(activeRide.id, activeRide.driver_phone);
        response.action = contactAction.action;
        response.data = contactAction.data;
        return response;
    }

    // CHANGE DESTINATION (mid-trip)
    if (classification.intent === 'CHANGE_DESTINATION') {
        response.message = lang === 'ar'
            ? '📍 عايز تغير الوجهة؟\n\nاكتب العنوان الجديد:'
            : '📍 Want to change destination?\n\nType the new address:';
        response.action = 'request_new_destination';

        await setConversationState(userId, 'AWAITING_NEW_DESTINATION', {
            trip_id: activeRide.id,
            original_destination: activeRide.destination
        });
        return response;
    }

    // ADD STOP (mid-trip)
    if (classification.intent === 'ADD_STOP') {
        response.message = lang === 'ar'
            ? '📍 عايز تضيف وقفة؟\n\nاكتب عنوان الوقفة:\n\n💡 ملحوظة: ممكن يتم إضافة رسوم إضافية'
            : '📍 Want to add a stop?\n\nType the stop address:\n\n💡 Note: Additional fees may apply';
        response.action = 'request_stop_location';

        await setConversationState(userId, 'AWAITING_STOP_LOCATION', {
            trip_id: activeRide.id
        });
        return response;
    }

    // ETA request (during active trip)
    if (classification.intent === 'ETA' || /\b(فاضل كام|متى|how long|eta)\b/i.test(message)) {
        const eta = activeRide.eta_minutes || 'غير محدد';
        response.message = lang === 'ar'
            ? `⏱️ الكابتن هيوصل في حوالي ${eta} دقيقة`
            : `⏱️ Driver will arrive in about ${eta} minutes`;
        response.quick_replies = lang === 'ar'
            ? ['اتصل بالكابتن', 'إلغاء الرحلة']
            : ['Call driver', 'Cancel trip'];
        return response;
    }

    // Default - show tracking
    response.message = lang === 'ar'
        ? `🚗 رحلتك الحالية:\n👨‍✈️ ${activeRide.driver_name}\n📊 الحالة: ${activeRide.status === 'pending' ? 'جاري البحث عن كابتن' : 'في الطريق إليك'}`
        : `🚗 Your current trip:\n👨‍✈️ ${activeRide.driver_name}\nStatus: ${activeRide.status === 'pending' ? 'Finding driver' : 'On the way'}`;

    const trackingAction = ActionBuilders.showTripTracking(activeRide.id);
    response.action = trackingAction.action;
    response.data = { ...trackingAction.data, ride: activeRide };
    response.quick_replies = lang === 'ar'
        ? ['إلغاء الرحلة', 'اتصل بالكابتن']
        : ['Cancel trip', 'Call driver'];

    return response;
}

async function handleAwaitingCancelConfirmState(userId, message, lang, convState, response) {
    // FIXED: Removed "confirm" from confirmPatterns - it was causing "confirm trip" to cancel the trip!
    // In this state, we're asking "Are you sure you want to cancel?"
    // - If user says YES/إلغاء → Cancel the trip
    // - If user says NO/استمرار → Keep the trip
    const confirmPatterns = /\b(نعم|اه|أيوه|yes)\b/i;
    const cancelPatterns = /\b(لا|استمرار|no|continue|back|keep|مش عايز|don't)\b/i;

    if (confirmPatterns.test(message)) {
        const cancelResult = await cancelTrip(convState.data.trip_id);

        response.message = cancelResult.success
            ? (lang === 'ar' ? '❌ تم إلغاء الرحلة بنجاح.' : '❌ Trip cancelled successfully.')
            : (lang === 'ar' ? '❌ تم إلغاء الرحلة.' : '❌ Trip cancelled.');

        response.action = ACTION_TYPES.CANCEL_TRIP;
        response.data = { trip_id: convState.data.trip_id };
        response.quick_replies = lang === 'ar'
            ? ['حجز رحلة جديدة']
            : ['Book new trip'];

        await setConversationState(userId, STATES.START, {});
    } else if (cancelPatterns.test(message)) {
        response.message = lang === 'ar'
            ? '✅ تمام، رحلتك مستمرة.'
            : '✅ Great, your trip continues.';
        response.quick_replies = lang === 'ar'
            ? ['أين الكابتن؟', 'اتصل بالكابتن']
            : ['Where\'s driver?', 'Call driver'];

        await setConversationState(userId, STATES.TRIP_ACTIVE, convState.data);
    } else {
        response.message = lang === 'ar'
            ? '🤔 عايز تلغي الرحلة ولا تستمر؟'
            : '🤔 Would you like to cancel or continue?';
        response.quick_replies = lang === 'ar'
            ? ['نعم، إلغاء', 'لا، استمرار']
            : ['Yes, cancel', 'No, continue'];
    }

    return response;
}

// ============================================
// 🆕 NEW STATE HANDLERS
// ============================================

/**
 * Handle promo code input
 */
async function handleAwaitingPromoCodeState(userId, message, lang, convState, response) {
    // Check if user wants to skip
    if (/\b(مفيش|لا|skip|no code|cancel)\b/i.test(message)) {
        response.message = lang === 'ar'
            ? '👍 تمام! عايز تحجز رحلة؟'
            : '👍 Okay! Would you like to book a ride?';
        response.quick_replies = getDefaultQuickReplies(lang);
        await setConversationState(userId, STATES.START, {});
        return response;
    }

    // Try to apply the promo code
    const promoResult = await applyPromoCode(userId, message.trim().toUpperCase());

    if (promoResult.success) {
        const discountText = promoResult.discount_type === 'percentage'
            ? `${promoResult.discount}%`
            : `${promoResult.discount} ج.م`;

        response.message = lang === 'ar'
            ? `🎉 تم تطبيق الكود بنجاح!\n\n💰 الخصم: ${discountText}\n\nهيتطبق على رحلتك الجاية. عايز تحجز دلوقتي؟`
            : `🎉 Code applied successfully!\n\n💰 Discount: ${discountText}\n\nWill be applied to your next ride. Book now?`;
        response.quick_replies = lang === 'ar'
            ? ['احجز رحلة', 'لاحقاً']
            : ['Book ride', 'Later'];

        await setConversationState(userId, STATES.START, { promo_id: promoResult.promo_id, discount: promoResult.discount });
    } else {
        const errorMsg = {
            invalid_code: lang === 'ar' ? 'الكود غير صحيح أو منتهي الصلاحية' : 'Invalid or expired code',
            already_used: lang === 'ar' ? 'استخدمت الكود ده قبل كده' : 'You already used this code',
            system_error: lang === 'ar' ? 'حدث خطأ، حاول تاني' : 'An error occurred, try again'
        };

        response.message = lang === 'ar'
            ? `❌ ${errorMsg[promoResult.error] || errorMsg.invalid_code}\n\nجرب كود تاني أو اختار:`
            : `❌ ${errorMsg[promoResult.error] || errorMsg.invalid_code}\n\nTry another code or choose:`;
        response.quick_replies = lang === 'ar'
            ? ['جرب كود تاني', 'مفيش كود', 'احجز بدون كود']
            : ['Try another', 'No code', 'Book without code'];
    }

    return response;
}

/**
 * Handle schedule time input
 */
async function handleAwaitingScheduleTimeState(userId, message, lang, convState, response) {
    // Parse time from message
    let scheduledTime = null;
    const now = new Date();

    // Quick reply handlers
    if (/\b(بعد ساعة|in 1 hour|1 hour)\b/i.test(message)) {
        scheduledTime = new Date(now.getTime() + 60 * 60 * 1000);
    } else if (/\b(بكرة الصبح|tomorrow morning)\b/i.test(message)) {
        scheduledTime = new Date(now);
        scheduledTime.setDate(scheduledTime.getDate() + 1);
        scheduledTime.setHours(8, 0, 0, 0);
    } else if (/\b(بكرة بالليل|tomorrow evening)\b/i.test(message)) {
        scheduledTime = new Date(now);
        scheduledTime.setDate(scheduledTime.getDate() + 1);
        scheduledTime.setHours(18, 0, 0, 0);
    } else if (/\b(اختار تاريخ|pick date)\b/i.test(message)) {
        response.message = lang === 'ar'
            ? '📅 اكتب التاريخ والوقت\n\nمثال: 15/1 الساعة 3 العصر'
            : '📅 Type the date and time\n\nExample: 15/1 at 3 PM';
        return response;
    } else {
        // Try to parse custom time
        const timeMatch = message.match(/(\d{1,2})(?::(\d{2}))?\s*(صباحا|مساء|ص|م|am|pm)?/i);
        if (timeMatch) {
            scheduledTime = new Date(now);
            let hours = parseInt(timeMatch[1]);
            const minutes = parseInt(timeMatch[2]) || 0;
            const period = timeMatch[3]?.toLowerCase();

            if (period && (period.includes('م') || period.includes('pm') || period.includes('مساء'))) {
                if (hours < 12) hours += 12;
            }

            scheduledTime.setHours(hours, minutes, 0, 0);
            if (scheduledTime <= now) {
                scheduledTime.setDate(scheduledTime.getDate() + 1);
            }
        }
    }

    if (scheduledTime) {
        const formattedTime = scheduledTime.toLocaleString(lang === 'ar' ? 'ar-EG' : 'en-US', {
            weekday: 'long',
            hour: 'numeric',
            minute: '2-digit'
        });

        response.message = lang === 'ar'
            ? `📅 تم جدولة الرحلة:\n⏰ ${formattedTime}\n\nمن فين عايز تتحرك؟`
            : `📅 Ride scheduled for:\n⏰ ${formattedTime}\n\nWhere would you like to be picked up?`;

        await setConversationState(userId, STATES.AWAITING_PICKUP, {
            scheduled_time: scheduledTime.toISOString(),
            is_scheduled: true
        });
    } else {
        response.message = lang === 'ar'
            ? '🤔 مش فاهم الوقت. اختار من القائمة أو اكتب الوقت (مثال: 3 العصر)'
            : '🤔 I didn\'t understand the time. Choose from the list or type (e.g., 3 PM)';
        response.quick_replies = lang === 'ar'
            ? ['بعد ساعة', 'بكرة الصبح', 'بكرة بالليل']
            : ['In 1 hour', 'Tomorrow morning', 'Tomorrow evening'];
    }

    return response;
}

/**
 * Handle complaint type selection
 */
async function handleAwaitingComplaintTypeState(userId, message, lang, convState, response) {
    let complaintType = 'other';

    if (/\b(كابتن|سواق|driver)\b/i.test(message)) {
        complaintType = 'driver';
        response.message = lang === 'ar'
            ? '😔 آسفين على تجربتك مع الكابتن.\n\nممكن توصف إيه اللي حصل بالتفصيل؟'
            : '😔 Sorry about your driver experience.\n\nCan you describe what happened?';
    } else if (/\b(سعر|فلوس|price|pricing|fare)\b/i.test(message)) {
        complaintType = 'pricing';
        response.message = lang === 'ar'
            ? '💰 فاهمين قلقك بخصوص السعر.\n\nإيه المشكلة بالظبط؟ (مثال: السعر كان أعلى من المتوقع)'
            : '💰 We understand your pricing concern.\n\nWhat exactly was the issue? (e.g., price was higher than expected)';
    } else if (/\b(موظف|agent|كلمني)\b/i.test(message)) {
        response.message = lang === 'ar'
            ? '🎧 جاري تحويلك لفريق الدعم...'
            : '🎧 Connecting you to support team...';
        response.action = ACTION_TYPES.CONNECT_SUPPORT;
        await setConversationState(userId, STATES.START, {});
        return response;
    } else {
        complaintType = 'other';
        response.message = lang === 'ar'
            ? '📝 من فضلك اكتب تفاصيل المشكلة:'
            : '📝 Please describe the issue:';
    }

    response.quick_replies = lang === 'ar'
        ? ['كلمني موظف']
        : ['Talk to agent'];

    await setConversationState(userId, 'AWAITING_COMPLAINT_DETAILS', {
        complaint_type: complaintType
    });

    return response;
}

/**
 * Handle complaint details input
 */
async function handleAwaitingComplaintDetailsState(userId, message, lang, convState, response) {
    if (message.length < 10) {
        response.message = lang === 'ar'
            ? '📝 من فضلك اكتب تفاصيل أكتر عشان نقدر نساعدك'
            : '📝 Please provide more details so we can help you';
        return response;
    }

    // Get last trip for complaint
    const lastTrip = await getLastTrip(userId);
    const tripId = lastTrip?.id || null;

    // Submit complaint
    const result = await submitComplaint(
        userId,
        tripId,
        convState.data.complaint_type,
        message
    );

    if (result.success) {
        response.message = lang === 'ar'
            ? `✅ تم استلام شكواك.\n\n📋 رقم الشكوى: ${result.complaint_id?.slice(0, 8)}\n\nفريق الدعم هيتواصل معاك قريباً.`
            : `✅ Complaint received.\n\n📋 Reference: ${result.complaint_id?.slice(0, 8)}\n\nOur support team will contact you soon.`;
    } else {
        response.message = lang === 'ar'
            ? '❌ حدث خطأ. من فضلك تواصل مع الدعم مباشرة.'
            : '❌ An error occurred. Please contact support directly.';
    }

    response.quick_replies = getDefaultQuickReplies(lang);
    await setConversationState(userId, STATES.START, {});

    return response;
}

/**
 * Handle rating input
 */
async function handleAwaitingRatingState(userId, message, lang, convState, response) {
    // Parse rating (1-5 stars or star emojis)
    let rating = 0;

    const starCount = (message.match(/⭐/g) || []).length;
    if (starCount > 0) {
        rating = Math.min(starCount, 5);
    } else {
        const numMatch = message.match(/[1-5]/);
        if (numMatch) {
            rating = parseInt(numMatch[0]);
        }
    }

    if (rating >= 1 && rating <= 5) {
        const result = await submitRating(userId, convState.data.trip_id, rating, null);

        if (result.success) {
            const thankYouMsg = rating >= 4
                ? (lang === 'ar' ? '🎉 شكراً! سعداء إنك استمتعت برحلتك.' : '🎉 Thanks! Glad you enjoyed your ride.')
                : (lang === 'ar' ? '🙏 شكراً على تقييمك. هنحاول نحسن خدمتنا.' : '🙏 Thanks for your feedback. We\'ll work to improve.');

            response.message = thankYouMsg;

            // Ask for feedback if rating is low
            if (rating <= 3) {
                response.message += lang === 'ar'
                    ? '\n\nعايز تقولنا إيه اللي ممكن نحسنه؟'
                    : '\n\nWould you like to tell us what we can improve?';
                response.quick_replies = lang === 'ar'
                    ? ['مشكلة مع الكابتن', 'مشكلة في السعر', 'لا شكراً']
                    : ['Driver issue', 'Pricing issue', 'No thanks'];
            } else {
                response.quick_replies = getDefaultQuickReplies(lang);
            }
        } else {
            response.message = lang === 'ar'
                ? '❌ حدث خطأ. حاول تاني.'
                : '❌ An error occurred. Try again.';
            response.quick_replies = ['⭐', '⭐⭐', '⭐⭐⭐', '⭐⭐⭐⭐', '⭐⭐⭐⭐⭐'];
            return response;
        }
    } else {
        response.message = lang === 'ar'
            ? '⭐ من فضلك اختار تقييم من 1 لـ 5 نجوم'
            : '⭐ Please choose a rating from 1 to 5 stars';
        response.quick_replies = ['⭐', '⭐⭐', '⭐⭐⭐', '⭐⭐⭐⭐', '⭐⭐⭐⭐⭐'];
        return response;
    }

    await setConversationState(userId, STATES.START, {});
    return response;
}

/**
 * Get default quick replies based on language
 * V3.4.1: Uses centralized quick replies
 */
function getDefaultQuickReplies(lang) {
    return getQuickReplies('MAIN_MENU', lang);
}

// ============================================
// 🚀 MAIN CHAT ENDPOINT
// ============================================

app.post('/chat',
    burstLimiter,
    chatRateLimiter,
    [
        body('user_id').trim().notEmpty().withMessage('user_id is required').isLength({ max: 100 }),
        body('message').trim().notEmpty().withMessage('message is required').isLength({ max: 500 })
    ],
    async (req, res) => {
        const requestStart = Date.now();
        const requestId = req.requestId;

        try {
            const errors = validationResult(req);
            if (!errors.isEmpty()) {
                return res.status(400).json({
                    message: 'Invalid request. Please provide user_id and message.',
                    message_ar: 'طلب غير صالح. من فضلك أدخل user_id و message.',
                    action: ACTION_TYPES.NONE,
                    errors: errors.array()
                });
            }

            const { user_id, message, location_data } = req.body;

            // 1. Language detection
            const userPrefs = await getUserPreferences(user_id);
            const langResult = await LanguageManager.determineTargetLanguage(user_id, message, userPrefs);
            const lang = langResult.targetLang;

            // 2. Check for repeated message
            if (isRepeatedMessage(user_id, message)) {
                const responseTime = Date.now() - requestStart;
                updateMetrics(responseTime, true);

                return res.json({
                    message: lang === 'ar'
                        ? 'استلمت رسالتك. في حاجة تانية أقدر أساعدك فيها؟'
                        : 'Got your message. Anything else I can help with?',
                    action: ACTION_TYPES.NONE,
                    repeated: true,
                    language: { primary: lang }
                });
            }

            // 3. Security: Check for prompt injection attempts
            const injectionCheck = checkPromptInjection(message);
            if (injectionCheck.isInjection) {
                logSecurityEvent('prompt_injection_blocked', {
                    userId: user_id,
                    pattern: injectionCheck.pattern,
                    requestId
                });

                return res.json({
                    message: lang === 'ar'
                        ? '⚠️ عذراً، لا أستطيع معالجة هذه الرسالة.\n\nأقدر أساعدك في حجز رحلة أو تتبعها؟'
                        : '⚠️ Sorry, I cannot process this message.\n\nCan I help you book or track a ride?',
                    action: ACTION_TYPES.NONE,
                    quick_replies: getDefaultQuickReplies(lang),
                    security: { blocked: true, reason: 'invalid_input' },
                    language: { primary: lang }
                });
            }

            // 4. Content moderation (profanity)
            const profanityResult = checkProfanity(message);

            // Collect training data for ML moderation (Phase 1)
            if (isFeatureEnabled('ML_MODERATION', user_id)) {
                const mlConfig = require('./utils/featureFlags').getFeatureConfig('ML_MODERATION');
                if (mlConfig?.logOnly) {
                    // Collect data but don't block
                    mlModerationCollector.collectTrainingData(
                        message,
                        profanityResult,
                        user_id,
                        dbExecute,
                        async (uid) => {
                            const prefs = await getUserPreferences(uid);
                            return {
                                userType: getUserType(uid),
                                preferredLanguage: prefs.preferred_language
                            };
                        }
                    ).catch(e => console.warn('[MLModeration] Collection failed:', e.message));
                }
            }

            if (profanityResult.flagged && profanityResult.severity !== SEVERITY.CLEAN) {
                logSecurityEvent('moderation_blocked', {
                    userId: user_id,
                    severity: profanityResult.severity,
                    requestId
                });

                const escReply = escalationReply(lang, profanityResult.severity);

                return res.json({
                    message: escReply.message,
                    action: escReply.action === 'escalate' ? ACTION_TYPES.CONNECT_SUPPORT : ACTION_TYPES.NONE,
                    handoff: escReply.requiresHumanReview,
                    moderation: { flagged: true, severity: escReply.severity },
                    language: { primary: lang }
                });
            }

            // 4. User type detection with database verification for captains
            let userType = getUserType(user_id);
            const detectedType = detectUserType(message, userType);

            // If detected as captain, verify from database
            if (detectedType === 'captain' || userType === 'captain') {
                try {
                    const captainVerification = await verifyCaptainAccess(user_id, dbQuery);
                    if (captainVerification.verified) {
                        userType = 'captain';
                        if (!getUserType(user_id)) {
                            setUserType(user_id, 'captain');
                        }
                    } else {
                        // Not a captain in database - treat as customer
                        if (detectedType === 'captain') {
                            logSecurityEvent('captain_access_denied', {
                                userId: user_id,
                                reason: captainVerification.reason
                            });
                        }
                        userType = 'customer';
                    }
                } catch (error) {
                    logger.error('Captain verification error', { error: error.message, userId: user_id });
                    // On error, default to customer
                    userType = 'customer';
                }
            } else if (detectedType && !userType) {
                setUserType(user_id, detectedType);
                userType = detectedType;
            } else if (!userType) {
                // Default to customer if no type detected
                userType = 'customer';
            }

            // 5. Handle location data
            if (location_data?.lat && location_data?.lng) {
                const convState = await getConversationState(user_id);
                await setConversationState(user_id, convState.state, {
                    ...convState.data,
                    user_lat: location_data.lat,
                    user_lng: location_data.lng,
                    zone_id: location_data.zone_id || req.headers.zoneid || process.env.DEFAULT_ZONE_ID
                });
            }

            // 6. Process conversation (with language enforcement if enabled)
            let response;
            try {
                response = await processConversation(user_id, message, lang, userType, langResult);

                // Ensure response has all required fields
                if (!response) {
                    throw new Error('processConversation returned null/undefined');
                }

                // Ensure required fields exist
                response.message = response.message || (lang === 'ar' ? 'كيف أقدر أساعدك؟' : 'How can I help you?');
                response.action = response.action || ACTION_TYPES.NONE;
                response.data = response.data || {};
                response.quick_replies = response.quick_replies || [];
                response.language = response.language || lang;
                response.userType = response.userType || userType;
                response.confidence = response.confidence || 0.5;
                response.handoff = response.handoff || false;
            } catch (error) {
                logger.error('processConversation error', {
                    error: error.message,
                    stack: error.stack,
                    userId: user_id
                });

                // Fallback response
                response = {
                    message: lang === 'ar'
                        ? 'عذراً، حدث خطأ. حاول مرة أخرى.'
                        : 'Sorry, an error occurred. Please try again.',
                    action: ACTION_TYPES.NONE,
                    data: {},
                    quick_replies: getDefaultQuickReplies(lang),
                    language: lang,
                    userType: userType,
                    confidence: 0,
                    handoff: false
                };
            }

            // 6.5. Language enforcement (if enabled)
            const enforceLanguage = isFeatureEnabled('LANGUAGE_ENFORCEMENT', user_id);
            if (enforceLanguage && response.message) {
                try {
                    const validation = LanguageManager.validateResponseLanguage(response.message, lang);
                    if (!validation.valid) {
                        // Response doesn't match target language - apply enforcement
                        const enforcement = await LanguageManager.enforceResponseLanguage(
                            response.message,
                            lang,
                            {
                                regenerateFn: async () => {
                                    // Re-generate with stronger prompt
                                    const systemPrompt = await getSystemPrompt();
                                    const langInstruction = LanguageManager.getLanguageInstruction(lang);
                                    const enhancedPrompt = `${systemPrompt}\n\n${langInstruction}`;

                                    const history = await getChatHistory(user_id, 4);
                                    const messages = [
                                        { role: 'system', content: enhancedPrompt },
                                        ...history.map(h => ({ role: h.role, content: h.content })),
                                        { role: 'user', content: message }
                                    ];

                                    return await callLLM(messages, { maxTokens: 300 });
                                },
                                fallbackFn: async (targetLang) => {
                                    // Pre-written fallback messages
                                    const fallbacks = {
                                        en: "I apologize, but I'm having trouble responding in the correct language. How can I help you?",
                                        ar: "عذراً، واجهت مشكلة في الرد باللغة الصحيحة. كيف أقدر أساعدك؟"
                                    };
                                    return fallbacks[targetLang] || fallbacks.en;
                                }
                            }
                        );

                        if (enforcement.success && enforcement.method !== 'none') {
                            response.message = enforcement.text;
                            response.languageEnforced = true;
                            response.enforcementMethod = enforcement.method;
                        }
                    }
                } catch (error) {
                    logger.warn('Language enforcement failed', {
                        error: error.message,
                        userId: user_id
                    });
                    // Continue with original response
                }
            }

            // 7. Calculate response time
            const responseTime = Date.now() - requestStart;
            updateMetrics(responseTime, true);

            // 8. Save to history
            await saveChat(user_id, 'user', message, null, null, {
                language: lang,
                isArabizi: langResult.isArabizi
            });
            await saveChat(user_id, 'assistant', response.message, response.action, response.data, {
                language: lang,
                confidence: response.confidence,
                responseTime
            });

            // 9. Send response
            res.json({
                message: response.message,
                action: response.action,
                data: response.data,
                quick_replies: response.quick_replies || [],
                ui_hint: response.ui_hint,
                confidence: response.confidence,
                handoff: response.handoff,
                language: {
                    primary: lang,
                    isArabizi: langResult.isArabizi,
                    rtl: lang === 'ar'
                },
                userType: response.userType,
                model: 'Llama 3.3 70B',
                _debug: process.env.NODE_ENV !== 'production' ? {
                    requestId,
                    responseTime: `${responseTime}ms`
                } : undefined
            });

        } catch (error) {
            const responseTime = Date.now() - requestStart;
            updateMetrics(responseTime, false);

            logError(error, {
                endpoint: '/chat',
                userId: req.body?.user_id,
                requestId
            });

            res.status(500).json({
                message: 'عذراً، حدث خطأ. حاول مرة تانية.',
                message_en: 'Sorry, an error occurred. Please try again.',
                action: ACTION_TYPES.NONE,
                handoff: true
            });
        }
    }
);

// ============================================
// 📍 LOCATION SUBMISSION ENDPOINT
// ============================================

app.post('/submit-location',
    burstLimiter,
    [
        body('user_id').trim().notEmpty(),
        body('lat').isFloat({ min: 22, max: 32 }),
        body('lng').isFloat({ min: 24, max: 37 })
    ],
    async (req, res) => {
        try {
            const errors = validationResult(req);
            if (!errors.isEmpty()) {
                return res.status(400).json({
                    success: false,
                    error: 'Invalid location data',
                    details: errors.array()
                });
            }

            const { user_id, lat, lng, address, type } = req.body;
            const location_data = { lat, lng, address: address || '' };
            const convState = await getConversationState(user_id);

            // Detect language from recent messages
            const lang = 'ar'; // Default to Arabic for Egypt

            let response = {
                success: true,
                message: '',
                action: ACTION_TYPES.NONE,
                data: {},
                quick_replies: []
            };

            if (type === 'pickup' || convState.state === STATES.AWAITING_PICKUP) {
                await setConversationState(user_id, STATES.AWAITING_DESTINATION, {
                    ...convState.data,
                    pickup: location_data,
                    pickup_lat: lat,
                    pickup_lng: lng,
                    pickup_address: address
                });

                response.message = lang === 'ar'
                    ? '✅ تم تحديد موقع الانطلاق.\n\n📍 إلى أين تريد الذهاب؟'
                    : '✅ Pickup location set.\n\n📍 Where to?';

                const destAction = ActionBuilders.requestDestination(location_data);
                response.action = destAction.action;
                response.data = destAction.data;

            } else if (type === 'destination' || convState.state === STATES.AWAITING_DESTINATION) {
                const categories = await getVehicleCategories();

                await setConversationState(user_id, STATES.AWAITING_RIDE_TYPE, {
                    ...convState.data,
                    destination: location_data,
                    destination_lat: lat,
                    destination_lng: lng,
                    destination_address: address,
                    vehicle_categories: categories
                });

                response.message = formatVehicleCategoriesMessage(categories, lang);

                const rideOptions = ActionBuilders.showRideOptions(convState.data.pickup, location_data, categories);
                response.action = rideOptions.action;
                response.data = rideOptions.data;
                response.quick_replies = categories.map(c => c.name);

            } else {
                response.message = lang === 'ar'
                    ? '✅ تم استلام الموقع.'
                    : '✅ Location received.';
                response.quick_replies = getDefaultQuickReplies(lang);
            }

            await saveChat(user_id, 'user', `📍 ${address || `${lat},${lng}`}`);
            await saveChat(user_id, 'assistant', response.message, response.action, response.data);

            res.json(response);

        } catch (error) {
            logError(error, { endpoint: '/submit-location' });
            res.status(500).json({ success: false, error: error.message });
        }
    }
);

// ============================================
// 🔧 ADMIN ENDPOINTS
// ============================================

app.use('/admin', adminAuth, adminRateLimiter);

app.post('/admin/clear-memory', async (req, res) => {
    try {
        const { user_id } = req.body;
        if (!user_id) {
            return res.status(400).json({ success: false, error: 'user_id required' });
        }

        await dbExecute('DELETE FROM ai_chat_history WHERE user_id = ?', [user_id]);
        await dbExecute('DELETE FROM ai_conversation_state WHERE user_id = ?', [user_id]);
        userTypes.delete(user_id);
        lastMessages.delete(user_id);
        LanguageManager.clearSession(user_id);

        logger.info('User memory cleared', { user_id, admin: true });
        res.json({ success: true, message: `Memory cleared for user ${user_id}` });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

app.post('/admin/reset-state', async (req, res) => {
    try {
        const { user_id } = req.body;
        if (!user_id) {
            return res.status(400).json({ success: false, error: 'user_id required' });
        }

        await setConversationState(user_id, STATES.START, {});
        logger.info('User state reset', { user_id, admin: true });
        res.json({ success: true, message: `State reset for user ${user_id}` });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

app.get('/admin/user-state/:user_id', async (req, res) => {
    try {
        const { user_id } = req.params;
        const state = await getConversationState(user_id);
        const uType = getUserType(user_id);
        const langStats = await LanguageManager.getUserStats(user_id);
        const history = await getChatHistory(user_id, 10);

        res.json({
            ...state,
            userType: uType,
            languageStats: langStats,
            recentHistory: history
        });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.get('/admin/stats', async (req, res) => {
    try {
        const [userCount] = await pool.execute('SELECT COUNT(*) as count FROM users');
        const [chatCount] = await pool.execute('SELECT COUNT(*) as count FROM ai_chat_history');
        const [stateCount] = await pool.execute('SELECT COUNT(*) as count FROM ai_conversation_state');

        const mem = process.memoryUsage();

        res.json({
            success: true,
            stats: {
                database: {
                    users: userCount[0].count,
                    chatHistory: chatCount[0].count,
                    activeStates: stateCount[0].count,
                    connected: dbConnected
                },
                memory: {
                    userTypesCache: userTypes.size,
                    lastMessagesCache: lastMessages.size
                },
                performance: {
                    ...appMetrics,
                    uptime: `${Math.round(process.uptime())}s`,
                    uptimeHuman: formatUptime(process.uptime())
                },
                cache: responseCache.getStats(),
                auth: getAuthStats(),
                language: LanguageManager.getStats(),
                stateGuard: StateGuard.getStats(),
                system: {
                    heapUsed: `${Math.round(mem.heapUsed / 1024 / 1024)}MB`,
                    heapTotal: `${Math.round(mem.heapTotal / 1024 / 1024)}MB`,
                    rss: `${Math.round(mem.rss / 1024 / 1024)}MB`,
                    nodeEnv: process.env.NODE_ENV || 'development'
                }
            }
        });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

app.post('/admin/set-user-type', (req, res) => {
    const { user_id, type } = req.body;
    if (!user_id || !['captain', 'customer'].includes(type)) {
        return res.status(400).json({ success: false, error: 'Invalid user_id or type' });
    }
    setUserType(user_id, type);
    logger.info('User type set', { user_id, type, admin: true });
    res.json({ success: true, user_id, type });
});

app.post('/admin/reset-metrics', (req, res) => {
    Object.keys(appMetrics).forEach(key => {
        if (typeof appMetrics[key] === 'number' && key !== 'startTime') {
            appMetrics[key] = 0;
        }
    });
    appMetrics.lastReset = Date.now();
    res.json({ success: true, message: 'Metrics reset' });
});

// ============================================
// 📊 PUBLIC ENDPOINTS
// ============================================

app.get('/health', async (req, res) => {
    const healthCheck = {
        status: 'ok',
        timestamp: new Date().toISOString(),
        version: 'v3.2',
        uptime: Math.round(process.uptime()),
        checks: {}
    };

    // Database check
    try {
        if (pool && dbConnected) {
            await pool.execute('SELECT 1');
            healthCheck.checks.database = { status: 'healthy' };
        } else {
            healthCheck.checks.database = { status: 'unhealthy', error: 'Not connected' };
            healthCheck.status = 'degraded';
        }
    } catch (error) {
        healthCheck.checks.database = { status: 'unhealthy', error: error.message };
        healthCheck.status = 'degraded';
    }

    // Memory check
    const mem = process.memoryUsage();
    const heapUsedPercent = mem.heapUsed / mem.heapTotal;
    healthCheck.checks.memory = {
        heapUsed: `${Math.round(mem.heapUsed / 1024 / 1024)}MB`,
        heapTotal: `${Math.round(mem.heapTotal / 1024 / 1024)}MB`,
        status: heapUsedPercent < 0.9 ? 'healthy' : 'warning'
    };

    if (heapUsedPercent >= 0.9) {
        healthCheck.status = 'degraded';
    }

    res.status(healthCheck.status === 'ok' ? 200 : 503).json(healthCheck);
});

app.get('/action-types', (req, res) => {
    res.json({
        action_types: ACTION_TYPES,
        ui_hints: UI_HINTS,
        description: 'Flutter action types for SmartLine chatbot',
        version: 'v3.2'
    });
});

app.get('/chat', (req, res) => {
    res.send(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>SmartLine AI Chatbot</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; min-height: 100vh; margin: 0; }
                h1 { font-size: 2.5em; margin-bottom: 10px; }
                p { font-size: 1.2em; opacity: 0.9; }
                .status { background: rgba(255,255,255,0.2); padding: 20px; border-radius: 10px; display: inline-block; margin-top: 20px; }
                .version { font-size: 0.9em; opacity: 0.7; }
            </style>
        </head>
        <body>
            <h1>🚗 SmartLine AI Chatbot</h1>
            <p>Production-Ready Customer Service AI</p>
            <div class="status">
                <p>✅ Server Running</p>
                <p>📡 API: POST /chat</p>
                <p>🏥 Health: GET /health</p>
                <p class="version">Version 3.2</p>
            </div>
        </body>
        </html>
    `);
});

// Prometheus-style metrics endpoint
app.get('/metrics/prometheus', (req, res) => {
    const lines = [
        '# HELP smartline_requests_total Total HTTP requests',
        '# TYPE smartline_requests_total counter',
        `smartline_requests_total ${appMetrics.requestsTotal}`,
        '',
        '# HELP smartline_requests_success Successful requests',
        '# TYPE smartline_requests_success counter',
        `smartline_requests_success ${appMetrics.requestsSuccess}`,
        '',
        '# HELP smartline_requests_failed Failed requests',
        '# TYPE smartline_requests_failed counter',
        `smartline_requests_failed ${appMetrics.requestsFailed}`,
        '',
        '# HELP smartline_response_time_avg Average response time in ms',
        '# TYPE smartline_response_time_avg gauge',
        `smartline_response_time_avg ${Math.round(appMetrics.avgResponseTime)}`,
        '',
        '# HELP smartline_llm_calls Total LLM API calls',
        '# TYPE smartline_llm_calls counter',
        `smartline_llm_calls ${appMetrics.llmCalls}`,
        '',
        '# HELP smartline_llm_errors LLM API errors',
        '# TYPE smartline_llm_errors counter',
        `smartline_llm_errors ${appMetrics.llmErrors}`,
        '',
        '# HELP smartline_db_queries Total database queries',
        '# TYPE smartline_db_queries counter',
        `smartline_db_queries ${appMetrics.dbQueries}`,
        '',
        '# HELP smartline_uptime_seconds Server uptime',
        '# TYPE smartline_uptime_seconds gauge',
        `smartline_uptime_seconds ${Math.round(process.uptime())}`,
    ];

    res.set('Content-Type', 'text/plain');
    res.send(lines.join('\n'));
});

// ============================================
// 🛠️ HELPER FUNCTIONS
// ============================================

function formatUptime(seconds) {
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    const parts = [];
    if (days > 0) parts.push(`${days}d`);
    if (hours > 0) parts.push(`${hours}h`);
    if (minutes > 0) parts.push(`${minutes}m`);

    return parts.join(' ') || '< 1m';
}

// ============================================
// 🛑 GRACEFUL SHUTDOWN
// ============================================

let isShuttingDown = false;

async function gracefulShutdown(signal) {
    if (isShuttingDown) return;
    isShuttingDown = true;

    logger.info(`Received ${signal}. Starting graceful shutdown...`);

    // Stop accepting new connections
    const server = app.get('server');
    if (server) {
        server.close(() => {
            logger.info('HTTP server closed');
        });
    }

    // Shutdown language manager
    try {
        await LanguageManager.shutdown();
    } catch (e) {
        logger.error('Error shutting down LanguageManager', { error: e.message });
    }

    // Close database pool
    if (pool) {
        try {
            await pool.end();
            logger.info('Database pool closed');
        } catch (e) {
            logger.error('Error closing database pool', { error: e.message });
        }
    }

    logger.info('Graceful shutdown complete');
    process.exit(0);
}

process.on('SIGTERM', () => gracefulShutdown('SIGTERM'));
process.on('SIGINT', () => gracefulShutdown('SIGINT'));

process.on('unhandledRejection', (reason, promise) => {
    logger.error('Unhandled Rejection', {
        reason: reason instanceof Error ? reason.message : reason,
        stack: reason instanceof Error ? reason.stack : undefined
    });
});

process.on('uncaughtException', (error) => {
    logError(error, { type: 'uncaughtException', fatal: true });
    process.exit(1);
});

// ============================================
// 🚀 START SERVER
// ============================================

const PORT = process.env.PORT || 3000;

async function start() {
    try {
        // Initialize database
        await initDatabase();

        // Start server
        const server = app.listen(PORT, () => {
            console.log(`
╔════════════════════════════════════════════════════════════╗
║                                                            ║
║   🚗 SMARTLINE AI CHATBOT V3.2                            ║
║   ─────────────────────────────────────────────────────   ║
║                                                            ║
║   Server:    http://localhost:${String(PORT).padEnd(5)}                      ║
║   Database:  ${DB_CONFIG.database.padEnd(20)}                   ║
║   Env:       ${(process.env.NODE_ENV || 'development').padEnd(20)}                   ║
║                                                            ║
║   Endpoints:                                               ║
║     POST /chat              Main chat endpoint             ║
║     POST /submit-location   Location submission            ║
║     GET  /health            Health check                   ║
║     GET  /action-types      Flutter actions                ║
║     GET  /metrics/prometheus Prometheus metrics            ║
║                                                            ║
║   Features:                                                ║
║     ✅ Rate Limiting        ✅ Content Moderation          ║
║     ✅ Multi-language       ✅ State Management            ║
║     ✅ LLM Integration      ✅ Trip Creation               ║
║     ✅ Graceful Shutdown    ✅ Metrics & Logging           ║
║                                                            ║
╚════════════════════════════════════════════════════════════╝
            `);
        });

        // Store server reference for graceful shutdown
        app.set('server', server);

    } catch (error) {
        logger.error('Failed to start server', { error: error.message });
        process.exit(1);
    }
}

start();

module.exports = app; // For testing