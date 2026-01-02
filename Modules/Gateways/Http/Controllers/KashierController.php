<?php

namespace Modules\Gateways\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\Gateways\Traits\Processor;
use Modules\Gateways\Entities\PaymentRequest;
use Modules\TripManagement\Entities\TripRequest;

/**
 * Kashier Payment Gateway Controller
 * 
 * Implements Kashier payment integration according to official documentation:
 * https://developers.kashier.io/
 * 
 * Features:
 * - Hosted Payment Page integration
 * - SHA256 HMAC signature verification
 * - Webhook support for payment notifications
 * - Secure callback validation
 */
class KashierController extends Controller
{
    use Processor;

    private array $config;
    private PaymentRequest $payment;
    private string $mode;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->paymentConfig('kashier', PAYMENT_CONFIG);

        if (!$config) {
            Log::error('Kashier configuration not found in database');
            abort(500, 'Kashier configuration not found');
        }

        $this->mode = $config->mode ?? 'test';
        $this->config = json_decode(
            $this->mode === 'live' ? $config->live_values : $config->test_values,
            true
        ) ?? [];

        $this->payment = $payment;
    }

    /**
     * Initialize payment and redirect to Kashier Hosted Payment Page
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function pay(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            Log::warning('Kashier: Invalid payment request', ['errors' => $validator->errors()]);
            return response()->json(['message' => 'Invalid request', 'errors' => $validator->errors()], 400);
        }

        try {
            // Find unpaid payment request
            $paymentData = $this->payment::where('id', $request->payment_id)
                ->where('is_paid', 0)
                ->first();

            if (!$paymentData) {
                Log::warning('Kashier: Payment not found or already paid', ['payment_id' => $request->payment_id]);
                return response()->json(['message' => 'Payment not found or already paid'], 404);
            }

            // Store payment ID in session
            session()->put('kashier_payment_id', $paymentData->id);

            // Get Kashier configuration
            $merchantId = $this->config['merchant_id'] ?? null;
            $apiKey = $this->config['api_key'] ?? null;
            $secretKey = $this->config['secret_key'] ?? null;
            $currency = $this->config['currency'] ?? 'EGP';

            if (!$merchantId || !$secretKey || !$apiKey) {
                Log::error('Kashier: Missing merchant_id, api_key or secret_key in configuration');
                return response()->json(['message' => 'Payment gateway not properly configured'], 500);
            }

            // Generate unique order ID using cryptographically secure random
            $orderId = 'SL-' . time() . '-' . random_int(1000, 9999);
            $amount = number_format((float)$paymentData->payment_amount, 2, '.', '');

            // Store order ID in session for callback verification
            session()->put('kashier_order_id', $orderId);

            // Generate hash according to Kashier documentation
            // Format: /?payment={merchantId}.{orderId}.{amount}.{currency}
            // NOTE: For API calls from payment page, hash must use API key (not secret key)
            $hashPath = "/?payment={$merchantId}.{$orderId}.{$amount}.{$currency}";
            // Use API key for hash generation - required for payment page API calls
            $hash = hash_hmac('sha256', $hashPath, $apiKey, false);

            // Build callback URL
            $callbackUrl = $this->config['callback_url'] ?? route('kashier.callback');
            $webhookUrl = $this->config['webhook_url'] ?? route('kashier.webhook');

            // Build Kashier Hosted Payment Page URL
            // According to Kashier docs: https://developers.kashier.io/payment/payment-ui#i-frame
            $paymentParams = [
                'merchantId' => $merchantId,
                'orderId' => $orderId,
                'amount' => $amount,
                'currency' => $currency,
                'hash' => $hash,
                'mode' => $this->mode,
                'apiKey' => $apiKey, // Required for live mode!
                'merchantRedirect' => $callbackUrl,
                'serverWebhook' => $webhookUrl,
                'failureRedirect' => 'false',
                'redirectMethod' => 'get',
                'allowedMethods' => 'card,wallet',
                'display' => 'en',
                'brandColor' => $this->config['brand_color'] ?? '#00bcbc',
                'interactionSource' => 'Ecommerce',
                'enable3DS' => 'true',
            ];

            $paymentUrl = 'https://payments.kashier.io/?' . http_build_query($paymentParams);

            Log::info('Kashier: Payment initiated', [
                'payment_id' => $paymentData->id,
                'order_id' => $orderId,
                'amount' => $amount,
                'currency' => $currency,
                'mode' => $this->mode,
                'merchant_id' => $merchantId,
                'api_key' => substr($apiKey, 0, 8) . '****', // Masked for security
            ]);

            return redirect()->away($paymentUrl);

        } catch (Exception $e) {
            Log::error('Kashier: Payment initialization failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Payment initialization failed'], 500);
        }
    }

    /**
     * Handle Kashier callback after payment
     * Validates signature and updates payment status
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function callback(Request $request)
    {
        try {
            $paymentId = session('kashier_payment_id');
            $expectedOrderId = session('kashier_order_id');

            if (!$paymentId) {
                Log::error('Kashier callback: Payment ID not found in session');
                return $this->redirectToFailure('Session expired');
            }

            $paymentData = $this->payment::where('id', $paymentId)->first();

            if (!$paymentData) {
                Log::error('Kashier callback: Payment not found', ['payment_id' => $paymentId]);
                return $this->redirectToFailure('Payment not found');
            }

            // Validate signature according to Kashier documentation
            if (!$this->validateSignature($request)) {
                Log::error('Kashier callback: Signature validation failed', [
                    'payment_id' => $paymentId,
                    'received_params' => $request->all()
                ]);
                return $this->paymentResponse($paymentData, 'fail');
            }

            // Check payment status
            $paymentStatus = $request->query('paymentStatus');
            $transactionId = $request->query('transactionId');
            $orderId = $request->query('orderId');

            // Verify order ID matches
            if ($orderId !== $expectedOrderId) {
                Log::error('Kashier callback: Order ID mismatch', [
                    'expected' => $expectedOrderId,
                    'received' => $orderId
                ]);
                return $this->paymentResponse($paymentData, 'fail');
            }

            if ($paymentStatus === 'SUCCESS') {
                // Update payment request
                DB::beginTransaction();
                try {
                    $this->payment::where('id', $paymentId)->update([
                        'payment_method' => 'kashier',
                        'is_paid' => 1,
                        'transaction_id' => $transactionId ?? $orderId,
                    ]);

                    // Update trip request payment status if applicable
                    if ($paymentData->attribute_id) {
                        TripRequest::where('id', $paymentData->attribute_id)->update([
                            'payment_method' => 'kashier',
                            'payment_status' => 'paid',
                        ]);
                    }

                    DB::commit();

                    Log::info('Kashier callback: Payment successful', [
                        'payment_id' => $paymentId,
                        'transaction_id' => $transactionId,
                        'order_id' => $orderId
                    ]);

                    // Clear session
                    session()->forget(['kashier_payment_id', 'kashier_order_id']);

                    // Call hook function if exists
                    if (isset($paymentData->hook) && function_exists($paymentData->hook)) {
                        call_user_func($paymentData->hook, $paymentData);
                    }

                    return $this->paymentResponse($paymentData, 'success');

                } catch (Exception $e) {
                    DB::rollBack();
                    Log::error('Kashier callback: Database update failed', [
                        'error' => $e->getMessage()
                    ]);
                    return $this->paymentResponse($paymentData, 'fail');
                }
            }

            Log::warning('Kashier callback: Payment not successful', [
                'payment_id' => $paymentId,
                'status' => $paymentStatus
            ]);

            return $this->paymentResponse($paymentData, 'fail');

        } catch (Exception $e) {
            Log::error('Kashier callback: Exception occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->route('payment-fail');
        }
    }

    /**
     * Handle Kashier webhook for server-to-server notifications
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function webhook(Request $request)
    {
        Log::info('Kashier webhook received', ['data' => $request->all()]);

        try {
            // Validate webhook signature
            if (!$this->validateSignature($request)) {
                Log::error('Kashier webhook: Signature validation failed');
                return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 400);
            }

            $orderId = $request->input('orderId') ?? $request->query('orderId');
            $paymentStatus = $request->input('paymentStatus') ?? $request->query('paymentStatus');
            $transactionId = $request->input('transactionId') ?? $request->query('transactionId');

            if (!$orderId) {
                return response()->json(['status' => 'error', 'message' => 'Order ID required'], 400);
            }

            // Find payment by transaction reference (we stored order ID in transaction_id temporarily)
            // Or we need to implement a proper order tracking mechanism
            
            Log::info('Kashier webhook: Processed', [
                'order_id' => $orderId,
                'status' => $paymentStatus,
                'transaction_id' => $transactionId
            ]);

            return response()->json(['status' => 'success', 'message' => 'Webhook processed']);

        } catch (Exception $e) {
            Log::error('Kashier webhook: Exception', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Validate Kashier signature according to official documentation
     * 
     * The signature is generated by:
     * 1. Building query string from all params except 'signature' and 'mode'
     * 2. Sorting parameters alphabetically
     * 3. Computing HMAC-SHA256 with secret key
     * 
     * @param Request $request
     * @return bool
     */
    private function validateSignature(Request $request): bool
    {
        $receivedSignature = $request->query('signature') ?? $request->input('signature');
        
        if (!$receivedSignature) {
            Log::warning('Kashier: No signature received');
            return false;
        }

        $secretKey = $this->config['secret_key'] ?? null;
        
        if (!$secretKey) {
            Log::error('Kashier: Secret key not configured');
            return false;
        }

        // Extract the actual secret (part after $) from the full secret key
        $secretParts = explode('$', $secretKey);
        $actualSecret = $secretParts[1] ?? $secretKey;

        // Build query string excluding signature and mode
        $params = $request->except(['signature', 'mode']);
        ksort($params); // Sort alphabetically as per Kashier docs

        $queryParts = [];
        foreach ($params as $key => $value) {
            $queryParts[] = $key . '=' . $value;
        }
        $queryString = implode('&', $queryParts);

        // Generate signature using the actual secret (part after $)
        $generatedSignature = hash_hmac('sha256', $queryString, $actualSecret, false);

        $isValid = hash_equals($generatedSignature, $receivedSignature);

        if (!$isValid) {
            Log::debug('Kashier signature validation', [
                'query_string' => $queryString,
                'generated' => $generatedSignature,
                'received' => $receivedSignature
            ]);
        }

        return $isValid;
    }

    /**
     * Redirect to failure page with error message
     * 
     * @param string $message
     * @return \Illuminate\Http\RedirectResponse
     */
    private function redirectToFailure(string $message): \Illuminate\Http\RedirectResponse
    {
        Log::warning('Kashier: Redirecting to failure', ['message' => $message]);
        return redirect()->route('payment-fail')->with('error', $message);
    }
}
