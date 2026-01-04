<?php

use App\CentralLogics\Helpers;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\BusinessManagement\Entities\ExternalConfiguration;
use Modules\BusinessManagement\Entities\ReferralEarningSetting;
use Modules\UserManagement\Entities\User;
use Pusher\Pusher;
use Pusher\PusherException;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Modules\BusinessManagement\Entities\BusinessSetting;
use Modules\AdminModule\Repositories\ActivityLogRepository;
use Modules\BusinessManagement\Entities\FirebasePushNotification;

if (!function_exists('translate')) {
    function translate($key, $replace = []): array|string|Translator|null
    {
        $local = app()->getLocale();
        try {
            $langFile = base_path('resources/lang/' . $local . '/lang.php');
            $langArray = file_exists($langFile) ? include($langFile) : [];

            // Ensure langArray is actually an array
            if (!is_array($langArray)) {
                $langArray = [];
            }

            $processedKey = ucfirst(str_replace('_', ' ', removeSpecialCharacters($key)));
            $key = removeSpecialCharacters($key);
            if (!array_key_exists($key, $langArray)) {
                $langArray[$key] = $processedKey;
                $str = "<?php return " . var_export($langArray, true) . ";";
                file_put_contents($langFile, $str);
                $result = $processedKey;
            } else {
                $result = trans('lang.' . $key);
            }
        } catch (\Exception $exception) {
            $result = trans('lang.' . $key);
        }
        return $result;
    }
}
if (!function_exists('defaultLang')) {
    function defaultLang()
    {
        if (strpos(url()->current(), '/api')) {
            $lang = App::getLocale();
        } elseif (session()->has('locale')) {
            $lang = session('locale');
        } elseif (businessConfig('system_language', 'language_settings')) {
            $data = businessConfig('system_language', 'language_settings')->value;
            $code = 'en';
            $direction = 'ltr';
            foreach ($data as $ln) {
                if (array_key_exists('default', $ln) && $ln['default']) {
                    $code = $ln['code'];
                    if (array_key_exists('direction', $ln)) {
                        $direction = $ln['direction'];
                    }
                }
            }
            session()->put('locale', $code);
            session()->put('direction', $direction);
            $lang = $code;
        } else {
            $lang = App::getLocale();
        }
        return $lang;
    }
}


if (!function_exists('removeSpecialCharacters')) {
    function removeSpecialCharacters(string|null $text): string|null
    {
        return str_ireplace(['\'', '"', ',', ';', '<', '>', '?'], ' ', preg_replace('/\s\s+/', ' ', $text));
    }
}

if (!function_exists('convertImageToWebP')) {
    /**
     * Convert and compress an image to WebP format
     *
     * @param mixed $image Image path string or UploadedFile object
     * @param int $quality WebP quality (0-100, default 85)
     * @return string|false Returns WebP image data or false on failure
     */
    function convertImageToWebP($image, int $quality = 85)
    {
        try {
            // Get the image path
            $imagePath = is_string($image) ? $image : $image->getRealPath();

            // Detect mime type
            $imageInfo = getimagesize($imagePath);
            if ($imageInfo === false) {
                throw new \Exception('Invalid image file');
            }

            $mimeType = $imageInfo['mime'];

            // Create image resource based on mime type
            switch ($mimeType) {
                case 'image/jpeg':
                case 'image/jpg':
                    $imageResource = imagecreatefromjpeg($imagePath);
                    break;
                case 'image/png':
                    $imageResource = imagecreatefrompng($imagePath);
                    // Preserve transparency for PNG
                    imagepalettetotruecolor($imageResource);
                    imagealphablending($imageResource, true);
                    imagesavealpha($imageResource, true);
                    break;
                case 'image/gif':
                    $imageResource = imagecreatefromgif($imagePath);
                    break;
                case 'image/webp':
                    // Already WebP, just optimize it
                    $imageResource = imagecreatefromwebp($imagePath);
                    break;
                default:
                    throw new \Exception('Unsupported image type: ' . $mimeType);
            }

            if ($imageResource === false) {
                throw new \Exception('Failed to create image resource');
            }

            // Convert to WebP
            ob_start();
            imagewebp($imageResource, null, $quality);
            $webpData = ob_get_clean();

            // Free memory
            imagedestroy($imageResource);

            return $webpData;
        } catch (\Exception $e) {
            Log::error('WebP conversion failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

if (!function_exists('fileUploader')) {
    function fileUploader(string $dir, string $format, $image = null, $oldImage = null)
    {
                    if ($image == null) {
                        return $oldImage ?? 'def.png';
                    }
                    try {
                        $disk = config('media.disk', 'secure_local'); // Use configured disk for file storage
                    // Delete old image(s) if exists
            if (is_array($oldImage) && !empty($oldImage)) {
                // Handle the case when $oldImage is an array (multiple images)
                foreach ($oldImage as $file) {
                    try {
                        Storage::disk($disk)->delete($dir . $file);
                    } catch (\Exception $e) {
                        Log::warning('Failed to delete old image', [
                            'file' => $dir . $file,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } elseif (is_string($oldImage) && !empty($oldImage)) {
                // Handle the case when $oldImage is a single image (string)
                try {
                    Storage::disk($disk)->delete($dir . $oldImage);
                } catch (\Exception $e) {
                    Log::warning('Failed to delete old image', [
                        'file' => $dir . $oldImage,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Force WebP format for images
            $isImage = in_array(strtolower($format), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            $finalFormat = $isImage ? 'webp' : $format;

            $imageName = Carbon::now()->toDateString() . "-" . uniqid() . "." . $finalFormat;

            // Create directory if it doesn't exist (R2 handles this automatically)
            if (!Storage::disk($disk)->exists($dir)) {
                Storage::disk($disk)->makeDirectory($dir);
            }

            // Get file contents and convert to WebP if it's an image
            if ($isImage) {
                $webpData = convertImageToWebP($image, 85);
                if ($webpData === false) {
                    throw new \Exception('Failed to convert image to WebP');
                }
                $contents = $webpData;
            } else {
                // For non-image files, just get contents
                if (is_string($image)) {
                    $contents = file_get_contents($image);
                } else {
                    $contents = file_get_contents($image->getRealPath());
                }

                if ($contents === false) {
                    throw new \Exception('Failed to read file contents');
                }
            }

            // Store the new image in Cloudflare R2
            Storage::disk($disk)->put($dir . $imageName, $contents);

            return $imageName;
        } catch (\Exception $e) {
            Log::error('File upload failed', [
                'directory' => $dir,
                'format' => $format,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \RuntimeException('Failed to upload file: ' . $e->getMessage(), 0, $e);
        }
    }
}

if (!function_exists('fileRemover')) {
    function fileRemover(string $dir, $image)
    {
        if (!isset($image)) return true;

        try {
            $disk = 'r2'; // Use Cloudflare R2 for file storage

            if (Storage::disk($disk)->exists($dir . $image)) {
                Storage::disk($disk)->delete($dir . $image);
                Log::info('File deleted successfully', ['file' => $dir . $image]);
            }
            return true;
        } catch (\Exception $e) {
            Log::error('File deletion failed', [
                'file' => $dir . $image,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return false instead of throwing to allow graceful degradation
            return false;
        }
    }
}

if (!function_exists('signedMediaUrl')) {
    /**
     * Generate a signed URL for a media object key.
     *
     * @param string|null $objectKey Object key in storage (e.g., "driver/123/profile/uuid.jpg")
     * @param int|null $expiresIn TTL in seconds (null = use default)
     * @param string|null $uid Optional user ID for per-user validation
     * @param string|null $scope Optional scope (e.g., "kyc", "profile")
     * @return string|null Signed URL or null if object key is empty
     */
    function signedMediaUrl(?string $objectKey, ?int $expiresIn = null, ?string $uid = null, ?string $scope = null): ?string
    {
        if (empty($objectKey)) {
            return null;
        }

        try {
            $signer = app(\App\Services\Media\MediaUrlSigner::class);
            return $signer->sign($objectKey, $expiresIn, $uid, $scope);
        } catch (\Exception $e) {
            Log::warning('Failed to generate signed URL', [
                'object_key' => $objectKey,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}

if (!function_exists('signedMediaUrls')) {
    /**
     * Generate signed URLs for multiple object keys (batch).
     *
     * @param array|null $objectKeys Array of object keys
     * @param int|null $expiresIn TTL in seconds
     * @param string|null $uid User ID
     * @param string|null $scope Scope
     * @return array Array of signed URLs (empty strings for invalid keys)
     */
    function signedMediaUrls(?array $objectKeys, ?int $expiresIn = null, ?string $uid = null, ?string $scope = null): array
    {
        if (empty($objectKeys)) {
            return [];
        }

        $result = [];
        foreach ($objectKeys as $key) {
            $result[] = signedMediaUrl($key, $expiresIn, $uid, $scope);
        }
        return array_filter($result);
    }
}

if (!function_exists('mediaResponse')) {
    /**
     * Create a media response with both object_key and signed_url.
     * Useful for API responses where client needs both for caching.
     *
     * @param string|null $objectKey The stored object key
     * @param int|null $expiresIn TTL in seconds
     * @param string|null $uid User ID
     * @param string|null $scope Scope
     * @return array|null ['object_key' => ..., 'signed_url' => ..., 'expires_at' => ...]
     */
    function mediaResponse(?string $objectKey, ?int $expiresIn = null, ?string $uid = null, ?string $scope = null): ?array
    {
        if (empty($objectKey)) {
            return null;
        }

        $signedUrl = signedMediaUrl($objectKey, $expiresIn, $uid, $scope);
        $ttl = $expiresIn ?? config('media.default_ttl', 300);

        return [
            'object_key' => $objectKey,
            'signed_url' => $signedUrl,
            'expires_at' => now()->addSeconds($ttl)->toIso8601String(),
        ];
    }
}

if (!function_exists('paginationLimit')) {
    function paginationLimit()
    {
        return getSession('pagination_limit') == false ? 10 : getSession('pagination_limit');
    }
}

if (!function_exists('stepValue')) {
    function stepValue()
    {
        $points = (int)getSession('currency_decimal_point') ?? 0;
        return 1 / pow(10, $points);
    }
}
if (!function_exists('businessConfig')) {
    function businessConfig($key, $settingsType = null)
    {
        try {
            $config = BusinessSetting::query()
                ->where('key_name', $key)
                ->when($settingsType, function ($query) use ($settingsType) {
                    $query->where('settings_type', $settingsType);
                })
                ->first();
        } catch (Exception $exception) {
            return null;
        }

        return (isset($config)) ? $config : null;
    }
}

if (!function_exists('newBusinessConfig')) {
    function newBusinessConfig($key, $settingsType = null)
    {
        $businessSettings = Cache::rememberForever(CACHE_BUSINESS_SETTINGS, function () {
            return BusinessSetting::all();
        });

        try {
            $config = $businessSettings->where('key_name', $key)
                ->when($settingsType, function ($query) use ($settingsType) {
                    $query->where('settings_type', $settingsType);
                })
                ->first()?->value;
        } catch (Exception $exception) {
            return null;
        }
        return (isset($config)) ? $config : null;
    }
}
if (!function_exists('referralEarningSetting')) {
    function referralEarningSetting($key, $settingsType = null)
    {
        try {
            $config = ReferralEarningSetting::query()
                ->where('key_name', $key)
                ->when($settingsType, function ($query) use ($settingsType) {
                    $query->where('settings_type', $settingsType);
                })
                ->first();
        } catch (Exception $exception) {
            return null;
        }

        return (isset($config)) ? $config : null;
    }
}
if (!function_exists('externalConfig')) {
    function externalConfig($key)
    {
        try {
            $config = ExternalConfiguration::query()
                ->where('key', $key)
                ->first();
        } catch (Exception $exception) {
            return null;
        }
        return (isset($config)) ? $config : null;
    }
}
if (!function_exists('checkExternalConfiguration')) {
    function checkExternalConfiguration($externalBaseUrl, $externalTokem, $drivemondToken)
    {
        $activationMode = externalConfig('activation_mode')?->value;
        $martBaseUrl = externalConfig('mart_base_url')?->value;
        $martToken = externalConfig('mart_token')?->value;
        $systemSelfToken = externalConfig('system_self_token')?->value;
        return $activationMode == 1 && $martBaseUrl == $externalBaseUrl && $martToken == $externalTokem && $systemSelfToken == $drivemondToken;
    }
}
if (!function_exists('checkSelfExternalConfiguration')) {
    function checkSelfExternalConfiguration()
    {
        $activationMode = externalConfig('activation_mode')?->value;
        $martBaseUrl = externalConfig('mart_base_url')?->value;
        $martToken = externalConfig('mart_token')?->value;
        $systemSelfToken = externalConfig('system_self_token')?->value;
        return $activationMode == 1 && $martBaseUrl != null && $martToken != null && $systemSelfToken != null;
    }
}

if (!function_exists('generateReferralCode')) {
    function generateReferralCode($user = null)
    {
        $refCode = strtoupper(Str::random(10));
        if (User::where('ref_code', $refCode)->exists()) {
            generateReferralCode();
        }
        if ($user) {
            $user->ref_code = $refCode;
            $user->save();
        }
        return $refCode;
    }
}


if (!function_exists('utf8Clean')) {
    /**
     * Clean string to ensure valid UTF-8 encoding for JSON responses
     * 
     * @param mixed $input The input to clean
     * @return mixed The cleaned input
     */
    function utf8Clean($input)
    {
        if (is_null($input)) {
            return null;
        }
        
        if (is_string($input)) {
            // Remove invalid UTF-8 sequences
            $cleaned = mb_convert_encoding($input, 'UTF-8', 'UTF-8');
            
            // Remove any remaining invalid characters
            $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $cleaned);
            
            // If still has issues, try iconv as fallback
            if (!mb_check_encoding($cleaned, 'UTF-8')) {
                $cleaned = iconv('UTF-8', 'UTF-8//IGNORE', $input);
            }
            
            return $cleaned ?: '';
        }
        
        if (is_array($input)) {
            return array_map('utf8Clean', $input);
        }
        
        if (is_object($input)) {
            foreach ($input as $key => $value) {
                $input->$key = utf8Clean($value);
            }
            return $input;
        }
        
        return $input;
    }
}

if (!function_exists('responseFormatter')) {
    function responseFormatter($constant, $content = null, $limit = null, $offset = null, $errors = []): array
    {
        // Handle paginator objects
        $totalSize = null;
        if (isset($limit) && $content !== null) {
            if (is_object($content) && method_exists($content, 'total')) {
                $totalSize = $content->total();
            } elseif (is_array($content) && isset($content['total'])) {
                $totalSize = $content['total'];
            }
        }

        $data = [
            'total_size' => $totalSize,
            'limit' => $limit,
            'offset' => $offset,
            'data' => $content,
            'errors' => $errors,
        ];
        $responseConst = [
            'response_code' => $constant['response_code'],
            'message' => translate($constant['message']),
        ];
        return array_merge($responseConst, $data);
    }
}

if (!function_exists('errorProcessor')) {
    function errorProcessor($validator)
    {
        $errors = [];
        foreach ($validator->errors()->getMessages() as $index => $error) {
            $errors[] = ['error_code' => $index, 'message' => translate($error[0])];
        }
        return $errors;
    }
}


if (!function_exists('autoTranslator')) {
    function autoTranslator($q, $sl, $tl): array|string
    {
        try {
            // Set timeout context for file_get_contents
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10, // 10 seconds timeout
                    'ignore_errors' => true,
                ]
            ]);

            $url = "https://translate.googleapis.com/translate_a/single?client=gtx&ie=UTF-8&oe=UTF-8&dt=bd&dt=ex&dt=ld&dt=md&dt=qca&dt=rw&dt=rm&dt=ss&dt=t&dt=at&sl=" . $sl . "&tl=" . $tl . "&hl=hl&q=" . urlencode($q);

            $res = @file_get_contents($url, false, $context);

            if ($res === false) {
                throw new \Exception('Failed to fetch translation from Google Translate API');
            }

            $decoded = json_decode($res);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response from translation API: ' . json_last_error_msg());
            }

            if (!isset($decoded[0][0][0])) {
                throw new \Exception('Unexpected translation API response structure');
            }

            return str_replace('_', ' ', $decoded[0][0][0]);

        } catch (\Exception $e) {
            Log::error('Translation API call failed', [
                'query' => $q,
                'source_lang' => $sl,
                'target_lang' => $tl,
                'error' => $e->getMessage(),
            ]);

            // Return original query as fallback
            return $q;
        }
    }
}

if (!function_exists('getLanguageCode')) {
    function getLanguageCode(string $countryCode): string
    {
        foreach (LANGUAGES as $locale) {
            if ($countryCode == $locale['code']) {
                return $countryCode;
            }
        }
        return "en";
    }

}

if (!function_exists('exportData')) {
    function exportData($data, $file, $viewPath)
    {
        return match ($file) {
            'csv' => (new FastExcel($data))->download(time() . '-file.csv'),
            'excel' => (new FastExcel($data))->download(time() . '-file.xlsx'),
            'pdf' => Pdf::loadView($viewPath, ['data' => $data])->download(time() . '-file.pdf'),
            default => view($viewPath, ['data' => $data]),
        };
    }
}

/**
 * Issue #28 FIX: Streaming export for large datasets
 *
 * Uses generators and chunking to avoid loading all data into memory.
 * Supports CSV and Excel exports with proper headers.
 */
if (!function_exists('exportDataStreamed')) {
    function exportDataStreamed(callable $queryBuilder, callable $rowMapper, string $format = 'csv', string $filename = null): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filename = $filename ?? time() . '-export.' . $format;

        if ($format === 'csv') {
            return new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($queryBuilder, $rowMapper) {
                $handle = fopen('php://output', 'w');
                $headerWritten = false;
                $chunkSize = 1000;

                $queryBuilder()->chunk($chunkSize, function ($records) use ($handle, $rowMapper, &$headerWritten) {
                    foreach ($records as $record) {
                        $row = $rowMapper($record);

                        // Write header on first row
                        if (!$headerWritten) {
                            fputcsv($handle, array_keys($row));
                            $headerWritten = true;
                        }

                        fputcsv($handle, array_values($row));
                    }
                });

                fclose($handle);
            }, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-cache, must-revalidate',
            ]);
        }

        // For Excel, use FastExcel with generator
        if ($format === 'excel' || $format === 'xlsx') {
            $generator = function () use ($queryBuilder, $rowMapper) {
                foreach ($queryBuilder()->cursor() as $record) {
                    yield $rowMapper($record);
                }
            };

            return (new FastExcel($generator()))->download($filename);
        }

        throw new \InvalidArgumentException("Unsupported streaming format: {$format}");
    }
}


if (!function_exists('log_viewer')) {

    function log_viewer($request)
    {
        $search = $request['search'] ?? null;
        $attributes['logable_type'] = $request['logable_type'];
        if (array_key_exists('id', $request)) {
            $attributes['logable_id'] = $request['id'];
        }
        if (array_key_exists('search', $request)) {
            $attributes['search'] = $request['search'];
        }

        if (array_key_exists('user_type', $request)) {
            $attributes['user_type'] = $request['user_type'];
        }

        $logs = new ActivityLogRepository;

        if (array_key_exists('file', $request)) {
            $logs = $logs->get(attributes: $attributes, export: true);
            $data = $logs->map(function ($item) {
                $objects = explode("\\", $item->logable_type);
                return [
                    'edited_date' => date('Y-m-d', strtotime($item->created_at)),
                    'edited_time' => date('h:i A', strtotime($item->created_at)),
                    'email' => $item->users?->email,
                    'edited_object' => end($objects),
                    'before' => json_encode($item?->before),
                    'after' => json_encode($item?->after)
                ];
            });
            return exportData($data, $request['file'], 'adminmodule::log-print');
        }
        $logs = $logs->get(attributes: $attributes);

        return view('adminmodule::activity-log', compact('logs', 'search'));
    }
}


if (!function_exists('get_cache')) {
    function get_cache($key)
    {
        if (!Cache::has($key)) {
            $config = businessConfig($key)?->value;
            if (!$config) {
                return null;
            }
            Cache::put($key, $config);
        }
        return Cache::get($key);
    }
}

if (!function_exists('getSession')) {
    function getSession($key)
    {
        if (!Session::has($key)) {
            $config = businessConfig($key)?->value;
            if (!$config) {
                return false;
            }
            Session::put($key, $config);
        }
        return Session::get($key);
    }
}

if (!function_exists('haversineDistance')) {
    function haversineDistance($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000)
    {
        // convert from degrees to radians
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
                cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        return $angle * $earthRadius;
    }
}

if (!function_exists('getDateRange')) {
    function getDateRange($request)
    {
        if (is_array($request)) {
            return [
                'start' => Carbon::parse($request['start'])->startOfDay(),
                'end' => Carbon::parse($request['end'])->endOfDay(),
            ];
        }

        return match ($request) {
            TODAY => [
                'start' => Carbon::parse(now())->startOfDay(),
                'end' => Carbon::parse(now())->endOfDay()
            ],
            PREVIOUS_DAY => [
                'start' => Carbon::yesterday()->startOfDay(),
                'end' => Carbon::yesterday()->endOfDay(),
            ],
            THIS_WEEK => [
                'start' => Carbon::parse(now())->startOfWeek(),
                'end' => Carbon::parse(now())->endOfWeek(),
            ],
            THIS_MONTH => [
                'start' => Carbon::parse(now())->startOfMonth(),
                'end' => Carbon::parse(now())->endOfMonth(),
            ],
            LAST_7_DAYS => [
                'start' => Carbon::today()->subDays(7)->startOfDay(),
                'end' => Carbon::parse(now())->endOfDay(),
            ],
            LAST_WEEK => [
                'start' => Carbon::now()->subWeek()->startOfWeek(),
                'end' => Carbon::now()->subWeek()->endOfWeek(),
            ],
            LAST_MONTH => [
                'start' => Carbon::now()->subMonth()->startOfMonth(),
                'end' => Carbon::now()->subMonth()->endOfMonth(),
            ],
            THIS_YEAR => [
                'start' => Carbon::now()->startOfYear(),
                'end' => Carbon::now()->endOfYear(),
            ],
            ALL_TIME => [
                'start' => Carbon::parse(BUSINESS_START_DATE),
                'end' => Carbon::now(),
            ]
        };
    }
}
if (!function_exists('getCustomDateRange')) {
    function getCustomDateRange($dateRange)
    {
        list($startDate, $endDate) = explode(' - ', $dateRange);
        $startDate = Carbon::createFromFormat('m/d/Y', trim($startDate));
        $endDate = Carbon::createFromFormat('m/d/Y', trim($endDate));
        return [
            'start' => Carbon::parse($startDate)->startOfDay(),
            'end' => Carbon::parse($endDate)->endOfDay(),
        ];


    }
}

if (!function_exists('configSettings')) {
    function configSettings($key, $settingsType)
    {
        try {
            $config = DB::table('settings')->where('key_name', $key)
                ->where('settings_type', $settingsType)->first();
        } catch (Exception $exception) {
            return null;
        }

        return (isset($config)) ? $config : null;
    }
}

if (!function_exists('languageLoad')) {
    function languageLoad()
    {
        if (\session()->has(LANGUAGE_SETTINGS)) {
            $language = \session(LANGUAGE_SETTINGS);
        } else {
            $language = businessConfig(SYSTEM_LANGUAGE)?->value;
            \session()->put(LANGUAGE_SETTINGS, $language);
        }
        return $language;
    }

}

if (!function_exists('set_currency_symbol')) {
    function set_currency_symbol($amount)
    {
        $points = (int)getSession('currency_decimal_point') ?? 0;
        $position = getSession('currency_symbol_position') ?? 'left';
        $symbol = getSession('currency_symbol') ?? '$';

        if ($position == 'left') {
            return $symbol . ' ' . number_format($amount, $points);
        }
        return number_format($amount, $points) . ' ' . $symbol;
    }
}

if (!function_exists('getCurrencyFormat')) {
    function getCurrencyFormat($amount)
    {
        $points = (int)getSession('currency_decimal_point') ?? 0;
        $position = getSession('currency_symbol_position') ?? 'left';
        if (session::has('currency_symbol')) {
            $symbol = session()->get('currency_symbol');
        } else {
            $symbol = businessConfig('currency_symbol', 'business_information')->value ?? "$";
        }

        if ($position == 'left') {
            return $symbol . ' ' . number_format($amount, $points);
        } else {
            return number_format($amount, $points) . ' ' . $symbol;
        }
    }
}


if (!function_exists('getNotification')) {
    /**
     * Issue #22 FIX: Cache notification settings to avoid repeated DB queries
     * Notifications rarely change, so we cache all of them for 1 hour
     */
    function getNotification($key)
    {
        // Cache all notifications instead of querying per key
        $notifications = \Illuminate\Support\Facades\Cache::remember('notifications:all', 3600, function () {
            return FirebasePushNotification::all()->keyBy('name');
        });

        $notification = $notifications->get($key);

        return [
            'title' => $notification['name'] ?? ' ',
            'description' => $notification['value'] ?? ' ',
            'status' => (bool)($notification['status'] ?? 0),
        ];
    }
}

if (!function_exists('clearNotificationCache')) {
    /**
     * Issue #22 FIX: Clear notification cache when settings change
     */
    function clearNotificationCache()
    {
        \Illuminate\Support\Facades\Cache::forget('notifications:all');
    }
}

if (!function_exists('getMainDomain')) {
    function getMainDomain($url)
    {
        // Remove protocol from the URL
        $url = preg_replace('#^https?://#', '', $url);

        // Split the URL by slashes
        $parts = explode('/', $url);

        // Extract the domain part
        // Return the subdomain and domain
        return $parts[0];
    }
}

if (!function_exists('getRoutes')) {
    function getRoutes(array $originCoordinates, array $destinationCoordinates, array $intermediateCoordinates = [], array $drivingMode = ["DRIVE"])
    {
        // Create cache key based on route parameters (rounded to 4 decimal places to group nearby coordinates)
        $cacheKey = 'route_' . md5(json_encode([
            'origin' => [round($originCoordinates[0], 4), round($originCoordinates[1], 4)],
            'destination' => [round($destinationCoordinates[0], 4), round($destinationCoordinates[1], 4)],
            'intermediate' => $intermediateCoordinates,
            'mode' => $drivingMode
        ]));

        // Cache for 10 minutes (600 seconds) - routes don't change frequently
        return Cache::remember($cacheKey, 600, function () use ($originCoordinates, $destinationCoordinates, $intermediateCoordinates, $drivingMode) {
            $apiKey = businessConfig(GOOGLE_MAP_API)?->value['map_api_key_server'] ?? '';
            $responses = [];

        $encodePolyline = function (array $points): string {
            $result = '';
            $prevLat = 0;
            $prevLng = 0;

            $encodeSigned = function (int $value) use (&$result) {
                $value = ($value < 0) ? ~($value << 1) : ($value << 1);
                while ($value >= 0x20) {
                    $result .= chr((0x20 | ($value & 0x1f)) + 63);
                    $value >>= 5;
                }
                $result .= chr($value + 63);
            };

            foreach ($points as $point) {
                if (!is_array($point) || count($point) < 2) {
                    continue;
                }
                $lat = (int) round(((float) $point[0]) * 1e5);
                $lng = (int) round(((float) $point[1]) * 1e5);

                $encodeSigned($lat - $prevLat);
                $encodeSigned($lng - $prevLng);

                $prevLat = $lat;
                $prevLng = $lng;
            }

            return $result;
        };

        $extractGeoLinkRoutes = function ($result): array {
            if (!is_array($result)) {
                return [];
            }

            // Supported response shapes:
            // 1) { data: { routes: [...] } }
            // 2) { data: [ ... ] } (GeoLink v2 directions)
            if (isset($result['data']['routes']) && is_array($result['data']['routes'])) {
                return $result['data']['routes'];
            }
            if (isset($result['routes']) && is_array($result['routes'])) {
                return $result['routes'];
            }
            if (isset($result['data']) && is_array($result['data'])) {
                return $result['data'];
            }

            return [];
        };
        
        // Build Geolink API parameters
        $params = [
            'origin_latitude' => $originCoordinates[0],
            'origin_longitude' => $originCoordinates[1],
            'destination_latitude' => $destinationCoordinates[0],
            'destination_longitude' => $destinationCoordinates[1],
            'key' => $apiKey,
            'alternatives' => 'true'
        ];
        
        // Add waypoints if provided
        if (!empty($intermediateCoordinates) && !is_null($intermediateCoordinates[0][0])) {
            $waypoints = [];
            foreach ($intermediateCoordinates as $wp) {
                if (isset($wp[0], $wp[1]) && !is_null($wp[0])) {
                    $waypoints[] = $wp[0] . ',' . $wp[1];
                }
            }
            if (!empty($waypoints)) {
                $params['waypoints'] = implode('|', $waypoints);
            }
        }
        
        if (empty($apiKey)) {
            $errorMessage = 'GeoLink API key not configured';
            return [
                0 => ['status' => 'ERROR', 'error_detail' => $errorMessage],
                1 => ['status' => 'ERROR', 'error_detail' => $errorMessage]
            ];
        }

        $response = Http::timeout(5)->get(MAP_API_BASE_URI . '/api/v2/directions', $params);
        if ($response->successful()) {
            $result = $response->json();

            $routes = $extractGeoLinkRoutes($result);
            if (empty($routes)) {
                $errorMessage = $result['message'] ?? 'No routes found between the specified locations';
                return [
                    0 => ['status' => 'ERROR', 'error_detail' => $errorMessage],
                    1 => ['status' => 'ERROR', 'error_detail' => $errorMessage]
                ];
            }

            $route = $routes[0];

            $distanceMeters = 0;
            if (isset($route['distance']['meters'])) {
                $distanceMeters = (float) $route['distance']['meters'];
            } elseif (isset($route['distance'])) {
                $distanceMeters = (float) $route['distance'];
            }

            $durationSeconds = 0;
            if (isset($route['duration']['seconds'])) {
                $durationSeconds = (float) $route['duration']['seconds'];
            } elseif (isset($route['duration'])) {
                $durationSeconds = (float) $route['duration'];
            }

            $encodedPolyline = $route['polyline'] ?? ($route['overview_polyline'] ?? '');
            if (empty($encodedPolyline) && isset($route['waypoints']) && is_array($route['waypoints'])) {
                $encodedPolyline = $encodePolyline($route['waypoints']);
            }

            $durationInTraffic = $durationSeconds;
            $convert_to_bike = 1.2;

            $responses[0] = [
                'distance' => (double) str_replace(',', '', number_format(($distanceMeters ?? 0) / 1000, 2)),
                'distance_text' => number_format(($distanceMeters ?? 0) / 1000, 2) . ' ' . 'km',
                'duration' => number_format((($durationSeconds / 60) / $convert_to_bike), 2) . ' ' . 'min',
                'duration_sec' => (int) ($durationSeconds / $convert_to_bike),
                'duration_in_traffic' => number_format((($durationInTraffic / 60) / $convert_to_bike), 2) . ' ' . 'min',
                'duration_in_traffic_sec' => (int) ($durationInTraffic / $convert_to_bike),
                'status' => "OK",
                'drive_mode' => 'TWO_WHEELER',
                'encoded_polyline' => $encodedPolyline,
            ];

            $responses[1] = [
                'distance' => (double) str_replace(',', '', number_format(($distanceMeters ?? 0) / 1000, 2)),
                'distance_text' => number_format(($distanceMeters ?? 0) / 1000, 2) . ' ' . 'km',
                'duration' => number_format(($durationSeconds / 60), 2) . ' ' . 'min',
                'duration_sec' => (int) $durationSeconds,
                'duration_in_traffic' => number_format(($durationInTraffic / 60), 2) . ' ' . 'min',
                'duration_in_traffic_sec' => (int) $durationInTraffic,
                'status' => "OK",
                'drive_mode' => 'DRIVE',
                'encoded_polyline' => $encodedPolyline,
            ];

            return $responses;
        } else {
            // Handle the error if the request was not successful
            $errorMessage = 'GeoLink API request failed with status: ' . $response->status();
            $responseBody = $response->json();

            // Log the full error for debugging
            \Log::error('GeoLink API Error', [
                'status' => $response->status(),
                'response' => $responseBody,
                'request_params' => $params,
                'url' => MAP_API_BASE_URI . '/api/v2/directions'
            ]);

            if ($responseBody && isset($responseBody['message'])) {
                $errorMessage = $responseBody['message'];
            }

            return [
                0 => ['status' => 'ERROR', 'error_detail' => $errorMessage],
                1 => ['status' => 'ERROR', 'error_detail' => $errorMessage]
            ];
        }
        }); // End of Cache::remember
    }
}

if (!function_exists('onErrorImage')) {
    function onErrorImage($data, $src, $error_src, $path)
    {
        // If data is an array, get the first item
        if (is_array($data)) {
            $data = $data[0] ?? null;
        }

        if (isset($data) && strlen($data) > 1) {
            // Use getMediaUrl to generate proper URL for the new storage system
            // Pass the path parameter to ensure correct folder structure
            return getMediaUrl($data, $path);
        }
        return $error_src;
    }
}

if (!function_exists('getMediaUrl')) {
    /**
     * Convert filesystem path or object key to accessible URL
     *
     * @param string|array|null $path The filesystem path, object key, or array of paths
     * @param string|null $folderOrDefault Folder path for arrays, or default URL for strings
     * @return string|array|null
     */
    function getMediaUrl(string|array|null $path, ?string $folderOrDefault = null): string|array|null
    {
        // Handle arrays
        if (is_array($path)) {
            $folder = $folderOrDefault ?? '';
            return array_map(function($item) use ($folder) {
                if (empty($item)) {
                    return null;
                }
                // If it's already a URL, return as is
                if (str_starts_with($item, 'http://') || str_starts_with($item, 'https://')) {
                    return $item;
                }
                // If it's a full filesystem path starting with /root/new/
                if (str_starts_with($item, '/root/new/')) {
                    $relativePath = str_replace('/root/new/', '', $item);
                    return url('media/' . $relativePath);
                }
                // If folder is provided, prepend it
                if ($folder) {
                    $folder = trim($folder, '/');
                    if ($folder == 'driver/identity') {
                        $item = str_replace('.png', '.webp', $item);
                    }
                    return url('media/' . $folder . '/' . ltrim($item, '/'));
                }
                // Otherwise, just prepend media URL
                return url('media/' . ltrim($item, '/'));
            }, $path);
        }

        // Handle strings
        if (empty($path)) {
            return $folderOrDefault;
        }

        // If it's already a URL, return as is
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        // If it's a full filesystem path starting with /root/new/
        if (str_starts_with($path, '/root/new/')) {
            $relativePath = str_replace('/root/new/', '', $path);
            return url('media/' . $relativePath);
        }

        // If folder is provided, prepend it
        if ($folderOrDefault) {
            $folder = trim($folderOrDefault, '/');
            if ($folder == 'driver/identity') {
                $path = str_replace('.png', '.webp', $path);
            }
            return url('media/' . $folder . '/' . ltrim($path, '/'));
        }

        // If it's a relative path, prepend media URL
        return url('media/' . ltrim($path, '/'));
    }
}

if (!function_exists('checkPusherConnection')) {
    function checkPusherConnection($event)
    {
        try {
            // Pusher configuration
            $pusher = new Pusher(
                config('broadcasting.connections.pusher.key'),
                config('broadcasting.connections.pusher.secret'),
                config('broadcasting.connections.pusher.app_id'),
                config('broadcasting.connections.pusher.options')
            );
//            if (!empty($event)) {
//                $event;
//            }


            return response()->json(['message' => 'Pusher connection established successfully']);
        } catch (PusherException $e) {

        } catch (\Exception $e) {
            // If cURL error 52 occurs
            if (strpos($e->getMessage(), 'cURL error 52') !== false) {
                return true;
            }
            return true;
        }
    }
}
if (!function_exists('spellOutNumber')) {
    function spellOutNumber($number)
    {
        $number = strval($number);
        $digits = [
            "zero", "one", "two", "three", "four",
            "five", "six", "seven", "eight", "nine"
        ];
        $tens = [
            "", "", "twenty", "thirty", "forty",
            "fifty", "sixty", "seventy", "eighty", "ninety"
        ];
        $teens = [
            "ten", "eleven", "twelve", "thirteen", "fourteen",
            "fifteen", "sixteen", "seventeen", "eighteen", "nineteen"
        ];

        $result = '';

        if (strlen($number) > 15) {
            $quadrillions = substr($number, 0, -15);
            $number = substr($number, -15);
            $result .= spellOutNumber($quadrillions) . ' quadrillion ';
        }

        if (strlen($number) > 12) {
            $trillions = substr($number, 0, -12);
            $number = substr($number, -12);
            $result .= spellOutNumber($trillions) . ' trillion ';
        }

        if (strlen($number) > 9) {
            $billions = substr($number, 0, -9);
            $number = substr($number, -9);
            $result .= spellOutNumber($billions) . ' billion ';
        }

        if (strlen($number) > 6) {
            $millions = substr($number, 0, -6);
            $number = substr($number, -6);
            $result .= spellOutNumber($millions) . ' million ';
        }

        if (strlen($number) > 3) {
            $thousands = substr($number, 0, -3);
            $number = substr($number, -3);
            $result .= spellOutNumber($thousands) . ' thousand ';
        }

        if (strlen($number) > 2) {
            $hundreds = substr($number, 0, -2);
            $number = substr($number, -2);
            $result .= $digits[intval($hundreds)] . ' hundred ';
        }

        if ($number > 0) {
            if ($number < 10) {
                $result .= $digits[intval($number)];
            } elseif ($number < 20) {
                $result .= $teens[$number - 10];
            } else {
                $result .= $tens[$number[0]];
                if ($number[1] > 0) {
                    $result .= '-' . $digits[intval($number[1])];
                }
            }
        }

        return trim($result);
    }
}
if (!function_exists('abbreviateNumber')) {
    function abbreviateNumber($number)
    {
        $points = (int)getSession('currency_decimal_point') ?? 0;
        $abbreviations = ['', 'K', 'M', 'B', 'T'];
        $abbreviated_number = $number;
        $abbreviation_index = 0;

        while ($abbreviated_number >= 1000 && $abbreviation_index < count($abbreviations) - 1) {
            $abbreviated_number /= 1000;
            $abbreviation_index++;
        }

        return round($abbreviated_number, $points) . $abbreviations[$abbreviation_index];
    }
}

if (!function_exists('abbreviateNumberWithSymbol')) {
    #TODO
    function abbreviateNumberWithSymbol($number)
    {
        $points = (int)getSession('currency_decimal_point') ?? 0;
        $position = getSession('currency_symbol_position') ?? 'left';
        if (session::has('currency_symbol')) {
            $symbol = session()->get('currency_symbol');
        } else {
            $symbol = businessConfig('currency_symbol', 'business_information')->value ?? "$";
        }
        $abbreviations = ['', 'K', 'M', 'B', 'T'];
        $abbreviated_number = $number;
        $abbreviation_index = 0;

        while ($abbreviated_number >= 1000 && $abbreviation_index < count($abbreviations) - 1) {
            $abbreviated_number /= 1000;
            $abbreviation_index++;
        }

        if ($position == 'left') {
            return $symbol . ' ' . round($abbreviated_number, $points) . $abbreviations[$abbreviation_index];
        } else {
            return round($abbreviated_number, $points) . $abbreviations[$abbreviation_index] . ' ' . $symbol;
        }

    }
}
if (!function_exists('removeInvalidCharcaters')) {
    function removeInvalidCharcaters($str)
    {
        return str_ireplace(['\'', '"', ';', '<', '>'], ' ', $str);
    }
}

if (!function_exists('textVariableDataFormat')) {
    function textVariableDataFormat($value, $tipsAmount = null, $levelName = null, $walletAmount = null, $tripId = null,
                                    $userName = null, $withdrawNote = null, $paidAmount = null, $methodName = null, $referralRewardAmount = null, $otp = null)
    {
        $data = $value;
        if ($value) {
            if ($tipsAmount) {
                $data = str_replace("{tipsAmount}", $tipsAmount, $data);
            }
            if ($paidAmount) {
                $data = str_replace("{paidAmount}", $paidAmount, $data);
            }
            if ($methodName) {
                $data = str_replace("{methodName}", $methodName, $data);
            }

            if ($levelName) {
                $data = str_replace("{levelName}", $levelName, $data);
            }
            if ($levelName == "") {
                $data = str_replace("and reached level {levelName}", ".", $data);
            }

            if ($walletAmount) {
                $data = str_replace("{walletAmount}", $walletAmount, $data);
            }
            if ($referralRewardAmount) {
                $data = str_replace("{referralRewardAmount}", $referralRewardAmount, $data);
            }

            if ($tripId) {
                $data = str_replace("{tripId}", $tripId, $data);
            }
            if ($otp) {
                $data = str_replace("{otp}", $otp, $data);
            }
            if ($userName) {
                $data = str_replace("{userName}", $userName, $data);
            }
            if ($withdrawNote) {
                $data = str_replace("{withdrawNote}", ('Please read carefully this note : ' . $withdrawNote . " If you have any questions, feel free to contact our support team"), $data);
            }
        }

        return $data;
    }
}
if (!function_exists('smsTemplateDataFormat')) {
    function smsTemplateDataFormat($value, $customerName = null, $parcelId = null, $trackingLink = null)
    {
        $data = $value;
        if ($value) {
            if ($customerName) {
                $data = str_replace("{CustomerName}", $customerName, $data);
            }
            if ($parcelId) {
                $data = str_replace("{ParcelId}", $parcelId, $data);
            }
            if ($trackingLink) {
                $data = str_replace("{TrackingLink}", $trackingLink, $data);
            }
        }

        return $data;
    }
}
if (!function_exists('checkMaintenanceMode')) {
    function checkMaintenanceMode(): array
    {
        $maintenanceSystemArray = ['user_app', 'driver_app'];
        $selectedMaintenanceSystem = businessConfig('maintenance_system_setup')?->value ?? [];

        $maintenanceSystem = [];
        foreach ($maintenanceSystemArray as $system) {
            $maintenanceSystem[$system] = in_array($system, $selectedMaintenanceSystem) ? 1 : 0;
        }

        $selectedMaintenanceDuration = businessConfig('maintenance_duration_setup')?->value ?? [];
        $maintenanceStatus = (integer)(businessConfig('maintenance_mode')?->value ?? 0);

        $status = 0;
        if ($maintenanceStatus == 1) {
            if (isset($selectedMaintenanceDuration['maintenance_duration']) && $selectedMaintenanceDuration['maintenance_duration'] == 'until_change') {
                $status = $maintenanceStatus;
            } else {
                if (isset($selectedMaintenanceDuration['start_date']) && isset($selectedMaintenanceDuration['end_date'])) {
                    $start = Carbon::parse($selectedMaintenanceDuration['start_date']);
                    $end = Carbon::parse($selectedMaintenanceDuration['end_date']);
                    $today = Carbon::now();
                    if ($today->between($start, $end)) {
                        $status = 1;
                    }
                }
            }
        }

        return [
            'maintenance_status' => $status,
            'selected_maintenance_system' => count($maintenanceSystem) > 0 ? $maintenanceSystem : null,
            'maintenance_messages' => businessConfig('maintenance_message_setup')?->value ?? null,
            'maintenance_type_and_duration' => count($selectedMaintenanceDuration) > 0 ? $selectedMaintenanceDuration : null,
        ];
    }
}

if (!function_exists('insertBusinessSetting')) {
    function insertBusinessSetting($keyName, $settingType = null, $value = null)
    {
        $data = BusinessSetting::where('key_name', $keyName)->where('settings_type', $settingType)->first();
        if (!$data) {
            BusinessSetting::updateOrCreate(['key_name' => $keyName, 'settings_type' => $settingType], [
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return true;
    }
}

if (!function_exists('hexToRgb')) {
    function hexToRgb($hex)
    {
        // Remove the hash at the start if it's there
        $hex = ltrim($hex, '#');

        // If the hex code is in shorthand (3 characters), convert to full form
        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        // Convert hex to RGB values
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return "$r, $g, $b";
    }
}

if (!function_exists('formatCustomDate')) {
    function formatCustomDate($date)
    {
        $carbonDate = Carbon::parse($date);
        $now = Carbon::now();

        if ($carbonDate->isToday()) {
            return $carbonDate->format('g:i A'); // e.g., 3:53 PM
        } elseif ($carbonDate->isYesterday()) {
            return 'Yesterday';
        } elseif ($carbonDate->diffInDays($now) <= 5) {
            // Returns "X days ago" for dates within the last 5 days
            return $carbonDate->diffInDays($now) . ' days ago';
        } else {
            return $carbonDate->format('d M Y'); // e.g., 17 Nov 2024
        }
    }
}


if (!function_exists('formatCustomDateForTooltip')) {
    function formatCustomDateForTooltip($dateTime)
    {
        $timestamp = strtotime($dateTime);
        $now = time();

        if (date('Y-m-d', $timestamp) === date('Y-m-d', $now)) {
            return date('h:i A', $timestamp); // Format as 01:43 PM
        }

        $oneWeekAgo = strtotime('-1 week', $now);
        if ($timestamp > $oneWeekAgo) {
            return date('l h:i A', $timestamp);
        }

        return date('d M Y', $timestamp);
    }
}

if (!function_exists('getExtensionIcon')) {
    function getExtensionIcon($document)
    {
        $extension = pathinfo($document, PATHINFO_EXTENSION);
        $asset = asset('public/assets/admin-module/img/file-format/svg');
        return match ($extension) {
            'pdf' => $asset . '/pdf.svg',
            'cvc' => $asset . '/cvc.svg',
            'csv' => $asset . '/csv.svg',
            'doc', 'docx' => $asset . '/doc.svg',
            'jpg' => $asset . '/jpg.svg',
            'jpeg' => $asset . '/jpeg.svg',
            'webp' => $asset . '/webp.svg',
            'png' => $asset . '/png.svg',
            'xls' => $asset . '/xls.svg',
            'xlsx' => $asset . '/xlsx.svg',
            default => asset('public/assets/admin-module/img/document-upload.png'),
        };
    }
}

if (!function_exists('convertTimeToSecond')) {
    function convertTimeToSecond($time, $type)
    {
        $time = floatval($time);

        return match (strtolower($type)) {
            'second' => $time,
            'minute' => $time * 60,
            'hour' => $time * 3600,
            default => null,
        };
    }
}

if (!function_exists('app_setting')) {
    /**
     * Get an application setting from the database
     *
     * @param string $key The setting key (e.g., 'tracking.update_interval_seconds')
     * @param mixed $default Default value if setting not found
     * @return mixed
     */
    function app_setting(string $key, mixed $default = null): mixed
    {
        try {
            return app(\App\Services\SettingsService::class)->get($key, $default);
        } catch (\Exception $e) {
            \Log::warning('Failed to get app setting', ['key' => $key, 'error' => $e->getMessage()]);
            return $default;
        }
    }
}

if (!function_exists('set_app_setting')) {
    /**
     * Set an application setting in the database
     *
     * @param string $key The setting key
     * @param mixed $value The value to set
     * @param string|null $adminId The admin who made the change
     * @return bool
     */
    function set_app_setting(string $key, mixed $value, ?string $adminId = null): bool
    {
        try {
            return app(\App\Services\SettingsService::class)->set($key, $value, $adminId);
        } catch (\Exception $e) {
            \Log::error('Failed to set app setting', ['key' => $key, 'error' => $e->getMessage()]);
            return false;
        }
    }
}

if (!function_exists('app_settings_group')) {
    /**
     * Get all settings for a specific group
     *
     * @param string $group The group name (tracking, dispatch, travel, map)
     * @return array
     */
    function app_settings_group(string $group): array
    {
        try {
            return app(\App\Services\SettingsService::class)->getGroup($group);
        } catch (\Exception $e) {
            \Log::warning('Failed to get app settings group', ['group' => $group, 'error' => $e->getMessage()]);
            return [];
        }
    }
}

if (!function_exists('customer_add_fund_to_wallet')) {
    /**
     * Add funds to customer wallet after successful payment (hook wrapper)
     *
     * @param object $data Payment data
     * @return bool
     */
    function customer_add_fund_to_wallet($data): bool
    {
        return \App\Library\CustomerAddFundToWallet::handle($data);
    }
}

