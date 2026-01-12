# 🚗 SmartLine AI Chatbot V3.2

**Production-ready AI chatbot for SmartLine ride-hailing platform** with comprehensive support for both customers and captains, featuring multi-language support, intelligent intent classification, and robust state management.

---

## 📋 Table of Contents

- [Features](#-features)
- [Architecture](#-architecture)
- [Quick Start](#-quick-start)
- [API Documentation](#-api-documentation)
- [Configuration](#-configuration)
- [Project Structure](#-project-structure)
- [Key Components](#-key-components)
- [Testing](#-testing)
- [Deployment](#-deployment)
- [Troubleshooting](#-troubleshooting)

---

## ✨ Features

### **Customer Features**
- ✅ **Trip Booking** - Complete booking flow (pickup, destination, vehicle type, confirmation)
- ✅ **Trip Management** - Track active trips, view trip history, cancel trips
- ✅ **Driver Interaction** - Contact driver, view driver information
- ✅ **Multi-language Support** - Arabic, English, and Arabizi with automatic detection
- ✅ **Language Consistency** - Sticky sessions with cooldown to prevent language switching
- ✅ **Content Moderation** - Real-time profanity and threat detection
- ✅ **Personalization** - Favorite locations, trip history, personalized responses
- ✅ **Safety Features** - Emergency trigger, live location sharing
- ✅ **State Management** - Versioned conversation states with automatic recovery

### **Captain Features**
- ✅ **Registration Status Check** - Check registration status in multiple languages
- ✅ **Status Types Supported:**
  - Under Review
  - Documents Missing
  - Approved
  - Rejected
  - Background Check
  - System Delay
- ✅ **Multi-language Support** - Arabic, English, and Arabizi
- ⚠️ **Note:** Operational features (earnings, trip requests) must use the Captain app

### **System Features**
- ✅ **Hybrid Intent Classification** - 3-layer system (Regex → NLP → LLM)
- ✅ **Feature Flags** - Gradual rollout and A/B testing support
- ✅ **Degradation Policies** - Graceful fallback when components fail
- ✅ **Performance Monitoring** - Metrics, logging, and health checks
- ✅ **Rate Limiting** - Burst protection and per-user limits
- ✅ **Security Hardening**:
  - Input sanitization (XSS, SQL injection protection)
  - Prompt injection detection and blocking
  - Out-of-context question filtering
  - CORS, security headers
- ✅ **Database Resilience** - Connection pooling, retry logic, graceful degradation
- ✅ **Content Moderation** - Multi-language profanity and threat detection

---

## 🏗️ Architecture

### **System Flow**
```
POST /chat
  ↓
1. Rate Limiting (burst + main)
  ↓
2. Input Validation & Sanitization
  ↓
3. Language Detection (LanguageManager)
  ↓
4. Content Moderation (Profanity/Threat Detection)
  ↓
5. User Type Detection (Customer/Captain)
  ↓
6. Conversation Processing
   ├─ Get Conversation State (StateGuard)
   ├─ Get User Preferences
   ├─ Intent Classification (Hybrid: L1/L2/L3)
   ├─ State-Based Flow Processing
   └─ LLM Generation (if needed)
  ↓
7. Language Enforcement (if enabled)
  ↓
8. Save Chat History
  ↓
9. Return Response with Flutter Actions
```

### **Intent Classification Pipeline**
- **L1 (Regex)**: Fast pattern matching (< 1ms) - 95% confidence threshold
- **L2 (NLP)**: Naive Bayes classifier (~5ms) - 75% confidence threshold
- **L3 (LLM)**: Groq/Llama 3.3 70B fallback (~500-2000ms) - Highest accuracy

### **State Management**
- **Versioned States**: Automatic migration between versions
- **Recovery**: Automatic repair of corrupted states
- **Compatibility**: Backward compatible with older state versions

---

## 🚀 Quick Start

### **Prerequisites**
- Node.js >= 16.0.0
- MySQL 5.7+ or MariaDB 10.3+
- Groq API key (for LLM features)

### **1. Install Dependencies**
```bash
npm install
```

### **2. Configure Environment**
Create a `.env` file in the project root:
```env
# Database Configuration
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=your_password
DB_NAME=merged2
DB_POOL_SIZE=20

# API Keys
GROQ_API_KEY=your_groq_api_key

# Server Configuration
PORT=3000
NODE_ENV=production
REQUEST_TIMEOUT=30000

# Feature Flags (optional - defaults to false)
FF_LANGUAGE_ENFORCEMENT=true
FF_HYBRID_CLASSIFIER=true
FF_CAPTAIN_V2=true
FF_ML_MODERATION=false

# Rate Limiting
RATE_LIMIT_WINDOW_MS=60000
RATE_LIMIT_MAX=10

# Laravel Integration (for autocomplete)
LARAVEL_BASE_URL=https://smartline-it.com
DEFAULT_ZONE_ID=182440b2-da90-11f0-bfad-581122408b4d
```

### **3. Start Server**
```bash
npm start
```

You should see:
```
╔════════════════════════════════════════════════════════════╗
║   🚗 SMARTLINE AI CHATBOT V3.2                            ║
║   Server:    http://localhost:3000                         ║
╚════════════════════════════════════════════════════════════╝
```

### **4. Test the Server**
```bash
# In another terminal
node test_chatbot.js
```

---

## 📡 API Documentation

### **POST /chat**
Main chat endpoint for customer and captain interactions.

**Request:**
```json
{
  "user_id": "user-123",
  "message": "مرحبا",
  "location_data": {
    "lat": 30.0444,
    "lng": 31.2357,
    "zone_id": "zone-123"
  }
}
```

**Response:**
```json
{
  "message": "مرحباً! كيف أقدر أساعدك؟",
  "action": "none",
  "data": {},
  "quick_replies": ["🚗 حجز رحلة", "📋 رحلاتي", "🎧 مساعدة"],
  "ui_hint": null,
  "confidence": 0.95,
  "handoff": false,
  "language": {
    "primary": "ar",
    "isArabizi": false,
    "rtl": true
  },
  "userType": "customer",
  "model": "Llama 3.3 70B",
  "_debug": {
    "requestId": "...",
    "responseTime": "245ms"
  }
}
```

### **POST /submit-location**
Submit location coordinates for trip booking.

**Request:**
```json
{
  "user_id": "user-123",
  "lat": 30.0444,
  "lng": 31.2357,
  "address": "Nasr City, Cairo",
  "type": "pickup"
}
```

### **GET /health**
Health check endpoint.

**Response:**
```json
{
  "status": "ok",
  "timestamp": "2025-01-XX...",
  "version": "v3.2",
  "uptime": 3600,
  "checks": {
    "database": { "status": "healthy" },
    "memory": { "status": "healthy" }
  }
}
```

### **GET /admin/stats**
Admin statistics (requires authentication).

### **GET /action-types**
List all available Flutter action types.

### **GET /metrics/prometheus**
Prometheus-compatible metrics endpoint.

---

## 🔧 Configuration

### **Feature Flags**
Control feature rollout via environment variables:

| Flag | Description | Default |
|------|-------------|---------|
| `FF_LANGUAGE_ENFORCEMENT` | Enable strict language enforcement cascade | `false` |
| `FF_HYBRID_CLASSIFIER` | Enable hybrid intent classification (L1/L2/L3) | `false` |
| `FF_L3_ENABLED` | Enable LLM fallback in classifier | `true` |
| `FF_CAPTAIN_V2` | Enable new captain registration flow | `false` |
| `FF_ML_MODERATION` | Enable ML-based moderation (Phase 1: logging) | `false` |
| `FF_PERSONALIZATION_V2` | Enable enhanced personalization | `false` |
| `FF_STATE_V2` | Enable state versioning system | `true` |

### **Database Schema**
Required tables:
- `users` - User accounts with `user_role` column
- `drivers` - Captain/driver information with `approval_status`, `is_verified`, `is_active`
- `trip_requests` - Trip bookings
- `trip_request_coordinates` - Location data
- `trip_status` - Trip status tracking
- `vehicle_categories` - Available vehicle types
- `trip_fares` - Fare calculation data
- `zones` - Service zones
- `ai_chat_history` - Chat history (auto-created)
- `ai_conversation_state` - Conversation state (auto-created)
- `ai_user_preferences` - User preferences (auto-created)

### **Performance Budgets**
- Language enforcement: +10ms max
- Hybrid classifier: +20ms max (vs regex-only)
- Personalization: +15ms max
- ML moderation: +50ms max (only on trigger)
- Total p99 latency: < 500ms for non-LLM responses

---

## 📁 Project Structure

```
ai-chat-bot-v3/
├── chat.js                      # Main application (Express server)
├── classifier.js                # Hybrid intent classifier
├── actions.js                   # Flutter action definitions
├── chatbot_capt.py              # Python captain chatbot (reference)
│
├── utils/                       # Utility modules
│   ├── language.js             # Language detection and management
│   ├── moderation.js           # Content moderation
│   ├── captainRegistrationBot.js # Captain registration status
│   ├── captainVerification.js  # Captain access control
│   ├── stateGuard.js           # State management utilities
│   ├── featureFlags.js        # Feature flag system
│   ├── degradation.js         # Degradation policies
│   ├── mlModeration.js        # ML moderation (Phase 1)
│   ├── logger.js              # Logging system
│   ├── cache.js               # Response caching
│   ├── auth.js                # Admin authentication
│   ├── escalationMessages.js  # Escalation templates
│   ├── circuitBreaker.js      # Circuit breaker pattern
│   └── validation.js          # Input validation
│
├── core/
│   └── personalization.js     # Personalization engine
│
├── public/
│   └── index.html             # Web interface
│
├── test_chatbot.js            # Test suite
├── test_bugfixes.js           # Bug fix tests
│
├── package.json               # Dependencies
├── .env                       # Environment variables (create this)
├── README.md                  # This file
├── FIXES_APPLIED.md          # Recent fixes documentation
└── QUICK_START.md            # Quick start guide
```

---

## 🔑 Key Components

### **LanguageManager** (`utils/language.js`)
- Automatic language detection (Arabic, English, Arabizi)
- Sticky session management with cooldown
- Language enforcement cascade (validate → regenerate → translate → fallback)
- Session persistence and cleanup

### **IntentClassifier** (`classifier.js`)
- **L1**: Regex pattern matching (fastest, 95% confidence threshold)
- **L2**: Naive Bayes NLP classifier (~5ms, 75% confidence threshold)
- **L3**: LLM fallback using Groq/Llama 3.3 70B (most accurate, ~500-2000ms)
- Ambiguous intent detection and clarification

### **StateGuard** (`utils/stateGuard.js`)
- State versioning (currently v3)
- Automatic migration between versions
- State validation and repair
- Compatibility matrix for version transitions

### **CaptainRegistrationBot** (`utils/captainRegistrationBot.js`)
- Registration status determination
- Multi-language response templates
- Database integration for status checking
- Bad word filtering

### **Moderation** (`utils/moderation.js`)
- Multi-language profanity detection (EN/AR/Arabizi)
- Threat detection
- Evasion pattern detection
- Zero-latency caching
- Severity classification (none, low, medium, high, critical)

### **FeatureFlags** (`utils/featureFlags.js`)
- Gradual rollout support
- User-specific allowlists
- Percentage-based rollout
- Configuration management

### **Degradation** (`utils/degradation.js`)
- Query budgeting
- Performance budgets
- Graceful fallback policies
- Component failure handling

---

## 🧪 Testing

### **Run Test Suite**
```bash
node test_chatbot.js
```

### **Test Cases Included**
- Customer greeting (Arabic & English)
- Book trip intent detection
- Captain registration status check
- Language switching
- Error handling

### **Manual Testing**
```bash
# Test health endpoint
curl http://localhost:3000/health

# Test chat endpoint
curl -X POST http://localhost:3000/chat \
  -H "Content-Type: application/json" \
  -d '{"user_id":"test-123","message":"مرحبا"}'
```

---

## 🚢 Deployment

### **Production Checklist**
- [ ] Set `NODE_ENV=production`
- [ ] Configure database connection pool size
- [ ] Set up proper logging (Winston)
- [ ] Configure rate limiting
- [ ] Set up monitoring (Prometheus metrics)
- [ ] Enable feature flags gradually
- [ ] Configure CORS for production domains
- [ ] Set up SSL/TLS
- [ ] Configure reverse proxy (Nginx)
- [ ] Set up process manager (PM2)

### **PM2 Configuration**
```bash
pm2 start ecosystem.config.js
```

### **Nginx Configuration**
See `nginx-chatbot.conf` for reverse proxy setup.

---

## 🐛 Troubleshooting

### **Server Won't Start**
- Check if port 3000 is available
- Verify database connection in `.env`
- Ensure all dependencies are installed: `npm install`

### **"Cannot find module" Errors**
```bash
npm install
```

### **Database Connection Failed**
- Verify MySQL is running
- Check `.env` database credentials
- Ensure database exists: `CREATE DATABASE merged2;`

### **Tests Failing with ECONNREFUSED**
- **Server must be running first!** Start with `npm start` in one terminal
- Then run `node test_chatbot.js` in another terminal

### **LLM Not Responding**
- Verify `GROQ_API_KEY` is set in `.env`
- Check API quota/limits
- Review logs for LLM errors

### **Language Inconsistency**
- Enable `FF_LANGUAGE_ENFORCEMENT=true`
- Check LanguageManager logs
- Verify language detection is working

### **Captain Status Not Showing**
- Verify user has `user_role = 'driver'` in `users` table
- Check `drivers` table has corresponding record
- Review `utils/captainRegistrationBot.js` logs

---

## 📝 Recent Changes (V3.2.1)

### Security Enhancements
- ✅ **Out-of-Context Question Filtering** - Blocks questions not related to ride-hailing (e.g., "who owns the company?", general knowledge)
- ✅ **Prompt Injection Protection** - Detects and blocks attempts to manipulate the AI
- ✅ **Enhanced Input Sanitization** - SQL injection, XSS, and command injection protection
- ✅ **Rate Limiter Fix** - Fixed IPv6 validation warnings

### Language Improvements
- ✅ **Stricter Language Enforcement** - Much stronger prompts to prevent language mixing
- ✅ **Improved Language Instructions** - Clear multi-line instructions for LLM

### Bug Fixes
- ✅ Fixed moderation regex patterns (removed invalid asterisk characters)
- ✅ Fixed captain verification logic
- ✅ Added comprehensive error handling
- ✅ Improved response validation
- ✅ Enhanced state management

---

## 📚 Additional Documentation

- **FIXES_APPLIED.md** - Detailed fix documentation
- **QUICK_START.md** - Quick start guide

---

## 🤝 Contributing

1. Follow existing code style
2. Add tests for new features
3. Update documentation
4. Test with both customer and captain flows

---

## 📄 License

Proprietary - SmartLine IT

---

## 📞 Support

For issues or questions:
1. Check `FIXES_APPLIED.md` for known issues
2. Review server logs in `logs/` directory
3. Test with `test_chatbot.js`
4. Verify database connection and environment variables

---

**Version:** 3.2  
**Status:** ✅ Production Ready  
**Last Updated:** January 2025

---

## 🎯 Quick Reference

### **Start Server**
```bash
npm start
```

### **Run Tests**
```bash
node test_chatbot.js
```

### **Check Health**
```bash
curl http://localhost:3000/health
```

### **View Stats**
```bash
curl http://localhost:3000/admin/stats
```

---

**Built with ❤️ for SmartLine**
