<?php

namespace Modules\BusinessManagement\Service;

use App\Repository\EloquentRepositoryInterface;
use App\Service\BaseService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Modules\BusinessManagement\Jobs\UpdateLanguageFileJob;
use Modules\BusinessManagement\Repository\BusinessSettingRepositoryInterface;
use Modules\BusinessManagement\Service\Interface\LanguageSettingServiceInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Updated: 2026-01-14 - Added async file operations to prevent blocking requests
 */
class LanguageSettingService extends BaseService implements Interface\LanguageSettingServiceInterface
{
    protected $businessSettingRepository;

    public function __construct(BusinessSettingRepositoryInterface $businessSettingRepository)
    {
        parent::__construct($businessSettingRepository);
        $this->businessSettingRepository = $businessSettingRepository;
    }

    public function storeLanguage(array $data)
    {
        $language = $this->businessSettingRepository->findOneBy(criteria: ['key_name' => SYSTEM_LANGUAGE, 'settings_type' => LANGUAGE_SETTINGS]);

        $lang_array = [];
        $codes = [];
        foreach ($language['value'] as $key => $singleData) {
            if (!array_key_exists('default', $singleData)) {
                $default = array('default' => $singleData['code'] == 'en');
                $singleData = array_merge($singleData, $default);
            }
            $lang_array[] = $singleData;
            $codes[] = $singleData['code'];
        }
        $codes[] = $data['code'];

        if (!file_exists(base_path('resources/lang/' . $data['code']))) {
            mkdir(base_path('resources/lang/' . $data['code']), 0777, true);
        }

        $lang_file = fopen(base_path('resources/lang/' . $data['code'] . '/' . 'lang.php'), "w") or die("Unable to open file!");
        $read = file_get_contents(base_path('resources/lang/en/lang.php'));
        fwrite($lang_file, $read);

        $lang_array[] = [
            'id' => count($language['value']) + 1,
            'code' => $data['code'],
            'direction' => $data['direction'],
            'status' => 0,
            'default' => false,
        ];
        DB::beginTransaction();
        $this->businessSettingRepository->update(id: $language['id'], data: [
            'key_name' => SYSTEM_LANGUAGE,
            'value' => $lang_array,
            'settings_type' => LANGUAGE_SETTINGS
        ]);

//        $languageInfo = $this->businessSettingRepository->findOneBy(criteria: ['key_name' => 'language', 'settings_type' => BUSINESS_INFORMATION]);
//        $this->businessSettingRepository->update(id: $languageInfo->id, data: [
//            'key_name' => 'language',
//            'value' => $codes,
//            'settings_type' => BUSINESS_INFORMATION
//
//        ]);
        DB::commit();
    }

    public function updateLanguage(array $data)
    {
        $language = $this->businessSettingRepository->findOneBy(criteria: ['key_name' => SYSTEM_LANGUAGE, 'settings_type' => LANGUAGE_SETTINGS]);
        $lang_array = [];
        foreach ($language['value'] as $singleLanguage) {
            $lang = [
                'id' => $singleLanguage['id'],
                'direction' => $singleLanguage['code'] == $data['code'] ? $data['direction'] : $singleLanguage['direction'],
                'code' => $singleLanguage['code'],
                'status' => $singleLanguage['status'],
                'default' => (array_key_exists('default', $singleLanguage) ? $singleLanguage['default'] : (($singleLanguage['code'] == 'en') ? true : false)),
            ];
            $lang_array[] = $lang;
        }

        $attributes = [
            'key_name' => SYSTEM_LANGUAGE,
            'settings_type' => LANGUAGE_SETTINGS,
            'value' => $lang_array
        ];
        $this->businessSettingRepository->update(id: $language['id'], data: $attributes);
    }

    public function deleteLanguage($lang)
    {
        DB::beginTransaction();
        $language = $this->businessSettingRepository->findOneBy(criteria: ['key_name' => SYSTEM_LANGUAGE, 'settings_type' => LANGUAGE_SETTINGS]);
        $del_default = false;
        $lang_array = [];
        foreach ($language['value'] as $data) {
            if ($data['code'] != $lang) {
                $lang_data = [
                    'id' => $data['id'],
                    'direction' => $data['direction'] ?? 'ltr',
                    'code' => $data['code'],
                    'status' => ($del_default && $data['code'] == 'en') ? 1 : $data['status'],
                    'default' => ($del_default && $data['code'] == 'en') ? true : (array_key_exists('default', $data) ? $data['default'] : (($data['code'] == 'en') ? true : false)),
                ];
                $lang_array[] = $lang_data;
            }
        }
        $attributes = [
            'key_name' => SYSTEM_LANGUAGE,
            'settings_type' => LANGUAGE_SETTINGS,
            'value' => $lang_array
        ];
        $this->businessSettingRepository->update(id: $language['id'], data: $attributes);

        $dir = base_path('resources/lang/' . $lang);
        if (File::isDirectory($dir)) {
            $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }
            rmdir($dir);
        }

//        $languages = array();
//        $languageInfo = $this->businessSettingRepository->findOneBy(criteria: ['key_name' => 'language', 'settings_type' => BUSINESS_INFORMATION]);
//        foreach ($languageInfo['value'] as $key => $data) {
//            if ($data != $lang) {
//                $languages[] = $data;
//            }
//        }
//        if (in_array('en', $languages)) {
//            unset($languages[array_search('en', $languages)]);
//        }
//        array_unshift($languages, 'en');
//
//        $attributes = [
//            'key_name' => 'language',
//            'settings_type' => BUSINESS_INFORMATION,
//            'value' => $languages
//        ];
//        $this->businessSettingRepository->update(id: $languageInfo->id, data: $attributes);
        DB::commit();
    }

    public function changeLanguageStatus(array $data)
    {
        $language = $this->businessSettingRepository->findOneBy(criteria: ['key_name' => SYSTEM_LANGUAGE, 'settings_type' => LANGUAGE_SETTINGS]);
        $lang_array = [];
        foreach ($language['value'] as $key => $singleLanguage) {
            if ($singleLanguage['code'] == $data['code']) {
                $lang = [
                    'id' => $singleLanguage['id'],
                    'direction' => $singleLanguage['direction'] ?? 'ltr',
                    'code' => $singleLanguage['code'],
                    'status' => $singleLanguage['status'] == 1 ? 0 : 1,
                    'default' => (array_key_exists('default', $singleLanguage) ? $singleLanguage['default'] : (($singleLanguage['code'] == 'en') ? true : false)),
                ];
                $lang_array[] = $lang;
            } else {
                $lang = [
                    'id' => $singleLanguage['id'],
                    'direction' => $singleLanguage['direction'] ?? 'ltr',
                    'code' => $singleLanguage['code'],
                    'status' => $singleLanguage['status'],
                    'default' => (array_key_exists('default', $singleLanguage) ? $singleLanguage['default'] : (($singleLanguage['code'] == 'en') ? true : false)),
                ];
                $lang_array[] = $lang;
            }
        }
        $this->businessSettingRepository->update(id: $language['id'], data: [
            'key_name' => SYSTEM_LANGUAGE,
            'value' => $lang_array,
            'settings_type' => LANGUAGE_SETTINGS
        ]);
    }

    public function changeLanguageDefaultStatus(array $data)
    {
        $language = $this->businessSettingRepository->findOneBy(criteria: ['key_name' => SYSTEM_LANGUAGE, 'settings_type' => LANGUAGE_SETTINGS]);
        $lang_array = [];
        foreach ($language['value'] as $key => $singleLanguage) {
            if ($singleLanguage['code'] == $data['code']) {
                $lang = [
                    'id' => $singleLanguage['id'],
                    'direction' => $singleLanguage['direction'] ?? 'ltr',
                    'code' => $singleLanguage['code'],
                    'status' => 1,
                    'default' => true,
                ];
                $lang_array[] = $lang;
                session()->put('locale', $singleLanguage['code']);
                session()->put('direction', $singleLanguage['direction'] ?? 'ltr');
            } else {
                $lang = [
                    'id' => $singleLanguage['id'],
                    'direction' => $singleLanguage['direction'] ?? 'ltr',
                    'code' => $singleLanguage['code'],
                    'status' => $singleLanguage['status'],
                    'default' => false,
                ];
                $lang_array[] = $lang;
            }
        }

        $this->businessSettingRepository->update(id: $language['id'], data: [
            'key_name' => SYSTEM_LANGUAGE,
            'value' => $lang_array,
            'settings_type' => LANGUAGE_SETTINGS
        ]);
    }

    public function translate($lang, $data)
    {
        $fullData = include(base_path('resources/lang/' . $lang . '/lang.php'));
        $fullData = array_filter($fullData, fn($value) => !is_null($value) && $value !== '');

        if (array_key_exists('search', $data)) {
            $searchTerm = $data['search'];
            $fullData = array_filter($fullData, function ($value, $key) use ($searchTerm) {
                return (stripos($value, $searchTerm) !== false) || (stripos(ucfirst(str_replace('_', ' ', removeInvalidCharcaters($key))), $searchTerm) !== false);
            }, ARRAY_FILTER_USE_BOTH);
        }
        ksort($fullData);

        return $this->convertArrayToCollection($lang, $fullData, paginationLimit());
    }

    /**
     * Store translation - now uses async job to prevent blocking
     * Updated: 2026-01-14 - Changed to async queue job for file I/O
     */
    public function storeTranslate(array $data, $lang)
    {
        // Updated 2026-01-14: Use async job for file I/O to prevent blocking
        UpdateLanguageFileJob::dispatch($lang, $data['key'], $data['value']);

        /* ============================================================
         * OLD CODE - Commented 2026-01-14
         * Synchronous file I/O blocked request thread
         * ============================================================
         *
         * $translateData = include(base_path('resources/lang/' . $lang . '/lang.php'));
         * $translateData[$data['key']] = $data['value'];
         * $str = "<?php return " . var_export($translateData, true) . ";";
         * file_put_contents(base_path('resources/lang/' . $lang . '/lang.php'), $str);
         */
    }

    /**
     * Auto translate a single key
     * Updated: 2026-01-14 - File I/O now uses async job
     */
    public function autoTranslate(array $data, $lang)
    {
        $lang_code = getLanguageCode($lang);
        $translated = autoTranslator($data['key'], 'en', $lang_code);

        // Updated 2026-01-14: Use async job for file I/O to prevent blocking
        UpdateLanguageFileJob::dispatch($lang, $data['key'], $translated, 'auto_translate');

        return $translated;

        /* ============================================================
         * OLD CODE - Commented 2026-01-14
         * Synchronous file I/O blocked request thread
         * ============================================================
         *
         * $translateData = include(base_path('resources/lang/' . $lang . '/lang.php'));
         * $translateData[$data['key']] = $translated;
         * $str = "<?php return " . var_export($translateData, true) . ";";
         * file_put_contents(base_path('resources/lang/' . $lang . '/lang.php'), $str);
         */
    }

    public function autoTranslateAll(array $data, $lang)
    {
        $translatingCount = $data['translating_count'] <= 0 ? 1 : $data['translating_count'];
        $langCode = getLanguageCode($lang);
        $data_filtered = [];
        $data_filtered_2 = [];
        $newMessagesPath = base_path('resources/lang/' . $lang . '/new-messages.php');
        $count = 0;
        $start_time = now();
        $items_processed = 20;
        if (!file_exists($newMessagesPath)) {
            $str = "<?php return " . var_export($data_filtered, true) . ";";
            file_put_contents(base_path('resources/lang/' . $lang . '/new-messages.php'), $str);
        }

        $translatedData = include(base_path('resources/lang/' . $lang . '/new-messages.php'));
        $fullData = include(base_path('resources/lang/' . $lang . '/lang.php'));
        $translatedDataCount = count($translatedData);

        if ($translatedDataCount > 0) {
            foreach ($translatedData as $key_1 => $data_1) {
                if ($count > $items_processed) {
                    break;
                }
                $translated = str_replace('_', ' ', removeInvalidCharcaters($key_1));
                $translated = autoTranslator($translated, 'en', $langCode);
                $data_filtered_2[$key_1] = $translated;
                unset($translatedData[$key_1]);
                $count++;
            }


            $str = "<?php return " . var_export($translatedData, true) . ";";
            file_put_contents(base_path('resources/lang/' . $lang . '/new-messages.php'), $str);
            $merged_data = array_replace($fullData, $data_filtered_2);

            $str = "<?php\n\nreturn " . var_export($merged_data, true) . ";\n";
            file_put_contents(base_path('resources/lang/' . $lang . '/lang.php'), $str);
            $renmaining_translated_data_count = count($translatedData);
            $percentage = $renmaining_translated_data_count > 0 && $translatingCount > 0 ? 100 - (($renmaining_translated_data_count / $translatingCount) * 100) : 0;


            $percentage = $percentage > 0 ? $percentage : 1;

            $end_time = now();
            $time_taken = $start_time->diffInSeconds($end_time);
            $rate_per_second = $time_taken > 0 ? $items_processed / $time_taken : 0.01;
            $total_time_needed = $renmaining_translated_data_count > 0 ? $renmaining_translated_data_count / $rate_per_second : 1;

            $hours = floor($total_time_needed / 3600);
            $minutes = floor(2 + (($total_time_needed % 3600) / 60));
            $seconds = $total_time_needed % 60;


            return [
                'status' => 1,
                'translatedDataCount' => $translatedDataCount,
                'percentage' => $percentage,
                'minutes' => $minutes,
                'seconds' => $seconds,
                'hours' => $hours,
                'time_taken' => $time_taken,
                'renmaining_translated_data_count' => $renmaining_translated_data_count,
                'translated' => $translated,
            ];

        } else {

            foreach ($fullData as $key => $singleData) {
                if (preg_match('/^[\x20-\x7E\x{2019}]+$/u', $singleData)) {
                    $data_filtered[$key] = $singleData;
                    $str = "<?php return " . var_export($data_filtered, true) . ";";
                    file_put_contents(base_path('resources/lang/' . $lang . '/new-messages.php'), $str);
                }
            }
            return [
                'status' => 2,
                'data_filtered' =>$data_filtered
            ];


        }
    }

    private function convertArrayToCollection($lang, $items, $perPage = null, $page = null, $options = []): LengthAwarePaginator
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);
        $options = array_merge($options, [
            "path" => route('admin.business.languages.translate', [$lang]),
            "pageName" => "page"
        ]);
        return new LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options);
    }
}
