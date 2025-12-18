<?php

namespace Modules\Gateways\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Gateways\Traits\Processor;
use Modules\Gateways\Entities\PaymentRequest;
use Modules\TripManagement\Entities\TripRequest;

class KashierController extends Controller
{
    use Processor;

    private array $config;
    private PaymentRequest $payment;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->paymentConfig('kashier', PAYMENT_CONFIG);

        if (!$config) {
            abort(500, 'Kashier configuration not found');
        }

        $this->config = json_decode(
            $config->mode === 'live' ? $config->live_values : $config->test_values,
            true
        );

        $this->payment = $payment;
    }

    public function pay(Request $request)
{
    $validator = Validator::make($request->all(), [
        'payment_id' => 'required|uuid',
    ]);

    if ($validator->fails()) {
        return response()->json(['message' => 'Invalid request'], 400);
    }

    $paymentData = $this->payment::where('id', $request->payment_id)->where('is_paid', 0)->first();

    if (!$paymentData) {
        return response()->json(['message' => 'Payment not found'], 404);
    }

    session()->put('payment_id', $paymentData->id);

    // ✅ بيانات كاشير مُدخلة يدويًا (static)
    // $mid = 'MID-36316-436';
    // $secret = '8e034a5d-3fd0-40f7-b63f-9060144cea5c';
    // $currency = 'EGP';
    // $mode = 'live';
    // $callbackUrl = 'https://smartlinetest.scopehrd.com/payment/kashier/callback';
    
    
    $mid = $this->config['merchant_id'] ?? null;
    $publicKey = $this->config['public_key'] ?? null;
    $secretKey = $this->config['secret_key'] ?? null;
    $callbackUrl = $this->config['callback_url'] ?? route('kashier.callback');
    $currency = $this->config['currency'] ?? 'EGP';
    $mode = $this->config['mode'] ?? 'live';

    // Generate unique order ID using payment ID
    $merchantOrderId = (string)mt_rand(100000, 999999);
    $amount = (int)$paymentData->payment_amount;

    // Store merchantOrderId in session for callback verification
    session()->put('merchant_order_id', $merchantOrderId);

    // Generate signature using API Key (secret key)
    $path = "/?payment={$mid}.{$merchantOrderId}.{$amount}.{$currency}";
    $hash = hash_hmac('sha256', $path, $secretKey, false);

    // ✅ بناء رابط الدفع
    $url = 'https://payments.kashier.io/?' . http_build_query([
        'merchantId' => $mid,
        'amount' => $amount,
        'currency' => $currency,
        'orderId' => $merchantOrderId,
        'hash' => $hash,
        'mode' => $mode,
        'merchantRedirect' => $callbackUrl,
        'failureRedirect' => 'false',
        'redirectMethod' => 'get',
        'allowedMethods' => 'card,wallet',
        'display' => 'en',
        'brandColor' => '#00bcbc',
    ]);

    // return response()->json([
    //     'status' => true,
    //     'url' => $url,
    // ]);
    return redirect()->away($url);
}


    public function callback(Request $request)
    {
        $paymentId = session('payment_id');
        $paymentData = $this->payment::where('id', $paymentId)->first();

        if (!$paymentData) {
            return response()->json(['message' => 'Payment not found in session'], 404);
        }

        // Verify signature using secret key (API Key)
        $receivedSignature = $request->query('signature');
        $params = $request->except(['signature', 'mode']);
        ksort($params); // Sort parameters alphabetically
        $queryString = urldecode(http_build_query($params));
        $secretKey = $this->config['secret_key'] ?? null;

        $generatedSignature = hash_hmac('sha256', $queryString, $secretKey, false);

        if ($receivedSignature !== $generatedSignature) {
            \Log::error('Kashier signature verification failed', [
                'received' => $receivedSignature,
                'generated' => $generatedSignature,
                'query_string' => $queryString
            ]);
            return $this->paymentResponse($paymentData, 'fail');
        }

        // Verify payment status and transaction details
        if ($request->query('paymentStatus') === 'SUCCESS') {
            $kashierTransactionId = $request->query('transactionId');
            $kashierOrderId = $request->query('orderId');

            // Verify order ID matches
            if ($kashierOrderId !== session('merchant_order_id')) {
                \Log::error('Kashier order ID mismatch', [
                    'expected' => session('merchant_order_id'),
                    'received' => $kashierOrderId
                ]);
                return $this->paymentResponse($paymentData, 'fail');
            }

            // Update payment request with transaction details
            $this->payment::where(['id' => $paymentId])->update([
                'payment_method' => 'kashier',
                'is_paid' => 1,
                'transaction_id' => $kashierTransactionId ?? $kashierOrderId,
            ]);

            // Update trip request payment status
            TripRequest::where('id', $paymentData->attribute_id)->update([
                'payment_method' => 'kashier',
                'payment_status' => 'paid',
            ]);

            // Call the hook function if it exists
            if (isset($paymentData) && function_exists($paymentData->hook)) {
                call_user_func($paymentData->hook, $paymentData);
            }

            return $this->paymentResponse($paymentData, 'success');
        }

        return $this->paymentResponse($paymentData, 'fail');
    }
}

