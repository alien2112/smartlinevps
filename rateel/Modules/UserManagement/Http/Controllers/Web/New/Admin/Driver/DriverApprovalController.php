<?php

namespace Modules\UserManagement\Http\Controllers\Web\New\Admin\Driver;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\UserManagement\Entities\DriverDocument;
use Modules\UserManagement\Entities\User;

/**
 * DriverApprovalController
 * 
 * Admin controller for managing driver onboarding applications.
 * Handles approval, rejection, and document verification.
 */
class DriverApprovalController extends BaseController
{
    /**
     * Display pending driver applications
     */
    public function index(Request $request)
    {
        $status = $request->get('status', 'pending_approval');
        
        $query = User::where('user_type', 'driver')
            ->with(['driverDetails', 'vehicle']);

        // Filter by onboarding step
        if ($status === 'pending_approval') {
            $query->where('onboarding_step', 'pending_approval');
        } elseif ($status === 'approved') {
            $query->where('onboarding_step', 'approved');
        } elseif ($status === 'in_progress') {
            $query->whereNotIn('onboarding_step', ['pending_approval', 'approved']);
        }

        $drivers = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        // Get counts for tabs
        $counts = [
            'pending' => User::where('user_type', 'driver')->where('onboarding_step', 'pending_approval')->count(),
            'approved' => User::where('user_type', 'driver')->where('onboarding_step', 'approved')->count(),
            'in_progress' => User::where('user_type', 'driver')
                ->whereNotIn('onboarding_step', ['pending_approval', 'approved'])
                ->count(),
        ];

        return view('usermanagement::admin.driver.approvals.index', compact('drivers', 'status', 'counts'));
    }

    /**
     * Show driver application details
     */
    public function show(string $id)
    {
        $driver = User::where('user_type', 'driver')
            ->with(['driverDetails', 'vehicle'])
            ->findOrFail($id);

        // Get all uploaded documents
        $documents = DriverDocument::where('driver_id', $driver->id)
            ->get()
            ->keyBy('type');

        // Get required documents for this vehicle type
        $requiredDocs = DriverDocument::getRequiredDocuments($driver->selected_vehicle_type);

        return view('usermanagement::admin.driver.approvals.show', compact('driver', 'documents', 'requiredDocs'));
    }

    /**
     * Approve driver application
     */
    public function approve(Request $request, string $id)
    {
        $driver = User::where('user_type', 'driver')
            ->where('onboarding_step', 'pending_approval')
            ->findOrFail($id);

        try {
            DB::beginTransaction();

            $driver->onboarding_step = 'approved';
            $driver->is_active = true;
            $driver->save();

            // Mark all documents as verified
            DriverDocument::where('driver_id', $driver->id)
                ->update([
                    'verified' => true,
                    'verified_at' => now(),
                    'verified_by' => auth()->id(),
                ]);

            DB::commit();

            Log::info('Driver approved', [
                'driver_id' => $driver->id,
                'approved_by' => auth()->id(),
            ]);

            // TODO: Send push notification to driver about approval

            return redirect()
                ->route('admin.driver.approvals.index')
                ->with('success', 'Driver approved successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Driver approval failed', [
                'driver_id' => $driver->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to approve driver: ' . $e->getMessage());
        }
    }

    /**
     * Reject driver application
     */
    public function reject(Request $request, string $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $driver = User::where('user_type', 'driver')
            ->where('onboarding_step', 'pending_approval')
            ->findOrFail($id);

        try {
            DB::beginTransaction();

            // Reset to documents step so driver can re-upload
            $driver->onboarding_step = 'documents';
            $driver->documents_completed_at = null;
            $driver->save();

            // Mark documents as rejected
            DriverDocument::where('driver_id', $driver->id)
                ->update([
                    'verified' => false,
                    'verified_by' => auth()->id(),
                    'rejection_reason' => $request->reason,
                ]);

            DB::commit();

            Log::info('Driver rejected', [
                'driver_id' => $driver->id,
                'rejected_by' => auth()->id(),
                'reason' => $request->reason,
            ]);

            // TODO: Send push notification to driver about rejection

            return redirect()
                ->route('admin.driver.approvals.index')
                ->with('success', 'Driver application rejected');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Driver rejection failed', [
                'driver_id' => $driver->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to reject driver: ' . $e->getMessage());
        }
    }

    /**
     * Verify individual document
     */
    public function verifyDocument(Request $request, string $driverId, string $documentId)
    {
        $document = DriverDocument::where('driver_id', $driverId)
            ->where('id', $documentId)
            ->firstOrFail();

        $document->markAsVerified(auth()->id());

        return back()->with('success', 'Document verified successfully');
    }

    /**
     * Reject individual document
     */
    public function rejectDocument(Request $request, string $driverId, string $documentId)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $document = DriverDocument::where('driver_id', $driverId)
            ->where('id', $documentId)
            ->firstOrFail();

        $document->markAsRejected(auth()->id(), $request->reason);

        // Also reset driver's document completion status
        $driver = User::find($driverId);
        if ($driver) {
            $driver->onboarding_step = 'documents';
            $driver->documents_completed_at = null;
            $driver->save();
        }

        return back()->with('success', 'Document rejected. Driver notified to re-upload.');
    }

    /**
     * Deactivate an approved driver
     */
    public function deactivate(Request $request, string $id)
    {
        $request->validate([
            'reason' => 'sometimes|string|max:500',
        ]);

        $driver = User::where('user_type', 'driver')
            ->where('onboarding_step', 'approved')
            ->findOrFail($id);

        $driver->is_active = false;
        $driver->save();

        Log::info('Driver deactivated', [
            'driver_id' => $driver->id,
            'deactivated_by' => auth()->id(),
            'reason' => $request->reason,
        ]);

        return back()->with('success', 'Driver deactivated');
    }

    /**
     * Reactivate a deactivated driver
     */
    public function reactivate(Request $request, string $id)
    {
        $driver = User::where('user_type', 'driver')
            ->where('onboarding_step', 'approved')
            ->where('is_active', false)
            ->findOrFail($id);

        $driver->is_active = true;
        $driver->save();

        Log::info('Driver reactivated', [
            'driver_id' => $driver->id,
            'reactivated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Driver reactivated');
    }

    /**
     * API endpoint for approving driver (for mobile admin app)
     */
    public function apiApprove(Request $request, string $id)
    {
        $driver = User::where('user_type', 'driver')
            ->where('onboarding_step', 'pending_approval')
            ->findOrFail($id);

        try {
            DB::beginTransaction();

            $driver->onboarding_step = 'approved';
            $driver->is_active = true;
            $driver->save();

            DriverDocument::where('driver_id', $driver->id)
                ->update([
                    'verified' => true,
                    'verified_at' => now(),
                    'verified_by' => auth()->id(),
                ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Driver approved successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to approve driver',
            ], 500);
        }
    }

    /**
     * API endpoint for rejecting driver (for mobile admin app)
     */
    public function apiReject(Request $request, string $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $driver = User::where('user_type', 'driver')
            ->where('onboarding_step', 'pending_approval')
            ->findOrFail($id);

        try {
            DB::beginTransaction();

            $driver->onboarding_step = 'documents';
            $driver->documents_completed_at = null;
            $driver->save();

            DriverDocument::where('driver_id', $driver->id)
                ->update([
                    'verified' => false,
                    'verified_by' => auth()->id(),
                    'rejection_reason' => $request->reason,
                ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Driver rejected',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reject driver',
            ], 500);
        }
    }

    /**
     * API: Get pending applications list
     */
    public function apiPendingList(Request $request)
    {
        $drivers = User::where('user_type', 'driver')
            ->where('onboarding_step', 'pending_approval')
            ->with(['driverDetails'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $drivers,
        ]);
    }

    /**
     * API: Get driver application details
     */
    public function apiShow(string $id)
    {
        $driver = User::where('user_type', 'driver')
            ->with(['driverDetails', 'vehicle'])
            ->findOrFail($id);

        $documents = DriverDocument::where('driver_id', $driver->id)->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'driver' => $driver,
                'documents' => $documents,
            ],
        ]);
    }
}
