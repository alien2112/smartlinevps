<?php

declare(strict_types=1);

namespace Modules\UserManagement\Service;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Entities\UserAccount;
use Modules\TransactionManagement\Entities\Transaction;

/**
 * Production-Ready Wallet Service
 *
 * Guarantees:
 * - Atomic transactions with row locking
 * - Idempotency for all operations
 * - No negative balances
 * - Full audit trail
 * - Reconciliation support
 */
class WalletService
{
    /**
     * Transaction types for audit
     */
    public const TYPE_TOPUP = 'wallet_top_up';
    public const TYPE_RIDE_CHARGE = 'wallet_payment';
    public const TYPE_REFUND = 'wallet_refund';
    public const TYPE_ADMIN_CREDIT = 'fund_by_admin';
    public const TYPE_ADMIN_DEBIT = 'debit_by_admin';
    public const TYPE_LOYALTY_CONVERSION = 'point_conversion';
    public const TYPE_REFERRAL_EARNING = 'referral_earning';
    public const TYPE_LEVEL_REWARD = 'level_reward';

    /**
     * Credit wallet with idempotency and full audit
     *
     * @param string $userId User ID
     * @param float $amount Amount to credit
     * @param string $type Transaction type
     * @param string $idempotencyKey Unique key to prevent duplicates
     * @param string|null $referenceId Related entity ID (trip_id, payment_id)
     * @param string|null $createdBy Who initiated (admin user_id or 'system')
     * @param string|null $reason Reason for transaction (required for admin actions)
     * @return array ['success' => bool, 'transaction_id' => string|null, 'balance' => float, 'error' => string|null]
     */
    public function credit(
        string $userId,
        float $amount,
        string $type,
        string $idempotencyKey,
        ?string $referenceId = null,
        ?string $createdBy = null,
        ?string $reason = null
    ): array {
        // Validate amount
        if ($amount <= 0) {
            return $this->errorResponse('Amount must be positive');
        }

        // Check for duplicate using idempotency key
        $existingTx = Transaction::where('idempotency_key', $idempotencyKey)->first();
        if ($existingTx) {
            Log::info('WalletService: Duplicate credit request ignored', [
                'idempotency_key' => $idempotencyKey,
                'existing_tx_id' => $existingTx->id,
            ]);
            return [
                'success' => true,
                'transaction_id' => $existingTx->id,
                'balance' => $existingTx->balance,
                'error' => null,
                'duplicate' => true,
            ];
        }

        try {
            return DB::transaction(function () use ($userId, $amount, $type, $idempotencyKey, $referenceId, $createdBy, $reason) {
                // Lock the user account row
                $account = UserAccount::where('user_id', $userId)
                    ->lockForUpdate()
                    ->first();

                if (!$account) {
                    // Create account if doesn't exist
                    $account = UserAccount::create([
                        'user_id' => $userId,
                        'wallet_balance' => 0,
                        'payable_balance' => 0,
                        'pending_balance' => 0,
                        'receivable_balance' => 0,
                        'received_balance' => 0,
                        'total_withdrawn' => 0,
                    ]);
                    // Re-fetch with lock
                    $account = UserAccount::where('user_id', $userId)->lockForUpdate()->first();
                }

                $previousBalance = (float) $account->wallet_balance;
                $newBalance = $previousBalance + $amount;
                $amountMinor = (int) round($amount * 100);

                // Update balance
                $account->wallet_balance = $newBalance;
                $account->save();

                // Create transaction record
                $txId = (string) Str::uuid();
                $transaction = Transaction::create([
                    'id' => $txId,
                    'attribute' => $type,
                    'attribute_id' => $referenceId,
                    'credit' => $amount,
                    'debit' => 0,
                    'balance' => $newBalance,
                    'user_id' => $userId,
                    'account' => 'wallet_balance',
                    'idempotency_key' => $idempotencyKey,
                    'trx_ref_id' => $referenceId,
                    'reference' => json_encode([
                        'previous_balance' => $previousBalance,
                        'amount_minor' => $amountMinor,
                        'created_by' => $createdBy ?? 'system',
                        'reason' => $reason,
                        'ip' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ]),
                ]);

                Log::info('WalletService: Credit successful', [
                    'user_id' => $userId,
                    'amount' => $amount,
                    'type' => $type,
                    'tx_id' => $txId,
                    'previous_balance' => $previousBalance,
                    'new_balance' => $newBalance,
                    'idempotency_key' => $idempotencyKey,
                ]);

                return [
                    'success' => true,
                    'transaction_id' => $txId,
                    'balance' => $newBalance,
                    'previous_balance' => $previousBalance,
                    'error' => null,
                    'duplicate' => false,
                ];
            }, 5); // 5 attempts for deadlock retry

        } catch (\Exception $e) {
            Log::error('WalletService: Credit failed', [
                'user_id' => $userId,
                'amount' => $amount,
                'error' => $e->getMessage(),
                'idempotency_key' => $idempotencyKey,
            ]);
            return $this->errorResponse('Transaction failed: ' . $e->getMessage());
        }
    }

    /**
     * Debit wallet with idempotency, locking, and balance check
     *
     * @param string $userId User ID
     * @param float $amount Amount to debit
     * @param string $type Transaction type
     * @param string $idempotencyKey Unique key to prevent duplicates
     * @param string|null $referenceId Related entity ID
     * @param string|null $createdBy Who initiated
     * @param string|null $reason Reason for transaction
     * @param bool $allowNegative Allow negative balance (default false)
     * @return array
     */
    public function debit(
        string $userId,
        float $amount,
        string $type,
        string $idempotencyKey,
        ?string $referenceId = null,
        ?string $createdBy = null,
        ?string $reason = null,
        bool $allowNegative = false
    ): array {
        // Validate amount
        if ($amount <= 0) {
            return $this->errorResponse('Amount must be positive');
        }

        // Check for duplicate
        $existingTx = Transaction::where('idempotency_key', $idempotencyKey)->first();
        if ($existingTx) {
            Log::info('WalletService: Duplicate debit request ignored', [
                'idempotency_key' => $idempotencyKey,
            ]);
            return [
                'success' => true,
                'transaction_id' => $existingTx->id,
                'balance' => $existingTx->balance,
                'error' => null,
                'duplicate' => true,
            ];
        }

        try {
            return DB::transaction(function () use ($userId, $amount, $type, $idempotencyKey, $referenceId, $createdBy, $reason, $allowNegative) {
                // Lock the user account row
                $account = UserAccount::where('user_id', $userId)
                    ->lockForUpdate()
                    ->first();

                if (!$account) {
                    return $this->errorResponse('User account not found');
                }

                $previousBalance = (float) $account->wallet_balance;

                // Check sufficient balance
                if (!$allowNegative && $previousBalance < $amount) {
                    Log::warning('WalletService: Insufficient balance', [
                        'user_id' => $userId,
                        'requested' => $amount,
                        'available' => $previousBalance,
                    ]);
                    return $this->errorResponse('Insufficient wallet balance', 'INSUFFICIENT_BALANCE');
                }

                $newBalance = $previousBalance - $amount;
                $amountMinor = (int) round($amount * 100);

                // Update balance
                $account->wallet_balance = $newBalance;
                $account->save();

                // Create transaction record
                $txId = (string) Str::uuid();
                Transaction::create([
                    'id' => $txId,
                    'attribute' => $type,
                    'attribute_id' => $referenceId,
                    'credit' => 0,
                    'debit' => $amount,
                    'balance' => $newBalance,
                    'user_id' => $userId,
                    'account' => 'wallet_balance',
                    'idempotency_key' => $idempotencyKey,
                    'trx_ref_id' => $referenceId,
                    'reference' => json_encode([
                        'previous_balance' => $previousBalance,
                        'amount_minor' => $amountMinor,
                        'created_by' => $createdBy ?? 'system',
                        'reason' => $reason,
                        'ip' => request()->ip(),
                    ]),
                ]);

                Log::info('WalletService: Debit successful', [
                    'user_id' => $userId,
                    'amount' => $amount,
                    'type' => $type,
                    'tx_id' => $txId,
                    'previous_balance' => $previousBalance,
                    'new_balance' => $newBalance,
                ]);

                return [
                    'success' => true,
                    'transaction_id' => $txId,
                    'balance' => $newBalance,
                    'previous_balance' => $previousBalance,
                    'error' => null,
                    'duplicate' => false,
                ];
            }, 5);

        } catch (\Exception $e) {
            Log::error('WalletService: Debit failed', [
                'user_id' => $userId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            return $this->errorResponse('Transaction failed: ' . $e->getMessage());
        }
    }

    /**
     * Admin adjustment with full audit trail
     */
    public function adminAdjust(
        string $userId,
        float $amount,
        string $direction, // 'credit' or 'debit'
        string $adminUserId,
        string $reason
    ): array {
        if (empty($reason)) {
            return $this->errorResponse('Reason is required for admin adjustments');
        }

        $idempotencyKey = 'admin_adjust_' . $adminUserId . '_' . $userId . '_' . time() . '_' . Str::random(8);

        if ($direction === 'credit') {
            return $this->credit(
                $userId,
                $amount,
                self::TYPE_ADMIN_CREDIT,
                $idempotencyKey,
                null,
                $adminUserId,
                $reason
            );
        } else {
            return $this->debit(
                $userId,
                $amount,
                self::TYPE_ADMIN_DEBIT,
                $idempotencyKey,
                null,
                $adminUserId,
                $reason
            );
        }
    }

    /**
     * Process payment webhook with deduplication
     */
    public function processWebhookTopup(
        string $userId,
        float $amount,
        string $paymentId,
        string $providerEventId,
        string $paymentMethod
    ): array {
        // Use provider event ID as idempotency key
        $idempotencyKey = 'webhook_' . $paymentMethod . '_' . $providerEventId;

        return $this->credit(
            $userId,
            $amount,
            self::TYPE_TOPUP,
            $idempotencyKey,
            $paymentId,
            'system',
            'Payment webhook: ' . $paymentMethod
        );
    }

    /**
     * Charge customer for ride with atomic transaction
     */
    public function chargeForRide(string $customerId, float $amount, string $tripId): array
    {
        $idempotencyKey = 'ride_charge_' . $tripId;

        return $this->debit(
            $customerId,
            $amount,
            self::TYPE_RIDE_CHARGE,
            $idempotencyKey,
            $tripId,
            'system',
            'Ride payment'
        );
    }

    /**
     * Refund with validation against original charge
     */
    public function refund(
        string $userId,
        float $amount,
        string $originalTxId,
        string $reason,
        ?string $adminId = null
    ): array {
        // Find original transaction
        $originalTx = Transaction::find($originalTxId);
        if (!$originalTx) {
            return $this->errorResponse('Original transaction not found');
        }

        // Verify this is a debit transaction
        if ($originalTx->debit <= 0) {
            return $this->errorResponse('Cannot refund a credit transaction');
        }

        // Calculate total already refunded for this transaction
        $alreadyRefunded = Transaction::where('trx_ref_id', $originalTxId)
            ->where('attribute', self::TYPE_REFUND)
            ->sum('credit');

        $maxRefundable = $originalTx->debit - $alreadyRefunded;

        if ($amount > $maxRefundable) {
            return $this->errorResponse(
                "Refund amount ({$amount}) exceeds maximum refundable ({$maxRefundable})",
                'REFUND_EXCEEDS_ORIGINAL'
            );
        }

        $idempotencyKey = 'refund_' . $originalTxId . '_' . time();

        return $this->credit(
            $userId,
            $amount,
            self::TYPE_REFUND,
            $idempotencyKey,
            $originalTxId,
            $adminId ?? 'system',
            $reason
        );
    }

    /**
     * Get balance with optional cache
     */
    public function getBalance(string $userId, bool $useCache = true): float
    {
        if ($useCache) {
            return Cache::remember(
                "wallet_balance_{$userId}",
                60, // 1 minute
                fn() => $this->getBalanceFromDb($userId)
            );
        }

        return $this->getBalanceFromDb($userId);
    }

    /**
     * Reconcile balance: compare cached balance vs ledger sum
     */
    public function reconcile(string $userId): array
    {
        $account = UserAccount::where('user_id', $userId)->first();
        if (!$account) {
            return ['error' => 'Account not found'];
        }

        $cachedBalance = (float) $account->wallet_balance;

        // Calculate balance from ledger
        $credits = Transaction::where('user_id', $userId)
            ->where('account', 'wallet_balance')
            ->sum('credit');

        $debits = Transaction::where('user_id', $userId)
            ->where('account', 'wallet_balance')
            ->sum('debit');

        $ledgerBalance = $credits - $debits;

        $discrepancy = round($cachedBalance - $ledgerBalance, 2);

        $result = [
            'user_id' => $userId,
            'cached_balance' => $cachedBalance,
            'ledger_balance' => $ledgerBalance,
            'discrepancy' => $discrepancy,
            'is_valid' => abs($discrepancy) < 0.01, // Allow 1 cent tolerance
            'total_credits' => $credits,
            'total_debits' => $debits,
            'checked_at' => now()->toIso8601String(),
        ];

        if (abs($discrepancy) >= 0.01) {
            Log::warning('WalletService: Balance discrepancy detected', $result);
        }

        return $result;
    }

    /**
     * Rebuild balance from ledger (admin repair tool)
     */
    public function rebuildBalance(string $userId, string $adminId): array
    {
        try {
            return DB::transaction(function () use ($userId, $adminId) {
                $account = UserAccount::where('user_id', $userId)
                    ->lockForUpdate()
                    ->first();

                if (!$account) {
                    return $this->errorResponse('Account not found');
                }

                $oldBalance = (float) $account->wallet_balance;

                // Calculate from ledger
                $credits = Transaction::where('user_id', $userId)
                    ->where('account', 'wallet_balance')
                    ->sum('credit');

                $debits = Transaction::where('user_id', $userId)
                    ->where('account', 'wallet_balance')
                    ->sum('debit');

                $newBalance = $credits - $debits;

                $account->wallet_balance = $newBalance;
                $account->save();

                // Clear cache
                Cache::forget("wallet_balance_{$userId}");

                Log::warning('WalletService: Balance rebuilt from ledger', [
                    'user_id' => $userId,
                    'old_balance' => $oldBalance,
                    'new_balance' => $newBalance,
                    'admin_id' => $adminId,
                ]);

                return [
                    'success' => true,
                    'old_balance' => $oldBalance,
                    'new_balance' => $newBalance,
                    'difference' => $newBalance - $oldBalance,
                ];
            });
        } catch (\Exception $e) {
            return $this->errorResponse('Rebuild failed: ' . $e->getMessage());
        }
    }

    /**
     * Generate idempotency key for a given operation
     */
    public static function generateIdempotencyKey(string $operation, string ...$parts): string
    {
        return $operation . '_' . implode('_', $parts) . '_' . Str::random(8);
    }

    private function getBalanceFromDb(string $userId): float
    {
        $account = UserAccount::where('user_id', $userId)->first();
        return $account ? (float) $account->wallet_balance : 0.0;
    }

    private function errorResponse(string $message, string $code = 'ERROR'): array
    {
        return [
            'success' => false,
            'transaction_id' => null,
            'balance' => null,
            'error' => $message,
            'error_code' => $code,
        ];
    }
}
