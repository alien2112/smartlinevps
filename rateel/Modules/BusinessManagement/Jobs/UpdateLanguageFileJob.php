<?php

namespace Modules\BusinessManagement\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to update language files asynchronously
 *
 * Created: 2026-01-14 - Performance optimization to avoid blocking HTTP requests
 * File I/O operations are now handled in background queue
 */
class UpdateLanguageFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(
        protected string $lang,
        protected string $key,
        protected string $value,
        protected string $operation = 'update' // 'update' or 'auto_translate'
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $langFilePath = base_path('resources/lang/' . $this->lang . '/lang.php');

        if (!file_exists($langFilePath)) {
            Log::error('UpdateLanguageFileJob: Language file not found', ['lang' => $this->lang]);
            return;
        }

        try {
            // Use file locking to prevent race conditions
            $lockFile = base_path('resources/lang/' . $this->lang . '/.lock');
            $lock = fopen($lockFile, 'w');

            if (flock($lock, LOCK_EX)) {
                $translateData = include($langFilePath);
                $translateData[$this->key] = $this->value;

                $str = "<?php\n\nreturn " . var_export($translateData, true) . ";\n";
                file_put_contents($langFilePath, $str);

                flock($lock, LOCK_UN);
                Log::info('UpdateLanguageFileJob: Language file updated', [
                    'lang' => $this->lang,
                    'key' => $this->key
                ]);
            }

            fclose($lock);
            @unlink($lockFile);

        } catch (\Exception $e) {
            Log::error('UpdateLanguageFileJob: Failed to update language file', [
                'lang' => $this->lang,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
