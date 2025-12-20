<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class SecureFileUpload implements ValidationRule
{
    protected array $allowedMimeTypes;
    protected array $dangerousExtensions = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'pht', 'phar',
        'exe', 'dll', 'bat', 'cmd', 'com', 'sh', 'bash', 'zsh', 'csh',
        'js', 'jar', 'app', 'vb', 'vbs', 'wsf', 'asp', 'aspx', 'cer',
    ];

    /**
     * Create a new rule instance.
     *
     * @param array $allowedMimeTypes MIME types allowed (e.g., ['image/jpeg', 'image/png'])
     */
    public function __construct(array $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/jpg'])
    {
        $this->allowedMimeTypes = $allowedMimeTypes;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Ensure the value is an uploaded file
        if (!$value instanceof UploadedFile) {
            $fail('The :attribute must be a valid file.');
            return;
        }

        // Check if file was uploaded successfully
        if (!$value->isValid()) {
            $fail('The :attribute upload failed.');
            return;
        }

        // Get the real file extension
        $extension = strtolower($value->getClientOriginalExtension());

        // Check for dangerous extensions
        if (in_array($extension, $this->dangerousExtensions)) {
            $fail('The :attribute file type is not allowed for security reasons.');
            return;
        }

        // Validate actual MIME type from file contents (not client-provided)
        try {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $value->getRealPath());
            finfo_close($finfo);

            if (!in_array($mimeType, $this->allowedMimeTypes)) {
                $fail('The :attribute must be a valid file of type: ' . implode(', ', $this->allowedMimeTypes));
                return;
            }

            // Additional check: ensure MIME type matches extension for images
            if (str_starts_with($mimeType, 'image/')) {
                $expectedExtensions = [
                    'image/jpeg' => ['jpg', 'jpeg'],
                    'image/png' => ['png'],
                    'image/gif' => ['gif'],
                    'image/webp' => ['webp'],
                ];

                if (isset($expectedExtensions[$mimeType])) {
                    if (!in_array($extension, $expectedExtensions[$mimeType])) {
                        $fail('The :attribute file extension does not match its content type.');
                        return;
                    }
                }
            }

            // Check for PHP code in image files (common bypass technique)
            if (str_starts_with($mimeType, 'image/')) {
                $contents = file_get_contents($value->getRealPath());
                if (preg_match('/<\?php/i', $contents) || preg_match('/<script/i', $contents)) {
                    $fail('The :attribute contains malicious content.');
                    return;
                }
            }

        } catch (\Exception $e) {
            $fail('The :attribute could not be validated.');
            return;
        }
    }
}
