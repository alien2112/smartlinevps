# Driver Document Verification System

## Overview
A complete admin dashboard interface for reviewing and approving driver applications, including document verification.

## Features Created

### 1. Admin Dashboard Views
- **Location**: `/admin/driver/approvals`
- **Files Created**:
  - `Modules/UserManagement/Resources/views/admin/driver/approvals/index.blade.php`
  - `Modules/UserManagement/Resources/views/admin/driver/approvals/show.blade.php`

### 2. Navigation Menu
- Added "Driver Approvals" submenu item under "Driver Setup"
- Shows real-time badge count of pending applications
- Icon: File with checkmark
- Location: First item in Driver Setup submenu

### 3. Approval Dashboard Features

#### Index Page (`/admin/driver/approvals`)
- **Tabs**:
  - Pending Approval (with count badge)
  - In Progress (with count badge)
  - Approved (with count badge)
- **Table Columns**:
  - Driver profile picture and name
  - Phone number
  - Vehicle type badge
  - Status badge (color-coded)
  - Applied date
  - Review action button

#### Detail Page (`/admin/driver/approvals/{id}`)
- **Left Sidebar**:
  - Driver profile picture
  - Name and status badge
  - Contact information (phone, email)
  - Vehicle type
  - Applied date
  - Identity number
  - Approve/Reject buttons (for pending applications)

- **Right Section**:
  - **Document Grid** (7 required documents):
    - ID Front/Back
    - License Front/Back
    - Car Front/Back
    - Selfie
  - Each document shows:
    - Status badge (Verified/Rejected/Pending/Not Uploaded)
    - Document image (clickable to open in new tab)
    - Upload date and file size
    - Rejection reason (if rejected)
    - Verify/Reject buttons (for pending documents)
  
  - **Document Summary Stats**:
    - Total Required
    - Total Uploaded
    - Total Verified
    - Total Pending

  - **Vehicle Information** (if available):
    - Model, Brand, Category
    - License plate number

### 4. Document Verification Actions

#### Verify Document
- **Endpoint**: `POST /admin/driver/approvals/document/verify/{driverId}/{documentId}`
- **Action**: Marks document as verified
- **Confirmation**: Required before verification

#### Reject Document
- **Endpoint**: `POST /admin/driver/approvals/document/reject/{driverId}/{documentId}`
- **Form Fields**: Rejection reason (required)
- **Action**: Marks document as rejected and notifies driver to re-upload

#### Approve Driver
- **Endpoint**: `POST /admin/driver/approvals/approve/{id}`
- **Action**: Approves entire driver application
- **Modal**: Confirmation dialog

#### Reject Driver
- **Endpoint**: `POST /admin/driver/approvals/reject/{id}`
- **Form Fields**: Rejection reason (required)
- **Action**: Rejects driver application with reason

## API Integration

The system integrates with existing driver document API:
- **Get Documents**: `GET /api/driver/auth/documents?phone={phone}`
- **Response Format**:
```json
{
    "status": "success",
    "message": "Documents retrieved successfully",
    "data": {
        "uploaded_documents": [...],
        "missing_documents": [],
        "summary": {
            "total_required": 7,
            "total_uploaded": 7,
            "total_verified": 0,
            "total_pending": 7
        }
    }
}
```

## Database Tables Used
- `users` - Driver information and onboarding state
- `driver_documents` - Document uploads and verification status
- `driver_details` - Additional driver details
- `vehicles` - Vehicle information

## Permissions
All endpoints are protected by admin authentication middleware.
Additional permission checks can be added using Laravel policies if needed.

## Status Flow
1. **Documents Pending** → Driver uploads documents
2. **Pending Approval** → Admin reviews and verifies documents
3. **Approved** → Driver can start accepting rides
4. **Rejected** → Driver receives feedback and can reapply

## Testing
To test the feature:
1. Login to admin dashboard
2. Navigate to "Driver Setup" → "Driver Approvals"
3. View pending applications
4. Click "Review" on any driver
5. Verify or reject individual documents
6. Approve or reject the entire application

## Files Modified
1. `Modules/UserManagement/Http/Controllers/Web/New/Admin/Driver/DriverApprovalController.php` - Added missing import
2. `Modules/AdminModule/Resources/views/partials/_sidebar.blade.php` - Added menu item

## Files Created
1. `Modules/UserManagement/Resources/views/admin/driver/approvals/index.blade.php`
2. `Modules/UserManagement/Resources/views/admin/driver/approvals/show.blade.php`
3. `DRIVER_APPROVAL_DASHBOARD.md` (this file)

## Next Steps (Optional Enhancements)
- [ ] Add email notifications when documents are verified/rejected
- [ ] Add push notifications to driver app
- [ ] Add bulk approval functionality
- [ ] Add document zoom/lightbox modal
- [ ] Add document download functionality
- [ ] Add audit log for approval actions
- [ ] Add filters (by date, vehicle type, etc.)
- [ ] Add export functionality (PDF/Excel reports)
