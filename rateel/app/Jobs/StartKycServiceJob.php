<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Cache;

/**
 * Job to start the KYC service asynchronously
 *
 * Created: 2026-01-14 - Performance optimization to avoid blocking HTTP requests
 * This job handles the slow service startup process in the background
 */
class StartKycServiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    protected string $baseUrl;
    protected int $maxStartupWaitSeconds;

    public function __construct(int $maxStartupWaitSeconds = 60)
    {
        $this->maxStartupWaitSeconds = $maxStartupWaitSeconds;
        $this->onQueue('high');
    }

    public function handle(): void
    {
        $this->baseUrl = config('services.kyc.url', 'http://localhost:8100');

        // Check if already running
        if ($this->isServiceRunning()) {
            Log::debug('StartKycServiceJob: KYC service already running');
            Cache::put('kyc_service_status', 'running', now()->addMinutes(5));
            return;
        }

        Log::info('StartKycServiceJob: Starting KYC service...');
        Cache::put('kyc_service_status', 'starting', now()->addMinutes(2));

        $scriptPath = base_path('../smartline-ai/start_kyc_service.sh');

        if (!file_exists($scriptPath)) {
            $scriptPath = '/var/www/laravel/smartlinevps/smartline-ai/start_kyc_service.sh';
        }

        if (!file_exists($scriptPath)) {
            Log::error('StartKycServiceJob: KYC launcher script not found', ['path' => $scriptPath]);
            Cache::put('kyc_service_status', 'error', now()->addMinutes(5));
            return;
        }

        try {
            $result = Process::run("bash {$scriptPath}");

            Log::info('StartKycServiceJob: Launcher result', [
                'output' => $result->output(),
                'exitCode' => $result->exitCode(),
            ]);

            // Wait for service to be ready (this is OK in a background job)
            if ($this->waitForService()) {
                Cache::put('kyc_service_status', 'running', now()->addMinutes(30));
                Log::info('StartKycServiceJob: KYC service started successfully');
            } else {
                Cache::put('kyc_service_status', 'error', now()->addMinutes(5));
                Log::error('StartKycServiceJob: KYC service failed to start');
            }

        } catch (\Exception $e) {
            Log::error('StartKycServiceJob: Failed to start KYC service', ['error' => $e->getMessage()]);
            Cache::put('kyc_service_status', 'error', now()->addMinutes(5));
        }
    }

    protected function isServiceRunning(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/health");
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function waitForService(): bool
    {
        $startTime = time();

        while ((time() - $startTime) < $this->maxStartupWaitSeconds) {
            if ($this->isServiceRunning()) {
                return true;
            }
            sleep(2); // OK to sleep in background job
        }

        return false;
    }
}
