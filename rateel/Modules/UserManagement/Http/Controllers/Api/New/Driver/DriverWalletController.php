<?php

namespace Modules\UserManagement\Http\Controllers\Api\New\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Modules\UserManagement\Entities\UserAccount;
use Modules\TransactionManagement\Entities\Transaction;
use Modules\BusinessManagement\Entities\BusinessSetting;
use Modules\Gateways\Library\Payer;
use Modules\Gateways\Library\Payment as PaymentInfo;
use Modules\Gateways\Library\Receiver;
use Modules\Gateways\Traits\Payment;

class DriverWalletController extends Controller
{
    use Payment;
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

        // Check if wallet balance is negative
        $isNegative = $userAccount->wallet_balance < 0;
        $amountOwed = $isNegative ? abs($userAccount->wallet_balance) : 0;

        // Negative balance limit info
        $maxNegativeBalance = (float) ($driver->max_negative_balance ?? 200);
        $negativeBalanceUsed = $isNegative ? abs($userAccount->wallet_balance) : 0;
        $negativeBalanceRemaining = max(0, $maxNegativeBalance - $negativeBalanceUsed);
        $negativeBalancePercentage = $maxNegativeBalance > 0 ? round(($negativeBalanceUsed / $maxNegativeBalance) * 100, 2) : 0;
        $warningThreshold = $maxNegativeBalance * 0.75;
        $isNearLimit = $negativeBalanceUsed >= $warningThreshold;

        return response()->json(responseFormatter(DEFAULT_200, [
            'receivable_balance' => (float) $userAccount->receivable_balance,
            'payable_balance' => (float) $userAccount->payable_balance,
            'pending_balance' => (float) $userAccount->pending_balance,
            'received_balance' => (float) $userAccount->received_balance,
            'total_withdrawn' => (float) $userAccount->total_withdrawn,
            'wallet_balance' => (float) $userAccount->wallet_balance,
            'referral_earn' => (float) $userAccount->referral_earn,
            'withdrawable_amount' => $withdrawableAmount,
            // NEW: Negative balance indicators
            'is_negative' => $isNegative,
            'amount_owed' => $amountOwed,
            'formatted_wallet_balance' => getCurrencyFormat($userAccount->wallet_balance),
            'formatted_amount_owed' => $isNegative ? getCurrencyFormat($amountOwed) : null,
            'currency' => businessConfig('currency_code')?->value ?? 'EGP',
            'formatted_receivable' => getCurrencyFormat($userAccount->receivable_balance),
            'formatted_payable' => getCurrencyFormat($userAccount->payable_balance),
            'formatted_withdrawable' => getCurrencyFormat($withdrawableAmount),
            // Negative balance limit information
            'negative_balance_limit' => [
                'max_limit' => $maxNegativeBalance,
                'formatted_max_limit' => getCurrencyFormat($maxNegativeBalance),
                'used' => $negativeBalanceUsed,
                'formatted_used' => getCurrencyFormat($negativeBalanceUsed),
                'remaining' => $negativeBalanceRemaining,
                'formatted_remaining' => getCurrencyFormat($negativeBalanceRemaining),
                'percentage_used' => $negativeBalancePercentage,
                'is_near_limit' => $isNearLimit,
                'warning_threshold' => $warningThreshold,
                'formatted_warning_threshold' => getCurrencyFormat($warningThreshold),
            ],
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

    /**
     * Get driver daily balance/earnings for a specific date
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function dailyBalance(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => 'sometimes|date|date_format:Y-m-d',
            'include_hourly' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, null, errorProcessor($validator)), 400);
        }

        $driver = auth('api')->user();

        if ($driver->user_type !== DRIVER) {
            return response()->json(responseFormatter(DEFAULT_403), 403);
        }

        $date = $request->date ? \Carbon\Carbon::parse($request->date) : today();
        $includeHourly = $request->boolean('include_hourly', false);

        $cacheKey = "driver_daily_balance_{$driver->id}_{$date->format('Y-m-d')}_" . ($includeHourly ? '1' : '0');

        $responseData = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($driver, $date, $includeHourly) {
            $startOfDay = $date->copy()->startOfDay();
            $endOfDay = $date->copy()->endOfDay();

            // Optimize: Combine earnings, payable, and count queries
            $stats = Transaction::where('user_id', $driver->id)
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->whereIn('account', ['receivable_balance', 'payable_balance'])
                ->selectRaw("
                    SUM(CASE WHEN account = 'receivable_balance' THEN credit ELSE 0 END) as earnings,
                    SUM(CASE WHEN account = 'payable_balance' THEN credit ELSE 0 END) as payable,
                    COUNT(*) as count
                ")
                ->first();

            $earnings = $stats->earnings ?? 0;
            $payable = $stats->payable ?? 0;
            $transactionCount = $stats->count ?? 0;

            // Optimize: Trips count with index-friendly date range
            $trips = \Modules\TripManagement\Entities\TripRequest::where('driver_id', $driver->id)
                ->where('current_status', 'completed')
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->count();

            $netEarnings = $earnings - $payable;
            $hasNegativeBalance = $netEarnings < 0;
            $negativeBalance = $hasNegativeBalance ? abs($netEarnings) : null;

            // Get current wallet balance for negative balance limit info
            $userAccount = UserAccount::where('user_id', $driver->id)->first();
            $currentWalletBalance = $userAccount ? (float) $userAccount->wallet_balance : 0;
            
            // Calculate negative balance limit information
            $isNegative = $currentWalletBalance < 0;
            $amountOwed = $isNegative ? abs($currentWalletBalance) : 0;
            $maxNegativeBalance = (float) ($driver->max_negative_balance ?? 200);
            $negativeBalanceUsed = $isNegative ? abs($currentWalletBalance) : 0;
            $negativeBalanceRemaining = max(0, $maxNegativeBalance - $negativeBalanceUsed);
            $negativeBalancePercentage = $maxNegativeBalance > 0 ? round(($negativeBalanceUsed / $maxNegativeBalance) * 100, 2) : 0;
            $warningThreshold = $maxNegativeBalance * 0.75;
            $isNearLimit = $negativeBalanceUsed >= $warningThreshold;

            $response = [
                'date' => $date->format('Y-m-d'),
                'day_name' => $date->format('l'),
                'is_today' => $date->isToday(),
                'total_earnings' => (float) $earnings,
                'formatted_earnings' => getCurrencyFormat($earnings),
                'total_trips' => $trips,
                'total_payable' => (float) $payable,
                'formatted_payable' => getCurrencyFormat($payable),
                'net_earnings' => (float) $netEarnings,
                'formatted_net' => getCurrencyFormat($netEarnings),
                'has_negative_balance' => $hasNegativeBalance,
                'negative_balance' => $negativeBalance ? (float) $negativeBalance : null,
                'formatted_negative_balance' => $negativeBalance ? getCurrencyFormat($negativeBalance) : null,
                'transaction_count' => $transactionCount,
                'currency' => businessConfig('currency_code')?->value ?? 'EGP',
                // Current wallet balance
                'wallet_balance' => $currentWalletBalance,
                'formatted_wallet_balance' => getCurrencyFormat($currentWalletBalance),
                'is_wallet_negative' => $isNegative,
                'amount_owed' => $amountOwed,
                'formatted_amount_owed' => $isNegative ? getCurrencyFormat($amountOwed) : null,
                // Negative balance limit information
                'negative_balance_limit' => [
                    'max_limit' => $maxNegativeBalance,
                    'formatted_max_limit' => getCurrencyFormat($maxNegativeBalance),
                    'used' => $negativeBalanceUsed,
                    'formatted_used' => getCurrencyFormat($negativeBalanceUsed),
                    'remaining' => $negativeBalanceRemaining,
                    'formatted_remaining' => getCurrencyFormat($negativeBalanceRemaining),
                    'percentage_used' => $negativeBalancePercentage,
                    'is_near_limit' => $isNearLimit,
                    'warning_threshold' => $warningThreshold,
                    'formatted_warning_threshold' => getCurrencyFormat($warningThreshold),
                ],
            ];

            // Add hourly breakdown if requested
            if ($includeHourly) {
                $hourlyData = Transaction::where('user_id', $driver->id)
                    ->where('account', 'receivable_balance')
                    ->whereBetween('created_at', [$startOfDay, $endOfDay])
                    ->selectRaw('HOUR(created_at) as hour, SUM(credit) as earnings, COUNT(*) as transactions')
                    ->groupBy('hour')
                    ->orderBy('hour')
                    ->get()
                    ->keyBy('hour');

                $hourlyTrips = \Modules\TripManagement\Entities\TripRequest::where('driver_id', $driver->id)
                    ->where('current_status', 'completed')
                    ->whereBetween('created_at', [$startOfDay, $endOfDay])
                    ->selectRaw('HOUR(created_at) as hour, COUNT(*) as trips')
                    ->groupBy('hour')
                    ->get()
                    ->keyBy('hour');

                $hourlyBreakdown = [];
                for ($h = 0; $h < 24; $h++) {
                    $hourData = $hourlyData->get($h);
                    $tripData = $hourlyTrips->get($h);
                    
                    if ($hourData || $tripData) {
                        $hourlyBreakdown[] = [
                            'hour' => sprintf('%02d:00', $h),
                            'earnings' => (float) ($hourData->earnings ?? 0),
                            'formatted_earnings' => getCurrencyFormat($hourData->earnings ?? 0),
                            'trips' => $tripData->trips ?? 0,
                            'transactions' => $hourData->transactions ?? 0,
                        ];
                    }
                }

                $response['hourly_breakdown'] = $hourlyBreakdown;
            }

            return $response;
        });

        return response()->json(responseFormatter(DEFAULT_200, $responseData));
    }

    /**
     * Add funds to driver wallet via digital payment gateway
     *
     * @param Request $request
     * @return JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function addFund(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|in:kashier,ssl_commerz,stripe,paypal,razor_pay,paystack,senang_pay,paymob_accept,flutterwave,paytm,paytabs,liqpay,mercadopago,bkash,fatoorah,xendit,amazon_pay,iyzi_pay,hyper_pay,foloosi,ccavenue,pvit,moncash,thawani,tap,viva_wallet,hubtel,maxicash,esewa,swish,momo,payfast,worldpay,sixcash',
            'redirect_url' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, null, errorProcessor($validator)), 400);
        }

        $driver = auth('api')->user();

        if ($driver->user_type !== DRIVER) {
            return response()->json(responseFormatter(DEFAULT_403), 403);
        }

        $amount = (float) $request->amount;

        // Minimum amount validation
        $minAmount = (float) (businessConfig('min_wallet_add_fund_amount')?->value ?? 10);
        if ($amount < $minAmount) {
            return response()->json(responseFormatter([
                'response_code' => 'min_amount_error_400',
                'message' => translate('Minimum amount to add is ') . getCurrencyFormat($minAmount),
            ]), 400);
        }

        // Maximum amount validation
        $maxAmount = (float) (businessConfig('max_wallet_add_fund_amount')?->value ?? 50000);
        if ($amount > $maxAmount) {
            return response()->json(responseFormatter([
                'response_code' => 'max_amount_error_400',
                'message' => translate('Maximum amount to add is ') . getCurrencyFormat($maxAmount),
            ]), 400);
        }

        // Build payer info
        $payer = new Payer(
            name: $driver->first_name . ' ' . $driver->last_name,
            email: $driver->email ?? $driver->phone . '@smartline.com',
            phone: $driver->phone,
            address: ''
        );

        // Additional data for payment page
        $additionalData = [
            'business_name' => BusinessSetting::where(['key_name' => 'business_name'])->first()?->value ?? 'Smartline',
            'business_logo' => asset('storage/app/public/business') . '/' . BusinessSetting::where(['key_name' => 'header_logo'])->first()?->value,
        ];

        // Determine redirect URL
        $externalRedirectLink = $request->redirect_url;
        if (!$externalRedirectLink && $request->header('platform') === 'app') {
            $externalRedirectLink = 'smartline://driver/wallet/callback';
        }

        // Create payment info with hook for driver wallet top-up
        $paymentInfo = new PaymentInfo(
            hook: 'driver_add_fund_to_wallet',
            currencyCode: businessConfig('currency_code')?->value ?? 'EGP',
            paymentMethod: strtolower($request->payment_method),
            paymentPlatform: $request->header('platform') ?? 'app',
            payerId: $driver->id,
            receiverId: 'admin',
            additionalData: $additionalData,
            paymentAmount: $amount,
            externalRedirectLink: $externalRedirectLink,
            attribute: 'driver_wallet_add_fund',
            attributeId: $driver->id
        );

        $receiverInfo = new Receiver('Smartline Driver Wallet', 'wallet.png');

        // Generate payment link
        $redirectLink = $this->generate_link($payer, $paymentInfo, $receiverInfo);

        // For API requests, return the payment URL
        if ($request->wantsJson() || $request->header('Accept') === 'application/json') {
            return response()->json(responseFormatter(DEFAULT_200, [
                'payment_url' => $redirectLink,
                'amount' => $amount,
                'formatted_amount' => getCurrencyFormat($amount),
                'payment_method' => $request->payment_method,
                'message' => translate('Redirect to payment gateway'),
            ]));
        }

        // For web requests, redirect directly
        return redirect($redirectLink);
    }
}
