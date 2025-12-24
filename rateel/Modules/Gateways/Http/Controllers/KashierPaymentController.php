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
     * Generate Kashier order hash for payment verification
     * Format as per Kashier docs: /?payment={mid}.{orderId}.{amount}.{currency}
     */
    private function generateOrderHash(string $merchantId, string $orderId, string $amount, string $currency, string $apiKey, ?string $customerReference = null): string
    {
        $path = "/?payment={$merchantId}.{$orderId}.{$amount}.{$currency}";

        // Add customer reference if provided (for card saving)
        if ($customerReference) {
            $path .= ".{$customerReference}";
        }

        return hash_hmac('sha256', $path, $apiKey);
    }

    /**
     * Validate Kashier response signature
     */
    private function validateSignature(array $params, string $apiKey): bool
    {
        if (!isset($params['signature'])) {
            \Log::warning('Kashier: No signature in response');
            return false;
        }

        $signature = $params['signature'];
        unset($params['signature']);
        unset($params['mode']); // mode is not included in signature

        // Build query string from remaining parameters
        $queryString = '';
        foreach ($params as $key => $value) {
            if ($value !== null && $value !== '') {
                $queryString .= "&{$key}={$value}";
            }
        }

        // Remove leading '&'
        $queryString = ltrim($queryString, '&');

        // Generate expected signature
        $expectedSignature = hash_hmac('sha256', $queryString, $apiKey);

        $isValid = hash_equals($expectedSignature, $signature);

        if (!$isValid) {
            \Log::warning('Kashier signature validation failed', [
                'expected' => $expectedSignature,
                'received' => $signature,
                'query_string' => $queryString
            ]);
        }

        return $isValid;
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

        // Kashier configuration from database
        $config = $this->getConfigValues();
        if (!$config) {
            return response()->json($this->responseFormatter(GATEWAYS_DEFAULT_400, null, [['error_code' => 'kashier', 'message' => 'Kashier configuration not found']]), 400);
        }

        $merchantId = $config->merchant_id ?? '';
        $apiKey = $config->api_key ?? $config->secret_key ?? '';

        if (empty($merchantId) || empty($apiKey)) {
            \Log::error('Kashier configuration incomplete', ['merchant_id' => $merchantId, 'has_api_key' => !empty($apiKey)]);
            return response()->json($this->responseFormatter(GATEWAYS_DEFAULT_400, null, [['error_code' => 'kashier', 'message' => 'Kashier configuration incomplete']]), 400);
        }

        // Payment details
        $orderId = $data->id;
        $amount = number_format($data->payment_amount, 2, '.', '');
        $currency = $data->currency_code ?? 'EGP';

        // Mode (test or live)
        $mode = $this->config->mode ?? 'test';

        // Generate hash for Kashier - CORRECT format as per documentation
        $hash = $this->generateOrderHash($merchantId, $orderId, $amount, $currency, $apiKey);

        // URLs
        $callbackUrl = url('/payment/kashier/callback');
        $webhookUrl = url('/payment/kashier/webhook');

        // Display language
        $display = app()->getLocale() === 'ar' ? 'ar' : 'en';

        \Log::info('Kashier payment initiated', [
            'order_id' => $orderId,
            'amount' => $amount,
            'currency' => $currency,
            'mode' => $mode
        ]);

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
            'webhookUrl',
            'mode',
            'display'
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
        $signature = $request->input('signature');

        \Log::info('Kashier callback received', [
            'paymentStatus' => $paymentStatus,
            'orderId' => $orderId,
            'transactionId' => $transactionId,
            'has_signature' => !empty($signature),
            'all_params' => $request->all()
        ]);

        if (!$orderId) {
            \Log::warning('Kashier callback: No orderId provided');
            return $this->paymentResponse(null, 'fail');
        }

        $payment_data = $this->payment::where(['id' => $orderId])->first();

        if (!$payment_data) {
            \Log::warning('Kashier callback: Payment not found for orderId: ' . $orderId);
            return $this->paymentResponse(null, 'fail');
        }

        // Get Kashier configuration
        $config = $this->getConfigValues();
        if (!$config) {
            \Log::error('Kashier callback: Configuration not found');
            return $this->paymentResponse($payment_data, 'fail');
        }

        $apiKey = $config->api_key ?? $config->secret_key ?? '';

        // Validate signature for security (only if signature is present)
        if ($signature && !$this->validateSignature($request->all(), $apiKey)) {
            \Log::error('Kashier callback: Invalid signature - possible tampering detected', [
                'orderId' => $orderId,
                'paymentStatus' => $paymentStatus
            ]);
            return $this->paymentResponse($payment_data, 'fail');
        }

        // Check if already paid to prevent double processing
        if ($payment_data->is_paid == 1) {
            \Log::info('Kashier callback: Payment already processed for orderId: ' . $orderId);
            return $this->paymentResponse($payment_data, 'success');
        }

        // Only process successful payments
        if (strtoupper($paymentStatus) === 'SUCCESS' || strtoupper($paymentStatus) === 'CAPTURED') {
            $this->payment::where(['id' => $orderId])->update([
                'payment_method' => 'kashier',
                'is_paid' => 1,
                'transaction_id' => $transactionId,
            ]);

            $data = $this->payment::where(['id' => $orderId])->first();

            // Call the hook function to update trip payment status
            if (isset($data) && !empty($data->hook) && function_exists($data->hook)) {
                \Log::info('Kashier callback: Calling hook ' . $data->hook . ' for orderId: ' . $orderId);
                call_user_func($data->hook, $data);
            }

            return $this->paymentResponse($data, 'success');
        }

        // Payment failed - do NOT call the hook, just return fail response
        \Log::warning('Kashier callback: Payment failed with status: ' . $paymentStatus . ' for orderId: ' . $orderId);
        return $this->paymentResponse($payment_data, 'fail');
    }

    /**
     * Handle Kashier webhook notifications
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->all();

        \Log::info('Kashier webhook received', [
            'has_signature' => isset($payload['signature']),
            'payment_status' => $payload['paymentStatus'] ?? null,
            'order_id' => $payload['merchantOrderId'] ?? $payload['orderId'] ?? null
        ]);

        $orderId = $payload['merchantOrderId'] ?? $payload['orderId'] ?? null;
        $paymentStatus = $payload['paymentStatus'] ?? null;
        $transactionId = $payload['transactionId'] ?? null;
        $signature = $payload['signature'] ?? null;

        if (!$orderId) {
            \Log::warning('Kashier webhook: No orderId provided');
            return response()->json(['status' => 'error', 'message' => 'Invalid order ID'], 400);
        }

        $payment_data = $this->payment::where(['id' => $orderId])->first();

        if (!$payment_data) {
            \Log::warning('Kashier webhook: Payment not found for orderId: ' . $orderId);
            return response()->json(['status' => 'error', 'message' => 'Payment not found'], 404);
        }

        // Get Kashier configuration
        $config = $this->getConfigValues();
        if (!$config) {
            \Log::error('Kashier webhook: Configuration not found');
            return response()->json(['status' => 'error', 'message' => 'Configuration error'], 500);
        }

        $apiKey = $config->api_key ?? $config->secret_key ?? '';

        // Validate signature for security (only if signature is present)
        if ($signature && !$this->validateSignature($payload, $apiKey)) {
            \Log::error('Kashier webhook: Invalid signature - possible tampering detected', [
                'orderId' => $orderId,
                'paymentStatus' => $paymentStatus
            ]);
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 403);
        }

        // Check if already paid to prevent double processing
        if ($payment_data->is_paid == 1) {
            \Log::info('Kashier webhook: Payment already processed for orderId: ' . $orderId);
            return response()->json(['status' => 'success', 'message' => 'Payment already processed']);
        }

        if (strtoupper($paymentStatus) === 'SUCCESS' || strtoupper($paymentStatus) === 'CAPTURED') {
            $this->payment::where(['id' => $orderId])->update([
                'payment_method' => 'kashier',
                'is_paid' => 1,
                'transaction_id' => $transactionId,
            ]);

            $data = $this->payment::where(['id' => $orderId])->first();
            if (isset($data) && !empty($data->hook) && function_exists($data->hook)) {
                \Log::info('Kashier webhook: Calling hook ' . $data->hook . ' for orderId: ' . $orderId);
                call_user_func($data->hook, $data);
            }

            return response()->json(['status' => 'success', 'message' => 'Payment processed']);
        }

        \Log::warning('Kashier webhook: Payment not successful, status: ' . $paymentStatus);
        return response()->json(['status' => 'error', 'message' => 'Payment not successful'], 400);
    }
}
