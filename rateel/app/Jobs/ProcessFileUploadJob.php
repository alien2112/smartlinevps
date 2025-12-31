<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

/**
 * Issue #19 FIX: Async File Upload Processing Job
 *
 * Handles heavy file processing (resizing, optimization) asynchronously
 * so API responses are not blocked by large image uploads.
 *
 * Usage:
 *   // In controller, save original file quickly
 *   $path = $file->store('temp');
 *
 *   // Dispatch job for background processing
 *   ProcessFileUploadJob::dispatch($path, 'driver-documents', [
 *       'resize' => [800, 600],
 *       'quality' => 80,
 *       'model_type' => 'driver',
 *       'model_id' => $driver->id,
 *       'field' => 'license_image',
 *   ]);
 */
class ProcessFileUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 120;

    private string $tempPath;
    private string $targetFolder;
    private array $options;

    /**
     * Create a new job instance.
     *
     * @param string $tempPath Path to temporarily stored file
     * @param string $targetFolder Target folder for processed file
     * @param array $options Processing options:
     *   - resize: [width, height] or null
     *   - quality: 1-100 for JPEG/PNG
     *   - model_type: Model class to update
     *   - model_id: Model ID to update
     *   - field: Model field to update with final path
     */
    public function __construct(string $tempPath, string $targetFolder, array $options = [])
    {
        $this->tempPath = $tempPath;
        $this->targetFolder = $targetFolder;
        $this->options = array_merge([
            'resize' => null,
            'quality' => 85,
            'model_type' => null,
            'model_id' => null,
            'field' => null,
        ], $options);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Check if temp file exists
            if (!Storage::exists($this->tempPath)) {
                Log::warning('ProcessFileUploadJob: Temp file not found', [
                    'path' => $this->tempPath
                ]);
                return;
            }

            $tempFullPath = Storage::path($this->tempPath);
            $extension = pathinfo($tempFullPath, PATHINFO_EXTENSION);
            $filename = time() . '_' . uniqid() . '.' . $extension;
            $targetPath = $this->targetFolder . '/' . $filename;

            // Process image if it's an image file
            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array(strtolower($extension), $imageExtensions)) {
                $this->processImage($tempFullPath, $targetPath);
            } else {
                // Just move non-image files
                Storage::move($this->tempPath, $targetPath);
            }

            // Update model if specified
            if ($this->options['model_type'] && $this->options['model_id'] && $this->options['field']) {
                $this->updateModel($targetPath);
            }

            // Clean up temp file
            Storage::delete($this->tempPath);

            Log::info('ProcessFileUploadJob completed', [
                'temp_path' => $this->tempPath,
                'target_path' => $targetPath,
                'model_type' => $this->options['model_type'],
                'model_id' => $this->options['model_id'],
            ]);

        } catch (\Exception $e) {
            Log::error('ProcessFileUploadJob failed', [
                'error' => $e->getMessage(),
                'temp_path' => $this->tempPath,
            ]);
            throw $e;
        }
    }

    /**
     * Process and optimize image
     */
    private function processImage(string $sourcePath, string $targetPath): void
    {
        $image = Image::make($sourcePath);

        // Resize if specified
        if ($this->options['resize']) {
            [$width, $height] = $this->options['resize'];
            $image->resize($width, $height, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        }

        // Get full target path
        $fullTargetPath = Storage::path($targetPath);

        // Ensure directory exists
        $directory = dirname($fullTargetPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Save with quality setting
        $image->save($fullTargetPath, $this->options['quality']);
    }

    /**
     * Update the model with the new file path
     */
    private function updateModel(string $path): void
    {
        $modelClass = $this->options['model_type'];
        $modelId = $this->options['model_id'];
        $field = $this->options['field'];

        // Map short names to full class names
        $modelMap = [
            'driver' => \Modules\UserManagement\Entities\User::class,
            'driver_details' => \Modules\UserManagement\Entities\DriverDetails::class,
            'user' => \Modules\UserManagement\Entities\User::class,
            'vehicle' => \Modules\VehicleManagement\Entities\Vehicle::class,
        ];

        $fullClass = $modelMap[$modelClass] ?? $modelClass;

        if (!class_exists($fullClass)) {
            Log::warning('ProcessFileUploadJob: Model class not found', [
                'model_type' => $modelClass
            ]);
            return;
        }

        $model = $fullClass::find($modelId);
        if ($model) {
            $model->{$field} = $path;
            $model->save();
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessFileUploadJob permanently failed', [
            'error' => $exception->getMessage(),
            'temp_path' => $this->tempPath,
            'target_folder' => $this->targetFolder,
        ]);

        // Clean up temp file on permanent failure
        Storage::delete($this->tempPath);
    }
}
