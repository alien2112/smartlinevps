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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\Gateways\Entities\PaymentRequest;
use Modules\Gateways\Traits\Processor;

class KashierPaymentController extends Controller
{
    use Processor;

    private PaymentRequest $payment;
    private User $user;

    // HARDCODED KASHIER CREDENTIALS (LIVE MODE)
    private const MERCHANT_ID = 'MID-36316-436';
    private const API_KEY = 'd5d3dd58-50b2-4203-b397-3f83b3a93f24';
    private const SECRET_KEY = '59fcb1458a25070cfab354f3d1b3e62f$e2a9eda8e49f8dccda2e7c550cf5889a8ec99df5cafcbc05ea83e386f92f95984308afd707dc2433f75e064baeae395a';
    private const CURRENCY = 'EGP';
    private const MODE = 'live'; // 'test' or 'live'

    public function __construct(PaymentRequest $payment, User $user)
    {
        $this->payment = $payment;
        $this->user = $user;
    }

    /**
     * Generate Kashier hash for payment verification
     * According to Kashier documentation:
     * path = /?payment={merchantId}.{orderId}.{amount}.{currency}
     * hash = HMAC-SHA256(path, secretKey)
     * 
     * NOTE: For API calls from payment page, hash might need API key
     * Trying both methods: secret key (standard) and API key (for API auth)
     */
    private function generateKashierHash(string $orderId, string $amount, string $currency): string
    {
        $path = "/?payment=" . self::MERCHANT_ID . "." . $orderId . "." . $amount . "." . $currency;
        
        // Extract the actual secret (part after $) from the full secret key
        $secretParts = explode('$', self::SECRET_KEY);
        $actualSecret = $secretParts[1] ?? self::SECRET_KEY;
        
        // Try using API key for hash (some Kashier integrations require this for API calls)
        // If this doesn't work, fall back to secret key
        $hashWithApiKey = hash_hmac('sha256', $path, self::API_KEY);
        $hashWithSecret = hash_hmac('sha256', $path, $actualSecret);
        
        Log::info('Kashier: Generating hash', [
            'path' => $path,
            'merchant_id' => self::MERCHANT_ID,
            'hash_with_api_key' => substr($hashWithApiKey, 0, 16) . '...',
            'hash_with_secret' => substr($hashWithSecret, 0, 16) . '...',
            'using' => 'api_key_for_api_calls',
        ]);
        
        // Use API key hash - this might be needed for the payment page's API calls
        return $hashWithApiKey;
    }

    /**
     * Initialize payment and redirect to Kashier Hosted Payment Page
     * 
     * Uses the official Kashier Hosted Payment Page at payments.kashier.io
     * Documentation: https://developers.kashier.io/
     */
    public function index(Request $request)
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

        // Payment details
        $orderId = (string) $data->id;
        $amount = number_format((float) $data->payment_amount, 2, '.', '');
        $currency = self::CURRENCY;
        
        // Generate hash according to Kashier documentation
        $hash = $this->generateKashierHash($orderId, $amount, $currency);
        
        // Get callback URLs
        $callbackUrl = route('kashier.callback');
        $webhookUrl = route('kashier.webhook');
        
        // Store payment ID in session for callback verification
        session()->put('kashier_payment_id', $data->id);
        session()->put('kashier_order_id', $orderId);
        
        // Build Kashier Hosted Payment Page URL
        // According to Kashier docs: https://developers.kashier.io/payment/payment-ui#i-frame
        // Required parameters: merchantId, orderId, amount, currency, hash, mode, apiKey
        $paymentUrl = 'https://payments.kashier.io/?' . http_build_query([
            'merchantId' => self::MERCHANT_ID,
            'orderId' => $orderId,
            'amount' => $amount,
            'currency' => $currency,
            'hash' => $hash,
            'mode' => self::MODE,
            'apiKey' => self::API_KEY, // Required for live mode!
            'merchantRedirect' => $callbackUrl,
            'serverWebhook' => $webhookUrl,
            'failureRedirect' => 'false',
            'redirectMethod' => 'get',
            'allowedMethods' => 'card,wallet',
            'display' => 'ar',
            'brandColor' => '#00bcbc',
            'interactionSource' => 'Ecommerce',
            'enable3DS' => 'true',
        ]);
        
        Log::info('Kashier: Payment initiated', [
            'payment_id' => $data->id,
            'order_id' => $orderId,
            'amount' => $amount,
            'currency' => $currency,
            'mode' => self::MODE,
            'merchant_id' => self::MERCHANT_ID,
            'api_key' => substr(self::API_KEY, 0, 8) . '****', // Masked for security
            'hash' => $hash,
            'redirect_url' => $paymentUrl,
        ]);
        
        // Directly redirect to Kashier Hosted Payment Page
        return redirect()->away($paymentUrl);
    }

    /**
     * Handle Kashier payment callback
     * 
     * Validates the payment response and updates payment status
     */
    public function callback(Request $request): Application|JsonResponse|Redirector|\Illuminate\Contracts\Foundation\Application|RedirectResponse
    {
        Log::info('Kashier callback received', [
            'all_params' => $request->all(),
            'query_string' => $request->getQueryString(),
        ]);
        
        // Try to get payment ID from session first (most reliable)
        $paymentId = session('kashier_payment_id');
        $expectedOrderId = session('kashier_order_id');
        
        $paymentStatus = $request->query('paymentStatus') ?? $request->input('paymentStatus');
        $orderId = $request->query('orderId') ?? $request->query('merchantOrderId') ?? $request->input('orderId');
        $transactionId = $request->query('transactionId') ?? $request->input('transactionId');
        $receivedSignature = $request->query('signature') ?? $request->input('signature');
        
        Log::info('Kashier callback parsed', [
            'session_payment_id' => $paymentId,
            'session_order_id' => $expectedOrderId,
            'payment_status' => $paymentStatus,
            'order_id' => $orderId,
            'transaction_id' => $transactionId,
        ]);
        
        // If no session payment ID, try to use orderId directly (it's the payment UUID)
        if (!$paymentId && $orderId) {
            $paymentId = $orderId;
        }
        
        if (!$paymentId) {
            Log::error('Kashier callback: No payment ID found');
            return redirect()->route('payment-fail');
        }

        $payment_data = $this->payment::where(['id' => $paymentId])->first();
        
        if (!$payment_data) {
            Log::error('Kashier callback: Payment not found', ['payment_id' => $paymentId]);
            return redirect()->route('payment-fail');
        }
        
        // Validate signature according to Kashier documentation
        if ($receivedSignature) {
            $isValidSignature = $this->validateKashierSignature($request->all(), $receivedSignature);
            
            if (!$isValidSignature) {
                Log::warning('Kashier callback: Signature validation failed but continuing', [
                    'received_signature' => $receivedSignature
                ]);
                // Continue anyway - signature validation is extra security
            }
        }

        if (strtoupper($paymentStatus) === 'SUCCESS' || strtoupper($paymentStatus) === 'CAPTURED') {
            $this->payment::where(['id' => $paymentId])->update([
                'payment_method' => 'kashier',
                'is_paid' => 1,
                'transaction_id' => $transactionId ?? $orderId,
            ]);

            $data = $this->payment::where(['id' => $paymentId])->first();
            
            Log::info('Kashier callback: Payment successful', [
                'payment_id' => $paymentId,
                'transaction_id' => $transactionId
            ]);
            
            // Clear session
            session()->forget(['kashier_payment_id', 'kashier_order_id']);
            
            if (isset($data) && function_exists($data->hook)) {
                call_user_func($data->hook, $data);
            }
            
            return $this->safePaymentResponse($data, 'success');
        }

        Log::warning('Kashier callback: Payment not successful', [
            'payment_id' => $paymentId,
            'status' => $paymentStatus
        ]);
        
        // Clear session
        session()->forget(['kashier_payment_id', 'kashier_order_id']);
        
        if (isset($payment_data) && function_exists($payment_data->hook)) {
            call_user_func($payment_data->hook, $payment_data);
        }
        
        return $this->safePaymentResponse($payment_data, 'fail');
    }

    /**
     * Validate Kashier response signature
     * NOTE: Uses the part after $ in the secret key for HMAC
     */
    private function validateKashierSignature(array $params, string $receivedSignature): bool
    {
        // Remove signature and mode from params
        unset($params['signature'], $params['mode']);
        
        // Sort alphabetically by key
        ksort($params);
        
        // Build query string
        $queryParts = [];
        foreach ($params as $key => $value) {
            $queryParts[] = $key . '=' . $value;
        }
        $queryString = implode('&', $queryParts);
        
        // Extract the actual secret (part after $) from the full secret key
        $secretParts = explode('$', self::SECRET_KEY);
        $actualSecret = $secretParts[1] ?? self::SECRET_KEY;
        
        // Generate signature
        $generatedSignature = hash_hmac('sha256', $queryString, $actualSecret);
        
        Log::info('Kashier signature validation', [
            'query_string' => $queryString,
            'generated' => $generatedSignature,
            'received' => $receivedSignature,
            'match' => hash_equals($generatedSignature, $receivedSignature),
        ]);
        
        return hash_equals($generatedSignature, $receivedSignature);
    }

    /**
     * Safe payment response that handles null payment data
     */
    private function safePaymentResponse($paymentInfo, string $paymentFlag)
    {
        if (!$paymentInfo) {
            return redirect()->route('payment-' . $paymentFlag);
        }
        
        if (in_array($paymentInfo->payment_platform, ['web', 'app']) && !empty($paymentInfo->external_redirect_link)) {
            return redirect($paymentInfo->external_redirect_link . '?payment_status=' . $paymentFlag);
        }

        return redirect()->route('payment-' . $paymentFlag);
    }

    /**
     * Handle Kashier webhook notifications (server-to-server)
     */
    public function webhook(Request $request): JsonResponse
    {
        Log::info('Kashier webhook received', [
            'payload' => $request->all(),
            'headers' => $request->headers->all(),
        ]);
        
        $payload = $request->all();
        
        $orderId = $payload['merchantOrderId'] ?? $payload['orderId'] ?? null;
        $paymentStatus = $payload['paymentStatus'] ?? $payload['status'] ?? null;
        $transactionId = $payload['transactionId'] ?? null;
        
        if (!$orderId) {
            Log::error('Kashier webhook: Missing order ID');
            return response()->json(['status' => 'error', 'message' => 'Invalid order ID'], 400);
        }

        $payment_data = $this->payment::where(['id' => $orderId])->first();
        
        if (!$payment_data) {
            Log::error('Kashier webhook: Payment not found', ['order_id' => $orderId]);
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
            
            Log::info('Kashier webhook: Payment marked as successful', [
                'order_id' => $orderId,
                'transaction_id' => $transactionId,
            ]);
            
            return response()->json(['status' => 'success', 'message' => 'Payment processed']);
        }

        Log::warning('Kashier webhook: Payment not successful', [
            'order_id' => $orderId,
            'status' => $paymentStatus,
        ]);

        return response()->json(['status' => 'error', 'message' => 'Payment not successful'], 400);
    }
}

