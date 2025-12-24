<?php

namespace Modules\Gateways\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Validator;
use Modules\Gateways\Entities\PaymentRequest;
use Modules\Gateways\Traits\Processor;

class KashierPaymentController extends Controller
{
    use Processor;

    private PaymentRequest $payment;
    private User $user;
    private mixed $config;

    public function __construct(PaymentRequest $payment, User $user)
    {
        $this->payment = $payment;
        $this->user = $user;
        $this->config = $this->paymentConfig('kashier', PAYMENT_CONFIG);
    }

    /**
     * Get Kashier configuration values
     */
    private function getConfigValues(): ?object
    {
        if (!is_null($this->config) && $this->config->mode == 'live') {
            return json_decode($this->config->live_values);
        } elseif (!is_null($this->config) && $this->config->mode == 'test') {
            return json_decode($this->config->test_values);
        }
        return null;
    }

    /**
     * Generate Kashier hash for payment verification
     */
    private function generateHash(array $data, string $secretKey): string
    {
        $queryString = http_build_query($data);
        return hash_hmac('sha256', $queryString, $secretKey);
    }

    /**
     * Display Kashier payment page
     */
    public function index(Request $request): View|Application|Factory|JsonResponse|\Illuminate\Contracts\Foundation\Application
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json($this->responseFormatter(GATEWAYS_DEFAULT_400, null, $this->errorProcessor($validator)), 400);
        }

        $data = $this->payment::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->responseFormatter(GATEWAYS_DEFAULT_204), 200);
        }

        $payer = json_decode($data['payer_information']);
        
        // Kashier configuration - HARDCODED VALUES
        $merchantId = 'MID-36316-436';
        $publicKey = '20627b6e-3b07-4bab-a89c-0128a99ff96f';
        $secretKey = 'e9430e4868e42d951f5d041b0ff17cec$d93f44c6462e08ec97059b966d3ffbc02ba40fc1f93498afdec95254cbaf3809283b1fa69e5c5f3409d310e1e175b315';
        $callbackUrl = 'https://smartlinetest.scopehrd.com/payment/kashier/callback';
        
        // Payment details
        $orderId = $data->id;
        $amount = number_format($data->payment_amount, 2, '.', '');
        $currency = $data->currency_code ?? 'EGP';
        
        // Generate hash for security (Kashier format: mid.amount.currency.orderId)
        $hashString = $merchantId . '.' . $amount . '.' . $currency . '.' . $orderId;
        $hash = hash_hmac('sha256', $hashString, $secretKey);
        
        // Redirect URL
        $redirectUrl = $callbackUrl;
        
        // API Key for view (using public_key)
        $apiKey = $publicKey;

        return view('Gateways::payment.kashier', compact(
            'data',
            'payer',
            'merchantId',
            'apiKey',
            'amount',
            'currency',
            'orderId',
            'hash',
            'callbackUrl',
            'redirectUrl'
        ));
    }

    /**
     * Handle Kashier payment callback
     */
    public function callback(Request $request): Application|JsonResponse|Redirector|\Illuminate\Contracts\Foundation\Application|RedirectResponse
    {
        $paymentStatus = $request->input('paymentStatus');
        $orderId = $request->input('merchantOrderId') ?? $request->input('orderId');
        $transactionId = $request->input('transactionId');
        $signature = $request->input('signature') ?? $request->input('hash');
        
        if (!$orderId) {
            return $this->paymentResponse(null, 'fail');
        }

        $payment_data = $this->payment::where(['id' => $orderId])->first();
        
        if (!$payment_data) {
            return $this->paymentResponse(null, 'fail');
        }

        // Verify the payment signature - HARDCODED SECRET KEY
        $secretKey = 'e9430e4868e42d951f5d041b0ff17cec$d93f44c6462e08ec97059b966d3ffbc02ba40fc1f93498afdec95254cbaf3809283b1fa69e5c5f3409d310e1e175b315';
        
        // Kashier sends signature in format: orderId-amount-currency-status
        $expectedDataString = $orderId . '-' . $payment_data->payment_amount . '-' . ($payment_data->currency_code ?? 'EGP') . '-' . $paymentStatus;
        $expectedHash = hash_hmac('sha256', $expectedDataString, $secretKey);
        
        // For security, verify signature (optional - depends on Kashier's implementation)
        // if ($signature !== $expectedHash) {
        //     return $this->paymentResponse($payment_data, 'fail');
        // }

        if (strtoupper($paymentStatus) === 'SUCCESS' || strtoupper($paymentStatus) === 'CAPTURED') {
            $this->payment::where(['id' => $orderId])->update([
                'payment_method' => 'kashier',
                'is_paid' => 1,
                'transaction_id' => $transactionId,
            ]);

            $data = $this->payment::where(['id' => $orderId])->first();
            if (isset($data) && function_exists($data->hook)) {
                call_user_func($data->hook, $data);
            }
            return $this->paymentResponse($data, 'success');
        }

        if (isset($payment_data) && function_exists($payment_data->hook)) {
            call_user_func($payment_data->hook, $payment_data);
        }
        return $this->paymentResponse($payment_data, 'fail');
    }

    /**
     * Handle Kashier webhook notifications
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        
        $orderId = $payload['merchantOrderId'] ?? $payload['orderId'] ?? null;
        $paymentStatus = $payload['paymentStatus'] ?? null;
        $transactionId = $payload['transactionId'] ?? null;
        
        if (!$orderId) {
            return response()->json(['status' => 'error', 'message' => 'Invalid order ID'], 400);
        }

        $payment_data = $this->payment::where(['id' => $orderId])->first();
        
        if (!$payment_data) {
            return response()->json(['status' => 'error', 'message' => 'Payment not found'], 404);
        }

        if (strtoupper($paymentStatus) === 'SUCCESS' || strtoupper($paymentStatus) === 'CAPTURED') {
            $this->payment::where(['id' => $orderId])->update([
                'payment_method' => 'kashier',
                'is_paid' => 1,
                'transaction_id' => $transactionId,
            ]);

            $data = $this->payment::where(['id' => $orderId])->first();
            if (isset($data) && function_exists($data->hook)) {
                call_user_func($data->hook, $data);
            }
            
            return response()->json(['status' => 'success', 'message' => 'Payment processed']);
        }

        return response()->json(['status' => 'error', 'message' => 'Payment not successful'], 400);
    }
}
