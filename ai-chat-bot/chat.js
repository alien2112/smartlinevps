// ============================================
// 🚗 RIDE SUPPORT - CUSTOMER SERVICE CHATBOT
// ============================================

const express = require('express');
const cors = require('cors');
const mysql = require('mysql2/promise');
const path = require('path');
require('dotenv').config();

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
    password: process.env.DB_PASSWORD,
    database: process.env.DB_NAME || 'smartline_new2',
    waitForConnections: true,
    connectionLimit: 10
};

let pool;

async function initDatabase() {
    try {
        pool = mysql.createPool(DB_CONFIG);

        // Use a dedicated table for AI chat history
        // UUIDs in Laravel are 36 chars
        await pool.execute(`
            CREATE TABLE IF NOT EXISTS ai_chat_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(36) NOT NULL,
                role VARCHAR(20) NOT NULL,
                content TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_created (user_id, created_at)
            )
        `);

        console.log('✅ Database connected & initialized');
    } catch (err) {
        console.error('❌ Database initialization failed:', err);
    }
}

// ============================================
// 🧠 SYSTEM PROMPTS
// ============================================

const PROMPT_EN = `You are Ride Support, a helpful customer service agent for a ride-hailing app like Uber.

RULES:
- Maximum 4 short lines per response
- Ask only ONE question at a time
- Be friendly and professional
- Use numbered options (1-5) when asking user to choose
- Never mention internal systems or AI

ACTIVE RIDE:
If there is an active ride, always acknowledge it first:
"Your current trip: Captain [name], [pickup] → [destination]"

SAFETY:
If user mentions danger, harassment, accident, or unsafe driving:
1. Ask: "Are you safe right now? (Yes/No)"
2. If not safe: "Please call emergency services. I'm connecting you to support now."

FLOW:
1. Greet briefly
2. Ask what they need help with (give 4-5 options)
3. After they choose, ask ONE follow-up question
4. Provide solution or escalate

Be helpful, concise, and human-like.`;

const PROMPT_AR = `أنت "دعم الرحلات"، موظف خدمة عملاء لتطبيق توصيل زي أوبر.

القواعد:
- أقصى حد 4 سطور قصيرة في كل رد
- اسأل سؤال واحد بس في كل مرة
- كن ودود ومحترف
- استخدم أرقام (1-5) لما تعرض اختيارات
- متذكرش أنظمة داخلية أو AI

الرحلة النشطة:
لو في رحلة نشطة، اعترف بيها الأول:
"رحلتك الحالية: الكابتن [الاسم]، [المكان] → [الوجهة]"

الأمان:
لو العميل ذكر خطر أو تحرش أو حادثة أو قيادة خطرة:
1. اسأل: "إنت بأمان دلوقتي؟ (أيوه/لا)"
2. لو مش بأمان: "اتصل بالطوارئ فوراً. بحولك للدعم البشري."

طريقة المحادثة:
1. سلّم بشكل مختصر
2. اسأل محتاج مساعدة في إيه (اعرض 4-5 اختيارات)
3. بعد ما يختار، اسأل سؤال متابعة واحد
4. قدم الحل أو حوّل للدعم

كن مفيد، مختصر، وطبيعي.`;

// ============================================
// 🔍 LANGUAGE DETECTION
// ============================================

function isArabic(text) {
    return /[\u0600-\u06FF]/.test(text);
}

// ============================================
// 🗄️ DATABASE HELPERS
// ============================================

async function ensureUser(userId) {
    // Just verify existence. Do NOT insert.
    try {
        const [rows] = await pool.execute('SELECT id FROM users WHERE id = ?', [userId]);
        if (rows.length === 0) {
            console.warn(`⚠️ User ${userId} not found in main DB.`);
        }
    } catch (e) {
        console.error("Error checking user:", e);
    }
}

async function getRide(userId) {
    try {
        // Query the REAL Laravel tables
        // trip_requests (tr) JOIN trip_request_coordinates (trc) JOIN users (driver)
        const [rows] = await pool.execute(`
            SELECT 
                tr.id, 
                tr.current_status as status,
                tr.driver_id,
                COALESCE(trc.pickup_address, 'Pickup Location') as pickup,
                COALESCE(trc.destination_address, 'Destination') as destination,
                COALESCE(CONCAT(d.first_name, ' ', d.last_name), 'Assigning Driver...') as driver_name
            FROM trip_requests tr
            LEFT JOIN trip_request_coordinates trc ON tr.id = trc.trip_request_id
            LEFT JOIN users d ON tr.driver_id = d.id
            WHERE tr.customer_id = ?
            ORDER BY tr.created_at DESC
            LIMIT 1
        `, [userId]);

        return rows[0] || null;
    } catch (error) {
        console.error('Database Error (getRide):', error);
        return null;
    }
}

async function getChatHistory(userId) {
    try {
        const [rows] = await pool.execute(
            'SELECT role, content FROM ai_chat_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 6',
            [userId]
        );
        return rows.reverse();
    } catch (e) {
        console.error('Error fetching history:', e);
        return [];
    }
}

async function saveChat(userId, userMsg, botReply) {
    try {
        await pool.execute(
            'INSERT INTO ai_chat_history (user_id, role, content) VALUES (?, ?, ?)',
            [userId, 'user', userMsg]
        );
        await pool.execute(
            'INSERT INTO ai_chat_history (user_id, role, content) VALUES (?, ?, ?)',
            [userId, 'assistant', botReply]
        );

        // Cleanup old messages (keep last 10)
        // Note: DELETE with nested subquery same table can be tricky in MySQL.
        // Using a simpler approach or ignoring cleanup for performance is often okay for MVPs.
        // But here is a safe way:
        /*
        await pool.execute(`
            DELETE FROM ai_chat_history 
            WHERE user_id = ? AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM ai_chat_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 10
                ) as t
            )
        `, [userId, userId]);
        */
    } catch (e) {
        console.error('Error saving chat:', e);
    }
}

async function clearChat(userId) {
    await pool.execute('DELETE FROM ai_chat_history WHERE user_id = ?', [userId]);
}

// ============================================
// 🤖 GROQ API - LLAMA 3.1 70B
// ============================================

async function callLLM(messages) {
    // Retry logic could be added here
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
// 💬 BUILD PROMPT
// ============================================

function buildMessages(userMessage, ride, history, lang) {
    const systemPrompt = lang === 'ar' ? PROMPT_AR : PROMPT_EN;

    let context = '';
    // Statuses in Laravel: pending, accepted, ongoing, completed, cancelled
    // Ride logic: meaningful context only if active
    const activeStatuses = ['pending', 'accepted', 'ongoing', 'arrived'];

    if (ride && activeStatuses.includes(ride.status)) {
        if (lang === 'ar') {
            context = `\n\n[رحلة نشطة: الكابتن ${ride.driver_name}، من ${ride.pickup} إلى ${ride.destination}، الحالة: ${ride.status}]`;
        } else {
            context = `\n\n[Active ride: Captain ${ride.driver_name}, from ${ride.pickup} to ${ride.destination}, status: ${ride.status}]`;
        }
    } else {
        context = lang === 'ar' ? '\n\n[مفيش رحلة نشطة حالياً]' : '\n\n[No active ride currently]';
    }

    const messages = [
        { role: 'system', content: systemPrompt + context }
    ];

    // Add conversation history
    for (const msg of history) {
        messages.push({ role: msg.role, content: msg.content });
    }

    // Add current message
    messages.push({ role: 'user', content: userMessage });

    return messages;
}

// ============================================
// 🚀 MAIN CHAT ENDPOINT
// ============================================

app.get('/chat', (req, res) => res.send('AI Chatbot Service is Running!'));
app.post('/chat', async (req, res) => {
    try {
        const { user_id, message } = req.body;

        if (!user_id || !message) {
            return res.status(400).json({
                reply: 'Please provide user_id and message',
                confidence: 0,
                handoff: false
            });
        }

        // Detect language
        const lang = isArabic(message) ? 'ar' : 'en';

        // Check user (optional, just logging)
        await ensureUser(user_id);

        // Get ride and history
        const ride = await getRide(user_id);
        const history = await getChatHistory(user_id);

        // Build messages
        const messages = buildMessages(message, ride, history, lang);

        // Call LLM
        const reply = await callLLM(messages);

        // Save to history
        await saveChat(user_id, message, reply);

        // Check for safety/handoff keywords
        const safetyKeywords = ['خطر', 'تحرش', 'حادث', 'emergency', 'accident', 'danger', 'harassment', 'unsafe', 'not safe'];
        const needsHandoff = safetyKeywords.some(kw => message.toLowerCase().includes(kw) || reply.toLowerCase().includes(kw));

        res.json({
            reply: reply,
            confidence: 0.85,
            handoff: needsHandoff,
            language: lang,
            model: 'Llama 3.3 70B'
        });

    } catch (error) {
        console.error('Chat error:', error);
        res.status(500).json({
            reply: isArabic(req.body?.message)
                ? 'عذراً، حصل خطأ. حاول تاني.'
                : 'Sorry, an error occurred. Please try again.',
            confidence: 0,
            handoff: true
        });
    }
});

// ============================================
// 🔧 ADMIN ENDPOINTS
// ============================================

app.post('/admin/clear-memory', async (req, res) => {
    try {
        const { user_id } = req.body;
        await clearChat(user_id);
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

app.get('/health', async (req, res) => {
    try {
        await pool.execute('SELECT 1');
        res.json({ status: 'ok', database: 'connected' });
    } catch (error) {
        res.status(500).json({ status: 'error', database: 'disconnected', details: error.message });
    }
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
╔════════════════════════════════════════════╗
║  🚗 RIDE SUPPORT - Customer Service Bot    ║
║  ─────────────────────────────────────────║
║  Server: http://localhost:${PORT}             ║
║  Model: Llama 3.3 70B (Groq)              ║
║  DB: ${DB_CONFIG.database}                ║
╚════════════════════════════════════════════╝
            `);
        });
    } catch (error) {
        console.error('Failed to start:', error);
        process.exit(1);
    }
}

start();
