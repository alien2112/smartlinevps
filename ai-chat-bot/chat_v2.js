// ============================================
// 🚗 RIDE SUPPORT - CUSTOMER SERVICE CHATBOT V2
// With Action-Based Responses for Flutter
// ============================================

const express = require('express');
const cors = require('cors');
const mysql = require('mysql2/promise');
const path = require('path');
require('dotenv').config();

const { ACTION_TYPES, UI_HINTS, ActionBuilders } = require('./actions');

const app = express();
app.use(express.json());
app.use(cors());
app.use(express.static(path.join(__dirname, 'public')));

// ============================================
// 🗄️ DATABASE
// ============================================

const DB_CONFIG = {
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'merged2',
    waitForConnections: true,
    connectionLimit: 10
};

let pool;

async function initDatabase() {
    try {
        pool = mysql.createPool(DB_CONFIG);

        // AI chat history table
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

        // Conversation state table (for multi-turn flows)
        await pool.execute(`
            CREATE TABLE IF NOT EXISTS ai_conversation_state (
                user_id VARCHAR(36) PRIMARY KEY,
                current_state VARCHAR(50) NOT NULL DEFAULT 'START',
                flow_data JSON NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        `);

        console.log('✅ Database connected & initialized');
    } catch (err) {
        console.error('❌ Database initialization failed:', err);
    }
}

// ============================================
// 🎯 SYSTEM PROMPT FROM DATABASE
// ============================================

let cachedSystemPrompt = null;
let promptCacheTime = 0;
const PROMPT_CACHE_TTL = 60000; // Cache for 1 minute

async function getSystemPrompt() {
    try {
        // Return cached prompt if still valid
        if (cachedSystemPrompt && (Date.now() - promptCacheTime) < PROMPT_CACHE_TTL) {
            return cachedSystemPrompt;
        }

        const [rows] = await pool.execute(
            "SELECT value FROM business_settings WHERE key_name = 'ai_chatbot_prompt' AND settings_type = 'ai_config' LIMIT 1"
        );

        if (rows.length > 0) {
            // Remove quotes if stored as JSON string
            let prompt = rows[0].value;
            if (prompt.startsWith('"') && prompt.endsWith('"')) {
                prompt = JSON.parse(prompt);
            }
            cachedSystemPrompt = prompt;
            promptCacheTime = Date.now();
            console.log('📝 System prompt loaded from database');
            return prompt;
        }

        // Default fallback prompt
        return 'You are a helpful customer service assistant for SmartLine ride-hailing app. Help users with booking rides, tracking trips, and resolving issues. Be friendly and concise. Respond in the same language as the user (Arabic or English).';
    } catch (e) {
        console.error('Error fetching system prompt:', e);
        return 'You are a helpful customer service assistant for SmartLine ride-hailing app.';
    }
}

// ============================================
// 🎯 INTENT DETECTION
// ============================================

const INTENTS = {
    // Trip Booking
    BOOK_TRIP: {
        keywords: ['رحلة', 'توصيل', 'حجز', 'أحجز', 'أبي رحلة', 'وصلني', 'book', 'ride', 'trip'],
        action: 'book_trip'
    },

    // Trip Status
    TRIP_STATUS: {
        keywords: ['وين', 'أين', 'الكابتن', 'تتبع', 'status', 'where', 'driver', 'track'],
        action: 'trip_status'
    },

    // Cancel Trip
    CANCEL_TRIP: {
        keywords: ['إلغاء', 'الغاء', 'ألغي', 'cancel'],
        action: 'cancel_trip'
    },

    // Contact Driver
    CONTACT_DRIVER: {
        keywords: ['اتصل', 'رقم', 'تواصل', 'call', 'contact', 'phone'],
        action: 'contact_driver'
    },

    // Payment Issues
    PAYMENT: {
        keywords: ['سعر', 'دفع', 'فلوس', 'مبلغ', 'price', 'fare', 'payment', 'money'],
        action: 'payment'
    },

    // Trip History
    HISTORY: {
        keywords: ['سابق', 'قديم', 'رحلاتي', 'history', 'previous', 'past'],
        action: 'history'
    },

    // Complaint
    COMPLAINT: {
        keywords: ['شكوى', 'مشكلة', 'سيء', 'complaint', 'problem', 'issue', 'bad'],
        action: 'complaint'
    },

    // Safety
    SAFETY: {
        keywords: ['خطر', 'تحرش', 'حادث', 'شرطة', 'طوارئ', 'إسعاف', 'danger', 'emergency', 'accident', 'harassment'],
        action: 'safety'
    },

    // Human Handoff
    HUMAN: {
        keywords: ['موظف', 'بشري', 'إنسان', 'كلمني', 'agent', 'human', 'support'],
        action: 'human_handoff'
    },

    // Greeting
    GREETING: {
        keywords: ['مرحبا', 'هلا', 'السلام', 'صباح', 'مساء', 'hi', 'hello', 'hey'],
        action: 'greeting'
    }
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

        if (rows.length === 0) {
            return { state: STATES.START, data: {} };
        }

        return {
            state: rows[0].current_state,
            data: rows[0].flow_data ? JSON.parse(rows[0].flow_data) : {}
        };
    } catch (e) {
        console.error('Error getting state:', e);
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
        console.error('Error setting state:', e);
    }
}

// ============================================
// 🗄️ DATABASE HELPERS
// ============================================

async function getActiveRide(userId) {
    try {
        const activeStatuses = ['pending', 'accepted', 'ongoing', 'arrived'];

        const [rows] = await pool.execute(`
            SELECT 
                tr.id,
                tr.ref_id,
                tr.current_status as status,
                tr.driver_id,
                tr.estimated_fare,
                tr.actual_fare,
                COALESCE(trc.pickup_address, 'نقطة الانطلاق') as pickup,
                COALESCE(trc.destination_address, 'الوجهة') as destination,
                COALESCE(trc.pickup_coordinates, '') as pickup_coords,
                COALESCE(trc.destination_coordinates, '') as destination_coords,
                COALESCE(CONCAT(d.first_name, ' ', d.last_name), 'جاري البحث...') as driver_name,
                d.phone as driver_phone
            FROM trip_requests tr
            LEFT JOIN trip_request_coordinates trc ON tr.id = trc.trip_request_id
            LEFT JOIN users d ON tr.driver_id = d.id
            WHERE tr.customer_id = ?
              AND tr.current_status IN (?, ?, ?, ?)
            ORDER BY tr.created_at DESC
            LIMIT 1
        `, [userId, ...activeStatuses]);

        return rows[0] || null;
    } catch (error) {
        console.error('Database Error (getActiveRide):', error);
        return null;
    }
}

async function getLastTrip(userId) {
    try {
        const [rows] = await pool.execute(`
            SELECT 
                tr.id,
                tr.ref_id,
                tr.current_status as status,
                tr.estimated_fare,
                tr.actual_fare,
                tr.created_at,
                COALESCE(trc.pickup_address, 'نقطة الانطلاق') as pickup,
                COALESCE(trc.destination_address, 'الوجهة') as destination,
                COALESCE(CONCAT(d.first_name, ' ', d.last_name), 'غير معروف') as driver_name
            FROM trip_requests tr
            LEFT JOIN trip_request_coordinates trc ON tr.id = trc.trip_request_id
            LEFT JOIN users d ON tr.driver_id = d.id
            WHERE tr.customer_id = ?
            ORDER BY tr.created_at DESC
            LIMIT 1
        `, [userId]);

        return rows[0] || null;
    } catch (error) {
        console.error('Database Error (getLastTrip):', error);
        return null;
    }
}

async function getChatHistory(userId, limit = 6) {
    try {
        const [rows] = await pool.execute(
            'SELECT role, content, action_type, action_data FROM ai_chat_history WHERE user_id = ? ORDER BY created_at DESC LIMIT ?',
            [userId, limit]
        );
        return rows.reverse();
    } catch (e) {
        console.error('Error fetching history:', e);
        return [];
    }
}

async function saveChat(userId, role, content, actionType = null, actionData = null) {
    try {
        await pool.execute(
            'INSERT INTO ai_chat_history (user_id, role, content, action_type, action_data) VALUES (?, ?, ?, ?, ?)',
            [userId, role, content, actionType, actionData ? JSON.stringify(actionData) : null]
        );
    } catch (e) {
        console.error('Error saving chat:', e);
    }
}

// ============================================
// 🔍 LANGUAGE DETECTION
// ============================================

function isArabic(text) {
    return /[\u0600-\u06FF]/.test(text);
}

// ============================================
// 🤖 GROQ API - LLAMA 3.3 70B
// ============================================

async function callLLM(messages) {
    const apiKey = process.env.GROQ_API_KEY;
    if (!apiKey) throw new Error("GROQ_API_KEY not set");

    const response = await fetch('https://api.groq.com/openai/v1/chat/completions', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${apiKey}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            model: 'llama-3.3-70b-versatile',
            messages: messages,
            temperature: 0.4,
            max_tokens: 300
        })
    });

    if (!response.ok) {
        const err = await response.text();
        throw new Error(`Groq API error: ${err}`);
    }

    const data = await response.json();
    return data.choices[0].message.content;
}

// ============================================
// 🎬 FLOW PROCESSORS
// ============================================

/**
 * Process the conversation and return response with action
 */
async function processConversation(userId, message, lang) {
    const convState = await getConversationState(userId);
    const activeRide = await getActiveRide(userId);
    const detectedIntent = detectIntent(message);

    let response = {
        message: '',
        action: ACTION_TYPES.NONE,
        data: {},
        quick_replies: [],
        ui_hint: null,
        confidence: 0.85,
        handoff: false,
        language: lang
    };

    // ===== SAFETY CHECK (HIGHEST PRIORITY) =====
    if (detectedIntent.intent === 'SAFETY') {
        response.message = lang === 'ar'
            ? '🚨 سلامتك أهم شي! هل أنت بأمان الآن؟ إذا لا، اتصل بالطوارئ (999) فوراً.\nتم رفع بلاغ لفريق السلامة.'
            : '🚨 Your safety is our priority! Are you safe now? If not, call emergency services immediately.\nA safety report has been filed.';

        const emergencyAction = ActionBuilders.triggerEmergency(activeRide?.id);
        response.action = emergencyAction.action;
        response.data = emergencyAction.data;
        response.ui_hint = emergencyAction.ui_hint;
        response.quick_replies = ['نعم، أنا بأمان', 'لا، أحتاج مساعدة'];
        response.handoff = true;

        await setConversationState(userId, STATES.RESOLVED, {});
        return response;
    }

    // ===== HUMAN HANDOFF REQUEST =====
    if (detectedIntent.intent === 'HUMAN') {
        response.message = lang === 'ar'
            ? '🎧 أبشر، بحولك للموظف المختص حالاً. دقائق وبيكون معك...'
            : '🎧 Connecting you to a support agent. Please wait...';

        const supportAction = ActionBuilders.connectSupport('user_request', activeRide?.id);
        response.action = supportAction.action;
        response.data = supportAction.data;
        response.handoff = true;

        await setConversationState(userId, STATES.RESOLVED, {});
        return response;
    }

    // ===== STATE-BASED PROCESSING =====
    switch (convState.state) {

        // ----- START STATE -----
        case STATES.START:
            // Check for active ride first
            if (activeRide) {
                response.message = lang === 'ar'
                    ? `رحلتك الحالية: ${activeRide.driver_name}\n${activeRide.pickup} ← ${activeRide.destination}\n\nكيف أقدر أساعدك؟`
                    : `Your current trip: ${activeRide.driver_name}\n${activeRide.pickup} → ${activeRide.destination}\n\nHow can I help you?`;

                const trackingAction = ActionBuilders.showTripTracking(activeRide.id);
                response.action = trackingAction.action;
                response.data = { ...trackingAction.data, ride: activeRide };
                response.quick_replies = ['أين الكابتن؟', 'إلغاء الرحلة', 'اتصل بالكابتن', 'مشكلة أخرى'];

                await setConversationState(userId, STATES.TRIP_ACTIVE, { trip_id: activeRide.id });
            }
            // Handle different intents
            else if (detectedIntent.intent === 'BOOK_TRIP' || message.includes('1')) {
                response.message = lang === 'ar'
                    ? '🚗 سوف أحجز رحلة لك.\nمن أي مكان تريد أن تبدأ الرحلة؟'
                    : '🚗 Let me book a trip for you.\nWhere would you like to be picked up?';

                const pickupAction = ActionBuilders.requestPickup();
                response.action = pickupAction.action;
                response.data = pickupAction.data;
                response.ui_hint = pickupAction.ui_hint;

                await setConversationState(userId, STATES.AWAITING_PICKUP, {});
            }
            else if (detectedIntent.intent === 'TRIP_STATUS' || message.includes('2')) {
                const lastTrip = await getLastTrip(userId);
                if (lastTrip) {
                    response.message = lang === 'ar'
                        ? `آخر رحلة لك:\n${lastTrip.pickup} ← ${lastTrip.destination}\nالحالة: ${lastTrip.status}`
                        : `Your last trip:\n${lastTrip.pickup} → ${lastTrip.destination}\nStatus: ${lastTrip.status}`;

                    const detailsAction = ActionBuilders.showTripDetails(lastTrip.id);
                    response.action = detailsAction.action;
                    response.data = { ...detailsAction.data, trip: lastTrip };
                    response.quick_replies = ['رحلاتي السابقة', 'حجز رحلة جديدة', 'شكوى'];
                } else {
                    response.message = lang === 'ar'
                        ? 'ما عندك رحلات سابقة. تبي تحجز رحلة جديدة؟'
                        : "You don't have any previous trips. Would you like to book one?";
                    response.quick_replies = ['نعم، احجز رحلة', 'لا'];
                }
            }
            else if (detectedIntent.intent === 'COMPLAINT' || message.includes('3')) {
                response.message = lang === 'ar'
                    ? 'نأسف لسماع ذلك. 😔\nما هي مشكلتك؟'
                    : "We're sorry to hear that. 😔\nWhat's your issue?";
                response.quick_replies = ['مشكلة في رحلة', 'مشكلة في السائق', 'مشكلة في الدفع', 'أخرى'];

                await setConversationState(userId, STATES.COMPLAINT_FLOW, {});
            }
            else if (detectedIntent.intent === 'GREETING') {
                response.message = lang === 'ar'
                    ? 'مرحباً! كيف أساعدك اليوم؟\n1. حجز رحلة\n2. استعلام عن رحلة\n3. شكوى عن رحلة\n4. أسئلة عامة\n5. مساعدة تقنية'
                    : 'Hello! How can I help you today?\n1. Book a trip\n2. Trip inquiry\n3. File a complaint\n4. General questions\n5. Technical support';

                const menuAction = ActionBuilders.showQuickReplies([
                    'حجز رحلة', 'استعلام عن رحلة', 'شكوى', 'أسئلة عامة', 'مساعدة تقنية'
                ]);
                response.quick_replies = menuAction.quick_replies;
            }
            else if (detectedIntent.intent === 'UNKNOWN') {
                // Use LLM with system prompt from database for unknown queries
                try {
                    const systemPrompt = await getSystemPrompt();
                    const chatHistory = await getChatHistory(userId, 4);

                    const messages = [
                        { role: 'system', content: systemPrompt },
                        ...chatHistory.map(h => ({ role: h.role, content: h.content })),
                        { role: 'user', content: message }
                    ];

                    const llmResponse = await callLLM(messages);
                    response.message = llmResponse;
                    response.quick_replies = lang === 'ar'
                        ? ['حجز رحلة', 'رحلاتي', 'مساعدة']
                        : ['Book a ride', 'My trips', 'Help'];
                    response.confidence = 0.7;
                    console.log('🤖 LLM response generated for unknown query');
                } catch (llmError) {
                    console.error('LLM Error:', llmError);
                    // Fallback to default menu if LLM fails
                    response.message = lang === 'ar'
                        ? 'مرحباً! كيف أساعدك اليوم؟\n1. حجز رحلة\n2. استعلام عن رحلة\n3. شكوى عن رحلة\n4. أسئلة عامة\n5. مساعدة تقنية'
                        : 'Hello! How can I help you today?\n1. Book a trip\n2. Trip inquiry\n3. File a complaint\n4. General questions\n5. Technical support';
                    response.quick_replies = ['1', '2', '3', '4', '5'];
                }
            }
            else {
                // Default welcome
                response.message = lang === 'ar'
                    ? 'مرحباً! كيف أساعدك اليوم؟\n1. حجز رحلة\n2. استعلام عن رحلة\n3. شكوى عن رحلة\n4. أسئلة عامة\n5. مساعدة تقنية'
                    : 'Hello! How can I help you today?\n1. Book a trip\n2. Trip inquiry\n3. File a complaint\n4. General questions\n5. Technical support';
                response.quick_replies = ['1', '2', '3', '4', '5'];
            }
            break;

        // ----- AWAITING PICKUP LOCATION -----
        case STATES.AWAITING_PICKUP:
            // User should have selected pickup on map, we receive coordinates
            if (message.includes('lat:') || message.includes('location:') || convState.data.pickup_received) {
                response.message = lang === 'ar'
                    ? '✅ تم تحديد نقطة الانطلاق.\nإلى أين تريد الذهاب؟'
                    : '✅ Got your pickup location.\nWhere would you like to go?';

                const destAction = ActionBuilders.requestDestination(convState.data.pickup || message);
                response.action = destAction.action;
                response.data = destAction.data;

                await setConversationState(userId, STATES.AWAITING_DESTINATION, {
                    pickup: message
                });
            } else {
                // Still waiting for location
                response.message = lang === 'ar'
                    ? 'من فضلك حدد موقعك على الخريطة 📍'
                    : 'Please select your location on the map 📍';

                const pickupAction = ActionBuilders.requestPickup();
                response.action = pickupAction.action;
                response.data = pickupAction.data;
            }
            break;

        // ----- AWAITING DESTINATION -----
        case STATES.AWAITING_DESTINATION:
            if (message.includes('lat:') || message.includes('location:') || message.length > 3) {
                response.message = lang === 'ar'
                    ? '✅ ممتاز! اختر نوع الرحلة:\n1. توفير - 25 جنيه تقريباً\n2. سمارت برو - 40 جنيه تقريباً\n3. في اي بي - 55 جنيه تقريباً'
                    : '✅ Great! Choose ride type:\n1. Tawfer - ~25 EGP\n2. SmartPro - ~40 EGP\n3. VIP - ~55 EGP';

                const rideOptionsAction = ActionBuilders.showRideOptions(
                    convState.data.pickup,
                    message
                );
                response.action = rideOptionsAction.action;
                response.data = rideOptionsAction.data;
                response.quick_replies = rideOptionsAction.quick_replies;

                await setConversationState(userId, STATES.AWAITING_RIDE_TYPE, {
                    pickup: convState.data.pickup,
                    destination: message
                });
            } else {
                response.message = lang === 'ar'
                    ? 'من فضلك حدد وجهتك على الخريطة 📍'
                    : 'Please select your destination on the map 📍';

                const destAction = ActionBuilders.requestDestination(convState.data.pickup);
                response.action = destAction.action;
                response.data = destAction.data;
            }
            break;

        // ----- AWAITING RIDE TYPE -----
        case STATES.AWAITING_RIDE_TYPE:
            let rideType = 'tawfer';
            let estimatedFare = 25;

            if (message.includes('2') || message.includes('سمارت') || message.includes('برو') || message.includes('smartpro') || message.includes('pro')) {
                rideType = 'smartpro';
                estimatedFare = 40;
            } else if (message.includes('3') || message.includes('في اي بي') || message.includes('vip')) {
                rideType = 'vip';
                estimatedFare = 55;
            }

            response.message = lang === 'ar'
                ? `تأكيد الحجز:\n📍 من: ${convState.data.pickup}\n📍 إلى: ${convState.data.destination}\n💰 السعر التقريبي: ${estimatedFare} جنيه\n\nهل تريد تأكيد الحجز؟`
                : `Confirm booking:\n📍 From: ${convState.data.pickup}\n📍 To: ${convState.data.destination}\n💰 Estimated fare: ${estimatedFare} EGP\n\nConfirm booking?`;

            const fareAction = ActionBuilders.showFareEstimate(
                convState.data.pickup,
                convState.data.destination,
                rideType,
                estimatedFare
            );
            response.action = fareAction.action;
            response.data = fareAction.data;
            response.quick_replies = fareAction.quick_replies;

            await setConversationState(userId, STATES.AWAITING_CONFIRMATION, {
                ...convState.data,
                ride_type: rideType,
                estimated_fare: estimatedFare
            });
            break;

        // ----- AWAITING CONFIRMATION -----
        case STATES.AWAITING_CONFIRMATION:
            if (message.includes('تأكيد') || message.includes('نعم') || message.includes('confirm') || message.includes('yes')) {
                response.message = lang === 'ar'
                    ? '🎉 تم تأكيد الحجز!\nجاري البحث عن كابتن قريب منك...'
                    : '🎉 Booking confirmed!\nSearching for a nearby driver...';

                const confirmAction = ActionBuilders.confirmBooking({
                    pickup: convState.data.pickup,
                    destination: convState.data.destination,
                    ride_type: convState.data.ride_type,
                    estimated_fare: convState.data.estimated_fare
                });
                response.action = confirmAction.action;
                response.data = confirmAction.data;
                response.ui_hint = confirmAction.ui_hint;

                await setConversationState(userId, STATES.TRIP_ACTIVE, convState.data);
            } else if (message.includes('إلغاء') || message.includes('cancel') || message.includes('لا')) {
                response.message = lang === 'ar'
                    ? 'تم إلغاء الحجز. كيف أقدر أساعدك؟'
                    : 'Booking cancelled. How can I help you?';
                response.quick_replies = ['حجز رحلة جديدة', 'شيء آخر'];

                await setConversationState(userId, STATES.START, {});
            } else if (message.includes('تغيير') || message.includes('change')) {
                response.message = lang === 'ar'
                    ? 'اختر نوع الرحلة:\n1. توفير\n2. سمارت برو\n3. في اي بي'
                    : 'Choose ride type:\n1. Tawfer\n2. SmartPro\n3. VIP';

                await setConversationState(userId, STATES.AWAITING_RIDE_TYPE, convState.data);
            }
            break;

        // ----- ACTIVE TRIP -----
        case STATES.TRIP_ACTIVE:
            const currentRide = activeRide || await getLastTrip(userId);

            if (detectedIntent.intent === 'CANCEL_TRIP' || message.includes('إلغاء')) {
                response.message = lang === 'ar'
                    ? '⚠️ هل أنت متأكد من إلغاء الرحلة؟\nقد يتم تطبيق رسوم إلغاء.'
                    : '⚠️ Are you sure you want to cancel?\nCancellation fees may apply.';

                const cancelAction = ActionBuilders.confirmCancelTrip(currentRide?.id, 5);
                response.action = cancelAction.action;
                response.data = cancelAction.data;
                response.quick_replies = cancelAction.quick_replies;

                await setConversationState(userId, STATES.AWAITING_CANCEL_CONFIRM, { trip_id: currentRide?.id });
            }
            else if (detectedIntent.intent === 'CONTACT_DRIVER' || message.includes('اتصل')) {
                response.message = lang === 'ar'
                    ? '📞 جاري الاتصال بالكابتن...'
                    : '📞 Connecting to your driver...';

                const contactAction = ActionBuilders.contactDriver(currentRide?.id, currentRide?.driver_phone);
                response.action = contactAction.action;
                response.data = contactAction.data;
            }
            else if (message.includes('أين') || message.includes('where')) {
                response.message = lang === 'ar'
                    ? `الكابتن ${currentRide?.driver_name || 'في الطريق'} في الطريق إليك.\nيمكنك تتبع موقعه على الخريطة.`
                    : `Driver ${currentRide?.driver_name || ''} is on the way.\nYou can track their location on the map.`;

                const trackingAction = ActionBuilders.showTripTracking(currentRide?.id);
                response.action = trackingAction.action;
                response.data = { ...trackingAction.data, ride: currentRide };
                response.quick_replies = ['إلغاء الرحلة', 'اتصل بالكابتن'];
            }
            else {
                response.message = lang === 'ar'
                    ? 'كيف أقدر أساعدك برحلتك الحالية؟'
                    : 'How can I help you with your current trip?';
                response.quick_replies = ['أين الكابتن؟', 'إلغاء الرحلة', 'اتصل بالكابتن', 'مشكلة'];
            }
            break;

        // ----- AWAITING CANCEL CONFIRMATION -----
        case STATES.AWAITING_CANCEL_CONFIRM:
            if (message.includes('نعم') || message.includes('yes') || message.includes('إلغاء')) {
                response.message = lang === 'ar'
                    ? '❌ تم إلغاء الرحلة.\nهل تريد حجز رحلة جديدة؟'
                    : '❌ Trip cancelled.\nWould you like to book a new trip?';

                response.action = ACTION_TYPES.CANCEL_TRIP;
                response.data = { trip_id: convState.data.trip_id };
                response.quick_replies = ['حجز رحلة جديدة', 'لا، شكراً'];

                await setConversationState(userId, STATES.START, {});
            } else {
                response.message = lang === 'ar'
                    ? '✅ تم إلغاء طلب الإلغاء. رحلتك مستمرة.'
                    : '✅ Cancellation request cancelled. Your trip continues.';

                await setConversationState(userId, STATES.TRIP_ACTIVE, convState.data);
            }
            break;

        // ----- COMPLAINT FLOW -----
        case STATES.COMPLAINT_FLOW:
            response.message = lang === 'ar'
                ? 'شكراً لملاحظتك. تم تسجيل شكواك وسيتواصل معك فريق الدعم خلال 24 ساعة.\nهل تحتاج مساعدة بشيء آخر؟'
                : 'Thank you for your feedback. Your complaint has been recorded and our support team will contact you within 24 hours.\nDo you need help with anything else?';

            response.quick_replies = ['حجز رحلة', 'لا، شكراً'];
            await setConversationState(userId, STATES.START, {});
            break;

        // ----- RESOLVED -----
        case STATES.RESOLVED:
            response.message = lang === 'ar'
                ? 'هل تحتاج مساعدة بشيء آخر؟'
                : 'Is there anything else I can help you with?';
            response.quick_replies = ['حجز رحلة', 'رحلاتي', 'لا، شكراً'];

            await setConversationState(userId, STATES.START, {});
            break;

        default:
            // Reset to start
            await setConversationState(userId, STATES.START, {});
            response.message = lang === 'ar'
                ? 'مرحباً! كيف أساعدك اليوم?'
                : 'Hello! How can I help you today?';
            response.quick_replies = ['حجز رحلة', 'رحلاتي', 'مساعدة'];
            break;
    }

    return response;
}

// ============================================
// 🚀 MAIN CHAT ENDPOINT (V2 with Actions)
// ============================================

app.post('/chat', async (req, res) => {
    try {
        const { user_id, message, location_data } = req.body;

        if (!user_id || !message) {
            return res.status(400).json({
                message: 'Please provide user_id and message',
                action: ACTION_TYPES.NONE,
                data: {},
                quick_replies: [],
                confidence: 0,
                handoff: false
            });
        }

        // Detect language
        const lang = isArabic(message) ? 'ar' : 'en';

        // If location_data is provided, merge it with the message for processing
        let processedMessage = message;
        if (location_data && location_data.lat && location_data.lng) {
            processedMessage = `location:${location_data.lat},${location_data.lng} ${message}`;

            // Update conversation state with location
            const convState = await getConversationState(user_id);
            if (convState.state === STATES.AWAITING_PICKUP) {
                await setConversationState(user_id, convState.state, {
                    ...convState.data,
                    pickup: location_data,
                    pickup_received: true
                });
            } else if (convState.state === STATES.AWAITING_DESTINATION) {
                await setConversationState(user_id, convState.state, {
                    ...convState.data,
                    destination: location_data
                });
            }
        }

        // Process conversation
        const response = await processConversation(user_id, processedMessage, lang);

        // Save to history
        await saveChat(user_id, 'user', message);
        await saveChat(user_id, 'assistant', response.message, response.action, response.data);

        // Return structured response
        res.json({
            message: response.message,
            action: response.action,
            data: response.data,
            quick_replies: response.quick_replies,
            ui_hint: response.ui_hint,
            confidence: response.confidence,
            handoff: response.handoff,
            language: lang,
            model: 'Rule-based + LLM Fallback'
        });

    } catch (error) {
        console.error('Chat error:', error);
        const lang = isArabic(req.body?.message) ? 'ar' : 'en';
        res.status(500).json({
            message: lang === 'ar'
                ? 'عذراً، حصل خطأ. حاول تاني.'
                : 'Sorry, an error occurred. Please try again.',
            action: ACTION_TYPES.NONE,
            data: {},
            quick_replies: [],
            confidence: 0,
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

        // Get current state
        const convState = await getConversationState(user_id);

        // Update state with location
        if (type === 'pickup' || convState.state === STATES.AWAITING_PICKUP) {
            await setConversationState(user_id, STATES.AWAITING_DESTINATION, {
                ...convState.data,
                pickup: location_data,
                pickup_address: address
            });
        } else if (type === 'destination' || convState.state === STATES.AWAITING_DESTINATION) {
            await setConversationState(user_id, STATES.AWAITING_RIDE_TYPE, {
                ...convState.data,
                destination: location_data,
                destination_address: address
            });
        }

        // Process as a chat message to get the next step
        const lang = 'ar';
        const response = await processConversation(user_id, `location:${lat},${lng}`, lang);

        res.json({
            success: true,
            ...response
        });

    } catch (error) {
        console.error('Location submission error:', error);
        res.status(500).json({ success: false, error: error.message });
    }
});

// ============================================
// 🔧 ADMIN ENDPOINTS
// ============================================

app.post('/admin/clear-memory', async (req, res) => {
    try {
        const { user_id } = req.body;
        await pool.execute('DELETE FROM ai_chat_history WHERE user_id = ?', [user_id]);
        await pool.execute('DELETE FROM ai_conversation_state WHERE user_id = ?', [user_id]);
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

app.post('/admin/reset-state', async (req, res) => {
    try {
        const { user_id } = req.body;
        await setConversationState(user_id, STATES.START, {});
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

app.get('/admin/user-state/:user_id', async (req, res) => {
    try {
        const state = await getConversationState(req.params.user_id);
        res.json(state);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.get('/health', async (req, res) => {
    try {
        await pool.execute('SELECT 1');
        res.json({ status: 'ok', database: 'connected', version: 'v2-actions' });
    } catch (error) {
        res.status(500).json({ status: 'error', database: 'disconnected', details: error.message });
    }
});

// ============================================
// 📊 ACTION TYPES ENDPOINT (For Flutter)
// ============================================

app.get('/action-types', (req, res) => {
    res.json({
        action_types: ACTION_TYPES,
        ui_hints: UI_HINTS,
        description: 'Use these action types to handle responses in Flutter'
    });
});

// ============================================
// 🚀 START
// ============================================

const PORT = process.env.PORT || 3000;

async function start() {
    try {
        await initDatabase();
        app.listen(PORT, () => {
            console.log(`
╔════════════════════════════════════════════════╗
║  🚗 RIDE SUPPORT - Customer Service Bot V2    ║
║  ─────────────────────────────────────────────║
║  Server: http://localhost:${PORT}                 ║
║  API: /chat, /submit-location                 ║
║  Actions: /action-types                       ║
║  DB: ${DB_CONFIG.database}                    ║
╚════════════════════════════════════════════════╝
            `);
        });
    } catch (error) {
        console.error('Failed to start:', error);
        process.exit(1);
    }
}

start();
