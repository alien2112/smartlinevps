# 🚀 How to Start the Chatbot Server

## Quick Start

### 1. Install All Dependencies
```bash
npm install
```

### 2. Configure Environment
Make sure you have a `.env` file with:
```env
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=your_password
DB_NAME=merged2
GROQ_API_KEY=your_groq_api_key
PORT=3000
```

### 3. Start the Server
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

### 4. Test the Server (in another terminal)
```bash
node test_chatbot.js
```

---

## Troubleshooting

### Error: "Cannot find module 'compression'"
**Solution:** Run `npm install` to install all dependencies.

### Error: "ECONNREFUSED"
**Solution:** Make sure the server is running on port 3000 before running tests.

### Error: Database connection failed
**Solution:** 
1. Check your `.env` file has correct database credentials
2. Make sure MySQL is running
3. Verify database name exists

### Error: "GROQ_API_KEY not set"
**Solution:** Add `GROQ_API_KEY=your_key` to your `.env` file.

---

## Testing

Once the server is running, you can test it:

1. **Using the test script:**
   ```bash
   node test_chatbot.js
   ```

2. **Using curl:**
   ```bash
   curl -X POST http://localhost:3000/chat \
     -H "Content-Type: application/json" \
     -d '{"user_id":"test-123","message":"مرحبا"}'
   ```

3. **Using Postman:**
   - POST to `http://localhost:3000/chat`
   - Body: `{"user_id":"test-123","message":"Hello"}`

---

## Health Check

Check if server is running:
```bash
curl http://localhost:3000/health
```

Expected response:
```json
{
  "status": "ok",
  "timestamp": "...",
  "version": "v3.2"
}
```

