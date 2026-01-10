# Driver Honeycomb Dispatch Preference API

## 📍 Overview

Drivers can enable or disable **Honeycomb Dispatch** for their account. Honeycomb is an optimized zone-based dispatch system that:
- ✅ Matches drivers faster using H3 hexagonal cells
- ✅ Reduces unnecessary GPS calculations
- ✅ Provides better supply/demand analytics
- ✅ Enables smart surge pricing

**When disabled:** Driver will use standard distance-based dispatch (slower but works everywhere)  
**When enabled:** Driver gets trips faster in areas with Honeycomb coverage

**Base URL:** `https://smartline-it.com`

---

## 🎯 Features

- ✅ **Toggle** Honeycomb dispatch ON/OFF
- ✅ **Check status** of your Honeycomb preference
- ✅ **Set explicitly** enable or disable
- ✅ **Default:** Enabled for all drivers

---

## 📋 Endpoints Summary

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/driver/honeycomb/status` | Get current preference |
| PATCH | `/api/driver/honeycomb/toggle` | Toggle ON/OFF |
| PATCH | `/api/driver/honeycomb/set` | Set explicitly |

---

## 🔐 Authentication

All endpoints require authentication:
```
Authorization: Bearer {driver_access_token}
```

---

## 1️⃣ Get Honeycomb Status

### Endpoint
```
GET /api/driver/honeycomb/status
```

### Description
Retrieve your current Honeycomb dispatch preference.

### Request
```bash
curl -X GET "https://smartline-it.com/api/driver/honeycomb/status" \
  -H "Authorization: Bearer {your_access_token}"
```

### Success Response (200)
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "honeycomb_enabled": true,
    "description": "Honeycomb dispatch is enabled - you will be matched using optimized zone-based dispatch"
  }
}
```

**When Disabled:**
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "honeycomb_enabled": false,
    "description": "Honeycomb dispatch is disabled - you will be matched using standard distance-based dispatch"
  }
}
```

---

## 2️⃣ Toggle Honeycomb ON/OFF

### Endpoint
```
PATCH /api/driver/honeycomb/toggle
```

### Description
Toggle Honeycomb dispatch on or off. If currently enabled, it will be disabled, and vice versa.

### Request
```bash
curl -X PATCH "https://smartline-it.com/api/driver/honeycomb/toggle" \
  -H "Authorization: Bearer {your_access_token}"
```

### Success Response (200)
```json
{
  "response_code": "default_status_update_200",
  "message": "Honeycomb dispatch enabled",
  "data": {
    "honeycomb_enabled": true,
    "description": "Honeycomb dispatch is enabled - you will be matched using optimized zone-based dispatch"
  }
}
```

**Or when toggled off:**
```json
{
  "response_code": "default_status_update_200",
  "message": "Honeycomb dispatch disabled",
  "data": {
    "honeycomb_enabled": false,
    "description": "Honeycomb dispatch is disabled - you will be matched using standard distance-based dispatch"
  }
}
```

---

## 3️⃣ Set Honeycomb Status Explicitly

### Endpoint
```
PATCH /api/driver/honeycomb/set
```

### Description
Set Honeycomb dispatch status explicitly to `true` or `false`.

### Request Parameters
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `enabled` | boolean | ✅ Yes | `true` to enable, `false` to disable |

### Request
```bash
# Enable Honeycomb
curl -X PATCH "https://smartline-it.com/api/driver/honeycomb/set" \
  -H "Authorization: Bearer {your_access_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "enabled": true
  }'

# Disable Honeycomb
curl -X PATCH "https://smartline-it.com/api/driver/honeycomb/set" \
  -H "Authorization: Bearer {your_access_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "enabled": false
  }'
```

### Success Response (200)
```json
{
  "response_code": "default_status_update_200",
  "message": "Honeycomb dispatch enabled",
  "data": {
    "honeycomb_enabled": true,
    "description": "Honeycomb dispatch is enabled - you will be matched using optimized zone-based dispatch"
  }
}
```

### Validation Errors (400)
```json
{
  "response_code": "default_400",
  "message": "Validation failed",
  "errors": [
    {
      "error_code": "enabled",
      "message": "The enabled field is required."
    }
  ]
}
```

---

## 🧪 Testing Examples

### Test 1: Check Current Status
```bash
curl -X GET "https://smartline-it.com/api/driver/honeycomb/status" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

### Test 2: Toggle OFF
```bash
curl -X PATCH "https://smartline-it.com/api/driver/honeycomb/toggle" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

### Test 3: Enable Explicitly
```bash
curl -X PATCH "https://smartline-it.com/api/driver/honeycomb/set" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..." \
  -H "Content-Type: application/json" \
  -d '{"enabled": true}'
```

---

## 🔄 Integration with Dispatch System

### How It Works

1. **When Honeycomb is ENABLED:**
   - Driver appears in Honeycomb hexagonal cells
   - Gets matched faster using cell-based search
   - Participates in surge pricing zones
   - Better analytics and demand predictions

2. **When Honeycomb is DISABLED:**
   - Driver is excluded from Honeycomb dispatch
   - Falls back to standard distance-based matching
   - Still receives trips but may be slower in busy areas
   - Not visible in Honeycomb heatmaps

### Database Changes

A new field `honeycomb_enabled` was added to the `driver_details` table:

```sql
ALTER TABLE driver_details 
ADD COLUMN honeycomb_enabled BOOLEAN DEFAULT TRUE 
COMMENT 'Driver preference: enable/disable honeycomb dispatch';

CREATE INDEX idx_honeycomb_enabled ON driver_details(honeycomb_enabled);
```

### Dispatch Logic

The dispatch system filters drivers based on this preference:

```php
// In HoneycombDispatchTrait.php
->whereHas('driverDetails', fn($query) => $query
    ->where('is_online', true)
    ->whereNotIn('availability_status', ['unavailable', 'on_trip'])
    ->where('honeycomb_enabled', true) // Only honeycomb-enabled drivers
)
```

---

## 📱 Flutter Integration

### API Service
```dart
class HoneycombService {
  static const String baseUrl = 'https://smartline-it.com/api/driver/honeycomb';
  
  // Get status
  Future<HoneycombStatus> getStatus() async {
    final response = await http.get(
      Uri.parse('$baseUrl/status'),
      headers: {'Authorization': 'Bearer $token'},
    );
    return HoneycombStatus.fromJson(jsonDecode(response.body)['data']);
  }
  
  // Toggle
  Future<HoneycombStatus> toggle() async {
    final response = await http.patch(
      Uri.parse('$baseUrl/toggle'),
      headers: {'Authorization': 'Bearer $token'},
    );
    return HoneycombStatus.fromJson(jsonDecode(response.body)['data']);
  }
  
  // Set explicitly
  Future<HoneycombStatus> set(bool enabled) async {
    final response = await http.patch(
      Uri.parse('$baseUrl/set'),
      headers: {
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({'enabled': enabled}),
    );
    return HoneycombStatus.fromJson(jsonDecode(response.body)['data']);
  }
}

class HoneycombStatus {
  final bool honeycombEnabled;
  final String description;
  
  HoneycombStatus({
    required this.honeycombEnabled,
    required this.description,
  });
  
  factory HoneycombStatus.fromJson(Map<String, dynamic> json) {
    return HoneycombStatus(
      honeycombEnabled: json['honeycomb_enabled'] ?? true,
      description: json['description'] ?? '',
    );
  }
}
```

### UI Widget
```dart
class HoneycombToggle extends StatefulWidget {
  @override
  _HoneycombToggleState createState() => _HoneycombToggleState();
}

class _HoneycombToggleState extends State<HoneycombToggle> {
  bool _isEnabled = true;
  bool _isLoading = false;
  
  @override
  void initState() {
    super.initState();
    _loadStatus();
  }
  
  Future<void> _loadStatus() async {
    final status = await HoneycombService().getStatus();
    setState(() {
      _isEnabled = status.honeycombEnabled;
    });
  }
  
  Future<void> _toggle() async {
    setState(() => _isLoading = true);
    try {
      final status = await HoneycombService().toggle();
      setState(() {
        _isEnabled = status.honeycombEnabled;
        _isLoading = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(status.description)),
      );
    } catch (e) {
      setState(() => _isLoading = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to toggle: $e')),
      );
    }
  }
  
  @override
  Widget build(BuildContext context) {
    return ListTile(
      title: Text('مناطق زيادة الأجرة (Honeycomb)'),
      subtitle: Text(
        _isEnabled 
          ? 'تفعيل - توزيع سريع للرحلات'
          : 'معطل - توزيع عادي'
      ),
      trailing: _isLoading
        ? CircularProgressIndicator()
        : Switch(
            value: _isEnabled,
            onChanged: (value) => _toggle(),
          ),
    );
  }
}
```

---

## ⚠️ Important Notes

1. **Default Behavior:** All drivers have Honeycomb **ENABLED by default**
2. **Fallback:** If Honeycomb is disabled for a zone (admin setting), standard dispatch is used regardless of driver preference
3. **Performance:** Disabling Honeycomb may result in slower trip matching in busy areas
4. **Analytics:** Drivers with Honeycomb disabled won't appear in heatmaps or surge analytics
5. **Migration:** Run the migration to add the field:
   ```bash
   php artisan migrate
   ```

---

## 🚀 Deployment Checklist

- [x] Database migration created
- [x] Entity model updated
- [x] Controller created
- [x] Routes registered
- [x] Dispatch logic updated
- [x] Documentation written
- [ ] Migration applied to production
- [ ] Flutter app updated
- [ ] Admin dashboard shows driver preferences
- [ ] Testing completed

---

## 📊 Monitoring

Check driver preferences in the database:
```sql
SELECT 
    honeycomb_enabled,
    COUNT(*) as driver_count
FROM driver_details
GROUP BY honeycomb_enabled;
```

Track toggle events in logs:
```bash
tail -f storage/logs/laravel.log | grep "Driver toggled honeycomb"
```

---

## 🐛 Troubleshooting

### Issue: Toggle doesn't work
**Solution:** Check if driver_details record exists for the user

### Issue: Still getting trips with Honeycomb disabled
**Solution:** This is expected - disabled only affects Honeycomb dispatch, standard dispatch still works

### Issue: Migration fails
**Solution:** Ensure driver_details table exists and no conflicting columns

---

## 📞 Support

For integration help or issues, contact the development team.
