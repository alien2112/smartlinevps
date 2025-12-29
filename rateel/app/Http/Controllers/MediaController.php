<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    /**
     * Serve media files from /root/new/ directory
     *
     * @param Request $request
     * @param string $path The file path (e.g., "driver/profile/filename.webp")
     * @return BinaryFileResponse
     */
    public function serve(Request $request, string $path)
    {
        // Sanitize the path to prevent directory traversal
        $path = str_replace(['../', '..\\'], '', $path);

        // Try R2 storage first
        $disk = Storage::disk('r2');

        // Check if file exists in R2
        if ($disk->exists($path)) {
            try {
                // Get file from R2
                $file = $disk->get($path);
                $mimeType = $disk->mimeType($path);

                return Response::make($file, 200, [
                    'Content-Type' => $mimeType,
                    'Cache-Control' => 'public, max-age=31536000',
                ]);
            } catch (\Exception $e) {
                \Log::error('Error fetching from R2: ' . $e->getMessage());
            }
        }

        // Fall back to local filesystem
        $fullPath = '/root/new/' . $path;

        // Check if file exists locally
        if (!file_exists($fullPath) || !is_file($fullPath)) {
            // Try with .webp extension if original extension doesn't exist
            $pathInfo = pathinfo($fullPath);
            $webpPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';

            if (file_exists($webpPath) && is_file($webpPath)) {
                $fullPath = $webpPath;
            } else {
                // Return default/placeholder image
                $defaultImage = public_path('assets/admin-module/img/avatar/avatar.png');
                if (file_exists($defaultImage)) {
                    return response()->file($defaultImage);
                }
                abort(404, 'Image not found');
            }
        }

        // Get file mime type
        $mimeType = mime_content_type($fullPath);

        // Serve the file with appropriate headers
        return response()->file($fullPath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }

    /**
     * Get image URL for a given object key/path
     *
     * @param string|null $objectKey
     * @return string|null
     */
    public static function getImageUrl(?string $objectKey): ?string
    {
        if (empty($objectKey)) {
            return null;
        }

        // If it's already a full path starting with /root/new/
        if (str_starts_with($objectKey, '/root/new/')) {
            // Remove /root/new/ prefix to get relative path
            $relativePath = str_replace('/root/new/', '', $objectKey);
            return url('media/' . $relativePath);
        }

        // If it's a relative path, just prepend the media URL
        return url('media/' . $objectKey);
    }
}
