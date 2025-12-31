<?php

namespace Modules\UserManagement\Http\Controllers\Api\New\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\BusinessManagement\Entities\BusinessSetting;
use Modules\Gateways\Library\Payer;
use Modules\Gateways\Library\Payment as PaymentInfo;
use Modules\Gateways\Library\Receiver;
use Modules\Gateways\Traits\Payment;
use Modules\UserManagement\Entities\UserAccount;

class WalletController extends Controller
{
    use Payment;

    /**
     * Get wallet balance
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getBalance(Request $request): JsonResponse
    {
        $customer = auth('api')->user();

        $userAccount = UserAccount::firstOrCreate(
            ['user_id' => $customer->id],
            [
                'wallet_balance' => 0,
                'payable_balance' => 0,
                'pending_balance' => 0,
                'receivable_balance' => 0,
                'received_balance' => 0,
                'total_withdrawn' => 0,
            ]
        );

        return response()->json(responseFormatter(DEFAULT_200, [
            'wallet_balance' => (float) $userAccount->wallet_balance,
            'currency' => businessConfig('currency_code')?->value ?? 'EGP',
            'formatted_balance' => getCurrencyFormat($userAccount->wallet_balance),
        ]));
    }

    /**
     * Add funds to wallet via digital payment gateway
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

        $customer = auth('api')->user();
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
            name: $customer->first_name . ' ' . $customer->last_name,
            email: $customer->email ?? $customer->phone . '@smartline.com',
            phone: $customer->phone,
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
            $externalRedirectLink = 'smartline://wallet/callback';
        }

        // Create payment info with hook for wallet top-up
        $paymentInfo = new PaymentInfo(
            hook: 'customer_add_fund_to_wallet',
            currencyCode: businessConfig('currency_code')?->value ?? 'EGP',
            paymentMethod: strtolower($request->payment_method),
            paymentPlatform: $request->header('platform') ?? 'app',
            payerId: $customer->id,
            receiverId: 'admin',
            additionalData: $additionalData,
            paymentAmount: $amount,
            externalRedirectLink: $externalRedirectLink,
            attribute: 'wallet_add_fund',
            attributeId: $customer->id
        );

        $receiverInfo = new Receiver('Smartline Wallet', 'wallet.png');

        // Generate payment link
        $redirectLink = $this->generate_link($payer, $paymentInfo, $receiverInfo);

        // For API requests, return the payment URL
        if ($request->wantsJson() || $request->header('Accept') === 'application/json') {
            return response()->json(responseFormatter(DEFAULT_200, [
                'payment_url' => $redirectLink,
                'message' => translate('Redirect to payment gateway'),
            ]));
        }

        // For web requests, redirect directly
        return redirect($redirectLink);
    }

    /**
     * Get wallet transaction history
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function transactionHistory(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'sometimes|numeric|min:1|max:100',
            'offset' => 'sometimes|numeric|min:1',
            'type' => 'sometimes|in:all,credit,debit',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, null, errorProcessor($validator)), 400);
        }

        $customer = auth('api')->user();
        $limit = $request->limit ?? 20;
        $offset = $request->offset ?? 1;
        $type = $request->type ?? 'all';

        $query = \Modules\TransactionManagement\Entities\Transaction::where('user_id', $customer->id)
            ->where('account', 'wallet_balance')
            ->orderBy('created_at', 'desc');

        if ($type === 'credit') {
            $query->where('credit', '>', 0);
        } elseif ($type === 'debit') {
            $query->where('debit', '>', 0);
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
}
