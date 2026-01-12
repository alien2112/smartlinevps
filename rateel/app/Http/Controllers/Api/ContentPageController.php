<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContentPageController extends Controller
{
    /**
     * Get all content pages
     * GET /api/driver/auth/pages?language=en|ar
     *
     * Returns pages grouped by slug with available languages
     * Default language: Arabic (ar)
     */
    public function index(Request $request): JsonResponse
    {
        $userType = $request->user()?->user_type ?? 'driver';
        $language = $request->query('language', 'ar'); // Default to Arabic

        $allPages = DB::table('content_pages')
            ->where('is_active', true)
            ->whereIn('user_type', [$userType, 'both'])
            ->select('id', 'slug', 'title', 'page_type', 'published_at')
            ->orderBy('page_type')
            ->get();

        // Group pages by slug (remove _ar suffix) and track available languages
        $pages = [];
        foreach ($allPages as $page) {
            $baseSlug = str_replace('_ar', '', $page->slug);
            $lang = str_ends_with($page->slug, '_ar') ? 'ar' : 'en';

            if (!isset($pages[$baseSlug])) {
                $pages[$baseSlug] = [
                    'id' => $page->id,
                    'slug' => $baseSlug,
                    'title' => $page->title,
                    'type' => $page->page_type,
                    'published_at' => $page->published_at,
                    'available_languages' => [],
                ];
            }

            $pages[$baseSlug]['available_languages'][$lang] = true;
        }

        return response()->json(responseFormatter(DEFAULT_200, [
            'pages' => array_values($pages),
            'default_language' => 'ar',
            'available_languages' => ['ar', 'en'],
        ]));
    }

    /**
     * Get specific content page by slug
     * GET /api/driver/auth/pages/{slug}?language=en|ar
     *
     * Supports both Arabic and English versions
     * Default language: Arabic (ar)
     * Query parameter ?language=en for English
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $requestedLanguage = $request->query('language', 'ar'); // Default to Arabic
        $language = in_array($requestedLanguage, ['en', 'ar']) ? $requestedLanguage : 'ar';

        // Construct slug with language suffix
        $pageSlug = $language === 'ar' ? $slug . '_ar' : $slug;

        // Try to get the page in requested language
        $page = DB::table('content_pages')
            ->where('slug', $pageSlug)
            ->where('is_active', true)
            ->first();

        // If Arabic not found, try English (for backward compatibility)
        if (!$page && $language === 'ar') {
            $page = DB::table('content_pages')
                ->where('slug', $slug)
                ->where('is_active', true)
                ->first();
            $language = 'en';
        }

        // If still not found, try the opposite language
        if (!$page && $language === 'en') {
            $page = DB::table('content_pages')
                ->where('slug', $slug . '_ar')
                ->where('is_active', true)
                ->first();
            $language = 'ar';
        }

        if (!$page) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        // Get both versions for client to support language switching
        $arabicPage = DB::table('content_pages')
            ->where('slug', $slug . '_ar')
            ->where('is_active', true)
            ->first();

        $englishPage = DB::table('content_pages')
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        return response()->json(responseFormatter(DEFAULT_200, [
            'id' => $page->id,
            'slug' => str_replace('_ar', '', $page->slug),
            'title' => $page->title,
            'content' => $page->content,
            'type' => $page->page_type,
            'version' => $page->version,
            'language' => $language,
            'published_at' => $page->published_at,
            'updated_at' => $page->updated_at,
            // Include both versions for language switching
            'available_languages' => [
                'ar' => $arabicPage ? true : false,
                'en' => $englishPage ? true : false,
            ],
        ]));
    }
}
