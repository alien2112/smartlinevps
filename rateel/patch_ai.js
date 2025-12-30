
const fs = require('fs');
const path = require('path');

const filePath = '../ai-chat-bot/chat_v2.js';
let content = fs.readFileSync(filePath, 'utf8');

// 1. Add isChatbotEnabled helper
const helperFunction = `
async function isChatbotEnabled() {
    try {
        const [rows] = await pool.execute(
            "SELECT value FROM business_settings WHERE key_name = 'ai_chatbot_enable' AND settings_type = 'ai_config' LIMIT 1"
        );
        return rows.length > 0 && parseInt(rows[0].value) === 1;
    } catch (e) {
        console.error('Error checking chatbot status:', e);
        return true; // Default to enabled if error
    }
}

`;

if (!content.includes('async function isChatbotEnabled()')) {
    content = content.replace('async function getActiveRide', helperFunction + 'async function getActiveRide');
}

// 2. Add check to /chat endpoint
const checkLogic = `
app.post('/chat', async (req, res) => {
    try {
        const { user_id, message, location_data } = req.body;

        // CHECK IF ENABLED IN SETTINGS
        const enabled = await isChatbotEnabled();
        if (!enabled) {
            const lang = isArabic(message || '') ? 'ar' : 'en';
            return res.json({
                message: lang === 'ar' 
                    ? "روبوت الدردشة غير متاح حالياً. يرجى التواصل مع الدعم الفني." 
                    : "AI Chatbot is currently disabled. Please contact support.",
                action: ACTION_TYPES.NONE,
                data: {},
                quick_replies: [],
                confidence: 1,
                handoff: true
            });
        }
`;

if (!content.includes('// CHECK IF ENABLED IN SETTINGS')) {
    content = content.replace(/app\.post\('\/chat', async \(req, res\) => \{[\s\S]*?try \{[\s\S]*?const \{ user_id, message, location_data \} = req\.body;/, checkLogic);
}

fs.writeFileSync(filePath, content);
console.log('Successfully patched chat_v2.js');
