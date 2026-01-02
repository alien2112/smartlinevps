# Bank-Grade Fault-Tolerant Payment System

## 🏦 Overview

This is a production-ready, fault-tolerant payment system designed to handle payment gateway failures, network outages, and edge cases without losing payment state or charging customers twice.

## 🎯 Key Features

✅ **Idempotency Protection** - Prevents duplicate charges
✅ **State Machine** - Robust payment state transitions
✅ **Automatic Reconciliation** - Verifies uncertain payments
✅ **Distributed Locking** - Prevents race conditions
✅ **Webhook Handling** - Async payment confirmations
✅ **Timeout Handling** - Graceful timeout recovery
✅ **Exponential Backoff** - Smart retry strategy
✅ **Comprehensive Logging** - Full audit trail

## 📊 Payment States

```
created          → Initial state
pending_gateway  → Sent to gateway, awaiting response
processing       → Gateway confirmed received
paid            → ✅ Payment successful
failed          → ❌ Payment failed
unknown         → ⚠️ Status unclear (reconciliation needed)
refunded        → Payment refunded
cancelled       → Payment cancelled
```

## 🚀 Quick Start

### 1. Run Migrations

```bash
php artisan migrate
```

### 2. Configure Environment

Add to `.env`:

```env
# Payment Configuration
PAYMENT_GATEWAY=kashier
PAYMENT_GATEWAY_TIMEOUT=30
PAYMENT_MAX_PROCESSING_TIME=300
PAYMENT_RECONCILIATION_ENABLED=true
PAYMENT_RECONCILIATION_BATCH_SIZE=50
PAYMENT_LOCK_DRIVER=redis

# Kashier Configuration
KASHIER_BASE_URL=https://fep.kashier.io
KASHIER_MERCHANT_ID=your-merchant-id
KASHIER_API_KEY=your-api-key
KASHIER_SECRET_KEY=your-secret-key
KASHIER_MODE=live
```

### 3. Set Up Cron

The reconciliation cron runs automatically every minute via Laravel Scheduler.

Ensure this is in your crontab:

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

### 4. Configure Queue Workers

```bash
# Start payment queue workers
php artisan queue:work --queue=payments-reconciliation,payments-retry
```

## 💻 Usage Examples

### Basic Payment Flow

```php
use App\Services\Payment\PaymentService;
use App\Models\PaymentTransaction;

$paymentService = new PaymentService();

// 1. Create payment with idempotency
$payment = $paymentService->createPayment([
    'trip_request_id' => $trip->id,
    'user_id' => $user->id,
    'amount' => 50.00,
    'currency' => 'EGP',
    'metadata' => [
        'customer_name' => $user->name,
        'trip_details' => '...',
    ],
]);

// 2. Process payment
try {
    $payment = $paymentService->processPayment($payment);

    if ($payment->status === PaymentTransaction::STATUS_PAID) {
        // Payment successful!
        return response()->json(['message' => 'Payment successful']);
    }

    if ($payment->status === PaymentTransaction::STATUS_UNKNOWN) {
        // Payment status unclear - reconciliation scheduled
        return response()->json([
            'message' => 'Payment processing, please wait...',
            'payment_id' => $payment->id,
        ]);
    }

} catch (\Exception $e) {
    // Log error, payment state is already saved
    Log::error('Payment error', ['error' => $e->getMessage()]);

    return response()->json([
        'message' => 'Payment processing, please check status later',
        'payment_id' => $payment->id,
    ]);
}
```

### Handle "getaddrinfo EAI_AGAIN" Error

```php
// This is handled automatically by the PaymentService

// When Kashier returns SERVER_ERROR:
// {
//   "cause": "getaddrinfo EAI_AGAIN",
//   "status": "SERVER_ERROR",
//   "messages": { "en": "Something went wrong" }
// }

// The system will:
// 1. Mark payment as 'unknown'
// 2. Schedule reconciliation job (30 seconds later)
// 3. Reconciliation job queries Kashier's /v3/orders/{id}
// 4. Updates payment to actual status (PAID or FAILED)
// 5. Retries with exponential backoff if needed
```

### Idempotency Protection

```php
// Multiple calls with same data return same payment
$payment1 = $paymentService->createPayment([
    'trip_request_id' => 'trip-123',
    'user_id' => 'user-456',
    'amount' => 50.00,
]);

$payment2 = $paymentService->createPayment([
    'trip_request_id' => 'trip-123',
    'user_id' => 'user-456',
    'amount' => 50.00,
]);

// $payment1->id === $payment2->id (same payment returned)
```

### Custom Idempotency Key

```php
// Use custom idempotency key
$idempotencyKey = 'custom-key-' . $request->header('Idempotency-Key');

$payment = $paymentService->createPayment($data, $idempotencyKey);
```

### Manual Reconciliation

```php
use App\Services\Payment\PaymentReconciliationService;

$reconciliation = new PaymentReconciliationService();

// Reconcile single payment
$payment = PaymentTransaction::find('payment-id');
$reconciliation->reconcile($payment);

// Reconcile all pending payments
$reconciled = $reconciliation->reconcileAll(100); // Limit 100
```

### Check Payment Status

```php
$payment = PaymentTransaction::find('payment-id');

// Check if payment is in final state
if ($payment->isFinal()) {
    // No further action needed
}

// Check if needs reconciliation
if ($payment->needsReconciliation()) {
    // Will be reconciled automatically
}

// Check specific status
if ($payment->status === PaymentTransaction::STATUS_PAID) {
    // Payment successful
}
```

## 🔄 Reconciliation Process

### How It Works

1. **Automatic Trigger**: When payment status is `unknown`, `pending_gateway`, or `processing`
2. **Scheduled Job**: ReconcilePaymentJob dispatched with exponential backoff
3. **Gateway Query**: Fetches current status from Kashier API
4. **State Update**: Updates payment to actual status
5. **Retry Logic**: Retries with delays: 1min → 2min → 4min → 8min → ...

### Exponential Backoff Schedule

```
Attempt 1: +1 minute
Attempt 2: +2 minutes
Attempt 3: +4 minutes
Attempt 4: +8 minutes
Attempt 5: +16 minutes
...
Max delay: 1 hour
Max attempts: 10
```

### Manual Commands

```bash
# Reconcile all pending payments
php artisan payments:reconcile

# Reconcile with custom limit
php artisan payments:reconcile --limit=100
```

## 🔐 Security Features

### Distributed Locking

```php
// Redis-based locking prevents concurrent processing
// Lock is automatically acquired and released
// Timeout prevents deadlocks
```

### Webhook Signature Verification

```php
// Webhooks are verified using HMAC-SHA256
// Invalid signatures are rejected
// Protects against forged webhooks
```

## 📈 Monitoring & Alerts

### Query Stuck Payments

```php
// Find payments stuck in processing
$stuckPayments = PaymentTransaction::stuck()->get();

// Find payments needing reconciliation
$needsReconciliation = PaymentTransaction::needsReconciliation()->get();
```

### Logging

All payment events are logged:

```php
// Check logs
tail -f storage/logs/laravel.log
tail -f storage/logs/payment-reconcile.log
```

## 🧪 Testing Edge Cases

```php
// Test timeout handling
// Set KASHIER_TIMEOUT=5 and simulate slow gateway

// Test network failure
// Disable network and attempt payment

// Test duplicate requests
// Send same payment request twice rapidly

// Test webhook race conditions
// Send webhook while reconciliation is running

// Test concurrent processing
// Process same payment from multiple workers
```

## 🎯 Best Practices

1. **Always use idempotency keys** for user-initiated payments
2. **Never assume failure** when gateway is unreachable
3. **Check payment status** before taking action
4. **Monitor reconciliation logs** for patterns
5. **Alert on high unknown status** rates
6. **Use distributed locks** in multi-server environments

## 🚨 Error Handling Matrix

| Error Type | Status | Action | User Message |
|-----------|--------|--------|--------------|
| Network timeout | unknown | Reconcile | "Processing, check back soon" |
| Server error (EAI_AGAIN) | unknown | Reconcile | "Processing, check back soon" |
| Invalid credentials | failed | None | "Payment failed" |
| Insufficient funds | failed | None | "Payment declined" |
| Gateway 5xx | unknown | Reconcile | "Processing, check back soon" |
| Gateway 4xx | failed | None | "Payment failed" |

## 📞 Support

For issues or questions, contact your development team.

## 🔧 Troubleshooting

### Payment stuck in "unknown" status

```bash
# Check reconciliation attempts
SELECT id, status, reconciliation_attempts, next_reconciliation_at
FROM payment_transactions
WHERE status = 'unknown';

# Manual reconciliation
php artisan payments:reconcile --force
```

### Reconciliation not running

```bash
# Check queue worker
ps aux | grep queue:work

# Check cron schedule
php artisan schedule:list

# Run manually
php artisan schedule:run
```

### High failure rate

```bash
# Check gateway status
# Review error logs
tail -f storage/logs/laravel.log | grep -i kashier

# Check gateway credentials
php artisan config:show payment.gateways.kashier
```

## 📝 License

Proprietary - All Rights Reserved
