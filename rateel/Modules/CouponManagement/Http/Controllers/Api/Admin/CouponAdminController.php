<?php

declare(strict_types=1);

namespace Modules\CouponManagement\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\CouponManagement\Entities\Coupon;
use Modules\CouponManagement\Entities\CouponTargetUser;
use Modules\CouponManagement\Http\Requests\AssignCouponUsersRequest;
use Modules\CouponManagement\Http\Requests\BroadcastCouponRequest;
use Modules\CouponManagement\Http\Requests\CreateCouponRequest;
use Modules\CouponManagement\Jobs\SendCouponBulkJob;
use Modules\CouponManagement\Jobs\SendCouponToUserJob;
use Modules\CouponManagement\Service\CouponService;

class CouponAdminController extends Controller
{
    public function __construct(
        private readonly CouponService $couponService
    ) {}

    /**
     * List all coupons
     *
     * GET /admin/coupons
     */
    public function index(Request $request): JsonResponse
    {
        $query = Coupon::query()->withCount('redemptions');

        // Filters
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('eligibility_type')) {
            $query->where('eligibility_type', $request->input('eligibility_type'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = min((int) $request->input('per_page', 20), 100);
        $coupons = $query->paginate($perPage);

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: $coupons
        ));
    }

    /**
     * Create a new coupon
     *
     * POST /admin/coupons
     *
     * Request:
     * {
     *   "code": "SAVE20",
     *   "name": "20% Off First Ride",
     *   "description": "Get 20% off your first ride",
     *   "type": "PERCENT",
     *   "value": 20,
     *   "max_discount": 10.00,
     *   "min_fare": 5.00,
     *   "global_limit": 1000,
     *   "per_user_limit": 1,
     *   "starts_at": "2025-01-01T00:00:00Z",
     *   "ends_at": "2025-12-31T23:59:59Z",
     *   "allowed_city_ids": ["city-1", "city-2"],
     *   "allowed_service_types": ["ride", "parcel"],
     *   "eligibility_type": "TARGETED",
     *   "target_user_ids": ["user-1", "user-2"],
     *   "is_active": true
     * }
     */
    public function store(CreateCouponRequest $request): JsonResponse
    {
        $admin = auth('api')->user();

        Log::info('CouponAdminController: Creating coupon', [
            'admin_id' => $admin->id,
            'code' => $request->input('code'),
        ]);

        try {
            $coupon = DB::transaction(function () use ($request, $admin) {
                $coupon = Coupon::create([
                    'code' => $request->input('code'),
                    'name' => $request->input('name'),
                    'description' => $request->input('description'),
                    'type' => $request->input('type'),
                    'value' => $request->input('value'),
                    'max_discount' => $request->input('max_discount'),
                    'min_fare' => $request->input('min_fare', 0),
                    'global_limit' => $request->input('global_limit'),
                    'per_user_limit' => $request->input('per_user_limit', 1),
                    'starts_at' => $request->input('starts_at'),
                    'ends_at' => $request->input('ends_at'),
                    'allowed_city_ids' => $request->input('allowed_city_ids'),
                    'allowed_service_types' => $request->input('allowed_service_types'),
                    'eligibility_type' => $request->input('eligibility_type', 'ALL'),
                    'segment_key' => $request->input('segment_key'),
                    'is_active' => $request->input('is_active', true),
                    'created_by' => $admin->id,
                ]);

                // Add target users if TARGETED eligibility
                if ($request->input('eligibility_type') === 'TARGETED' && $request->has('target_user_ids')) {
                    $targetUsers = collect($request->input('target_user_ids'))->map(fn($userId) => [
                        'id' => \Illuminate\Support\Str::uuid()->toString(),
                        'coupon_id' => $coupon->id,
                        'user_id' => $userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    CouponTargetUser::insert($targetUsers->toArray());
                }

                return $coupon;
            });

            Log::info('CouponAdminController: Coupon created', [
                'coupon_id' => $coupon->id,
                'code' => $coupon->code,
            ]);

            return response()->json(responseFormatter(
                constant: DEFAULT_STORE_200,
                content: $coupon
            ), 201);

        } catch (\Exception $e) {
            Log::error('CouponAdminController: Failed to create coupon', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                content: ['error' => 'Failed to create coupon']
            ), 400);
        }
    }

    /**
     * Get coupon details
     *
     * GET /admin/coupons/{coupon}
     */
    public function show(string $couponId): JsonResponse
    {
        $coupon = Coupon::with(['targetUsers.user:id,first_name,last_name,email'])
            ->withCount(['redemptions', 'appliedRedemptions'])
            ->findOrFail($couponId);

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: $coupon
        ));
    }

    /**
     * Update a coupon
     *
     * PUT /admin/coupons/{coupon}
     */
    public function update(Request $request, string $couponId): JsonResponse
    {
        $coupon = Coupon::findOrFail($couponId);

        // Only allow updating certain fields
        $allowedUpdates = [
            'name', 'description', 'max_discount', 'min_fare',
            'global_limit', 'ends_at', 'is_active',
            'allowed_city_ids', 'allowed_service_types',
        ];

        $data = $request->only($allowedUpdates);
        $coupon->update($data);

        Log::info('CouponAdminController: Coupon updated', [
            'coupon_id' => $coupon->id,
            'updates' => array_keys($data),
        ]);

        return response()->json(responseFormatter(
            constant: DEFAULT_UPDATE_200,
            content: $coupon->fresh()
        ));
    }

    /**
     * Assign users to a targeted coupon
     *
     * POST /admin/coupons/{coupon}/assign-users
     *
     * Request:
     * {
     *   "user_ids": ["user-1", "user-2", "user-3"],
     *   "notify": true,
     *   "message_template": "Use code {code} to get {value} off!"
     * }
     */
    public function assignUsers(AssignCouponUsersRequest $request, string $couponId): JsonResponse
    {
        $coupon = Coupon::findOrFail($couponId);

        if ($coupon->eligibility_type !== Coupon::ELIGIBILITY_TARGETED) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                content: ['error' => 'Coupon must be TARGETED eligibility type']
            ), 400);
        }

        $userIds = $request->input('user_ids');
        $notify = $request->boolean('notify', false);
        $messageTemplate = $request->input('message_template');

        Log::info('CouponAdminController: Assigning users to coupon', [
            'coupon_id' => $coupon->id,
            'user_count' => count($userIds),
            'notify' => $notify,
        ]);

        $inserted = 0;
        $skipped = 0;

        foreach ($userIds as $userId) {
            try {
                CouponTargetUser::firstOrCreate(
                    ['coupon_id' => $coupon->id, 'user_id' => $userId],
                    ['notified' => false]
                );
                $inserted++;

                // Send notification if requested
                if ($notify) {
                    SendCouponToUserJob::dispatch($userId, $coupon->id, $messageTemplate);
                }
            } catch (\Exception $e) {
                $skipped++;
            }
        }

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: [
                'assigned' => $inserted,
                'skipped' => $skipped,
                'notifications_queued' => $notify ? $inserted : 0,
            ]
        ));
    }

    /**
     * Broadcast coupon notification
     *
     * POST /admin/coupons/{coupon}/broadcast
     *
     * Request:
     * {
     *   "target": "all" | "targeted" | "segment" | "user_ids",
     *   "segment_key": "INACTIVE_30_DAYS",
     *   "user_ids": ["user-1", "user-2"],
     *   "message_template": "Use code {code} to get {value} off!"
     * }
     */
    public function broadcast(BroadcastCouponRequest $request, string $couponId): JsonResponse
    {
        $coupon = Coupon::findOrFail($couponId);

        if (!$coupon->is_active) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                content: ['error' => 'Cannot broadcast inactive coupon']
            ), 400);
        }

        $target = $request->input('target');
        $messageTemplate = $request->input('message_template');

        Log::info('CouponAdminController: Broadcasting coupon', [
            'coupon_id' => $coupon->id,
            'target' => $target,
        ]);

        // Dispatch bulk job
        match ($target) {
            'all' => SendCouponBulkJob::dispatch($coupon->id, null, null, $messageTemplate),
            'targeted' => SendCouponBulkJob::dispatch($coupon->id, null, null, $messageTemplate),
            'segment' => SendCouponBulkJob::dispatch($coupon->id, null, $request->input('segment_key'), $messageTemplate),
            'user_ids' => SendCouponBulkJob::dispatch($coupon->id, $request->input('user_ids'), null, $messageTemplate),
        };

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: [
                'message' => 'Broadcast job queued successfully',
                'target' => $target,
            ]
        ));
    }

    /**
     * Get coupon statistics
     *
     * GET /admin/coupons/{coupon}/stats
     */
    public function stats(string $couponId): JsonResponse
    {
        $stats = $this->couponService->getCouponStats($couponId);

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: $stats
        ));
    }

    /**
     * Deactivate a coupon
     *
     * POST /admin/coupons/{coupon}/deactivate
     */
    public function deactivate(string $couponId): JsonResponse
    {
        $coupon = Coupon::findOrFail($couponId);
        $coupon->update(['is_active' => false]);

        Log::info('CouponAdminController: Coupon deactivated', [
            'coupon_id' => $coupon->id,
        ]);

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: ['message' => 'Coupon deactivated']
        ));
    }

    /**
     * Delete a coupon (soft delete)
     *
     * DELETE /admin/coupons/{coupon}
     */
    public function destroy(string $couponId): JsonResponse
    {
        $coupon = Coupon::findOrFail($couponId);

        // Check if coupon has any applied redemptions
        if ($coupon->appliedRedemptions()->count() > 0) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                content: ['error' => 'Cannot delete coupon with applied redemptions']
            ), 400);
        }

        $coupon->delete(); // Soft delete

        Log::info('CouponAdminController: Coupon deleted', [
            'coupon_id' => $coupon->id,
        ]);

        return response()->json(responseFormatter(
            constant: DEFAULT_DELETE_200
        ));
    }
}
