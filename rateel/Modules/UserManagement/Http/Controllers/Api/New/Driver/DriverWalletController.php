<?php

namespace Modules\UserManagement\Http\Controllers\Api\New\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\UserManagement\Entities\UserAccount;
use Modules\TransactionManagement\Entities\Transaction;

class DriverWalletController extends Controller
{
    /**
     * Get driver wallet balance and earnings summary
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getBalance(Request $request): JsonResponse
    {
        $driver = auth('api')->user();

        if ($driver->user_type !== DRIVER) {
            return response()->json(responseFormatter(DEFAULT_403), 403);
        }

        $userAccount = UserAccount::firstOrCreate(
            ['user_id' => $driver->id],
            [
                'wallet_balance' => 0,
                'payable_balance' => 0,
                'pending_balance' => 0,
                'receivable_balance' => 0,
                'received_balance' => 0,
                'total_withdrawn' => 0,
            ]
        );

        // Calculate withdrawable amount (receivable - payable)
        $withdrawableAmount = max(0, (float) $userAccount->receivable_balance - (float) $userAccount->payable_balance);

        return response()->json(responseFormatter(DEFAULT_200, [
            'receivable_balance' => (float) $userAccount->receivable_balance,
            'payable_balance' => (float) $userAccount->payable_balance,
            'pending_balance' => (float) $userAccount->pending_balance,
            'received_balance' => (float) $userAccount->received_balance,
            'total_withdrawn' => (float) $userAccount->total_withdrawn,
            'wallet_balance' => (float) $userAccount->wallet_balance,
            'referral_earn' => (float) $userAccount->referral_earn,
            'withdrawable_amount' => $withdrawableAmount,
            'currency' => businessConfig('currency_code')?->value ?? 'EGP',
            'formatted_receivable' => getCurrencyFormat($userAccount->receivable_balance),
            'formatted_payable' => getCurrencyFormat($userAccount->payable_balance),
            'formatted_withdrawable' => getCurrencyFormat($withdrawableAmount),
        ]));
    }

    /**
     * Get driver earnings transaction history
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function earnings(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'sometimes|numeric|min:1|max:100',
            'offset' => 'sometimes|numeric|min:1',
            'type' => 'sometimes|in:all,earnings,payable,withdrawn',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, null, errorProcessor($validator)), 400);
        }

        $driver = auth('api')->user();

        if ($driver->user_type !== DRIVER) {
            return response()->json(responseFormatter(DEFAULT_403), 403);
        }

        $limit = $request->limit ?? 20;
        $offset = $request->offset ?? 1;
        $type = $request->type ?? 'all';

        $query = Transaction::where('user_id', $driver->id)
            ->orderBy('created_at', 'desc');

        // Filter by transaction type
        switch ($type) {
            case 'earnings':
                $query->where('account', 'receivable_balance');
                break;
            case 'payable':
                $query->where('account', 'payable_balance');
                break;
            case 'withdrawn':
                $query->whereIn('account', ['pending_withdraw_balance', 'received_withdraw_balance']);
                break;
            default:
                $query->whereIn('account', [
                    'receivable_balance',
                    'payable_balance',
                    'received_balance',
                    'pending_withdraw_balance',
                    'received_withdraw_balance',
                ]);
                break;
        }

        $total = $query->count();
        $transactions = $query->skip(($offset - 1) * $limit)->take($limit)->get();

        $formattedTransactions = $transactions->map(function ($txn) {
            return [
                'id' => $txn->id,
                'type' => $txn->credit > 0 ? 'credit' : 'debit',
                'amount' => $txn->credit > 0 ? $txn->credit : $txn->debit,
                'formatted_amount' => getCurrencyFormat($txn->credit > 0 ? $txn->credit : $txn->debit),
                'balance_after' => $txn->balance,
                'formatted_balance' => getCurrencyFormat($txn->balance),
                'attribute' => $txn->attribute,
                'account' => $txn->account,
                'reference' => $txn->trx_ref_id,
                'created_at' => $txn->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json(responseFormatter(DEFAULT_200, [
            'transactions' => $formattedTransactions,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]));
    }

    /**
     * Get driver earnings summary for a specific period
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function summary(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'period' => 'sometimes|in:today,week,month,year,all',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, null, errorProcessor($validator)), 400);
        }

        $driver = auth('api')->user();

        if ($driver->user_type !== DRIVER) {
            return response()->json(responseFormatter(DEFAULT_403), 403);
        }

        $period = $request->period ?? 'month';

        $query = Transaction::where('user_id', $driver->id)
            ->where('account', 'receivable_balance');

        // Apply date filter
        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
                break;
            case 'year':
                $query->whereYear('created_at', now()->year);
                break;
        }

        $totalEarnings = $query->sum('credit');
        $totalDeductions = (clone $query)->sum('debit');
        $transactionCount = $query->count();

        // Get payable (commission) for same period
        $payableQuery = Transaction::where('user_id', $driver->id)
            ->where('account', 'payable_balance');

        switch ($period) {
            case 'today':
                $payableQuery->whereDate('created_at', today());
                break;
            case 'week':
                $payableQuery->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $payableQuery->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
                break;
            case 'year':
                $payableQuery->whereYear('created_at', now()->year);
                break;
        }

        $totalPayable = $payableQuery->sum('credit');

        return response()->json(responseFormatter(DEFAULT_200, [
            'period' => $period,
            'total_earnings' => (float) $totalEarnings,
            'total_deductions' => (float) $totalDeductions,
            'net_earnings' => (float) ($totalEarnings - $totalDeductions),
            'total_payable' => (float) $totalPayable,
            'transaction_count' => $transactionCount,
            'formatted_earnings' => getCurrencyFormat($totalEarnings),
            'formatted_net' => getCurrencyFormat($totalEarnings - $totalDeductions),
            'currency' => businessConfig('currency_code')?->value ?? 'EGP',
        ]));
    }
}
