# Smartline Fixes Plan
**Date:** January 10, 2026  
**Status:** Pending Implementation

---

## 📋 Overview

This document outlines the implementation plan for two critical fixes:

1. **Cash Payment Commission Handling** - Deduct commission from driver wallet (allow negative balance)
2. **Driver Support Screen Endpoint** - New endpoint for driver support screen

---

## 🔴 Problem 1: Cash Payment Commission Handling

### Current Behavior

When a customer pays with **cash**, the current `cashTransaction()` method in `TransactionTrait.php` (lines 95-173):

```php
// Current logic:
$riderAccount->payable_balance += $adminReceived;     // Commission owed to admin
$riderAccount->received_balance += $tripBalanceAfterRemoveCommission;  // Driver earning
```

**Current Flow:**
1. Driver receives full cash payment from customer (e.g., 100 EGP)
2. System tracks commission (e.g., 30 EGP) in `payable_balance` 
3. Admin must manually collect cash from driver later
4. Driver's `wallet_balance` is NOT affected

### Required Behavior

When a customer pays with **cash**, the commission should be **immediately deducted** from the driver's `wallet_balance`, allowing it to go **negative** if necessary.

**Required Flow:**
1. Driver receives full cash payment from customer (e.g., 100 EGP)
2. Commission (e.g., 30 EGP) is **deducted from driver's wallet_balance**
3. Driver's wallet can go **negative** (e.g., -30 EGP)
4. Driver must top-up wallet to clear the negative balance

### Files to Modify

| File | Changes Required |
|------|-----------------|
| `Modules/TransactionManagement/Traits/TransactionTrait.php` | Modify `cashTransaction()` method |
| `Modules/UserManagement/Entities/UserAccount.php` | Ensure `wallet_balance` can be negative |
| `database/migrations/` | New migration to allow negative wallet_balance |

### Implementation Details

#### Step 1: Database Migration

Create migration to ensure `wallet_balance` column allows negative values:

```php
// database/migrations/2026_01_10_000001_allow_negative_driver_wallet_balance.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure wallet_balance can be negative (it should already support decimal)
        // This migration documents the business rule change
        Schema::table('user_accounts', function (Blueprint $table) {
            // wallet_balance is already decimal, just document the change
            // Add a comment or index for tracking negative balances
        });
        
        // Add index for quickly finding drivers with negative balances
        Schema::table('user_accounts', function (Blueprint $table) {
            $table->index('wallet_balance', 'idx_wallet_balance_negative');
        });
    }

    public function down(): void
    {
        Schema::table('user_accounts', function (Blueprint $table) {
            $table->dropIndex('idx_wallet_balance_negative');
        });
    }
};
```

#### Step 2: Modify `cashTransaction()` Method

Update `Modules/TransactionManagement/Traits/TransactionTrait.php`:

```php
public function cashTransaction($trip, $returnFee = false): void
{
    $adminUserId = User::where('user_type', ADMIN_USER_TYPES[0])->first()->id;
    DB::beginTransaction();
    
    $adminReceived = $trip->fee->admin_commission; // Commission amount
    
    if ($returnFee) {
        $tripBalanceAfterRemoveCommission = ($trip->paid_fare - $trip->return_fee) - $trip->fee->admin_commission;
    } else {
        $tripBalanceAfterRemoveCommission = $trip->paid_fare - $trip->fee->admin_commission;
    }

    // ============================================================
    // NEW: Deduct commission from driver's wallet_balance
    // ============================================================
    
    // Get driver account with lock to prevent race conditions
    $riderAccount = UserAccount::where('user_id', $trip->driver->id)
        ->lockForUpdate()
        ->first();
    
    // Deduct commission from wallet_balance (can go negative)
    $riderAccount->wallet_balance -= $adminReceived;
    
    // Still track received cash (driver's earning portion)
    $riderAccount->received_balance += $tripBalanceAfterRemoveCommission;
    $riderAccount->save();
    
    // Transaction 1: Record wallet debit for commission
    $riderTransaction1 = new Transaction();
    $riderTransaction1->attribute = 'cash_trip_commission_deducted';
    $riderTransaction1->attribute_id = $trip->id;
    $riderTransaction1->debit = $adminReceived;
    $riderTransaction1->balance = $riderAccount->wallet_balance;
    $riderTransaction1->user_id = $trip->driver->id;
    $riderTransaction1->account = 'wallet_balance';
    $riderTransaction1->save();
    
    // Transaction 2: Record driver earning
    $riderTransaction2 = new Transaction();
    $riderTransaction2->attribute = 'driver_earning';
    $riderTransaction2->attribute_id = $trip->id;
    $riderTransaction2->credit = $tripBalanceAfterRemoveCommission;
    $riderTransaction2->balance = $riderAccount->received_balance;
    $riderTransaction2->user_id = $trip->driver->id;
    $riderTransaction2->account = 'received_balance';
    $riderTransaction2->trx_ref_id = $riderTransaction1->id;
    $riderTransaction2->save();

    // Handle coupon and discount as before
    if ($trip->coupon_id !== null && $trip->coupon_amount > 0) {
        $this->riderAccountUpdateWithTransactionForCoupon($trip);
    }
    if ($trip->discount_amount !== null && $trip->discount_amount > 0) {
        $this->riderAccountUpdateWithTransactionForDiscount($trip);
    }

    // ============================================================
    // Admin receives commission directly (already "collected")
    // ============================================================
    
    $adminAccount = UserAccount::where('user_id', $adminUserId)->first();
    $adminAccount->received_balance += $adminReceived; // Commission directly received
    $adminAccount->save();

    // Admin transaction: Commission received
    $adminTransaction = new Transaction();
    $adminTransaction->attribute = 'admin_commission';
    $adminTransaction->attribute_id = $trip->id;
    $adminTransaction->credit = $adminReceived;
    $adminTransaction->balance = $adminAccount->received_balance;
    $adminTransaction->user_id = $adminUserId;
    $adminTransaction->account = 'received_balance';
    $adminTransaction->trx_ref_id = $riderTransaction1->id;
    $adminTransaction->save();

    // Handle admin coupon/discount
    if ($trip->coupon_id !== null && $trip->coupon_amount > 0) {
        $this->adminAccountUpdateWithTransactionForCoupon($trip, $adminUserId);
    }
    if ($trip->discount_amount !== null && $trip->discount_amount > 0) {
        $this->adminAccountUpdateWithTransactionForDiscount($trip, $adminUserId);
    }

    $this->driverLevelUpdateChecker($trip->driver);

    // Send notification to driver about commission deduction
    if ($riderAccount->wallet_balance < 0) {
        sendDeviceNotification(
            fcm_token: $trip->driver->fcm_token,
            title: translate('Commission Deducted'),
            description: translate('Commission of ' . $adminReceived . ' EGP has been deducted. Your wallet balance is now ' . $riderAccount->wallet_balance . ' EGP. Please top-up to clear the negative balance.'),
            status: 1,
            action: 'wallet_negative',
            user_id: $trip->driver->id,
        );
    }

    DB::commit();
}
```

#### Step 3: Add Negative Balance Indicator to Driver Wallet API

Update `Modules/UserManagement/Http/Controllers/Api/New/Driver/DriverWalletController.php`:

```php
public function getBalance()
{
    $driver = auth('api')->user();
    $account = UserAccount::where('user_id', $driver->id)->first();
    
    return response()->json(responseFormatter(DEFAULT_200, [
        'wallet_balance' => $account->wallet_balance,
        'receivable_balance' => $account->receivable_balance,
        'received_balance' => $account->received_balance,
        'payable_balance' => $account->payable_balance,
        'pending_balance' => $account->pending_balance,
        'total_withdrawn' => $account->total_withdrawn,
        // NEW: Indicate if balance is negative
        'is_negative' => $account->wallet_balance < 0,
        'amount_owed' => $account->wallet_balance < 0 ? abs($account->wallet_balance) : 0,
    ]));
}
```

### Testing Scenarios

| Scenario | Expected Result |
|----------|----------------|
| Cash trip with driver wallet balance 100 EGP, commission 30 EGP | Wallet becomes 70 EGP |
| Cash trip with driver wallet balance 10 EGP, commission 30 EGP | Wallet becomes -20 EGP |
| Cash trip with driver wallet balance -50 EGP, commission 30 EGP | Wallet becomes -80 EGP |
| Driver checks wallet after negative balance | `is_negative: true`, `amount_owed: 80` |

---

## 🟡 Problem 2: Driver Support Screen Endpoint

### Current State

- Customer support endpoint exists: `GET /api/customer/support/app-info`
- **No driver support endpoint exists**
- The Flutter driver app needs a support screen with app info, contact details, etc.

### Required Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/driver/support/app-info` | Get app version, support contact info |
| GET | `/api/driver/support/faq` | Get frequently asked questions |
| POST | `/api/driver/support/ticket` | Submit a support ticket |
| GET | `/api/driver/support/tickets` | Get driver's support tickets |

### Files to Create

| File | Description |
|------|-------------|
| `Modules/UserManagement/Http/Controllers/Api/New/Driver/SupportController.php` | Main controller |
| `Modules/UserManagement/Entities/SupportTicket.php` | Entity for support tickets |
| `database/migrations/2026_01_10_000002_create_support_tickets_table.php` | Migration |

### Implementation Details

#### Step 1: Database Migration

```php
// database/migrations/2026_01_10_000002_create_support_tickets_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->string('user_type'); // 'driver' or 'customer'
            $table->string('subject');
            $table->text('message');
            $table->string('category')->nullable(); // 'payment', 'trip', 'account', 'other'
            $table->string('priority')->default('normal'); // 'low', 'normal', 'high', 'urgent'
            $table->string('status')->default('open'); // 'open', 'in_progress', 'resolved', 'closed'
            $table->foreignUuid('trip_id')->nullable()->constrained('trip_requests')->onDelete('set null');
            $table->text('admin_response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->foreignUuid('responded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['user_type', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
```

#### Step 2: Create Support Ticket Entity

```php
// Modules/UserManagement/Entities/SupportTicket.php

<?php

namespace Modules\UserManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SupportTicket extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'user_type',
        'subject',
        'message',
        'category',
        'priority',
        'status',
        'trip_id',
        'admin_response',
        'responded_at',
        'responded_by',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    // Status constants
    const STATUS_OPEN = 'open';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_CLOSED = 'closed';

    // Category constants
    const CATEGORY_PAYMENT = 'payment';
    const CATEGORY_TRIP = 'trip';
    const CATEGORY_ACCOUNT = 'account';
    const CATEGORY_OTHER = 'other';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function trip()
    {
        return $this->belongsTo(\Modules\TripManagement\Entities\TripRequest::class, 'trip_id');
    }

    public function responder()
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
```

#### Step 3: Create Driver Support Controller

```php
// Modules/UserManagement/Http/Controllers/Api/New/Driver/SupportController.php

<?php

namespace Modules\UserManagement\Http\Controllers\Api\New\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\UserManagement\Entities\SupportTicket;

class SupportController extends Controller
{
    /**
     * Get app version and support info
     * GET /api/driver/support/app-info
     */
    public function appInfo(): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_200, [
            'app_name' => config('app.name'),
            'app_version' => '1.0.0',
            'api_version' => '2.0',
            'minimum_supported_version' => '1.0.0',
            'latest_version' => '1.0.0',
            'force_update_required' => false,
            'support_email' => businessConfig('business_support_email')?->value ?? 'support@smartline-it.com',
            'support_phone' => businessConfig('business_support_phone')?->value ?? '+20 xxx xxx xxxx',
            'support_whatsapp' => businessConfig('business_support_whatsapp')?->value ?? null,
            'working_hours' => businessConfig('support_working_hours')?->value ?? '9:00 AM - 9:00 PM',
            'emergency_number' => '911',
            'help_center_url' => businessConfig('help_center_url')?->value ?? null,
        ]));
    }

    /**
     * Get frequently asked questions
     * GET /api/driver/support/faq
     */
    public function faq(): JsonResponse
    {
        // Could be fetched from database in future
        $faqs = [
            [
                'id' => 1,
                'question' => 'كيف أسحب أرباحي؟',
                'question_en' => 'How do I withdraw my earnings?',
                'answer' => 'يمكنك سحب أرباحك من خلال قائمة المحفظة > طلب سحب',
                'answer_en' => 'You can withdraw your earnings from Wallet menu > Request Withdrawal',
                'category' => 'payment',
            ],
            [
                'id' => 2,
                'question' => 'كيف أغير حالتي إلى متاح؟',
                'question_en' => 'How do I change my status to available?',
                'answer' => 'انقر على زر التبديل في الشاشة الرئيسية لتغيير حالتك',
                'answer_en' => 'Click the toggle button on the home screen to change your status',
                'category' => 'trip',
            ],
            [
                'id' => 3,
                'question' => 'ما هي نسبة العمولة؟',
                'question_en' => 'What is the commission rate?',
                'answer' => 'تختلف نسبة العمولة حسب فئة السيارة. راجع تفاصيل العقد الخاص بك.',
                'answer_en' => 'Commission rate varies by vehicle category. Check your contract details.',
                'category' => 'payment',
            ],
            [
                'id' => 4,
                'question' => 'كيف أحدث بيانات سيارتي؟',
                'question_en' => 'How do I update my vehicle information?',
                'answer' => 'اذهب إلى الملف الشخصي > السيارة > تعديل البيانات',
                'answer_en' => 'Go to Profile > Vehicle > Edit Information',
                'category' => 'account',
            ],
            [
                'id' => 5,
                'question' => 'ماذا أفعل في حالة الطوارئ؟',
                'question_en' => 'What do I do in an emergency?',
                'answer' => 'استخدم زر SOS في التطبيق أو اتصل بالطوارئ مباشرة',
                'answer_en' => 'Use the SOS button in the app or call emergency services directly',
                'category' => 'other',
            ],
        ];

        return response()->json(responseFormatter(DEFAULT_200, [
            'faqs' => $faqs,
            'categories' => [
                ['id' => 'all', 'name' => 'الكل', 'name_en' => 'All'],
                ['id' => 'payment', 'name' => 'الدفع', 'name_en' => 'Payment'],
                ['id' => 'trip', 'name' => 'الرحلات', 'name_en' => 'Trips'],
                ['id' => 'account', 'name' => 'الحساب', 'name_en' => 'Account'],
                ['id' => 'other', 'name' => 'أخرى', 'name_en' => 'Other'],
            ],
        ]));
    }

    /**
     * Submit a support ticket
     * POST /api/driver/support/ticket
     */
    public function createTicket(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
            'category' => 'nullable|in:payment,trip,account,other',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'trip_id' => 'nullable|uuid|exists:trip_requests,id',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $driver = auth('api')->user();

        $ticket = SupportTicket::create([
            'user_id' => $driver->id,
            'user_type' => 'driver',
            'subject' => $request->subject,
            'message' => $request->message,
            'category' => $request->category ?? SupportTicket::CATEGORY_OTHER,
            'priority' => $request->priority ?? 'normal',
            'trip_id' => $request->trip_id,
            'status' => SupportTicket::STATUS_OPEN,
        ]);

        // Send notification to admin (optional)
        // dispatch(new \App\Jobs\NotifyAdminNewTicketJob($ticket));

        return response()->json(responseFormatter(DEFAULT_STORE_200, [
            'ticket_id' => $ticket->id,
            'status' => $ticket->status,
            'message' => 'Your support ticket has been submitted. We will respond shortly.',
        ]));
    }

    /**
     * Get driver's support tickets
     * GET /api/driver/support/tickets
     */
    public function getTickets(Request $request): JsonResponse
    {
        $driver = auth('api')->user();
        
        $query = SupportTicket::where('user_id', $driver->id)
            ->orderBy('created_at', 'desc');

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $tickets = $query->paginate($request->get('limit', 10));

        $data = $tickets->map(function ($ticket) {
            return [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'message' => $ticket->message,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'admin_response' => $ticket->admin_response,
                'responded_at' => $ticket->responded_at?->toIso8601String(),
                'created_at' => $ticket->created_at->toIso8601String(),
                'trip_id' => $ticket->trip_id,
            ];
        });

        return response()->json(responseFormatter(DEFAULT_200, [
            'tickets' => $data,
            'pagination' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]));
    }

    /**
     * Get single ticket details
     * GET /api/driver/support/ticket/{id}
     */
    public function getTicket(string $id): JsonResponse
    {
        $driver = auth('api')->user();
        
        $ticket = SupportTicket::where('user_id', $driver->id)
            ->where('id', $id)
            ->with('trip:id,ref_id,created_at')
            ->first();

        if (!$ticket) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        return response()->json(responseFormatter(DEFAULT_200, [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'message' => $ticket->message,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'admin_response' => $ticket->admin_response,
            'responded_at' => $ticket->responded_at?->toIso8601String(),
            'created_at' => $ticket->created_at->toIso8601String(),
            'trip' => $ticket->trip ? [
                'id' => $ticket->trip->id,
                'ref_id' => $ticket->trip->ref_id,
                'created_at' => $ticket->trip->created_at->toIso8601String(),
            ] : null,
        ]));
    }
}
```

#### Step 4: Register Routes

Add to `Modules/UserManagement/Routes/api.php`:

```php
// Inside Route::group(['prefix' => 'driver'], function () {
//     Route::group(['middleware' => ['auth:api', 'maintenance_mode']], function () {

        // Support routes
        Route::group(['prefix' => 'support'], function () {
            Route::controller(\Modules\UserManagement\Http\Controllers\Api\New\Driver\SupportController::class)->group(function () {
                Route::get('app-info', 'appInfo');
                Route::get('faq', 'faq');
                Route::post('ticket', 'createTicket');
                Route::get('tickets', 'getTickets');
                Route::get('ticket/{id}', 'getTicket');
            });
        });

//     });
// });
```

### API Documentation

#### GET `/api/driver/support/app-info`

**Response:**
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "app_name": "Smartline",
    "app_version": "1.0.0",
    "api_version": "2.0",
    "minimum_supported_version": "1.0.0",
    "latest_version": "1.0.0",
    "force_update_required": false,
    "support_email": "support@smartline-it.com",
    "support_phone": "+20 xxx xxx xxxx",
    "support_whatsapp": "+20 xxx xxx xxxx",
    "working_hours": "9:00 AM - 9:00 PM",
    "emergency_number": "911",
    "help_center_url": "https://smartline-it.com/help"
  }
}
```

#### GET `/api/driver/support/faq`

**Response:**
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "faqs": [...],
    "categories": [...]
  }
}
```

#### POST `/api/driver/support/ticket`

**Request:**
```json
{
  "subject": "Payment issue",
  "message": "I didn't receive payment for trip #12345",
  "category": "payment",
  "priority": "high",
  "trip_id": "uuid-of-trip (optional)"
}
```

**Response:**
```json
{
  "response_code": "default_store_200",
  "message": "Successfully created",
  "data": {
    "ticket_id": "uuid",
    "status": "open",
    "message": "Your support ticket has been submitted. We will respond shortly."
  }
}
```

#### GET `/api/driver/support/tickets`

**Query Parameters:**
- `status` (optional): Filter by status (open, in_progress, resolved, closed)
- `limit` (optional): Items per page (default: 10)
- `page` (optional): Page number

**Response:**
```json
{
  "response_code": "default_200",
  "message": "Successfully loaded",
  "data": {
    "tickets": [...],
    "pagination": {
      "current_page": 1,
      "last_page": 5,
      "per_page": 10,
      "total": 50
    }
  }
}
```

---

## 🗓️ Implementation Timeline

| Phase | Task | Estimated Time |
|-------|------|---------------|
| **Phase 1** | Database migrations | 30 mins |
| **Phase 2** | Modify `cashTransaction()` method | 1 hour |
| **Phase 3** | Update driver wallet API | 30 mins |
| **Phase 4** | Create Support entities & controller | 2 hours |
| **Phase 5** | Register routes | 15 mins |
| **Phase 6** | Testing | 2 hours |
| **Phase 7** | Documentation | 30 mins |
| **Total** | | ~6.5 hours |

---

## ✅ Deployment Checklist

### Cash Payment Commission
- [ ] Create migration for wallet balance index
- [ ] Backup current `TransactionTrait.php`
- [ ] Modify `cashTransaction()` method
- [ ] Update driver wallet balance API
- [ ] Test with positive wallet balance
- [ ] Test with wallet going negative
- [ ] Test with already negative wallet
- [ ] Verify transactions are recorded correctly
- [ ] Verify admin receives commission in `received_balance`

### Driver Support Screen
- [ ] Create `support_tickets` table migration
- [ ] Create `SupportTicket` entity
- [ ] Create `SupportController`
- [ ] Register routes in `api.php`
- [ ] Test `app-info` endpoint
- [ ] Test `faq` endpoint
- [ ] Test ticket creation
- [ ] Test ticket listing
- [ ] Test ticket detail view

### Final Steps
- [ ] Run `php artisan migrate`
- [ ] Clear caches: `php artisan config:clear && php artisan route:clear`
- [ ] Update API documentation
- [ ] Notify Flutter team of new endpoints

---

## ⚠️ Important Notes

1. **Negative Wallet Impact**: Drivers with negative wallets should be:
   - Notified after each cash trip
   - Potentially restricted from withdrawals until balance is positive
   - Shown warnings in the app

2. **Commission Calculation**: Ensure the `admin_commission` in `TripRequestFee` is correctly calculated before this logic runs.

3. **Rollback Plan**: Keep backup of `TransactionTrait.php` before modifications.

4. **Support Tickets**: Admin panel will need to be updated to view/respond to tickets (separate task).

---

## 📞 Contact

For questions about this implementation plan, contact the development team.

---

**Document Version:** 1.0  
**Last Updated:** January 10, 2026  
**Author:** Development Team
