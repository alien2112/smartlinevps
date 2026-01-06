<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * KYC Verification Service Client
 * 
 * Communicates with the FastAPI KYC microservice.
 * Automatically starts the service if not running (on-demand).
 * The service auto-shuts down after 30 minutes of inactivity.
 */
class KycVerificationService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected int $port = 8100;
    protected int $maxStartupWaitSeconds = 60;

    public function __construct()
    {
        $this->baseUrl = config('services.kyc.url', 'http://localhost:8100');
        $this->apiKey = config('services.kyc.api_key', env('FASTAPI_VERIFICATION_KEY', 'your-secret-key'));
    }

    /**
     * Verify driver documents (liveness + ID validation)
     *
     * @param string $sessionId Unique session/driver ID
     * @param string $selfieUrl Signed URL to selfie image
     * @param string $idFrontUrl Signed URL to ID front image
     * @return array Verification result
     */
    public function verify(string $sessionId, string $selfieUrl, string $idFrontUrl): array
    {
        // Ensure service is running
        if (!$this->ensureServiceRunning()) {
            return [
                'success' => false,
                'error' => 'KYC service failed to start',
            ];
        }

        try {
            $response = Http::timeout(120)
                ->withHeaders([
                    'X-API-Key' => $this->apiKey,
                ])
                ->post("{$this->baseUrl}/internal/verify", [
                    'session_id' => $sessionId,
                    'media' => [
                        'selfie' => $selfieUrl,
                        'id_front' => $idFrontUrl,
                    ],
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            Log::error('KYC verification failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'Verification request failed',
                'status' => $response->status(),
            ];

        } catch (\Exception $e) {
            Log::error('KYC verification exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if KYC service is running
     */
    public function isServiceRunning(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/health");
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get service status including auto-shutdown timer
     */
    public function getServiceStatus(): ?array
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/internal/status");
            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            // Service not running
        }
        return null;
    }

    /**
     * Ensure the KYC service is running, start it if not
     */
    protected function ensureServiceRunning(): bool
    {
        if ($this->isServiceRunning()) {
            Log::debug('KYC service already running');
            return true;
        }

        Log::info('KYC service not running, starting...');

        // Start the service using the launcher script
        $scriptPath = base_path('../smartline-ai/start_kyc_service.sh');
        
        if (!file_exists($scriptPath)) {
            // Try alternate path
            $scriptPath = '/var/www/laravel/smartlinevps/smartline-ai/start_kyc_service.sh';
        }

        if (!file_exists($scriptPath)) {
            Log::error('KYC launcher script not found', ['path' => $scriptPath]);
            return false;
        }

        try {
            // Execute the launcher script
            $result = Process::run("bash {$scriptPath}");
            
            Log::info('KYC launcher result', [
                'output' => $result->output(),
                'exitCode' => $result->exitCode(),
            ]);

            // Wait for service to be ready
            return $this->waitForService();

        } catch (\Exception $e) {
            Log::error('Failed to start KYC service', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Wait for the service to become available
     */
    protected function waitForService(): bool
    {
        $startTime = time();
        
        while ((time() - $startTime) < $this->maxStartupWaitSeconds) {
            if ($this->isServiceRunning()) {
                Log::info('KYC service is now running');
                return true;
            }
            
            sleep(2);  // Check every 2 seconds
        }

        Log::error('KYC service failed to start within timeout');
        return false;
    }
}
