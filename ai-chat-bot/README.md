# 🚗 Ride-Hailing AI Customer Support Backend

A production-ready Node.js backend for AI-powered customer support in a ride-hailing application.

## 🏗️ Architecture

```
Client (Web / Postman / Mobile)
        ↓
REST API (/chat)
        ↓
Validation + Rate Limit
        ↓
Context Builder (user + last ride)
        ↓
Prompt Builder
        ↓
LLM (Groq - Llama 3.1)
        ↓
Post-processing
        ↓
JSON Response
```

## 🚀 Quick Start

### 1. Install Dependencies
```bash
npm install
```

### 2. Configure Environment
Create a `.env` file (already provided):
```env
GROQ_API_KEY=your_groq_api_key
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=1234
DB_NAME=my_database
PORT=3000
```

### 3. Setup MySQL Database
Make sure MySQL is running and create the database:
```sql
CREATE DATABASE IF NOT EXISTS my_database;
```

### 4. Start the Server
```bash
npm start
```

## 📡 API Endpoints

### Main Chat Endpoint

**POST /chat**

Request:
```json
{
  "user_id": "u_123",
  "message": "Driver is late",
  "language": "en"
}
```

Response:
```json
{
  "reply": "Sorry for the delay. Your driver is on the way.",
  "confidence": 0.83,
  "handoff": false
}
```

### Admin Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/admin/create-ride` | POST | Create a new ride |
| `/admin/update-ride` | POST | Update ride status |
| `/admin/clear-memory` | POST | Clear user chat memory |
| `/admin/unblock` | POST | Unblock a user |
| `/admin/user/:id` | GET | Get user stats |
| `/health` | GET | Health check |

## 🧪 Testing Examples

### Create a Test Ride
```bash
curl -X POST http://localhost:3000/admin/create-ride \
  -H "Content-Type: application/json" \
  -d '{
    "ride_id": "r_456",
    "user_id": "u_123",
    "driver_name": "Ahmed",
    "pickup": "Mall",
    "destination": "Airport",
    "status": "ongoing"
  }'
```

### Send a Chat Message
```bash
curl -X POST http://localhost:3000/chat \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "u_123",
    "message": "Where is my driver?",
    "language": "en"
  }'
```

### Arabic Message
```bash
curl -X POST http://localhost:3000/chat \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "u_123",
    "message": "السائق تأخر",
    "language": "ar"
  }'
```

## 🛡️ Security Features

| Feature | Limit |
|---------|-------|
| Rate Limit | 5 messages/minute |
| Message Length | 300 characters max |
| Repeated Messages | Blocked |
| Auto-Block | After 3 violations |

## 💰 Cost Control

- **Token Limit**: 150 max tokens per response
- **Cached Responses**: Common queries (cancel, help, hello, thanks)
- **Short Replies**: System prompt enforces 3 sentences max
- **Memory Limit**: Only last 6 messages stored

## 📊 Database Tables

- `users` - User accounts and block status
- `rides` - Ride information
- `chat_memory` - Short-term conversation memory
- `rate_limits` - Rate limiting records
- `violations` - User violation tracking

## 🔧 For Mobile Developers

Simply consume the `/chat` endpoint:

```javascript
const response = await fetch('http://your-server/chat', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    user_id: 'u_123',
    message: 'Driver is late',
    language: 'en'
  })
});

const data = await response.json();
// { reply: "...", confidence: 0.83, handoff: false }
```

## 📝 License

MIT

