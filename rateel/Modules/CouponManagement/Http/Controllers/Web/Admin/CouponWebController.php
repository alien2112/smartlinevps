<?php

declare(strict_types=1);

namespace Modules\CouponManagement\Http\Controllers\Web\Admin;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\CouponManagement\Entities\Coupon;
use Modules\CouponManagement\Entities\CouponRedemption;
use Modules\CouponManagement\Entities\CouponTargetUser;
use Modules\CouponManagement\Jobs\SendCouponBulkJob;
use Modules\UserManagement\Entities\User;
use Modules\ZoneManagement\Entities\Zone;

class CouponWebController extends Controller
{
    use AuthorizesRequests;
    /**
     * List all coupons
     */
    public function index(Request $request)
    {
        $this->authorize('promotion_view');

        $query = Coupon::query()
            ->withCount(['redemptions', 'appliedRedemptions', 'targetUsers']);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // Filters
        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($eligibility = $request->input('eligibility_type')) {
            $query->where('eligibility_type', $eligibility);
        }

        $coupons = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        // Stats
        $stats = [
            'total' => Coupon::count(),
            'active' => Coupon::where('is_active', true)->count(),
            'total_redemptions' => CouponRedemption::where('status', CouponRedemption::STATUS_APPLIED)->count(),
            'total_discount_given' => CouponRedemption::where('status', CouponRedemption::STATUS_APPLIED)->sum('discount_amount'),
        ];

        return view('couponmanagement::admin.index', compact('coupons', 'stats'));
    }

    /**
     * Show create coupon form
     */
    public function create()
    {
        $this->authorize('promotion_add');

        $zones = Zone::where('is_active', true)->get(['id', 'name']);
        $serviceTypes = ['ride', 'parcel'];
        $types = [
            Coupon::TYPE_PERCENT => 'Percentage (%)',
            Coupon::TYPE_FIXED => 'Fixed Amount',
            Coupon::TYPE_FREE_RIDE_CAP => 'Free Ride (100%)',
        ];
        $eligibilityTypes = [
            Coupon::ELIGIBILITY_ALL => 'All Users',
            Coupon::ELIGIBILITY_TARGETED => 'Targeted Users Only',
            Coupon::ELIGIBILITY_SEGMENT => 'User Segment',
        ];
        $segments = [
            Coupon::SEGMENT_NEW_USER => 'New Users (first 7 days)',
            Coupon::SEGMENT_INACTIVE_30_DAYS => 'Inactive 30+ days',
            Coupon::SEGMENT_HIGH_VALUE => 'High Value Customers',
        ];

        return view('couponmanagement::admin.create', compact('zones', 'serviceTypes', 'types', 'eligibilityTypes', 'segments'));
    }

    /**
     * Store new coupon
     */
    public function store(Request $request)
    {
        $this->authorize('promotion_add');

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:coupons,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type' => 'required|in:PERCENT,FIXED,FREE_RIDE_CAP',
            'value' => 'required|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'min_fare' => 'nullable|numeric|min:0',
            'global_limit' => 'nullable|integer|min:1',
            'per_user_limit' => 'required|integer|min:1',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'allowed_city_ids' => 'nullable|array',
            'allowed_service_types' => 'nullable|array',
            'eligibility_type' => 'required|in:ALL,TARGETED,SEGMENT',
            'segment_key' => 'nullable|string',
            'target_user_ids' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            $coupon = Coupon::create([
                'code' => strtoupper(trim($validated['code'])),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'type' => $validated['type'],
                'value' => $validated['value'],
                'max_discount' => $validated['max_discount'] ?? null,
                'min_fare' => $validated['min_fare'] ?? 0,
                'global_limit' => $validated['global_limit'] ?? null,
                'per_user_limit' => $validated['per_user_limit'],
                'starts_at' => $validated['starts_at'],
                'ends_at' => $validated['ends_at'],
                'allowed_city_ids' => $validated['allowed_city_ids'] ?? null,
                'allowed_service_types' => $validated['allowed_service_types'] ?? null,
                'eligibility_type' => $validated['eligibility_type'],
                'segment_key' => $validated['segment_key'] ?? null,
                'is_active' => $request->boolean('is_active', true),
                'created_by' => auth()->id(),
            ]);

            // Add target users if TARGETED
            if ($validated['eligibility_type'] === 'TARGETED' && !empty($validated['target_user_ids'])) {
                $targetUsers = collect($validated['target_user_ids'])->map(fn($userId) => [
                    'id' => Str::uuid()->toString(),
                    'coupon_id' => $coupon->id,
                    'user_id' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                CouponTargetUser::insert($targetUsers->toArray());
            }

            DB::commit();

            flash()->success(translate('Coupon created successfully'));
            return redirect()->route('admin.coupon-management.index');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create coupon', ['error' => $e->getMessage()]);
            flash()->error(translate('Failed to create coupon: ') . $e->getMessage());
            return back()->withInput();
        }
    }

    /**
     * Show coupon details
     */
    public function show(string $id)
    {
        $this->authorize('promotion_view');

        $coupon = Coupon::with(['creator', 'targetUsers.user'])
            ->withCount(['redemptions', 'appliedRedemptions'])
            ->findOrFail($id);

        // Get redemption stats
        $redemptionStats = CouponRedemption::where('coupon_id', $id)
            ->selectRaw('status, COUNT(*) as count, SUM(discount_amount) as total_discount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        // Recent redemptions
        $recentRedemptions = CouponRedemption::where('coupon_id', $id)
            ->with(['user:id,first_name,last_name,phone'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return view('couponmanagement::admin.show', compact('coupon', 'redemptionStats', 'recentRedemptions'));
    }

    /**
     * Show edit form
     */
    public function edit(string $id)
    {
        $this->authorize('promotion_edit');

        $coupon = Coupon::with('targetUsers')->findOrFail($id);
        $zones = Zone::where('is_active', true)->get(['id', 'name']);
        $serviceTypes = ['ride', 'parcel'];
        $types = [
            Coupon::TYPE_PERCENT => 'Percentage (%)',
            Coupon::TYPE_FIXED => 'Fixed Amount',
            Coupon::TYPE_FREE_RIDE_CAP => 'Free Ride (100%)',
        ];
        $eligibilityTypes = [
            Coupon::ELIGIBILITY_ALL => 'All Users',
            Coupon::ELIGIBILITY_TARGETED => 'Targeted Users Only',
            Coupon::ELIGIBILITY_SEGMENT => 'User Segment',
        ];
        $segments = [
            Coupon::SEGMENT_NEW_USER => 'New Users (first 7 days)',
            Coupon::SEGMENT_INACTIVE_30_DAYS => 'Inactive 30+ days',
            Coupon::SEGMENT_HIGH_VALUE => 'High Value Customers',
        ];

        return view('couponmanagement::admin.edit', compact('coupon', 'zones', 'serviceTypes', 'types', 'eligibilityTypes', 'segments'));
    }

    /**
     * Update coupon
     */
    public function update(Request $request, string $id)
    {
        $this->authorize('promotion_edit');

        $coupon = Coupon::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'max_discount' => 'nullable|numeric|min:0',
            'min_fare' => 'nullable|numeric|min:0',
            'global_limit' => 'nullable|integer|min:1',
            'ends_at' => 'required|date|after:starts_at',
            'allowed_city_ids' => 'nullable|array',
            'allowed_service_types' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $coupon->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? $coupon->description,
            'max_discount' => $validated['max_discount'] ?? $coupon->max_discount,
            'min_fare' => $validated['min_fare'] ?? $coupon->min_fare,
            'global_limit' => $validated['global_limit'] ?? $coupon->global_limit,
            'ends_at' => $validated['ends_at'],
            'allowed_city_ids' => $validated['allowed_city_ids'] ?? $coupon->allowed_city_ids,
            'allowed_service_types' => $validated['allowed_service_types'] ?? $coupon->allowed_service_types,
            'is_active' => $request->boolean('is_active'),
        ]);

        flash()->success(translate('Coupon updated successfully'));
        return redirect()->route('admin.coupon-management.show', $id);
    }

    /**
     * Delete coupon
     */
    public function destroy(string $id)
    {
        $this->authorize('promotion_delete');

        $coupon = Coupon::findOrFail($id);
        
        // Soft delete
        $coupon->delete();

        flash()->success(translate('Coupon deleted successfully'));
        return redirect()->route('admin.coupon-management.index');
    }

    /**
     * Toggle coupon status
     */
    public function toggleStatus(string $id)
    {
        $this->authorize('promotion_edit');

        $coupon = Coupon::findOrFail($id);
        $coupon->update(['is_active' => !$coupon->is_active]);

        $status = $coupon->is_active ? 'activated' : 'deactivated';
        flash()->success(translate("Coupon {$status} successfully"));

        return back();
    }

    /**
     * Broadcast coupon to users
     */
    public function broadcast(Request $request, string $id)
    {
        $this->authorize('promotion_edit');

        $coupon = Coupon::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:100',
            'body' => 'required|string|max:500',
        ]);

        $notification = [
            'title' => $request->input('title'),
            'body' => $request->input('body'),
            'coupon_code' => $coupon->code,
        ];

        // Dispatch job to send notifications
        dispatch(new SendCouponBulkJob($coupon, $notification));

        flash()->success(translate('Coupon notification is being sent to eligible users'));
        return back();
    }

    /**
     * Get coupon statistics
     */
    public function stats(string $id)
    {
        $this->authorize('promotion_view');

        $coupon = Coupon::findOrFail($id);

        $stats = [
            'total_redemptions' => CouponRedemption::where('coupon_id', $id)->count(),
            'applied_redemptions' => CouponRedemption::where('coupon_id', $id)
                ->where('status', CouponRedemption::STATUS_APPLIED)->count(),
            'total_discount' => CouponRedemption::where('coupon_id', $id)
                ->where('status', CouponRedemption::STATUS_APPLIED)
                ->sum('discount_amount'),
            'unique_users' => CouponRedemption::where('coupon_id', $id)
                ->distinct('user_id')->count('user_id'),
            'remaining_uses' => $coupon->global_limit 
                ? max(0, $coupon->global_limit - $coupon->global_used_count) 
                : 'Unlimited',
            'daily_stats' => CouponRedemption::where('coupon_id', $id)
                ->where('status', CouponRedemption::STATUS_APPLIED)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(discount_amount) as discount')
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->limit(30)
                ->get(),
        ];

        return view('couponmanagement::admin.stats', compact('coupon', 'stats'));
    }

    /**
     * Manage target users
     */
    public function targetUsers(Request $request, string $id)
    {
        $this->authorize('promotion_view');

        $coupon = Coupon::findOrFail($id);
        
        $targetUsers = CouponTargetUser::where('coupon_id', $id)
            ->with('user:id,first_name,last_name,phone,email')
            ->paginate(50);

        // Search for users to add
        $searchResults = [];
        if ($search = $request->input('search')) {
            $searchResults = User::where('user_type', 'customer')
                ->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                ->whereNotIn('id', $coupon->targetUsers()->pluck('user_id'))
                ->limit(20)
                ->get(['id', 'first_name', 'last_name', 'phone', 'email']);
        }

        return view('couponmanagement::admin.target-users', compact('coupon', 'targetUsers', 'searchResults'));
    }

    /**
     * Add target users
     */
    public function addTargetUsers(Request $request, string $id)
    {
        $this->authorize('promotion_edit');

        $coupon = Coupon::findOrFail($id);

        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $existingUserIds = $coupon->targetUsers()->pluck('user_id')->toArray();
        $newUserIds = array_diff($validated['user_ids'], $existingUserIds);

        if (!empty($newUserIds)) {
            $targetUsers = collect($newUserIds)->map(fn($userId) => [
                'id' => Str::uuid()->toString(),
                'coupon_id' => $coupon->id,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            CouponTargetUser::insert($targetUsers->toArray());
        }

        flash()->success(translate('Users added successfully'));
        return back();
    }

    /**
     * Remove target user
     */
    public function removeTargetUser(string $couponId, string $userId)
    {
        $this->authorize('promotion_edit');

        CouponTargetUser::where('coupon_id', $couponId)
            ->where('user_id', $userId)
            ->delete();

        flash()->success(translate('User removed successfully'));
        return back();
    }
}
