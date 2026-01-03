<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Modules\TripManagement\Entities\TripRequest;

class ReadinessController extends Controller
{
    /**
     * Get comprehensive driver readiness status
     * GET /api/driver/auth/readiness-check
     *
     * This endpoint checks all prerequisites for driver to start accepting trips:
     * - Driver account status
     * - Vehicle status & documents
     * - GPS/Location status
     * - Network connectivity
     * - Active trip status
     * - Last trip information
     */
    public function check(): JsonResponse
    {
        $driver = auth('api')->user();

        // Load necessary relations
        $driver->load([
            'driverDetails',
            'vehicle',
            'vehicle.category',
            'latestTrack',
            'receivedReviews'
        ]);

        $driverDetails = $driver->driverDetails;
        // Use primary vehicle for readiness check
        $vehicle = $driver->primaryVehicle;

        // 1. ACCOUNT STATUS
        $accountStatus = $this->checkAccountStatus($driver);

        // 2. DRIVER STATUS (Online/Offline)
        $driverStatus = $this->checkDriverStatus($driver, $driverDetails);

        // 3. GPS/LOCATION STATUS
        $gpsStatus = $this->checkGPSStatus($driver);

        // 4. VEHICLE STATUS
        $vehicleStatus = $this->checkVehicleStatus($vehicle);

        // 5. DOCUMENT STATUS
        $documentStatus = $this->checkDocumentStatus($driver->id);

        // 6. NETWORK/CONNECTIVITY (Client-side check, we provide last seen)
        $connectivityStatus = $this->checkConnectivity($driver);

        // 7. ACTIVE TRIP STATUS
        $activeTripStatus = $this->checkActiveTripStatus($driver->id);

        // 8. LAST COMPLETED TRIP
        $lastTrip = $this->getLastCompletedTrip($driver->id);

        // 9. READY TO ACCEPT TRIPS STATUS
        $readyStatus = $this->calculateReadyStatus(
            $accountStatus,
            $driverStatus,
            $gpsStatus,
            $vehicleStatus,
            $documentStatus,
            $activeTripStatus
        );

        return response()->json(responseFormatter(DEFAULT_200, [
            'ready_status' => $readyStatus,
            'account' => $accountStatus,
            'driver' => $driverStatus,
            'gps' => $gpsStatus,
            'vehicle' => $vehicleStatus,
            'documents' => $documentStatus,
            'connectivity' => $connectivityStatus,
            'active_trip' => $activeTripStatus,
            'last_trip' => $lastTrip,
            'timestamp' => now()->toIso8601String(),
        ]));
    }

    /**
     * Check account status
     */
    private function checkAccountStatus($driver): array
    {
        $issues = [];

        // Check if account is active
        if (!$driver->is_active) {
            $issues[] = 'account_inactive';
        }

        // Check if account is verified
        if (!$driver->email_verified_at && $driver->email) {
            $issues[] = 'email_not_verified';
        }

        // Check if onboarding is complete
        if ($driver->onboarding_step !== 'approved') {
            $issues[] = 'onboarding_incomplete';
        }

        // Check for pending deletion request
        $deletionRequest = DB::table('account_deletion_requests')
            ->where('driver_id', $driver->id)
            ->where('status', 'pending')
            ->first();

        if ($deletionRequest) {
            $issues[] = 'deletion_pending';
        }

        return [
            'status' => count($issues) === 0 ? 'ready' : 'issues',
            'is_active' => (bool) $driver->is_active,
            'is_verified' => (bool) $driver->email_verified_at,
            'onboarding_complete' => $driver->onboarding_step === 'approved',
            'has_deletion_request' => (bool) $deletionRequest,
            'issues' => $issues,
            'message' => count($issues) === 0
                ? translate('Account is ready')
                : translate('Account has issues that need attention'),
        ];
    }

    /**
     * Check driver online/offline status
     */
    private function checkDriverStatus($driver, $driverDetails): array
    {
        $isOnline = $driverDetails ? (bool) $driverDetails->is_online : false;
        $availability = $driverDetails ? $driverDetails->availability_status : 'unavailable';

        // Get rating
        $avgRating = $driver->receivedReviews->avg('rating') ?? 0;
        $totalReviews = $driver->receivedReviews->count();

        return [
            'status' => $isOnline ? 'online' : 'offline',
            'is_online' => $isOnline,
            'availability' => $availability,
            'can_toggle_online' => true, // Can always toggle
            'rating' => [
                'average' => round($avgRating, 2),
                'total_reviews' => $totalReviews,
            ],
            'message' => $isOnline
                ? translate('You are online and can accept trips')
                : translate('Go online to start accepting trips'),
        ];
    }

    /**
     * Check GPS/Location status
     */
    private function checkGPSStatus($driver): array
    {
        $latestTrack = $driver->latestTrack;
        $hasLocation = (bool) $latestTrack;

        if ($hasLocation) {
            $lastUpdate = \Carbon\Carbon::parse($latestTrack->created_at);
            $minutesSinceUpdate = now()->diffInMinutes($lastUpdate);
            $isLocationStale = $minutesSinceUpdate > 5; // Stale if older than 5 minutes

            $status = $isLocationStale ? 'stale' : 'active';
        } else {
            $status = 'no_location';
            $minutesSinceUpdate = null;
        }

        return [
            'status' => $status,
            'has_location' => $hasLocation,
            'is_stale' => $status === 'stale',
            'latitude' => $hasLocation ? (float) $latestTrack->latitude : null,
            'longitude' => $hasLocation ? (float) $latestTrack->longitude : null,
            'last_update' => $hasLocation ? $latestTrack->created_at : null,
            'minutes_since_update' => $minutesSinceUpdate,
            'accuracy' => $hasLocation ? 'good' : null, // Client should provide this
            'message' => match($status) {
                'active' => translate('GPS location is active'),
                'stale' => translate('Location is outdated. Please enable GPS.'),
                'no_location' => translate('No location data. Please enable GPS.'),
                default => translate('Unknown GPS status'),
            },
        ];
    }

    /**
     * Check vehicle status
     */
    private function checkVehicleStatus($vehicle): array
    {
        if (!$vehicle) {
            return [
                'status' => 'missing',
                'has_vehicle' => false,
                'issues' => ['no_vehicle'],
                'message' => translate('No vehicle registered. Please add your vehicle.'),
            ];
        }

        $issues = [];

        // Check insurance expiry
        if ($vehicle->insurance_expiry_date) {
            $daysUntilExpiry = now()->diffInDays($vehicle->insurance_expiry_date, false);
            if ($daysUntilExpiry < 0) {
                $issues[] = 'insurance_expired';
            } elseif ($daysUntilExpiry <= 7) {
                $issues[] = 'insurance_expiring_soon';
            }
        } else {
            $issues[] = 'no_insurance_date';
        }

        // Check inspection
        if ($vehicle->next_inspection_due) {
            $daysUntilDue = now()->diffInDays($vehicle->next_inspection_due, false);
            if ($daysUntilDue < 0) {
                $issues[] = 'inspection_overdue';
            } elseif ($daysUntilDue <= 7) {
                $issues[] = 'inspection_due_soon';
            }
        } else {
            $issues[] = 'no_inspection_date';
        }

        $status = count($issues) === 0 ? 'ready' : 'issues';

        return [
            'status' => $status,
            'has_vehicle' => true,
            'vehicle_id' => $vehicle->id,
            'model' => $vehicle->model?->name ?? 'Unknown',
            'brand' => $vehicle->brand?->name ?? 'Unknown',
            'category' => $vehicle->category?->name ?? 'Unknown',
            'licence_plate' => $vehicle->licence_plate_number,
            'insurance' => [
                'expiry_date' => $vehicle->insurance_expiry_date,
                'days_remaining' => $vehicle->insurance_expiry_date
                    ? now()->diffInDays($vehicle->insurance_expiry_date, false)
                    : null,
                'is_valid' => $vehicle->insurance_expiry_date
                    ? now()->diffInDays($vehicle->insurance_expiry_date, false) >= 0
                    : false,
            ],
            'inspection' => [
                'next_due' => $vehicle->next_inspection_due,
                'days_remaining' => $vehicle->next_inspection_due
                    ? now()->diffInDays($vehicle->next_inspection_due, false)
                    : null,
                'is_current' => $vehicle->next_inspection_due
                    ? now()->diffInDays($vehicle->next_inspection_due, false) >= 0
                    : false,
            ],
            'issues' => $issues,
            'message' => $status === 'ready'
                ? translate('Vehicle is ready')
                : translate('Vehicle has issues that need attention'),
        ];
    }

    /**
     * Check document status
     */
    private function checkDocumentStatus($driverId): array
    {
        $documents = DB::table('driver_documents')
            ->where('driver_id', $driverId)
            ->get();

        $issues = [];
        $expiring = 0;
        $expired = 0;

        foreach ($documents as $doc) {
            // Check verification
            if (!$doc->verified) {
                $issues[] = "document_{$doc->type}_not_verified";
            }

            // Check expiry
            if ($doc->expiry_date) {
                $daysRemaining = now()->diffInDays($doc->expiry_date, false);
                if ($daysRemaining < 0) {
                    $issues[] = "document_{$doc->type}_expired";
                    $expired++;
                } elseif ($daysRemaining <= 30) {
                    $expiring++;
                }
            }
        }

        $allVerified = $documents->every(fn($d) => $d->verified);
        $status = count($issues) === 0 ? 'ready' : 'issues';

        return [
            'status' => $status,
            'total_documents' => $documents->count(),
            'verified_documents' => $documents->where('verified', true)->count(),
            'all_verified' => $allVerified,
            'expiring_soon' => $expiring,
            'expired' => $expired,
            'issues' => $issues,
            'message' => $status === 'ready'
                ? translate('All documents are valid')
                : translate('Some documents need attention'),
        ];
    }

    /**
     * Check network connectivity
     */
    private function checkConnectivity($driver): array
    {
        // This is primarily client-side check
        // We can check last API activity
        $lastActivity = $driver->updated_at;
        $minutesSinceActivity = now()->diffInMinutes($lastActivity);

        return [
            'status' => 'connected', // Client will update this
            'last_api_call' => $lastActivity,
            'minutes_since_activity' => $minutesSinceActivity,
            'message' => translate('Check your WiFi or mobile data connection'),
            'note' => 'This status should be updated by client based on actual network state',
        ];
    }

    /**
     * Check active trip status
     */
    private function checkActiveTripStatus($driverId): array
    {
        $activeTrip = TripRequest::where('driver_id', $driverId)
            ->whereIn('current_status', ['accepted', 'ongoing', 'arrived'])
            ->with(['customer', 'pickupZone', 'destinationZone'])
            ->first();

        if ($activeTrip) {
            return [
                'has_active_trip' => true,
                'trip_id' => $activeTrip->id,
                'status' => $activeTrip->current_status,
                'customer_name' => $activeTrip->customer
                    ? $activeTrip->customer->first_name . ' ' . $activeTrip->customer->last_name
                    : 'Unknown',
                'pickup_address' => $activeTrip->pickup_address,
                'destination_address' => $activeTrip->destination_address,
                'pickup_coordinates' => [
                    'lat' => (float) $activeTrip->pickup_coordinates->latitude,
                    'lng' => (float) $activeTrip->pickup_coordinates->longitude,
                ],
                'destination_coordinates' => $activeTrip->destination_coordinates ? [
                    'lat' => (float) $activeTrip->destination_coordinates->latitude,
                    'lng' => (float) $activeTrip->destination_coordinates->longitude,
                ] : null,
                'estimated_fare' => (float) $activeTrip->estimated_fare,
                'started_at' => $activeTrip->accepted_time,
                'message' => translate('You have an active trip'),
            ];
        }

        return [
            'has_active_trip' => false,
            'trip_id' => null,
            'message' => translate('No active trips'),
        ];
    }

    /**
     * Get last completed trip
     */
    private function getLastCompletedTrip($driverId): ?array
    {
        $lastTrip = TripRequest::where('driver_id', $driverId)
            ->where('current_status', 'completed')
            ->orderBy('created_at', 'desc')
            ->with(['customer'])
            ->first();

        if (!$lastTrip) {
            return null;
        }

        return [
            'trip_id' => $lastTrip->id,
            'completed_at' => $lastTrip->updated_at->toIso8601String(),
            'time_ago' => $lastTrip->updated_at->diffForHumans(),
            'customer_name' => $lastTrip->customer
                ? $lastTrip->customer->first_name
                : 'Unknown',
            'fare' => (float) $lastTrip->paid_fare,
            'formatted_fare' => getCurrencyFormat($lastTrip->paid_fare),
            'pickup_address' => $lastTrip->pickup_address,
            'destination_address' => $lastTrip->destination_address,
            'distance_km' => $lastTrip->estimated_distance,
            'rating_given' => $lastTrip->driverReceivedReview()->exists() || $lastTrip->customerReceivedReview()->exists(),
        ];
    }

    /**
     * Calculate overall ready status
     */
    private function calculateReadyStatus(
        array $account,
        array $driver,
        array $gps,
        array $vehicle,
        array $documents,
        array $activeTrip
    ): array {
        $blockers = [];
        $warnings = [];

        // Critical blockers
        if ($account['status'] !== 'ready') {
            $blockers[] = 'account_not_ready';
        }

        if ($vehicle['status'] === 'missing') {
            $blockers[] = 'no_vehicle';
        }

        if ($documents['status'] === 'issues' && !$documents['all_verified']) {
            $blockers[] = 'documents_not_verified';
        }

        if ($activeTrip['has_active_trip']) {
            $blockers[] = 'trip_in_progress';
        }

        // Warnings (not blocking but should be addressed)
        if ($gps['status'] !== 'active') {
            $warnings[] = 'gps_not_active';
        }

        if (!$driver['is_online']) {
            $warnings[] = 'driver_offline';
        }

        if ($vehicle['status'] === 'issues') {
            $warnings[] = 'vehicle_has_issues';
        }

        if ($documents['expiring_soon'] > 0) {
            $warnings[] = 'documents_expiring_soon';
        }

        // Determine overall status
        $canAcceptTrips = count($blockers) === 0 && $driver['is_online'];

        $overallStatus = match(true) {
            count($blockers) > 0 => 'blocked',
            !$driver['is_online'] => 'offline',
            count($warnings) > 0 => 'ready_with_warnings',
            default => 'ready',
        };

        return [
            'overall_status' => $overallStatus,
            'can_accept_trips' => $canAcceptTrips,
            'is_ready' => $overallStatus === 'ready',
            'blockers' => $blockers,
            'blocker_count' => count($blockers),
            'warnings' => $warnings,
            'warning_count' => count($warnings),
            'message' => $this->getReadyStatusMessage($overallStatus, $blockers, $warnings),
            'action_required' => count($blockers) > 0
                ? translate('Please resolve the issues before going online')
                : (count($warnings) > 0
                    ? translate('Some items need attention')
                    : null),
        ];
    }

    /**
     * Get human-readable ready status message
     */
    private function getReadyStatusMessage(string $status, array $blockers, array $warnings): string
    {
        return match($status) {
            'ready' => translate('All systems ready! You can start accepting trips.'),
            'ready_with_warnings' => translate('Ready to go, but ' . count($warnings) . ' items need attention.'),
            'offline' => translate('Go online to start accepting trips.'),
            'blocked' => translate('Cannot accept trips: ' . count($blockers) . ' issues must be resolved.'),
            default => translate('Status unknown'),
        };
    }
}
