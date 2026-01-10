# Safety Center API Documentation
**Date:** January 10, 2026  
**Version:** 1.0

---

## 📍 Overview

The Safety Center provides comprehensive safety features for drivers including:
- 🔒 **Trusted Contacts** - Add emergency contacts
- 📍 **Trip Sharing** - Share real-time trip location
- 👁️ **Trip Monitoring** - Automatic monitoring with alerts
- 🚨 **Emergency Alerts** - Panic button and police assistance

**Base URL:** `https://smartline-it.com/api/driver/safety`

---

## 🔐 Authentication

All endpoints require driver authentication:
```
Authorization: Bearer {driver_access_token}
```

---

## 📋 Endpoints Summary

| Category | Method | Endpoint | Description |
|----------|--------|----------|-------------|
| **Trusted Contacts** | GET | `/trusted-contacts` | List all contacts |
| | POST | `/trusted-contacts` | Add new contact |
| | PUT | `/trusted-contacts/{id}` | Update contact |
| | DELETE | `/trusted-contacts/{id}` | Remove contact |
| **Trip Sharing** | POST | `/share-trip` | Share current trip |
| | GET | `/shared-trips` | List shared trips |
| | DELETE | `/share-trip/{id}` | Stop sharing |
| **Trip Monitoring** | POST | `/enable-monitoring` | Enable monitoring |
| | GET | `/monitoring-status` | Check status |
| | POST | `/update-monitoring-location` | Update location |
| **Emergency** | POST | `/emergency-alert` | Trigger alert |
| | GET | `/emergency-contacts` | Get emergency numbers |
| | GET | `/my-alerts` | List my alerts |

---

## 🔒 Trusted Contacts

### 1. Get Trusted Contacts

```
GET /api/driver/safety/trusted-contacts
```

**Response:**
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "contacts": [
      {
        "id": "uuid",
        "name": "Ahmed Mohamed",
        "phone": "+201234567890",
        "relationship": "family",
        "priority": 1,
        "is_active": true,
        "created_at": "2026-01-10T12:00:00+00:00"
      }
    ],
    "total": 3,
    "max_contacts": 5
  }
}
```

### 2. Add Trusted Contact

```
POST /api/driver/safety/trusted-contacts
Content-Type: application/json

{
  "name": "Ahmed Mohamed",
  "phone": "+201234567890",
  "relationship": "family",
  "priority": 1
}
```

**Validation:**
- `name`: required, max 255 characters
- `phone`: required, max 20 characters
- `relationship`: optional, one of: family, friend, colleague, other
- `priority`: optional, integer 1-5 (1 = highest priority)

**Response:**
```json
{
  "response_code": "default_store_200",
  "message": "Successfully created",
  "data": {
    "contact": {
      "id": "uuid",
      "name": "Ahmed Mohamed",
      "phone": "+201234567890",
      "relationship": "family",
      "priority": 1
    }
  }
}
```

**Error - Max Contacts Reached:**
```json
{
  "response_code": "max_contacts_reached_400",
  "message": "You can only have up to 5 trusted contacts"
}
```

### 3. Update Trusted Contact

```
PUT /api/driver/safety/trusted-contacts/{id}
Content-Type: application/json

{
  "name": "Ahmed Ali",
  "phone": "+201234567891",
  "priority": 2
}
```

### 4. Delete Trusted Contact

```
DELETE /api/driver/safety/trusted-contacts/{id}
```

---

## 📍 Trip Sharing

### 1. Share Trip

```
POST /api/driver/safety/share-trip
Content-Type: application/json

{
  "trip_id": "trip-uuid",
  "contact_ids": ["contact-uuid-1", "contact-uuid-2"],
  "share_method": "auto",
  "expires_in_hours": 3
}
```

**Parameters:**
- `trip_id`: required, UUID of active trip
- `contact_ids`: optional, array of contact UUIDs (if empty and method=auto, shares with all)
- `share_method`: required, one of:
  - `sms` - Send SMS with tracking link
  - `whatsapp` - Send WhatsApp message
  - `link` - Generate shareable link only
  - `auto` - Send via both SMS and WhatsApp
- `expires_in_hours`: optional, 1-24 hours (default: no expiration)

**Response:**
```json
{
  "response_code": "default_store_200",
  "message": "Successfully created",
  "data": {
    "shares": [
      {
        "id": "share-uuid",
        "contact_name": "Ahmed Mohamed",
        "share_url": "https://smartline-it.com/track-trip/abc123xyz",
        "whatsapp_url": "https://wa.me/201234567890?text=...",
        "expires_at": "2026-01-10T15:00:00+00:00"
      }
    ],
    "total_shared": 3,
    "message": "Trip shared successfully"
  }
}
```

**Trip Status Requirements:**
- Trip must be in status: `accepted`, `ongoing`, or `arrived`
- Trip must belong to authenticated driver

### 2. Get Shared Trips

```
GET /api/driver/safety/shared-trips?limit=20
```

**Response:**
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "shares": [
      {
        "id": "share-uuid",
        "trip_ref": "TRIP-12345",
        "trip_status": "ongoing",
        "shared_with": {
          "name": "Ahmed Mohamed",
          "phone": "+201234567890"
        },
        "share_url": "https://smartline-it.com/track-trip/abc123xyz",
        "share_method": "auto",
        "access_count": 5,
        "expires_at": "2026-01-10T15:00:00+00:00",
        "created_at": "2026-01-10T12:00:00+00:00"
      }
    ],
    "total": 3
  }
}
```

### 3. Stop Sharing Trip

```
DELETE /api/driver/safety/share-trip/{id}
```

---

## 👁️ Trip Monitoring

### 1. Enable Trip Monitoring

```
POST /api/driver/safety/enable-monitoring
Content-Type: application/json

{
  "trip_id": "trip-uuid",
  "auto_alert_enabled": true,
  "alert_delay_minutes": 15
}
```

**Parameters:**
- `trip_id`: required, UUID of active trip
- `auto_alert_enabled`: optional, boolean (default: true)
- `alert_delay_minutes`: optional, 5-60 minutes (default: 15)

**How It Works:**
- Monitors trip progress in real-time
- If trip exceeds expected duration by `alert_delay_minutes`, triggers automatic alert
- Sends notifications to all trusted contacts
- Tracks driver location updates

**Response:**
```json
{
  "response_code": "default_store_200",
  "message": "Successfully created",
  "data": {
    "monitoring": {
      "id": "monitoring-uuid",
      "trip_id": "trip-uuid",
      "is_enabled": true,
      "auto_alert_enabled": true,
      "alert_delay_minutes": 15,
      "started_at": "2026-01-10T12:00:00+00:00"
    },
    "message": "Trip monitoring enabled"
  }
}
```

### 2. Get Monitoring Status

```
GET /api/driver/safety/monitoring-status?trip_id=trip-uuid
```

**Response:**
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "monitoring": [
      {
        "id": "monitoring-uuid",
        "trip_ref": "TRIP-12345",
        "trip_status": "ongoing",
        "is_enabled": true,
        "auto_alert_enabled": true,
        "alert_delay_minutes": 15,
        "alert_triggered": false,
        "last_location_update": "2026-01-10T12:05:00+00:00",
        "started_at": "2026-01-10T12:00:00+00:00"
      }
    ],
    "active_count": 1
  }
}
```

### 3. Update Monitoring Location

```
POST /api/driver/safety/update-monitoring-location
Content-Type: application/json

{
  "trip_id": "trip-uuid",
  "latitude": 30.0444,
  "longitude": 31.2357
}
```

**Note:** Call this endpoint periodically (every 30-60 seconds) during monitored trips.

---

## 🚨 Emergency Alerts

### 1. Trigger Emergency Alert

```
POST /api/driver/safety/emergency-alert
Content-Type: application/json

{
  "alert_type": "panic",
  "trip_id": "trip-uuid",
  "latitude": 30.0444,
  "longitude": 31.2357,
  "notes": "Suspicious passenger behavior"
}
```

**Alert Types:**
- `panic` - General panic/SOS
- `police` - Police assistance needed
- `medical` - Medical emergency
- `accident` - Traffic accident
- `harassment` - Harassment situation

**What Happens:**
1. Alert created with status "active"
2. **All trusted contacts** receive emergency SMS with location
3. Admin/support team notified
4. If trip monitoring enabled, triggers monitoring alert
5. Emergency contact numbers provided in response

**Response:**
```json
{
  "response_code": "default_store_200",
  "message": "Successfully created",
  "data": {
    "alert": {
      "id": "alert-uuid",
      "alert_type": "panic",
      "status": "active",
      "created_at": "2026-01-10T12:00:00+00:00"
    },
    "message": "Emergency alert triggered. Help is on the way.",
    "emergency_numbers": [
      {
        "service": "police",
        "name": "Police",
        "name_ar": "الشرطة",
        "number": "122",
        "icon": "police"
      },
      {
        "service": "ambulance",
        "name": "Ambulance",
        "name_ar": "الإسعاف",
        "number": "123",
        "icon": "medical"
      },
      {
        "service": "traffic",
        "name": "Traffic Police",
        "name_ar": "مرور",
        "number": "128",
        "icon": "traffic"
      },
      {
        "service": "fire",
        "name": "Fire Department",
        "name_ar": "الإطفاء",
        "number": "180",
        "icon": "fire"
      }
    ]
  }
}
```

### 2. Get Emergency Contacts

```
GET /api/driver/safety/emergency-contacts
```

Returns Egyptian emergency service numbers (Police: 122, Ambulance: 123, etc.)

### 3. Get My Alerts

```
GET /api/driver/safety/my-alerts?limit=20
```

**Response:**
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "alerts": [
      {
        "id": "alert-uuid",
        "alert_type": "panic",
        "status": "active",
        "trip_id": "trip-uuid",
        "location": {
          "latitude": 30.0444,
          "longitude": 31.2357
        },
        "notes": "Suspicious passenger behavior",
        "resolved_at": null,
        "created_at": "2026-01-10T12:00:00+00:00"
      }
    ],
    "total": 1
  }
}
```

---

## 🧪 Testing Examples

### Test 1: Add Trusted Contact

```bash
curl -X POST "https://smartline-it.com/api/driver/safety/trusted-contacts" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Ahmed Mohamed",
    "phone": "+201234567890",
    "relationship": "family",
    "priority": 1
  }'
```

### Test 2: Share Trip

```bash
curl -X POST "https://smartline-it.com/api/driver/safety/share-trip" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "trip_id": "trip-uuid",
    "share_method": "auto",
    "expires_in_hours": 3
  }'
```

### Test 3: Trigger Emergency

```bash
curl -X POST "https://smartline-it.com/api/driver/safety/emergency-alert" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "alert_type": "panic",
    "latitude": 30.0444,
    "longitude": 31.2357,
    "notes": "Need help"
  }'
```

---

## 📱 Flutter Integration

### Safety Center Screen

```dart
class SafetyCenterScreen extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Safety Center')),
      body: ListView(
        children: [
          // Safety Control Monthly Report
          Card(
            child: ListTile(
              title: Text('Safety Control Monthly Report for November'),
              trailing: Icon(Icons.arrow_forward),
              onTap: () => Navigator.push(context, SafetyReportScreen()),
            ),
          ),
          
          SizedBox(height: 16),
          Text('Safety Tools', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
          
          // Trusted Contacts
          ListTile(
            leading: CircleAvatar(
              backgroundColor: Colors.orange[100],
              child: Icon(Icons.person_add, color: Colors.orange),
            ),
            title: Text('Trusted Contacts'),
            subtitle: Text('Share trip quickly'),
            trailing: TextButton(
              child: Text('Add Now'),
              onPressed: () => Navigator.push(context, AddTrustedContactScreen()),
            ),
          ),
          
          // Trip Sharing
          ListTile(
            leading: CircleAvatar(
              backgroundColor: Colors.blue[100],
              child: Icon(Icons.share_location, color: Colors.blue),
            ),
            title: Text('Trip Sharing'),
            subtitle: Text('Real-Time updates'),
            trailing: TextButton(
              child: Text('See More'),
              onPressed: () => Navigator.push(context, TripSharingScreen()),
            ),
          ),
          
          // Trip Monitoring
          ListTile(
            leading: CircleAvatar(
              backgroundColor: Colors.blue[100],
              child: Icon(Icons.monitor, color: Colors.blue),
            ),
            title: Text('Trip Monitoring'),
            subtitle: Text('Alarm and Help'),
            trailing: Switch(
              value: true,
              onChanged: (value) => enableMonitoring(value),
            ),
          ),
          
          // Police Assistance
          ListTile(
            leading: CircleAvatar(
              backgroundColor: Colors.red[100],
              child: Icon(Icons.local_police, color: Colors.red),
            ),
            title: Text('Police Assistance'),
            subtitle: Text('Tap for police'),
            trailing: TextButton(
              child: Text('See More'),
              onPressed: () => showEmergencyContacts(context),
            ),
          ),
        ],
      ),
    );
  }
}
```

### Share Trip Function

```dart
Future<void> shareTrip(String tripId) async {
  final response = await http.post(
    Uri.parse('https://smartline-it.com/api/driver/safety/share-trip'),
    headers: {
      'Authorization': 'Bearer $token',
      'Content-Type': 'application/json',
    },
    body: jsonEncode({
      'trip_id': tripId,
      'share_method': 'auto',
      'expires_in_hours': 3,
    }),
  );
  
  if (response.statusCode == 200) {
    final data = jsonDecode(response.body)['data'];
    showSnackBar('Trip shared with ${data['total_shared']} contacts');
  }
}
```

### Emergency Alert Button

```dart
FloatingActionButton(
  backgroundColor: Colors.red,
  onPressed: () => showEmergencyDialog(context),
  child: Icon(Icons.warning, size: 32),
);

void showEmergencyDialog(BuildContext context) {
  showDialog(
    context: context,
    builder: (context) => AlertDialog(
      title: Text('Emergency Alert'),
      content: Text('This will notify all your trusted contacts and emergency services. Continue?'),
      actions: [
        TextButton(
          child: Text('Cancel'),
          onPressed: () => Navigator.pop(context),
        ),
        ElevatedButton(
          style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
          child: Text('SEND ALERT'),
          onPressed: () {
            Navigator.pop(context);
            triggerEmergencyAlert('panic');
          },
        ),
      ],
    ),
  );
}

Future<void> triggerEmergencyAlert(String alertType) async {
  final position = await getCurrentPosition();
  
  final response = await http.post(
    Uri.parse('https://smartline-it.com/api/driver/safety/emergency-alert'),
    headers: {
      'Authorization': 'Bearer $token',
      'Content-Type': 'application/json',
    },
    body: jsonEncode({
      'alert_type': alertType,
      'latitude': position.latitude,
      'longitude': position.longitude,
    }),
  );
  
  if (response.statusCode == 200) {
    final data = jsonDecode(response.body)['data'];
    showEmergencyContactsDialog(data['emergency_numbers']);
  }
}
```

---

## 🗄️ Database Schema

### trusted_contacts
```sql
id (UUID)
user_id (UUID) → users.id
name (VARCHAR)
phone (VARCHAR)
relationship (family/friend/colleague/other)
is_active (BOOLEAN)
priority (INTEGER 1-5)
created_at, updated_at
```

### trip_shares
```sql
id (UUID)
trip_id (UUID) → trip_requests.id
driver_id (UUID) → users.id
shared_with_contact_id (UUID) → trusted_contacts.id (nullable)
share_token (VARCHAR, unique)
share_method (sms/whatsapp/link/auto)
is_active (BOOLEAN)
expires_at (TIMESTAMP, nullable)
last_accessed_at (TIMESTAMP, nullable)
access_count (INTEGER)
created_at, updated_at
```

### emergency_alerts
```sql
id (UUID)
user_id (UUID) → users.id
user_type (driver/customer)
trip_id (UUID) → trip_requests.id (nullable)
alert_type (panic/police/medical/accident/harassment)
status (active/resolved/false_alarm/cancelled)
latitude, longitude (DECIMAL)
location_address (VARCHAR, nullable)
notes (TEXT, nullable)
resolved_at (TIMESTAMP, nullable)
resolved_by (UUID) → users.id (nullable)
resolution_notes (TEXT, nullable)
created_at, updated_at
```

### trip_monitoring
```sql
id (UUID)
trip_id (UUID) → trip_requests.id (unique)
driver_id (UUID) → users.id
is_enabled (BOOLEAN)
auto_alert_enabled (BOOLEAN)
alert_delay_minutes (INTEGER)
monitoring_started_at (TIMESTAMP, nullable)
monitoring_ended_at (TIMESTAMP, nullable)
last_location_update (TIMESTAMP, nullable)
last_latitude, last_longitude (DECIMAL)
alert_triggered (BOOLEAN)
alert_triggered_at (TIMESTAMP, nullable)
created_at, updated_at
```

---

## ⚠️ Important Notes

1. **Max Trusted Contacts:** Limit of 5 contacts per driver
2. **Trip Sharing:** Only works for active trips (accepted/ongoing/arrived status)
3. **Emergency SMS:** Sent to all active trusted contacts automatically
4. **Location Updates:** Should be sent every 30-60 seconds during monitored trips
5. **Alert Expiration:** Trip shares can expire after specified hours

---

## 📞 Support

For questions: support@smartline-it.com

---

**Status:** ✅ Ready for Implementation  
**Version:** 1.0  
**Last Updated:** January 10, 2026
