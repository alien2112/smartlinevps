<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentTransaction extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'trip_request_id',
        'user_id',
        'payment_request_id',
        'amount',
        'currency',
        'gateway',
        'idempotency_key',
        'gateway_order_id',
        'gateway_transaction_id',
        'status',
        'previous_status',
        'gateway_request',
        'gateway_response',
        'gateway_error',
        'error_message',
        'error_code',
        'last_reconciliation_at',
        'reconciliation_attempts',
        'next_reconciliation_at',
        'retry_count',
        'last_retry_at',
        'gateway_sent_at',
        'gateway_responded_at',
        'response_time_ms',
        'lock_token',
        'locked_at',
        'locked_by',
        'webhook_received',
        'webhook_received_at',
        'webhook_payload',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_request' => 'array',
        'gateway_response' => 'array',
        'gateway_error' => 'array',
        'webhook_payload' => 'array',
        'metadata' => 'array',
        'last_reconciliation_at' => 'datetime',
        'next_reconciliation_at' => 'datetime',
        'last_retry_at' => 'datetime',
        'gateway_sent_at' => 'datetime',
        'gateway_responded_at' => 'datetime',
        'locked_at' => 'datetime',
        'webhook_received_at' => 'datetime',
        'webhook_received' => 'boolean',
        'reconciliation_attempts' => 'integer',
        'retry_count' => 'integer',
        'response_time_ms' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | State Machine Constants
    |--------------------------------------------------------------------------
    */

    public const STATUS_CREATED = 'created';
    public const STATUS_PENDING_GATEWAY = 'pending_gateway';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_UNKNOWN = 'unknown';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_CANCELLED = 'cancelled';

    public const FINAL_STATUSES = [
        self::STATUS_PAID,
        self::STATUS_FAILED,
        self::STATUS_REFUNDED,
        self::STATUS_CANCELLED,
    ];

    public const RECONCILIATION_NEEDED_STATUSES = [
        self::STATUS_PENDING_GATEWAY,
        self::STATUS_PROCESSING,
        self::STATUS_UNKNOWN,
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function tripRequest(): BelongsTo
    {
        return $this->belongsTo(\Modules\TripManagement\Entities\TripRequest::class, 'trip_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stateTransitions(): HasMany
    {
        return $this->hasMany(PaymentStateTransition::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeNeedsReconciliation($query)
    {
        return $query->whereIn('status', self::RECONCILIATION_NEEDED_STATUSES)
                    ->where(function ($q) {
                        $q->whereNull('next_reconciliation_at')
                          ->orWhere('next_reconciliation_at', '<=', now());
                    })
                    ->where(function ($q) {
                        $q->whereNull('reconciliation_attempts')
                          ->orWhere('reconciliation_attempts', '<', config('payment.reconciliation.max_attempts'));
                    });
    }

    public function scopePending($query)
    {
        return $query->whereNotIn('status', self::FINAL_STATUSES);
    }

    public function scopeStuck($query)
    {
        $stuckThreshold = now()->subMinutes(config('payment.timeout.max_processing_time') / 60);

        return $query->whereIn('status', self::RECONCILIATION_NEEDED_STATUSES)
                    ->where('created_at', '<', $stuckThreshold);
    }

    public function scopeByGatewayOrderId($query, string $gatewayOrderId)
    {
        return $query->where('gateway_order_id', $gatewayOrderId);
    }

    public function scopeByIdempotencyKey($query, string $key)
    {
        return $query->where('idempotency_key', $key);
    }

    /*
    |--------------------------------------------------------------------------
    | State Machine Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Transition to a new state with validation and logging
     */
    public function transitionTo(string $newStatus, string $trigger = 'system', ?array $context = null): bool
    {
        if (!$this->canTransitionTo($newStatus)) {
            Log::warning('Invalid state transition attempted', [
                'payment_id' => $this->id,
                'from' => $this->status,
                'to' => $newStatus,
                'trigger' => $trigger,
            ]);
            return false;
        }

        return DB::transaction(function () use ($newStatus, $trigger, $context) {
            $oldStatus = $this->status;

            // Update status
            $this->previous_status = $oldStatus;
            $this->status = $newStatus;
            $this->save();

            // Log transition
            $this->stateTransitions()->create([
                'from_state' => $oldStatus,
                'to_state' => $newStatus,
                'trigger' => $trigger,
                'context' => $context,
                'transitioned_by' => auth()->id() ?? 'system',
                'transitioned_at' => now(),
            ]);

            Log::info('Payment state transitioned', [
                'payment_id' => $this->id,
                'from' => $oldStatus,
                'to' => $newStatus,
                'trigger' => $trigger,
            ]);

            return true;
        });
    }

    /**
     * Check if transition to new status is allowed
     */
    public function canTransitionTo(string $newStatus): bool
    {
        $allowedTransitions = config('payment.state_machine.allowed_transitions');

        if (!isset($allowedTransitions[$this->status])) {
            return false;
        }

        return in_array($newStatus, $allowedTransitions[$this->status]);
    }

    /**
     * Check if payment is in a final state
     */
    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL_STATUSES);
    }

    /**
     * Check if payment needs reconciliation
     */
    public function needsReconciliation(): bool
    {
        return in_array($this->status, self::RECONCILIATION_NEEDED_STATUSES) &&
               ($this->reconciliation_attempts < config('payment.reconciliation.max_attempts'));
    }

    /**
     * Mark as pending gateway
     */
    public function markAsPendingGateway(array $request): void
    {
        $this->gateway_request = $request;
        $this->gateway_sent_at = now();
        $this->transitionTo(self::STATUS_PENDING_GATEWAY, 'gateway_request_sent', [
            'request' => $request,
        ]);
    }

    /**
     * Mark as unknown (needs reconciliation)
     */
    public function markAsUnknown(array $errorData): void
    {
        $this->gateway_error = $errorData;
        $this->error_message = $errorData['message'] ?? null;
        $this->error_code = $errorData['code'] ?? null;
        $this->scheduleNextReconciliation();
        $this->transitionTo(self::STATUS_UNKNOWN, 'gateway_error', $errorData);
    }

    /**
     * Mark as paid
     */
    public function markAsPaid(array $response, string $trigger = 'gateway_response'): void
    {
        $this->gateway_response = $response;
        $this->gateway_responded_at = now();
        $this->calculateResponseTime();
        $this->transitionTo(self::STATUS_PAID, $trigger, $response);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(array $response, string $trigger = 'gateway_response'): void
    {
        $this->gateway_response = $response;
        $this->gateway_responded_at = now();
        $this->calculateResponseTime();
        $this->error_message = $response['error'] ?? $response['message'] ?? null;
        $this->error_code = $response['code'] ?? null;
        $this->transitionTo(self::STATUS_FAILED, $trigger, $response);
    }

    /**
     * Schedule next reconciliation attempt
     */
    public function scheduleNextReconciliation(): void
    {
        $this->reconciliation_attempts = ($this->reconciliation_attempts ?? 0) + 1;
        $this->last_reconciliation_at = now();

        // Exponential backoff
        $delay = $this->calculateReconciliationDelay();
        $this->next_reconciliation_at = now()->addSeconds($delay);

        $this->save();
    }

    /**
     * Calculate reconciliation delay using exponential backoff
     */
    private function calculateReconciliationDelay(): int
    {
        $initialDelay = config('payment.reconciliation.initial_delay');
        $maxDelay = config('payment.reconciliation.max_delay');
        $attempt = $this->reconciliation_attempts ?? 0;

        if (config('payment.reconciliation.backoff_strategy') === 'exponential') {
            $delay = min($initialDelay * pow(2, $attempt), $maxDelay);
        } else {
            $delay = min($initialDelay * $attempt, $maxDelay);
        }

        return (int) $delay;
    }

    /**
     * Calculate response time
     */
    private function calculateResponseTime(): void
    {
        if ($this->gateway_sent_at && $this->gateway_responded_at) {
            $this->response_time_ms = $this->gateway_sent_at->diffInMilliseconds($this->gateway_responded_at);
        }
    }

    /**
     * Lock this transaction for processing
     */
    public function acquireLock(int $timeout = 10): bool
    {
        $lockToken = \Illuminate\Support\Str::uuid()->toString();
        $lockExpiry = now()->addSeconds($timeout);

        $updated = DB::table('payment_transactions')
            ->where('id', $this->id)
            ->whereNull('locked_at')
            ->orWhere('locked_at', '<', now()->subSeconds($timeout))
            ->update([
                'lock_token' => $lockToken,
                'locked_at' => now(),
                'locked_by' => gethostname() . ':' . getmypid(),
            ]);

        if ($updated) {
            $this->lock_token = $lockToken;
            $this->locked_at = now();
            $this->locked_by = gethostname() . ':' . getmypid();
            return true;
        }

        return false;
    }

    /**
     * Release lock
     */
    public function releaseLock(): void
    {
        if ($this->lock_token) {
            DB::table('payment_transactions')
                ->where('id', $this->id)
                ->where('lock_token', $this->lock_token)
                ->update([
                    'lock_token' => null,
                    'locked_at' => null,
                    'locked_by' => null,
                ]);

            $this->lock_token = null;
            $this->locked_at = null;
            $this->locked_by = null;
        }
    }

    /**
     * Check if transaction is locked
     */
    public function isLocked(): bool
    {
        if (!$this->locked_at) {
            return false;
        }

        $lockTimeout = config('payment.locking.timeout', 10);
        return $this->locked_at->isAfter(now()->subSeconds($lockTimeout));
    }
}
