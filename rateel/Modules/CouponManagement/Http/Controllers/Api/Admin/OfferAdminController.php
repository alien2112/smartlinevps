<?php

declare(strict_types=1);

namespace Modules\CouponManagement\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\CouponManagement\Entities\Offer;
use Modules\CouponManagement\Entities\OfferUsage;
use Modules\CouponManagement\Service\OfferService;

class OfferAdminController extends Controller
{
    public function __construct(
        private readonly OfferService $offerService
    ) {}

    /**
     * List all offers
     *
     * GET /admin/offers
     */
    public function index(Request $request): JsonResponse
    {
        $query = Offer::query()
            ->withCount(['usages', 'appliedUsages']);

        // Filters
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('discount_type')) {
            $query->where('discount_type', $request->input('discount_type'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('title', 'like', "%{$search}%");
        }

        // Status filter
        if ($status = $request->input('status')) {
            $now = now();
            match ($status) {
                'active' => $query->where('is_active', true)
                    ->where('start_date', '<=', $now)
                    ->where('end_date', '>=', $now),
                'expired' => $query->where('end_date', '<', $now),
                'scheduled' => $query->where('start_date', '>', $now),
                'inactive' => $query->where('is_active', false),
                default => null,
            };
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = min((int) $request->input('per_page', 20), 100);
        $offers = $query->paginate($perPage);

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: $offers
        ));
    }

    /**
     * Create a new offer
     *
     * POST /admin/offers
     */
    public function store(Request $request): JsonResponse
    {
        $admin = auth('api')->user();

        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'short_description' => 'nullable|string|max:500',
            'terms_conditions' => 'nullable|string',
            'image' => 'nullable|string',
            'banner_image' => 'nullable|string',
            'discount_type' => 'required|in:percentage,fixed,free_ride',
            'discount_amount' => 'required|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'min_trip_amount' => 'nullable|numeric|min:0',
            'limit_per_user' => 'required|integer|min:1',
            'global_limit' => 'nullable|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'zone_type' => 'required|in:all,selected',
            'zone_ids' => 'nullable|array',
            'customer_level_type' => 'required|in:all,selected',
            'customer_level_ids' => 'nullable|array',
            'customer_type' => 'required|in:all,selected',
            'customer_ids' => 'nullable|array',
            'service_type' => 'required|in:all,ride,parcel,selected',
            'vehicle_category_ids' => 'nullable|array',
            'priority' => 'nullable|integer|min:0|max:255',
            'is_active' => 'boolean',
            'show_in_app' => 'boolean',
        ]);

        Log::info('OfferAdminController: Creating offer', [
            'admin_id' => $admin->id,
            'title' => $validated['title'],
        ]);

        try {
            $offer = Offer::create([
                ...$validated,
                'min_trip_amount' => $validated['min_trip_amount'] ?? 0,
                'priority' => $validated['priority'] ?? 0,
                'is_active' => $request->boolean('is_active', true),
                'show_in_app' => $request->boolean('show_in_app', true),
                'created_by' => $admin->id,
            ]);

            Log::info('OfferAdminController: Offer created', [
                'offer_id' => $offer->id,
            ]);

            return response()->json(responseFormatter(
                constant: DEFAULT_STORE_200,
                content: $offer
            ), 201);

        } catch (\Exception $e) {
            Log::error('OfferAdminController: Failed to create offer', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                content: ['error' => 'Failed to create offer']
            ), 400);
        }
    }

    /**
     * Get offer details
     *
     * GET /admin/offers/{id}
     */
    public function show(string $id): JsonResponse
    {
        $offer = Offer::with('creator')
            ->withCount(['usages', 'appliedUsages'])
            ->findOrFail($id);

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: [
                'offer' => $offer,
                'zones_list' => $offer->zones_list,
                'customer_levels_list' => $offer->customer_levels_list,
                'vehicle_categories_list' => $offer->vehicle_categories_list,
            ]
        ));
    }

    /**
     * Update an offer
     *
     * PUT /admin/offers/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $offer = Offer::findOrFail($id);

        $validated = $request->validate([
            'title' => 'string|max:150',
            'short_description' => 'nullable|string|max:500',
            'terms_conditions' => 'nullable|string',
            'image' => 'nullable|string',
            'banner_image' => 'nullable|string',
            'max_discount' => 'nullable|numeric|min:0',
            'min_trip_amount' => 'nullable|numeric|min:0',
            'global_limit' => 'nullable|integer|min:1',
            'end_date' => 'date|after:start_date',
            'zone_type' => 'in:all,selected',
            'zone_ids' => 'nullable|array',
            'customer_level_type' => 'in:all,selected',
            'customer_level_ids' => 'nullable|array',
            'customer_type' => 'in:all,selected',
            'customer_ids' => 'nullable|array',
            'service_type' => 'in:all,ride,parcel,selected',
            'vehicle_category_ids' => 'nullable|array',
            'priority' => 'nullable|integer|min:0|max:255',
            'is_active' => 'boolean',
            'show_in_app' => 'boolean',
        ]);

        $offer->update($validated);

        Log::info('OfferAdminController: Offer updated', [
            'offer_id' => $offer->id,
            'updates' => array_keys($validated),
        ]);

        return response()->json(responseFormatter(
            constant: DEFAULT_UPDATE_200,
            content: $offer->fresh()
        ));
    }

    /**
     * Delete an offer
     *
     * DELETE /admin/offers/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $offer = Offer::findOrFail($id);

        // Soft delete
        $offer->delete();

        Log::info('OfferAdminController: Offer deleted', [
            'offer_id' => $id,
        ]);

        return response()->json(responseFormatter(
            constant: DEFAULT_DELETE_200
        ));
    }

    /**
     * Toggle offer status
     *
     * POST /admin/offers/{id}/toggle
     */
    public function toggle(string $id): JsonResponse
    {
        $offer = Offer::findOrFail($id);
        $offer->update(['is_active' => !$offer->is_active]);

        Log::info('OfferAdminController: Offer status toggled', [
            'offer_id' => $id,
            'is_active' => $offer->is_active,
        ]);

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: ['is_active' => $offer->is_active]
        ));
    }

    /**
     * Get offer statistics
     *
     * GET /admin/offers/{id}/stats
     */
    public function stats(string $id): JsonResponse
    {
        $stats = $this->offerService->getOfferStats($id);

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: $stats
        ));
    }

    /**
     * Get recent usages
     *
     * GET /admin/offers/{id}/usages
     */
    public function usages(Request $request, string $id): JsonResponse
    {
        $usages = OfferUsage::where('offer_id', $id)
            ->with(['user:id,first_name,last_name,phone', 'trip:id,ref_id'])
            ->orderBy('created_at', 'desc')
            ->paginate(min((int) $request->input('per_page', 20), 100));

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: $usages
        ));
    }
}
