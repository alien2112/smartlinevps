# 🚗 AI Chatbot Flutter Integration Guide

## Overview

This document explains how to integrate the AI chatbot backend with your Flutter application. The backend sends structured responses with **actions** that the Flutter app should handle.

---

## API Endpoints

### Base URL
```
http://your-server:3000
```

### Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/chat` | Main chat endpoint |
| POST | `/submit-location` | Submit location from map |
| GET | `/action-types` | Get all available actions |
| GET | `/health` | Health check |

---

## Response Structure

Every response from `/chat` follows this structure:

```json
{
  "message": "رحلتك الحالية: الكابتن أحمد...",
  "action": "show_trip_tracking",
  "data": {
    "trip_id": "abc-123",
    "ride": { ... }
  },
  "quick_replies": ["أين الكابتن؟", "إلغاء الرحلة", "اتصل بالكابتن"],
  "ui_hint": "typing_delay",
  "confidence": 0.85,
  "handoff": false,
  "language": "ar"
}
```

---

## Action Types

These are all the actions your Flutter app should handle:

### Trip Booking Flow
| Action | Description | Data Fields |
|--------|-------------|-------------|
| `request_pickup_location` | Open map for pickup selection | `map_type: "pickup"` |
| `request_destination` | Open map for destination | `map_type: "destination"`, `pickup: {...}` |
| `show_ride_options` | Show ride type selection | `pickup`, `destination` |
| `show_fare_estimate` | Show fare before confirming | `pickup`, `destination`, `ride_type`, `estimated_fare` |
| `confirm_booking` | Confirm and create trip | All trip data |

### Trip Tracking
| Action | Description | Data Fields |
|--------|-------------|-------------|
| `show_trip_tracking` | Navigate to tracking screen | `trip_id`, `ride` object |
| `show_driver_info` | Show driver details card | `trip_id`, `driver` object |

### Trip Actions
| Action | Description | Data Fields |
|--------|-------------|-------------|
| `cancel_trip` | Cancel the trip | `trip_id` |
| `confirm_cancel_trip` | Show cancel confirmation | `trip_id`, `cancellation_fee` |
| `contact_driver` | Open call/message to driver | `trip_id`, `phone`, `options: ["call", "message"]` |

### History & Support
| Action | Description | Data Fields |
|--------|-------------|-------------|
| `show_trip_history` | Navigate to history | - |
| `show_trip_details` | Show specific trip | `trip_id`, `trip` object |
| `rate_trip` | Open rating modal | `trip_id` |

### Safety
| Action | Description | Data Fields |
|--------|-------------|-------------|
| `trigger_emergency` | Trigger SOS | `trip_id` (optional) |
| `share_live_location` | Share location | `trip_id` (optional) |

### Human Handoff
| Action | Description | Data Fields |
|--------|-------------|-------------|
| `connect_support` | Connect to human agent | `reason`, `trip_id` |
| `call_support` | Open call to support | - |

### Other
| Action | Description |
|--------|-------------|
| `none` | Just display the message |
| `show_quick_replies` | Show quick reply buttons |

---

## Flutter Implementation

### 1. Create Chat Service

```dart
// lib/services/ai_chat_service.dart

import 'dart:convert';
import 'package:http/http.dart' as http;

class AIChatService {
  static const String baseUrl = 'http://your-server:3000';
  
  /// Send a chat message
  Future<ChatResponse> sendMessage(String userId, String message, {Map<String, dynamic>? locationData}) async {
    final response = await http.post(
      Uri.parse('$baseUrl/chat'),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({
        'user_id': userId,
        'message': message,
        if (locationData != null) 'location_data': locationData,
      }),
    );
    
    if (response.statusCode == 200) {
      return ChatResponse.fromJson(jsonDecode(response.body));
    } else {
      throw Exception('Failed to send message');
    }
  }
  
  /// Submit location from map
  Future<ChatResponse> submitLocation(String userId, double lat, double lng, String? address, String type) async {
    final response = await http.post(
      Uri.parse('$baseUrl/submit-location'),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({
        'user_id': userId,
        'lat': lat,
        'lng': lng,
        'address': address,
        'type': type, // 'pickup' or 'destination'
      }),
    );
    
    if (response.statusCode == 200) {
      return ChatResponse.fromJson(jsonDecode(response.body));
    } else {
      throw Exception('Failed to submit location');
    }
  }
}
```

### 2. Create Response Models

```dart
// lib/models/chat_response.dart

class ChatResponse {
  final String message;
  final String action;
  final Map<String, dynamic> data;
  final List<String> quickReplies;
  final String? uiHint;
  final double confidence;
  final bool handoff;
  final String language;

  ChatResponse({
    required this.message,
    required this.action,
    required this.data,
    required this.quickReplies,
    this.uiHint,
    required this.confidence,
    required this.handoff,
    required this.language,
  });

  factory ChatResponse.fromJson(Map<String, dynamic> json) {
    return ChatResponse(
      message: json['message'] ?? '',
      action: json['action'] ?? 'none',
      data: json['data'] ?? {},
      quickReplies: List<String>.from(json['quick_replies'] ?? []),
      uiHint: json['ui_hint'],
      confidence: (json['confidence'] ?? 0).toDouble(),
      handoff: json['handoff'] ?? false,
      language: json['language'] ?? 'ar',
    );
  }
}
```

### 3. Create Action Handler

```dart
// lib/services/action_handler.dart

import 'package:flutter/material.dart';

class ChatActionHandler {
  final BuildContext context;
  final Function(String) onSendMessage; // Callback to send message after action
  
  ChatActionHandler({
    required this.context,
    required this.onSendMessage,
  });

  /// Handle the action from chat response
  Future<void> handleAction(ChatResponse response) async {
    switch (response.action) {
      // ===== TRIP BOOKING =====
      case 'request_pickup_location':
        await _openMapPicker(
          type: 'pickup',
          title: 'اختر نقطة الانطلاق',
        );
        break;
        
      case 'request_destination':
        await _openMapPicker(
          type: 'destination',
          title: 'اختر الوجهة',
        );
        break;
        
      case 'show_ride_options':
        await _showRideOptions(response.data);
        break;
        
      case 'show_fare_estimate':
        await _showFareEstimate(response.data);
        break;
        
      case 'confirm_booking':
        await _confirmBooking(response.data);
        break;
      
      // ===== TRIP TRACKING =====
      case 'show_trip_tracking':
        _navigateToTripTracking(response.data['trip_id']);
        break;
        
      case 'show_driver_info':
        _showDriverInfoSheet(response.data);
        break;
      
      // ===== TRIP ACTIONS =====
      case 'cancel_trip':
        _cancelTrip(response.data['trip_id']);
        break;
        
      case 'confirm_cancel_trip':
        await _showCancelConfirmation(response.data);
        break;
        
      case 'contact_driver':
        await _contactDriver(response.data);
        break;
      
      // ===== HISTORY =====
      case 'show_trip_history':
        _navigateToTripHistory();
        break;
        
      case 'show_trip_details':
        _navigateToTripDetails(response.data['trip_id']);
        break;
        
      case 'rate_trip':
        await _showRatingDialog(response.data['trip_id']);
        break;
      
      // ===== SAFETY =====
      case 'trigger_emergency':
        await _triggerEmergency(response.data);
        break;
        
      case 'share_live_location':
        await _shareLiveLocation(response.data);
        break;
      
      // ===== HUMAN HANDOFF =====
      case 'connect_support':
        _connectToSupport(response.data);
        break;
        
      case 'call_support':
        _callSupport();
        break;
      
      // ===== DEFAULT =====
      case 'none':
      case 'show_quick_replies':
      default:
        // Just display message and quick replies
        break;
    }
  }

  // ==========================================
  // IMPLEMENTATION METHODS
  // ==========================================

  /// Open map picker for location selection
  Future<void> _openMapPicker({required String type, required String title}) async {
    final result = await Navigator.push<LocationResult>(
      context,
      MaterialPageRoute(
        builder: (context) => MapPickerScreen(
          title: title,
          type: type,
        ),
      ),
    );
    
    if (result != null) {
      // Submit location to backend
      final chatService = AIChatService();
      final userId = AuthService.getCurrentUserId();
      
      await chatService.submitLocation(
        userId,
        result.latitude,
        result.longitude,
        result.address,
        type,
      );
    }
  }

  /// Show ride type options
  Future<void> _showRideOptions(Map<String, dynamic> data) async {
    await showModalBottomSheet(
      context: context,
      builder: (context) => RideOptionsSheet(
        pickup: data['pickup'],
        destination: data['destination'],
        onSelect: (rideType) {
          Navigator.pop(context);
          onSendMessage(rideType); // e.g., "اقتصادي"
        },
      ),
    );
  }

  /// Show fare estimate with confirm/cancel
  Future<void> _showFareEstimate(Map<String, dynamic> data) async {
    await showModalBottomSheet(
      context: context,
      builder: (context) => FareEstimateSheet(
        pickup: data['pickup'],
        destination: data['destination'],
        rideType: data['ride_type'],
        estimatedFare: data['estimated_fare'],
        currency: data['currency'] ?? 'SAR',
        onConfirm: () {
          Navigator.pop(context);
          onSendMessage('تأكيد الحجز');
        },
        onCancel: () {
          Navigator.pop(context);
          onSendMessage('إلغاء');
        },
      ),
    );
  }

  /// Navigate to trip tracking screen
  void _navigateToTripTracking(String tripId) {
    Navigator.pushNamed(
      context,
      '/trip-tracking',
      arguments: {'trip_id': tripId},
    );
  }

  /// Show driver info bottom sheet
  void _showDriverInfoSheet(Map<String, dynamic> data) {
    showModalBottomSheet(
      context: context,
      builder: (context) => DriverInfoSheet(
        driver: data['driver'],
        tripId: data['trip_id'],
      ),
    );
  }

  /// Show cancel confirmation dialog
  Future<void> _showCancelConfirmation(Map<String, dynamic> data) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('إلغاء الرحلة'),
        content: Text(
          data['cancellation_fee'] > 0
            ? 'سيتم تطبيق رسوم إلغاء بقيمة ${data['cancellation_fee']} ${data['currency'] ?? 'SAR'}'
            : 'هل أنت متأكد من إلغاء الرحلة؟'
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text('لا'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text('نعم، إلغاء'),
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
          ),
        ],
      ),
    );
    
    if (confirmed == true) {
      onSendMessage('نعم، إلغاء');
    } else {
      onSendMessage('لا، استمر');
    }
  }

  /// Contact driver
  Future<void> _contactDriver(Map<String, dynamic> data) async {
    final phone = data['phone'];
    final options = List<String>.from(data['options'] ?? ['call']);
    
    if (options.length > 1) {
      final choice = await showModalBottomSheet<String>(
        context: context,
        builder: (context) => Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: Icon(Icons.phone),
              title: Text('اتصال'),
              onTap: () => Navigator.pop(context, 'call'),
            ),
            ListTile(
              leading: Icon(Icons.message),
              title: Text('رسالة'),
              onTap: () => Navigator.pop(context, 'message'),
            ),
          ],
        ),
      );
      
      if (choice == 'call') {
        _makePhoneCall(phone);
      } else if (choice == 'message') {
        _navigateToChat(data['trip_id']);
      }
    } else {
      _makePhoneCall(phone);
    }
  }

  void _makePhoneCall(String phone) {
    // Use url_launcher
    // launchUrl(Uri.parse('tel:$phone'));
  }

  void _navigateToChat(String tripId) {
    Navigator.pushNamed(context, '/driver-chat', arguments: {'trip_id': tripId});
  }

  void _navigateToTripHistory() {
    Navigator.pushNamed(context, '/trip-history');
  }

  void _navigateToTripDetails(String tripId) {
    Navigator.pushNamed(context, '/trip-details', arguments: {'trip_id': tripId});
  }

  Future<void> _showRatingDialog(String tripId) async {
    // Show rating dialog
    await showDialog(
      context: context,
      builder: (context) => RatingDialog(tripId: tripId),
    );
  }

  Future<void> _triggerEmergency(Map<String, dynamic> data) async {
    // Show SOS screen
    Navigator.pushNamed(context, '/emergency', arguments: data);
  }

  Future<void> _shareLiveLocation(Map<String, dynamic> data) async {
    // Share location
  }

  void _connectToSupport(Map<String, dynamic> data) {
    Navigator.pushNamed(context, '/live-support', arguments: data);
  }

  void _callSupport() {
    // Call support number
    // launchUrl(Uri.parse('tel:+966XXXXXXXX'));
  }

  void _cancelTrip(String tripId) {
    // API call to cancel trip
  }

  Future<void> _confirmBooking(Map<String, dynamic> data) async {
    // Create the trip via your existing trip booking API
    // Then navigate to tracking screen
  }
}
```

### 4. Create Chat Screen

```dart
// lib/screens/ai_chat_screen.dart

import 'package:flutter/material.dart';

class AIChatScreen extends StatefulWidget {
  @override
  _AIChatScreenState createState() => _AIChatScreenState();
}

class _AIChatScreenState extends State<AIChatScreen> {
  final AIChatService _chatService = AIChatService();
  final TextEditingController _messageController = TextEditingController();
  final List<ChatMessage> _messages = [];
  List<String> _quickReplies = [];
  bool _isLoading = false;
  late ChatActionHandler _actionHandler;
  
  @override
  void initState() {
    super.initState();
    _actionHandler = ChatActionHandler(
      context: context,
      onSendMessage: _sendMessage,
    );
    
    // Send initial greeting
    _sendMessage('مرحبا');
  }

  Future<void> _sendMessage(String message) async {
    if (message.trim().isEmpty) return;
    
    setState(() {
      _messages.add(ChatMessage(
        text: message,
        isUser: true,
        timestamp: DateTime.now(),
      ));
      _isLoading = true;
      _quickReplies = [];
    });
    
    _messageController.clear();
    
    try {
      final userId = AuthService.getCurrentUserId();
      final response = await _chatService.sendMessage(userId, message);
      
      setState(() {
        _messages.add(ChatMessage(
          text: response.message,
          isUser: false,
          timestamp: DateTime.now(),
          action: response.action,
          data: response.data,
        ));
        _quickReplies = response.quickReplies;
        _isLoading = false;
      });
      
      // Handle the action
      if (response.action != 'none' && response.action != 'show_quick_replies') {
        await _actionHandler.handleAction(response);
      }
      
    } catch (e) {
      setState(() {
        _messages.add(ChatMessage(
          text: 'عذراً، حدث خطأ. حاول مرة أخرى.',
          isUser: false,
          timestamp: DateTime.now(),
        ));
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            CircleAvatar(
              backgroundColor: Colors.purple.shade100,
              child: Icon(Icons.support_agent, color: Colors.purple),
            ),
            SizedBox(width: 12),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('AI Assistant', style: TextStyle(fontSize: 16)),
                Text('Online', style: TextStyle(fontSize: 12, color: Colors.green)),
              ],
            ),
          ],
        ),
      ),
      body: Column(
        children: [
          // Messages list
          Expanded(
            child: ListView.builder(
              padding: EdgeInsets.all(16),
              itemCount: _messages.length,
              itemBuilder: (context, index) {
                final message = _messages[index];
                return ChatBubble(message: message);
              },
            ),
          ),
          
          // Loading indicator
          if (_isLoading)
            Padding(
              padding: EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: Row(
                children: [
                  SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  ),
                  SizedBox(width: 12),
                  Text('جاري الكتابة...', style: TextStyle(color: Colors.grey)),
                ],
              ),
            ),
          
          // Quick replies
          if (_quickReplies.isNotEmpty)
            Container(
              height: 50,
              padding: EdgeInsets.symmetric(horizontal: 8),
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: _quickReplies.length,
                separatorBuilder: (_, __) => SizedBox(width: 8),
                itemBuilder: (context, index) {
                  return ActionChip(
                    label: Text(_quickReplies[index]),
                    onPressed: () => _sendMessage(_quickReplies[index]),
                    backgroundColor: Colors.purple.shade50,
                  );
                },
              ),
            ),
          
          // Input field
          Container(
            padding: EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: Colors.white,
              boxShadow: [
                BoxShadow(
                  color: Colors.black12,
                  blurRadius: 4,
                  offset: Offset(0, -2),
                ),
              ],
            ),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _messageController,
                    textDirection: TextDirection.rtl,
                    decoration: InputDecoration(
                      hintText: 'اكتب رسالتك...',
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(25),
                        borderSide: BorderSide.none,
                      ),
                      filled: true,
                      fillColor: Colors.grey.shade100,
                      contentPadding: EdgeInsets.symmetric(horizontal: 20, vertical: 10),
                    ),
                    onSubmitted: _sendMessage,
                  ),
                ),
                SizedBox(width: 8),
                CircleAvatar(
                  backgroundColor: Colors.purple,
                  child: IconButton(
                    icon: Icon(Icons.send, color: Colors.white),
                    onPressed: () => _sendMessage(_messageController.text),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

// Chat message model
class ChatMessage {
  final String text;
  final bool isUser;
  final DateTime timestamp;
  final String? action;
  final Map<String, dynamic>? data;

  ChatMessage({
    required this.text,
    required this.isUser,
    required this.timestamp,
    this.action,
    this.data,
  });
}

// Chat bubble widget
class ChatBubble extends StatelessWidget {
  final ChatMessage message;

  const ChatBubble({required this.message});

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: message.isUser ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: EdgeInsets.symmetric(vertical: 4),
        padding: EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.75),
        decoration: BoxDecoration(
          color: message.isUser ? Colors.purple : Colors.grey.shade200,
          borderRadius: BorderRadius.circular(20),
        ),
        child: Text(
          message.text,
          style: TextStyle(
            color: message.isUser ? Colors.white : Colors.black,
          ),
          textDirection: TextDirection.rtl,
        ),
      ),
    );
  }
}
```

---

## Complete Flow Example

### Booking a Trip

1. **User**: "أريد حجز رحلة"
2. **Backend Response**:
   ```json
   {
     "message": "🚗 سوف أحجز رحلة لك.\nمن أي مكان تريد أن تبدأ الرحلة؟",
     "action": "request_pickup_location",
     "data": { "map_type": "pickup" },
     "quick_replies": []
   }
   ```
3. **Flutter**: Opens map picker
4. **User**: Selects location
5. **Flutter**: Calls `submitLocation(userId, lat, lng, address, 'pickup')`
6. **Backend Response**:
   ```json
   {
     "message": "✅ تم تحديد نقطة الانطلاق.\nإلى أين تريد الذهاب؟",
     "action": "request_destination",
     "data": { "map_type": "destination", "pickup": {...} }
   }
   ```
7. **Flutter**: Opens map picker for destination
8. **User**: Selects destination
9. **Flutter**: Calls `submitLocation(userId, lat, lng, address, 'destination')`
10. **Backend Response**:
    ```json
    {
      "message": "✅ ممتاز! اختر نوع الرحلة...",
      "action": "show_ride_options",
      "data": { "pickup": {...}, "destination": {...} },
      "quick_replies": ["اقتصادي", "مميز", "عائلي"]
    }
    ```
...and so on.

---

## Best Practices

1. **Always handle all action types** - Even if just logging unknown actions
2. **Show loading states** - Display typing indicator while waiting for response
3. **Handle errors gracefully** - Network errors should show user-friendly messages
4. **Cache user ID** - Don't regenerate on every message
5. **Support RTL** - Arabic text and UI should be right-to-left
6. **Quick replies** - Make them easily tappable for better UX

---

## Questions?

Contact the backend team for any API issues or feature requests.
