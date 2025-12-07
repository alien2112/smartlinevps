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
    $secretFull = $this->config['secret_key'] ?? null;
    $callbackUrl = $this->config['callback_url'] ?? route('kashier.callback');
    $currency = $this->config['currency'] ?? 'EGP';
    $mode = $this->config['mode'] ?? 'live';

    // orderId عشوائي بدون حروف
    $merchantOrderId = (string)mt_rand(10000, 99999);
    $amount = (int)$paymentData->payment_amount;

    // ✅ توليد توقيع
    $path = "/?payment={$mid}.{$merchantOrderId}.{$amount}.{$currency}";
    $hash = hash_hmac('sha256', $path, $publicKey, false);

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

        // تحقق من التوقيع
        $receivedSignature = $request->query('signature');
        $params = $request->except(['signature', 'mode']);
        $queryString = urldecode(http_build_query($params));
        $publicKey = $this->config['public_key'] ?? null;
        // $secretFull = $this->config['secret_key'] ?? '';
        // $secret = explode('$', $secretFull)[1] ?? '';

        $generatedSignature = hash_hmac('sha256', $queryString, $publicKey, false);

        if ($receivedSignature !== $generatedSignature) {

            return $this->paymentResponse($paymentData, 'fail');
        }

       if ($request->query('paymentStatus') === 'SUCCESS') {
            // $paymentData->update([
            //     'payment_method' => 'kashier',
            //     'is_paid' => 1,
            //     'transaction_id' => $request->query('order_id'),
            // ]);
            
             $this->payment::where(['id' => session('payment_id')])->update([
                'payment_method' => 'kashier',
                'is_paid' => 1,
                'transaction_id' => session('payment_id'),
            ]);

		TripRequest::where('id', $paymentData->attribute_id)->update([
        	'payment_method' => 'kashier',
        	'payment_status' => 'paid',
    		]);
            if (isset($paymentData) && function_exists($paymentData->hook)) {
                call_user_func($paymentData->hook, $paymentData);
            }

            return $this->paymentResponse($paymentData, 'success');
        }
        
        return $this->paymentResponse($paymentData, 'fail');
    }
}

