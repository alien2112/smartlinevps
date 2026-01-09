# Test Execution Instructions

## ✅ Implementation Complete

Backend state transition validation has been implemented with:
- SUBMIT_TRIP blocking (only allowed at `booking_ready_to_submit`)
- Confirmation message enforcement after valid submission
- State transition validation (prevents skipping/regression)

---

## 🧪 Tests Required

Run the test scenarios and verify outputs match requirements.

### Prerequisites

1. **Start the server:**
   ```bash
   node chat.js
   ```

2. **Ensure server is healthy:**
   ```bash
   curl http://localhost:3000/health
   ```

### Test Execution

Run the test script:
```powershell
.\test_scenarios.ps1
```

Or test manually using the scenarios in `TEST_REQUIREMENTS.md`.

---

## ✅ Expected Test Results

### Test A: Customer Booking (Step-by-Step)

**Step 1:** "book a trip"
- `/chat/v2`: `state = "booking_awaiting_pickup"`, `actions` includes `OPEN_MAPS select_pickup`
- `/chat`: `reply` present, all legacy keys present

**Step 2:** After pickup
- `/chat/v2`: `state = "booking_awaiting_destination"`, `OPEN_MAPS select_destination`

**Step 3:** After destination
- `/chat/v2`: `state = "booking_awaiting_time"`, asks for timing

**Step 4:** Submit
- `/chat/v2`: `SUBMIT_TRIP` only if state = `booking_ready_to_submit`
- Message forced to: "Trip initialized successfully. Waiting for verification." (localized)
- `state = "waiting_verification"`

### Test B: Captain Support

Input: "انا كابتن التطبيق مش شغال"
- `/chat/v2`: `role = "captain"`, `intent = "captain_app_issue"`
- **NOT** booking states/actions

### Test C: Malformed LLM Output

- `/chat/v2`: Returns fallback JSON with `error.code = "PARSE_ERROR"`
- `/chat`: Returns legacy format with safe `reply` string

---

## 📋 Response Format

Once tests are complete, provide:
1. Full outputs for Customer booking step-by-step (all 4 steps)
2. Captain issue response (role=captain, not booking)
3. Malformed output fallback response (v2 PARSE_ERROR + legacy reply)

If all outputs match expected behavior, implementation is accepted.



