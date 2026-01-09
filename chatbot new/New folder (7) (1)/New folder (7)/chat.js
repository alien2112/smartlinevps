// ============================================
// 🚗 RIDE SUPPORT - CUSTOMER SERVICE CHATBOT
// ============================================

const express = require('express');
const cors = require('cors');
const mysql = require('mysql2/promise');
const path = require('path');
const rateLimit = require('express-rate-limit');
const { body, validationResult } = require('express-validator');
const morgan = require('morgan');
require('dotenv').config();

// ============================================
// 🛡️ MODERATION & ESCALATION
// ============================================

const { detectUserLanguage, checkProfanity } = require('./utils/moderation');
const { escalationReply, languageGuardReply } = require('./utils/escalationMessages');
const { adminAuth } = require('./utils/auth');
const { logger, logRequest, logError } = require('./utils/logger');
const { get: getCache, set: setCache } = require('./utils/cache');

const app = express();
app.use(express.json());
app.use(cors());
app.use(express.static(path.join(__dirname, 'public')));

// ============================================
// 📝 REQUEST LOGGING
// ============================================

// HTTP request logging middleware
morgan.token('user-id', (req) => req.body?.user_id || '-');
const morganFormat = process.env.NODE_ENV === 'production'
    ? 'combined'
    : ':method :url :status :response-time ms';
app.use(morgan(morganFormat, {
    stream: {
        write: (message) => logger.info(message.trim())
    }
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
// ⚡ RATE LIMITING
// ============================================

// Rate limiter for chat endpoint
// Dev mode: 30 messages/minute, Production: 10 messages/minute
const rateLimitMax = process.env.NODE_ENV === 'production' ? 10 : 30;
const chatRateLimiter = rateLimit({
    windowMs: 60 * 1000, // 1 minute
    max: rateLimitMax,
    message: (req, res) => {
        // Bilingual rate limit message
        const isArabic = req.body?.message && /[\u0600-\u06FF]/.test(req.body.message);
        const reply = isArabic
            ? 'عدد كبير من الطلبات. من فضلك انتظر دقيقة ثم حاول مرة أخرى. / Too many requests. Please wait a minute and try again.'
            : 'Too many requests. Please wait a minute and try again. / عدد كبير من الطلبات. من فضلك انتظر دقيقة.';

        res.status(429).json({
            reply: reply,
            error: 'RATE_LIMIT_EXCEEDED',
            retryAfter: 60,
            language: {
                primary: isArabic ? 'ar' : 'en',
                confidence: 0.8,
                arabicRatio: isArabic ? 1.0 : 0,
                latinRatio: isArabic ? 0 : 1.0,
                hasArabizi: false
            },
            confidence: 0,
            handoff: false
        });
    },
    standardHeaders: true,
    legacyHeaders: false,
    // Key generator based on user_id from request body
    keyGenerator: (req) => {
        return req.body?.user_id || req.ip || 'unknown';
    },
    skip: (req) => {
        // Skip rate limiting for health check
        return req.path === '/health';
    }
});

// ============================================
// 🗄️ DATABASE
// ============================================

const DB_CONFIG = {
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '1234',
    database: process.env.DB_NAME || 'my_database',
    waitForConnections: true,
    connectionLimit: parseInt(process.env.DB_POOL_SIZE) || 20, // Increased & configurable
    queueLimit: 0,
    enableKeepAlive: true,
    keepAliveInitialDelay: 10000
};

let pool;
let dbRetryCount = 0;
const MAX_DB_RETRIES = 5;

async function initDatabase() {
    try {
        pool = mysql.createPool(DB_CONFIG);

        // Handle pool errors for recovery
        pool.on('error', (err) => {
            logger.error('Database pool error', { error: err.message, code: err.code });
            if (err.code === 'PROTOCOL_CONNECTION_LOST' ||
                err.code === 'ECONNREFUSED' ||
                err.code === 'ECONNRESET') {
                logger.info('Attempting to recreate database pool...');
                reconnectDatabase();
            }
        });

        // Test connection
        const connection = await pool.getConnection();
        connection.release();
        logger.info('Database connection verified');
        dbRetryCount = 0; // Reset retry count on success

        await pool.execute(`
        CREATE TABLE IF NOT EXISTS users (
            id VARCHAR(50) PRIMARY KEY,
                preferred_language VARCHAR(10) NULL DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    `);

        // Add preferred_language column if it doesn't exist (migration support)
        try {
            await pool.execute(`ALTER TABLE users ADD COLUMN preferred_language VARCHAR(10) NULL DEFAULT NULL`);
            logger.info('Added preferred_language column to users table');
        } catch (e) {
            // Column already exists, ignore
            if (!e.message.includes('Duplicate column')) {
                logger.warn('Migration note: preferred_language column may already exist');
            }
        }

        // Add user_role column if it doesn't exist (optional, non-blocking)
        try {
            await pool.execute(`ALTER TABLE users ADD COLUMN user_role VARCHAR(20) NULL DEFAULT NULL`);
            logger.info('Added user_role column to users table');
        } catch (e) {
            // Column already exists, ignore
            if (!e.message.includes('Duplicate column')) {
                // Non-critical, continue
            }
        }

        await pool.execute(`
        CREATE TABLE IF NOT EXISTS rides (
            id VARCHAR(50) PRIMARY KEY,
            user_id VARCHAR(50) NOT NULL,
            status VARCHAR(20) DEFAULT 'ongoing',
            driver_name VARCHAR(100),
            pickup VARCHAR(255),
            destination VARCHAR(255),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id)
        )
    `);

        await pool.execute(`
        CREATE TABLE IF NOT EXISTS chat_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(50) NOT NULL,
            role VARCHAR(20) NOT NULL,
            content TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_user_created (user_id, created_at)
            )
        `);

        await pool.execute(`
            CREATE TABLE IF NOT EXISTS user_violations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(50) NOT NULL,
                violation_type VARCHAR(20) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id)
            )
        `);

        logger.info('Database initialized successfully');

    } catch (error) {
        logger.error('Database initialization failed', { error: error.message });
        reconnectDatabase();
    }
}

async function reconnectDatabase() {
    if (dbRetryCount >= MAX_DB_RETRIES) {
        logger.error('Max database reconnection attempts reached', { attempts: dbRetryCount });
        return;
    }

    dbRetryCount++;
    const delay = Math.min(1000 * Math.pow(2, dbRetryCount), 30000); // Exponential backoff, max 30s

    logger.info(`Retrying database connection in ${delay}ms`, { attempt: dbRetryCount });

    setTimeout(async () => {
        try {
            if (pool) {
                await pool.end().catch(() => { }); // Close existing pool
            }
            await initDatabase();
        } catch (error) {
            logger.error('Database reconnection failed', { error: error.message });
        }
    }, delay);
}

// Database health check helper
async function isDatabaseHealthy() {
    try {
        await pool.execute('SELECT 1');
        return true;
    } catch (error) {
        return false;
    }
}

// ============================================
// 🧠 SYSTEM PROMPTS
// ============================================

const PROMPT_EN = `You are a customer support system for a ride-hailing service.

Your role is to provide accurate, factual, and concise information related to user requests.

STRICT LANGUAGE RULES:
- Output ONLY in English. Never use any other language.
- Use clear, simple language.
- Never output Arabic, French, or any other language.

INSTRUCTIONS:
- Use clear, simple language.
- Do not use emotional expressions or conversational fillers.
- Do not include greetings, apologies, or personal statements.
- Do not assume user intent beyond the explicit request.
- Do not personify the system.
- Provide direct instructions or information only.
- Ask for required information only when necessary to complete the task.
- Never mention internal systems or AI.

CONVERSATION RULES:
- If user intent is clear, respond directly to it. Do not ask for clarification unnecessarily.
- Only show a menu (numbered options) when you genuinely need to understand what they want.
- Once you know what they need, continue the conversation without repeating options.
- Do not repeat the same menu every turn.
- Maximum 4 short lines per response.
- Ask only ONE question at a time when clarification is needed.

RESPONSE STYLE:
- Professional
- Neutral
- Task-focused
- Consistent

MENU FORMATTING (when needed):
When you must show options, use this EXACT format with newlines:

1- Payment issue
2- Lost item
3- Account help
4- Trip status
5- Something else

Always put a blank line before the numbered list. Each option on its own line.

ACTIVE RIDE:
If there is an active ride, state it first:
"Current trip: Captain [name], [pickup] → [destination]"

SAFETY:
If user mentions danger, harassment, accident, or unsafe driving:
1. Ask: "Are you safe right now? (Yes/No)"
2. If not safe: "Call emergency services. Connecting to human support."

If information is missing, request it directly and concisely.`;

const PROMPT_AR = `أنت مساعد دعم العملاء لخدمة توصيل "رايد سبورت".

قدراتك:
- الإجابة على أسئلة عن الخدمة
- إعطاء معلومات عن الرحلة الحالية (لو موجودة)
- توجيه المستخدم لاستخدام التطبيق
- تحويل للدعم البشري لو محتاج

قيودك (كن صريح):
- مش هتقدر تحجز رحلة - قول للعميل يستخدم التطبيق
- مش هتقدر تلغي رحلة - قول للعميل: "روح للتطبيق > رحلاتي > إلغاء" أو اعرض دعم بشري
- مش هتقدر تغير الوجهة
- مش هتقدر تعمل أي عملية دفع

قواعد الرد:
- أقصى حد 3 سطور قصيرة
- متعرضش قائمة اختيارات إلا لو العميل سأل "تقدر تساعدني في إيه"
- متكررش القوائم - لما تعرف عايز إيه، ساعده مباشرة
- كن مباشر ومفيد
- لو مش هتقدر تعمل حاجة، قول بصراحة واعرض بديل

أمثلة للمحادثة:
- "عايز احجز رحلة" ← "للحجز: افتح التطبيق، حط وجهتك، واضغط احجز. محتاج حاجة تانية؟"
- "عايز الغي الرحلة" ← "للإلغاء: التطبيق > رحلاتي > إلغاء. عايز أحولك للدعم؟"
- "فين السواق" ← اعطي معلومات الرحلة لو موجودة، أو قول "مفيش رحلة نشطة. حجزت من التطبيق؟"
- سؤال عام ← رد مباشرة بدون قائمة

الأمان:
لو العميل ذكر خطر أو تحرش أو حادثة:
1. اسأل: "إنت بأمان دلوقتي؟"
2. لو مش بأمان: "اتصل بالطوارئ فوراً. بحولك للدعم البشري."

معلومات الرحلة:
لو في رحلة، اذكرها بشكل طبيعي. لو مفيش، متتظاهرش إن في.`;

// ============================================
// 🧠 SYSTEM PROMPT V2 (STRUCTURED JSON)
// ============================================

const PROMPT_V2 = `You are a customer support system for a ride-hailing service. You must handle TWO user roles: Customer (rider) and Captain (driver).

Your outputs must be neutral, factual, and task-focused. Do not use greetings, apologies, emotional language, flattery, or personal statements. Do not personify the system.

CRITICAL: You MUST respond with ONLY valid JSON, no markdown, no extra text. Use this EXACT schema:

{
  "language": "ar" | "en" | "arabizi",
  "role": "customer" | "captain" | "unknown",
  "intent": "role_clarification" | "trip_status" | "payment_issue" | "refund_request" | "lost_item" | "safety_issue" | "complaint" | "account_verification" | "captain_app_issue" | "captain_trip_issue" | "vehicle_issue" | "general_support" | "unknown",
  "state": "start" | "awaiting_role" | "support_intake" | "issue_triage" | "resolved" | "error_state",
  "message": "string in user's language",
  "required_inputs": [{"key": "string", "prompt": "string"}],
  "actions": [{"type": "CREATE_SUPPORT_TICKET" | "ESCALATE_TO_HUMAN", "payload": {}}],
  "status": "need_info" | "in_progress" | "resolved" | "escalate" | "error",
  "error": null | {"code": "string", "detail": "string"}
}

LANGUAGE HANDLING:
- Arabic script → respond in Arabic
- English → respond in English
- Arabizi (Latin+numbers like "3ayez", "7gez") → respond in Arabizi
- Detect from user's latest message

ROLE DETECTION:
- Detect from keywords: "captain"/"driver"/"كابتن"/"سائق" → Captain
- "customer"/"rider"/"راكب"/"عميل" → Customer
- If unclear: role="unknown", state="awaiting_role", message="Specify role: Customer or Captain."

SUPPORT FLOWS:
- Lost item: ask trip_id + description → CREATE_SUPPORT_TICKET
- Payment/refund: ask trip_id + issue type → CREATE_SUPPORT_TICKET
- Safety: if immediate danger → ESCALATE_TO_HUMAN
- Captain issues: ask category + description → CREATE_SUPPORT_TICKET or ESCALATE_TO_HUMAN
- Trip status: provide information if available, otherwise ask for trip_id

IMPORTANT: This is a SUPPORT-ONLY system. Do NOT handle booking requests. If user asks to book, cancel, or modify a trip, direct them to use the mobile app.

Return ONLY the JSON object, nothing else.`;

// ============================================
// 🔄 STATE MANAGEMENT (IN-MEMORY)
// ============================================

// User conversation state: userId -> { role, state }
const userStates = new Map();

// Cleanup stale states (older than 30 minutes)
setInterval(() => {
    const now = Date.now();
    for (const [userId, stateData] of userStates.entries()) {
        if (stateData.lastUpdate && (now - stateData.lastUpdate > 1800000)) {
            userStates.delete(userId);
        }
    }
}, 600000); // Every 10 minutes

function getUserState(userId) {
    return userStates.get(userId) || { role: null, state: 'start', lastUpdate: Date.now() };
}

function setUserState(userId, updates) {
    const current = getUserState(userId);
    userStates.set(userId, {
        ...current,
        ...updates,
        lastUpdate: Date.now()
    });
}

function clearUserState(userId) {
    userStates.delete(userId);
}

// ============================================
// 👤 ROLE DETECTION
// ============================================

function detectRoleFromMessage(message) {
    const text = message.toLowerCase();

    // Captain indicators
    const captainKeywords = [
        'captain', 'driver', 'kaptan', 'sayye2', 'sayy2',
        'كابتن', 'سائق', 'سواق', 'توصيل', 'trips assigned', 'earnings', 'availability'
    ];

    // Customer indicators
    const customerKeywords = [
        'customer', 'rider', 'passenger', 'عميل', 'راكب', 'زبون'
    ];

    const hasCaptain = captainKeywords.some(kw => text.includes(kw));
    const hasCustomer = customerKeywords.some(kw => text.includes(kw));

    if (hasCaptain && !hasCustomer) return 'captain';
    if (hasCustomer && !hasCaptain) return 'customer';

    // Explicit statements
    if (text.includes('i am a captain') || text.includes('انا كابتن') || text.includes('ana kaptan')) {
        return 'captain';
    }
    if (text.includes('i am a customer') || text.includes('انا عميل')) {
        return 'customer';
    }

    return null;
}

// ============================================
// 👤 USER TYPE MANAGEMENT SYSTEM
// ============================================

const userTypes = new Map(); // userId -> { type: 'captain'|'customer', detectedAt: timestamp }
const MAX_USER_TYPES = 50000;

// Comprehensive keyword detection
const USER_TYPE_KEYWORDS = {
    captain: {
        strong: [
            // English - Strong indicators
            'i am a driver', "i'm a driver", 'i am a captain', "i'm a captain",
            'my earnings', 'my rating as driver', 'acceptance rate', 'vehicle inspection',
            'passenger complained', 'rider no show', 'trip request', 'going online',
            'go offline', 'my vehicle', 'driver app', 'captain app',
            // Arabic - Strong indicators
            'انا سواق', 'أنا سواق', 'انا كابتن', 'أنا كابتن',
            'أرباحي', 'تقييمي كسواق', 'نسبة القبول', 'فحص العربية',
            'الراكب اشتكى', 'الراكب مجاش', 'طلب رحلة', 'عايز اعمل اونلاين',
            'اعمل اوفلاين', 'عربيتي', 'تطبيق السواق', 'تطبيق الكابتن'
        ],
        weak: [
            // Could be captain but not certain
            'driver', 'captain', 'earnings', 'payout', 'incentive', 'bonus', 'documents',
            'سواق', 'كابتن', 'ارباح', 'حافز', 'مكافأة', 'مستندات'
        ]
    },
    customer: {
        strong: [
            // English - Strong indicators
            'i am a customer', "i'm a customer", 'i am a rider', "i'm a rider",
            'i am a passenger', "i'm a passenger", 'book a ride', 'book me a ride',
            'need a ride', 'where is my driver', 'my driver is late', 'cancel my ride',
            'i want to go to', 'take me to', 'pick me up', 'waiting for driver',
            'driver cancelled', 'i left something', 'lost my phone', 'refund my money',
            // Arabic - Strong indicators
            'انا عميل', 'أنا عميل', 'انا راكب', 'أنا راكب', 'انا زبون',
            'احجزلي', 'عايز رحلة', 'فين السواق', 'السواق اتأخر', 'الغي الرحلة',
            'عايز اروح', 'وديني', 'خدني', 'مستني السواق', 'السواق لغى',
            'نسيت حاجة', 'نسيت موبايلي', 'رجعلي فلوسي', 'استرداد'
        ],
        weak: [
            // Could be customer but not certain
            'ride', 'trip', 'driver late', 'cancel', 'refund', 'promo', 'book',
            'رحلة', 'السواق', 'الغاء', 'استرداد', 'خصم', 'حجز'
        ]
    }
};

// Direct declarations that immediately set type
const DIRECT_DECLARATIONS = {
    captain: [
        'driver', 'captain', 'سواق', 'كابتن',
        'i am driver', 'i am a driver', "i'm a driver", 'i am captain', "i'm captain",
        'انا سواق', 'أنا سواق', 'انا كابتن', 'أنا كابتن', 'كابتن', 'سواق'
    ],
    customer: [
        'customer', 'rider', 'passenger', 'عميل', 'راكب', 'زبون',
        'i am customer', 'i am a customer', "i'm a customer", 'i am rider', "i'm rider",
        'انا عميل', 'أنا عميل', 'انا راكب', 'أنا راكب', 'انا زبون', 'عميل', 'راكب'
    ]
};

function detectUserType(message, userId) {
    // Check if already known
    const existing = userTypes.get(userId);
    if (existing && existing.type) {
        return existing.type;
    }

    const msgLower = message.toLowerCase().trim();

    // Check direct declarations first (highest priority)
    for (const declaration of DIRECT_DECLARATIONS.captain) {
        if (msgLower === declaration || msgLower.includes(declaration)) {
            setUserType(userId, 'captain');
            return 'captain';
        }
    }

    for (const declaration of DIRECT_DECLARATIONS.customer) {
        if (msgLower === declaration || msgLower.includes(declaration)) {
            setUserType(userId, 'customer');
            return 'customer';
        }
    }

    // Check strong indicators
    for (const keyword of USER_TYPE_KEYWORDS.captain.strong) {
        if (msgLower.includes(keyword.toLowerCase())) {
            setUserType(userId, 'captain');
            return 'captain';
        }
    }

    for (const keyword of USER_TYPE_KEYWORDS.customer.strong) {
        if (msgLower.includes(keyword.toLowerCase())) {
            setUserType(userId, 'customer');
            return 'customer';
        }
    }

    // Check weak indicators (only if strong not found)
    let captainScore = 0;
    let customerScore = 0;

    for (const keyword of USER_TYPE_KEYWORDS.captain.weak) {
        if (msgLower.includes(keyword.toLowerCase())) {
            captainScore++;
        }
    }

    for (const keyword of USER_TYPE_KEYWORDS.customer.weak) {
        if (msgLower.includes(keyword.toLowerCase())) {
            customerScore++;
        }
    }

    // Only set if clear winner with 2+ point lead
    if (captainScore > customerScore + 1) {
        setUserType(userId, 'captain');
        return 'captain';
    }

    if (customerScore > captainScore + 1) {
        setUserType(userId, 'customer');
        return 'customer';
    }

    return null; // Unknown - bot should ask
}

function setUserType(userId, type) {
    if (type === 'captain' || type === 'customer') {
        // Check map size before adding
        if (userTypes.size >= MAX_USER_TYPES) {
            cleanupUserTypes();
        }
        userTypes.set(userId, { type, detectedAt: Date.now() });
        logger.info('User type set', { userId, type });
    }
}

function getUserType(userId) {
    const data = userTypes.get(userId);
    return data ? data.type : null;
}

function clearUserType(userId) {
    userTypes.delete(userId);
}

function cleanupUserTypes() {
    const now = Date.now();
    const maxAge = 24 * 60 * 60 * 1000; // 24 hours
    let cleaned = 0;

    for (const [userId, data] of userTypes.entries()) {
        if (now - data.detectedAt > maxAge) {
            userTypes.delete(userId);
            cleaned++;
        }
    }

    // If still too large, remove oldest
    if (userTypes.size > MAX_USER_TYPES * 0.9) {
        const entries = [...userTypes.entries()]
            .sort((a, b) => a[1].detectedAt - b[1].detectedAt)
            .slice(0, Math.floor(userTypes.size * 0.2));
        entries.forEach(([key]) => userTypes.delete(key));
        cleaned += entries.length;
    }

    if (cleaned > 0) {
        logger.info(`Cleaned ${cleaned} entries from userTypes map`);
    }
}

// Periodic cleanup
setInterval(cleanupUserTypes, 30 * 60 * 1000); // Every 30 minutes

// ============================================
// 🗄️ DATABASE HELPERS
// ============================================

async function ensureUser(userId) {
    const [rows] = await pool.execute('SELECT id, preferred_language, user_role FROM users WHERE id = ?', [userId]);
    if (rows.length === 0) {
        await pool.execute('INSERT INTO users (id, preferred_language, user_role) VALUES (?, NULL, NULL)', [userId]);
        return { id: userId, preferred_language: null, user_role: null };
    }
    return rows[0];
}

async function getUserRole(userId) {
    try {
        const [rows] = await pool.execute('SELECT user_role FROM users WHERE id = ?', [userId]);
        return rows[0]?.user_role || null;
    } catch (error) {
        logger.warn('Error getting user role', { userId, error: error.message });
        return null;
    }
}

async function setUserRole(userId, role) {
    try {
        await pool.execute('UPDATE users SET user_role = ? WHERE id = ?', [role, userId]);
    } catch (error) {
        logger.warn('Error setting user role', { userId, role, error: error.message });
        // Non-critical, continue
    }
}

async function getUserPreferredLanguage(userId) {
    const [rows] = await pool.execute('SELECT preferred_language FROM users WHERE id = ?', [userId]);
    return rows[0]?.preferred_language || null;
}

async function setUserPreferredLanguage(userId, lang) {
    // Lock language: ar/arabizi -> 'ar', en -> 'en'
    const lockedLang = (lang === 'ar' || lang === 'arabizi') ? 'ar' : (lang === 'en' ? 'en' : null);
    if (lockedLang) {
        await pool.execute('UPDATE users SET preferred_language = ? WHERE id = ?', [lockedLang, userId]);
    }
    return lockedLang;
}

async function getRide(userId) {
    const [rows] = await pool.execute(
        'SELECT * FROM rides WHERE user_id = ? ORDER BY created_at DESC LIMIT 1',
        [userId]
    );
    return rows[0] || null;
}

async function getChatHistory(userId) {
    // Performance: Keep only last 4 messages as LLM context
    const [rows] = await pool.execute(
        'SELECT role, content FROM chat_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 4',
        [userId]
    );
    return rows.reverse();
}

async function saveChat(userId, userMsg, botReply) {
    await pool.execute(
        'INSERT INTO chat_history (user_id, role, content) VALUES (?, ?, ?)',
        [userId, 'user', userMsg]
    );
    await pool.execute(
        'INSERT INTO chat_history (user_id, role, content) VALUES (?, ?, ?)',
        [userId, 'assistant', botReply]
    );

    // Optimized cleanup: only run if likely needed (every 5th message)
    // This reduces database load significantly
    try {
        const [countResult] = await pool.execute(
            'SELECT COUNT(*) as count FROM chat_history WHERE user_id = ?',
            [userId]
        );

        if (countResult[0].count > 12) { // Only cleanup if > 12 messages
            await pool.execute(`
        DELETE FROM chat_history 
        WHERE user_id = ? AND id NOT IN (
            SELECT id FROM (
                SELECT id FROM chat_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 10
            ) AS t
        )
    `, [userId, userId]);
        }
    } catch (error) {
        // Non-critical - log but don't fail the request
        logger.warn('Chat cleanup failed', { userId, error: error.message });
    }
}

async function clearChat(userId) {
    await pool.execute('DELETE FROM chat_history WHERE user_id = ?', [userId]);
    clearUserType(userId); // Also clear user type
}

// ============================================
// 🔄 REPEATED MESSAGE DETECTION
// ============================================

// In-memory store for last messages per user
const lastMessages = new Map(); // userId -> { message, timestamp }
const MAX_LAST_MESSAGES = 50000; // Maximum entries to prevent memory bloat

function isRepeatedMessage(userId, message) {
    const normalizedMessage = message.trim().toLowerCase();
    const lastMessage = lastMessages.get(userId);

    if (!lastMessage) {
        // Check size before adding new entry
        if (lastMessages.size >= MAX_LAST_MESSAGES) {
            // Emergency cleanup - remove oldest 10%
            cleanupLastMessages(true);
        }
        lastMessages.set(userId, { message: normalizedMessage, timestamp: Date.now() });
        return false;
    }

    // Check if message is identical and within 30 seconds
    const timeDiff = Date.now() - lastMessage.timestamp;
    if (normalizedMessage === lastMessage.message && timeDiff < 30000) {
        return true;
    }

    // Update last message
    lastMessages.set(userId, { message: normalizedMessage, timestamp: Date.now() });
    return false;
}

// Cleanup function for lastMessages Map
function cleanupLastMessages(emergency = false) {
    const now = Date.now();
    const maxAge = 300000; // 5 minutes
    let cleaned = 0;

    for (const [userId, data] of lastMessages.entries()) {
        if (now - data.timestamp > maxAge) {
            lastMessages.delete(userId);
            cleaned++;
        }
    }

    // Emergency cleanup: if still too large, remove oldest entries
    if (emergency && lastMessages.size > MAX_LAST_MESSAGES * 0.9) {
        const entries = [...lastMessages.entries()]
            .sort((a, b) => a[1].timestamp - b[1].timestamp)
            .slice(0, Math.floor(lastMessages.size * 0.1));
        entries.forEach(([key]) => lastMessages.delete(key));
        cleaned += entries.length;
        logger.warn('Emergency cleanup triggered for lastMessages', { removed: entries.length });
    }

    if (cleaned > 0) {
        logger.info(`Cleaned ${cleaned} stale entries from lastMessages`);
    }
    return cleaned;
}

// Periodic cleanup every 10 minutes
setInterval(() => {
    cleanupLastMessages();
}, 600000);

// ============================================
// 🤖 GROQ API - LLAMA 3.3 70B
// ============================================

// Request Queue to prevent rate limits (Groq limit: 30 RPM)
class RequestQueue {
    constructor(maxPerMinute = 25) {
        this.queue = [];
        this.processing = false;
        this.requestsThisMinute = 0;
        this.maxPerMinute = maxPerMinute;

        setInterval(() => {
            this.requestsThisMinute = 0;
        }, 60000);
    }

    async add(fn) {
        return new Promise((resolve, reject) => {
            this.queue.push({ fn, resolve, reject });
            this.process();
        });
    }

    async process() {
        if (this.processing) return;
        this.processing = true;

        while (this.queue.length > 0) {
            if (this.requestsThisMinute >= this.maxPerMinute) {
                await new Promise(r => setTimeout(r, 5000));
                continue;
            }

            const { fn, resolve, reject } = this.queue.shift();
            this.requestsThisMinute++;

            try {
                const result = await fn();
                resolve(result);
            } catch (error) {
                reject(error);
            }

            await new Promise(r => setTimeout(r, 100));
        }

        this.processing = false;
    }
}

const llmQueue = new RequestQueue(25);

// Rate limit tracking
let lastRequestTime = 0;
const MIN_REQUEST_INTERVAL = 2000;

async function callLLM(messages, retryCount = 0) {
    const MAX_RETRIES = 3;

    // Enforce minimum interval between requests
    const now = Date.now();
    const timeSinceLastRequest = now - lastRequestTime;
    if (timeSinceLastRequest < MIN_REQUEST_INTERVAL) {
        await new Promise(resolve => setTimeout(resolve, MIN_REQUEST_INTERVAL - timeSinceLastRequest));
    }
    lastRequestTime = Date.now();

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 15000);

    try {
        const response = await fetch('https://api.groq.com/openai/v1/chat/completions', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${process.env.GROQ_API_KEY}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                model: 'llama-3.3-70b-versatile',
                messages: messages,
                temperature: 0.3,
                max_tokens: 300
            }),
            signal: controller.signal
        });

        clearTimeout(timeoutId);

        // Handle rate limits
        if (response.status === 429) {
            const retryAfter = parseInt(response.headers.get('retry-after')) || 5;
            logger.warn('Groq rate limit hit', { retryAfter, retryCount });

            if (retryCount < MAX_RETRIES) {
                await new Promise(resolve => setTimeout(resolve, retryAfter * 1000));
                return callLLM(messages, retryCount + 1);
            }
            throw new Error('RATE_LIMIT_EXCEEDED');
        }

        if (!response.ok) {
            const err = await response.text();
            logger.error('Groq API error', { status: response.status, error: err });
            throw new Error(`Groq API error: ${response.status}`);
        }

        const data = await response.json();
        return data.choices[0].message.content;

    } catch (error) {
        clearTimeout(timeoutId);

        if (error.name === 'AbortError') {
            logger.error('Groq API timeout');
            throw new Error('API_TIMEOUT');
        }

        if (error.message === 'RATE_LIMIT_EXCEEDED') {
            throw error;
        }

        if (retryCount < MAX_RETRIES && (error.code === 'ECONNRESET' || error.code === 'ETIMEDOUT')) {
            logger.warn('Network error, retrying', { error: error.message, retryCount });
            await new Promise(resolve => setTimeout(resolve, 2000));
            return callLLM(messages, retryCount + 1);
        }

        throw error;
    }
}

// Queued version to prevent rate limits
async function callLLMQueued(messages) {
    return llmQueue.add(() => callLLM(messages));
}

// ============================================
// 🤖 LLM V2 (STRUCTURED JSON OUTPUT)
// ============================================

/**
 * Fallback v2 response when parsing fails
 */
function createFallbackV2Response(language = 'en') {
    return {
        language: language,
        role: 'unknown',
        intent: 'unknown',
        state: 'start',
        message: language === 'ar' ? 'حدد الدور: عميل أو كابتن.' : language === 'arabizi' ? 'حدد el role: customer wala captain.' : 'Specify role: Customer or Captain.',
        required_inputs: [{ key: 'role', prompt: language === 'ar' ? 'حدد الدور: عميل أو كابتن.' : language === 'arabizi' ? 'حدد el role: customer wala captain.' : 'Specify role: Customer or Captain.' }],
        actions: [],
        status: 'need_info',
        error: { code: 'PARSE_ERROR', detail: 'Model output not valid JSON' }
    };
}

/**
 * Extract JSON from LLM text response (handles markdown code blocks, extra text)
 */
function extractJSONFromText(text) {
    if (!text || typeof text !== 'string') return null;

    // Try to parse as-is first
    try {
        return JSON.parse(text.trim());
    } catch (e) {
        // Try to extract from markdown code blocks
        const jsonMatch = text.match(/```(?:json)?\s*(\{[\s\S]*\})\s*```/);
        if (jsonMatch) {
            try {
                return JSON.parse(jsonMatch[1]);
            } catch (e2) {
                // Continue to other methods
            }
        }

        // Try to find first valid JSON object
        const braceMatch = text.match(/\{[\s\S]*\}/);
        if (braceMatch) {
            try {
                return JSON.parse(braceMatch[0]);
            } catch (e3) {
                // Continue
            }
        }
    }

    return null;
}

/**
 * Validate v2 response structure
 */
function validateV2Response(obj) {
    if (!obj || typeof obj !== 'object') return false;

    // Required fields
    const required = ['language', 'role', 'intent', 'state', 'message', 'required_inputs', 'actions', 'status'];
    for (const field of required) {
        if (!(field in obj)) return false;
    }

    // Type checks
    if (typeof obj.message !== 'string') return false;
    if (!Array.isArray(obj.required_inputs)) return false;
    if (!Array.isArray(obj.actions)) return false;
    if (!['need_info', 'in_progress', 'submitted', 'waiting_verification', 'resolved', 'escalate', 'error'].includes(obj.status)) return false;

    return true;
}

/**
 * Call LLM and return structured v2 JSON response
 */
async function callLLM_v2(messages, retryCount = 0) {
    const MAX_RETRIES = 3;

    // Enforce minimum interval
    const now = Date.now();
    const timeSinceLastRequest = now - lastRequestTime;
    if (timeSinceLastRequest < MIN_REQUEST_INTERVAL) {
        await new Promise(resolve => setTimeout(resolve, MIN_REQUEST_INTERVAL - timeSinceLastRequest));
    }
    lastRequestTime = Date.now();

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 20000); // 20s timeout for JSON parsing

    try {
        const response = await fetch('https://api.groq.com/openai/v1/chat/completions', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${process.env.GROQ_API_KEY}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                model: 'llama-3.3-70b-versatile',
                messages: messages,
                temperature: 0.3,
                max_tokens: 800, // Increased for JSON output
                // Note: Groq may not support response_format yet, so we parse text
            }),
            signal: controller.signal
        });

        clearTimeout(timeoutId);

        // Handle rate limits
        if (response.status === 429) {
            const retryAfter = parseInt(response.headers.get('retry-after')) || 5;
            logger.warn('Groq rate limit hit (v2)', { retryAfter, retryCount });

            if (retryCount < MAX_RETRIES) {
                await new Promise(resolve => setTimeout(resolve, retryAfter * 1000));
                return callLLM_v2(messages, retryCount + 1);
            }
            throw new Error('RATE_LIMIT_EXCEEDED');
        }

        if (!response.ok) {
            const err = await response.text();
            logger.error('Groq API error (v2)', { status: response.status, error: err });
            throw new Error(`Groq API error: ${response.status}`);
        }

        const data = await response.json();
        const rawText = data.choices[0].message.content;

        // Extract and parse JSON
        const parsed = extractJSONFromText(rawText);

        if (!parsed || !validateV2Response(parsed)) {
            logger.warn('Invalid JSON from LLM (v2)', { rawText: rawText.substring(0, 200) });
            // Return fallback (language detection from first message if available)
            const detectedLang = messages.find(m => m.role === 'user')?.content
                ? detectUserLanguage(messages.find(m => m.role === 'user').content).primary || 'en'
                : 'en';
            return createFallbackV2Response(detectedLang);
        }

        return parsed;

    } catch (error) {
        clearTimeout(timeoutId);

        if (error.name === 'AbortError') {
            logger.error('Groq API timeout (v2)');
            throw new Error('API_TIMEOUT');
        }

        if (error.message === 'RATE_LIMIT_EXCEEDED') {
            throw error;
        }

        if (retryCount < MAX_RETRIES && (error.code === 'ECONNRESET' || error.code === 'ETIMEDOUT')) {
            logger.warn('Network error, retrying (v2)', { error: error.message, retryCount });
            await new Promise(resolve => setTimeout(resolve, 2000));
            return callLLM_v2(messages, retryCount + 1);
        }

        // On final error, return fallback
        logger.error('LLM v2 error, using fallback', { error: error.message });
        const detectedLang = messages.find(m => m.role === 'user')?.content
            ? detectUserLanguage(messages.find(m => m.role === 'user').content).primary || 'en'
            : 'en';
        return createFallbackV2Response(detectedLang);
    }
}

// Queued version for v2
async function callLLM_v2Queued(messages) {
    return llmQueue.add(() => callLLM_v2(messages));
}

// ============================================
// 📝 RESPONSE FORMATTING
// ============================================

/**
 * Format response text to ensure proper newlines for menus
 */
function formatResponseText(text) {
    if (!text || typeof text !== 'string') return text;

    // Check if text contains menu-like patterns (numbered options)
    const menuPattern = /(\d+[-.)]\s*[^\n]+)/g;
    const matches = text.match(menuPattern);

    if (matches && matches.length >= 2) {
        // Menu detected, ensure proper formatting
        let formatted = text;

        // Ensure there's a blank line before first menu item
        formatted = formatted.replace(/([^\n])(\n\s*\d+[-.)])/, '$1\n\n$2');

        // Ensure each menu item is on its own line
        formatted = formatted.replace(/(\d+[-.)]\s*[^\n]+)\s+(\d+[-.)])/g, '$1\n$2');

        return formatted;
    }

    return text;
}

// ============================================
// 💬 BUILD PROMPT
// ============================================

// Topic detection for better responses
function detectTopic(message, userType, lang) {
    const msgLower = message.toLowerCase();

    const topics = {
        captain: {
            earnings: ['earning', 'money', 'pay', 'payout', 'salary', 'فلوس', 'ارباح', 'مرتب', 'تحويل'],
            ratings: ['rating', 'star', 'review', 'تقييم', 'نجوم'],
            acceptance: ['accept', 'decline', 'refuse', 'قبول', 'رفض'],
            cancellation: ['cancel', 'no show', 'الغاء', 'مجاش'],
            incentives: ['bonus', 'incentive', 'surge', 'quest', 'مكافأة', 'حافز', 'زيادة'],
            documents: ['document', 'license', 'expire', 'مستند', 'رخصة', 'انتهاء'],
            vehicle: ['car', 'vehicle', 'inspection', 'عربية', 'سيارة', 'فحص'],
            support: ['help', 'support', 'contact', 'مساعدة', 'دعم', 'تواصل'],
            safety: ['safe', 'danger', 'accident', 'أمان', 'خطر', 'حادث'],
            account: ['account', 'suspend', 'block', 'حساب', 'ايقاف', 'حظر']
        },
        customer: {
            booking: ['book', 'ride', 'order', 'احجز', 'رحلة', 'طلب'],
            cancel: ['cancel', 'الغ', 'الغاء'],
            payment: ['pay', 'card', 'cash', 'wallet', 'دفع', 'كارت', 'كاش', 'محفظة'],
            pricing: ['price', 'cost', 'expensive', 'surge', 'سعر', 'غالي', 'زيادة'],
            safety: ['safe', 'danger', 'emergency', 'أمان', 'خطر', 'طوارئ'],
            refund: ['refund', 'money back', 'charge', 'استرداد', 'رجع', 'فلوس'],
            lost: ['lost', 'forgot', 'left', 'نسيت', 'سيبت', 'فقدت'],
            complaint: ['complain', 'report', 'bad', 'شكوى', 'بلاغ', 'وحش'],
            promo: ['promo', 'code', 'discount', 'كود', 'خصم'],
            account: ['account', 'password', 'phone', 'حساب', 'باسورد', 'موبايل'],
            driver: ['driver', 'captain', 'late', 'سواق', 'كابتن', 'اتأخر']
        }
    };

    const userTopics = userType ? topics[userType] : { ...topics.captain, ...topics.customer };

    for (const [topic, keywords] of Object.entries(userTopics)) {
        for (const keyword of keywords) {
            if (msgLower.includes(keyword)) {
                return topic;
            }
        }
    }

    return null;
}

function buildMessages(userMessage, ride, history, lang, userId) {
    const selectedLang = (lang === 'mixed' || lang === 'unknown' || lang === 'arabizi') ?
        (lang === 'arabizi' ? 'ar' : 'en') : lang;

    const systemPrompt = selectedLang === 'ar' ? PROMPT_AR : PROMPT_EN;

    // Detect or get user type
    let userType = getUserType(userId);
    const detectedType = detectUserType(userMessage, userId);

    if (detectedType && !userType) {
        userType = detectedType;
    }

    // Build dynamic context
    let context = '\n\n---\nCONTEXT:';

    // User type context
    if (userType) {
        const typeLabel = userType === 'captain' ?
            (selectedLang === 'ar' ? 'كابتن (سواق)' : 'Captain (Driver)') :
            (selectedLang === 'ar' ? 'عميل' : 'Customer');
        context += selectedLang === 'ar'
            ? `\n• نوع المستخدم: ${typeLabel}`
            : `\n• User Type: ${typeLabel}`;
    } else {
        context += selectedLang === 'ar'
            ? '\n• نوع المستخدم: غير معروف - اسأل "إنت كابتن ولا عميل؟"'
            : '\n• User Type: Unknown - Ask "Are you a Captain or Customer?"';
    }

    // Conversation context
    const messageCount = history.length;
    if (messageCount === 0) {
        context += selectedLang === 'ar'
            ? '\n• حالة المحادثة: محادثة جديدة'
            : '\n• Conversation: New conversation';
    } else if (messageCount > 6) {
        context += selectedLang === 'ar'
            ? '\n• حالة المحادثة: محادثة طويلة - كن مختصر ومباشر'
            : '\n• Conversation: Long conversation - be concise and direct';
    }

    // Active ride context (for customers only)
    if (userType === 'customer' && ride) {
        if (['ongoing', 'accepted', 'arrived', 'en_route', 'pending'].includes(ride.status)) {
            context += selectedLang === 'ar'
                ? `\n• رحلة نشطة: الكابتن ${ride.driver_name}، من ${ride.pickup} إلى ${ride.destination} (${ride.status})`
                : `\n• Active Ride: Captain ${ride.driver_name}, from ${ride.pickup} to ${ride.destination} (${ride.status})`;
        }
    } else if (userType === 'customer') {
        context += selectedLang === 'ar'
            ? '\n• الرحلات: مفيش رحلة نشطة'
            : '\n• Rides: No active ride';
    }

    // Add relevant knowledge hints based on detected topic
    const topicHint = detectTopic(userMessage, userType, selectedLang);
    if (topicHint) {
        context += `\n• Topic Hint: ${topicHint}`;
    }

    context += '\n---';
    const messages = [
        { role: 'system', content: systemPrompt + context }
    ];
    // Add conversation history (limited to last 4 for performance)
    for (const msg of history.slice(-4)) {
        messages.push({ role: msg.role, content: msg.content });
    }
    // Add current message
    messages.push({ role: 'user', content: userMessage });
    return { messages, lang: selectedLang, userType };
}

// ============================================
// 🚀 MAIN CHAT ENDPOINT
// ============================================

/**
 * Helper: Standardize language response format (always return object)
 */
function formatLanguageResponse(langResult, defaultLang = 'en') {
    if (langResult && typeof langResult === 'object' && langResult.primary) {
        return langResult;
    }

    // Fallback: create object from string or default
    const lang = typeof langResult === 'string' ? langResult : defaultLang;
    return {
        primary: lang,
        confidence: lang === 'unknown' ? 0 : 0.8,
        arabicRatio: lang === 'ar' ? 1.0 : 0,
        latinRatio: lang === 'en' ? 1.0 : 0,
        hasArabizi: false
    };
}

// Helper function to record violation
async function recordViolation(userId, violationType) {
    try {
        await pool.execute(
            'INSERT INTO user_violations (user_id, violation_type) VALUES (?, ?)',
            [userId, violationType]
        );
    } catch (error) {
        logError(error, { userId, violationType, function: 'recordViolation' });
    }
}

// Helper function to check violation count
async function getViolationCount(userId) {
    try {
        const [rows] = await pool.execute(
            'SELECT COUNT(*) as count FROM user_violations WHERE user_id = ?',
            [userId]
        );
        return rows[0].count || 0;
    } catch (error) {
        logError(error, { userId, function: 'getViolationCount' });
        return 0;
    }
}

// ============================================
// 🔧 V2 HELPER FUNCTIONS
// ============================================

/**
 * Build messages for v2 (structured JSON) system
 */
function buildMessagesV2(userMessage, ride, history, lang, userRole, userState) {
    const systemPrompt = PROMPT_V2;

    let context = '';

    // Add role context if known
    if (userRole) {
        context += `\n\n[User role: ${userRole}]`;
    }

    // Add state context
    if (userState && userState !== 'start') {
        context += `\n\n[Current state: ${userState}]`;
    }

    // Add ride context
    if (ride && ['ongoing', 'accepted', 'arrived', 'en_route', 'pending'].includes(ride.status)) {
        context += `\n\n[Active ride: trip_id=${ride.id}, driver=${ride.driver_name}, pickup=${ride.pickup}, destination=${ride.destination}, status=${ride.status}]`;
    } else {
        context += `\n\n[No active ride]`;
    }

    const messages = [
        { role: 'system', content: systemPrompt + context }
    ];

    // Add conversation history (last 6 messages for v2 context)
    for (const msg of history) {
        messages.push({ role: msg.role, content: msg.content });
    }

    // Add current message
    messages.push({ role: 'user', content: userMessage });

    return messages;
}

/**
 * Generate v2 response (core logic used by both /chat and /chat/v2)
 */
async function generateV2Response(user_id, userText, detectedLang, preferredLang, ride, history) {
    // Get or detect role
    let userRole = await getUserRole(user_id);
    const detectedRole = detectRoleFromMessage(userText);

    if (detectedRole && !userRole) {
        userRole = detectedRole;
        await setUserRole(user_id, userRole);
    }

    // Get user state
    const userStateData = getUserState(user_id);
    const currentState = userStateData.state || 'start';

    // Determine language to use
    const langToUse = preferredLang || detectedLang;

    // Build messages
    const messages = buildMessagesV2(userText, ride, history, langToUse, userRole, currentState);

    // Call LLM v2
    const v2Response = await callLLM_v2Queued(messages);

    // Update user state based on response
    if (v2Response.role && v2Response.role !== 'unknown' && v2Response.role !== userRole) {
        await setUserRole(user_id, v2Response.role);
    }

    // Update state
    setUserState(user_id, {
        role: v2Response.role,
        state: v2Response.state
    });

    // Save to history (save the message field as assistant reply)
    await saveChat(user_id, userText, v2Response.message);

    return v2Response;
}

/**
 * Convert v2 response to legacy format
 */
function v2ToLegacyFormat(v2Response, langResult, detectedLang) {
    const legacyLang = formatLanguageResponse(langResult, detectedLang);

    // Map v2.status to legacy.handoff
    const handoff = v2Response.status === 'escalate' ||
        (v2Response.actions && v2Response.actions.some(a => a.type === 'ESCALATE_TO_HUMAN'));

    return {
        reply: v2Response.message || 'Specify role: Customer or Captain.',
        confidence: v2Response.status === 'error' ? 0 : 0.85,
        handoff: handoff,
        language: legacyLang,
        model: 'Llama 3.3 70B',
        // Optionally include v2 data for debugging (won't break frontend)
        v2: v2Response
    };
}

// Input validation rules for chat endpoint
// Note: We don't escape() message to preserve Arabic characters
// Sanitization happens in moderation.js if needed
const chatValidation = [
    body('user_id').notEmpty().withMessage('user_id is required').trim().escape(),
    body('message').notEmpty().withMessage('message is required').isLength({ min: 1, max: 500 }).withMessage('message must be between 1 and 500 characters').trim()
    // Note: .trim() is safe for Arabic, but we avoid .escape() which would HTML-encode Arabic characters
];

// ============================================
// 🚀 CHAT V2 ENDPOINT (STRUCTURED JSON)
// ============================================

app.post('/chat/v2', chatRateLimiter, chatValidation, async (req, res) => {
    try {
        const errors = validationResult(req);
        if (!errors.isEmpty()) {
            return res.status(400).json({
                language: 'en',
                role: 'unknown',
                intent: 'unknown',
                state: 'error_state',
                message: 'Invalid request. Please check your input.',
                required_inputs: [],
                actions: [],
                status: 'error',
                error: { code: 'VALIDATION_ERROR', detail: errors.array().map(e => e.msg).join(', ') }
            });
        }

        const { user_id, message } = req.body;
        const userText = typeof message === 'string' ? message.substring(0, 500).trim() : String(message).substring(0, 500).trim();

        // Repeated message check
        if (isRepeatedMessage(user_id, userText)) {
            const langResult = detectUserLanguage(userText);
            const detectedLang = langResult.primary || 'en';
            return res.json(createFallbackV2Response(detectedLang));
        }

        // Language gate
        const langResult = detectUserLanguage(userText);
        const detectedLang = langResult.primary;

        if (detectedLang === 'unknown') {
            return res.json(createFallbackV2Response('en'));
        }

        // Language locking (optional, can be skipped for v2)
        await ensureUser(user_id);
        const preferredLang = await getUserPreferredLanguage(user_id);
        if (!preferredLang && (detectedLang === 'ar' || detectedLang === 'arabizi' || detectedLang === 'en')) {
            await setUserPreferredLanguage(user_id, detectedLang === 'arabizi' ? 'ar' : detectedLang);
        }

        // Moderation gate
        const profanityCheck = checkProfanity(userText);
        if (profanityCheck.flagged) {
            await recordViolation(user_id, 'profanity');
            const violationCount = await getViolationCount(user_id);

            if (violationCount >= 3) {
                return res.json({
                    language: detectedLang,
                    role: 'unknown',
                    intent: 'unknown',
                    state: 'error_state',
                    message: detectedLang === 'ar'
                        ? 'تم حظرك مؤقتاً لاستخدام لغة غير لائقة.'
                        : 'You have been temporarily blocked for inappropriate language.',
                    required_inputs: [],
                    actions: [{ type: 'ESCALATE_TO_HUMAN', payload: { reason: 'abuse_block' } }],
                    status: 'escalate',
                    error: { code: 'BLOCKED', detail: 'Inappropriate language' }
                });
            }

            const reply = escalationReply(detectedLang === 'ar' ? 'ar' : 'en', profanityCheck.severity);
            return res.json({
                language: detectedLang,
                role: 'unknown',
                intent: 'unknown',
                state: 'error_state',
                message: reply,
                required_inputs: [],
                actions: profanityCheck.severity === 'high' || violationCount >= 2
                    ? [{ type: 'ESCALATE_TO_HUMAN', payload: { reason: 'abuse' } }]
                    : [],
                status: 'escalate',
                error: null
            });
        }

        // Get ride and history
        const ride = await getRide(user_id);
        const history = await getChatHistory(user_id);

        // Generate v2 response
        const v2Response = await generateV2Response(user_id, userText, detectedLang, preferredLang, ride, history);

        // Return v2 format
        res.json(v2Response);

    } catch (error) {
        logError(error, { endpoint: '/chat/v2', user_id: req.body?.user_id });

        const langResult = req.body?.message ? detectUserLanguage(req.body.message) : { primary: 'en' };
        const detectedLang = langResult.primary || 'en';

        res.status(500).json({
            language: detectedLang,
            role: 'unknown',
            intent: 'unknown',
            state: 'error_state',
            message: detectedLang === 'ar'
                ? 'عذراً، حصل خطأ. حاول تاني.'
                : 'Sorry, an error occurred. Please try again.',
            required_inputs: [],
            actions: [],
            status: 'error',
            error: { code: 'INTERNAL_ERROR', detail: error.message }
        });
    }
});

// ============================================
// 🚀 MAIN CHAT ENDPOINT (LEGACY - BACKWARD COMPATIBLE)
// ============================================

app.post('/chat', chatRateLimiter, chatValidation, async (req, res) => {
    try {
        // Check validation errors
        const errors = validationResult(req);
        if (!errors.isEmpty()) {
            return res.status(400).json({
                reply: 'Invalid request. Please check your input.',
                error: 'VALIDATION_ERROR',
                details: errors.array(),
                confidence: 0,
                handoff: false,
                language: formatLanguageResponse(null, 'en'),
                model: 'System'
            });
        }

        const { user_id, message } = req.body;

        // Performance: Cap user message length (500 chars - already validated, but double-check)
        const userText = typeof message === 'string' ? message.substring(0, 500).trim() : String(message).substring(0, 500).trim();

        // ============================================
        // REPEATED MESSAGE DETECTION (PRE-LLM)
        // ============================================
        if (isRepeatedMessage(user_id, userText)) {
            const langResult = detectUserLanguage(userText);
            const langObj = formatLanguageResponse(langResult);
            const lang = langObj.primary;
            const reply = lang === 'ar'
                ? 'تم إرسال هذه الرسالة مؤخراً. من فضلك انتظر قليلاً قبل إعادة المحاولة.'
                : 'This message was recently sent. Please wait a moment before trying again.';

            return res.json({
                reply: reply,
                language: langObj,
                handoff: false,
                blocked: true,
                error: 'REPEATED_MESSAGE',
                confidence: 0,
                model: 'System'
            });
        }

        // ============================================
        // A) LANGUAGE GATE (PRE-LLM)
        // ============================================
        const langResult = detectUserLanguage(userText);
        const detectedLang = langResult.primary;
        const langObj = formatLanguageResponse(langResult);

        // Debug: Log language detection for troubleshooting
        if (process.env.NODE_ENV !== 'production') {
            logger.info('Language detection', {
                text: userText.substring(0, 50),
                detected: detectedLang,
                confidence: langResult.confidence
            });
        }

        if (detectedLang === 'unknown') {
            const reply = languageGuardReply({ detectedLang: null });
            return res.json({
                reply: reply,
                language: formatLanguageResponse(null, 'en'),
                handoff: false,
                blocked: true,
                confidence: 0,
                model: 'System'
            });
        }

        // ============================================
        // A1) LANGUAGE LOCKING (SESSION LOCK)
        // ============================================
        await ensureUser(user_id);
        const preferredLang = await getUserPreferredLanguage(user_id);

        // Lock language on first valid message
        if (!preferredLang && (detectedLang === 'ar' || detectedLang === 'arabizi' || detectedLang === 'en')) {
            await setUserPreferredLanguage(user_id, detectedLang);
            logger.info('Language locked', { user_id, language: detectedLang });
        }

        // Check language lock violation
        if (preferredLang) {
            const currentLang = (detectedLang === 'ar' || detectedLang === 'arabizi') ? 'ar' : 'en';

            // If user locked to Arabic but message is clearly English
            if (preferredLang === 'ar' && detectedLang === 'en' && langResult.latinRatio > 0.8) {
                return res.json({
                    reply: 'من فضلك استمر بالعربية فقط. / Please continue in Arabic only.',
                    language: formatLanguageResponse({ primary: 'ar', confidence: 1.0, arabicRatio: 1.0, latinRatio: 0, hasArabizi: false }),
                    handoff: false,
                    blocked: true,
                    confidence: 0,
                    model: 'System'
                });
            }

            // If user locked to English but message is clearly Arabic
            if (preferredLang === 'en' && detectedLang === 'ar' && langResult.arabicRatio > 0.8) {
                return res.json({
                    reply: 'Please continue in English only. / من فضلك استمر بالإنجليزية فقط.',
                    language: formatLanguageResponse({ primary: 'en', confidence: 1.0, arabicRatio: 0, latinRatio: 1.0, hasArabizi: false }),
                    handoff: false,
                    blocked: true,
                    confidence: 0,
                    model: 'System'
                });
            }
        }

        // ============================================
        // B) MODERATION GATE (PRE-LLM)
        // ============================================
        const profanityCheck = checkProfanity(userText);

        if (profanityCheck.flagged) {
            // Record violation
            await recordViolation(user_id, 'profanity');

            // Check violation count (optional: block after 3 violations)
            const violationCount = await getViolationCount(user_id);
            if (violationCount >= 3) {
                const blockReply = detectedLang === 'ar'
                    ? 'تم حظرك مؤقتاً لاستخدام لغة غير لائقة. سيتم رفع الحظر بعد 30 دقيقة. تم تحويلك للدعم.'
                    : 'You have been temporarily blocked for inappropriate language. Block will be lifted after 30 minutes. You have been connected to support.';

                return res.json({
                    reply: blockReply,
                    language: formatLanguageResponse(langResult),
                    handoff: true,
                    blocked: true,
                    severity: profanityCheck.severity,
                    escalation_reason: 'abuse',
                    confidence: 0,
                    model: 'System'
                });
            }

            // Return escalation response immediately
            const reply = escalationReply(detectedLang === 'ar' ? 'ar' : 'en', profanityCheck.severity);
            const handoff = profanityCheck.severity === 'high' || violationCount >= 2;

            return res.json({
                reply: reply,
                language: formatLanguageResponse(langResult),
                handoff: handoff,
                blocked: true,
                severity: profanityCheck.severity,
                escalation_reason: 'abuse',
                confidence: 0,
                model: 'System'
            });
        }

        // ============================================
        // C) RESPONSE CACHING (PRE-LLM)
        // ============================================
        const cachedResponse = getCache(userText, user_id);
        if (cachedResponse) {
            // Ensure cached response has standardized language format (backward compatibility)
            if (!cachedResponse.language || typeof cachedResponse.language !== 'object') {
                cachedResponse.language = formatLanguageResponse(langResult, detectedLang);
            }
            return res.json(cachedResponse);
        }

        // ============================================
        // D) ONLY IF CLEAN - Use v2 logic but return legacy format
        // ============================================

        // Get ride and history
        const ride = await getRide(user_id);
        const history = await getChatHistory(user_id);

        // Generate v2 response (shared logic)
        const v2Response = await generateV2Response(user_id, userText, detectedLang, preferredLang, ride, history);

        // Convert v2 to legacy format for backward compatibility
        const legacyResponse = v2ToLegacyFormat(v2Response, langResult, detectedLang);

        // Cache the legacy response (for backward compatibility)
        setCache(userText, user_id, legacyResponse);

        // Return legacy format (frontend expects this)
        res.json(legacyResponse);

    } catch (error) {
        logError(error, { endpoint: '/chat', user_id: req.body?.user_id });

        const langResult = req.body?.message ? detectUserLanguage(req.body.message) : { primary: 'en' };
        const userLang = langResult.primary || 'en';

        let errorMessage;
        let shouldHandoff = false;

        if (error.message === 'RATE_LIMIT_EXCEEDED') {
            errorMessage = userLang === 'ar'
                ? 'الخدمة مشغولة دلوقتي. استنى ثواني وحاول تاني.'
                : 'Service is busy. Please wait a few seconds and try again.';
        } else if (error.message === 'API_TIMEOUT') {
            errorMessage = userLang === 'ar'
                ? 'الرد أخد وقت طويل. حاول تاني.'
                : 'Response took too long. Please try again.';
        } else {
            errorMessage = userLang === 'ar'
                ? 'عذراً، حصل خطأ. حاول تاني.'
                : 'Sorry, an error occurred. Please try again.';
            shouldHandoff = true;
        }

        res.status(500).json({
            reply: errorMessage,
            confidence: 0,
            handoff: shouldHandoff,
            language: formatLanguageResponse(langResult, userLang),
            model: 'System',
            error: error.message
        });
    }
});

// ============================================
// 🔧 ADMIN ENDPOINTS
// ============================================

// Admin input validation
const adminCreateRideValidation = [
    body('user_id').notEmpty().withMessage('user_id is required').trim().escape(),
    body('driver_name').optional().trim().escape(),
    body('pickup').optional().trim().escape(),
    body('destination').optional().trim().escape(),
    body('status').optional().isIn(['ongoing', 'completed', 'cancelled', 'pending']).withMessage('Invalid status')
];

const adminUpdateRideValidation = [
    body('user_id').notEmpty().withMessage('user_id is required').trim().escape(),
    body('status').notEmpty().isIn(['ongoing', 'completed', 'cancelled', 'pending']).withMessage('Invalid status')
];

const adminClearMemoryValidation = [
    body('user_id').notEmpty().withMessage('user_id is required').trim().escape()
];

// Apply admin authentication to all admin routes
app.use('/admin/*', adminAuth);

app.post('/admin/clear-memory', adminClearMemoryValidation, async (req, res) => {
    try {
        const errors = validationResult(req);
        if (!errors.isEmpty()) {
            return res.status(400).json({ success: false, error: 'Validation failed', details: errors.array() });
        }

        const { user_id } = req.body;
        await clearChat(user_id);
        logger.info('Chat memory cleared', { user_id });
        res.json({ success: true });
    } catch (error) {
        logError(error, { endpoint: '/admin/clear-memory', user_id: req.body?.user_id });
        res.status(500).json({ success: false, error: error.message });
    }
});

app.post('/admin/create-ride', adminCreateRideValidation, async (req, res) => {
    try {
        const errors = validationResult(req);
        if (!errors.isEmpty()) {
            return res.status(400).json({ success: false, error: 'Validation failed', details: errors.array() });
        }

        const { ride_id, user_id, driver_name, pickup, destination, status = 'ongoing' } = req.body;
        await ensureUser(user_id);

        // Delete existing rides for this user
        await pool.execute('DELETE FROM rides WHERE user_id = ?', [user_id]);

        await pool.execute(
            'INSERT INTO rides (id, user_id, driver_name, pickup, destination, status) VALUES (?, ?, ?, ?, ?, ?)',
            [ride_id || `r_${Date.now()}`, user_id, driver_name, pickup, destination, status]
        );
        logger.info('Ride created', { ride_id: ride_id || `r_${Date.now()}`, user_id });
        res.json({ success: true });
    } catch (error) {
        logError(error, { endpoint: '/admin/create-ride', user_id: req.body?.user_id });
        res.status(500).json({ success: false, error: error.message });
    }
});

app.post('/admin/update-ride', adminUpdateRideValidation, async (req, res) => {
    try {
        const errors = validationResult(req);
        if (!errors.isEmpty()) {
            return res.status(400).json({ success: false, error: 'Validation failed', details: errors.array() });
        }

        const { user_id, status } = req.body;
        await pool.execute('UPDATE rides SET status = ? WHERE user_id = ?', [status, user_id]);
        if (status === 'completed') {
            await clearChat(user_id);
        }
        logger.info('Ride updated', { user_id, status });
        res.json({ success: true });
    } catch (error) {
        logError(error, { endpoint: '/admin/update-ride', user_id: req.body?.user_id });
        res.status(500).json({ success: false, error: error.message });
    }
});

app.post('/admin/reset-all', async (req, res) => {
    try {
        await pool.execute('DELETE FROM chat_history');
        await pool.execute('DELETE FROM rides');
        userTypes.clear(); // Clear all user types
        lastMessages.clear(); // Clear last messages
        logger.warn('All data reset by admin');
        res.json({ success: true, message: 'All reset' });
    } catch (error) {
        logError(error, { endpoint: '/admin/reset-all' });
        res.status(500).json({ success: false, error: error.message });
    }
});

// Clear user type (for testing)
app.post('/admin/clear-user-type', adminClearMemoryValidation, async (req, res) => {
    try {
        const errors = validationResult(req);
        if (!errors.isEmpty()) {
            return res.status(400).json({ success: false, error: 'Validation failed', details: errors.array() });
        }
        const { user_id } = req.body;
        clearUserType(user_id);
        logger.info('User type cleared', { user_id });
        res.json({ success: true, message: 'User type cleared' });
    } catch (error) {
        logError(error, { endpoint: '/admin/clear-user-type' });
        res.status(500).json({ success: false, error: error.message });
    }
});

// Set user type manually
app.post('/admin/set-user-type', adminAuth, async (req, res) => {
    try {
        const { user_id, type } = req.body;
        if (!user_id || !type) {
            return res.status(400).json({ success: false, error: 'user_id and type required' });
        }
        if (type !== 'captain' && type !== 'customer') {
            return res.status(400).json({ success: false, error: 'type must be captain or customer' });
        }
        setUserType(user_id, type);
        res.json({ success: true, message: `User type set to ${type}` });
    } catch (error) {
        logError(error, { endpoint: '/admin/set-user-type' });
        res.status(500).json({ success: false, error: error.message });
    }
});

// Get user info including type
app.get('/admin/user-info/:user_id', adminAuth, async (req, res) => {
    try {
        const { user_id } = req.params;
        const userType = getUserType(user_id);
        const [userRows] = await pool.execute('SELECT * FROM users WHERE id = ?', [user_id]);
        const [violationRows] = await pool.execute(
            'SELECT COUNT(*) as count FROM user_violations WHERE user_id = ?',
            [user_id]
        );
        const [historyRows] = await pool.execute(
            'SELECT COUNT(*) as count FROM chat_history WHERE user_id = ?',
            [user_id]
        );

        res.json({
            success: true,
            user: userRows[0] || null,
            userType: userType,
            violations: violationRows[0].count,
            messageCount: historyRows[0].count
        });
    } catch (error) {
        logError(error, { endpoint: '/admin/user-info' });
        res.status(500).json({ success: false, error: error.message });
    }
});

// Full user reset
app.post('/admin/reset-user', adminClearMemoryValidation, async (req, res) => {
    try {
        const errors = validationResult(req);
        if (!errors.isEmpty()) {
            return res.status(400).json({ success: false, error: 'Validation failed', details: errors.array() });
        }
        const { user_id } = req.body;

        // Clear all user data
        await pool.execute('DELETE FROM chat_history WHERE user_id = ?', [user_id]);
        await pool.execute('DELETE FROM user_violations WHERE user_id = ?', [user_id]);
        await pool.execute('UPDATE users SET preferred_language = NULL WHERE id = ?', [user_id]);

        // Clear from memory
        clearUserType(user_id);
        lastMessages.delete(user_id);

        logger.info('User fully reset', { user_id });
        res.json({ success: true, message: 'User fully reset including type, language, history, and violations' });
    } catch (error) {
        logError(error, { endpoint: '/admin/reset-user' });
        res.status(500).json({ success: false, error: error.message });
    }
});

// System stats
app.get('/admin/stats', adminAuth, async (req, res) => {
    try {
        const [userCount] = await pool.execute('SELECT COUNT(*) as count FROM users');
        const [messageCount] = await pool.execute('SELECT COUNT(*) as count FROM chat_history');
        const [violationCount] = await pool.execute('SELECT COUNT(*) as count FROM user_violations');
        const [rideCount] = await pool.execute('SELECT COUNT(*) as count FROM rides');

        const memUsage = process.memoryUsage();

        res.json({
            success: true,
            stats: {
                users: userCount[0].count,
                messages: messageCount[0].count,
                violations: violationCount[0].count,
                rides: rideCount[0].count,
                userTypesInMemory: userTypes.size,
                lastMessagesInMemory: lastMessages.size,
                memory: {
                    heapUsed: Math.round(memUsage.heapUsed / 1024 / 1024) + 'MB',
                    heapTotal: Math.round(memUsage.heapTotal / 1024 / 1024) + 'MB',
                    rss: Math.round(memUsage.rss / 1024 / 1024) + 'MB'
                },
                uptime: Math.round(process.uptime()) + 's'
            }
        });
    } catch (error) {
        logError(error, { endpoint: '/admin/stats' });
        res.status(500).json({ success: false, error: error.message });
    }
});

// ============================================
// 🏥 HEALTH CHECK
// ============================================

app.get('/health', async (req, res) => {
    try {
        await pool.execute('SELECT 1');
        const memUsage = process.memoryUsage();
        res.json({
            status: 'ok',
            database: 'connected',
            memory: {
                heapUsed: Math.round(memUsage.heapUsed / 1024 / 1024) + 'MB',
                heapTotal: Math.round(memUsage.heapTotal / 1024 / 1024) + 'MB',
                rss: Math.round(memUsage.rss / 1024 / 1024) + 'MB'
            },
            lastMessagesMapSize: lastMessages.size,
            uptime: Math.round(process.uptime()) + 's'
        });
    } catch (error) {
        logError(error, { endpoint: '/health' });
        res.status(500).json({ status: 'error', database: 'disconnected' });
    }
});

// ============================================
// ⚠️ ERROR HANDLING MIDDLEWARE
// ============================================

// Centralized error handling
app.use((err, req, res, next) => {
    logError(err, {
        method: req.method,
        path: req.path,
        body: req.body
    });

    res.status(err.status || 500).json({
        success: false,
        error: process.env.NODE_ENV === 'production'
            ? 'Internal server error'
            : err.message,
        code: err.code || 'INTERNAL_ERROR'
    });
});

// ============================================
// 🛡️ GLOBAL ERROR HANDLERS
// ============================================

// Handle unhandled promise rejections
process.on('unhandledRejection', (reason, promise) => {
    logger.error('Unhandled Rejection', {
        reason: reason?.message || String(reason),
        stack: reason?.stack
    });
    // Don't crash - log and continue
});

// Handle uncaught exceptions
process.on('uncaughtException', (err) => {
    logger.error('Uncaught Exception', {
        error: err.message,
        stack: err.stack
    });
    // For critical errors, attempt graceful shutdown
    gracefulShutdown('UNCAUGHT_EXCEPTION');
});

// ============================================
// 🔄 GRACEFUL SHUTDOWN
// ============================================

let server; // Store server reference
let isShuttingDown = false;

async function gracefulShutdown(signal) {
    if (isShuttingDown) {
        logger.warn('Shutdown already in progress');
        return;
    }
    isShuttingDown = true;

    logger.info(`${signal} received, starting graceful shutdown...`);

    // Stop accepting new connections
    if (server) {
        server.close(async () => {
            logger.info('HTTP server closed');

            // Close database pool
            if (pool) {
                try {
                    await pool.end();
                    logger.info('Database pool closed');
                } catch (err) {
                    logger.error('Error closing database pool', { error: err.message });
                }
            }

            // Clear in-memory data
            lastMessages.clear();
            logger.info('In-memory data cleared');

            logger.info('Graceful shutdown complete');
            process.exit(0);
        });
    }

    // Force shutdown after 30 seconds
    setTimeout(() => {
        logger.error('Forced shutdown after timeout');
        process.exit(1);
    }, 30000);
}

process.on('SIGTERM', () => gracefulShutdown('SIGTERM'));
process.on('SIGINT', () => gracefulShutdown('SIGINT'));

// ============================================
// 🚀 START
// ============================================

const PORT = process.env.PORT || 3000;

async function start() {
    try {
        await initDatabase();

        // Store server reference for graceful shutdown
        server = app.listen(PORT, () => {
            logger.info(`Server started on port ${PORT}`, {
                port: PORT,
                env: process.env.NODE_ENV || 'development'
            });
            console.log(`
╔════════════════════════════════════════════╗
║  🚗 RIDE SUPPORT - Customer Service Bot    ║
║  ─────────────────────────────────────────║
║  Server: http://localhost:${PORT}             ║
║  Model: Llama 3.3 70B (Groq)              ║
║  Languages: English + Arabic (Egyptian)   ║
╚════════════════════════════════════════════╝
            `);
        });

    } catch (error) {
        logError(error, { function: 'start' });
        console.error('Failed to start:', error);
        process.exit(1);
    }
}

start();
