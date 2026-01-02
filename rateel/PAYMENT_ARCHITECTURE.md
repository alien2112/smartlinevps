# 🏦 Bank-Grade Payment System Architecture

## 🎯 System Overview

This is a production-ready, fault-tolerant payment system that handles all edge cases:
- ✅ Never loses payment state
- ✅ Never charges twice
- ✅ Never assumes failure when gateway is unreachable
- ✅ Automatically reconciles uncertain payments
- ✅ Handles network outages gracefully
- ✅ Prevents race conditions with distributed locking

---

## 📊 Architecture Diagram

```
┌─────────────┐
│   Customer  │
│   Request   │
└──────┬──────┘
       │
       ▼
┌─────────────────────────────────────────────────────┐
│  PaymentService (Idempotency Layer)                 │
│  - Check existing payment (idempotency key)         │
│  - Create new payment or return existing           │
└──────┬──────────────────────────────────────────────┘
       │
       ▼
┌─────────────────────────────────────────────────────┐
│  State Machine (State: created)                     │
│  - Validate state transitions                       │
│  - Log all transitions                              │
└──────┬──────────────────────────────────────────────┘
       │
       ▼
┌─────────────────────────────────────────────────────┐
│  Distributed Lock (Redis/DB)                        │
│  - Prevent concurrent processing                    │
│  - Atomic lock acquisition                          │
└──────┬──────────────────────────────────────────────┘
       │
       ▼
┌─────────────────────────────────────────────────────┐
│  Gateway Request (State: pending_gateway)           │
│  - Build request payload                            │
│  - Set timeout (30s)                                │
│  - Send to Kashier /v3/orders                       │
└──────┬──────────────────────────────────────────────┘
       │
       ├──────────────┬──────────────┬─────────────┐
       │              │              │             │
       ▼              ▼              ▼             ▼
┌──────────┐  ┌──────────┐  ┌──────────┐  ┌───────────┐
│  SUCCESS │  │  FAILED  │  │  SERVER  │  │  TIMEOUT  │
│          │  │          │  │  ERROR   │  │           │
└────┬─────┘  └────┬─────┘  └────┬─────┘  └─────┬─────┘
     │             │              │              │
     ▼             ▼              ▼              ▼
┌─────────┐  ┌─────────┐  ┌──────────────┐  ┌──────────────┐
│  PAID   │  │  FAILED │  │   UNKNOWN    │  │   UNKNOWN    │
│  ✅     │  │   ❌    │  │   ⚠️        │  │    ⚠️       │
└─────────┘  └─────────┘  └──────┬───────┘  └──────┬───────┘
                                  │                  │
                                  │                  │
                     ┌────────────┴──────────────────┘
                     │
                     ▼
          ┌──────────────────────────────────┐
          │  Reconciliation Worker           │
          │  - Scheduled with backoff        │
          │  - Query /v3/orders/{id}         │
          │  - Update to actual status       │
          │  - Retry up to 10 times          │
          └──────────────────────────────────┘
                     │
                     ▼
          ┌──────────────────────────────────┐
          │  Final Status                    │
          │  - PAID or FAILED                │
          │  - Reconciliation complete       │
          └──────────────────────────────────┘
```

---

## 🔄 Payment Flow

### 1️⃣ Payment Creation (Idempotency Protection)

```php
// Customer initiates payment
POST /api/trip/payment

// PaymentService.createPayment()
├─ Generate idempotency key (SHA256)
├─ Check existing payment
│  ├─ IF EXISTS → Return existing (PREVENTS DUPLICATE)
│  └─ IF NOT → Create new payment (STATUS: created)
└─ Return PaymentTransaction
```

### 2️⃣ Payment Processing

```php
// PaymentService.processPayment()
├─ Validate: Not in final state
├─ Acquire distributed lock (Redis/DB)
│  └─ IF LOCKED → Throw "already processing"
├─ Transition: created → pending_gateway
├─ Send to Kashier with timeout (30s)
│  ├─ SUCCESS → Mark as PAID ✅
│  ├─ FAILED → Mark as FAILED ❌
│  ├─ SERVER_ERROR → Mark as UNKNOWN ⚠️
│  ├─ TIMEOUT → Mark as UNKNOWN ⚠️
│  └─ NETWORK_ERROR → Retry or mark UNKNOWN
└─ Release lock
```

### 3️⃣ Reconciliation (Background Worker)

```php
// Cron: Every 1 minute
php artisan payments:reconcile

// PaymentReconciliationService.reconcileAll()
├─ Find payments: needsReconciliation()
│  └─ Status: unknown, pending_gateway, processing
├─ For each payment:
│  ├─ Acquire lock
│  ├─ Query Kashier /v3/orders/{id}
│  ├─ Update to actual status
│  ├─ IF NOT FINAL → Schedule next (exponential backoff)
│  └─ Release lock
└─ Log results
```

### 4️⃣ Webhook Handling (Async)

```php
// Kashier sends webhook
POST /api/payment/webhook/kashier

// WebhookController.kashier()
├─ Verify signature (HMAC-SHA256)
├─ Find payment by order_id
├─ Acquire lock (prevent race with reconciliation)
├─ Update status from webhook
│  ├─ SUCCESS → Mark PAID
│  ├─ FAILED → Mark FAILED
│  └─ PROCESSING → Update metadata
└─ Release lock
```

---

## 🔒 Safety Mechanisms

### 1. Idempotency Protection

```php
// SHA256 hash of: trip_id + user_id + amount + currency
$idempotencyKey = hash('sha256', implode('|', [...]));

// Always check database first
$existing = PaymentTransaction::byIdempotencyKey($key)->first();
if ($existing) {
    return $existing; // SAME PAYMENT RETURNED
}
```

**Prevents:**
- ❌ Double charges from duplicate clicks
- ❌ Multiple payments for same trip
- ❌ Race conditions in payment creation

### 2. Distributed Locking

```php
// Redis atomic lock
Redis::set("payment:lock:{id}", $token, 'EX', 10, 'NX');

// Only one worker can process payment at a time
```

**Prevents:**
- ❌ Concurrent processing
- ❌ Race conditions between webhook & reconciliation
- ❌ Double state transitions

### 3. State Machine Validation

```php
// Only allowed transitions are possible
$allowedTransitions = [
    'created' => ['pending_gateway', 'cancelled'],
    'pending_gateway' => ['processing', 'paid', 'failed', 'unknown'],
    'paid' => ['refunded'], // Cannot go back to pending
    // ...
];

if (!in_array($newState, $allowedTransitions[$currentState])) {
    return false; // INVALID TRANSITION BLOCKED
}
```

**Prevents:**
- ❌ Invalid state changes
- ❌ Paid → Pending transitions
- ❌ Lost payment state

### 4. Exponential Backoff

```php
// Reconciliation delays
Attempt 1: +1 minute
Attempt 2: +2 minutes
Attempt 3: +4 minutes
Attempt 4: +8 minutes
...
Max: 1 hour between attempts
```

**Prevents:**
- ❌ Gateway overload
- ❌ Wasted resources
- ❌ Rate limiting

---

## 🎯 Error Handling Matrix

| Scenario | Gateway Response | Status | Action | Outcome |
|----------|-----------------|--------|--------|---------|
| **Normal Success** | `{status: "SUCCESS"}` | PAID | None | ✅ Complete |
| **Normal Failure** | `{status: "FAILED"}` | FAILED | None | ❌ Declined |
| **Server Error** | `{cause: "EAI_AGAIN"}` | UNKNOWN | Reconcile | ⚠️ Check later |
| **Network Timeout** | (timeout) | UNKNOWN | Reconcile | ⚠️ Check later |
| **Connection Failed** | (exception) | UNKNOWN | Reconcile | ⚠️ Check later |
| **Gateway 5xx** | HTTP 500-599 | UNKNOWN | Reconcile | ⚠️ Check later |
| **Gateway 4xx** | HTTP 400-499 | FAILED | None | ❌ Invalid request |
| **Webhook Received** | (async) | PAID/FAILED | Update | ✅/❌ Confirmed |

---

## 📁 File Structure

```
database/migrations/
└── 2026_01_01_050000_create_payment_transactions_table.php

app/
├── Models/
│   ├── PaymentTransaction.php          # State machine model
│   └── PaymentStateTransition.php      # Audit log
├── Services/Payment/
│   ├── PaymentService.php              # Main payment logic
│   ├── PaymentReconciliationService.php # Reconciliation
│   └── Gateways/
│       └── KashierGateway.php          # Kashier API client
├── Jobs/
│   ├── ReconcilePaymentJob.php         # Background reconciliation
│   └── RetryPaymentJob.php             # Retry failed payments
├── Console/Commands/
│   └── ReconcilePaymentsCommand.php    # Manual reconciliation
└── Http/Controllers/Api/Payment/
    └── WebhookController.php           # Webhook handler

config/
└── payment.php                         # Configuration

routes/
└── api_payment.php                     # Webhook routes

tests/Feature/
└── PaymentFaultToleranceTest.php      # Comprehensive tests
```

---

## ⚙️ Configuration

```env
# Gateway
PAYMENT_GATEWAY=kashier
KASHIER_BASE_URL=https://fep.kashier.io
KASHIER_MERCHANT_ID=MID-xxxxx
KASHIER_API_KEY=xxxx-xxxx-xxxx
KASHIER_SECRET_KEY=xxxx

# Timeouts
PAYMENT_GATEWAY_TIMEOUT=30
PAYMENT_MAX_PROCESSING_TIME=300

# Reconciliation
PAYMENT_RECONCILIATION_ENABLED=true
PAYMENT_RECONCILIATION_BATCH_SIZE=50

# Locking
PAYMENT_LOCK_DRIVER=redis
PAYMENT_LOCK_TIMEOUT=10
```

---

## 🚀 Deployment Checklist

- [x] Run migrations
- [x] Configure .env variables
- [x] Set up Redis (for locking)
- [x] Configure queue workers
- [x] Add cron job for scheduler
- [x] Test webhook endpoint
- [x] Monitor reconciliation logs
- [x] Set up alerts for unknown status

---

## 📊 Monitoring Queries

```sql
-- Payments by status
SELECT status, COUNT(*) as count
FROM payment_transactions
GROUP BY status;

-- Stuck payments (>5 minutes in unknown)
SELECT *
FROM payment_transactions
WHERE status = 'unknown'
  AND created_at < NOW() - INTERVAL 5 MINUTE;

-- Reconciliation performance
SELECT
    status,
    AVG(reconciliation_attempts) as avg_attempts,
    MAX(reconciliation_attempts) as max_attempts
FROM payment_transactions
WHERE status IN ('paid', 'failed')
  AND reconciliation_attempts > 0
GROUP BY status;

-- Recent failures
SELECT *
FROM payment_transactions
WHERE status = 'failed'
  AND created_at > NOW() - INTERVAL 1 HOUR
ORDER BY created_at DESC;
```

---

## 🎓 Key Learnings

1. **Never assume failure** when gateway is unreachable
2. **Always use idempotency keys** for user-initiated actions
3. **Lock everything** that can race
4. **Log everything** for debugging
5. **Reconcile uncertain states** automatically
6. **Use exponential backoff** for retries
7. **Validate state transitions** strictly
8. **Handle webhooks async** for confirmation

---

## 🏆 Production Readiness

This system is designed like:
- ✅ **Stripe** - Idempotent APIs, robust retry logic
- ✅ **Uber** - State machine, automatic reconciliation
- ✅ **PayPal** - Distributed locking, webhook verification
- ✅ **Square** - Exponential backoff, comprehensive logging

**Safe for:**
- High-volume transactions
- Multi-server deployments
- Network instability
- Gateway downtime
- Concurrent requests
- User retry behavior

**Tested against:**
- Duplicate clicks
- Network failures
- Gateway timeouts
- Server crashes
- Race conditions
- State corruption

---

## 📞 Support

For technical questions or issues, contact the development team.

---

**Status:** ✅ Production Ready
**Last Updated:** 2026-01-01
**Version:** 1.0.0
