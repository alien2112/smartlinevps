# 🚗 Ride Support AI Chatbot V2

AI-powered customer service chatbot with **action-based responses** for seamless Flutter integration.

## 🌟 Features

- **Action-Based Responses**: Each response includes an `action` that tells Flutter what to do
- **State Machine**: Tracks conversation state for multi-turn flows (booking, complaints, etc.)
- **Arabic & English**: Auto-detects language and responds accordingly
- **Safety Priority**: Detects safety keywords and triggers emergency flows
- **Human Handoff**: Seamlessly escalates to human support when needed
- **Location Integration**: Receives location data from Flutter map picker

## 📁 Files

| File | Description |
|------|-------------|
| `chat_v2.js` | Main server with action-based responses (V2) |
| `chat.js` | Original server (V1 - LLM only) |
| `actions.js` | Action type definitions and builders |
| `bot_engine.js` | State machine and intent detection |
| `templates_sa.js` | Arabic response templates |
| `FLUTTER_INTEGRATION.md` | Complete Flutter integration guide |
| `postman_collection.json` | Postman collection for testing |

## 🚀 Quick Start

### 1. Install Dependencies
```bash
npm install
```

### 2. Configure Environment
```bash
cp .env.example .env
# Edit .env with your database and API keys
```

### 3. Start Server
```bash
# V2 (with actions)
npm start

# Or V1 (LLM only)
npm run start:v1
```

## 🔌 API Endpoints

### Main Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/chat` | Send chat message, receive response with action |
| `POST` | `/submit-location` | Submit location from map picker |
| `GET` | `/action-types` | Get all available action types |
| `GET` | `/health` | Health check |

### Admin Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/admin/user-state/:user_id` | Get user's conversation state |
| `POST` | `/admin/reset-state` | Reset user's conversation state |
| `POST` | `/admin/clear-memory` | Clear user's chat history |

## 📤 Response Format

```json
{
  "message": "سوف أحجز رحلة لك. من أي مكان تريد أن تبدأ الرحلة؟",
  "action": "request_pickup_location",
  "data": {
    "map_type": "pickup"
  },
  "quick_replies": [],
  "ui_hint": "typing_delay",
  "confidence": 0.85,
  "handoff": false,
  "language": "ar"
}
```

## 🎬 Action Types

### Trip Booking
- `request_pickup_location` - Open map for pickup
- `request_destination` - Open map for destination
- `show_ride_options` - Show ride type selection
- `show_fare_estimate` - Show fare estimate
- `confirm_booking` - Create the trip

### Trip Tracking
- `show_trip_tracking` - Navigate to tracking screen
- `show_driver_info` - Show driver details

### Trip Actions
- `cancel_trip` - Cancel trip
- `confirm_cancel_trip` - Show cancel confirmation
- `contact_driver` - Call/message driver

### Safety
- `trigger_emergency` - Trigger SOS
- `share_live_location` - Share location

### Support
- `connect_support` - Connect to human agent
- `call_support` - Call support line

See `FLUTTER_INTEGRATION.md` for complete implementation details.

## 🔄 Conversation Flow Example

```
User: "أريد حجز رحلة"
↓
Backend: { message: "...", action: "request_pickup_location" }
↓
Flutter: Opens map picker
↓
User: Selects location
↓
Flutter: POST /submit-location { lat, lng, type: "pickup" }
↓
Backend: { message: "...", action: "request_destination" }
↓
...continues until trip is booked
```

## 🛡️ Safety Features

The chatbot automatically detects safety-related keywords:
- خطر (danger)
- تحرش (harassment)
- حادث (accident)
- طوارئ (emergency)

When detected, it immediately:
1. Asks if user is safe
2. Provides emergency numbers
3. Triggers handoff to human support
4. Sends `trigger_emergency` action to Flutter

## 📱 Flutter Integration

See `FLUTTER_INTEGRATION.md` for:
- Complete Dart code examples
- Service classes
- Action handler implementation
- UI components
- Best practices

## 🧪 Testing

Import `postman_collection.json` into Postman to test all endpoints.

## 📝 Environment Variables

```env
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=your_password
DB_NAME=smartline_new2
GROQ_API_KEY=your_groq_api_key
PORT=3000
```

## 📄 License

MIT
