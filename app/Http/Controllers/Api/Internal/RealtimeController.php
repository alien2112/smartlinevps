<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\TripManagement\Entities\TripRequest;
use Modules\TripManagement\Repository\Eloquent\TripRequestRepository;
use App\Services\TripLockingService;

/**
 * Internal API Controller for Node.js Realtime Service
 *
 * SECURITY: This controller should only be accessible by the Node.js service
 * Verify API key in middleware
 */
class RealtimeController extends Controller
{
    protected $tripRepository;
    protected $lockingService;

    public function __construct(
        TripRequestRepository $tripRepository,
        TripLockingService $lockingService
    ) {
        $this->tripRepository = $tripRepository;
        $this->lockingService = $lockingService;

        // Verify API key from Node.js service
        $this->middleware(function ($request, $next) {
            $apiKey = $request->header('X-API-Key');
            $expectedKey = config('services.realtime.api_key');

            if (!$expectedKey) {
                Log::error('Internal API key not configured');
                return response()->json([
                    'success' => false,
                    'message' => 'Internal API not configured'
                ], 500);
            }

            if ($apiKey !== $expectedKey) {
                Log::warning('Invalid internal API key attempt', [
                    'ip' => $request->ip(),
                    'provided_key' => substr($apiKey, 0, 8) . '...'
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            return $next($request);
        });
    }

    /**
     * Node.js calls this when a driver accepts a ride
     * This handles the database update with proper locking
     *
     * POST /api/internal/ride/assign-driver
     *
     * Request:
     * {
     *   "ride_id": "uuid",
     *   "driver_id": "uuid"
     * }
     */
    public function assignDriver(Request $request)
    {
        $validated = $request->validate([
            'ride_id' => 'required|uuid',
            'driver_id' => 'required|uuid',
        ]);

        $rideId = $validated['ride_id'];
        $driverId = $validated['driver_id'];

        Log::info('Internal API: Assign driver request', [
            'ride_id' => $rideId,
            'driver_id' => $driverId
        ]);

        try {
            // Use locking service to prevent race conditions
            $result = $this->lockingService->lockAndAssignTrip($rideId, $driverId);

            if ($result['success']) {
                $trip = $result['trip'];

                return response()->json([
                    'success' => true,
                    'message' => 'Driver assigned successfully',
                    'ride' => [
                        'id' => $trip->id,
                        'customer_id' => $trip->customer_id,
                        'driver_id' => $trip->driver_id,
                        'current_status' => $trip->current_status,
                        'estimated_fare' => $trip->estimated_fare,
                    ],
                    'driver' => [
                        'id' => $trip->driver->id,
                        'name' => $trip->driver->name,
                        'phone' => $trip->driver->phone,
                        'vehicle' => [
                            'model' => $trip->driver->vehicle->model ?? null,
                            'plate_number' => $trip->driver->vehicle->plate_number ?? null,
                        ],
                    ],
                    'estimated_arrival' => 5, // TODO: Calculate actual ETA
                ]);
            }

            // Assignment failed (ride already taken, driver not available, etc.)
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to assign driver'
            ], 409);

        } catch (\Exception $e) {
            Log::error('Internal API: Error assigning driver', [
                'ride_id' => $rideId,
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Handle generic events from Node.js
     *
     * POST /api/internal/events/{event}
     *
     * Events:
     * - ride.no_drivers - No drivers available for ride
     * - ride.timeout - No driver accepted within timeout
     * - driver.disconnected - Driver lost connection during ride
     */
    public function handleEvent(Request $request, string $event)
    {
        $data = $request->all();

        Log::info('Internal API: Event received', [
            'event' => $event,
            'data' => $data
        ]);

        try {
            switch ($event) {
                case 'ride.no_drivers':
                    return $this->handleNoDriversEvent($data);

                case 'ride.timeout':
                    return $this->handleRideTimeoutEvent($data);

                case 'driver.disconnected':
                    return $this->handleDriverDisconnectedEvent($data);

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Unknown event type'
                    ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Internal API: Error handling event', [
                'event' => $event,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Handle no drivers available event
     */
    protected function handleNoDriversEvent(array $data)
    {
        $rideId = $data['ride_id'] ?? null;

        if (!$rideId) {
            return response()->json(['success' => false, 'message' => 'Missing ride_id'], 400);
        }

        $trip = TripRequest::find($rideId);

        if (!$trip) {
            return response()->json(['success' => false, 'message' => 'Ride not found'], 404);
        }

        // Only update if still pending
        if ($trip->current_status === 'pending') {
            $trip->current_status = 'no_drivers_available';
            $trip->save();

            Log::info('Ride marked as no drivers available', ['ride_id' => $rideId]);

            // TODO: Send notification to customer
        }

        return response()->json(['success' => true]);
    }

    /**
     * Handle ride timeout event (no driver accepted)
     */
    protected function handleRideTimeoutEvent(array $data)
    {
        $rideId = $data['ride_id'] ?? null;

        if (!$rideId) {
            return response()->json(['success' => false, 'message' => 'Missing ride_id'], 400);
        }

        $trip = TripRequest::find($rideId);

        if (!$trip) {
            return response()->json(['success' => false, 'message' => 'Ride not found'], 404);
        }

        // Only update if still pending
        if ($trip->current_status === 'pending') {
            $trip->current_status = 'timeout';
            $trip->save();

            Log::info('Ride timed out', ['ride_id' => $rideId]);

            // TODO: Send notification to customer
        }

        return response()->json(['success' => true]);
    }

    /**
     * Handle driver disconnected during active ride
     */
    protected function handleDriverDisconnectedEvent(array $data)
    {
        $rideId = $data['ride_id'] ?? null;
        $driverId = $data['driver_id'] ?? null;

        if (!$rideId || !$driverId) {
            return response()->json(['success' => false, 'message' => 'Missing parameters'], 400);
        }

        $trip = TripRequest::find($rideId);

        if (!$trip) {
            return response()->json(['success' => false, 'message' => 'Ride not found'], 404);
        }

        // Log the disconnection
        Log::warning('Driver disconnected during ride', [
            'ride_id' => $rideId,
            'driver_id' => $driverId,
            'ride_status' => $trip->current_status
        ]);

        // TODO: Implement reconnection grace period
        // TODO: If driver doesn't reconnect within X minutes, handle accordingly

        return response()->json(['success' => true]);
    }

    /**
     * Health check endpoint for Node.js to verify Laravel API is reachable
     *
     * GET /api/internal/health
     */
    public function health()
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'laravel-api',
            'timestamp' => now()->toIso8601String()
        ]);
    }
}
