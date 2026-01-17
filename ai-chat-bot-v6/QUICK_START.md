# ⚡ Quick Start Guide

## The Problem
The test script is failing because **the server isn't running**. You need to start the server first!

## Solution (3 Steps)

### Step 1: Install Dependencies ✅ (Already Done)
```bash
npm install
```

### Step 2: Start the Server
Open a **NEW terminal window** and run:
```bash
npm start
```

Wait until you see:
```
╔════════════════════════════════════════════════════════════╗
║   🚗 SMARTLINE AI CHATBOT V3.2                            ║
║   Server:    http://localhost:3000                         ║
╚════════════════════════════════════════════════════════════╝
```

**Keep this terminal open!** The server must stay running.

### Step 3: Run Tests (in the original terminal)
```bash
node test_chatbot.js
```

---

## Alternative: Test Without Server

If you just want to verify the code works, you can test individual components:

```bash
# Test syntax
node -c chat.js

# Test classifier
node -e "const c = require('./classifier'); console.log('Classifier OK');"

# Test language manager
node -e "const L = require('./utils/language'); console.log('Language Manager OK');"
```

---

## Common Issues

### ❌ "Cannot find module 'compression'"
**Fixed!** Already installed. If you see this again, run:
```bash
npm install compression
```

### ❌ "ECONNREFUSED ::1:3000"
**The server isn't running!** Start it with `npm start` first.

### ❌ "Database connection failed"
Check your `.env` file has correct database settings.

---

## Full Workflow

```bash
# Terminal 1: Start Server
npm start

# Terminal 2: Run Tests
node test_chatbot.js

# Terminal 2: Or test manually with curl
curl -X POST http://localhost:3000/chat \
  -H "Content-Type: application/json" \
  -d "{\"user_id\":\"test-123\",\"message\":\"مرحبا\"}"
```

---

**Remember:** The server must be running before you can test it! 🚀

