// ============================================
// 🚗 SMARTLINE AI CHATBOT V3
// Production-Ready + Flutter Actions
// ============================================

const express = require('express');
const cors = require('cors');
const mysql = require('mysql2/promise');
const path = require('path');
const rateLimit = require('express-rate-limit');
const { body, validationResult } = require('express-validator');
const morgan = require('morgan');
require('dotenv').config();

// Utilities
const { logger, logRequest, logError } = require('./utils/logger');
const { adminAuth } = require('./utils/auth');
const responseCache = require('./utils/cache');
const { checkProfanity, detectUserLanguage } = require('./utils/moderation');
const { escalationReply, languageGuardReply } = require('./utils/escalationMessages');

// Flutter Actions
const { ACTION_TYPES, UI_HINTS, ActionBuilders } = require('./actions');

const app = express();
app.use(express.json());
app.use(cors());
app.use(express.static(path.join(__dirname, 'public')));

// HTTP logging
morgan.token('user-id', (req) => req.body?.user_id || '-');
const morganFormat = process.env.NODE_ENV === 'production' ? 'combined' : ':method :url :status :response-time ms';
app.use(morgan(morganFormat, { stream: { write: (message) => logger.info(message.trim()) } }));

// Response time tracking
app.use((req, res, next) => {
    const start = Date.now();
    res.on('finish', () => logRequest(req, res, Date.now() - start));
    next();
});

// ============================================
// 🛡️ RATE LIMITING
// ============================================

const rateLimitMax = process.env.NODE_ENV === 'production' ? 10 : 30;
const chatRateLimiter = rateLimit({
    windowMs: 60 * 1000,
    max: rateLimitMax,
    message: (req) => {
        const lang = detectUserLanguage(req.body?.message || '').primary;
        return {
            message: lang === 'ar' ? 'طلبات كتير. استنى شوية.' : 'Too many requests. Please wait.',
            action: ACTION_TYPES.NONE,
            error: 'RATE_LIMIT_EXCEEDED',
            retryAfter: 60
        };
    },
    keyGenerator: (req) => req.body?.user_id || req.ip,
    skip: (req) => req.path.startsWith('/admin') || req.path === '/health'
});

// ============================================
// 🗄️ DATABASE (Resilient)
// ============================================

const DB_CONFIG = {
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'merged2',
    waitForConnections: true,
    connectionLimit: parseInt(process.env.DB_POOL_SIZE) || 20,
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

        pool.on('error', (err) => {
            logger.error('Database pool error', { error: err.message });
            if (err.code === 'PROTOCOL_CONNECTION_LOST') reconnectDatabase();
        });

        // Create tables
        await pool.execute(`
            CREATE TABLE IF NOT EXISTS ai_chat_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(36) NOT NULL,
                role VARCHAR(20) NOT NULL,
                content TEXT NOT NULL,
                action_type VARCHAR(50) NULL,
                action_data JSON NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_created (user_id, created_at)
            )
        `);

        await pool.execute(`
            CREATE TABLE IF NOT EXISTS ai_conversation_state (
                user_id VARCHAR(36) PRIMARY KEY,
                current_state VARCHAR(50) NOT NULL DEFAULT 'START',
                flow_data JSON NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        `);

        await pool.execute(`
            CREATE TABLE IF NOT EXISTS users (
                id VARCHAR(50) PRIMARY KEY,
                preferred_language VARCHAR(10) NULL,
                user_role VARCHAR(20) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        `);

        dbRetryCount = 0;
        logger.info('✅ Database connected & initialized');
    } catch (err) {
        logger.error('❌ Database initialization failed', { error: err.message });
        if (dbRetryCount < MAX_DB_RETRIES) {
            dbRetryCount++;
            const delay = Math.pow(2, dbRetryCount) * 1000;
            logger.info(`Retrying database connection in ${delay}ms (attempt ${dbRetryCount})`);
            setTimeout(initDatabase, delay);
        }
    }
}

async function reconnectDatabase() {
    logger.info('Attempting database reconnection...');
    if (pool) try { await pool.end(); } catch (e) { }
    await initDatabase();
}

// ============================================
// 🧠 USER TYPE DETECTION (Captain/Customer)
// ============================================

const userTypes = new Map();
const MAX_USER_TYPES = 50000;

const USER_TYPE_KEYWORDS = {
    captain: {
        strong: ['driver', 'captain', 'كابتن', 'سائق', 'earnings', 'acceptance rate', 'my vehicle', 'الأرباح', 'معدل القبول'],
        weak: ['trip request', 'passenger', 'pickup customer', 'راكب', 'طلب رحلة']
    },
    customer: {
        strong: ['rider', 'customer', 'راكب', 'عميل', 'book a ride', 'driver is late', 'أحجز رحلة', 'السواق متأخر'],
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
    return userTypes.get(userId)?.type || null;
}

function setUserType(userId, type) {
    if (userTypes.size >= MAX_USER_TYPES) {
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
const REPEATED_MSG_WINDOW = 30000;

setInterval(() => {
    const now = Date.now();
    for (const [userId, data] of userTypes.entries()) {
        if (now - data.timestamp > 86400000) userTypes.delete(userId);
    }
    for (const [userId, data] of lastMessages.entries()) {
        if (now - data.timestamp > 300000) lastMessages.delete(userId);
    }
}, 600000);

function isRepeatedMessage(userId, message) {
    const last = lastMessages.get(userId);
    if (last && last.message === message && (Date.now() - last.timestamp) < REPEATED_MSG_WINDOW) {
        return true;
    }
    if (lastMessages.size >= MAX_LAST_MESSAGES) {
        const oldest = lastMessages.keys().next().value;
        lastMessages.delete(oldest);
    }
    lastMessages.set(userId, { message, timestamp: Date.now() });
    return false;
}

// ============================================
// 🎯 SYSTEM PROMPT
// ============================================

let cachedSystemPrompt = null;
let promptCacheTime = 0;
const PROMPT_CACHE_TTL = 60000;

const DEFAULT_SYSTEM_PROMPT = `You are a Customer Service AI for SmartLine ride-hailing app.

<RULES>
- NEVER invent solutions or endpoints
- ONLY use predefined actions from ALLOWED_ACTIONS
- ALWAYS respond in the user's language (Arabic/English)
- If information is missing, ask with options
</RULES>

<ALLOWED_ACTIONS>
BOOKING: request_pickup_location, request_destination, show_ride_options, confirm_booking
TRACKING: show_trip_tracking, show_driver_info
TRIP: cancel_trip, confirm_cancel_trip, contact_driver
SAFETY: trigger_emergency, share_live_location
SUPPORT: connect_support, call_support
</ALLOWED_ACTIONS>

<STYLE>
- Be warm but concise (Egyptian dialect OK)
- Use emojis sparingly: 🚗 📍 ✅ ❌ 🎧
- Max 3 sentences per response
- Always end with clear next step
</STYLE>`;

async function getSystemPrompt() {
    try {
        if (cachedSystemPrompt && (Date.now() - promptCacheTime) < PROMPT_CACHE_TTL) {
            return cachedSystemPrompt;
        }
        const [rows] = await pool.execute(
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
// 🎯 INTENT DETECTION
// ============================================

const INTENTS = {
    BOOK_TRIP: { keywords: ['رحلة', 'توصيل', 'حجز', 'أحجز', 'وصلني', 'book', 'ride', 'trip'], action: 'book_trip' },
    TRIP_STATUS: { keywords: ['وين', 'أين', 'الكابتن', 'تتبع', 'status', 'where', 'driver', 'track'], action: 'trip_status' },
    CANCEL_TRIP: { keywords: ['إلغاء', 'الغاء', 'ألغي', 'cancel'], action: 'cancel_trip' },
    CONTACT_DRIVER: { keywords: ['اتصل', 'رقم', 'تواصل', 'call', 'contact', 'phone'], action: 'contact_driver' },
    PAYMENT: { keywords: ['سعر', 'دفع', 'فلوس', 'مبلغ', 'price', 'fare', 'payment'], action: 'payment' },
    HISTORY: { keywords: ['سابق', 'قديم', 'رحلاتي', 'history', 'previous'], action: 'history' },
    COMPLAINT: { keywords: ['شكوى', 'مشكلة', 'سيء', 'complaint', 'problem', 'issue'], action: 'complaint' },
    SAFETY: { keywords: ['خطر', 'تحرش', 'حادث', 'شرطة', 'طوارئ', 'danger', 'emergency', 'accident'], action: 'safety' },
    HUMAN: { keywords: ['موظف', 'بشري', 'إنسان', 'كلمني', 'agent', 'human', 'support'], action: 'human_handoff' },
    GREETING: { keywords: ['مرحبا', 'هلا', 'السلام', 'صباح', 'مساء', 'hi', 'hello', 'hey'], action: 'greeting' }
};

function detectIntent(message) {
    const lowerMessage = message.toLowerCase();
    for (const [intentName, intentData] of Object.entries(INTENTS)) {
        for (const keyword of intentData.keywords) {
            if (lowerMessage.includes(keyword.toLowerCase())) {
                return { intent: intentName, action: intentData.action };
            }
        }
    }
    return { intent: 'UNKNOWN', action: 'unknown' };
}

// ============================================
// 🔄 CONVERSATION STATE MACHINE
// ============================================

const STATES = {
    START: 'START',
    AWAITING_PICKUP: 'AWAITING_PICKUP',
    AWAITING_DESTINATION: 'AWAITING_DESTINATION',
    AWAITING_RIDE_TYPE: 'AWAITING_RIDE_TYPE',
    AWAITING_CONFIRMATION: 'AWAITING_CONFIRMATION',
    TRIP_ACTIVE: 'TRIP_ACTIVE',
    AWAITING_CANCEL_CONFIRM: 'AWAITING_CANCEL_CONFIRM',
    COMPLAINT_FLOW: 'COMPLAINT_FLOW',
    RESOLVED: 'RESOLVED'
};

async function getConversationState(userId) {
    try {
        const [rows] = await pool.execute(
            'SELECT current_state, flow_data FROM ai_conversation_state WHERE user_id = ?',
            [userId]
        );
        if (rows.length === 0) return { state: STATES.START, data: {} };
        // flow_data is JSON column - mysql2 auto-parses it
        let flowData = rows[0].flow_data || {};
        if (typeof flowData === 'string') {
            try {
                flowData = JSON.parse(flowData);
            } catch (e) {
                flowData = {};
            }
        }
        return { state: rows[0].current_state, data: flowData };
    } catch (e) {
        logger.error('Error getting conversation state', { error: e.message, userId });
        return { state: STATES.START, data: {} };
    }
}

async function setConversationState(userId, state, data = {}) {
    try {
        await pool.execute(`
            INSERT INTO ai_conversation_state (user_id, current_state, flow_data)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE current_state = ?, flow_data = ?
        `, [userId, state, JSON.stringify(data), state, JSON.stringify(data)]);
    } catch (e) {
        logger.error('Error setting state', { error: e.message });
    }
}

// ============================================
// 🗄️ DATABASE HELPERS
// ============================================

async function getActiveRide(userId) {
    try {
        const [rows] = await pool.execute(`
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
    } catch (e) { return null; }
}

async function getLastTrip(userId) {
    try {
        const [rows] = await pool.execute(`
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
        const [rows] = await pool.execute(
            'SELECT role, content FROM ai_chat_history WHERE user_id = ? ORDER BY created_at DESC LIMIT ?',
            [userId, limit]
        );
        return rows.reverse();
    } catch (e) { return []; }
}

async function saveChat(userId, role, content, actionType = null, actionData = null) {
    try {
        await pool.execute(
            'INSERT INTO ai_chat_history (user_id, role, content, action_type, action_data) VALUES (?, ?, ?, ?, ?)',
            [userId, role, content, actionType, actionData ? JSON.stringify(actionData) : null]
        );
    } catch (e) { }
}

// ============================================
// 🚗 VEHICLE CATEGORIES
// ============================================

let cachedVehicleCategories = null;
let vehicleCategoriesCacheTime = 0;

async function getVehicleCategories() {
    try {
        if (cachedVehicleCategories && (Date.now() - vehicleCategoriesCacheTime) < 300000) {
            return cachedVehicleCategories;
        }
        const [rows] = await pool.execute(`
            SELECT id, name, description, type FROM vehicle_categories
            WHERE is_active = 1 AND deleted_at IS NULL ORDER BY name ASC
        `);
        if (rows.length > 0) {
            cachedVehicleCategories = rows;
            vehicleCategoriesCacheTime = Date.now();
            return rows;
        }
        return [{ id: '1', name: 'توفير' }, { id: '2', name: 'سمارت برو' }, { id: '3', name: 'في اي بي' }];
    } catch (e) {
        return [{ id: '1', name: 'توفير' }, { id: '2', name: 'سمارت برو' }, { id: '3', name: 'في اي بي' }];
    }
}

function formatVehicleCategoriesMessage(categories, lang) {
    let msg = lang === 'ar' ? '✅ تم تحديد الوجهة.\nاختر نوع الرحلة:\n' : '✅ Destination set.\nChoose ride type:\n';
    categories.forEach((cat, i) => { msg += `${i + 1}. ${cat.name}\n`; });
    return msg.trim();
}

// ============================================
// 🎫 TRIP CREATION SYSTEM
// ============================================

const { v4: uuidv4 } = require('uuid');

/**
 * Generate UUID for trip
 */
function generateUUID() {
    return uuidv4();
}

/**
 * Find zone based on pickup coordinates
 */
async function findZoneByCoordinates(lat, lng) {
    try {
        // Get zones with their coordinates
        const [zones] = await pool.execute(`
            SELECT id, name, coordinates FROM zones
            WHERE is_active = 1 AND deleted_at IS NULL
        `);

        if (zones.length === 0) {
            // Return first zone as default
            const [defaultZone] = await pool.execute(`SELECT id FROM zones LIMIT 1`);
            return defaultZone[0]?.id || null;
        }

        // For now, return the first active zone
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
        const [result] = await pool.execute(`
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
        const [fares] = await pool.execute(`
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
        return 25.00; // Default fare
    }
}

/**
 * Create a new trip in the database
 */
async function createTrip(tripData) {
    const connection = await pool.getConnection();

    try {
        await connection.beginTransaction();

        const tripId = generateUUID();
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

        // Default coordinates if not parsed
        if (!pickupLat || !pickupLng) {
            pickupLat = 30.0444; // Cairo default
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

        // Get pickup and destination addresses
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
            tripId,
            refId,
            tripData.customer_id,
            tripData.ride_type,
            zoneId,
            estimatedFare,
            estimatedFare,
            5.0, // Default 5km
            'cash', // Default payment method
            'ride_request',
            'pending',
            now,
            now
        ]);

        // 2. Insert into trip_status (id is auto_increment)
        await connection.execute(`
            INSERT INTO trip_status (
                trip_request_id, customer_id, pending, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?)
        `, [
            tripId,
            tripData.customer_id,
            now,
            now,
            now
        ]);

        // 3. Insert into trip_request_coordinates (id is auto_increment)
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
            `POINT(${pickupLng} ${pickupLat})`,
            `POINT(${destLng} ${destLat})`,
            `POINT(${pickupLng} ${pickupLat})`,
            `POINT(${pickupLng} ${pickupLat})`,
            pickupAddress,
            destAddress,
            now,
            now
        ]);

        // 4. Insert into trip_request_fees (id is auto_increment)
        await connection.execute(`
            INSERT INTO trip_request_fees (
                trip_request_id, created_at, updated_at
            ) VALUES (?, ?, ?)
        `, [
            tripId,
            now,
            now
        ]);

        // 5. Insert into trip_request_times (id is auto_increment)
        await connection.execute(`
            INSERT INTO trip_request_times (
                trip_request_id, estimated_time, created_at, updated_at
            ) VALUES (?, ?, ?, ?)
        `, [
            tripId,
            15, // Default 15 minutes
            now,
            now
        ]);

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
        return {
            success: false,
            error: error.message
        };
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
        await pool.execute(`
            UPDATE trip_requests
            SET current_status = 'cancelled', updated_at = ?
            WHERE id = ?
        `, [now, tripId]);

        await pool.execute(`
            UPDATE trip_request_fees
            SET cancelled_by = 'customer', updated_at = ?
            WHERE trip_request_id = ?
        `, [now, tripId]);

        return { success: true };
    } catch (e) {
        logger.error('Failed to cancel trip', { error: e.message });
        return { success: false, error: e.message };
    }
}

// ============================================
// 🤖 GROQ LLM API
// ============================================

async function callLLM(messages) {
    const apiKey = process.env.GROQ_API_KEY;
    if (!apiKey) throw new Error("GROQ_API_KEY not set");

    const response = await fetch('https://api.groq.com/openai/v1/chat/completions', {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${apiKey}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({ model: 'llama-3.3-70b-versatile', messages, temperature: 0.4, max_tokens: 300 })
    });

    if (!response.ok) throw new Error(`Groq API error: ${await response.text()}`);
    const data = await response.json();
    return data.choices[0].message.content;
}

// ============================================
// 🎬 MAIN CONVERSATION PROCESSOR
// ============================================

async function processConversation(userId, message, lang, userType) {
    const convState = await getConversationState(userId);
    const activeRide = await getActiveRide(userId);
    const detectedIntent = detectIntent(message);

    let response = {
        message: '', action: ACTION_TYPES.NONE, data: {}, quick_replies: [],
        ui_hint: null, confidence: 0.85, handoff: false, language: lang, userType
    };

    // SAFETY CHECK (HIGHEST PRIORITY)
    if (detectedIntent.intent === 'SAFETY') {
        response.message = lang === 'ar'
            ? '🚨 سلامتك أهم شي! هل أنت بأمان؟ إذا لا، اتصل بالطوارئ (999) فوراً.'
            : '🚨 Your safety is our priority! Are you safe? Call emergency services if needed.';
        const emergencyAction = ActionBuilders.triggerEmergency(activeRide?.id);
        response.action = emergencyAction.action;
        response.data = emergencyAction.data;
        response.handoff = true;
        response.quick_replies = ['نعم، أنا بأمان', 'لا، أحتاج مساعدة'];
        await setConversationState(userId, STATES.RESOLVED, {});
        return response;
    }

    // HUMAN HANDOFF
    if (detectedIntent.intent === 'HUMAN') {
        response.message = lang === 'ar' ? '🎧 جاري تحويلك للموظف المختص...' : '🎧 Connecting you to support...';
        const supportAction = ActionBuilders.connectSupport('user_request', activeRide?.id);
        response.action = supportAction.action;
        response.data = supportAction.data;
        response.handoff = true;
        await setConversationState(userId, STATES.RESOLVED, {});
        return response;
    }

    // STATE-BASED PROCESSING
    switch (convState.state) {
        case STATES.START:
            if (activeRide) {
                response.message = lang === 'ar'
                    ? `رحلتك الحالية: ${activeRide.driver_name}\n${activeRide.pickup} ← ${activeRide.destination}`
                    : `Your trip: ${activeRide.driver_name}\n${activeRide.pickup} → ${activeRide.destination}`;
                const trackingAction = ActionBuilders.showTripTracking(activeRide.id);
                response.action = trackingAction.action;
                response.data = { ...trackingAction.data, ride: activeRide };
                response.quick_replies = ['أين الكابتن؟', 'إلغاء الرحلة', 'اتصل بالكابتن'];
                await setConversationState(userId, STATES.TRIP_ACTIVE, { trip_id: activeRide.id });
            } else if (detectedIntent.intent === 'BOOK_TRIP' || message.includes('1')) {
                response.message = lang === 'ar' ? '🚗 من أي مكان تريد أن تبدأ الرحلة؟' : '🚗 Where would you like to be picked up?';
                const pickupAction = ActionBuilders.requestPickup();
                response.action = pickupAction.action;
                response.data = pickupAction.data;
                await setConversationState(userId, STATES.AWAITING_PICKUP, {});
            } else if (detectedIntent.intent === 'GREETING' || detectedIntent.intent === 'UNKNOWN') {
                // Use LLM for general queries
                try {
                    const systemPrompt = await getSystemPrompt();
                    const history = await getChatHistory(userId, 4);
                    const messages = [
                        { role: 'system', content: systemPrompt },
                        ...history.map(h => ({ role: h.role, content: h.content })),
                        { role: 'user', content: message }
                    ];
                    response.message = await callLLM(messages);
                    response.quick_replies = lang === 'ar' ? ['حجز رحلة', 'رحلاتي', 'مساعدة'] : ['Book ride', 'My trips', 'Help'];
                } catch (e) {
                    response.message = lang === 'ar'
                        ? 'مرحباً! كيف أساعدك؟\n1. حجز رحلة\n2. استعلام عن رحلة\n3. مساعدة'
                        : 'Hello! How can I help?\n1. Book a ride\n2. Trip inquiry\n3. Help';
                    response.quick_replies = ['1', '2', '3'];
                }
            } else {
                response.message = lang === 'ar' ? 'كيف أقدر أساعدك؟' : 'How can I help you?';
                response.quick_replies = ['حجز رحلة', 'رحلاتي', 'مساعدة'];
            }
            break;

        case STATES.AWAITING_PICKUP:
            if (message.includes('lat:') || message.includes('location:') || convState.data.pickup_received) {
                response.message = lang === 'ar' ? '✅ تم. إلى أين تريد الذهاب؟' : '✅ Got it. Where to?';
                const destAction = ActionBuilders.requestDestination(convState.data.pickup || message);
                response.action = destAction.action;
                response.data = destAction.data;
                await setConversationState(userId, STATES.AWAITING_DESTINATION, { pickup: message });
            } else {
                response.message = lang === 'ar' ? '📍 حدد موقعك على الخريطة' : '📍 Select your location on the map';
                const pickupAction = ActionBuilders.requestPickup();
                response.action = pickupAction.action;
                response.data = pickupAction.data;
            }
            break;

        case STATES.AWAITING_DESTINATION:
            if (message.includes('lat:') || message.includes('location:') || message.length > 3) {
                const categories = await getVehicleCategories();
                response.message = formatVehicleCategoriesMessage(categories, lang);
                const rideOptions = ActionBuilders.showRideOptions(convState.data.pickup, message, categories);
                response.action = rideOptions.action;
                response.data = rideOptions.data;
                response.quick_replies = categories.map(c => c.name);
                await setConversationState(userId, STATES.AWAITING_RIDE_TYPE, { ...convState.data, destination: message, vehicle_categories: categories });
            } else {
                response.message = lang === 'ar' ? '📍 إلى أين تريد الذهاب؟' : '📍 Where would you like to go?';
                const destAction = ActionBuilders.requestDestination(convState.data.pickup);
                response.action = destAction.action;
                response.data = destAction.data;
            }
            break;

        case STATES.AWAITING_RIDE_TYPE:
            const categories = convState.data.vehicle_categories || await getVehicleCategories();
            let selectedCat = categories[0];
            for (let i = 0; i < categories.length; i++) {
                if (message.includes(String(i + 1)) || message.includes(categories[i].name.toLowerCase())) {
                    selectedCat = categories[i]; break;
                }
            }
            response.message = lang === 'ar'
                ? `تأكيد:\n📍 من: ${convState.data.pickup}\n📍 إلى: ${convState.data.destination}\n🚗 ${selectedCat.name}\n\nتأكيد الحجز؟`
                : `Confirm:\n📍 From: ${convState.data.pickup}\n📍 To: ${convState.data.destination}\n🚗 ${selectedCat.name}\n\nConfirm?`;
            response.quick_replies = ['تأكيد الحجز', 'إلغاء'];
            await setConversationState(userId, STATES.AWAITING_CONFIRMATION, { ...convState.data, ride_type: selectedCat.id, ride_type_name: selectedCat.name });
            break;

        case STATES.AWAITING_CONFIRMATION:
            if (message.includes('تأكيد') || message.includes('نعم') || message.includes('confirm') || message.includes('yes')) {
                // Actually create the trip in the database
                const tripResult = await createTrip({
                    customer_id: userId,
                    pickup: convState.data.pickup,
                    destination: convState.data.destination,
                    ride_type: convState.data.ride_type,
                    ride_type_name: convState.data.ride_type_name,
                    pickup_address: convState.data.pickup_address,
                    destination_address: convState.data.destination_address
                });

                if (tripResult.success) {
                    response.message = lang === 'ar'
                        ? `🎉 تم تأكيد الحجز!\n📋 رقم الرحلة: ${tripResult.ref_id}\n💰 السعر المتوقع: ${tripResult.estimated_fare} ج.م\n\nجاري البحث عن كابتن...`
                        : `🎉 Booking confirmed!\n📋 Trip #${tripResult.ref_id}\n💰 Estimated fare: ${tripResult.estimated_fare} EGP\n\nSearching for driver...`;

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
                        estimated_fare: tripResult.estimated_fare,
                        pickup_address: tripResult.pickup_address,
                        destination_address: tripResult.destination_address
                    };
                    response.ui_hint = confirmAction.ui_hint;
                    response.quick_replies = ['أين الكابتن؟', 'إلغاء الرحلة'];

                    await setConversationState(userId, STATES.TRIP_ACTIVE, {
                        ...convState.data,
                        trip_id: tripResult.trip_id,
                        ref_id: tripResult.ref_id
                    });
                } else {
                    response.message = lang === 'ar'
                        ? '❌ عذراً، حدث خطأ أثناء إنشاء الرحلة. حاول مرة أخرى.'
                        : '❌ Sorry, an error occurred while creating the trip. Please try again.';
                    response.quick_replies = ['حاول مرة أخرى', 'مساعدة'];
                    logger.error('Trip creation failed', { error: tripResult.error, userId });
                }
            } else {
                response.message = lang === 'ar' ? 'تم إلغاء الحجز. كيف أساعدك؟' : 'Booking cancelled. How can I help?';
                response.quick_replies = ['حجز رحلة جديدة'];
                await setConversationState(userId, STATES.START, {});
            }
            break;

        case STATES.TRIP_ACTIVE:
            const currentRide = activeRide || await getLastTrip(userId);
            if (detectedIntent.intent === 'CANCEL_TRIP') {
                response.message = lang === 'ar' ? '⚠️ هل أنت متأكد من الإلغاء؟' : '⚠️ Are you sure you want to cancel?';
                const cancelAction = ActionBuilders.confirmCancelTrip(currentRide?.id, 5);
                response.action = cancelAction.action;
                response.data = cancelAction.data;
                response.quick_replies = cancelAction.quick_replies;
                await setConversationState(userId, STATES.AWAITING_CANCEL_CONFIRM, { trip_id: currentRide?.id });
            } else if (detectedIntent.intent === 'CONTACT_DRIVER') {
                response.message = lang === 'ar' ? '📞 جاري الاتصال بالكابتن...' : '📞 Connecting to driver...';
                const contactAction = ActionBuilders.contactDriver(currentRide?.id, currentRide?.driver_phone);
                response.action = contactAction.action;
                response.data = contactAction.data;
            } else {
                response.message = lang === 'ar'
                    ? `الكابتن ${currentRide?.driver_name || ''} في الطريق.`
                    : `Driver ${currentRide?.driver_name || ''} is on the way.`;
                const trackingAction = ActionBuilders.showTripTracking(currentRide?.id);
                response.action = trackingAction.action;
                response.data = { ...trackingAction.data, ride: currentRide };
                response.quick_replies = ['إلغاء الرحلة', 'اتصل بالكابتن'];
            }
            break;

        case STATES.AWAITING_CANCEL_CONFIRM:
            if (message.includes('نعم') || message.includes('yes') || message.includes('إلغاء')) {
                // Actually cancel the trip in the database
                const cancelResult = await cancelTrip(convState.data.trip_id);

                if (cancelResult.success) {
                    response.message = lang === 'ar' ? '❌ تم إلغاء الرحلة بنجاح.' : '❌ Trip cancelled successfully.';
                } else {
                    response.message = lang === 'ar' ? '❌ تم إلغاء الرحلة.' : '❌ Trip cancelled.';
                }
                response.action = ACTION_TYPES.CANCEL_TRIP;
                response.data = { trip_id: convState.data.trip_id };
                response.quick_replies = ['حجز رحلة جديدة'];
                await setConversationState(userId, STATES.START, {});
            } else {
                response.message = lang === 'ar' ? '✅ رحلتك مستمرة.' : '✅ Your trip continues.';
                await setConversationState(userId, STATES.TRIP_ACTIVE, convState.data);
            }
            break;

        default:
            await setConversationState(userId, STATES.START, {});
            response.message = lang === 'ar' ? 'كيف أقدر أساعدك؟' : 'How can I help you?';
            response.quick_replies = ['حجز رحلة', 'رحلاتي', 'مساعدة'];
    }

    return response;
}

// ============================================
// 🚀 MAIN CHAT ENDPOINT
// ============================================

app.post('/chat', chatRateLimiter, [
    body('user_id').trim().notEmpty().escape(),
    body('message').trim().notEmpty().isLength({ max: 500 })
], async (req, res) => {
    try {
        const errors = validationResult(req);
        if (!errors.isEmpty()) {
            return res.status(400).json({
                message: 'Invalid request. Please provide user_id and message.',
                action: ACTION_TYPES.NONE,
                errors: errors.array()
            });
        }

        const { user_id, message, location_data } = req.body;

        // Language detection
        const langResult = detectUserLanguage(message);
        const lang = langResult.primary === 'ar' || langResult.primary === 'arabizi' ? 'ar' : 'en';

        // Block unsupported languages
        if (langResult.primary === 'unknown' && langResult.confidence > 0.5) {
            const guardReply = languageGuardReply({ bilingual: true });
            return res.json({
                message: guardReply.message,
                action: ACTION_TYPES.NONE,
                blocked: true,
                language: langResult
            });
        }

        // Check for repeated message
        if (isRepeatedMessage(user_id, message)) {
            return res.json({
                message: lang === 'ar' ? 'لقد أرسلت هذه الرسالة مؤخراً.' : 'You recently sent this message.',
                action: ACTION_TYPES.NONE,
                repeated: true
            });
        }

        // Profanity check
        const profanityResult = checkProfanity(message);
        if (profanityResult.flagged && profanityResult.severity !== 'none') {
            const escReply = escalationReply(lang, profanityResult.severity);
            return res.json({
                message: escReply.message,
                action: escReply.action === 'escalate' ? ACTION_TYPES.CONNECT_SUPPORT : ACTION_TYPES.NONE,
                handoff: escReply.requiresHumanReview,
                moderation: { flagged: true, severity: profanityResult.severity }
            });
        }

        // User type detection
        let userType = getUserType(user_id);
        const detectedType = detectUserType(message, userType);
        if (detectedType && !userType) {
            setUserType(user_id, detectedType);
            userType = detectedType;
        }

        // Handle location data
        let processedMessage = message;
        if (location_data?.lat && location_data?.lng) {
            processedMessage = `location:${location_data.lat},${location_data.lng} ${message}`;
            const convState = await getConversationState(user_id);
            if (convState.state === STATES.AWAITING_PICKUP) {
                await setConversationState(user_id, convState.state, { ...convState.data, pickup: location_data, pickup_received: true });
            } else if (convState.state === STATES.AWAITING_DESTINATION) {
                await setConversationState(user_id, convState.state, { ...convState.data, destination: location_data });
            }
        }

        // Process conversation
        const response = await processConversation(user_id, processedMessage, lang, userType);

        // Save to history
        await saveChat(user_id, 'user', message);
        await saveChat(user_id, 'assistant', response.message, response.action, response.data);

        res.json({
            message: response.message,
            action: response.action,
            data: response.data,
            quick_replies: response.quick_replies,
            ui_hint: response.ui_hint,
            confidence: response.confidence,
            handoff: response.handoff,
            language: { primary: lang, ...langResult },
            userType: response.userType,
            model: 'Llama 3.3 70B'
        });

    } catch (error) {
        logError(error, { endpoint: '/chat', user_id: req.body?.user_id });
        const lang = detectUserLanguage(req.body?.message || '').primary === 'ar' ? 'ar' : 'en';
        res.status(500).json({
            message: lang === 'ar' ? 'عذراً، حصل خطأ. حاول تاني.' : 'Sorry, an error occurred.',
            action: ACTION_TYPES.NONE,
            handoff: true
        });
    }
});

// ============================================
// 📍 LOCATION SUBMISSION ENDPOINT
// ============================================

app.post('/submit-location', async (req, res) => {
    try {
        const { user_id, lat, lng, address, type } = req.body;
        if (!user_id || !lat || !lng) {
            return res.status(400).json({ success: false, error: 'Missing required fields' });
        }

        const location_data = { lat, lng, address: address || '' };
        const convState = await getConversationState(user_id);
        const lang = 'ar';

        let response = { success: true, message: '', action: ACTION_TYPES.NONE, data: {}, quick_replies: [] };

        if (type === 'pickup' || convState.state === STATES.AWAITING_PICKUP) {
            await setConversationState(user_id, STATES.AWAITING_DESTINATION, { ...convState.data, pickup: location_data });
            response.message = 'إلى أين تريد الذهاب؟ 📍';
            const destAction = ActionBuilders.requestDestination(location_data);
            response.action = destAction.action;
            response.data = destAction.data;
        } else if (type === 'destination' || convState.state === STATES.AWAITING_DESTINATION) {
            const categories = await getVehicleCategories();
            await setConversationState(user_id, STATES.AWAITING_RIDE_TYPE, { ...convState.data, destination: location_data, vehicle_categories: categories });
            response.message = formatVehicleCategoriesMessage(categories, lang);
            const rideOptions = ActionBuilders.showRideOptions(convState.data.pickup, location_data, categories);
            response.action = rideOptions.action;
            response.data = rideOptions.data;
            response.quick_replies = categories.map(c => c.name);
        } else {
            response.message = 'تم استلام الموقع.';
            response.quick_replies = ['حجز رحلة', 'مساعدة'];
        }

        await saveChat(user_id, 'user', `📍 ${address || `${lat},${lng}`}`);
        await saveChat(user_id, 'assistant', response.message, response.action, response.data);
        res.json(response);
    } catch (error) {
        logError(error, { endpoint: '/submit-location' });
        res.status(500).json({ success: false, error: error.message });
    }
});

// ============================================
// 🔧 ADMIN ENDPOINTS
// ============================================

app.use('/admin', adminAuth);

app.post('/admin/clear-memory', async (req, res) => {
    try {
        const { user_id } = req.body;
        await pool.execute('DELETE FROM ai_chat_history WHERE user_id = ?', [user_id]);
        await pool.execute('DELETE FROM ai_conversation_state WHERE user_id = ?', [user_id]);
        userTypes.delete(user_id);
        lastMessages.delete(user_id);
        res.json({ success: true });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

app.post('/admin/reset-state', async (req, res) => {
    try {
        const { user_id } = req.body;
        await setConversationState(user_id, STATES.START, {});
        res.json({ success: true });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

app.get('/admin/user-state/:user_id', async (req, res) => {
    try {
        const state = await getConversationState(req.params.user_id);
        const uType = getUserType(req.params.user_id);
        res.json({ ...state, userType: uType });
    } catch (error) { res.status(500).json({ error: error.message }); }
});

app.get('/admin/stats', async (req, res) => {
    try {
        const [users] = await pool.execute('SELECT COUNT(*) as count FROM users');
        const [chats] = await pool.execute('SELECT COUNT(*) as count FROM ai_chat_history');
        const mem = process.memoryUsage();
        res.json({
            success: true,
            stats: {
                usersInDatabase: users[0].count,
                chatHistoryEntries: chats[0].count,
                userTypesInMemory: userTypes.size,
                lastMessagesInMemory: lastMessages.size,
                cacheStats: responseCache.getStats(),
                memory: { heapUsed: `${Math.round(mem.heapUsed / 1024 / 1024)}MB`, rss: `${Math.round(mem.rss / 1024 / 1024)}MB` },
                uptime: `${Math.round(process.uptime())}s`
            }
        });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

app.post('/admin/set-user-type', (req, res) => {
    const { user_id, type } = req.body;
    if (!user_id || !['captain', 'customer'].includes(type)) {
        return res.status(400).json({ success: false, error: 'Invalid user_id or type' });
    }
    setUserType(user_id, type);
    res.json({ success: true, user_id, type });
});

// ============================================
// 📊 PUBLIC ENDPOINTS
// ============================================

app.get('/health', async (req, res) => {
    try {
        await pool.execute('SELECT 1');
        const mem = process.memoryUsage();
        res.json({
            status: 'ok', database: 'connected', version: 'v3',
            memory: { heapUsed: `${Math.round(mem.heapUsed / 1024 / 1024)}MB` },
            uptime: `${Math.round(process.uptime())}s`
        });
    } catch (error) {
        res.status(500).json({ status: 'error', database: 'disconnected', details: error.message });
    }
});

app.get('/action-types', (req, res) => {
    res.json({ action_types: ACTION_TYPES, ui_hints: UI_HINTS, description: 'Flutter action types' });
});

app.get('/chat', (req, res) => res.send('SmartLine AI Chatbot v3 is Running!'));

// ============================================
// 🛑 GRACEFUL SHUTDOWN
// ============================================

async function gracefulShutdown(signal) {
    logger.info(`Received ${signal}. Shutting down gracefully...`);
    if (pool) try { await pool.end(); } catch (e) { }
    process.exit(0);
}

process.on('SIGTERM', () => gracefulShutdown('SIGTERM'));
process.on('SIGINT', () => gracefulShutdown('SIGINT'));
process.on('unhandledRejection', (reason) => logger.error('Unhandled Rejection', { reason }));
process.on('uncaughtException', (error) => { logError(error, { type: 'uncaughtException' }); process.exit(1); });

// ============================================
// 🚀 START SERVER
// ============================================

const PORT = process.env.PORT || 3000;

async function start() {
    try {
        await initDatabase();
        app.listen(PORT, () => {
            console.log(`
╔═══════════════════════════════════════════════════╗
║  🚗 SMARTLINE AI CHATBOT V3                       ║
║  ────────────────────────────────────────────────║
║  Server: http://localhost:${PORT}                     ║
║  API: /chat, /submit-location                    ║
║  Actions: /action-types                          ║
║  Health: /health                                 ║
║  DB: ${DB_CONFIG.database.padEnd(20)}             ║
║  Features:                                       ║
║    ✅ Rate Limiting    ✅ Moderation             ║
║    ✅ User Types       ✅ Flutter Actions        ║
║    ✅ Caching          ✅ Graceful Shutdown      ║
╚═══════════════════════════════════════════════════╝
            `);
        });
    } catch (error) {
        logger.error('Failed to start', { error: error.message });
        process.exit(1);
    }
}

start();
