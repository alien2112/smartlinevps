<?php

namespace Modules\UserManagement\Http\Controllers\Web\New\Admin\Driver;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\UserManagement\Entities\DriverDetail;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Service\Interface\DriverDetailServiceInterface;

/**
 * Admin Travel Approval Controller
 * 
 * Enterprise-grade travel privilege management:
 * - View pending travel requests
 * - Approve/reject driver travel applications
 * - Revoke approved travel privileges
 * 
 * Travel orders only go to drivers with travel_status = 'approved'
 */
class TravelApprovalController extends Controller
{
    protected DriverDetailServiceInterface $driverDetailService;

    public function __construct(DriverDetailServiceInterface $driverDetailService)
    {
        $this->driverDetailService = $driverDetailService;
    }

    /**
     * Display travel approval requests list
     */
    public function index(Request $request)
    {
        $status = $request->get('status', 'requested');
        
        $query = DriverDetail::with([
            'user',
            'user.vehicle',
            'user.vehicle.category',
            'user.vehicle.brand',
            'user.vehicle.model',
        ])
        ->whereHas('user', function ($q) {
            $q->where('user_type', 'driver')
              ->where('is_active', true);
        });

        // Filter by status
        if ($status !== 'all') {
            $query->where('travel_status', $status);
        } else {
            $query->whereIn('travel_status', ['requested', 'approved', 'rejected']);
        }

        // Order by request date
        $query->orderByDesc('travel_requested_at');

        $requests = $query->paginate(perPage: $request->get('limit', 10));

        $counts = [
            'pending' => DriverDetail::where('travel_status', 'requested')->count(),
            'approved' => DriverDetail::where('travel_status', 'approved')->count(),
            'rejected' => DriverDetail::where('travel_status', 'rejected')->count(),
        ];

        return view('usermanagement::admin.driver.travel-approval.index', [
            'requests' => $requests,
            'status' => $status,
            'counts' => $counts,
        ]);
    }

    /**
     * Approve a driver's travel request
     */
    public function approve(Request $request, string $driverId): JsonResponse
    {
        DB::beginTransaction();
        try {
            $driverDetail = DriverDetail::where('user_id', $driverId)->first();

            if (!$driverDetail) {
                return response()->json([
                    'status' => 'error',
                    'message' => translate('Driver not found'),
                ], 404);
            }

            if ($driverDetail->travel_status !== DriverDetail::TRAVEL_STATUS_REQUESTED) {
                return response()->json([
                    'status' => 'error',
                    'message' => translate('No pending travel request for this driver'),
                ], 400);
            }

            // Verify driver is VIP category
            $driver = User::with('vehicle.category')->find($driverId);
            $categoryLevel = $driver?->vehicle?->category?->category_level ?? 0;
            
            if ($categoryLevel < \Modules\VehicleManagement\Entities\VehicleCategory::LEVEL_VIP) {
                return response()->json([
                    'status' => 'error',
                    'message' => translate('Driver must be VIP category to receive travel approval'),
                ], 400);
            }

            // Approve the request
            $driverDetail->approveTravelPrivilege(auth()->id());

            DB::commit();

            // Send notification to driver
            if ($driver && $driver->fcm_token) {
                sendDeviceNotification(
                    fcm_token: $driver->fcm_token,
                    title: translate('Travel Approved'),
                    description: translate('Congratulations! Your travel request has been approved. You can now receive travel bookings.'),
                    status: 1,
                    action: 'travel_approved',
                    user_id: $driver->id
                );
            }

            Log::info('Travel request approved', [
                'driver_id' => $driverId,
                'approved_by' => auth()->id(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => translate('Travel request approved successfully'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to approve travel request', [
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => translate('Failed to approve travel request'),
            ], 500);
        }
    }

    /**
     * Reject a driver's travel request
     */
    public function reject(Request $request, string $driverId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 400);
        }

        DB::beginTransaction();
        try {
            $driverDetail = DriverDetail::where('user_id', $driverId)->first();

            if (!$driverDetail) {
                return response()->json([
                    'status' => 'error',
                    'message' => translate('Driver not found'),
                ], 404);
            }

            if ($driverDetail->travel_status !== DriverDetail::TRAVEL_STATUS_REQUESTED) {
                return response()->json([
                    'status' => 'error',
                    'message' => translate('No pending travel request for this driver'),
                ], 400);
            }

            // Reject the request
            $reason = $request->get('reason', translate('Your travel application was not approved at this time.'));
            $driverDetail->rejectTravelPrivilege(auth()->id(), $reason);

            DB::commit();

            // Send notification to driver
            $driver = User::find($driverId);
            if ($driver && $driver->fcm_token) {
                sendDeviceNotification(
                    fcm_token: $driver->fcm_token,
                    title: translate('Travel Request Update'),
                    description: $reason,
                    status: 1,
                    action: 'travel_rejected',
                    user_id: $driver->id
                );
            }

            Log::info('Travel request rejected', [
                'driver_id' => $driverId,
                'rejected_by' => auth()->id(),
                'reason' => $reason,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => translate('Travel request rejected'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to reject travel request', [
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => translate('Failed to reject travel request'),
            ], 500);
        }
    }

    /**
     * Revoke approved travel privilege
     */
    public function revoke(Request $request, string $driverId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 400);
        }

        DB::beginTransaction();
        try {
            $driverDetail = DriverDetail::where('user_id', $driverId)->first();

            if (!$driverDetail) {
                return response()->json([
                    'status' => 'error',
                    'message' => translate('Driver not found'),
                ], 404);
            }

            if ($driverDetail->travel_status !== DriverDetail::TRAVEL_STATUS_APPROVED) {
                return response()->json([
                    'status' => 'error',
                    'message' => translate('Driver does not have approved travel privilege'),
                ], 400);
            }

            // Revoke the privilege
            $reason = $request->get('reason');
            $driverDetail->revokeTravelPrivilege(auth()->id(), $reason);

            DB::commit();

            // Send notification to driver
            $driver = User::find($driverId);
            if ($driver && $driver->fcm_token) {
                sendDeviceNotification(
                    fcm_token: $driver->fcm_token,
                    title: translate('Travel Privilege Revoked'),
                    description: $reason,
                    status: 1,
                    action: 'travel_revoked',
                    user_id: $driver->id
                );
            }

            Log::info('Travel privilege revoked', [
                'driver_id' => $driverId,
                'revoked_by' => auth()->id(),
                'reason' => $reason,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => translate('Travel privilege revoked'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to revoke travel privilege', [
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => translate('Failed to revoke travel privilege'),
            ], 500);
        }
    }

    /**
     * Bulk approve travel requests
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'driver_ids' => 'required|array|min:1',
            'driver_ids.*' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $approved = 0;
        $failed = 0;

        foreach ($request->driver_ids as $driverId) {
            try {
                $driverDetail = DriverDetail::where('user_id', $driverId)
                    ->where('travel_status', DriverDetail::TRAVEL_STATUS_REQUESTED)
                    ->first();

                if ($driverDetail) {
                    $driverDetail->approveTravelPrivilege(auth()->id());
                    $approved++;

                    // Send notification
                    $driver = User::find($driverId);
                    if ($driver && $driver->fcm_token) {
                        sendDeviceNotification(
                            fcm_token: $driver->fcm_token,
                            title: translate('Travel Approved'),
                            description: translate('Congratulations! Your travel request has been approved.'),
                            status: 1,
                            action: 'travel_approved',
                            user_id: $driver->id
                        );
                    }
                }
            } catch (\Exception $e) {
                $failed++;
                Log::error('Bulk approval failed for driver', [
                    'driver_id' => $driverId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => translate('Bulk approval completed'),
            'approved' => $approved,
            'failed' => $failed,
        ]);
    }

    /**
     * Get travel statistics for dashboard
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'pending_requests' => DriverDetail::where('travel_status', 'requested')->count(),
            'approved_drivers' => DriverDetail::where('travel_status', 'approved')->count(),
            'rejected_requests' => DriverDetail::where('travel_status', 'rejected')
                ->where('travel_rejected_at', '>=', now()->subDays(30))
                ->count(),
            'today_requests' => DriverDetail::where('travel_status', 'requested')
                ->whereDate('travel_requested_at', today())
                ->count(),
            'today_approved' => DriverDetail::where('travel_status', 'approved')
                ->whereDate('travel_approved_at', today())
                ->count(),
        ];

        return response()->json($stats);
    }
}
