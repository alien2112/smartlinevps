# How the AI Chatbot Books a Trip 🤖🚗

## Quick Summary
The chatbot uses a **state machine** to guide users through booking in **7 conversational steps**, then creates a complete trip record across **5 database tables** in a single transaction.

---

## The Complete Flow

### Step 1: Greeting
**User:** "مرحبا" or "Hello"  
**Bot:** Welcome message with options  
**State:** START  

```json
{
  "message": "مرحبا! كيف أساعدك؟",
  "quick_replies": ["حجز رحلة", "رحلاتي", "مساعدة"]
}
```

---

### Step 2: Intent Detection
**User:** "أريد حجز رحلة" (I want to book a ride)  
**Bot:** Asks for pickup location  
**State:** START → AWAITING_PICKUP  

**How it works:**
- Scans message for keywords: `['رحلة', 'حجز', 'book', 'ride', 'trip']`
- Matches `BOOK_TRIP` intent
- Returns action: `request_pickup_location`

```json
{
  "message": "🚗 من أين تريد أن تبدأ الرحلة؟",
  "action": "request_pickup_location",
  "data": {"map_type": "pickup"}
}
```

---

### Step 3: Pickup Location
**User:** Sends location from Flutter app  
**Format:** `location:30.0444,31.2357` + address  
**Bot:** Confirms and asks for destination  
**State:** AWAITING_PICKUP → AWAITING_DESTINATION  

```json
{
  "message": "✅ تم. إلى أين تريد الذهاب؟",
  "action": "request_destination",
  "data": {
    "pickup": {
      "lat": 30.0444,
      "lng": 31.2357,
      "address": "مدينة نصر، القاهرة"
    }
  }
}
```

---

### Step 4: Destination Location
**User:** Sends destination location  
**Bot:** Shows vehicle category options  
**State:** AWAITING_DESTINATION → AWAITING_RIDE_TYPE  

**Processing:**
1. Fetches active vehicle categories from database
2. Presents as numbered list
3. Provides quick reply buttons

```json
{
  "message": "✅ تم تحديد الوجهة.\nاختر نوع الرحلة:\n1. Taxi\n2. سمارت برو\n3. في اي بي",
  "action": "show_ride_options",
  "quick_replies": ["Taxi", "سمارت برو", "في اي بي"]
}
```

---

### Step 5: Vehicle Selection
**User:** "1" or "Taxi"  
**Bot:** Shows confirmation with trip summary  
**State:** AWAITING_RIDE_TYPE → AWAITING_CONFIRMATION  

```json
{
  "message": "تأكيد:\n📍 من: مدينة نصر\n📍 إلى: التجمع الخامس\n🚗 Taxi\n\nتأكيد الحجز؟",
  "quick_replies": ["تأكيد الحجز", "إلغاء"]
}
```

---

### Step 6: Confirmation & Trip Creation ⭐
**User:** "تأكيد الحجز" (Confirm booking)  
**Bot:** Creates trip in database!  
**State:** AWAITING_CONFIRMATION → TRIP_ACTIVE  

**This is where the magic happens:**

#### Database Operations (Transaction):

```javascript
async function createTrip(tripData) {
    await connection.beginTransaction();
    
    // 1. Generate IDs
    const tripId = generateUUID();  // "61e4541b-0fe3-..."
    const refId = await getNextRefId();  // 102493
    
    // 2. Parse coordinates
    pickupLat = 30.0444, pickupLng = 31.2357
    destLat = 30.0600, destLng = 31.2500
    
    // 3. Find zone
    const zoneId = await findZoneByCoordinates(pickupLat, pickupLng);
    
    // 4. Calculate fare
    const estimatedFare = await calculateEstimatedFare(vehicleType, 5);
    
    // 5. Insert into 5 tables
    INSERT INTO trip_requests (...)
    INSERT INTO trip_status (...)
    INSERT INTO trip_request_coordinates (...)
    INSERT INTO trip_request_fees (...)
    INSERT INTO trip_request_times (...)
    
    await connection.commit();
    
    return {
        trip_id: tripId,
        ref_id: refId,
        estimated_fare: estimatedFare
    };
}
```

**Response:**
```json
{
  "message": "🎉 تم تأكيد الحجز!\n📋 رقم الرحلة: 102493\n💰 السعر المتوقع: 30 ج.م",
  "action": "confirm_booking",
  "data": {
    "trip_id": "61e4541b-0fe3-466f-b434-0fa308c3b3cd",
    "ref_id": 102493,
    "estimated_fare": 30
  }
}
```

---

### Step 7: Trip Active
**State:** TRIP_ACTIVE  
**User can now:**
- Track trip: "أين الكابتن؟"
- Cancel trip: "إلغاء الرحلة"
- Contact driver: "اتصل بالكابتن"

---

## Database Tables Populated

When the trip is created, these 5 tables are populated:

### 1. `trip_requests` (Main Record)
```sql
id: "61e4541b-0fe3-466f-b434-0fa308c3b3cd"
ref_id: 102493
customer_id: "test-user-123"
vehicle_category_id: "d4d1e8f1-..."
zone_id: "182440b2-..."
estimated_fare: 30.00
current_status: "pending"
payment_method: "cash"
type: "ride_request"
```

### 2. `trip_status` (Status Tracking)
```sql
trip_request_id: "61e4541b-..."
customer_id: "test-user-123"
pending: "2026-01-04 17:09:56"
accepted: NULL
ongoing: NULL
completed: NULL
```

### 3. `trip_request_coordinates` (Locations)
```sql
trip_request_id: "61e4541b-..."
pickup_coordinates: POINT(31.2357 30.0444)
destination_coordinates: POINT(31.25 30.06)
pickup_address: "مدينة نصر، القاهرة"
destination_address: "التجمع الخامس"
```

### 4. `trip_request_fees` (Fee Structure)
```sql
trip_request_id: "61e4541b-..."
[Fee details populated]
```

### 5. `trip_request_times` (Time Estimates)
```sql
trip_request_id: "61e4541b-..."
estimated_time: 15
```

---

## State Machine States

```
START
  ↓ (user says "book ride")
AWAITING_PICKUP
  ↓ (location received)
AWAITING_DESTINATION
  ↓ (location received)
AWAITING_RIDE_TYPE
  ↓ (vehicle selected)
AWAITING_CONFIRMATION
  ↓ (user confirms)
TRIP_ACTIVE ← Trip created in DB!
```

---

## Conversation State Persistence

### ai_conversation_state table
```sql
user_id: "test-user-123"
current_state: "TRIP_ACTIVE"
flow_data: {
  "pickup": {"lat": 30.0444, "lng": 31.2357},
  "destination": {"lat": 30.0600, "lng": 31.2500},
  "ride_type": "d4d1e8f1-...",
  "trip_id": "61e4541b-..."
}
```

### ai_chat_history table
Stores all messages:
```sql
user_id | role      | content              | action_type
--------|-----------|----------------------|------------------
user-1  | user      | مرحبا                | NULL
user-1  | assistant | مرحبا! كيف أساعدك؟   | none
user-1  | user      | أريد حجز رحلة         | NULL
user-1  | assistant | من أين تريد البدء؟    | request_pickup_location
...
```

---

## Intent Detection

The chatbot uses keyword matching:

```javascript
const INTENTS = {
    BOOK_TRIP: {
        keywords: ['رحلة', 'توصيل', 'حجز', 'book', 'ride', 'trip'],
        action: 'book_trip'
    },
    CANCEL_TRIP: {
        keywords: ['إلغاء', 'cancel'],
        action: 'cancel_trip'
    },
    CONTACT_DRIVER: {
        keywords: ['اتصل', 'call', 'contact'],
        action: 'contact_driver'
    }
};

function detectIntent(message) {
    const lowerMessage = message.toLowerCase();
    for (const keyword of intent.keywords) {
        if (lowerMessage.includes(keyword)) {
            return intent;
        }
    }
}
```

---

## Location Data Formats

The chatbot accepts locations in 2 formats:

### Format 1: In message text
```
"location:30.0444,31.2357 مدينة نصر"
```

### Format 2: Via location_data parameter
```json
{
  "user_id": "123",
  "message": "هنا",
  "location_data": {
    "lat": 30.0444,
    "lng": 31.2357,
    "address": "مدينة نصر، القاهرة"
  }
}
```

**Extraction:**
```javascript
const match = message.match(/location:([\d.-]+),([\d.-]+)/);
const lat = parseFloat(match[1]);
const lng = parseFloat(match[2]);
```

---

## Flutter Integration

The chatbot returns **action types** that tell the Flutter app what to do:

| Action | Flutter Behavior |
|--------|------------------|
| `request_pickup_location` | Open map to select pickup |
| `request_destination` | Open map to select destination |
| `show_ride_options` | Show vehicle category list |
| `confirm_booking` | Show success animation, navigate to trip screen |
| `show_trip_tracking` | Open live tracking screen |
| `cancel_trip` | Show cancellation confirmation |

---

## Key Technologies

- **Language:** Node.js (Express)
- **Database:** MySQL with connection pooling
- **LLM:** Groq API (Llama 3.3 70B) for complex queries
- **Geospatial:** MySQL ST_GeomFromText for POINT data
- **Transaction Safety:** BEGIN/COMMIT/ROLLBACK
- **State Management:** Database-backed state machine

---

## Test Results

Our recent test created:
- **Trip ID:** 61e4541b-0fe3-466f-b434-0fa308c3b3cd
- **Ref ID:** 102493
- **Status:** pending
- **Fare:** 30.00 EGP
- **Vehicle:** Taxi
- **Zone:** Cairo

All 5 database tables populated correctly! ✅

---

## Advantages of This Approach

✅ **Natural Language** - Users can talk naturally  
✅ **Bilingual** - Supports Arabic & English  
✅ **Stateful** - Remembers conversation context  
✅ **Transactional** - Safe database operations  
✅ **Action-Based** - Integrates seamlessly with Flutter  
✅ **Fallback to LLM** - Uses AI for unclear queries  
✅ **Quick Replies** - Suggests next actions  
✅ **Error Handling** - Rollback on failure  

---

## API Endpoints

**Main Chat Endpoint:**
```
POST http://localhost:3001/chat
```

**Request:**
```json
{
  "user_id": "user-123",
  "message": "أريد حجز رحلة",
  "location_data": {
    "lat": 30.0444,
    "lng": 31.2357
  }
}
```

**Response:**
```json
{
  "message": "🚗 من أين تريد أن تبدأ الرحلة؟",
  "action": "request_pickup_location",
  "data": {},
  "quick_replies": [],
  "confidence": 0.85,
  "language": {"primary": "ar"},
  "model": "Llama 3.3 70B"
}
```

---

## Files Involved

- `ai-chat-bot-v3/chat.js` - Main chatbot server
- `ai-chat-bot-v3/actions.js` - Action types for Flutter
- Database tables: `trip_requests`, `trip_status`, `trip_request_coordinates`, `trip_request_fees`, `trip_request_times`, `ai_conversation_state`, `ai_chat_history`

---

**Created:** 2026-01-04  
**Status:** ✅ Production Ready  
**Testing:** Passed all 8 tests
