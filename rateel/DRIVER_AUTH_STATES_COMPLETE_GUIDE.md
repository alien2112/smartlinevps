# Driver Authentication System - Complete States Guide

## Overview

The driver authentication/onboarding system uses a state machine with **10 distinct states** that track the driver's progress from initial registration to full approval.

---

## 📊 All States and Their Meanings

### 1. `otp_pending`
**Value:** `'otp_pending'`  
**Label:** "Phone Verification Pending"  
**Progress:** 0%  
**Next Step:** `verify_otp`

**Meaning:**
- Initial state when driver starts onboarding
- OTP has been sent to phone number
- Waiting for driver to verify the OTP code
- Driver cannot proceed until OTP is verified

**What Happens:**
- Driver enters phone number
- System sends OTP via SMS
- Driver must enter correct OTP to proceed

**Can Transition To:**
- `otp_verified` (after successful OTP verification)

---

### 2. `otp_verified`
**Value:** `'otp_verified'`  
**Label:** "Phone Verified"  
**Progress:** 15%  
**Next Step:** `set_password`

**Meaning:**
- Phone number has been successfully verified
- Driver needs to set a password
- Account is created but not yet complete

**What Happens:**
- Phone verification is complete
- Driver receives onboarding token
- Must set password to continue

**Can Transition To:**
- `password_set` (after password is set)

---

### 3. `password_set`
**Value:** `'password_set'`  
**Label:** "Password Set"  
**Progress:** 30%  
**Next Step:** `submit_profile`

**Meaning:**
- Password has been created
- Driver needs to complete profile information
- Basic account security is in place

**What Happens:**
- Password is set and encrypted
- Driver can now log in with phone + password
- Must complete profile to continue

**Can Transition To:**
- `profile_complete` (after profile is submitted)

---

### 4. `profile_complete`
**Value:** `'profile_complete'`  
**Label:** "Profile Complete"  
**Progress:** 50%  
**Next Step:** `select_vehicle`

**Meaning:**
- Personal information has been submitted
- Profile includes: name, national ID, city, date of birth, etc.
- Driver needs to select/add vehicle information

**What Happens:**
- Profile data is saved
- Driver information is complete
- Must select vehicle to continue

**Can Transition To:**
- `vehicle_selected` (after vehicle is selected)

---

### 5. `vehicle_selected`
**Value:** `'vehicle_selected'`  
**Label:** "Vehicle Selected"  
**Progress:** 70%  
**Next Step:** `upload_documents`

**Meaning:**
- Vehicle information has been added
- Driver needs to upload required documents
- Vehicle is registered but documents are pending

**What Happens:**
- Vehicle details are saved
- Driver knows which documents are required
- Must upload all required documents

**Can Transition To:**
- `documents_pending` (after documents are uploaded)

---

### 6. `documents_pending`
**Value:** `'documents_pending'`  
**Label:** "Documents Uploaded"  
**Progress:** 85%  
**Next Step:** `submit_for_review`

**Meaning:**
- All required documents have been uploaded
- Documents are waiting for admin review
- Driver can submit application for approval

**What Happens:**
- Documents are stored and pending verification
- Driver can submit application
- Admin will review documents

**Can Transition To:**
- `pending_approval` (after submitting for review)
- `vehicle_selected` (if documents are rejected and need re-upload)

---

### 7. `pending_approval`
**Value:** `'pending_approval'`  
**Label:** "Pending Admin Approval"  
**Progress:** 95%  
**Next Step:** `wait_for_approval`

**Meaning:**
- Application has been submitted
- Waiting for admin to review and approve
- Driver cannot operate until approved

**What Happens:**
- Application is in review queue
- Admin reviews documents and profile
- Driver must wait for decision

**Can Transition To:**
- `approved` (admin approves)
- `rejected` (admin rejects)
- `documents_pending` (admin requests document changes)

**Requires Admin Action:** ✅ Yes

---

### 8. `approved`
**Value:** `'approved'`  
**Label:** "Approved"  
**Progress:** 100%  
**Next Step:** `go_online`

**Meaning:**
- Driver has been fully approved
- Can now accept trips and operate
- Account is fully active

**What Happens:**
- Driver can log in and go online
- Can accept ride requests
- Full access to driver dashboard

**Can Transition To:**
- `suspended` (if admin suspends account)

**Can Operate:** ✅ Yes (can accept trips)

**Terminal State:** ✅ Yes

---

### 9. `rejected`
**Value:** `'rejected'`  
**Label:** "Application Rejected"  
**Progress:** 95%  
**Next Step:** `fix_issues`

**Meaning:**
- Application was rejected by admin
- Driver needs to fix issues and resubmit
- Cannot operate until approved

**What Happens:**
- Admin has rejected the application
- Driver receives rejection reasons
- Can restart the onboarding process

**Can Transition To:**
- `otp_pending` (can restart the process)

**Requires Admin Action:** ✅ Yes

**Terminal State:** ✅ Yes

---

### 10. `suspended`
**Value:** `'suspended'`  
**Label:** "Account Suspended"  
**Progress:** 100%  
**Next Step:** `contact_support`

**Meaning:**
- Account has been suspended by admin
- Driver cannot operate
- Usually due to policy violations or issues

**What Happens:**
- Account is temporarily disabled
- Driver cannot accept trips
- Must contact support to resolve

**Can Transition To:**
- `approved` (admin can unsuspend)

**Requires Admin Action:** ✅ Yes

**Terminal State:** ✅ Yes

---

## 🔄 State Flow Diagram

```
┌─────────────┐
│ otp_pending │ (0%)
└──────┬──────┘
       │ OTP Verified
       ▼
┌──────────────┐
│otp_verified  │ (15%)
└──────┬───────┘
       │ Password Set
       ▼
┌──────────────┐
│password_set  │ (30%)
└──────┬───────┘
       │ Profile Submitted
       ▼
┌─────────────────┐
│profile_complete │ (50%)
└──────┬──────────┘
       │ Vehicle Selected
       ▼
┌─────────────────┐
│vehicle_selected │ (70%)
└──────┬──────────┘
       │ Documents Uploaded
       ▼
┌──────────────────┐
│documents_pending │ (85%)
└──────┬───────────┘
       │ Submitted for Review
       ▼
┌──────────────────┐
│pending_approval  │ (95%)
└──────┬───────────┘
       │
       ├───► APPROVED ────► Can Operate ✅
       │     (100%)
       │
       ├───► REJECTED ────► Can Restart
       │     (95%)
       │
       └───► DOCUMENTS_PENDING (if changes needed)

APPROVED ────► SUSPENDED (if admin suspends)
```

---

## 📋 State Properties Summary

| State | Progress | Can Operate | Terminal | Requires Admin | Next Step |
|-------|----------|-------------|----------|----------------|-----------|
| `otp_pending` | 0% | ❌ | ❌ | ❌ | `verify_otp` |
| `otp_verified` | 15% | ❌ | ❌ | ❌ | `set_password` |
| `password_set` | 30% | ❌ | ❌ | ❌ | `submit_profile` |
| `profile_complete` | 50% | ❌ | ❌ | ❌ | `select_vehicle` |
| `vehicle_selected` | 70% | ❌ | ❌ | ❌ | `upload_documents` |
| `documents_pending` | 85% | ❌ | ❌ | ❌ | `submit_for_review` |
| `pending_approval` | 95% | ❌ | ❌ | ✅ | `wait_for_approval` |
| `approved` | 100% | ✅ | ✅ | ❌ | `go_online` |
| `rejected` | 95% | ❌ | ✅ | ✅ | `fix_issues` |
| `suspended` | 100% | ❌ | ✅ | ✅ | `contact_support` |

---

## 🔐 State Machine Rules

### Valid Transitions

1. **Linear Flow (Normal Onboarding):**
   ```
   otp_pending → otp_verified → password_set → profile_complete 
   → vehicle_selected → documents_pending → pending_approval → approved
   ```

2. **Document Rejection Flow:**
   ```
   documents_pending → pending_approval → documents_pending (if admin requests changes)
   ```

3. **Rejection Flow:**
   ```
   pending_approval → rejected → otp_pending (can restart)
   ```

4. **Suspension Flow:**
   ```
   approved → suspended → approved (admin can unsuspend)
   ```

### Invalid Transitions

- Cannot skip states (e.g., cannot go from `otp_pending` directly to `password_set`)
- Cannot go backwards except:
  - `documents_pending` ← `pending_approval` (if admin requests changes)
  - `rejected` → `otp_pending` (restart process)
- Cannot transition from terminal states except:
  - `rejected` → `otp_pending` (restart)
  - `suspended` → `approved` (unsuspend)

---

## 📱 Client Implementation Guide

### Checking Current State

Use the status endpoint:
```
GET /api/v2/driver/onboarding/status
```

Response includes:
- `onboarding_state`: Current state value
- `next_step`: What action to take next
- `state_version`: Version number (prevents stale updates)
- `progress_percentage`: Progress indicator

### Handling Each State

1. **`otp_pending`** → Show OTP verification screen
2. **`otp_verified`** → Show password creation screen
3. **`password_set`** → Show profile form
4. **`profile_complete`** → Show vehicle selection
5. **`vehicle_selected`** → Show document upload screen
6. **`documents_pending`** → Show submit button
7. **`pending_approval`** → Show waiting screen with message
8. **`approved`** → Redirect to dashboard
9. **`rejected`** → Show rejection reasons and restart option
10. **`suspended`** → Show suspension message and support contact

---

## 🎯 Key Points

1. **State Version:** Each state change increments `state_version` to prevent race conditions
2. **Progress Tracking:** Progress percentage helps show onboarding completion
3. **Terminal States:** Only `approved`, `rejected`, and `suspended` are terminal
4. **Admin States:** `pending_approval`, `rejected`, and `suspended` require admin action
5. **Operating Status:** Only `approved` state allows driver to accept trips

---

## 🔄 State Transition Methods

The system uses these methods to manage states:

- `canTransitionTo($newState)` - Check if transition is allowed
- `allowedTransitions()` - Get all valid next states
- `canOperate()` - Check if driver can accept trips
- `isTerminal()` - Check if state is terminal
- `requiresAdminAction()` - Check if admin action needed
- `isOnboardingComplete()` - Check if onboarding is done
- `progressPercentage()` - Get progress percentage
- `nextStep()` - Get next action name
- `label()` - Get human-readable label

---

**Last Updated:** 2026-01-03  
**API Version:** 2.0
