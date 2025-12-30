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
        $method = '';
        
        // ⚡ EARLY BALANCE CHECK for wallet payments (before any DB changes)
        if ($request->payment_method == 'wallet') {
            $totalAmount = $trip->paid_fare + ($request->tips ?? 0);
            if ($trip->customer->userAccount->wallet_balance < $totalAmount) {
                return response()->json(responseFormatter(INSUFFICIENT_FUND_403), 403);
            }
        }
        
        DB::beginTransaction();
        try {
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
            
            if ($request->payment_method == 'wallet') {
                // Wallet transaction handles its own lock and balance re-check
                // Pass the amount to deduct for atomic operation
                $this->walletTransaction($trip, $trip->paid_fare + $tips);
                $method = '_with_wallet_balance';
            } elseif ($request->payment_method == 'cash') {
                $method = '_by_cash';
                $this->cashTransaction($trip);
            }
            
            // Update trip AFTER successful transaction
            $trip->fee()->update($feeAttributes);
            $trip = $this->tripRequestservice->update(id: $request->trip_request_id, data: $data);

            $this->amountChecker($trip->customer, $trip->paid_fare);
            DB::commit();
        } catch (\Modules\TransactionManagement\Exceptions\InsufficientFundsException $e) {
            DB::rollBack();
            return response()->json(responseFormatter(INSUFFICIENT_FUND_403), 403);
        } catch (\Exception $exception) {
            DB::rollBack();
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: [['code' => 'payment_error', 'message' => $exception->getMessage()]]), 400);
        }

        $push = getNotification('payment_successful');
        sendDeviceNotification(
            fcm_token: auth('api')->user()->user_type == 'customer' ? $trip->driver->fcm_token : $trip->customer->fcm_token,
            title: translate($push['title']),
            description: translate(textVariableDataFormat($push['description'],paidAmount: $trip->paid_fare,methodName:$method )),
            status: $push['status'],
            ride_request_id: $trip->id,
            type: $trip->type,
            action: 'payment_successful',
            user_id: $trip->driver->id
        );
        $pushTips = getNotification("tips_from_customer");
        if ($trip->tips > 0) {
            sendDeviceNotification(
                fcm_token: $trip->driver->fcm_token,
                title: translate($pushTips['title']),
                description: translate(textVariableDataFormat(value: $pushTips['description'],tipsAmount: $trip->tips)) ,
                status: $push['status'],
                ride_request_id: $trip->id,
                action: 'got_tipped',
                user_id: $trip->driver->id,
            );
        }

        return response()->json(responseFormatter(DEFAULT_UPDATE_200));
    }


    public function digitalPayment(Request $request)
    {
        // Normalize payment method to lowercase for case-insensitive validation
        $request->merge(['payment_method' => strtolower($request->payment_method)]);

        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'payment_method' => 'required|in:kashier'
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
