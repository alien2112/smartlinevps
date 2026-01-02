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
     * GET /api/driver/auth/pages
     */
    public function index(Request $request): JsonResponse
    {
        $userType = $request->user()?->user_type ?? 'driver';

        $pages = DB::table('content_pages')
            ->where('is_active', true)
            ->whereIn('user_type', [$userType, 'both'])
            ->select('id', 'slug', 'title', 'page_type', 'published_at')
            ->orderBy('page_type')
            ->get();

        return response()->json(responseFormatter(DEFAULT_200, [
            'pages' => $pages->map(fn($page) => [
                'id' => $page->id,
                'slug' => $page->slug,
                'title' => $page->title,
                'type' => $page->page_type,
                'published_at' => $page->published_at,
            ]),
        ]));
    }

    /**
     * Get specific content page by slug
     * GET /api/driver/auth/pages/{slug}
     */
    public function show(string $slug): JsonResponse
    {
        $page = DB::table('content_pages')
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$page) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        // Increment view count (optional analytics)
        // DB::table('content_pages')->where('id', $page->id)->increment('view_count');

        return response()->json(responseFormatter(DEFAULT_200, [
            'id' => $page->id,
            'slug' => $page->slug,
            'title' => $page->title,
            'content' => $page->content,
            'type' => $page->page_type,
            'version' => $page->version,
            'published_at' => $page->published_at,
            'updated_at' => $page->updated_at,
        ]));
    }
}
