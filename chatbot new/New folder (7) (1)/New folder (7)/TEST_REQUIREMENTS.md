# Test Requirements - V2 Implementation Verification

## Test Execution Required

The implementation is complete, but **tests must be run to verify the behavior matches requirements**.

---

## Test A: Customer Booking (Step-by-Step)

### Step 1: Initial Booking Request
**Request:**
```json
POST /chat/v2
{
  "user_id": "u_test_customer_1",
  "message": "book a trip"
}
```

**Expected /chat/v2 Response:**
- `role` = `"customer"` (or `"unknown"` if role not detected, then it should ask for role)
- `state` = `"booking_awaiting_pickup"`
- `actions` includes: `{ "type": "OPEN_MAPS", "payload": { "mode": "select_pickup" } }`
- `status` = `"need_info"` or `"in_progress"`
- `message` tells user to select pickup location (localized)

**Expected /chat (legacy) Response:**
- `reply` is present and matches the same meaning
- Includes all legacy keys: `reply`, `confidence`, `handoff`, `language`, `model`

### Step 2: After Pickup Selected
**Request:**
```json
POST /chat/v2
{
  "user_id": "u_test_customer_1",
  "message": "pickup location selected: 123 Main St"
}
```

**Expected:**
- `state` = `"booking_awaiting_destination"`
- `actions` includes: `{ "type": "OPEN_MAPS", "payload": { "mode": "select_destination" } }`

### Step 3: Timing Selection
**Request (after destination):**
```json
POST /chat/v2
{
  "user_id": "u_test_customer_1",
  "message": "destination selected: 456 Oak Ave"
}
```

**Expected:**
- `state` = `"booking_awaiting_time"`
- Asks for immediate vs scheduled timing
- If scheduled → asks for datetime

### Step 4: Submit Trip
**Request (after time selected):**
```json
POST /chat/v2
{
  "user_id": "u_test_customer_1",
  "message": "immediate"
}
```

**Expected (only when pickup + destination + time exist):**
- `SUBMIT_TRIP` action allowed (backend validation ensures state = `booking_ready_to_submit`)
- Response message **FORCED** to exactly:
  - English: `"Trip initialized successfully. Waiting for verification."`
  - Arabic: `"تم تهيئة الرحلة بنجاح. في انتظار التحقق."`
  - Arabizi: `"El trip اتعمل initialize بنجاح. Mostaneyeen el verification."`
- `state` = `"waiting_verification"`
- `status` = `"waiting_verification"`

---

## Test B: Captain Support Issue

**Request:**
```json
POST /chat/v2
{
  "user_id": "u_test_captain_1",
  "message": "انا كابتن التطبيق مش شغال"
}
```

**Expected:**
- `role` = `"captain"`
- `intent` = `"captain_app_issue"` (or similar captain-related intent)
- **NOT** booking states/actions (no `booking_awaiting_*`, no `OPEN_MAPS` for booking)
- Support flow, not booking flow

---

## Test C: Malformed LLM Output Fallback

**Simulation:** Need to simulate LLM returning non-JSON text (requires code change or API mock)

**Expected /chat/v2:**
```json
{
  "language": "en",
  "role": "unknown",
  "intent": "unknown",
  "state": "start",
  "message": "Specify role: Customer or Captain.",
  "required_inputs": [...],
  "actions": [],
  "status": "need_info",
  "error": {
    "code": "PARSE_ERROR",
    "detail": "Model output not valid JSON"
  }
}
```

**Expected /chat (legacy):**
```json
{
  "reply": "Specify role: Customer or Captain.",
  "confidence": 0.85,
  "handoff": false,
  "language": {...},
  "model": "Llama 3.3 70B"
}
```

---

## Production Notes

⚠️ **State Management Limitation:**
- State is currently **in-memory only**
- Server restart wipes state
- Multiple server instances won't share state
- **For production:** Consider persisting state in DB/Redis for multi-instance deployments

---

## How to Run Tests

1. **Start the server:**
   ```bash
   node chat.js
   ```

2. **Run test script:**
   ```powershell
   .\test_scenarios.ps1
   ```

3. **Or test manually with curl/Postman using the scenarios above**

---

## Acceptance Criteria

Tests pass if:
1. ✅ Customer booking flow shows correct states and actions at each step
2. ✅ SUBMIT_TRIP only allowed at `booking_ready_to_submit`
3. ✅ Confirmation message is forced after valid SUBMIT_TRIP
4. ✅ Captain role detected correctly (not booking flow)
5. ✅ Malformed LLM output returns safe fallback (v2 + legacy)



