# Flutter Driver App - Socket Integration Guide

## Overview

This document provides the contract for integrating the new socket events in the Flutter driver app to fix the "second press works" bug.

**Important**: The backend changes are complete. The driver app needs to listen for these new events.

---

## New Socket Events to Handle

### 1. `trip:accepted:confirmed` (CRITICAL)

This event is emitted **immediately** when the driver's accept request is processed by the server. The driver app should use this event to update the UI, not just the HTTP response.

```dart
// Listen for this event
socket.on('trip:accepted:confirmed', (data) {
  final rideId = data['rideId'];
  final tripId = data['tripId'];
  final status = data['status']; // 'accepted'
  final otp = data['otp'];
  final trip = data['trip']; // Full trip details
  final traceId = data['trace_id'];
  
  // Update UI immediately
  // This should happen before or at same time as HTTP response
  print('[Socket] Trip accepted confirmed: $rideId');
  
  // Update local state
  tripProvider.updateTripStatus(rideId, 'accepted');
  tripProvider.setCurrentTrip(trip);
  
  // Navigate to accepted trip screen
  Navigator.pushNamed(context, '/trip-accepted', arguments: trip);
});
```

### 2. `trip:otp:verified` (CRITICAL)

This event confirms OTP verification was successful and trip is now ongoing.

```dart
socket.on('trip:otp:verified', (data) {
  final rideId = data['rideId'];
  final status = data['status']; // 'ongoing'
  final traceId = data['trace_id'];
  
  print('[Socket] OTP verified, trip ongoing: $rideId');
  
  // Update UI to show ongoing trip
  tripProvider.updateTripStatus(rideId, 'ongoing');
  
  // Navigate to ongoing trip screen
  Navigator.pushReplacementNamed(context, '/trip-ongoing');
});
```

### 3. `trip:arrival:confirmed`

Confirms driver arrival was recorded.

```dart
socket.on('trip:arrival:confirmed', (data) {
  final rideId = data['rideId'];
  print('[Socket] Arrival confirmed: $rideId');
  
  // Show confirmation toast
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(content: Text('Arrival confirmed'))
  );
});
```

### 4. `ride:taken` (Already exists, but important)

Another driver accepted the ride. Stop showing it as available.

```dart
socket.on('ride:taken', (data) {
  final rideId = data['rideId'];
  print('[Socket] Ride taken by another driver: $rideId');
  
  // Remove from available rides list
  ridesProvider.removeRide(rideId);
  
  // If driver was viewing this ride, show message
  if (currentViewingRide?.id == rideId) {
    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        title: Text('Ride No Longer Available'),
        content: Text('Another driver accepted this ride.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text('OK')
          )
        ]
      )
    );
  }
});
```

---

## Hard Rules for Driver App

### ✅ DO

1. **Listen for socket events in parallel with HTTP requests**
   - Don't disable socket listeners when making API calls
   - The socket event may arrive before or after the HTTP response

2. **Update UI from BOTH socket and HTTP response**
   - Whichever arrives first should update the UI
   - Use a flag to prevent double-updates:
   
   ```dart
   bool _hasReceivedAcceptConfirmation = false;
   
   Future<void> acceptRide(String rideId) async {
     _hasReceivedAcceptConfirmation = false;
     
     // Start listening for socket confirmation
     socket.once('trip:accepted:confirmed', (data) {
       if (data['rideId'] == rideId && !_hasReceivedAcceptConfirmation) {
         _hasReceivedAcceptConfirmation = true;
         _onTripAccepted(data);
       }
     });
     
     // Make HTTP request
     try {
       final response = await api.acceptRide(rideId);
       if (!_hasReceivedAcceptConfirmation) {
         _hasReceivedAcceptConfirmation = true;
         _onTripAccepted(response.data);
       }
     } catch (e) {
       // Handle error
     }
   }
   ```

3. **Disable Accept button immediately on tap**
   - Prevent double-taps
   - Re-enable only if server returns error

4. **Include trace_id in API requests**
   - Send `X-Trace-Id` header for debugging:
   
   ```dart
   dio.options.headers['X-Trace-Id'] = 'trc_${DateTime.now().millisecondsSinceEpoch}_${Random().nextInt(99999)}';
   ```

### ❌ DON'T

1. **Don't rely solely on HTTP response for state updates**
   - Socket events are more reliable for real-time updates

2. **Don't ignore FCM when socket is connected**
   - FCM is a backup, but socket is primary

3. **Don't poll for ride status**
   - Use socket events, not `GET /ride/{id}` polling

4. **Don't trust local state over server events**
   - If socket says "ride taken", remove it from list even if local state says "available"

---

## Recommended Socket Event Registration

Register all events once when socket connects:

```dart
class SocketService {
  late IO.Socket socket;
  
  void connect(String userId, String token) {
    socket = IO.io('ws://your-server.com', {
      'auth': {'token': token},
      'transports': ['websocket']
    });
    
    socket.onConnect((_) {
      print('Socket connected');
      _registerEventHandlers();
    });
  }
  
  void _registerEventHandlers() {
    // Trip acceptance
    socket.on('trip:accepted:confirmed', _onTripAccepted);
    socket.on('ride:accept:failed', _onAcceptFailed);
    socket.on('ride:taken', _onRideTaken);
    
    // OTP & Trip progress
    socket.on('trip:otp:verified', _onOtpVerified);
    socket.on('trip:arrival:confirmed', _onArrivalConfirmed);
    
    // Ride updates
    socket.on('ride:cancelled', _onRideCancelled);
    socket.on('ride:completed', _onRideCompleted);
    
    // New rides
    socket.on('ride:new', _onNewRide);
  }
  
  void _onTripAccepted(dynamic data) {
    // ... handle
  }
}
```

---

## Debugging

### Check if events are received

Add logging to all socket event handlers:

```dart
socket.onAny((event, data) {
  print('[Socket Event] $event: $data');
});
```

### Verify trace_id correlation

1. Note the `X-Trace-Id` header sent with request
2. Check backend logs for same trace_id
3. Check socket event for same trace_id
4. All three should match for the same action

---

## Migration Steps

1. Add new socket event listeners (see above)
2. Modify accept flow to handle both socket and HTTP
3. Modify OTP flow to handle socket confirmation
4. Test with single press of Accept button
5. Verify no double-updates occur

---

## Questions?

If the socket events are not being received:

1. Check that Redis is running and connected
2. Check Node.js realtime-service logs
3. Verify socket is connected to correct user room (`user:{driverId}`)
4. Check for network/firewall issues blocking WebSocket
