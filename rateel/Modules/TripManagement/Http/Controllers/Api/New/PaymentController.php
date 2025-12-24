<?php

namespace Modules\TripManagement\Http\Controllers\Api\New;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\Gateways\Library\Payer;
use Modules\Gateways\Library\Payment as PaymentInfo;
use Modules\Gateways\Library\Receiver;
use Modules\Gateways\Traits\Payment;
use Modules\TransactionManagement\Traits\TransactionTrait;
use Modules\TripManagement\Service\Interface\TripRequestServiceInterface;
use Modules\UserManagement\Lib\LevelHistoryManagerTrait;


class PaymentController extends Controller
{
    use TransactionTrait, Payment, LevelHistoryManagerTrait;
    protected $tripRequestservice;


    public function __construct(
        TripRequestServiceInterface $tripRequestservice,


    ) {
        $this->tripRequestservice = $tripRequestservice;
    }

    public function payment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'payment_method' => 'required|in:wallet,cash'
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        $trip = $this->tripRequestservice->findOne(id: $request->trip_request_id, relations: ['customer.userAccount', 'driver', 'fee']);
        if (!$trip) {
            return response()->json(responseFormatter(TRIP_REQUEST_404), 403);
        }
        if ($trip->payment_status == PAID) {

            return response()->json(responseFormatter(DEFAULT_PAID_200));
        }

        $tips = 0;
        DB::beginTransaction();
        if (!is_null($request->tips) && $request->payment_method == 'wallet') {
            $tips = $request->tips;
        }
        $feeAttributes['tips'] = $tips;

        $data = [
            'tips' => $tips,
            'payment_method' => $request->payment_method,
            'paid_fare' => $trip->paid_fare + $tips,
            'payment_status' => PAID
        ];
        $trip->fee()->update($feeAttributes);
        $trip = $this->tripRequestservice->update(id: $request->trip_request_id, data: $data);
        if ($request->payment_method == 'wallet') {
            if ($trip->customer->userAccount->wallet_balance < ($trip->paid_fare)) {

                return response()->json(responseFormatter(INSUFFICIENT_FUND_403), 403);
            }
            $method = '_with_wallet_balance';
            $this->walletTransaction($trip);
        } // driver only make cash payment
        elseif ($request->payment_method == 'cash') {
            $method = '_by_cash';
            $this->cashTransaction($trip);
        }

        $this->amountChecker($trip->customer, $trip->paid_fare);
        DB::commit();

        // Offload slow external API calls to background job
        dispatch(new \App\Jobs\ProcessPaymentNotificationsJob(
            $trip->id,
            $request->payment_method,
            auth('api')->id(),
            auth('api')->user()->user_type
        ));

        return response()->json(responseFormatter(DEFAULT_UPDATE_200));
    }


    public function digitalPayment(Request $request)
    {
        // Normalize payment method to lowercase for case-insensitive validation
        $request->merge(['payment_method' => strtolower($request->payment_method)]);

        $validator = Validator::make($request->query(), [
            'trip_request_id' => 'required',
            'payment_method' => 'required|in:kashier,ssl_commerz,stripe,paypal,razor_pay,paystack,senang_pay,paymob_accept,flutterwave,paytm,paytabs,liqpay,mercadopago,bkash,fatoorah,xendit,amazon_pay,iyzi_pay,hyper_pay,foloosi,ccavenue,pvit,moncash,thawani,tap,viva_wallet,hubtel,maxicash,esewa,swish,momo,payfast,worldpay,sixcash'
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        $trip = $this->tripRequestservice->findOne(id: $request->trip_request_id, relations: ['customer.userAccount', 'fee', 'time', 'driver']);
        if (!$trip) {
            return response()->json(responseFormatter(TRIP_REQUEST_404), 403);
        }
        if ($trip->payment_status == PAID) {

            return response()->json(responseFormatter(DEFAULT_PAID_200));
        }

        $attributes = [
            'column' => 'id',
            'payment_method' => $request->payment_method,
        ];
        $tips = $request->tips;
        $feeAttributes['tips'] = $tips;

        $trip->fee()->update($feeAttributes);

        $data = [
            'tips' => $tips,
            'payment_method' => $request->payment_method,
        ];


        $trip = $this->tripRequestservice->update(id: $request->trip_request_id, data: $data);
        $paymentAmount = $trip->paid_fare + $tips;
        $customer = $trip->customer;
        $payer = new Payer(
            name: $customer?->first_name,
            email: $customer->email,
            phone: $customer->phone,
            address: ''
        );

        //hook is look for a autoloaded function to perform action after payment
        $paymentInfo = new PaymentInfo(
            hook: 'tripRequestUpdate',
            currencyCode: businessConfig('currency_code')?->value ?? 'EGP',
            paymentMethod: $request->payment_method,
            paymentPlatform: 'mono',
            payerId: $customer->id,
            receiverId: '100',
            additionalData: [],
            paymentAmount: $paymentAmount,
            externalRedirectLink: null,
            attribute: 'order',
            attributeId: $request->trip_request_id
        );
        $receiverInfo = new Receiver('receiver_name', 'example.png');
        $redirectLink = $this->generate_link($payer, $paymentInfo, $receiverInfo);

        return redirect($redirectLink);
    }
}
