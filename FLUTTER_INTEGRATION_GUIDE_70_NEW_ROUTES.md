# Flutter Integration Guide - 70+ New Driver API Routes

**Date:** January 2, 2026  
**Base URL:** `https://smartline-it.com/api`  
**Authentication:** All routes require `Authorization: Bearer {token}` header

---

## Table of Contents

1. [Setup & Configuration](#setup--configuration)
2. [Authentication](#authentication)
3. [Notifications (9 APIs)](#1-notifications-9-apis)
4. [Support & Help (9 APIs)](#2-support--help-9-apis)
5. [Content Pages (2 APIs)](#3-content-pages-2-apis)
6. [Account Management (13 APIs)](#4-account-management-13-apis)
7. [Dashboard & Activity (3 APIs)](#5-dashboard--activity-3-apis)
8. [Trip Reports (3 APIs)](#6-trip-reports-3-apis)
9. [Vehicle Management (5 APIs)](#7-vehicle-management-5-apis)
10. [Documents (2 APIs)](#8-documents-2-apis)
11. [Gamification (3 APIs)](#9-gamification-3-apis)
12. [Promotions & Offers (3 APIs)](#10-promotions--offers-3-apis)
13. [Readiness Check (1 API)](#11-readiness-check-1-api)
14. [Error Handling](#error-handling)
15. [Flutter Best Practices](#flutter-best-practices)

---

## Setup & Configuration

### 1. Add Dependencies

Add to `pubspec.yaml`:

```yaml
dependencies:
  flutter:
    sdk: flutter
  http: ^1.1.0
  dio: ^5.4.0
  shared_preferences: ^2.2.2
  connectivity_plus: ^5.0.2
```

### 2. Create API Service Class

```dart
import 'package:dio/dio.dart';
import 'package:shared_preferences/shared_preferences.dart';

class ApiService {
  static const String baseUrl = 'https://smartline-it.com/api';
  late Dio _dio;
  
  ApiService() {
    _dio = Dio(BaseOptions(
      baseUrl: baseUrl,
      connectTimeout: const Duration(seconds: 30),
      receiveTimeout: const Duration(seconds: 30),
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    ));
    
    // Add interceptor for auth token
    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        final prefs = await SharedPreferences.getInstance();
        final token = prefs.getString('auth_token');
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        return handler.next(options);
      },
      onError: (error, handler) {
        // Handle 401 unauthorized
        if (error.response?.statusCode == 401) {
          // Redirect to login
        }
        return handler.next(error);
      },
    ));
  }
  
  // Generic GET request
  Future<Response> get(String path, {Map<String, dynamic>? queryParameters}) async {
    try {
      return await _dio.get(path, queryParameters: queryParameters);
    } catch (e) {
      rethrow;
    }
  }
  
  // Generic POST request
  Future<Response> post(String path, {dynamic data}) async {
    try {
      return await _dio.post(path, data: data);
    } catch (e) {
      rethrow;
    }
  }
  
  // Generic PUT request
  Future<Response> put(String path, {dynamic data}) async {
    try {
      return await _dio.put(path, data: data);
    } catch (e) {
      rethrow;
    }
  }
  
  // Generic DELETE request
  Future<Response> delete(String path) async {
    try {
      return await _dio.delete(path);
    } catch (e) {
      rethrow;
    }
  }
}
```

### 3. Create Response Models

```dart
class ApiResponse<T> {
  final String? status;
  final String? message;
  final T? data;
  final Map<String, dynamic>? errors;
  
  ApiResponse({
    this.status,
    this.message,
    this.data,
    this.errors,
  });
  
  factory ApiResponse.fromJson(Map<String, dynamic> json, T Function(dynamic)? fromJsonT) {
    return ApiResponse<T>(
      status: json['status'],
      message: json['message'],
      data: json['data'] != null && fromJsonT != null ? fromJsonT(json['data']) : json['data'],
      errors: json['errors'],
    );
  }
  
  bool get isSuccess => status == 'success';
}
```

---

## Authentication

All routes require authentication token in the header:

```dart
headers: {
  'Authorization': 'Bearer $token',
  'Content-Type': 'application/json',
  'Accept': 'application/json',
}
```

---

## 1. Notifications (9 APIs)

### 1.1 Get All Notifications

**Endpoint:** `GET /api/driver/auth/notifications`

**Flutter Implementation:**

```dart
class NotificationService {
  final ApiService _api = ApiService();
  
  Future<List<NotificationModel>> getNotifications({
    int? page,
    int? limit,
  }) async {
    final response = await _api.get(
      '/driver/auth/notifications',
      queryParameters: {
        if (page != null) 'page': page,
        if (limit != null) 'limit': limit,
      },
    );
    
    final apiResponse = ApiResponse.fromJson(
      response.data,
      (data) => (data as List).map((e) => NotificationModel.fromJson(e)).toList(),
    );
    
    if (apiResponse.isSuccess) {
      return apiResponse.data ?? [];
    }
    throw Exception(apiResponse.message ?? 'Failed to fetch notifications');
  }
}
```

**Response Model:**

```dart
class NotificationModel {
  final String id;
  final String title;
  final String? body;
  final String type;
  final bool isRead;
  final DateTime createdAt;
  final Map<String, dynamic>? data;
  
  NotificationModel({
    required this.id,
    required this.title,
    this.body,
    required this.type,
    required this.isRead,
    required this.createdAt,
    this.data,
  });
  
  factory NotificationModel.fromJson(Map<String, dynamic> json) {
    return NotificationModel(
      id: json['id'] ?? '',
      title: json['title'] ?? '',
      body: json['body'],
      type: json['type'] ?? '',
      isRead: json['is_read'] ?? false,
      createdAt: DateTime.parse(json['created_at'] ?? DateTime.now().toIso8601String()),
      data: json['data'],
    );
  }
}
```

### 1.2 Get Unread Count

**Endpoint:** `GET /api/driver/auth/notifications/unread-count`

```dart
Future<int> getUnreadCount() async {
  final response = await _api.get('/driver/auth/notifications/unread-count');
  final apiResponse = ApiResponse.fromJson(response.data, null);
  
  if (apiResponse.isSuccess && apiResponse.data != null) {
    return apiResponse.data['unread_count'] ?? 0;
  }
  return 0;
}
```

### 1.3 Mark Notification as Read

**Endpoint:** `POST /api/driver/auth/notifications/{id}/read`

```dart
Future<bool> markAsRead(String notificationId) async {
  final response = await _api.post('/driver/auth/notifications/$notificationId/read');
  final apiResponse = ApiResponse.fromJson(response.data, null);
  return apiResponse.isSuccess;
}
```

### 1.4 Mark Notification as Unread

**Endpoint:** `POST /api/driver/auth/notifications/{id}/unread`

```dart
Future<bool> markAsUnread(String notificationId) async {
  final response = await _api.post('/driver/auth/notifications/$notificationId/unread');
  final apiResponse = ApiResponse.fromJson(response.data, null);
  return apiResponse.isSuccess;
}
```

### 1.5 Mark All as Read

**Endpoint:** `POST /api/driver/auth/notifications/read-all`

```dart
Future<bool> markAllAsRead() async {
  final response = await _api.post('/driver/auth/notifications/read-all');
  final apiResponse = ApiResponse.fromJson(response.data, null);
  return apiResponse.isSuccess;
}
```

### 1.6 Delete Notification

**Endpoint:** `DELETE /api/driver/auth/notifications/{id}`

```dart
Future<bool> deleteNotification(String notificationId) async {
  final response = await _api.delete('/driver/auth/notifications/$notificationId');
  final apiResponse = ApiResponse.fromJson(response.data, null);
  return apiResponse.isSuccess;
}
```

### 1.7 Clear Read Notifications

**Endpoint:** `POST /api/driver/auth/notifications/clear-read`

```dart
Future<bool> clearReadNotifications() async {
  final response = await _api.post('/driver/auth/notifications/clear-read');
  final apiResponse = ApiResponse.fromJson(response.data, null);
  return apiResponse.isSuccess;
}
```

### 1.8 Get Notification Settings

**Endpoint:** `GET /api/driver/auth/notifications/settings`

```dart
Future<NotificationSettings> getNotificationSettings() async {
  final response = await _api.get('/driver/auth/notifications/settings');
  final apiResponse = ApiResponse.fromJson(
    response.data,
    (data) => NotificationSettings.fromJson(data),
  );
  
  if (apiResponse.isSuccess) {
    return apiResponse.data!;
  }
  throw Exception(apiResponse.message ?? 'Failed to fetch settings');
}
```

**Settings Model:**

```dart
class NotificationSettings {
  final bool pushEnabled;
  final bool emailEnabled;
  final bool smsEnabled;
  final bool tripUpdates;
  final bool promotions;
  final bool earnings;
  final bool support;
  
  NotificationSettings({
    required this.pushEnabled,
    required this.emailEnabled,
    required this.smsEnabled,
    required this.tripUpdates,
    required this.promotions,
    required this.earnings,
    required this.support,
  });
  
  factory NotificationSettings.fromJson(Map<String, dynamic> json) {
    return NotificationSettings(
      pushEnabled: json['push_enabled'] ?? true,
      emailEnabled: json['email_enabled'] ?? false,
      smsEnabled: json['sms_enabled'] ?? false,
      tripUpdates: json['trip_updates'] ?? true,
      promotions: json['promotions'] ?? true,
      earnings: json['earnings'] ?? true,
      support: json['support'] ?? true,
    );
  }
  
  Map<String, dynamic> toJson() {
    return {
      'push_enabled': pushEnabled,
      'email_enabled': emailEnabled,
      'sms_enabled': smsEnabled,
      'trip_updates': tripUpdates,
      'promotions': promotions,
      'earnings': earnings,
      'support': support,
    };
  }
}
```

### 1.9 Update Notification Settings

**Endpoint:** `PUT /api/driver/auth/notifications/settings`

```dart
Future<bool> updateNotificationSettings(NotificationSettings settings) async {
  final response = await _api.put(
    '/driver/auth/notifications/settings',
    data: settings.toJson(),
  );
  final apiResponse = ApiResponse.fromJson(response.data, null);
  return apiResponse.isSuccess;
}
```

---

## 2. Support & Help (9 APIs)

### 2.1 Get FAQs

**Endpoint:** `GET /api/driver/auth/support/faqs`

```dart
class SupportService {
  final ApiService _api = ApiService();
  
  Future<List<FAQModel>> getFAQs() async {
    final response = await _api.get('/driver/auth/support/faqs');
    final apiResponse = ApiResponse.fromJson(
      response.data,
      (data) => (data as List).map((e) => FAQModel.fromJson(e)).toList(),
    );
    
    if (apiResponse.isSuccess) {
      return apiResponse.data ?? [];
    }
    throw Exception(apiResponse.message ?? 'Failed to fetch FAQs');
  }
}

class FAQModel {
  final String id;
  final String question;
  final String answer;
  final String? category;
  final int helpfulCount;
  final int notHelpfulCount;
  
  FAQModel({
    required this.id,
    required this.question,
    required this.answer,
    this.category,
    required this.helpfulCount,
    required this.notHelpfulCount,
  });
  
  factory FAQModel.fromJson(Map<String, dynamic> json) {
    return FAQModel(
      id: json['id'] ?? '',
      question: json['question'] ?? '',
      answer: json['answer'] ?? '',
      category: json['category'],
      helpfulCount: json['helpful_count'] ?? 0,
      notHelpfulCount: json['not_helpful_count'] ?? 0,
    );
  }
}
```

### 2.2 FAQ Feedback

**Endpoint:** `POST /api/driver/auth/support/faqs/{id}/feedback`

```dart
Future<bool> submitFAQFeedback(String faqId, bool isHelpful) async {
  final response = await _api.post(
    '/driver/auth/support/faqs/$faqId/feedback',
    data: {
      'is_helpful': isHelpful,
    },
  );
  final apiResponse = ApiResponse.fromJson(response.data, null);
  return apiResponse.isSuccess;
}
```

### 2.3 Get Support Tickets

**Endpoint:** `GET /api/driver/auth/support/tickets`

```dart
Future<List<SupportTicketModel>> getTickets({
  String? status,
  int? page,
  int? limit,
}) async {
  final response = await _api.get(
    '/driver/auth/support/tickets',
    queryParameters: {
      if (status != null) 'status': status,
      if (page != null) 'page': page,
      if (limit != null) 'limit': limit,
    },
  );
  
  final apiResponse = ApiResponse.fromJson(
    response.data,
    (data) => (data as List).map((e) => SupportTicketModel.fromJson(e)).toList(),
  );
  
  if (apiResponse.isSuccess) {
    return apiResponse.data ?? [];
  }
  throw Exception(apiResponse.message ?? 'Failed to fetch tickets');
}
```

**Ticket Model:**

```dart
class SupportTicketModel {
  final String id;
  final String subject;
  final String description;
  final String status; // open, in_progress, resolved, closed
  final String priority; // low, medium, high, urgent
  final DateTime createdAt;
  final DateTime? updatedAt;
  final List<TicketReplyModel>? replies;
  
  SupportTicketModel({
    required this.id,
    required this.subject,
    required this.description,
    required this.status,
    required this.priority,
    required this.createdAt,
    this.updatedAt,
    this.replies,
  });
  
  factory SupportTicketModel.fromJson(Map<String, dynamic> json) {
    return SupportTicketModel(
      id: json['id'] ?? '',
      subject: json['subject'] ?? '',
      description: json['description'] ?? '',
      status: json['status'] ?? 'open',
      priority: json['priority'] ?? 'medium',
      createdAt: DateTime.parse(json['created_at'] ?? DateTime.now().toIso8601String()),
      updatedAt: json['updated_at'] != null ? DateTime.parse(json['updated_at']) : null,
      replies: json['replies'] != null
          ? (json['replies'] as List).map((e) => TicketReplyModel.fromJson(e)).toList()
          : null,
    );
  }
}

class TicketReplyModel {
  final String id;
  final String message;
  final bool isFromDriver;
  final DateTime createdAt;
  
  TicketReplyModel({
    required this.id,
    required this.message,
    required this.isFromDriver,
    required this.createdAt,
  });
  
  factory TicketReplyModel.fromJson(Map<String, dynamic> json) {
    return TicketReplyModel(
      id: json['id'] ?? '',
      message: json['message'] ?? '',
      isFromDriver: json['is_from_driver'] ?? true,
      createdAt: DateTime.parse(json['created_at'] ?? DateTime.now().toIso8601String()),
    );
  }
}
```

### 2.4 Create Support Ticket

**Endpoint:** `POST /api/driver/auth/support/tickets`

```dart
Future<SupportTicketModel> createTicket({
  required String subject,
  required String description,
  String priority = 'medium',
  String? category,
}) async {
  final response = await _api.post(
    '/driver/auth/support/tickets',
    data: {
      'subject': subject,
      'description': description,
      'priority': priority,
      if (category != null) 'category': category,
    },
  );
  
  final apiResponse = ApiResponse.fromJson(
    response.data,
    (data) => SupportTicketModel.fromJson(data),
  );
  
  if (apiResponse.isSuccess) {
    return apiResponse.data!;
  }
  throw Exception(apiResponse.message ?? 'Failed to create ticket');
}
```

### 2.5 Get Ticket Details

**Endpoint:** `GET /api/driver/auth/support/tickets/{id}`

```dart
Future<SupportTicketModel> getTicketDetails(String ticketId) async {
  final response = await _api.get('/driver/auth/support/tickets/$ticketId');
  final apiResponse = ApiResponse.fromJson(
    response.data,
    (data) => SupportTicketModel.fromJson(data),
  );
  
  if (apiResponse.isSuccess) {
    return apiResponse.data!;
  }
  throw Exception(apiResponse.message ?? 'Failed to fetch ticket details');
}
```

### 2.6 Reply to Ticket

**Endpoint:** `POST /api/driver/auth/support/tickets/{id}/reply`

```dart
Future<TicketReplyModel> replyToTicket(String ticketId, String message) async {
  final response = await _api.post(
    '/driver/auth/support/tickets/$ticketId/reply',
    data: {
      'message': message,
    },
  );
  
  final apiResponse = ApiResponse.fromJson(
    response.data,
    (data) => TicketReplyModel.fromJson(data),
  );
  
  if (apiResponse.isSuccess) {
    return apiResponse.data!;
  }
  throw Exception(apiResponse.message ?? 'Failed to reply to ticket');
}
```

### 2.7 Rate Support

**Endpoint:** `POST /api/driver/auth/support/tickets/{id}/rate`

```dart
Future<bool> rateTicket(String ticketId, int rating, {String? comment}) async {
  final response = await _api.post(
    '/driver/auth/support/tickets/$ticketId/rate',
    data: {
      'rating': rating, // 1-5
      if (comment != null) 'comment': comment,
    },
  );
  final apiResponse = ApiResponse.fromJson(response.data, null);
  return apiResponse.isSuccess;
}
```

### 2.8 Submit Feedback

**Endpoint:** `POST /api/driver/auth/support/feedback`

```dart
Future<bool> submitFeedback({
  required String feedback,
  String? category,
  int? rating,
}) async {
  final response = await _api.post(
    '/driver/auth/support/feedback',
    data: {
      'feedback': feedback,
      if (category != null) 'category': category,
      if (rating != null) 'rating': rating,
    },
  );
  final apiResponse = ApiResponse.fromJson(response.data, null);
  return apiResponse.isSuccess;
}
```

### 2.9 Report Issue

**Endpoint:** `POST /api/driver/auth/support/report-issue`

```dart
Future<bool> reportIssue({
  required String issueType,
  required String description,
  String? screenshotUrl,
  Map<String, dynamic>? metadata,
}) async {
  final response = await _api.post(
    '/driver/auth/support/report-issue',
    data: {
      'issue_type': issueType,
      'description': description,
      if (screenshotUrl != null) 'screenshot_url': screenshotUrl,
      if (metadata != null) 'metadata': metadata,
    },
  );
  final apiResponse = ApiResponse.fromJson(response.data, null);
  return apiResponse.isSuccess;
}
```

### 2.10 Get App Info

**Endpoint:** `GET /api/driver/auth/support/app-info`

```dart
Future<AppInfoModel> getAppInfo() async {
  final response = await _api.get('/driver/auth/support/app-info');
  final apiResponse = ApiResponse.fromJson(
    response.data,
    (data) => AppInfoModel.fromJson(data),
  );
  
  if (apiResponse.isSuccess) {
    return apiResponse.data!;
  }
  throw Exception(apiResponse.message ?? 'Failed to fetch app info');
}

class AppInfoModel {
  final String version;
  final String buildNumber;
  final DateTime? lastUpdate;
  final Map<String, dynamic>? features;
  
  AppInfoModel({
    required this.version,
    required this.buildNumber,
    this.lastUpdate,
    this.features,
  });
  
  factory AppInfoModel.fromJson(Map<String, dynamic> json) {
    return AppInfoModel(
      version: json['version'] ?? '',
      buildNumber: json['build_number'] ?? '',
      lastUpdate: json['last_update'] != null
          ? DateTime.parse(json['last_update'])
          : null,
      features: json['features'],
    );
  }
}
```

---

## 3. Content Pages (2 APIs)

### 3.1 Get All Pages

**Endpoint:** `GET /api/driver/auth/pages`

```dart
class ContentPageService {
  final ApiService _api = ApiService();
  
  Future<List<ContentPageModel>> getAllPages() async {
    final response = await _api.get('/driver/auth/pages');
    final apiResponse = ApiResponse.fromJson(
      response.data,
      (data) => (data as List).map((e) => ContentPageModel.fromJson(e)).toList(),
    );
    
    if (apiResponse.isSuccess) {
      return apiResponse.data ?? [];
    }
    throw Exception(apiResponse.message ?? 'Failed to fetch pages');
  }
}

class ContentPageModel {
  final String slug;
  final String title;
  final String content;
  final String? type;
  
  ContentPageModel({
    required this.slug,
    required this.title,
    required this.content,
    this.type,
  });
  
  factory ContentPageModel.fromJson(Map<String, dynamic> json) {
    return ContentPageModel(
      slug: json['slug'] ?? '',
      title: json['title'] ?? '',
      content: json['content'] ?? '',
      type: json['type'],
    );
  }
}
```

### 3.2 Get Page by Slug

**Endpoint:** `GET /api/driver/auth/pages/{slug}`

```dart
Future<ContentPageModel> getPageBySlug(String slug) async {
  final response = await _api.get('/driver/auth/pages/$slug');
  final apiResponse = ApiResponse.fromJson(
    response.data,
    (data) => ContentPageModel.fromJson(data),
  );
  
  if (apiResponse.isSuccess) {
    return apiResponse.data!;
  }
  throw Exception(apiResponse.message ?? 'Failed to fetch page');
}

// Common slugs:
// - 'terms' - Terms & Conditions
// - 'privacy' - Privacy Policy
// - 'about' - About Us
// - 'help' - Help Page
```

---

## 4. Account Management (13 APIs)

### 4.1 Get Privacy Settings

**Endpoint:** `GET /api/driver/auth/account/privacy-settings`

```dart
class AccountService {
  final ApiService _api = ApiService();
  
  Future<PrivacySettings> getPrivacySettings() async {
    final response = await _api.get('/driver/auth/account/privacy-settings');
    final apiResponse = ApiResponse.fromJson(
      response.data,
      (data) => PrivacySettings.fromJson(data),
    );
    
    if (apiResponse.isSuccess) {
      return apiResponse.data!;
    }
    throw Exception(apiResponse.message ?? 'Failed to fetch privacy settings');
  }
}

class PrivacySettings {
  final bool showProfilePhoto;
  final bool showPhoneNumber;
  final bool showInLeaderboard;
  final bool shareTripDataForImprovement;
  final bool allowPromotionalContacts;
  final bool dataSharingWithPartners;
  
  PrivacySettings({
    required this.showProfilePhoto,
    required this.showPhoneNumber,
    required this.showInLeaderboard,
    required this.shareTripDataForImprovement,
    required this.allowPromotionalContacts,
    required this.dataSharingWithPartners,
  });
  
  factory PrivacySettings.fromJson(Map<String, dynamic> json) {
    return PrivacySettings(
      showProfilePhoto: json['show_profile_photo'] ?? true,
      showPhoneNumber: json['show_phone_number'] ?? false,
      showInLeaderboard: json['show_in_leaderboard'] ?? true,
      shareTripDataForImprovement: json['share_trip_data_for_improvement'] ?? true,
      allowPromotionalContacts: json['allow_promotional_contacts'] ?? true,
      dataSharingWithPartners: json['data_sharing_with_partners'] ?? false,
    );
  }
  
  Map<String, dynamic> toJson() {
    return {
      'show_profile_photo': showProfilePhoto,
      'show_phone_number': showPhoneNumber,
      'show_in_leaderboard': showInLeaderboard,
      'share_trip_data_for_improvement': shareTripDataForImprovement,
      'allow_promotional_contacts': allowPromotionalContacts,
      'data_sharing_with_partners': dataSharingWithPartners,
    };
  }
}
```

### 4.2 Update Privacy Settings

**Endpoint:** `PUT /api/driver/auth/account/privacy-settings`

```dart
Future<bool> updatePrivacySettings(PrivacySettings settings) async {
  final response = await _api.put(
    '/driver/auth/account/privacy-settings',
    data: settings.toJson(),
  );
  final apiResponse = ApiResponse.fromJson(response.data, null);
  return apiResponse.isSuccess;
}
```

### 4.3 Get Emergency Contacts

**Endpoint:** `GET /api/driver/auth/account/emergency-contacts`

```dart
Future<List<EmergencyContactModel>> getEmergencyContacts() async {
  final response = await _api.get('/driver/auth/account/emergency-contacts');
  final apiResponse = ApiResponse.fromJson(
    response.data,
    (data) => (data as List).map((e) => EmergencyContactModel.fromJson(e)).toList(),
  );
  
  if (apiResponse.isSuccess) {
    return apiResponse.data ?? [];
  }
  throw Exception(apiResponse.message ?? 'Failed to fetch emergency contacts');
}

class EmergencyContactModel {
  final String id;
  final String name;
  final String phone;
  final String? relationship;
  final bool isPrimary;
  
  EmergencyContactModel({
    required this.id,
    required this.name,
    required this.phone,
    this.relationship,
    required this.isPrimary,
  });
  
  factory EmergencyContactModel.fromJson(Map<String, dynamic> json) {
    return EmergencyContactModel(
      id: json['id'] ?? '',
      name: json['name'] ?? '',
      phone: json['phone'] ?? '',
      relationship: json['relationship'],
      isPrimary: json['is_primary'] ?? false,
    );
  }
  
  Map<String, dynamic> toJson() {
    return {
      'name': name,
      'phone': phone,
      if (relationship != null) 'relationship': relationship,
    };
  }
}
```

### 4.4 Create Emergency Contact

**Endpoint:** `POST /api/driver/auth/account/emergency-contacts`

```dart
Future<EmergencyContactModel> createEmergencyContact({
  required String name,
  required String phone,
  String? relationship,
}) async {
  final response = await _api.post(
    '/driver/auth/account/emergency-contacts',
    data: {
      'name': name,
      'phone': phone,
      if (relationship != null) 'relationship': relationship,
    },
  );
  
  final apiResponse = ApiResponse.fromJson(
    response.data,
    (data) => EmergencyContactModel.fromJson(data),
  );
  
  if (apiResponse.isSuccess) {
    return apiResponse.data!;
  }
  throw Exception(apiResponse.message ?? 'Failed to create emergency contact');
}
```

### 4.5 Update Emergency Contact

**Endpoint:** `PUT /api/driver/auth/account/emergency-contacts/{id}`

```dart
Future<EmergencyContactModel> updateEmergencyContact(
  String contactId, {
  String? name,
  String? phone,
  String? relationship,
}) async {
  final response = await _api.put(
    '/driver/auth/account/emergency-contacts/$contactId',
    data: {
      if (name != null) 'name': name,
      if (phone != null) 'phone': phone,
      if (relationship != null) 'relationship': relationship,
    },
  );
  
  final apiResponse = ApiResponse.fromJson(
    response.data,
    (data) => EmergencyContactModel.fromJson(data),
  );
  
  if (apiResponse.isSuccess) {
    return apiResponse.data!;
  }
  throw Exception(apiResponse.message ?? 'Failed to update emergency contact');
}
```

### 4.6 Delete Emergency Contact

**Endpoint:** `DELETE /api/driver/auth/account/emergency-contacts/{id}`

```dart
Future<bool> deleteEmergencyContact(String contactId) async {
  final response = await _api.delete('/driver/auth/account/emergency-contacts/$contactId');
  final apiResponse = ApiResponse.fromJson(response.data, null);
  return apiResponse.isSuccess;
}
```

### 4.7 Set Primary Emergency Contact

**Endpoint:** `POST /api/driver/auth/account/emergency-contacts/{id}/set-primary`

```dart
Future<bool> setPrimaryContact(String contactId) async {
  final response = await _api.post(
    '/driver/auth/account/emergency-contacts/$contactId/set-primary',
  );
  final apiResponse = ApiResponse.fromJson(response.data, null);
  return apiResponse.isSuccess;
}
```

### 4.8 Request Phone Change

**Endpoint:** `POST /api/driver/auth/account/change-phone/request`

```dart
Future<bool> requestPhoneChange(String newPhone) async {
  final response = await _api.post(
    '/driver/auth/account/change-phone/request',
    data: {
      'new_phone': newPhone,
    },
  );
  final apiResponse = ApiResponse.fromJson(response.data, null);
  return apiResponse.isSuccess;
}
```

### 4.9 Verify Old Phone

**Endpoint:** `POST /api/driver/auth/account/change-phone/verify-old`

```dart
Future<bool> verifyOldPhone(String otp) async {
  final response = await _api.post(
    '/driver/auth/account/change-phone/verify-old',
    data: {
      'otp': otp,
    },
  );
  final apiResponse = ApiResponse.fromJson(response.data, null);
  return apiResponse.isSuccess;
}
```

### 4.10 Verify New Phone

**Endpoint:** `POST /api/driver/auth/account/change-phone/verify-new`

```dart
Future<bool> verifyNewPhone(String otp) async {
  final response = await _api.post(
    '/driver/auth/account/change-phone/verify-new',
    data: {
      'otp': otp,
    },
  );
  final apiResponse = ApiResponse.fromJson(response.data, null);
  return apiResponse.isSuccess;
}
```

### 4.11 Request Account Deletion

**Endpoint:** `POST /api/driver/auth/account/delete-request`

```dart
Future<bool> requestAccountDeletion({String? reason}) async {
  final response = await _api.post(
    '/driver/auth/account/delete-request',
    data: {
      if (reason != null) 'reason': reason,
    },
  );
  final apiResponse = ApiResponse.fromJson(response.data, null);
  return apiResponse.isSuccess;
}
```

### 4.12 Cancel Deletion Request

**Endpoint:** `POST /api/driver/auth/account/delete-cancel`

```dart
Future<bool> cancelDeletionRequest() async {
  final response = await _api.post('/driver/auth/account/delete-cancel');
  final apiResponse = ApiResponse.fromJson(response.data, null);
  return apiResponse.isSuccess;
}
```

### 4.13 Get Deletion Status

**Endpoint:** `GET /api/driver/auth/account/delete-status`

```dart
Future<DeletionStatusModel> getDeletionStatus() async {
  final response = await _api.get('/driver/auth/account/delete-status');
  final apiResponse = ApiResponse.fromJson(
    response.data,
    (data) => DeletionStatusModel.fromJson(data),
  );
  
  if (apiResponse.isSuccess) {
    return apiResponse.data!;
  }
  throw Exception(apiResponse.message ?? 'Failed to fetch deletion status');
}

class DeletionStatusModel {
  final bool isPending;
  final DateTime? scheduledDate;
  final String? reason;
  
  DeletionStatusModel({
    required this.isPending,
    this.scheduledDate,
    this.reason,
  });
  
  factory DeletionStatusModel.fromJson(Map<String, dynamic> json) {
    return DeletionStatusModel(
      isPending: json['is_pending'] ?? false,
      scheduledDate: json['scheduled_date'] != null
          ? DateTime.parse(json['scheduled_date'])
          : null,
      reason: json['reason'],
    );
  }
}
```

---

## 5. Dashboard & Activity (3 APIs)

### 5.1 Get Dashboard Widgets

**Endpoint:** `GET /api/driver/auth/dashboard/widgets`

```dart
class DashboardService {
  final ApiService _api = ApiService();
  
  Future<DashboardWidgets> getWidgets() async {
    final response = await _api.get('/driver/auth/dashboard/widgets');
    final apiResponse = ApiResponse.fromJson(
      response.data,
      (data) => DashboardWidgets.fromJson(data),
    );
    
    if (apiResponse.isSuccess) {
      return apiResponse.data!;
    }
    throw Exception(apiResponse.message ?? 'Failed to fetch dashboard widgets');
  }
}

class DashboardWidgets {
  final EarningsSummary todayEarnings;
  final EarningsSummary weeklyEarnings;
  final EarningsSummary monthlyEarnings;
  final int todayTrips;
  final int weeklyTrips;
  final int monthlyTrips;
  final double walletBalance;
  final double withdrawableAmount;
  final double rating;
  final int totalReviews;
  final int activePromotions;
  final List<ReminderModel> reminders;
  
  DashboardWidgets({
    required this.todayEarnings,
    required this.weeklyEarnings,
    required this.monthlyEarnings,
    required this.todayTrips,
    required this.weeklyTrips,
    required this.monthlyTrips,
    required this.walletBalance,
    required this.withdrawableAmount,
    required this.rating,
    required this.totalReviews,
    required this.activePromotions,
    required this.reminders,
  });
  
  factory DashboardWidgets.fromJson(Map<String, dynamic> json) {
    return DashboardWidgets(
      todayEarnings: EarningsSummary.fromJson(json['today_earnings'] ?? {}),
      weeklyEarnings: EarningsSummary.fromJson(json['weekly_earnings'] ?? {}),
      monthlyEarnings: EarningsSummary.fromJson(json['monthly_earnings'] ?? {}),
      todayTrips: json['today_trips'] ?? 0,
      weeklyTrips: json['weekly_trips'] ?? 0,
      monthlyTrips: json['monthly_trips'] ?? 0,
      walletBalance: (json['wallet_balance'] ?? 0).toDouble(),
      withdrawableAmount: (json['withdrawable_amount'] ?? 0).toDouble(),
      rating: (json['rating'] ?? 0).toDouble(),
      totalReviews: json['total_reviews'] ?? 0,
      activePromotions: json['active_promotions'] ?? 0,
      reminders: (json['reminders'] ?? []).map((e) => ReminderModel.fromJson(e)).toList(),
    );
  }
}

class EarningsSummary {
  final double amount;
  final String formattedAmount;
  
  EarningsSummary({
    required this.amount,
    required this.formattedAmount,
  });
  
  factory EarningsSummary.fromJson(Map<String, dynamic> json) {
    return EarningsSummary(
      amount: (json['amount'] ?? 0).toDouble(),
      formattedAmount: json['formatted_amount'] ?? '0.00',
    );
  }
}

class ReminderModel {
  final String type;
  final String title;
  final String message;
  final int daysRemaining;
  
  ReminderModel({
    required this.type,
    required this.title,
    required this.message,
    required this.daysRemaining,
  });
  
  factory ReminderModel.fromJson(Map<String, dynamic> json) {
    return ReminderModel(
      type: json['type'] ?? '',
      title: json['title'] ?? '',
      message: json['message'] ?? '',
      daysRemaining: json['days_remaining'] ?? 0,
    );
  }
}
```

### 5.2 Get Recent Activity

**Endpoint:** `GET /api/driver/auth/dashboard/recent-activity`

```dart
Future<List<ActivityModel>> getRecentActivity({int? limit}) async {
  final response = await _api.get(
    '/driver/auth/dashboard/recent-activity',
    queryParameters: {
      if (limit != null) 'limit': limit,
    },
  );
  
  final apiResponse = ApiResponse.fromJson(
    response.data,
    (data) => (data as List).map((e) => ActivityModel.fromJson(e)).toList(),
  );
  
  if (apiResponse.isSuccess) {
    return apiResponse.data ?? [];
  }
  throw Exception(apiResponse.message ?? 'Failed to fetch recent activity');
}

class ActivityModel {
  final String id;
  final String type; // trip, payment, withdrawal, etc.
  final String title;
  final String? description;
  final DateTime createdAt;
  final Map<String, dynamic>? metadata;
  
  ActivityModel({
    required this.id,
    required this.type,
    required this.title,
    this.description,
    required this.createdAt,
    this.metadata,
  });
  
  factory ActivityModel.fromJson(Map<String, dynamic> json) {
    return ActivityModel(
      id: json['id'] ?? '',
      type: json['type'] ?? '',
      title: json['title'] ?? '',
      description: json['description'],
      createdAt: DateTime.parse(json['created_at'] ?? DateTime.now().toIso8601String()),
      metadata: json['metadata'],
    );
  }
}
```

### 5.3 Get Promotional Banners

**Endpoint:** `GET /api/driver/auth/dashboard/promotional-banners`

```dart
Future<List<PromotionalBannerModel>> getPromotionalBanners() async {
  final response = await _api.get('/driver/auth/dashboard/promotional-banners');
  final apiResponse = ApiResponse.fromJson(
    response.data,
    (data) => (data as List).map((e) => PromotionalBannerModel.fromJson(e)).toList(),
  );
  
  if (apiResponse.isSuccess) {
    return apiResponse.data ?? [];
  }
  throw Exception(apiResponse.message ?? 'Failed to fetch promotional banners');
}

class PromotionalBannerModel {
  final String id;
  final String title;
  final String? description;
  final String? imageUrl;
  final String? actionUrl;
  final DateTime? expiresAt;
  
  PromotionalBannerModel({
    required this.id,
    required this.title,
    this.description,
    this.imageUrl,
    this.actionUrl,
    this.expiresAt,
  });
  
  factory PromotionalBannerModel.fromJson(Map<String, dynamic> json) {
    return PromotionalBannerModel(
      id: json['id'] ?? '',
      title: json['title'] ?? '',
      description: json['description'],
      imageUrl: json['image_url'],
      actionUrl: json['action_url'],
      expiresAt: json['expires_at'] != null
          ? DateTime.parse(json['expires_at'])
          : null,
    );
  }
}
```

---

## 6. Trip Reports (3 APIs)

### 6.1 Get Weekly Report

**Endpoint:** `GET /api/driver/auth/reports/weekly`

```dart
class ReportService {
  final ApiService _api = ApiService();
  
  Future<WeeklyReportModel> getWeeklyReport({int weekOffset = 0}) async {
    final response = await _api.get(
      '/driver/auth/reports/weekly',
      queryParameters: {
        'week_offset': weekOffset, // 0 = current week, -1 = last week
      },
    );
    
    final apiResponse = ApiResponse.fromJson(
      response.data,
      (data) => WeeklyReportModel.fromJson(data),
    );
    
    if (apiResponse.isSuccess) {
      return apiResponse.data!;
    }
    throw Exception(apiResponse.message ?? 'Failed to fetch weekly report');
  }
}

class WeeklyReportModel {
  final PeriodModel period;
  final ReportSummary summary;
  final List<DailyStatsModel> dailyBreakdown;
  final ReportInsights insights;
  
  WeeklyReportModel({
    required this.period,
    required this.summary,
    required this.dailyBreakdown,
    required this.insights,
  });
  
  factory WeeklyReportModel.fromJson(Map<String, dynamic> json) {
    return WeeklyReportModel(
      period: PeriodModel.fromJson(json['period'] ?? {}),
      summary: ReportSummary.fromJson(json['summary'] ?? {}),
      dailyBreakdown: (json['daily_breakdown'] ?? [])
          .map((e) => DailyStatsModel.fromJson(e))
          .toList(),
      insights: ReportInsights.fromJson(json['insights'] ?? {}),
    );
  }
}

class PeriodModel {
  final String start;
  final String end;
  final int weekNumber;
  final bool isCurrentWeek;
  
  PeriodModel({
    required this.start,
    required this.end,
    required this.weekNumber,
    required this.isCurrentWeek,
  });
  
  factory PeriodModel.fromJson(Map<String, dynamic> json) {
    return PeriodModel(
      start: json['start'] ?? '',
      end: json['end'] ?? '',
      weekNumber: json['week_number'] ?? 0,
      isCurrentWeek: json['is_current_week'] ?? false,
    );
  }
}

class ReportSummary {
  final int totalTrips;
  final int completedTrips;
  final int cancelledTrips;
  final double completionRate;
  final double totalEarnings;
  final String formattedEarnings;
  final double avgPerTrip;
  final double totalDistanceKm;
  final double totalDurationMinutes;
  
  ReportSummary({
    required this.totalTrips,
    required this.completedTrips,
    required this.cancelledTrips,
    required this.completionRate,
    required this.totalEarnings,
    required this.formattedEarnings,
    required this.avgPerTrip,
    required this.totalDistanceKm,
    required this.totalDurationMinutes,
  });
  
  factory ReportSummary.fromJson(Map<String, dynamic> json) {
    return ReportSummary(
      totalTrips: json['total_trips'] ?? 0,
      completedTrips: json['completed_trips'] ?? 0,
      cancelledTrips: json['cancelled_trips'] ?? 0,
      completionRate: (json['completion_rate'] ?? 0).toDouble(),
      totalEarnings: (json['total_earnings'] ?? 0).toDouble(),
      formattedEarnings: json['formatted_earnings'] ?? '0.00',
      avgPerTrip: (json['avg_per_trip'] ?? 0).toDouble(),
      totalDistanceKm: (json['total_distance_km'] ?? 0).toDouble(),
      totalDurationMinutes: (json['total_duration_minutes'] ?? 0).toDouble(),
    );
  }
}

class DailyStatsModel {
  final String date;
  final String dayName;
  final int trips;
  final int completed;
  final double earnings;
  final String formattedEarnings;
  
  DailyStatsModel({
    required this.date,
    required this.dayName,
    required this.trips,
    required this.completed,
    required this.earnings,
    required this.formattedEarnings,
  });
  
  factory DailyStatsModel.fromJson(Map<String, dynamic> json) {
    return DailyStatsModel(
      date: json['date'] ?? '',
      dayName: json['day_name'] ?? '',
      trips: json['trips'] ?? 0,
      completed: json['completed'] ?? 0,
      earnings: (json['earnings'] ?? 0).toDouble(),
      formattedEarnings: json['formatted_earnings'] ?? '0.00',
    );
  }
}

class ReportInsights {
  final List<PeakHourModel> peakHours;
  final List<DailyStatsModel> topEarningDays;
  final DailyStatsModel? busiestDay;
  
  ReportInsights({
    required this.peakHours,
    required this.topEarningDays,
    this.busiestDay,
  });
  
  factory ReportInsights.fromJson(Map<String, dynamic> json) {
    return ReportInsights(
      peakHours: (json['peak_hours'] ?? [])
          .map((e) => PeakHourModel.fromJson(e))
          .toList(),
      topEarningDays: (json['top_earning_days'] ?? [])
          .map((e) => DailyStatsModel.fromJson(e))
          .toList(),
      busiestDay: json['busiest_day'] != null
          ? DailyStatsModel.fromJson(json['busiest_day'])
          : null,
    );
  }
}

class PeakHourModel {
  final String hour;
  final int trips;
  
  PeakHourModel({
    required this.hour,
    required this.trips,
  });
  
  factory PeakHourModel.fromJson(Map<String, dynamic> json) {
    return PeakHourModel(
      hour: json['hour'] ?? '',
      trips: json['trips'] ?? 0,
    );
  }
}
```

### 6.2 Get Monthly Report

**Endpoint:** `GET /api/driver/auth/reports/monthly`

```dart
Future<MonthlyReportModel> getMonthlyReport({int monthOffset = 0}) async {
  final response = await _api.get(
    '/driver/auth/reports/monthly',
    queryParameters: {
      'month_offset': monthOffset, // 0 = current month, -1 = last month
    },
  );
  
  final apiResponse = ApiResponse.fromJson(
    response.data,
    (data) => MonthlyReportModel.fromJson(data),
  );
  
  if (apiResponse.isSuccess) {
    return apiResponse.data!;
  }
  throw Exception(apiResponse.message ?? 'Failed to fetch monthly report');
}

class MonthlyReportModel {
  final PeriodModel period;
  final ReportSummary summary;
  final List<WeeklyStatsModel> weeklyBreakdown;
  final ReportInsights insights;
  
  MonthlyReportModel({
    required this.period,
    required this.summary,
    required this.weeklyBreakdown,
    required this.insights,
  });
  
  factory MonthlyReportModel.fromJson(Map<String, dynamic> json) {
    return MonthlyReportModel(
      period: PeriodModel.fromJson(json['period'] ?? {}),
      summary: ReportSummary.fromJson(json['summary'] ?? {}),
      weeklyBreakdown: (json['weekly_breakdown'] ?? [])
          .map((e) => WeeklyStatsModel.fromJson(e))
          .toList(),
      insights: ReportInsights.fromJson(json['insights'] ?? {}),
    );
  }
}

class WeeklyStatsModel {
  final String weekStart;
  final String weekEnd;
  final int weekNumber;
  final int trips;
  final double earnings;
  final String formattedEarnings;
  
  WeeklyStatsModel({
    required this.weekStart,
    required this.weekEnd,
    required this.weekNumber,
    required this.trips,
    required this.earnings,
    required this.formattedEarnings,
  });
  
  factory WeeklyStatsModel.fromJson(Map<String, dynamic> json) {
    return WeeklyStatsModel(
      weekStart: json['week_start'] ?? '',
      weekEnd: json['week_end'] ?? '',
      weekNumber: json['week_number'] ?? 0,
      trips: json['trips'] ?? 0,
      earnings: (json['earnings'] ?? 0).toDouble(),
      formattedEarnings: json['formatted_earnings'] ?? '0.00',
    );
  }
}
```

### 6.3 Export Report

**Endpoint:** `POST /api/driver/auth/reports/export`

```dart
Future<String> exportReport({
  required String type, // 'weekly' or 'monthly'
  int? weekOffset,
  int? monthOffset,
  String? format, // 'pdf', 'excel', 'csv'
}) async {
  final response = await _api.post(
    '/driver/auth/reports/export',
    data: {
      'type': type,
      if (weekOffset != null) 'week_offset': weekOffset,
      if (monthOffset != null) 'month_offset': monthOffset,
      if (format != null) 'format': format,
    },
  );
  
  final apiResponse = ApiResponse.fromJson(response.data, null);
  
  if (apiResponse.isSuccess) {
    return apiResponse.data['download_url'] ?? '';
  }
  throw Exception(apiResponse.message ?? 'Failed to export report');
}
```

---

## 7. Vehicle Management (5 APIs)

### 7.1 Get Insurance Status

**Endpoint:** `GET /api/driver/auth/vehicle/insurance-status`

```dart
class VehicleService {
  final ApiService _api = ApiService();
  
  Future<InsuranceStatusModel> getInsuranceStatus({String? vehicleId}) async {
    final response = await _api.get(
      '/driver/auth/vehicle/insurance-status',
      queryParameters: {
        if (vehicleId != null) 'vehicle_id': vehicleId,
      },
    );
    
    final apiResponse = ApiResponse.fromJson(
      response.data,
      (data) => InsuranceStatusModel.fromJson(data),
    );
    
    if (apiResponse.isSuccess) {
      return apiResponse.data!;
    }
    throw Exception(apiResponse.message ?? 'Failed to fetch insurance status');
  }
}

class InsuranceStatusModel {
  final InsuranceInfo insurance;
  
  InsuranceStatusModel({
    required this.insurance,
  });
  
  factory InsuranceStatusModel.fromJson(Map<String, dynamic> json) {
    return InsuranceStatusModel(
      insurance: InsuranceInfo.fromJson(json['insurance'] ?? {}),
    );
  }
}

class InsuranceInfo {
  final String? expiryDate;
  final String? company;
  final String? policyNumber;
  final String status; // valid, warning, critical, expired, unknown
  final int? daysRemaining;
  final bool isExpired;
  final bool needsRenewal;
  
  InsuranceInfo({
    this.expiryDate,
    this.company,
    this.policyNumber,
    required this.status,
    this.daysRemaining,
    required this.isExpired,
    required this.needsRenewal,
  });
  
  factory InsuranceInfo.fromJson(Map<String, dynamic> json) {
    return InsuranceInfo(
      expiryDate: json['expiry_date'],
      company: json['company'],
      policyNumber: json['policy_number'],
      status: json['status'] ?? 'unknown',
      daysRemaining: json['days_remaining'],
      isExpired: json['is_expired'] ?? false,
      needsRenewal: json['needs_renewal'] ?? false,
    );
  }
}
```

### 7.2 Update Insurance

**Endpoint:** `POST /api/driver/auth/vehicle/insurance-update`

```dart
Future<bool> updateInsurance({
  required String expiryDate, // YYYY-MM-DD
  String? company,
  String? policyNumber,
  String? vehicleId,
}) async {
  final response = await _api.post(
    '/driver/auth/vehicle/insurance-update',
    data: {
      'expiry_date': expiryDate,
      if (company != null) 'company': company,
      if (policyNumber != null) 'policy_number': policyNumber,
      if (vehicleId != null) 'vehicle_id': vehicleId,
    },
  );
  final apiResponse = ApiResponse.fromJson(response.data, null);
  return apiResponse.isSuccess;
}
```

### 7.3 Get Inspection Status

**Endpoint:** `GET /api/driver/auth/vehicle/inspection-status`

```dart
Future<InspectionStatusModel> getInspectionStatus({String? vehicleId}) async {
  final response = await _api.get(
    '/driver/auth/vehicle/inspection-status',
    queryParameters: {
      if (vehicleId != null) 'vehicle_id': vehicleId,
    },
  );
  
  final apiResponse = ApiResponse.fromJson(
    response.data,
    (data) => InspectionStatusModel.fromJson(data),
  );
  
  if (apiResponse.isSuccess) {
    return apiResponse.data!;
  }
  throw Exception(apiResponse.message ?? 'Failed to fetch inspection status');
}

class InspectionStatusModel {
  final InspectionInfo inspection;
  
  InspectionStatusModel({
    required this.inspection,
  });
  
  factory InspectionStatusModel.fromJson(Map<String, dynamic> json) {
    return InspectionStatusModel(
      inspection: InspectionInfo.fromJson(json['inspection'] ?? {}),
    );
  }
}

class InspectionInfo {
  final String? lastInspectionDate;
  final String? nextInspectionDate;
  final String status; // valid, warning, critical, expired, unknown
  final int? daysRemaining;
  final bool isExpired;
  final bool needsRenewal;
  
  InspectionInfo({
    this.lastInspectionDate,
    this.nextInspectionDate,
    required this.status,
    this.daysRemaining,
    required this.isExpired,
    required this.needsRenewal,
  });
  
  factory InspectionInfo.fromJson(Map<String, dynamic> json) {
    return InspectionInfo(
      lastInspectionDate: json['last_inspection_date'],
      nextInspectionDate: json['next_inspection_date'],
      status: json['status'] ?? 'unknown',
      daysRemaining: json['days_remaining'],
      isExpired: json['is_expired'] ?? false,
      needsRenewal: json['needs_renewal'] ?? false,
    );
  }
}
```

### 7.4 Update Inspection

**Endpoint:** `POST /api/driver/auth/vehicle/inspection-update`

```dart
Future<bool> updateInspection({
  required String lastInspectionDate, // YYYY-MM-DD
  required String nextInspectionDate, // YYYY-MM-DD
  String? vehicleId,
}) async {
  final response = await _api.post(
    '/driver/auth/vehicle/inspection-update',
    data: {
      'last_inspection_date': lastInspectionDate,
      'next_inspection_date': nextInspectionDate,
      if (vehicleId != null) 'vehicle_id': vehicleId,
    },
  );
  final apiResponse = ApiResponse.fromJson(response.data, null);
  return apiResponse.isSuccess;
}
```

### 7.5 Get Vehicle Reminders

**Endpoint:** `GET /api/driver/auth/vehicle/reminders`

```dart
Future<List<VehicleReminderModel>> getReminders({String? vehicleId}) async {
  final response = await _api.get(
    '/driver/auth/vehicle/reminders',
    queryParameters: {
      if (vehicleId != null) 'vehicle_id': vehicleId,
    },
  );
  
  final apiResponse = ApiResponse.fromJson(
    response.data,
    (data) => (data as List).map((e) => VehicleReminderModel.fromJson(e)).toList(),
  );
  
  if (apiResponse.isSuccess) {
    return apiResponse.data ?? [];
  }
  throw Exception(apiResponse.message ?? 'Failed to fetch reminders');
}

class VehicleReminderModel {
  final String type; // insurance_expiry, inspection_due, etc.
  final String title;
  final String message;
  final int daysRemaining;
  final DateTime? dueDate;
  
  VehicleReminderModel({
    required this.type,
    required this.title,
    required this.message,
    required this.daysRemaining,
    this.dueDate,
  });
  
  factory VehicleReminderModel.fromJson(Map<String, dynamic> json) {
    return VehicleReminderModel(
      type: json['type'] ?? '',
      title: json['title'] ?? '',
      message: json['message'] ?? '',
      daysRemaining: json['days_remaining'] ?? 0,
      dueDate: json['due_date'] != null
          ? DateTime.parse(json['due_date'])
          : null,
    );
  }
}
```

---

## 8. Documents (2 APIs)

### 8.1 Get Document Expiry Status

**Endpoint:** `GET /api/driver/auth/documents/expiry-status`

```dart
class DocumentService {
  final ApiService _api = ApiService();
  
  Future<List<DocumentExpiryModel>> getExpiryStatus() async {
    final response = await _api.get('/driver/auth/documents/expiry-status');
    final apiResponse = ApiResponse.fromJson(
      response.data,
      (data) => (data as List).map((e) => DocumentExpiryModel.fromJson(e)).toList(),
    );
    
    if (apiResponse.isSuccess) {
      return apiResponse.data ?? [];
    }
    throw Exception(apiResponse.message ?? 'Failed to fetch document expiry status');
  }
}

class DocumentExpiryModel {
  final String id;
  final String type; // license, id_card, etc.
  final String name;
  final String? expiryDate;
  final String status; // valid, warning, critical, expired
  final int? daysRemaining;
  final bool isExpired;
  final bool needsRenewal;
  
  DocumentExpiryModel({
    required this.id,
    required this.type,
    required this.name,
    this.expiryDate,
    required this.status,
    this.daysRemaining,
    required this.isExpired,
    required this.needsRenewal,
  });
  
  factory DocumentExpiryModel.fromJson(Map<String, dynamic> json) {
    return DocumentExpiryModel(
      id: json['id'] ?? '',
      type: json['type'] ?? '',
      name: json['name'] ?? '',
      expiryDate: json['expiry_date'],
      status: json['status'] ?? 'unknown',
      daysRemaining: json['days_remaining'],
      isExpired: json['is_expired'] ?? false,
      needsRenewal: json['needs_renewal'] ?? false,
    );
  }
}
```

### 8.2 Update Document Expiry

**Endpoint:** `POST /api/driver/auth/documents/{id}/update-expiry`

```dart
Future<bool> updateDocumentExpiry(String documentId, String expiryDate) async {
  final response = await _api.post(
    '/driver/auth/documents/$documentId/update-expiry',
    data: {
      'expiry_date': expiryDate, // YYYY-MM-DD
    },
  );
  final apiResponse = ApiResponse.fromJson(response.data, null);
  return apiResponse.isSuccess;
}
```

---

## 9. Gamification (3 APIs)

### 9.1 Get Achievements

**Endpoint:** `GET /api/driver/auth/gamification/achievements`

```dart
class GamificationService {
  final ApiService _api = ApiService();
  
  Future<AchievementsModel> getAchievements() async {
    final response = await _api.get('/driver/auth/gamification/achievements');
    final apiResponse = ApiResponse.fromJson(
      response.data,
      (data) => AchievementsModel.fromJson(data),
    );
    
    if (apiResponse.isSuccess) {
      return apiResponse.data!;
    }
    throw Exception(apiResponse.message ?? 'Failed to fetch achievements');
  }
}

class AchievementsModel {
  final List<AchievementModel> unlocked;
  final List<AchievementModel> locked;
  final int totalUnlocked;
  final int totalAvailable;
  
  AchievementsModel({
    required this.unlocked,
    required this.locked,
    required this.totalUnlocked,
    required this.totalAvailable,
  });
  
  factory AchievementsModel.fromJson(Map<String, dynamic> json) {
    return AchievementsModel(
      unlocked: (json['unlocked'] ?? [])
          .map((e) => AchievementModel.fromJson(e))
          .toList(),
      locked: (json['locked'] ?? [])
          .map((e) => AchievementModel.fromJson(e))
          .toList(),
      totalUnlocked: json['total_unlocked'] ?? 0,
      totalAvailable: json['total_available'] ?? 0,
    );
  }
}

class AchievementModel {
  final String id;
  final String name;
  final String description;
  final String? iconUrl;
  final int points;
  final bool isUnlocked;
  final DateTime? unlockedAt;
  final Map<String, dynamic>? progress;
  
  AchievementModel({
    required this.id,
    required this.name,
    required this.description,
    this.iconUrl,
    required this.points,
    required this.isUnlocked,
    this.unlockedAt,
    this.progress,
  });
  
  factory AchievementModel.fromJson(Map<String, dynamic> json) {
    return AchievementModel(
      id: json['id'] ?? '',
      name: json['name'] ?? '',
      description: json['description'] ?? '',
      iconUrl: json['icon_url'],
      points: json['points'] ?? 0,
      isUnlocked: json['is_unlocked'] ?? false,
      unlockedAt: json['unlocked_at'] != null
          ? DateTime.parse(json['unlocked_at'])
          : null,
      progress: json['progress'],
    );
  }
}
```

### 9.2 Get Badges

**Endpoint:** `GET /api/driver/auth/gamification/badges`

```dart
Future<List<BadgeModel>> getBadges() async {
  final response = await _api.get('/driver/auth/gamification/badges');
  final apiResponse = ApiResponse.fromJson(
    response.data,
    (data) => (data as List).map((e) => BadgeModel.fromJson(e)).toList(),
  );
  
  if (apiResponse.isSuccess) {
    return apiResponse.data ?? [];
  }
  throw Exception(apiResponse.message ?? 'Failed to fetch badges');
}

class BadgeModel {
  final String id;
  final String name;
  final String description;
  final String? iconUrl;
  final String tier; // bronze, silver, gold, platinum
  final bool isEarned;
  final DateTime? earnedAt;
  
  BadgeModel({
    required this.id,
    required this.name,
    required this.description,
    this.iconUrl,
    required this.tier,
    required this.isEarned,
    this.earnedAt,
  });
  
  factory BadgeModel.fromJson(Map<String, dynamic> json) {
    return BadgeModel(
      id: json['id'] ?? '',
      name: json['name'] ?? '',
      description: json['description'] ?? '',
      iconUrl: json['icon_url'],
      tier: json['tier'] ?? 'bronze',
      isEarned: json['is_earned'] ?? false,
      earnedAt: json['earned_at'] != null
          ? DateTime.parse(json['earned_at'])
          : null,
    );
  }
}
```

### 9.3 Get Progress

**Endpoint:** `GET /api/driver/auth/gamification/progress`

```dart
Future<GamificationProgressModel> getProgress() async {
  final response = await _api.get('/driver/auth/gamification/progress');
  final apiResponse = ApiResponse.fromJson(
    response.data,
    (data) => GamificationProgressModel.fromJson(data),
  );
  
  if (apiResponse.isSuccess) {
    return apiResponse.data!;
  }
  throw Exception(apiResponse.message ?? 'Failed to fetch progress');
}

class GamificationProgressModel {
  final int totalPoints;
  final int currentLevel;
  final String levelName;
  final int pointsToNextLevel;
  final double levelProgress; // 0.0 to 1.0
  final int totalTrips;
  final int totalEarnings;
  final int streakDays;
  
  GamificationProgressModel({
    required this.totalPoints,
    required this.currentLevel,
    required this.levelName,
    required this.pointsToNextLevel,
    required this.levelProgress,
    required this.totalTrips,
    required this.totalEarnings,
    required this.streakDays,
  });
  
  factory GamificationProgressModel.fromJson(Map<String, dynamic> json) {
    return GamificationProgressModel(
      totalPoints: json['total_points'] ?? 0,
      currentLevel: json['current_level'] ?? 1,
      levelName: json['level_name'] ?? '',
      pointsToNextLevel: json['points_to_next_level'] ?? 0,
      levelProgress: (json['level_progress'] ?? 0).toDouble(),
      totalTrips: json['total_trips'] ?? 0,
      totalEarnings: json['total_earnings'] ?? 0,
      streakDays: json['streak_days'] ?? 0,
    );
  }
}
```

---

## 10. Promotions & Offers (3 APIs)

### 10.1 Get Promotions

**Endpoint:** `GET /api/driver/auth/promotions`

```dart
class PromotionService {
  final ApiService _api = ApiService();
  
  Future<List<PromotionModel>> getPromotions({bool? activeOnly}) async {
    final response = await _api.get(
      '/driver/auth/promotions',
      queryParameters: {
        if (activeOnly != null) 'active_only': activeOnly,
      },
    );
    
    final apiResponse = ApiResponse.fromJson(
      response.data,
      (data) => (data as List).map((e) => PromotionModel.fromJson(e)).toList(),
    );
    
    if (apiResponse.isSuccess) {
      return apiResponse.data ?? [];
    }
    throw Exception(apiResponse.message ?? 'Failed to fetch promotions');
  }
}

class PromotionModel {
  final String id;
  final String title;
  final String description;
  final String type; // bonus, discount, reward
  final String? imageUrl;
  final double? amount;
  final String? currency;
  final DateTime? startDate;
  final DateTime? endDate;
  final bool isActive;
  final bool isClaimed;
  final DateTime? claimedAt;
  final Map<String, dynamic>? terms;
  
  PromotionModel({
    required this.id,
    required this.title,
    required this.description,
    required this.type,
    this.imageUrl,
    this.amount,
    this.currency,
    this.startDate,
    this.endDate,
    required this.isActive,
    required this.isClaimed,
    this.claimedAt,
    this.terms,
  });
  
  factory PromotionModel.fromJson(Map<String, dynamic> json) {
    return PromotionModel(
      id: json['id'] ?? '',
      title: json['title'] ?? '',
      description: json['description'] ?? '',
      type: json['type'] ?? '',
      imageUrl: json['image_url'],
      amount: json['amount'] != null ? (json['amount'] as num).toDouble() : null,
      currency: json['currency'],
      startDate: json['start_date'] != null
          ? DateTime.parse(json['start_date'])
          : null,
      endDate: json['end_date'] != null
          ? DateTime.parse(json['end_date'])
          : null,
      isActive: json['is_active'] ?? false,
      isClaimed: json['is_claimed'] ?? false,
      claimedAt: json['claimed_at'] != null
          ? DateTime.parse(json['claimed_at'])
          : null,
      terms: json['terms'],
    );
  }
}
```

### 10.2 Get Promotion Details

**Endpoint:** `GET /api/driver/auth/promotions/{id}`

```dart
Future<PromotionModel> getPromotionDetails(String promotionId) async {
  final response = await _api.get('/driver/auth/promotions/$promotionId');
  final apiResponse = ApiResponse.fromJson(
    response.data,
    (data) => PromotionModel.fromJson(data),
  );
  
  if (apiResponse.isSuccess) {
    return apiResponse.data!;
  }
  throw Exception(apiResponse.message ?? 'Failed to fetch promotion details');
}
```

### 10.3 Claim Promotion

**Endpoint:** `POST /api/driver/auth/promotions/{id}/claim`

```dart
Future<bool> claimPromotion(String promotionId) async {
  final response = await _api.post('/driver/auth/promotions/$promotionId/claim');
  final apiResponse = ApiResponse.fromJson(response.data, null);
  return apiResponse.isSuccess;
}
```

---

## 11. Readiness Check (1 API)

### 11.1 Driver Readiness Check

**Endpoint:** `GET /api/driver/auth/readiness-check`

```dart
class ReadinessService {
  final ApiService _api = ApiService();
  
  Future<ReadinessCheckModel> checkReadiness() async {
    final response = await _api.get('/driver/auth/readiness-check');
    final apiResponse = ApiResponse.fromJson(
      response.data,
      (data) => ReadinessCheckModel.fromJson(data),
    );
    
    if (apiResponse.isSuccess) {
      return apiResponse.data!;
    }
    throw Exception(apiResponse.message ?? 'Failed to check readiness');
  }
}

class ReadinessCheckModel {
  final bool isReady;
  final ReadinessStatus account;
  final ReadinessStatus gps;
  final ReadinessStatus vehicle;
  final ReadinessStatus documents;
  final ReadinessStatus connectivity;
  final ReadinessStatus activeTrips;
  final List<String> issues;
  final Map<String, dynamic>? details;
  
  ReadinessCheckModel({
    required this.isReady,
    required this.account,
    required this.gps,
    required this.vehicle,
    required this.documents,
    required this.connectivity,
    required this.activeTrips,
    required this.issues,
    this.details,
  });
  
  factory ReadinessCheckModel.fromJson(Map<String, dynamic> json) {
    return ReadinessCheckModel(
      isReady: json['is_ready'] ?? false,
      account: ReadinessStatus.fromJson(json['account'] ?? {}),
      gps: ReadinessStatus.fromJson(json['gps'] ?? {}),
      vehicle: ReadinessStatus.fromJson(json['vehicle'] ?? {}),
      documents: ReadinessStatus.fromJson(json['documents'] ?? {}),
      connectivity: ReadinessStatus.fromJson(json['connectivity'] ?? {}),
      activeTrips: ReadinessStatus.fromJson(json['active_trips'] ?? {}),
      issues: (json['issues'] ?? []).map((e) => e.toString()).toList(),
      details: json['details'],
    );
  }
}

class ReadinessStatus {
  final bool status;
  final String? message;
  
  ReadinessStatus({
    required this.status,
    this.message,
  });
  
  factory ReadinessStatus.fromJson(Map<String, dynamic> json) {
    return ReadinessStatus(
      status: json['status'] ?? false,
      message: json['message'],
    );
  }
}
```

---

## Error Handling

### Standard Error Response Format

```dart
class ApiException implements Exception {
  final String message;
  final int? statusCode;
  final Map<String, dynamic>? errors;
  
  ApiException({
    required this.message,
    this.statusCode,
    this.errors,
  });
  
  @override
  String toString() => message;
}

// Error handler in ApiService
Future<Response> _handleError(DioException error) {
  if (error.response != null) {
    final statusCode = error.response!.statusCode;
    final data = error.response!.data;
    
    switch (statusCode) {
      case 400:
        throw ApiException(
          message: data['message'] ?? 'Bad Request',
          statusCode: 400,
          errors: data['errors'],
        );
      case 401:
        throw ApiException(
          message: 'Unauthorized. Please login again.',
          statusCode: 401,
        );
      case 403:
        throw ApiException(
          message: data['message'] ?? 'Forbidden',
          statusCode: 403,
        );
      case 404:
        throw ApiException(
          message: data['message'] ?? 'Not Found',
          statusCode: 404,
        );
      case 422:
        throw ApiException(
          message: data['message'] ?? 'Validation Error',
          statusCode: 422,
          errors: data['errors'],
        );
      case 500:
        throw ApiException(
          message: 'Server Error. Please try again later.',
          statusCode: 500,
        );
      default:
        throw ApiException(
          message: data['message'] ?? 'An error occurred',
          statusCode: statusCode,
        );
    }
  } else {
    throw ApiException(
      message: 'Network error. Please check your connection.',
      statusCode: null,
    );
  }
}
```

### Usage Example

```dart
try {
  final notifications = await notificationService.getNotifications();
  // Handle success
} on ApiException catch (e) {
  // Handle API errors
  print('Error: ${e.message}');
  if (e.errors != null) {
    // Handle validation errors
    e.errors!.forEach((key, value) {
      print('$key: $value');
    });
  }
} catch (e) {
  // Handle other errors
  print('Unexpected error: $e');
}
```

---

## Flutter Best Practices

### 1. State Management

Use Provider, Riverpod, or Bloc for state management:

```dart
// Example with Provider
class NotificationProvider extends ChangeNotifier {
  final NotificationService _service = NotificationService();
  List<NotificationModel> _notifications = [];
  int _unreadCount = 0;
  bool _isLoading = false;
  
  List<NotificationModel> get notifications => _notifications;
  int get unreadCount => _unreadCount;
  bool get isLoading => _isLoading;
  
  Future<void> loadNotifications() async {
    _isLoading = true;
    notifyListeners();
    
    try {
      _notifications = await _service.getNotifications();
      _unreadCount = await _service.getUnreadCount();
    } catch (e) {
      // Handle error
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }
}
```

### 2. Caching

Implement caching for frequently accessed data:

```dart
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';

class CacheService {
  static const String _cachePrefix = 'api_cache_';
  static const Duration _defaultCacheDuration = Duration(minutes: 5);
  
  static Future<void> setCache(String key, dynamic data, {Duration? duration}) async {
    final prefs = await SharedPreferences.getInstance();
    final cacheData = {
      'data': data,
      'expires_at': DateTime.now().add(duration ?? _defaultCacheDuration).toIso8601String(),
    };
    await prefs.setString('$_cachePrefix$key', jsonEncode(cacheData));
  }
  
  static Future<dynamic> getCache(String key) async {
    final prefs = await SharedPreferences.getInstance();
    final cached = prefs.getString('$_cachePrefix$key');
    if (cached == null) return null;
    
    final cacheData = jsonDecode(cached);
    final expiresAt = DateTime.parse(cacheData['expires_at']);
    if (DateTime.now().isAfter(expiresAt)) {
      await prefs.remove('$_cachePrefix$key');
      return null;
    }
    
    return cacheData['data'];
  }
}
```

### 3. Retry Logic

Implement retry for failed requests:

```dart
Future<Response> getWithRetry(
  String path, {
  int maxRetries = 3,
  Duration retryDelay = const Duration(seconds: 2),
}) async {
  int attempts = 0;
  while (attempts < maxRetries) {
    try {
      return await _api.get(path);
    } catch (e) {
      attempts++;
      if (attempts >= maxRetries) rethrow;
      await Future.delayed(retryDelay);
    }
  }
  throw Exception('Max retries exceeded');
}
```

### 4. Loading States

Show loading indicators:

```dart
class NotificationScreen extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Consumer<NotificationProvider>(
      builder: (context, provider, child) {
        if (provider.isLoading) {
          return Center(child: CircularProgressIndicator());
        }
        
        if (provider.notifications.isEmpty) {
          return Center(child: Text('No notifications'));
        }
        
        return ListView.builder(
          itemCount: provider.notifications.length,
          itemBuilder: (context, index) {
            return NotificationTile(
              notification: provider.notifications[index],
            );
          },
        );
      },
    );
  }
}
```

### 5. Error Display

Show user-friendly error messages:

```dart
void showErrorSnackBar(BuildContext context, String message) {
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(
      content: Text(message),
      backgroundColor: Colors.red,
      duration: Duration(seconds: 3),
    ),
  );
}

// Usage
try {
  await service.performAction();
} on ApiException catch (e) {
  showErrorSnackBar(context, e.message);
}
```

### 6. Pagination

Handle paginated responses:

```dart
class PaginatedList<T> {
  final List<T> items;
  final int currentPage;
  final int totalPages;
  final int totalItems;
  final bool hasMore;
  
  PaginatedList({
    required this.items,
    required this.currentPage,
    required this.totalPages,
    required this.totalItems,
    required this.hasMore,
  });
  
  factory PaginatedList.fromJson(
    Map<String, dynamic> json,
    T Function(Map<String, dynamic>) fromJson,
  ) {
    return PaginatedList(
      items: (json['data'] ?? []).map((e) => fromJson(e)).toList(),
      currentPage: json['current_page'] ?? 1,
      totalPages: json['last_page'] ?? 1,
      totalItems: json['total'] ?? 0,
      hasMore: (json['current_page'] ?? 1) < (json['last_page'] ?? 1),
    );
  }
}
```

### 7. Refresh Indicator

Add pull-to-refresh:

```dart
RefreshIndicator(
  onRefresh: () async {
    await provider.loadNotifications();
  },
  child: ListView(...),
)
```

---

## Summary

This guide covers **53 new driver API routes** organized into 11 categories:

1. **Notifications** - 9 APIs
2. **Support & Help** - 9 APIs
3. **Content Pages** - 2 APIs
4. **Account Management** - 13 APIs
5. **Dashboard & Activity** - 3 APIs
6. **Trip Reports** - 3 APIs
7. **Vehicle Management** - 5 APIs
8. **Documents** - 2 APIs
9. **Gamification** - 3 APIs
10. **Promotions & Offers** - 3 APIs
11. **Readiness Check** - 1 API

**Total: 53 routes** (from `api_driver_new_features.php`)

### Route Breakdown:
- **GET requests:** 25 routes
- **POST requests:** 20 routes
- **PUT requests:** 4 routes
- **DELETE requests:** 4 routes

### All Routes Require:
- **Authentication:** `Authorization: Bearer {token}` header
- **Base URL:** `https://smartline-it.com/api`
- **Content-Type:** `application/json`
- **Accept:** `application/json`

All routes require authentication via Bearer token and follow consistent response formats. Use the provided Flutter code examples and best practices to integrate these APIs into your Flutter driver app.

---

**Last Updated:** January 2, 2026  
**API Version:** 1.0  
**Base URL:** `https://smartline-it.com/api`
