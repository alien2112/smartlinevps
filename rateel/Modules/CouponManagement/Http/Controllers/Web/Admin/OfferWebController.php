<?php

declare(strict_types=1);

namespace Modules\CouponManagement\Http\Controllers\Web\Admin;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Brian2694\Toastr\Facades\Toastr;
use Modules\CouponManagement\Entities\Offer;
use Modules\CouponManagement\Entities\OfferUsage;
use Modules\CouponManagement\Service\OfferService;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Entities\UserLevel;
use Modules\VehicleManagement\Entities\VehicleCategory;
use Modules\ZoneManagement\Entities\Zone;

class OfferWebController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly OfferService $offerService
    ) {}

    /**
     * List all offers
     */
    public function index(Request $request)
    {
        $this->authorize('promotion_view');

        // Auto-deactivate expired offers
        $this->offerService->deactivateExpiredOffers();

        $query = Offer::query()
            ->withCount(['usages', 'appliedUsages']);

        // Search
        if ($search = $request->input('search')) {
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

        // Discount type filter
        if ($type = $request->input('discount_type')) {
            $query->where('discount_type', $type);
        }

        $offers = $query->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Stats
        $stats = [
            'total' => Offer::count(),
            'active' => Offer::where('is_active', true)
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->count(),
            'total_usages' => OfferUsage::where('status', 'applied')->count(),
            'total_discount' => OfferUsage::where('status', 'applied')->sum('discount_amount'),
        ];

        return view('couponmanagement::admin.offers.index', compact('offers', 'stats'));
    }

    /**
     * Show create form
     */
    public function create()
    {
        $this->authorize('promotion_add');

        $zones = Zone::where('is_active', true)->get(['id', 'name']);
        $customerLevels = UserLevel::where('user_type', 'customer')->get(['id', 'name']);
        $vehicleCategories = VehicleCategory::all(['id', 'name']);

        return view('couponmanagement::admin.offers.create', compact('zones', 'customerLevels', 'vehicleCategories'));
    }

    /**
     * Store new offer
     */
    public function store(Request $request)
    {
        $this->authorize('promotion_add');

        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'short_description' => 'nullable|string|max:500',
            'terms_conditions' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'banner_image' => 'nullable|image|max:2048',
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
        ]);

        try {
            // Handle image uploads
            $imagePath = null;
            $bannerPath = null;
            
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('offers', 'public');
            }
            if ($request->hasFile('banner_image')) {
                $bannerPath = $request->file('banner_image')->store('offers', 'public');
            }

            $offer = Offer::create([
                'title' => $validated['title'],
                'short_description' => $validated['short_description'] ?? null,
                'terms_conditions' => $validated['terms_conditions'] ?? null,
                'image' => $imagePath,
                'banner_image' => $bannerPath,
                'discount_type' => $validated['discount_type'],
                'discount_amount' => $validated['discount_amount'],
                'max_discount' => $validated['max_discount'] ?? null,
                'min_trip_amount' => $validated['min_trip_amount'] ?? 0,
                'limit_per_user' => $validated['limit_per_user'],
                'global_limit' => $validated['global_limit'] ?? null,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'zone_type' => $validated['zone_type'],
                'zone_ids' => $validated['zone_ids'] ?? null,
                'customer_level_type' => $validated['customer_level_type'],
                'customer_level_ids' => $validated['customer_level_ids'] ?? null,
                'customer_type' => $validated['customer_type'],
                'customer_ids' => $validated['customer_ids'] ?? null,
                'service_type' => $validated['service_type'],
                'vehicle_category_ids' => $validated['vehicle_category_ids'] ?? null,
                'priority' => $validated['priority'] ?? 0,
                'is_active' => $request->boolean('is_active', true),
                'show_in_app' => $request->boolean('show_in_app', true),
                'created_by' => auth()->id(),
            ]);

            Toastr::success(translate('Offer created successfully'));
            return redirect()->route('admin.offer-management.index');

        } catch (\Exception $e) {
            Log::error('Failed to create offer', ['error' => $e->getMessage()]);
            Toastr::error(translate('Failed to create offer: ') . $e->getMessage());
            return back()->withInput();
        }
    }

    /**
     * Show offer details
     */
    public function show(string $id)
    {
        $this->authorize('promotion_view');

        $offer = Offer::with('creator')
            ->withCount(['usages', 'appliedUsages'])
            ->findOrFail($id);

        // Recent usages
        $recentUsages = OfferUsage::where('offer_id', $id)
            ->with(['user:id,first_name,last_name,phone', 'trip:id,ref_id'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // Usage stats
        $usageStats = OfferUsage::where('offer_id', $id)
            ->selectRaw('status, COUNT(*) as count, SUM(discount_amount) as total_discount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return view('couponmanagement::admin.offers.show', compact('offer', 'recentUsages', 'usageStats'));
    }

    /**
     * Show edit form
     */
    public function edit(string $id)
    {
        $this->authorize('promotion_edit');

        $offer = Offer::findOrFail($id);
        $zones = Zone::where('is_active', true)->get(['id', 'name']);
        $customerLevels = UserLevel::where('user_type', 'customer')->get(['id', 'name']);
        $vehicleCategories = VehicleCategory::all(['id', 'name']);

        return view('couponmanagement::admin.offers.edit', compact('offer', 'zones', 'customerLevels', 'vehicleCategories'));
    }

    /**
     * Update offer
     */
    public function update(Request $request, string $id)
    {
        $this->authorize('promotion_edit');

        $offer = Offer::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'short_description' => 'nullable|string|max:500',
            'terms_conditions' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'banner_image' => 'nullable|image|max:2048',
            'max_discount' => 'nullable|numeric|min:0',
            'min_trip_amount' => 'nullable|numeric|min:0',
            'global_limit' => 'nullable|integer|min:' . $offer->total_used,
            'end_date' => 'required|date',
            'zone_type' => 'required|in:all,selected',
            'zone_ids' => 'nullable|array',
            'customer_level_type' => 'required|in:all,selected',
            'customer_level_ids' => 'nullable|array',
            'customer_type' => 'required|in:all,selected',
            'customer_ids' => 'nullable|array',
            'service_type' => 'required|in:all,ride,parcel,selected',
            'vehicle_category_ids' => 'nullable|array',
            'priority' => 'nullable|integer|min:0|max:255',
        ]);

        // Handle image uploads
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('offers', 'public');
        }
        if ($request->hasFile('banner_image')) {
            $validated['banner_image'] = $request->file('banner_image')->store('offers', 'public');
        }

        $offer->update([
            ...$validated,
            'is_active' => $request->boolean('is_active'),
            'show_in_app' => $request->boolean('show_in_app'),
        ]);

        Toastr::success(translate('Offer updated successfully'));
        return redirect()->route('admin.offer-management.show', $id);
    }

    /**
     * Delete offer
     */
    public function destroy(string $id)
    {
        $this->authorize('promotion_delete');

        $offer = Offer::findOrFail($id);
        $offer->delete();

        Toastr::success(translate('Offer deleted successfully'));
        return redirect()->route('admin.offer-management.index');
    }

    /**
     * Toggle status
     */
    public function toggleStatus(string $id)
    {
        $this->authorize('promotion_edit');

        $offer = Offer::findOrFail($id);
        $offer->update(['is_active' => !$offer->is_active]);

        $status = $offer->is_active ? 'activated' : 'deactivated';
        Toastr::success(translate("Offer {$status} successfully"));

        return back();
    }

    /**
     * Statistics page
     */
    public function stats(string $id)
    {
        $this->authorize('promotion_view');

        $offer = Offer::findOrFail($id);
        $stats = $this->offerService->getOfferStats($id);

        return view('couponmanagement::admin.offers.stats', compact('offer', 'stats'));
    }
}
