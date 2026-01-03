<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

/**
 * Image Upload Validation Rule
 * 
 * Validates image uploads using centralized configuration from config/image.php
 * 
 * Usage in FormRequest:
 *   'profile_image' => ['image', new ImageUpload()],
 *   'icon' => ['image', new ImageUpload('icon')],  // PNG only
 */
class ImageUpload implements Rule
{
    protected string $type;
    protected int $maxSize;
    protected array $mimes;
    protected string $errorMessage = '';

    /**
     * Create a new rule instance.
     *
     * @param string $type Type of image: 'profile', 'identity', 'banner', 'icon', 'vehicle_documents'
     */
    public function __construct(string $type = 'profile')
    {
        $this->type = $type;
        $this->loadConfig();
    }

    /**
     * Load configuration based on type
     */
    protected function loadConfig(): void
    {
        $config = config("image.{$this->type}");
        
        if ($config) {
            $this->maxSize = $config['max_size'] ?? config('image.max_size', 500);
            $this->mimes = $config['mimes'] ?? config('image.allowed_mimes');
        } else {
            // Default configuration
            $this->maxSize = config('image.max_size', 500);
            $this->mimes = config('image.allowed_mimes', ['jpeg', 'jpg', 'png', 'gif', 'webp']);
        }
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        if (!$value) {
            return true; // Let 'required' rule handle this
        }

        // Check if it's a valid uploaded file
        if (!$value instanceof \Illuminate\Http\UploadedFile) {
            $this->errorMessage = 'The :attribute must be a valid uploaded file.';
            return false;
        }

        // Check file size (convert KB to bytes)
        $maxBytes = $this->maxSize * 1024;
        if ($value->getSize() > $maxBytes) {
            $this->errorMessage = "The :attribute must not be larger than {$this->maxSize}KB.";
            return false;
        }

        // Check mime type
        $extension = strtolower($value->getClientOriginalExtension());
        if (!in_array($extension, $this->mimes)) {
            $this->errorMessage = 'The :attribute must be a file of type: ' . implode(', ', $this->mimes) . '.';
            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return $this->errorMessage;
    }

    /**
     * Get max size in KB
     */
    public function getMaxSize(): int
    {
        return $this->maxSize;
    }

    /**
     * Get allowed mimes
     */
    public function getMimes(): array
    {
        return $this->mimes;
    }
}
