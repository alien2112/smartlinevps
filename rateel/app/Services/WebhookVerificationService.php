<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook Verification Service
 *
 * Verifies signatures for various payment gateways to prevent
 * forged webhook attacks.
 */
class WebhookVerificationService
{
    /**
     * Verify Kashier webhook signature
     */
    public function verifyKashier(Request $request): bool
    {
        $apiKey = config('services.kashier.api_key');
        if (!$apiKey) {
            Log::warning('Kashier API key not configured');
            return false;
        }

        $signature = $request->header('x-kashier-signature');
        if (!$signature) {
            Log::warning('Kashier webhook missing signature');
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $apiKey);

        $isValid = hash_equals($expectedSignature, $signature);

        if (!$isValid) {
            Log::warning('Kashier webhook signature mismatch', [
                'expected' => substr($expectedSignature, 0, 10) . '...',
                'received' => substr($signature, 0, 10) . '...',
            ]);
        }

        return $isValid;
    }

    /**
     * Verify Stripe webhook signature
     */
    public function verifyStripe(Request $request): bool
    {
        $secret = config('services.stripe.webhook_secret');
        if (!$secret) {
            Log::warning('Stripe webhook secret not configured');
            return false;
        }

        $signature = $request->header('Stripe-Signature');
        if (!$signature) {
            Log::warning('Stripe webhook missing signature');
            return false;
        }

        $payload = $request->getContent();

        try {
            // Parse the signature header
            $elements = explode(',', $signature);
            $timestamp = null;
            $signatures = [];

            foreach ($elements as $element) {
                $parts = explode('=', $element, 2);
                if (count($parts) !== 2) continue;

                if ($parts[0] === 't') {
                    $timestamp = $parts[1];
                } elseif ($parts[0] === 'v1') {
                    $signatures[] = $parts[1];
                }
            }

            if (!$timestamp || empty($signatures)) {
                return false;
            }

            // Check timestamp tolerance (5 minutes)
            if (abs(time() - (int)$timestamp) > 300) {
                Log::warning('Stripe webhook timestamp too old');
                return false;
            }

            $signedPayload = "{$timestamp}.{$payload}";
            $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

            foreach ($signatures as $sig) {
                if (hash_equals($expectedSignature, $sig)) {
                    return true;
                }
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Stripe webhook verification failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Verify Paymob webhook signature
     */
    public function verifyPaymob(Request $request): bool
    {
        $hmacSecret = config('services.paymob.hmac_secret');
        if (!$hmacSecret) {
            Log::warning('Paymob HMAC secret not configured');
            return false;
        }

        $receivedHmac = $request->input('hmac');
        if (!$receivedHmac) {
            Log::warning('Paymob webhook missing HMAC');
            return false;
        }

        // Paymob specific concatenation order
        $obj = $request->input('obj');
        $concatenatedString = $obj['amount_cents'] .
            $obj['created_at'] .
            $obj['currency'] .
            ($obj['error_occured'] ? 'true' : 'false') .
            ($obj['has_parent_transaction'] ? 'true' : 'false') .
            $obj['id'] .
            $obj['integration_id'] .
            ($obj['is_3d_secure'] ? 'true' : 'false') .
            ($obj['is_auth'] ? 'true' : 'false') .
            ($obj['is_capture'] ? 'true' : 'false') .
            ($obj['is_refunded'] ? 'true' : 'false') .
            ($obj['is_standalone_payment'] ? 'true' : 'false') .
            ($obj['is_voided'] ? 'true' : 'false') .
            $obj['order']['id'] .
            $obj['owner'] .
            ($obj['pending'] ? 'true' : 'false') .
            $obj['source_data']['pan'] .
            $obj['source_data']['sub_type'] .
            $obj['source_data']['type'] .
            ($obj['success'] ? 'true' : 'false');

        $expectedHmac = hash_hmac('sha512', $concatenatedString, $hmacSecret);

        return hash_equals($expectedHmac, $receivedHmac);
    }

    /**
     * Verify Paystack webhook signature
     */
    public function verifyPaystack(Request $request): bool
    {
        $secretKey = config('services.paystack.secret_key');
        if (!$secretKey) {
            Log::warning('Paystack secret key not configured');
            return false;
        }

        $signature = $request->header('x-paystack-signature');
        if (!$signature) {
            Log::warning('Paystack webhook missing signature');
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha512', $payload, $secretKey);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Generic verification method
     */
    public function verify(string $gateway, Request $request): bool
    {
        return match (strtolower($gateway)) {
            'kashier' => $this->verifyKashier($request),
            'stripe' => $this->verifyStripe($request),
            'paymob', 'paymob_accept' => $this->verifyPaymob($request),
            'paystack' => $this->verifyPaystack($request),
            default => true, // For gateways without signature verification
        };
    }
}
