# 🚗 SmartLine AI Chatbot V3

Production-ready AI chatbot for SmartLine ride-hailing platform with support for both customers and captains.

## ✨ Features

### **Customer Features:**
- ✅ Book rides (pickup, destination, vehicle type selection)
- ✅ Check trip status and track active rides
- ✅ Cancel trips
- ✅ Contact driver
- ✅ View trip history
- ✅ Multi-language support (Arabic, English, Arabizi)
- ✅ Language consistency enforcement
- ✅ Content moderation

### **Captain Features:**
- ✅ Registration status check
- ✅ Support for all registration statuses:
  - Under Review
  - Documents Missing
  - Approved
  - Rejected
  - Background Check
  - System Delay
- ✅ Multi-language support (Arabic, English, Arabizi)
- ⚠️ **Note:** Operational features (earnings, trips) must use the Captain app

---

## 🚀 Quick Start

### 1. Install Dependencies
```bash
npm install
```

### 2. Configure Environment
Create a `.env` file:
```env
# Database
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=your_password
DB_NAME=merged2

# API Keys
GROQ_API_KEY=your_groq_api_key

# Server
PORT=3000
NODE_ENV=production

# Feature Flags (optional)
FF_LANGUAGE_ENFORCEMENT=true
FF_HYBRID_CLASSIFIER=true
FF_CAPTAIN_V2=true
```

### 3. Start Server
```bash
npm start
```

### 4. Test
```bash
node test_chatbot.js
```

---

## 📡 API Endpoints

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
  "language": {
    "primary": "ar",
    "isArabizi": false,
    "rtl": true
  },
  "userType": "customer",
  "confidence": 0.95
}
```

### **POST /submit-location**
Submit location coordinates for trip booking.

### **GET /health**
Health check endpoint.

### **GET /admin/stats**
Admin statistics (requires authentication).

---

## 🏗️ Architecture

### **Flow Diagram:**
```
POST /chat
  ↓
1. Rate Limiting
  ↓
2. Input Validation
  ↓
3. Language Detection
  ↓
4. Content Moderation
  ↓
5. User Type Detection (Customer/Captain)
  ↓
6. Process Conversation
   ├─ Customer → Booking Flow
   └─ Captain → Registration Status
  ↓
7. Generate Response
  ↓
8. Return JSON
```

### **Key Components:**
- **LanguageManager** - Handles language detection and consistency
- **IntentClassifier** - Hybrid intent classification (L1: Regex, L2: NLP, L3: LLM)
- **StateGuard** - Conversation state versioning and recovery
- **CaptainRegistrationBot** - Captain registration status handler
- **Moderation** - Content filtering and profanity detection

---

## 🔧 Configuration

### **Feature Flags:**
Control feature rollout via environment variables:

- `FF_LANGUAGE_ENFORCEMENT` - Enable strict language enforcement
- `FF_HYBRID_CLASSIFIER` - Enable hybrid intent classification
- `FF_CAPTAIN_V2` - Enable new captain flow
- `FF_ML_MODERATION` - Enable ML-based moderation (Phase 1: logging only)

### **Database Schema:**
Required tables:
- `users` - User accounts
- `drivers` - Captain/driver information
- `trip_requests` - Trip bookings
- `ai_chat_history` - Chat history
- `ai_conversation_state` - Conversation state
- `ai_user_preferences` - User preferences

---

## 🧪 Testing

### **Run Test Suite:**
```bash
node test_chatbot.js
```

### **Test Cases:**
- Customer greeting (Arabic & English)
- Book trip intent
- Captain registration status check
- Language switching
- Error handling

---

## 📝 Recent Fixes

See `FIXES_APPLIED.md` for detailed information about recent critical fixes:
- Fixed captain verification logic
- Added comprehensive error handling
- Improved response validation
- Fixed database query issues

---

## 🛠️ Development

### **Project Structure:**
```
├── chat.js                    # Main application
├── classifier.js              # Intent classifier
├── actions.js                 # Flutter action definitions
├── stateGuard.js              # State versioning
├── utils/
│   ├── language.js           # Language manager
│   ├── captainRegistrationBot.js  # Captain flow
│   ├── captainVerification.js     # Captain access control
│   ├── moderation.js          # Content moderation
│   ├── featureFlags.js        # Feature flag system
│   └── ...
└── test_chatbot.js            # Test suite
```

---

## 📞 Support

For issues or questions:
1. Check `FIXES_APPLIED.md` for known issues
2. Review server logs
3. Test with `test_chatbot.js`
4. Verify database connection and environment variables

---

## 📄 License

Proprietary - SmartLine IT

---

**Version:** 3.2  
**Status:** ✅ Production Ready  
**Last Updated:** $(date)
