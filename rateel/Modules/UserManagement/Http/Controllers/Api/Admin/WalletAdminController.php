<?php

declare(strict_types=1);

namespace Modules\UserManagement\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\UserManagement\Service\WalletService;
use Modules\UserManagement\Entities\UserAccount;
use Modules\UserManagement\Entities\User;
use Modules\TransactionManagement\Entities\Transaction;

/**
 * Admin Wallet Management API Controller
 *
 * Provides admin endpoints for:
 * - Viewing wallet balances
 * - Making adjustments with audit trail
 * - Reconciliation
 * - Transaction history
 */
class WalletAdminController extends Controller
{
    public function __construct(
        private readonly WalletService $walletService
    ) {}

    /**
     * Get wallet balance and details for a user
     *
     * GET /api/admin/wallet/{userId}
     */
    public function show(string $userId): JsonResponse
    {
        $account = UserAccount::where('user_id', $userId)->first();

        if (!$account) {
            return response()->json(responseFormatter(
                constant: DEFAULT_404,
                content: ['message' => 'User account not found']
            ), 404);
        }

        $recentTransactions = Transaction::where('user_id', $userId)
            ->where('account', 'wallet_balance')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($tx) => [
                'id' => $tx->id,
                'type' => $tx->credit > 0 ? 'credit' : 'debit',
                'amount' => $tx->credit > 0 ? $tx->credit : $tx->debit,
                'balance_after' => $tx->balance,
                'attribute' => $tx->attribute,
                'created_at' => $tx->created_at->toIso8601String(),
            ]);

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: [
                'user_id' => $userId,
                'wallet_balance' => (float) $account->wallet_balance,
                'formatted_balance' => getCurrencyFormat($account->wallet_balance),
                'payable_balance' => (float) $account->payable_balance,
                'receivable_balance' => (float) $account->receivable_balance,
                'received_balance' => (float) $account->received_balance,
                'pending_balance' => (float) $account->pending_balance,
                'total_withdrawn' => (float) $account->total_withdrawn,
                'recent_transactions' => $recentTransactions,
            ]
        ));
    }

    /**
     * Make an admin adjustment to a user's wallet
     *
     * POST /api/admin/wallet/{userId}/adjust
     *
     * Body:
     * {
     *   "amount": 100.00,
     *   "direction": "credit" | "debit",
     *   "reason": "Customer complaint resolution #12345"
     * }
     */
    public function adjust(Request $request, string $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01|max:1000000',
            'direction' => 'required|in:credit,debit',
            'reason' => 'required|string|min:10|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $adminUser = auth('api')->user();
        if (!$adminUser || !in_array($adminUser->user_type, ['super-admin', 'admin-employee'])) {
            return response()->json(responseFormatter(
                constant: DEFAULT_403,
                content: ['message' => 'Admin access required']
            ), 403);
        }

        $result = $this->walletService->adminAdjust(
            userId: $userId,
            amount: (float) $request->amount,
            direction: $request->direction,
            adminUserId: $adminUser->id,
            reason: $request->reason
        );

        if (!$result['success']) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                content: ['message' => $result['error']]
            ), 400);
        }

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: [
                'message' => 'Wallet adjusted successfully',
                'transaction_id' => $result['transaction_id'],
                'new_balance' => $result['balance'],
                'formatted_balance' => getCurrencyFormat($result['balance']),
            ]
        ));
    }

    /**
     * Reconcile a user's wallet balance against ledger
     *
     * GET /api/admin/wallet/{userId}/reconcile
     */
    public function reconcile(string $userId): JsonResponse
    {
        $result = $this->walletService->reconcile($userId);

        if (isset($result['error'])) {
            return response()->json(responseFormatter(
                constant: DEFAULT_404,
                content: ['message' => $result['error']]
            ), 404);
        }

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: $result
        ));
    }

    /**
     * Rebuild wallet balance from ledger (repair tool)
     *
     * POST /api/admin/wallet/{userId}/rebuild
     */
    public function rebuild(Request $request, string $userId): JsonResponse
    {
        $adminUser = auth('api')->user();
        if (!$adminUser || $adminUser->user_type !== 'super-admin') {
            return response()->json(responseFormatter(
                constant: DEFAULT_403,
                content: ['message' => 'Super admin access required']
            ), 403);
        }

        // Require confirmation
        if ($request->input('confirm') !== 'REBUILD') {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                content: ['message' => 'Confirmation required. Send {"confirm": "REBUILD"}']
            ), 400);
        }

        $result = $this->walletService->rebuildBalance($userId, $adminUser->id);

        if (!$result['success']) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                content: ['message' => $result['error']]
            ), 400);
        }

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: [
                'message' => 'Wallet balance rebuilt from ledger',
                'old_balance' => $result['old_balance'],
                'new_balance' => $result['new_balance'],
                'difference' => $result['difference'],
            ]
        ));
    }

    /**
     * Get transaction history for a user with filtering
     *
     * GET /api/admin/wallet/{userId}/transactions
     */
    public function transactions(Request $request, string $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'sometimes|integer|min:1|max:100',
            'offset' => 'sometimes|integer|min:1',
            'type' => 'sometimes|in:all,credit,debit',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $limit = $request->input('limit', 20);
        $offset = $request->input('offset', 1);
        $type = $request->input('type', 'all');

        $query = Transaction::where('user_id', $userId)
            ->where('account', 'wallet_balance')
            ->orderBy('created_at', 'desc');

        if ($type === 'credit') {
            $query->where('credit', '>', 0);
        } elseif ($type === 'debit') {
            $query->where('debit', '>', 0);
        }

        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $total = $query->count();
        $transactions = $query->skip(($offset - 1) * $limit)->take($limit)->get();

        $formatted = $transactions->map(fn($tx) => [
            'id' => $tx->id,
            'type' => $tx->credit > 0 ? 'credit' : 'debit',
            'amount' => $tx->credit > 0 ? $tx->credit : $tx->debit,
            'formatted_amount' => getCurrencyFormat($tx->credit > 0 ? $tx->credit : $tx->debit),
            'balance_after' => $tx->balance,
            'attribute' => $tx->attribute,
            'attribute_id' => $tx->attribute_id,
            'reference' => $tx->trx_ref_id,
            'idempotency_key' => $tx->idempotency_key,
            'created_at' => $tx->created_at->toIso8601String(),
        ]);

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: [
                'transactions' => $formatted,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
            ]
        ));
    }

    /**
     * Get wallet statistics for dashboard
     *
     * GET /api/admin/wallet/stats
     */
    public function stats(): JsonResponse
    {
        $totalWalletBalance = UserAccount::sum('wallet_balance');
        $totalPayable = UserAccount::sum('payable_balance');
        $totalReceivable = UserAccount::sum('receivable_balance');
        $totalWithdrawn = UserAccount::sum('total_withdrawn');

        $todayCredits = Transaction::where('account', 'wallet_balance')
            ->whereDate('created_at', today())
            ->sum('credit');

        $todayDebits = Transaction::where('account', 'wallet_balance')
            ->whereDate('created_at', today())
            ->sum('debit');

        $topupCount = Transaction::where('attribute', 'wallet_top_up')
            ->whereDate('created_at', today())
            ->count();

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: [
                'total_wallet_balance' => (float) $totalWalletBalance,
                'formatted_total' => getCurrencyFormat($totalWalletBalance),
                'total_payable' => (float) $totalPayable,
                'total_receivable' => (float) $totalReceivable,
                'total_withdrawn' => (float) $totalWithdrawn,
                'today' => [
                    'credits' => (float) $todayCredits,
                    'debits' => (float) $todayDebits,
                    'net' => (float) ($todayCredits - $todayDebits),
                    'topup_count' => $topupCount,
                ],
            ]
        ));
    }

    /**
     * List all users with wallet information
     *
     * GET /api/admin/wallet/users
     */
    public function listUsers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'sometimes|integer|min:1|max:100',
            'offset' => 'sometimes|integer|min:1',
            'user_type' => 'sometimes|in:customer,driver,all',
            'sort_by' => 'sometimes|in:wallet_balance,receivable_balance,payable_balance,created_at',
            'sort_order' => 'sometimes|in:asc,desc',
            'search' => 'sometimes|string|max:100',
            'min_balance' => 'sometimes|numeric',
            'max_balance' => 'sometimes|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $limit = $request->input('limit', 20);
        $offset = $request->input('offset', 1);
        $userType = $request->input('user_type', 'all');
        $sortBy = $request->input('sort_by', 'wallet_balance');
        $sortOrder = $request->input('sort_order', 'desc');

        $query = UserAccount::with(['user:id,first_name,last_name,phone,email,user_type']);

        // Filter by user type
        if ($userType !== 'all') {
            $query->whereHas('user', function ($q) use ($userType) {
                $q->where('user_type', $userType);
            });
        }

        // Exclude admin users
        $query->whereHas('user', function ($q) {
            $q->whereIn('user_type', ['customer', 'driver']);
        });

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Balance filters
        if ($request->has('min_balance')) {
            $query->where('wallet_balance', '>=', $request->min_balance);
        }
        if ($request->has('max_balance')) {
            $query->where('wallet_balance', '<=', $request->max_balance);
        }

        // Sort
        $query->orderBy($sortBy, $sortOrder);

        $total = $query->count();
        $accounts = $query->skip(($offset - 1) * $limit)->take($limit)->get();

        $formatted = $accounts->map(fn($account) => [
            'user_id' => $account->user_id,
            'user' => $account->user ? [
                'name' => $account->user->first_name . ' ' . $account->user->last_name,
                'phone' => $account->user->phone,
                'email' => $account->user->email,
                'type' => $account->user->user_type,
            ] : null,
            'wallet_balance' => (float) $account->wallet_balance,
            'formatted_wallet' => getCurrencyFormat($account->wallet_balance),
            'receivable_balance' => (float) $account->receivable_balance,
            'payable_balance' => (float) $account->payable_balance,
            'pending_balance' => (float) $account->pending_balance,
            'total_withdrawn' => (float) $account->total_withdrawn,
        ]);

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: [
                'users' => $formatted,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
            ]
        ));
    }

    /**
     * Refund a transaction
     *
     * POST /api/admin/wallet/{userId}/refund
     */
    public function refund(Request $request, string $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|string|exists:transactions,id',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|min:10|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $adminUser = auth('api')->user();
        if (!$adminUser || !in_array($adminUser->user_type, ['super-admin', 'admin-employee'])) {
            return response()->json(responseFormatter(
                constant: DEFAULT_403,
                content: ['message' => 'Admin access required']
            ), 403);
        }

        $result = $this->walletService->refund(
            userId: $userId,
            amount: (float) $request->amount,
            originalTxId: $request->transaction_id,
            reason: $request->reason,
            adminId: $adminUser->id
        );

        if (!$result['success']) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                content: ['message' => $result['error'], 'error_code' => $result['error_code'] ?? 'ERROR']
            ), 400);
        }

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: [
                'message' => 'Refund processed successfully',
                'transaction_id' => $result['transaction_id'],
                'new_balance' => $result['balance'],
                'formatted_balance' => getCurrencyFormat($result['balance']),
            ]
        ));
    }

    /**
     * Get driver wallet/earnings details
     *
     * GET /api/admin/wallet/{userId}/driver-earnings
     */
    public function driverEarnings(Request $request, string $userId): JsonResponse
    {
        $user = User::find($userId);

        if (!$user || $user->user_type !== 'driver') {
            return response()->json(responseFormatter(
                constant: DEFAULT_404,
                content: ['message' => 'Driver not found']
            ), 404);
        }

        $account = UserAccount::where('user_id', $userId)->first();

        if (!$account) {
            return response()->json(responseFormatter(
                constant: DEFAULT_404,
                content: ['message' => 'Driver account not found']
            ), 404);
        }

        // Get earnings breakdown
        $totalEarnings = Transaction::where('user_id', $userId)
            ->where('account', 'receivable_balance')
            ->sum('credit');

        $totalPayable = Transaction::where('user_id', $userId)
            ->where('account', 'payable_balance')
            ->sum('credit');

        $totalCashCollected = Transaction::where('user_id', $userId)
            ->where('attribute', 'admin_cash_collect')
            ->where('account', 'payable_balance')
            ->sum('debit');

        $withdrawableAmount = max(0, (float) $account->receivable_balance - (float) $account->payable_balance);

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: [
                'user_id' => $userId,
                'driver_name' => $user->first_name . ' ' . $user->last_name,
                'receivable_balance' => (float) $account->receivable_balance,
                'payable_balance' => (float) $account->payable_balance,
                'pending_balance' => (float) $account->pending_balance,
                'received_balance' => (float) $account->received_balance,
                'total_withdrawn' => (float) $account->total_withdrawn,
                'withdrawable_amount' => $withdrawableAmount,
                'formatted_withdrawable' => getCurrencyFormat($withdrawableAmount),
                'total_lifetime_earnings' => (float) $totalEarnings,
                'total_lifetime_payable' => (float) $totalPayable,
                'total_cash_collected' => (float) $totalCashCollected,
            ]
        ));
    }

    /**
     * Bulk credit users (for promotions, etc.)
     *
     * POST /api/admin/wallet/bulk-credit
     */
    public function bulkCredit(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array|min:1|max:1000',
            'user_ids.*' => 'required|string|exists:users,id',
            'amount' => 'required|numeric|min:0.01|max:10000',
            'reason' => 'required|string|min:10|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $adminUser = auth('api')->user();
        if (!$adminUser || $adminUser->user_type !== 'super-admin') {
            return response()->json(responseFormatter(
                constant: DEFAULT_403,
                content: ['message' => 'Super admin access required for bulk operations']
            ), 403);
        }

        $success = [];
        $failed = [];

        foreach ($request->user_ids as $userId) {
            $result = $this->walletService->adminAdjust(
                userId: $userId,
                amount: (float) $request->amount,
                direction: 'credit',
                adminUserId: $adminUser->id,
                reason: $request->reason . ' (Bulk operation)'
            );

            if ($result['success']) {
                $success[] = $userId;
            } else {
                $failed[] = ['user_id' => $userId, 'error' => $result['error']];
            }
        }

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: [
                'message' => 'Bulk credit operation completed',
                'total_requested' => count($request->user_ids),
                'successful' => count($success),
                'failed' => count($failed),
                'failed_details' => $failed,
            ]
        ));
    }
}
