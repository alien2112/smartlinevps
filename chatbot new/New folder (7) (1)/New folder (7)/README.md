# 🚗 Ride-Hailing AI Customer Support Backend

A production-ready Node.js backend for AI-powered customer support in a ride-hailing application. The system provides intelligent, bilingual support (English and Arabic) using Groq's Llama 3.3 70B model, with comprehensive user type detection (Customer/Captain), contextual conversation management, and robust error handling.

---

## 📋 Table of Contents

- [Overview](#overview)
- [Architecture](#architecture)
- [Key Features](#key-features)
- [Technology Stack](#technology-stack)
- [Installation & Setup](#installation--setup)
- [Configuration](#configuration)
- [API Documentation](#api-documentation)
- [Database Schema](#database-schema)
- [Security Features](#security-features)
- [Production Features](#production-features)
- [Project Structure](#project-structure)
- [Testing](#testing)
- [Deployment](#deployment)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)

---

## 🎯 Overview

This is a sophisticated customer support chatbot backend designed for ride-hailing services. It intelligently handles conversations with both **Customers** (riders) and **Captains** (drivers), automatically detecting user roles and providing contextually appropriate responses.

### Core Capabilities

- ✅ **Bilingual Support**: English and Arabic (Egyptian dialect) with automatic language detection
- ✅ **User Type Detection**: Automatic identification of Customers vs Captains using keyword analysis
- ✅ **Context-Aware Conversations**: Maintains conversation history and ride context
- ✅ **Production-Ready**: Memory management, error handling, graceful shutdown, database resilience
- ✅ **Rate Limiting**: Configurable rate limits per user
- ✅ **Moderation System**: Profanity detection and language validation
- ✅ **Admin Tools**: Comprehensive admin endpoints for testing and management

---

## 🏗️ Architecture

### System Flow

```
Client Request
    ↓
Rate Limiting (10-30 msg/min per user)
    ↓
Input Validation (express-validator)
    ↓
Language Gate (PRE-LLM - blocks unsupported languages)
    ↓
Moderation Gate (PRE-LLM - profanity detection)
    ↓
Repeated Message Detection
    ↓
Language Locking (session-based)
    ↓
User Type Detection (Customer/Captain)
    ↓
Context Builder (chat history + active ride + user type)
    ↓
Response Cache Check
    ↓
LLM Call (Groq - Llama 3.3 70B)
    ↓
Post-processing (safety detection, handoff detection)
    ↓
Database Save (chat history)
    ↓
JSON Response
```

### Key Components

1. **Main Server** (`chat.js`): Express.js REST API server
2. **Database Layer**: MySQL with connection pooling and automatic reconnection
3. **LLM Integration**: Groq API (Llama 3.3 70B)
4. **Utilities**: Moderation, caching, logging, authentication
5. **Frontend Demo**: Beautiful web interface (`public/index.html`)

---

## ✨ Key Features

### 1. **Intelligent User Type Detection**

The system automatically detects whether a user is a **Customer** (rider) or **Captain** (driver) using:

- **Direct Declarations**: "I am a driver", "انا كابتن"
- **Strong Indicators**: Keywords like "earnings", "acceptance rate", "my vehicle" (Captain) or "book a ride", "driver is late" (Customer)
- **Weak Indicators**: Scoring system for ambiguous cases
- **Memory**: User types are stored in memory and persist across sessions

### 2. **Bilingual Support**

- **Languages**: English, Arabic (Egyptian dialect), Arabizi (Arabic written in Latin script)
- **Auto-Detection**: Detects language from user input
- **Language Locking**: Locks to first detected language per session
- **Smart Handling**: Handles code-switching and mixed language inputs

### 3. **Context-Aware Conversations**

- **Chat History**: Maintains last 10 messages per user
- **Active Ride Context**: Automatically includes active ride information in prompts
- **User Type Context**: Provides user role context to LLM for appropriate responses
- **Topic Detection**: Identifies conversation topics for better responses

### 4. **Production-Ready Features**

- ✅ **Memory Management**: Automatic cleanup of stale data (24-hour TTL)
- ✅ **Database Resilience**: Connection pooling, auto-reconnection with exponential backoff
- ✅ **Graceful Shutdown**: Clean resource cleanup on SIGTERM/SIGINT
- ✅ **Error Handling**: Global error handlers, structured logging
- ✅ **Rate Limiting**: Per-user rate limits (10/min in prod, 30/min in dev)
- ✅ **Response Caching**: Caches common queries for performance

### 5. **Safety & Moderation**

- **Profanity Detection**: Multi-language profanity filtering
- **Language Validation**: Blocks unsupported languages
- **Repeated Message Protection**: Prevents spam
- **Safety Keywords**: Detects emergency/safety-related messages
- **Handoff Detection**: Automatic escalation to human support when needed

---

## 🛠️ Technology Stack

| Component | Technology |
|-----------|-----------|
| **Runtime** | Node.js |
| **Framework** | Express.js |
| **Database** | MySQL 8.0+ |
| **LLM Provider** | Groq API (Llama 3.3 70B) |
| **Logging** | Winston |
| **Caching** | node-cache |
| **Validation** | express-validator |
| **Rate Limiting** | express-rate-limit |
| **HTTP Logging** | Morgan |

### Dependencies

```json
{
  "express": "^4.18.2",
  "mysql2": "^3.6.5",
  "dotenv": "^16.3.1",
  "winston": "^3.19.0",
  "node-cache": "^5.1.2",
  "express-rate-limit": "^8.2.1",
  "express-validator": "^7.3.1",
  "cors": "^2.8.5",
  "body-parser": "^1.20.2",
  "morgan": "^1.10.1"
}
```

---

## 📦 Installation & Setup

### Prerequisites

- **Node.js**: v16+ (recommended: v18+)
- **MySQL**: 8.0+
- **Groq API Key**: Get one at [console.groq.com](https://console.groq.com)

### Step 1: Clone Repository

```bash
git clone <repository-url>
cd "New folder (7)"
```

### Step 2: Install Dependencies

```bash
npm install
```

### Step 3: Configure Environment

Create a `.env` file in the root directory:

```env
# Groq API Configuration
GROQ_API_KEY=your_groq_api_key_here

# Database Configuration
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=1234
DB_NAME=my_database
DB_POOL_SIZE=20

# Server Configuration
PORT=3000
NODE_ENV=development

# Admin API Key (REQUIRED in production)
ADMIN_API_KEY=your_secure_admin_api_key_here

# Logging
LOG_LEVEL=info
```

### Step 4: Setup MySQL Database

```sql
CREATE DATABASE IF NOT EXISTS my_database;
```

The application will automatically create all required tables on startup.

### Step 5: Start Server

```bash
npm start
```

You should see:
```
╔════════════════════════════════════════════╗
║  🚗 RIDE SUPPORT - Customer Service Bot    ║
║  ─────────────────────────────────────────║
║  Server: http://localhost:3000             ║
║  Model: Llama 3.3 70B (Groq)              ║
║  Languages: English + Arabic (Egyptian)   ║
╚════════════════════════════════════════════╝
```

### Step 6: Access Web Demo

Open your browser to: `http://localhost:3000`

---

## ⚙️ Configuration

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `GROQ_API_KEY` | *required* | Groq API key for LLM access |
| `DB_HOST` | `localhost` | MySQL host |
| `DB_USER` | `root` | MySQL username |
| `DB_PASSWORD` | `1234` | MySQL password |
| `DB_NAME` | `my_database` | Database name |
| `DB_POOL_SIZE` | `20` | Connection pool size |
| `PORT` | `3000` | Server port |
| `NODE_ENV` | `development` | Environment mode |
| `ADMIN_API_KEY` | *none* | Admin API key (required in production) |
| `LOG_LEVEL` | `info` | Logging level (error, warn, info, debug) |

### Rate Limiting

- **Development**: 30 messages/minute per user
- **Production**: 10 messages/minute per user
- Configurable in code: `chatRateLimiter` configuration

### Memory Management

- **User Types**: Max 50,000 entries, 24-hour TTL, auto-cleanup every 30 minutes
- **Last Messages**: Max 50,000 entries, 5-minute TTL, auto-cleanup every 10 minutes
- **Cache**: Max 1,000 keys, 5-minute TTL

---

## 📡 API Documentation

### Base URL

```
http://localhost:3000
```

### Main Chat Endpoint

#### POST `/chat`

Main endpoint for chat conversations.

**Request:**

```json
{
  "user_id": "u_123",
  "message": "Where is my driver?",
  "language": "en"
}
```

**Response:**

```json
{
  "reply": "Your driver Ahmed is on the way. Estimated arrival: 5 minutes.",
  "confidence": 0.85,
  "handoff": false,
  "language": {
    "primary": "en",
    "confidence": 0.95,
    "arabicRatio": 0.0,
    "latinRatio": 1.0,
    "hasArabizi": false
  },
  "model": "Llama 3.3 70B",
  "userType": "customer"
}
```

**Error Responses:**

```json
{
  "reply": "Too many requests. Please wait a minute.",
  "error": "RATE_LIMIT_EXCEEDED",
  "retryAfter": 60
}
```

```json
{
  "reply": "Language not supported. Please use English or Arabic.",
  "error": "LANGUAGE_NOT_SUPPORTED",
  "blocked": true
}
```

**Rate Limit**: 30 msg/min (dev), 10 msg/min (prod)

---

### Admin Endpoints

All admin endpoints require authentication via `X-API-Key` header or `Authorization: Bearer <key>`.

#### POST `/admin/create-ride`

Create a test ride.

```bash
curl -X POST http://localhost:3000/admin/create-ride \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your_admin_api_key_here" \
  -d '{
    "ride_id": "r_456",
    "user_id": "u_123",
    "driver_name": "Ahmed",
    "pickup": "Mall",
    "destination": "Airport",
    "status": "ongoing"
  }'
```

#### POST `/admin/update-ride`

Update ride status.

```bash
curl -X POST http://localhost:3000/admin/update-ride \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your_admin_api_key_here" \
  -d '{
    "ride_id": "r_456",
    "status": "completed"
  }'
```

#### POST `/admin/clear-memory`

Clear user's chat history and user type.

```bash
curl -X POST http://localhost:3000/admin/clear-memory \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your_admin_api_key_here" \
  -d '{
    "user_id": "u_123"
  }'
```

#### POST `/admin/reset-user`

Reset all data for a specific user.

```bash
curl -X POST http://localhost:3000/admin/reset-user \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your_admin_api_key_here" \
  -d '{
    "user_id": "u_123"
  }'
```

#### POST `/admin/reset-all`

**⚠️ DANGER**: Resets ALL data (use with caution).

```bash
curl -X POST http://localhost:3000/admin/reset-all \
  -H "X-API-Key: your_admin_api_key_here"
```

#### POST `/admin/set-user-type`

Manually set user type.

```bash
curl -X POST http://localhost:3000/admin/set-user-type \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your_admin_api_key_here" \
  -d '{
    "user_id": "u_123",
    "type": "captain"
  }'
```

#### POST `/admin/clear-user-type`

Clear user type from memory.

```bash
curl -X POST http://localhost:3000/admin/clear-user-type \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your_admin_api_key_here" \
  -d '{
    "user_id": "u_123"
  }'
```

#### GET `/admin/user-info/:user_id`

Get user information.

```bash
curl http://localhost:3000/admin/user-info/u_123 \
  -H "X-API-Key: your_admin_api_key_here"
```

**Response:**

```json
{
  "success": true,
  "user_id": "u_123",
  "user_type": "customer",
  "preferred_language": "en",
  "violations": 0,
  "user_data": { ... }
}
```

#### GET `/admin/stats`

Get system statistics.

```bash
curl http://localhost:3000/admin/stats \
  -H "X-API-Key: your_admin_api_key_here"
```

**Response:**

```json
{
  "success": true,
  "stats": {
    "usersInDatabase": 150,
    "ridesInDatabase": 45,
    "chatHistoryEntries": 1234,
    "userTypesInMemory": 120,
    "lastMessagesInMemory": 85,
    "memory": {
      "heapUsed": "45MB",
      "heapTotal": "60MB",
      "rss": "120MB"
    },
    "uptime": "3600s"
  }
}
```

---

### Health Check

#### GET `/health`

Check server and database health.

```bash
curl http://localhost:3000/health
```

**Response:**

```json
{
  "status": "ok",
  "database": "connected",
  "memory": {
    "heapUsed": "45MB",
    "heapTotal": "60MB",
    "rss": "120MB"
  },
  "lastMessagesMapSize": 85,
  "uptime": "3600s"
}
```

---

## 🗄️ Database Schema

### Tables

#### `users`

Stores user information.

```sql
CREATE TABLE users (
    id VARCHAR(50) PRIMARY KEY,
    preferred_language VARCHAR(10) NULL DEFAULT NULL,
    user_role VARCHAR(20) NULL DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

**Columns:**
- `id`: User ID (primary key)
- `preferred_language`: Locked language preference (en, ar, arabizi)
- `user_role`: User role (captain, customer) - optional, mainly for future use
- `created_at`: Account creation timestamp

#### `rides`

Stores active and completed rides.

```sql
CREATE TABLE rides (
    id VARCHAR(50) PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL,
    status VARCHAR(20) DEFAULT 'ongoing',
    driver_name VARCHAR(100),
    pickup VARCHAR(255),
    destination VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id)
);
```

**Columns:**
- `id`: Ride ID (primary key)
- `user_id`: Customer user ID
- `status`: Ride status (ongoing, completed, cancelled)
- `driver_name`: Driver/captain name
- `pickup`: Pickup location
- `destination`: Destination location
- `created_at`: Ride creation timestamp

#### `chat_history`

Stores conversation history.

```sql
CREATE TABLE chat_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL,
    role VARCHAR(20) NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_user_created (user_id, created_at)
);
```

**Columns:**
- `id`: Auto-increment primary key
- `user_id`: User ID
- `role`: Message role (user, assistant)
- `content`: Message content
- `created_at`: Message timestamp

**Management:**
- Only last 10 messages are kept per user (auto-cleanup)
- Used for LLM context (last 4 messages sent to LLM)

#### `user_violations`

Tracks user violations (future use).

```sql
CREATE TABLE user_violations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL,
    violation_type VARCHAR(20) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id)
);
```

---

## 🛡️ Security Features

| Feature | Implementation | Status |
|---------|---------------|--------|
| **Rate Limiting** | 30 msg/min (dev), 10 msg/min (prod) per user_id | ✅ |
| **Input Validation** | express-validator (max 500 chars, required fields) | ✅ |
| **Input Sanitization** | XSS protection, parameterized queries | ✅ |
| **Repeated Messages** | Blocked within 30 seconds | ✅ |
| **Language Locking** | Single language per user session | ✅ |
| **Profanity Detection** | Multi-language profanity filtering | ✅ |
| **Admin Authentication** | API key required for all /admin/* endpoints | ✅ |
| **SQL Injection Protection** | Parameterized queries (mysql2) | ✅ |
| **CORS** | Configurable (currently open in dev) | ✅ |
| **Error Handling** | No sensitive data in error responses | ✅ |

### Admin Authentication

All `/admin/*` endpoints require authentication:

- **Header**: `X-API-Key: <your_admin_api_key>`
- **Alternative**: `Authorization: Bearer <your_admin_api_key>`
- **Warning**: If `ADMIN_API_KEY` is not set, endpoints are unprotected (dev mode only)

---

## 🚀 Production Features

### Memory Management

- **Automatic Cleanup**: Stale data cleaned every 10-30 minutes
- **Size Limits**: Max 50,000 entries per in-memory map
- **TTL-based Expiration**: 24 hours for user types, 5 minutes for last messages
- **Emergency Cleanup**: Triggers when maps exceed 90% capacity

### Database Resilience

- **Connection Pooling**: Configurable pool size (default: 20)
- **Auto-Reconnection**: Exponential backoff on connection loss
- **Error Handling**: Pool error listeners with automatic recovery
- **Health Checks**: Database health check endpoint

### Error Handling

- **Global Handlers**: `unhandledRejection` and `uncaughtException` handlers
- **Graceful Shutdown**: Clean resource cleanup on SIGTERM/SIGINT
- **Structured Logging**: Winston logger with JSON format
- **Error Context**: Detailed error context without exposing sensitive data

### Performance

- **Response Caching**: Common queries cached for 5 minutes
- **Optimized Queries**: Indexed database queries
- **Context Limits**: Only last 4 messages sent to LLM
- **Connection Pooling**: Efficient database connection management

---

## 📁 Project Structure

```
New folder (7)/
├── chat.js                    # Main server entry point (Express API)
├── bot_engine.js              # State machine bot engine (NOT USED - legacy)
├── templates_sa.js            # Saudi Arabic templates (NOT USED - legacy)
├── test_bot.js                # Test suite for bot_engine (NOT USED)
├── package.json               # Dependencies and scripts
├── package-lock.json          # Locked dependencies
├── README.md                  # This file
├── .env                       # Environment variables (create this)
│
├── public/                    # Static files
│   └── index.html             # Beautiful web demo interface
│
├── utils/                     # Utility modules
│   ├── auth.js                # Admin authentication middleware
│   ├── cache.js               # Response caching utility
│   ├── escalationMessages.js  # Escalation and language guard messages
│   ├── logger.js              # Winston logger configuration
│   └── moderation.js          # Language detection and profanity filtering
│
└── [test files]               # Various test files (json, txt, etc.)
```

### Key Files

- **`chat.js`**: Main server file (2159 lines) - contains all API endpoints, LLM integration, database logic
- **`utils/moderation.js`**: Language detection and profanity filtering (1042 lines)
- **`utils/logger.js`**: Structured logging with Winston
- **`utils/cache.js`**: Response caching with node-cache
- **`utils/auth.js`**: Admin API key authentication
- **`public/index.html`**: Beautiful web demo interface

---

## 🧪 Testing

### Manual Testing

#### 1. Test Chat Endpoint

```bash
curl -X POST http://localhost:3000/chat \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "test_user_1",
    "message": "Hello, I need help"
  }'
```

#### 2. Test Arabic Message

```bash
curl -X POST http://localhost:3000/chat \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "test_user_1",
    "message": "مرحبا، محتاج مساعدة"
  }'
```

#### 3. Test User Type Detection (Captain)

```bash
curl -X POST http://localhost:3000/chat \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "captain_1",
    "message": "I am a driver, I want to check my earnings"
  }'
```

#### 4. Test User Type Detection (Customer)

```bash
curl -X POST http://localhost:3000/chat \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "customer_1",
    "message": "I want to book a ride"
  }'
```

#### 5. Test Health Check

```bash
curl http://localhost:3000/health
```

### PowerShell Test Script

A PowerShell test script is available: `test_scenarios.ps1`

```powershell
.\test_scenarios.ps1
```

### Web Demo

Access the web demo at `http://localhost:3000` for interactive testing.

---

## 🚢 Deployment

### Production Checklist

- [ ] Set `NODE_ENV=production`
- [ ] Set strong `ADMIN_API_KEY`
- [ ] Configure `DB_POOL_SIZE` appropriately
- [ ] Set `LOG_LEVEL=info` or `warn`
- [ ] Configure CORS appropriately
- [ ] Set up process manager (PM2, systemd, etc.)
- [ ] Configure database backups
- [ ] Set up monitoring and alerts
- [ ] Configure reverse proxy (nginx, etc.)
- [ ] Enable HTTPS/SSL

### Using PM2

```bash
# Install PM2
npm install -g pm2

# Start application
pm2 start chat.js --name ride-support

# Save PM2 configuration
pm2 save

# Setup PM2 startup script
pm2 startup
```

### Using Docker (Example)

```dockerfile
FROM node:18-alpine
WORKDIR /app
COPY package*.json ./
RUN npm ci --only=production
COPY . .
EXPOSE 3000
CMD ["node", "chat.js"]
```

### Environment Variables (Production)

```env
NODE_ENV=production
GROQ_API_KEY=your_production_key
DB_HOST=your_db_host
DB_USER=your_db_user
DB_PASSWORD=your_secure_password
DB_NAME=your_db_name
DB_POOL_SIZE=50
PORT=3000
ADMIN_API_KEY=your_very_secure_admin_key
LOG_LEVEL=info
```

---

## 🔧 Troubleshooting

### Common Issues

#### 1. Database Connection Failed

**Error**: `Database initialization failed`

**Solutions**:
- Check MySQL is running: `mysql -u root -p`
- Verify credentials in `.env`
- Check database exists: `CREATE DATABASE my_database;`
- Check firewall/network connectivity

#### 2. Groq API Errors

**Error**: `Groq API error: 401` or `RATE_LIMIT_EXCEEDED`

**Solutions**:
- Verify `GROQ_API_KEY` is correct
- Check Groq API quota/rate limits
- Wait for rate limit to reset (30 RPM limit)

#### 3. Rate Limit Errors

**Error**: `Too many requests`

**Solutions**:
- Wait 60 seconds before retrying
- Check if you're hitting per-user limit (10/min in prod)
- Consider increasing limit in development mode

#### 4. Language Detection Issues

**Error**: Language detected as "unknown"

**Solutions**:
- Ensure message contains recognizable English or Arabic text
- Check for mixed languages or unsupported characters
- Language detection requires at least some recognizable text

#### 5. Memory Issues

**Symptoms**: Slow responses, high memory usage

**Solutions**:
- Check memory cleanup is running (check logs)
- Reduce `MAX_USER_TYPES` or `MAX_LAST_MESSAGES` if needed
- Increase server memory
- Monitor with `/admin/stats` endpoint

### Debug Mode

Set `LOG_LEVEL=debug` in `.env` for detailed logging:

```env
LOG_LEVEL=debug
```

### Logs

Logs are output to console. In production, configure Winston to write to files:

```javascript
// In utils/logger.js, add file transports:
new winston.transports.File({ filename: 'error.log', level: 'error' }),
new winston.transports.File({ filename: 'combined.log' })
```

---

## 📊 Monitoring

### Health Check Endpoint

Monitor server health:

```bash
curl http://localhost:3000/health
```

### Statistics Endpoint

Monitor system statistics (requires admin auth):

```bash
curl http://localhost:3000/admin/stats \
  -H "X-API-Key: your_admin_api_key"
```

### Key Metrics to Monitor

- **Memory Usage**: Heap, RSS, in-memory map sizes
- **Database Connections**: Pool status, connection errors
- **Response Times**: Average response time per request
- **Error Rates**: Error count by type
- **Rate Limits**: Number of rate-limited requests
- **LLM API Status**: Groq API errors and rate limits

---

## 🔄 API Versioning

Currently, two endpoints exist:

- **`POST /chat`**: Legacy endpoint (returns legacy format)
- **`POST /chat/v2`**: V2 endpoint (returns structured JSON format)

The main `/chat` endpoint uses the new `buildMessages` function internally but returns legacy-compatible responses for backward compatibility.

---

## 📝 Notes

### Unused Files

The following files exist but are **NOT USED** in the current implementation:

- **`bot_engine.js`**: State machine bot engine (legacy, not integrated)
- **`templates_sa.js`**: Saudi Arabic templates (legacy, not used)
- **`test_bot.js`**: Test suite for bot_engine (legacy)

These can be safely removed if not needed.

### Language Support

- **English**: Full support
- **Arabic (Egyptian)**: Full support
- **Arabizi**: Detected and treated as Arabic
- **Other Languages**: Blocked by language gate

### User Type Detection

User types are stored **in-memory only** (not in database). They persist for 24 hours and are cleaned automatically. Use `/admin/set-user-type` to manually set types if needed.

---

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

---

## 📄 License

[Specify your license here]

---

## 👥 Support

For issues, questions, or contributions, please [open an issue](link-to-issues) or contact the development team.

---

## 🙏 Acknowledgments

- **Groq** for the Llama 3.3 70B API
- **Express.js** community
- All contributors and testers

---

**Last Updated**: 2024

**Version**: 1.0.0

---
