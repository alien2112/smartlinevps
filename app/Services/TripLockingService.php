<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\TripManagement\Entities\TripRequest;

/**
 * Service to handle trip assignment with proper database locking
 * Prevents race conditions where multiple drivers accept the same trip
 */
class TripLockingService
{
    /**
     * Attempt to lock and assign a trip to a driver atomically
     * Uses pessimistic locking with SELECT FOR UPDATE
     *
     * @param string $tripId
     * @param string $driverId
     * @param int $expectedVersion Optional version for optimistic locking
     * @param array $additionalData Optional additional fields to update (otp, vehicle_id, vehicle_category_id, actual_fare)
     * @return array ['success' => bool, 'trip' => TripRequest|null, 'message' => string]
     */
    public function lockAndAssignTrip(string $tripId, string $driverId, int $expectedVersion = null, array $additionalData = []): array
    {
        Log::info('Attempting to lock and assign trip', [
            'trip_id' => $tripId,
            'driver_id' => $driverId,
            'expected_version' => $expectedVersion,
            'has_additional_data' => !empty($additionalData)
        ]);

        return DB::transaction(function () use ($tripId, $driverId, $expectedVersion, $additionalData) {
            // Pessimistic locking: Lock the row for update
            $trip = TripRequest::where('id', $tripId)
                ->lockForUpdate() // SELECT ... FOR UPDATE
                ->first();

            if (!$trip) {
                Log::warning('Trip not found for locking', ['trip_id' => $tripId]);
                return [
                    'success' => false,
                    'trip' => null,
                    'message' => 'Trip not found'
                ];
            }

            Log::debug('Trip found for locking', [
                'trip_id' => $tripId,
                'current_driver_id' => $trip->driver_id,
                'current_status' => $trip->current_status,
                'version' => $trip->version
            ]);

            // Check if trip is already assigned
            if ($trip->driver_id && $trip->driver_id !== $driverId) {
                Log::warning('Trip already assigned to another driver', [
                    'trip_id' => $tripId,
                    'assigned_driver' => $trip->driver_id,
                    'attempting_driver' => $driverId
                ]);

                return [
                    'success' => false,
                    'trip' => $trip,
                    'message' => 'Trip already assigned to another driver'
                ];
            }

            // Idempotent check: if same driver already assigned, return success
            if ($trip->driver_id === $driverId && $trip->current_status === 'accepted') {
                Log::info('Idempotent request: trip already assigned to this driver', [
                    'trip_id' => $tripId,
                    'driver_id' => $driverId
                ]);
                return [
                    'success' => false, // Return false so controller handles idempotent case
                    'trip' => $trip,
                    'message' => 'Already assigned to you'
                ];
            }

            // Check if trip status is acceptable
            if (!in_array($trip->current_status, ['pending', 'searching', null])) {
                Log::warning('Trip status not acceptable for assignment', [
                    'trip_id' => $tripId,
                    'current_status' => $trip->current_status,
                    'driver_id' => $driverId
                ]);
                return [
                    'success' => false,
                    'trip' => $trip,
                    'message' => "Trip status is {$trip->current_status}, cannot accept"
                ];
            }

            // Optimistic locking check if version provided
            if ($expectedVersion !== null && $trip->version != $expectedVersion) {
                Log::warning('Optimistic lock conflict', [
                    'trip_id' => $tripId,
                    'expected_version' => $expectedVersion,
                    'actual_version' => $trip->version
                ]);

                return [
                    'success' => false,
                    'trip' => $trip,
                    'message' => 'Trip was modified by another request, please retry'
                ];
            }

            // Assign trip to driver
            $trip->driver_id = $driverId;
            $trip->current_status = 'accepted';
            $trip->locked_at = now();
            $trip->version = ($trip->version ?? 0) + 1; // Increment version with null safety

            // Apply additional data in the same transaction for atomicity
            if (isset($additionalData['otp'])) {
                $trip->otp = $additionalData['otp'];
            }
            if (isset($additionalData['vehicle_id'])) {
                $trip->vehicle_id = $additionalData['vehicle_id'];
            }
            if (isset($additionalData['vehicle_category_id'])) {
                $trip->vehicle_category_id = $additionalData['vehicle_category_id'];
            }
            if (isset($additionalData['actual_fare'])) {
                $trip->actual_fare = $additionalData['actual_fare'];
            }

            $trip->save();

            Log::info('Trip successfully assigned', [
                'trip_id' => $tripId,
                'driver_id' => $driverId,
                'version' => $trip->version,
                'status' => $trip->current_status,
                'otp_set' => isset($additionalData['otp'])
            ]);

            return [
                'success' => true,
                'trip' => $trip->fresh(),
                'message' => 'Trip assigned successfully'
            ];
        }, 5); // Retry up to 5 times on deadlock
    }

    /**
     * Release a trip lock (e.g., driver cancels before pickup)
     * Uses atomic update with version check
     *
     * @param string $tripId
     * @param string $driverId
     * @return array
     */
    public function releaseTripLock(string $tripId, string $driverId): array
    {
        return DB::transaction(function () use ($tripId, $driverId) {
            $trip = TripRequest::where('id', $tripId)
                ->where('driver_id', $driverId)
                ->lockForUpdate()
                ->first();

            if (!$trip) {
                return [
                    'success' => false,
                    'message' => 'Trip not found or not assigned to this driver'
                ];
            }

            // Only release if status allows it
            if (in_array($trip->current_status, ['ongoing', 'completed', 'started'])) {
                return [
                    'success' => false,
                    'message' => "Cannot release trip in {$trip->current_status} status"
                ];
            }

            $trip->driver_id = null;
            $trip->current_status = 'pending';
            $trip->locked_at = null;
            $trip->version = $trip->version + 1;
            $trip->save();

            return [
                'success' => true,
                'message' => 'Trip lock released successfully'
            ];
        }, 5);
    }

    /**
     * Atomic driver availability update
     * Updates driver status and trip assignment in single transaction
     *
     * @param string $driverId
     * @param string $availabilityStatus
     * @param bool $isOnline
     * @return bool
     */
    public function updateDriverAvailabilityAtomic(
        string $driverId,
        string $availabilityStatus,
        bool $isOnline
    ): bool {
        return DB::transaction(function () use ($driverId, $availabilityStatus, $isOnline) {
            // Lock driver details row
            $driverDetails = DB::table('driver_details')
                ->where('user_id', $driverId)
                ->lockForUpdate()
                ->first();

            if (!$driverDetails) {
                return false;
            }

            // Update driver availability
            DB::table('driver_details')
                ->where('user_id', $driverId)
                ->update([
                    'availability_status' => $availabilityStatus,
                    'is_online' => $isOnline,
                    'updated_at' => now(),
                ]);

            // If going offline, release any pending trips
            if (!$isOnline || $availabilityStatus !== 'available') {
                DB::table('trip_requests')
                    ->where('driver_id', $driverId)
                    ->whereIn('current_status', ['accepted', 'pending'])
                    ->update([
                        'driver_id' => null,
                        'current_status' => 'pending',
                        'locked_at' => null,
                        'version' => DB::raw('version + 1'),
                        'updated_at' => now(),
                    ]);
            }

            return true;
        }, 5);
    }

    /**
     * Check if a trip can be accepted (with shared lock for read)
     *
     * @param string $tripId
     * @return array
     */
    public function canAcceptTrip(string $tripId): array
    {
        $trip = TripRequest::where('id', $tripId)
            ->sharedLock() // SELECT ... LOCK IN SHARE MODE (allow concurrent reads)
            ->first();

        if (!$trip) {
            return ['can_accept' => false, 'reason' => 'Trip not found'];
        }

        if ($trip->driver_id) {
            return ['can_accept' => false, 'reason' => 'Already assigned'];
        }

        if (!in_array($trip->current_status, ['pending', 'searching', null])) {
            return ['can_accept' => false, 'reason' => 'Invalid status'];
        }

        return ['can_accept' => true, 'trip' => $trip];
    }
}
