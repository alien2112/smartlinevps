<?php

namespace Modules\UserManagement\Service;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\UserManagement\Service\Interface\FastApiClientServiceInterface;

class FastApiClientService implements FastApiClientServiceInterface
{
    protected string $baseUrl;
    protected string $apiKey;
    protected int $timeout;
    protected int $retryTimes;
    protected int $retrySleep;

    public function __construct()
    {
        $config = config('verification.fastapi', []);
        $this->baseUrl = rtrim($config['base_url'] ?? 'http://localhost:8100', '/');
        $this->apiKey = $config['api_key'] ?? '';
        $this->timeout = $config['timeout'] ?? 120;
        $this->retryTimes = $config['retry_times'] ?? 3;
        $this->retrySleep = $config['retry_sleep'] ?? 1000;
    }

    /**
     * Call FastAPI verification endpoint.
     */
    public function verify(string $sessionId, array $mediaUrls): array
    {
        $url = "{$this->baseUrl}/internal/verify";

        $payload = [
            'session_id' => $sessionId,
            'media' => $mediaUrls,
        ];

        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->retry($this->retryTimes, $this->retrySleep, function ($exception, $request) {
                // Only retry on connection errors, not on 4xx/5xx responses
                return $exception instanceof \Illuminate\Http\Client\ConnectionException;
            })
            ->post($url, $payload);

            if ($response->failed()) {
                Log::error('FastAPI verification failed', [
                    'session_id' => $sessionId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \RuntimeException(
                    "FastAPI verification failed with status {$response->status()}: {$response->body()}"
                );
            }

            $result = $response->json();

            Log::info('FastAPI verification completed', [
                'session_id' => $sessionId,
                'suggested_decision' => $result['suggested_decision'] ?? 'unknown',
            ]);

            return $result;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('FastAPI connection failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Cannot connect to FastAPI service: {$e->getMessage()}");
        }
    }

    /**
     * Check if FastAPI service is healthy.
     */
    public function healthCheck(): bool
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
            ])
            ->timeout(10)
            ->get("{$this->baseUrl}/health");

            return $response->successful();

        } catch (\Exception $e) {
            Log::warning('FastAPI health check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get the base URL for testing purposes.
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}
