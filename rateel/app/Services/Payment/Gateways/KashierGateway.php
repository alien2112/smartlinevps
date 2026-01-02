<?php

namespace App\Services\Payment\Gateways;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class KashierGateway
{
    private Client $client;
    private string $baseUrl;
    private string $merchantId;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('payment.gateways.kashier.base_url');
        $this->merchantId = config('payment.gateways.kashier.merchant_id');
        $this->apiKey = config('payment.gateways.kashier.api_key');

        // NOTE: For hosted payment page, we don't need to initialize the HTTP client
        // The client is only created if needed (lazy initialization)
        // This prevents connection attempts to unavailable endpoints
        $this->client = null;
    }
    
    /**
     * Lazy initialization of HTTP client (only if needed)
     */
    private function getClient(): ?Client
    {
        // For hosted payment page integration, we don't use direct API calls
        // Return null to indicate API is not available
        return null;
    }

    /**
     * Create order at Kashier
     *
     * @param array $data
     * @param int $timeout
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createOrder(array $data, int $timeout = 30): array
    {
        // NOTE: For hosted payment page integration, direct API calls are NOT used
        // The payment is initiated via the hosted payment page URL at payments.kashier.io
        // This method should NOT be called - payments go through KashierPaymentController
        
        Log::warning('Kashier: createOrder called - This should not be used for hosted payment page', [
            'order_id' => $data['merchant_order_id'] ?? null,
            'amount' => $data['amount'] ?? null,
            'note' => 'Use /payment/kashier/pay route instead',
        ]);
        
        // Return response indicating to use hosted payment page
        // The actual payment URL should be generated in KashierPaymentController
        return [
            'status' => 'PENDING',
            'order_id' => $data['merchant_order_id'] ?? null,
            'message' => 'Use hosted payment page URL - do not call API',
            'payment_url_required' => true,
            'use_hosted_page' => true,
        ];
        
        /* Direct API call disabled - fep.kashier.io/v3/orders endpoint not available
         * Using hosted payment page instead at payments.kashier.io
         * 
        $payload = $this->prepareOrderPayload($data);

        Log::info('Sending order to Kashier', [
            'order_id' => $data['merchant_order_id'],
            'amount' => $data['amount'],
        ]);

        try {
            $response = $this->client->post('/v3/orders', [
                'json' => $payload,
                'timeout' => $timeout,
                'connect_timeout' => 10,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            Log::info('Kashier response received', [
                'order_id' => $data['merchant_order_id'],
                'status' => $body['status'] ?? null,
            ]);

            return $this->normalizeResponse($body);

        } catch (RequestException $e) {
            // Parse error response
            $errorBody = $e->getResponse() ? (string) $e->getResponse()->getBody() : null;
            $errorData = $errorBody ? json_decode($errorBody, true) : null;

            Log::error('Kashier API error', [
                'order_id' => $data['merchant_order_id'],
                'status_code' => $e->getCode(),
                'error' => $errorData,
            ]);

            // Check for specific Kashier errors
            if ($errorData && isset($errorData['cause'])) {
                // Handle "getaddrinfo EAI_AGAIN" and similar infrastructure errors
                if (str_contains($errorData['cause'], 'getaddrinfo') ||
                    str_contains($errorData['cause'], 'EAI_AGAIN') ||
                    $errorData['status'] === 'SERVER_ERROR') {

                    return [
                        'status' => 'SERVER_ERROR',
                        'cause' => $errorData['cause'] ?? 'SERVER_ERROR',
                        'messages' => $errorData['messages'] ?? [
                            'en' => 'Gateway server error - Use hosted payment page instead',
                            'ar' => 'خطأ في الخادم - استخدم صفحة الدفع المستضافة'
                        ],
                    ];
                }
            }

            throw $e;
        }
        */
    }

    /**
     * Get order status from Kashier
     *
     * @param string $orderId Kashier order ID
     * @return array
     */
    public function getOrderStatus(string $orderId): array
    {
        // NOTE: Direct API status check NOT available for Kashier hosted payment page
        // Status is received via webhook or callback from hosted payment page
        // This method should NOT be called - use webhook/callback instead
        
        Log::warning('Kashier: getOrderStatus called - API endpoint not available', [
            'kashier_order_id' => $orderId,
            'note' => 'Status should be received via webhook/callback, not API',
        ]);
        
        // Return unknown status - actual status comes from webhook/callback
        return [
            'status' => 'UNKNOWN',
            'order_id' => $orderId,
            'message' => 'Status check via API not available - check webhook/callback',
            'use_webhook' => true,
        ];
        
        /* Direct API call disabled - endpoint not available
         * 
        try {
            $response = $this->client->get("/v3/orders/{$orderId}", [
                'timeout' => 15,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            return $this->normalizeResponse($body);

        } catch (RequestException $e) {
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;

            // 404 means order not found (might not exist yet)
            if ($statusCode === 404) {
                return [
                    'status' => 'NOT_FOUND',
                    'message' => 'Order not found in gateway',
                ];
            }

            throw $e;
        }
        */
    }

    /**
     * Verify webhook signature
     * NOTE: Kashier secret key format is "key_id$actual_secret"
     * We must use only the part AFTER the $ for HMAC generation
     *
     * @param array $payload
     * @param string $signature
     * @return bool
     */
    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        $secretKey = config('payment.gateways.kashier.secret_key');

        // Extract the actual secret (part after $) from the full secret key
        $secretParts = explode('$', $secretKey);
        $actualSecret = $secretParts[1] ?? $secretKey;

        // Remove signature from payload
        $payloadToSign = $payload;
        unset($payloadToSign['signature']);

        // Sort keys alphabetically
        ksort($payloadToSign);

        // Build query string
        $queryString = http_build_query($payloadToSign);

        // Generate HMAC using the actual secret
        $expectedSignature = hash_hmac('sha256', $queryString, $actualSecret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Prepare order payload for Kashier API
     */
    private function prepareOrderPayload(array $data): array
    {
        return [
            'merchantId' => $this->merchantId,
            'merchantOrderId' => $data['merchant_order_id'],
            'amount' => [
                'value' => (float) $data['amount'],
                'currency' => $data['currency'],
            ],
            'customer' => [
                'id' => $data['customer_id'],
            ],
            'orderItems' => [
                [
                    'name' => $data['description'] ?? 'Ride payment',
                    'quantity' => 1,
                    'price' => (float) $data['amount'],
                ],
            ],
            'metadata' => $data['metadata'] ?? [],
            'webhookUrl' => route('api.payment.webhook.kashier'),
            'redirectUrl' => $data['redirect_url'] ?? route('payment.success'),
        ];
    }

    /**
     * Normalize Kashier response to standard format
     */
    private function normalizeResponse(array $response): array
    {
        return [
            'status' => $response['status'] ?? 'UNKNOWN',
            'order_id' => $response['orderId'] ?? $response['order_id'] ?? null,
            'transaction_id' => $response['transactionId'] ?? $response['transaction_id'] ?? null,
            'amount' => $response['amount']['value'] ?? $response['amount'] ?? null,
            'currency' => $response['amount']['currency'] ?? $response['currency'] ?? null,
            'payment_method' => $response['paymentMethod'] ?? null,
            'raw_response' => $response,
        ];
    }
}
