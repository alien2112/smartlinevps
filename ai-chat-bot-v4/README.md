# 🚗 SmartLine AI Chatbot V3

Production-ready AI-powered customer service chatbot with Flutter action-based responses.

## ✨ Features

### Production Infrastructure
- **Rate Limiting**: 10/min (prod), 30/min (dev) per user
- **Input Validation**: express-validator with 500 char limit
- **Structured Logging**: Winston with JSON format
- **Admin Authentication**: API key protected endpoints
- **Moderation**: Profanity detection (EN/AR/Arabizi)
- **Memory Management**: TTL-based cleanup, 50K entry limits
- **Database Resilience**: Auto-reconnect with exponential backoff
- **Graceful Shutdown**: SIGTERM/SIGINT handlers

### Flutter Integration
- **25 Action Types**: Complete action-based response system
- **Trip Booking Flow**: Full state machine with vehicle categories
- **Map Integration**: Location submission endpoint
- **Quick Replies**: UI hints and suggestions
- **Safety/Emergency**: Automatic SOS handling

### Intelligence
- **User Type Detection**: Auto-detect Captain vs Customer
- **Language Support**: English, Arabic (Egyptian), Arabizi
- **LLM Fallback**: Groq Llama 3.3 70B for unknown queries
- **Context-Aware**: Maintains conversation history

## 📦 Installation

```bash
cd ai-chat-bot-v3
npm install
cp .env.example .env
# Edit .env with your configuration
npm start
```

## ⚙️ Configuration

```env
GROQ_API_KEY=your_groq_api_key
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=your_password
DB_NAME=merged2
DB_POOL_SIZE=20
PORT=3000
NODE_ENV=development
ADMIN_API_KEY=your_admin_api_key
LOG_LEVEL=info
```

## 🔌 API Endpoints

### Main Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/chat` | Main chat endpoint with actions |
| `POST` | `/submit-location` | Submit location from map picker |
| `GET` | `/action-types` | Get all Flutter action types |
| `GET` | `/health` | Health check with stats |

### Admin Endpoints (Requires API Key)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/admin/clear-memory` | Clear user's chat history |
| `POST` | `/admin/reset-state` | Reset conversation state |
| `GET` | `/admin/user-state/:id` | Get user's state |
| `GET` | `/admin/stats` | System statistics |
| `POST` | `/admin/set-user-type` | Set user type manually |

## 📤 Response Format

```json
{
  "message": "🚗 من أي مكان تريد أن تبدأ الرحلة؟",
  "action": "request_pickup_location",
  "data": { "map_type": "pickup" },
  "quick_replies": [],
  "ui_hint": "typing_delay",
  "confidence": 0.85,
  "handoff": false,
  "language": { "primary": "ar" },
  "userType": "customer",
  "model": "Llama 3.3 70B"
}
```

## 🎬 Action Types

### Booking Flow
- `request_pickup_location` - Open map for pickup
- `request_destination` - Open map for destination
- `show_ride_options` - Show vehicle categories
- `show_fare_estimate` - Show price estimate
- `confirm_booking` - Create trip

### Trip Tracking
- `show_trip_tracking` - Navigate to tracking
- `show_driver_info` - Show driver details

### Trip Actions
- `cancel_trip` - Cancel trip
- `confirm_cancel_trip` - Confirm cancellation
- `contact_driver` - Call/message driver

### Safety
- `trigger_emergency` - Trigger SOS
- `share_live_location` - Share location

### Support
- `connect_support` - Human handoff
- `call_support` - Call support line

## 📁 Project Structure

```
ai-chat-bot-v3/
├── chat.js              # Main server (merged)
├── actions.js           # Flutter action definitions
├── package.json         # Dependencies
├── .env.example         # Environment template
├── utils/
│   ├── auth.js          # Admin authentication
│   ├── cache.js         # Response caching
│   ├── logger.js        # Winston logging
│   ├── moderation.js    # Profanity detection
│   └── escalationMessages.js
└── public/
    └── index.html       # Web demo
```

## 🔒 Security

- Rate limiting per user
- Input validation and sanitization
- Admin API key authentication
- Profanity blocking with escalation
- Language validation
- No sensitive data in logs

## 📄 License

MIT
