<?php

namespace Modules\UserManagement\Http\Controllers\Web\New\Admin\Driver;

use App\Enums\DriverOnboardingState;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\UserManagement\Entities\DriverDocument;
use Modules\UserManagement\Entities\User;
use Modules\VehicleManagement\Service\Interface\VehicleServiceInterface;

/**
 * DriverApprovalController
 *
 * Admin controller for managing driver onboarding applications.
 * Handles approval, rejection, and document verification.
 */
class DriverApprovalController extends Controller
{
    protected $vehicleService;

    public function __construct(VehicleServiceInterface $vehicleService)
    {
        $this->vehicleService = $vehicleService;
    }

    /**
     * Display pending driver applications
     */
    public function index(Request $request)
    {
        $status = $request->get('status', 'pending_approval');
        
        // Handle vehicle changes tab
        if ($status === 'vehicle_changes') {
            return $this->vehicleChanges($request);
        }
        
        $query = User::where('user_type', 'driver')
            ->with(['driverDetails', 'vehicle']);

        // Filter by onboarding state (new) or step (legacy)
        if ($status === 'pending_approval') {
            $query->where(function($q) {
                $q->where('onboarding_state', DriverOnboardingState::PENDING_APPROVAL->value)
                  ->orWhere('onboarding_step', 'pending_approval'); // Legacy support
            });
        } elseif ($status === 'approved') {
            $query->where(function($q) {
                $q->where('onboarding_state', DriverOnboardingState::APPROVED->value)
                  ->orWhere('onboarding_step', 'approved'); // Legacy support
            });
        } elseif ($status === 'in_progress') {
            $query->where(function($q) {
                $q->whereNotIn('onboarding_state', [
                    DriverOnboardingState::PENDING_APPROVAL->value,
                    DriverOnboardingState::APPROVED->value
                ])
                  ->orWhereNotIn('onboarding_step', ['pending_approval', 'approved']); // Legacy support
            });
        }

        $drivers = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        // Get counts for tabs
        $counts = [
            'pending' => User::where('user_type', 'driver')
                ->where(function($q) {
                    $q->where('onboarding_state', DriverOnboardingState::PENDING_APPROVAL->value)
                      ->orWhere('onboarding_step', 'pending_approval'); // Legacy support
                })
                ->count(),
            'approved' => User::where('user_type', 'driver')
                ->where(function($q) {
                    $q->where('onboarding_state', DriverOnboardingState::APPROVED->value)
                      ->orWhere('onboarding_step', 'approved'); // Legacy support
                })
                ->count(),
            'in_progress' => User::where('user_type', 'driver')
                ->where(function($q) {
                    $q->whereNotIn('onboarding_state', [
                        DriverOnboardingState::PENDING_APPROVAL->value,
                        DriverOnboardingState::APPROVED->value
                    ])
                      ->orWhereNotIn('onboarding_step', ['pending_approval', 'approved']); // Legacy support
                })
                ->count(),
            'vehicle_changes' => DB::table('vehicles')
                ->where('vehicle_request_status', 'pending')
                ->where('has_pending_primary_request', 1)
                ->count(),
        ];

        return view('usermanagement::admin.driver.approvals.index', compact('drivers', 'status', 'counts'));
    }
    
    /**
     * Show pending vehicle change requests
     */
    protected function vehicleChanges(Request $request)
    {
        // Get drivers with pending vehicle changes
        $driversWithChanges = DB::table('vehicles')
            ->select('driver_id')
            ->where('vehicle_request_status', 'pending')
            ->where('has_pending_primary_request', 1)
            ->distinct()
            ->pluck('driver_id');
        
        $drivers = User::where('user_type', 'driver')
            ->whereIn('id', $driversWithChanges)
            ->with(['driverDetails', 'vehicles' => function($query) {
                $query->where('vehicle_request_status', 'pending')
                      ->orWhere('is_primary', 1);
            }, 'vehicles.brand', 'vehicles.model', 'vehicles.category'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        // Get counts for tabs
        $counts = [
            'pending' => User::where('user_type', 'driver')
                ->where(function($q) {
                    $q->where('onboarding_state', DriverOnboardingState::PENDING_APPROVAL->value)
                      ->orWhere('onboarding_step', 'pending_approval');
                })
                ->count(),
            'approved' => User::where('user_type', 'driver')
                ->where(function($q) {
                    $q->where('onboarding_state', DriverOnboardingState::APPROVED->value)
                      ->orWhere('onboarding_step', 'approved');
                })
                ->count(),
            'in_progress' => User::where('user_type', 'driver')
                ->where(function($q) {
                    $q->whereNotIn('onboarding_state', [
                        DriverOnboardingState::PENDING_APPROVAL->value,
                        DriverOnboardingState::APPROVED->value
                    ])
                      ->orWhereNotIn('onboarding_step', ['pending_approval', 'approved']);
                })
                ->count(),
            'vehicle_changes' => count($driversWithChanges),
        ];
        
        $status = 'vehicle_changes';
        
        return view('usermanagement::admin.driver.approvals.vehicle-changes', compact('drivers', 'status', 'counts'));
    }

    /**
     * Show driver application details
     */
    public function show(string $id)
    {
        $driver = User::where('user_type', 'driver')
            ->with(['driverDetails', 'vehicle', 'vehicles.brand', 'vehicles.model', 'vehicles.category'])
            ->findOrFail($id);

        // Get all uploaded documents - show ALL active documents (including multiple per type)
        $allDocuments = DriverDocument::where('driver_id', $driver->id)
            ->where('is_active', 1)
            ->orderBy('type')
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Group by type - keep ALL documents per type (for front/back pairs)
        $documents = $allDocuments->groupBy('type');

        // Get required documents for this vehicle type
        $requiredDocs = DriverDocument::getRequiredDocuments($driver->selected_vehicle_type);

        return view('usermanagement::admin.driver.approvals.show', compact('driver', 'documents', 'requiredDocs'));
    }

    /**
     * Approve driver application
     *
     * NOTE: KYC verification is NOT checked here. Drivers can be approved regardless
     * of their kyc_status. This is controlled by KYC_REQUIRED_FOR_APPROVAL env variable.
     */
    public function approve(Request $request, string $id)
    {
        $driver = User::where('user_type', 'driver')
            ->where(function($query) {
                $query->where('onboarding_state', DriverOnboardingState::PENDING_APPROVAL->value)
                      ->orWhere('onboarding_step', 'pending_approval');
            })
            ->findOrFail($id);

        try {
            DB::beginTransaction();

            // Approve driver regardless of KYC status (unless explicitly required in config)
            // KYC runs in parallel but doesn't block approval
            if (config('verification.required_for_approval', false)) {
                // Only check KYC if explicitly required
                if (!in_array($driver->kyc_status ?? 'not_required', ['verified', 'not_required'])) {
                    DB::rollBack();
                    return back()->with('error', 'Driver KYC verification must be completed before approval');
                }
            }

            // Update both state and step for compatibility
            $driver->onboarding_state = DriverOnboardingState::APPROVED->value;
            $driver->onboarding_state_version = ($driver->onboarding_state_version ?? 0) + 1;
            $driver->onboarding_step = 'approved';
            $driver->is_active = true;
            $driver->is_approved = true;
            $driver->approved_at = now();
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
            ->where(function($query) {
                $query->where('onboarding_state', DriverOnboardingState::PENDING_APPROVAL->value)
                      ->orWhere('onboarding_step', 'pending_approval'); // Legacy support
            })
            ->findOrFail($id);

        try {
            DB::beginTransaction();

            // Update to new state machine - send back to documents (document rejection, not full rejection)
            $currentState = DriverOnboardingState::fromString($driver->onboarding_state ?? $driver->onboarding_step ?? 'pending_approval');
            $newState = DriverOnboardingState::DOCUMENTS_PENDING;
            
            if (!$currentState->canTransitionTo($newState)) {
                return back()->with('error', 'Invalid state transition');
            }

            $driver->onboarding_state = $newState->value;
            $driver->onboarding_state_version = ($driver->onboarding_state_version ?? 0) + 1;
            $driver->onboarding_step = 'documents'; // Keep for backward compatibility
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
            $currentState = DriverOnboardingState::fromString($driver->onboarding_state ?? $driver->onboarding_step ?? 'documents_pending');
            // Only update if in a state that allows going back to documents
            if ($currentState->canTransitionTo(DriverOnboardingState::DOCUMENTS_PENDING)) {
                $driver->onboarding_state = DriverOnboardingState::DOCUMENTS_PENDING->value;
                $driver->onboarding_state_version = ($driver->onboarding_state_version ?? 0) + 1;
            }
            $driver->onboarding_step = 'documents'; // Keep for backward compatibility
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
            ->where(function($query) {
                $query->where('onboarding_state', DriverOnboardingState::APPROVED->value)
                      ->orWhere('onboarding_step', 'approved'); // Legacy support
            })
            ->findOrFail($id);

        // Update to suspended state
        $currentState = DriverOnboardingState::fromString($driver->onboarding_state ?? $driver->onboarding_step ?? 'approved');
        $newState = DriverOnboardingState::SUSPENDED;
        
        if ($currentState->canTransitionTo($newState)) {
            $driver->onboarding_state = $newState->value;
            $driver->onboarding_state_version = ($driver->onboarding_state_version ?? 0) + 1;
        }
        
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
            ->where(function($query) {
                $query->where('onboarding_state', DriverOnboardingState::SUSPENDED->value)
                      ->orWhere(function($q) {
                          $q->where('onboarding_step', 'approved')
                            ->where('is_active', false);
                      }); // Legacy support
            })
            ->findOrFail($id);

        // Update from suspended to approved
        $currentState = DriverOnboardingState::fromString($driver->onboarding_state ?? $driver->onboarding_step ?? 'suspended');
        $newState = DriverOnboardingState::APPROVED;
        
        if ($currentState->canTransitionTo($newState)) {
            $driver->onboarding_state = $newState->value;
            $driver->onboarding_state_version = ($driver->onboarding_state_version ?? 0) + 1;
            $driver->onboarding_step = 'approved'; // Keep for backward compatibility
        }
        
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
     *
     * NOTE: KYC verification is NOT checked here. Drivers can be approved regardless
     * of their kyc_status. This is controlled by KYC_REQUIRED_FOR_APPROVAL env variable.
     */
    public function apiApprove(Request $request, string $id)
    {
        $driver = User::where('user_type', 'driver')
            ->where(function($query) {
                $query->where('onboarding_state', DriverOnboardingState::PENDING_APPROVAL->value)
                      ->orWhere('onboarding_step', 'pending_approval'); // Legacy support
            })
            ->findOrFail($id);

        try {
            DB::beginTransaction();

            // Check KYC only if required by config (default: false)
            if (config('verification.required_for_approval', false)) {
                if (!in_array($driver->kyc_status ?? 'not_required', ['verified', 'not_required'])) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Driver KYC verification must be completed before approval',
                    ], 400);
                }
            }

            // Update to new state machine
            $currentState = DriverOnboardingState::fromString($driver->onboarding_state ?? $driver->onboarding_step ?? 'pending_approval');
            $newState = DriverOnboardingState::APPROVED;

            if (!$currentState->canTransitionTo($newState)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid state transition',
                ], 400);
            }

            $driver->onboarding_state = $newState->value;
            $driver->onboarding_state_version = ($driver->onboarding_state_version ?? 0) + 1;
            $driver->onboarding_step = 'approved'; // Keep for backward compatibility
            $driver->is_active = true;
            $driver->is_approved = true;
            $driver->approved_at = now();
            $driver->approved_by = auth()->id();
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
            ->where(function($query) {
                $query->where('onboarding_state', DriverOnboardingState::PENDING_APPROVAL->value)
                      ->orWhere('onboarding_step', 'pending_approval'); // Legacy support
            })
            ->findOrFail($id);

        try {
            DB::beginTransaction();

            // Check if this is a full rejection or document rejection
            $isFullRejection = $request->input('full_rejection', false);
            
            if ($isFullRejection) {
                // Full rejection - go to rejected state
                $currentState = DriverOnboardingState::fromString($driver->onboarding_state ?? $driver->onboarding_step ?? 'pending_approval');
                $newState = DriverOnboardingState::REJECTED;
                
                if ($currentState->canTransitionTo($newState)) {
                    $driver->onboarding_state = $newState->value;
                    $driver->onboarding_state_version = ($driver->onboarding_state_version ?? 0) + 1;
                    $driver->onboarding_step = 'rejected';
                    $driver->rejection_reason = $request->reason;
                    $driver->rejected_at = now();
                    $driver->is_approved = false;
                }
            } else {
                // Document rejection - send back to documents
                $currentState = DriverOnboardingState::fromString($driver->onboarding_state ?? $driver->onboarding_step ?? 'pending_approval');
                $newState = DriverOnboardingState::DOCUMENTS_PENDING;
                
                if ($currentState->canTransitionTo($newState)) {
                    $driver->onboarding_state = $newState->value;
                    $driver->onboarding_state_version = ($driver->onboarding_state_version ?? 0) + 1;
                    $driver->onboarding_step = 'documents';
                    $driver->documents_completed_at = null;
                }
            }
            
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
            ->where(function($query) {
                $query->where('onboarding_state', DriverOnboardingState::PENDING_APPROVAL->value)
                      ->orWhere('onboarding_step', 'pending_approval'); // Legacy support
            })
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

    /**
     * Approve individual vehicle
     */
    public function approveVehicle(Request $request, string $driverId, string $vehicleId)
    {
        try {
            DB::beginTransaction();

            $vehicle = $this->vehicleService->findOne($vehicleId);

            if (!$vehicle || $vehicle->driver_id !== $driverId) {
                return back()->with('error', 'Vehicle not found');
            }

            // If vehicle has draft changes, apply them
            if ($vehicle->draft) {
                $this->vehicleService->approveVehicleUpdate($vehicleId);
            } else {
                // Just approve the vehicle
                $this->vehicleService->update($vehicleId, [
                    'vehicle_request_status' => APPROVED,
                    'is_active' => true
                ]);
            }

            // If this vehicle has a pending primary request, also approve it
            if ($vehicle->has_pending_primary_request) {
                $this->vehicleService->approvePrimaryVehicleChange($vehicleId);
                $message = 'Vehicle approved and set as primary successfully';
            } else {
                $message = 'Vehicle approved successfully';
            }

            DB::commit();

            Log::info('Vehicle approved', [
                'vehicle_id' => $vehicleId,
                'driver_id' => $driverId,
                'approved_by' => auth()->id(),
                'set_as_primary' => $vehicle->has_pending_primary_request,
            ]);

            return back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Vehicle approval failed', [
                'vehicle_id' => $vehicleId,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to approve vehicle: ' . $e->getMessage());
        }
    }

    /**
     * Reject individual vehicle
     */
    public function rejectVehicle(Request $request, string $driverId, string $vehicleId)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            $vehicle = $this->vehicleService->findOne($vehicleId);

            if (!$vehicle || $vehicle->driver_id !== $driverId) {
                return back()->with('error', 'Vehicle not found');
            }

            // Reject the vehicle
            $this->vehicleService->update($vehicleId, [
                'vehicle_request_status' => REJECTED,
                'deny_note' => $request->reason,
                'is_active' => false,
                'draft' => null // Clear any pending updates
            ]);

            DB::commit();

            Log::info('Vehicle rejected', [
                'vehicle_id' => $vehicleId,
                'driver_id' => $driverId,
                'rejected_by' => auth()->id(),
                'reason' => $request->reason,
            ]);

            return back()->with('success', 'Vehicle rejected');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Vehicle rejection failed', [
                'vehicle_id' => $vehicleId,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to reject vehicle: ' . $e->getMessage());
        }
    }

    /**
     * Approve primary vehicle change request
     */
    public function approvePrimaryVehicleChange(Request $request, string $driverId, string $vehicleId)
    {
        try {
            DB::beginTransaction();

            $vehicle = $this->vehicleService->findOne($vehicleId);

            if (!$vehicle || $vehicle->driver_id !== $driverId) {
                return back()->with('error', 'Vehicle not found');
            }

            if (!$vehicle->has_pending_primary_request) {
                return back()->with('error', 'No pending primary vehicle change request for this vehicle');
            }

            // Apply the primary vehicle change
            $this->vehicleService->approvePrimaryVehicleChange($vehicleId);

            DB::commit();

            Log::info('Primary vehicle change approved', [
                'vehicle_id' => $vehicleId,
                'driver_id' => $driverId,
                'approved_by' => auth()->id(),
            ]);

            return back()->with('success', 'Primary vehicle changed successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Primary vehicle change approval failed', [
                'vehicle_id' => $vehicleId,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to approve primary vehicle change: ' . $e->getMessage());
        }
    }

    /**
     * Reject primary vehicle change request
     */
    public function rejectPrimaryVehicleChange(Request $request, string $driverId, string $vehicleId)
    {
        try {
            DB::beginTransaction();

            $vehicle = $this->vehicleService->findOne($vehicleId);

            if (!$vehicle || $vehicle->driver_id !== $driverId) {
                return back()->with('error', 'Vehicle not found');
            }

            if (!$vehicle->has_pending_primary_request) {
                return back()->with('error', 'No pending primary vehicle change request for this vehicle');
            }

            // Reject the primary vehicle change request
            $this->vehicleService->denyPrimaryVehicleChange($vehicleId);

            DB::commit();

            Log::info('Primary vehicle change rejected', [
                'vehicle_id' => $vehicleId,
                'driver_id' => $driverId,
                'rejected_by' => auth()->id(),
            ]);

            return back()->with('success', 'Primary vehicle change request rejected');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Primary vehicle change rejection failed', [
                'vehicle_id' => $vehicleId,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to reject primary vehicle change: ' . $e->getMessage());
        }
    }
}
