# KYC Verification API - Flutter Integration Guide

## Base URL
```
https://your-api-domain.com/api/driver
```

## Authentication
All endpoints require Bearer token authentication:
```dart
headers: {
  'Authorization': 'Bearer $accessToken',
  'Content-Type': 'application/json',
  'Accept': 'application/json',
}
```

---

## API Endpoints

### 1. Create/Get Verification Session

**Endpoint:** `POST /verification/session`

**Description:** Creates a new verification session or returns existing active session.

**Request:**
```dart
final response = await http.post(
  Uri.parse('$baseUrl/verification/session'),
  headers: authHeaders,
);
```

**Response (200):**
```json
{
  "response_code": "default_200",
  "message": "Successfully fetched",
  "content": {
    "session_id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "unverified",
    "created_at": "2024-12-27T15:30:00Z",
    "existing_media": []
  }
}
```

**Flutter Model:**
```dart
class VerificationSession {
  final String sessionId;
  final String status;
  final DateTime createdAt;
  final List<String> existingMedia;

  VerificationSession({
    required this.sessionId,
    required this.status,
    required this.createdAt,
    required this.existingMedia,
  });

  factory VerificationSession.fromJson(Map<String, dynamic> json) {
    return VerificationSession(
      sessionId: json['session_id'],
      status: json['status'],
      createdAt: DateTime.parse(json['created_at']),
      existingMedia: List<String>.from(json['existing_media'] ?? []),
    );
  }
}
```

---

### 2. Upload Verification Media

**Endpoint:** `POST /verification/session/{session_id}/upload`

**Description:** Upload selfie, ID front, ID back, or liveness video.

**Parameters:**
| Field | Type | Required | Values |
|-------|------|----------|--------|
| kind | string | Yes | `selfie`, `id_front`, `id_back`, `liveness_video` |
| file | file | Yes | Image (JPEG, PNG, WebP) or Video (MP4, WebM) |

**Request:**
```dart
Future<void> uploadVerificationMedia({
  required String sessionId,
  required String kind,
  required File file,
}) async {
  final request = http.MultipartRequest(
    'POST',
    Uri.parse('$baseUrl/verification/session/$sessionId/upload'),
  );
  
  request.headers.addAll(authHeaders);
  request.fields['kind'] = kind;
  request.files.add(await http.MultipartFile.fromPath('file', file.path));
  
  final response = await request.send();
  final responseBody = await response.stream.bytesToString();
  // Handle response
}
```

**Response (200):**
```json
{
  "response_code": "default_200",
  "message": "Successfully fetched",
  "content": {
    "media_id": 123,
    "kind": "selfie",
    "size": 245678,
    "uploaded_at": "2024-12-27T15:31:00Z"
  }
}
```

**Error Response (400):**
```json
{
  "response_code": "default_400",
  "message": "Invalid request",
  "errors": [
    {"message": "Invalid file type: application/pdf"}
  ]
}
```

---

### 3. Submit Verification Session

**Endpoint:** `POST /verification/session/{session_id}/submit`

**Description:** Submit session for KYC processing. Requires at least `selfie` and `id_front` uploaded.

**Request:**
```dart
final response = await http.post(
  Uri.parse('$baseUrl/verification/session/$sessionId/submit'),
  headers: authHeaders,
);
```

**Response (200):**
```json
{
  "response_code": "default_200",
  "message": "Verification submitted successfully",
  "content": {
    "message": "Verification submitted successfully",
    "session_id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "pending"
  }
}
```

**Error Response (400):**
```json
{
  "response_code": "default_400",
  "errors": [
    {"message": "Missing required media. Required: selfie, id_front"}
  ]
}
```

---

### 4. Get Verification Status

**Endpoint:** `GET /verification/status`

**Description:** Get current verification status for the authenticated driver.

**Request:**
```dart
final response = await http.get(
  Uri.parse('$baseUrl/verification/status'),
  headers: authHeaders,
);
```

**Response (200) - No Session:**
```json
{
  "response_code": "default_200",
  "content": {
    "has_session": false,
    "kyc_status": "unverified"
  }
}
```

**Response (200) - With Session:**
```json
{
  "response_code": "default_200",
  "content": {
    "has_session": true,
    "session_id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "verified",
    "decision": "approved",
    "kyc_status": "verified",
    "created_at": "2024-12-27T15:30:00Z",
    "submitted_at": "2024-12-27T15:32:00Z",
    "processed_at": "2024-12-27T15:35:00Z",
    "existing_media": ["selfie", "id_front"],
    "scores": {
      "liveness": 95.50,
      "face_match": 88.25,
      "doc_auth": 82.00
    },
    "extracted_fields": {
      "name": "محمد أحمد",
      "id_number": "29001011234567",
      "birth_date": "1990-01-01",
      "governorate": "Cairo",
      "gender": "Male"
    }
  }
}
```

**Response (200) - Rejected:**
```json
{
  "response_code": "default_200",
  "content": {
    "has_session": true,
    "session_id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "rejected",
    "decision": "rejected",
    "kyc_status": "rejected",
    "reason_codes": [
      {"code": "FACE_MISMATCH", "message": "Face in selfie doesn't match ID photo"},
      {"code": "LOW_DOC_AUTHENTICITY", "message": "Document authenticity score too low"}
    ]
  }
}
```

---

## Flutter Models

```dart
enum KycStatus {
  notRequired,
  unverified,
  pending,
  verified,
  rejected,
}

enum VerificationStatus {
  unverified,
  pending,
  processing,
  verified,
  rejected,
  manualReview,
  expired,
}

class VerificationStatusResponse {
  final bool hasSession;
  final String? sessionId;
  final VerificationStatus? status;
  final String? decision;
  final KycStatus kycStatus;
  final DateTime? createdAt;
  final DateTime? submittedAt;
  final DateTime? processedAt;
  final List<String> existingMedia;
  final VerificationScores? scores;
  final Map<String, dynamic>? extractedFields;
  final List<ReasonCode>? reasonCodes;

  VerificationStatusResponse({
    required this.hasSession,
    this.sessionId,
    this.status,
    this.decision,
    required this.kycStatus,
    this.createdAt,
    this.submittedAt,
    this.processedAt,
    this.existingMedia = const [],
    this.scores,
    this.extractedFields,
    this.reasonCodes,
  });

  factory VerificationStatusResponse.fromJson(Map<String, dynamic> json) {
    return VerificationStatusResponse(
      hasSession: json['has_session'] ?? false,
      sessionId: json['session_id'],
      status: json['status'] != null 
          ? VerificationStatus.values.byName(json['status']) 
          : null,
      decision: json['decision'],
      kycStatus: KycStatus.values.firstWhere(
        (e) => e.name == (json['kyc_status'] ?? 'unverified').replaceAll('_', ''),
        orElse: () => KycStatus.unverified,
      ),
      createdAt: json['created_at'] != null 
          ? DateTime.parse(json['created_at']) 
          : null,
      submittedAt: json['submitted_at'] != null 
          ? DateTime.parse(json['submitted_at']) 
          : null,
      processedAt: json['processed_at'] != null 
          ? DateTime.parse(json['processed_at']) 
          : null,
      existingMedia: List<String>.from(json['existing_media'] ?? []),
      scores: json['scores'] != null 
          ? VerificationScores.fromJson(json['scores']) 
          : null,
      extractedFields: json['extracted_fields'],
      reasonCodes: json['reason_codes'] != null
          ? (json['reason_codes'] as List)
              .map((r) => ReasonCode.fromJson(r))
              .toList()
          : null,
    );
  }
}

class VerificationScores {
  final double liveness;
  final double faceMatch;
  final double docAuth;

  VerificationScores({
    required this.liveness,
    required this.faceMatch,
    required this.docAuth,
  });

  factory VerificationScores.fromJson(Map<String, dynamic> json) {
    return VerificationScores(
      liveness: (json['liveness'] ?? 0).toDouble(),
      faceMatch: (json['face_match'] ?? 0).toDouble(),
      docAuth: (json['doc_auth'] ?? 0).toDouble(),
    );
  }
}

class ReasonCode {
  final String code;
  final String message;

  ReasonCode({required this.code, required this.message});

  factory ReasonCode.fromJson(Map<String, dynamic> json) {
    return ReasonCode(
      code: json['code'],
      message: json['message'],
    );
  }
}
```

---

## Flutter Service Example

```dart
import 'dart:io';
import 'package:http/http.dart' as http;
import 'dart:convert';

class KycVerificationService {
  final String baseUrl;
  final String accessToken;

  KycVerificationService({
    required this.baseUrl,
    required this.accessToken,
  });

  Map<String, String> get _headers => {
    'Authorization': 'Bearer $accessToken',
    'Accept': 'application/json',
  };

  /// Create or get existing verification session
  Future<VerificationSession> createSession() async {
    final response = await http.post(
      Uri.parse('$baseUrl/driver/verification/session'),
      headers: _headers,
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      return VerificationSession.fromJson(data['content']);
    }
    throw Exception('Failed to create session: ${response.body}');
  }

  /// Upload verification media
  Future<void> uploadMedia({
    required String sessionId,
    required String kind,
    required File file,
  }) async {
    final request = http.MultipartRequest(
      'POST',
      Uri.parse('$baseUrl/driver/verification/session/$sessionId/upload'),
    );

    request.headers.addAll(_headers);
    request.fields['kind'] = kind;
    request.files.add(await http.MultipartFile.fromPath('file', file.path));

    final streamedResponse = await request.send();
    final response = await http.Response.fromStream(streamedResponse);

    if (response.statusCode != 200) {
      throw Exception('Failed to upload: ${response.body}');
    }
  }

  /// Submit session for processing
  Future<void> submitSession(String sessionId) async {
    final response = await http.post(
      Uri.parse('$baseUrl/driver/verification/session/$sessionId/submit'),
      headers: _headers,
    );

    if (response.statusCode != 200) {
      throw Exception('Failed to submit: ${response.body}');
    }
  }

  /// Get current verification status
  Future<VerificationStatusResponse> getStatus() async {
    final response = await http.get(
      Uri.parse('$baseUrl/driver/verification/status'),
      headers: _headers,
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      return VerificationStatusResponse.fromJson(data['content']);
    }
    throw Exception('Failed to get status: ${response.body}');
  }
}
```

---

## Complete Verification Flow (Flutter)

```dart
class KycVerificationPage extends StatefulWidget {
  @override
  _KycVerificationPageState createState() => _KycVerificationPageState();
}

class _KycVerificationPageState extends State<KycVerificationPage> {
  final KycVerificationService _service = KycVerificationService(
    baseUrl: 'https://your-api.com/api',
    accessToken: AuthService.token,
  );
  
  String? _sessionId;
  VerificationStatus? _status;
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    _checkStatus();
  }

  Future<void> _checkStatus() async {
    setState(() => _isLoading = true);
    try {
      final status = await _service.getStatus();
      setState(() {
        _sessionId = status.sessionId;
        _status = status.status;
      });
    } finally {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _startVerification() async {
    setState(() => _isLoading = true);
    try {
      // 1. Create session
      final session = await _service.createSession();
      _sessionId = session.sessionId;

      // 2. Capture and upload selfie
      final selfie = await ImagePicker().pickImage(source: ImageSource.camera);
      if (selfie != null) {
        await _service.uploadMedia(
          sessionId: _sessionId!,
          kind: 'selfie',
          file: File(selfie.path),
        );
      }

      // 3. Capture and upload ID front
      final idFront = await ImagePicker().pickImage(source: ImageSource.camera);
      if (idFront != null) {
        await _service.uploadMedia(
          sessionId: _sessionId!,
          kind: 'id_front',
          file: File(idFront.path),
        );
      }

      // 4. Submit for processing
      await _service.submitSession(_sessionId!);

      // 5. Check status (poll or show pending UI)
      await _checkStatus();
      
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Verification submitted successfully!')),
      );
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error: $e')),
      );
    } finally {
      setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    // Build your UI based on _status
    return Scaffold(
      appBar: AppBar(title: Text('KYC Verification')),
      body: _isLoading 
        ? Center(child: CircularProgressIndicator())
        : _buildContent(),
    );
  }

  Widget _buildContent() {
    switch (_status) {
      case VerificationStatus.verified:
        return Center(child: Text('✅ Verified'));
      case VerificationStatus.pending:
      case VerificationStatus.processing:
        return Center(child: Text('⏳ Processing...'));
      case VerificationStatus.rejected:
        return Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text('❌ Rejected'),
            ElevatedButton(
              onPressed: _startVerification,
              child: Text('Try Again'),
            ),
          ],
        );
      default:
        return Center(
          child: ElevatedButton(
            onPressed: _startVerification,
            child: Text('Start Verification'),
          ),
        );
    }
  }
}
```

---

## Status Values Reference

| Status | Description |
|--------|-------------|
| `unverified` | Session created, no media uploaded yet |
| `pending` | Submitted, waiting for processing |
| `processing` | Currently being processed by backend |
| `verified` | Successfully verified |
| `rejected` | Verification failed |
| `manual_review` | Needs admin review |
| `expired` | Session expired (timeout) |

## KYC Status Values

| Status | Description |
|--------|-------------|
| `not_required` | KYC not required for this user |
| `unverified` | User hasn't completed KYC |
| `pending` | KYC submitted, waiting for result |
| `verified` | KYC approved |
| `rejected` | KYC rejected |
