<?php

namespace Modules\TripManagement\Http\Controllers\Api\Driver;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\TripManagement\Entities\TripRequest;
use Modules\TripManagement\Entities\TripRoutePoint;

class LocationController extends Controller
{
    /**
     * Store driver location update
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'ride_id' => 'required|uuid',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'speed' => 'nullable|numeric|min:0',
            'heading' => 'nullable|numeric|between:0,360',
            'accuracy' => 'nullable|numeric|min:0',
            'timestamp' => 'required|integer',
            'event_type' => 'nullable|in:START,PICKUP,DROPOFF,SOS,IDLE',
        ]);

        if ($validator->fails()) {
            return response()->json(
                responseFormatter(
                    constant: DEFAULT_400,
                    errors: errorProcessor($validator)
                ),
                400
            );
        }

        $validated = $validator->validated();
        $user = auth('api')->user();

        // Get active ride for this driver
        $ride = TripRequest::where('id', $validated['ride_id'])
            ->where('driver_id', $user->id)
            ->whereIn('current_status', ['ACCEPTED', 'ONGOING', 'STARTED'])
            ->first();

        if (!$ride) {
            return response()->json(
                responseFormatter(
                    constant: TRIP_REQUEST_404,
                    message: 'No active ride found or you are not the driver for this ride'
                ),
                404
            );
        }

        // Perform sanity checks
        $anomalies = $this->checkLocationAnomaly($ride, $validated);

        if (!empty($anomalies)) {
            Log::warning('Location anomaly detected', [
                'ride_id' => $ride->id,
                'driver_id' => $user->id,
                'anomalies' => $anomalies,
                'location' => $validated,
            ]);

            // Increment anomaly counter
            $ride->increment('anomaly_count');
            $ride->last_anomaly_at = now();
            $ride->save();

            // Optionally reject if configured to do so
            if (config('tracking.reject_anomalous_updates', false)) {
                return response()->json(
                    responseFormatter(
                        constant: DEFAULT_400,
                        message: 'Location update rejected due to anomalies: ' . implode(', ', $anomalies)
                    ),
                    400
                );
            }
        }

        // Update ride metrics
        $this->updateRideMetrics($ride, $validated);

        // Store route point if configured
        if (config('tracking.store_route_points', true)) {
            $shouldStore = true;

            // Check if only storing events
            if (config('tracking.store_events_only', false)) {
                $shouldStore = !empty($validated['event_type']);
            }

            if ($shouldStore) {
                TripRoutePoint::create([
                    'trip_request_id' => $ride->id,
                    'latitude' => $validated['latitude'],
                    'longitude' => $validated['longitude'],
                    'speed' => $validated['speed'] ?? 0,
                    'heading' => $validated['heading'] ?? null,
                    'accuracy' => $validated['accuracy'] ?? null,
                    'timestamp' => $validated['timestamp'],
                    'event_type' => $validated['event_type'] ?? 'NORMAL',
                ]);
            }
        }

        // Handle special events
        if (!empty($validated['event_type'])) {
            $this->handleLocationEvent($ride, $validated['event_type'], $validated);
        }

        // Notify rider (throttled)
        $this->notifyRider($ride, $validated);

        return response()->json(
            responseFormatter(
                constant: DEFAULT_200,
                message: 'Location updated successfully'
            ),
            200
        );
    }

    /**
     * Check for location anomalies
     *
     * @param TripRequest $ride
     * @param array $location
     * @return array List of detected anomalies
     */
    private function checkLocationAnomaly(TripRequest $ride, array $location): array
    {
        $anomalies = [];

        // Check timestamp - not too far in the future
        $maxFutureOffset = config('tracking.max_future_timestamp_offset', 60);
        if ($location['timestamp'] > time() + $maxFutureOffset) {
            $anomalies[] = 'future_timestamp';
        }

        // Check speed
        if (isset($location['speed'])) {
            $maxSpeed = config('tracking.max_speed_ms', 55.5); // m/s
            if ($location['speed'] > $maxSpeed) {
                $anomalies[] = 'excessive_speed';
            }
        }

        // Check jump distance (teleportation)
        if ($ride->last_latitude && $ride->last_longitude && $ride->last_location_timestamp) {
            $distance = haversineDistance(
                $ride->last_latitude,
                $ride->last_longitude,
                $location['latitude'],
                $location['longitude']
            );

            $timeDiff = $location['timestamp'] - $ride->last_location_timestamp;
            $maxJumpMeters = config('tracking.max_jump_meters', 1000);
            $maxJumpTime = config('tracking.max_jump_time_seconds', 15);

            // Suspicious if moved more than max distance in less than max time
            if ($distance > $maxJumpMeters && $timeDiff < $maxJumpTime) {
                $anomalies[] = 'teleport';
            }

            // Also check if speed derived from distance/time is reasonable
            if ($timeDiff > 0) {
                $derivedSpeed = $distance / $timeDiff; // m/s
                $maxSpeed = config('tracking.max_speed_ms', 55.5);
                if ($derivedSpeed > $maxSpeed) {
                    $anomalies[] = 'impossible_speed';
                }
            }
        }

        return $anomalies;
    }

    /**
     * Update ride metrics based on new location
     *
     * @param TripRequest $ride
     * @param array $location
     * @return void
     */
    private function updateRideMetrics(TripRequest $ride, array $location): void
    {
        // Calculate distance increment
        $distanceAdded = 0;
        if ($ride->last_latitude && $ride->last_longitude) {
            $distanceAdded = haversineDistance(
                $ride->last_latitude,
                $ride->last_longitude,
                $location['latitude'],
                $location['longitude']
            );
        }

        // Calculate duration increment
        $durationAdded = 0;
        if ($ride->last_location_timestamp) {
            $durationAdded = max(0, $location['timestamp'] - $ride->last_location_timestamp);
        }

        // Update ride
        $ride->update([
            'total_distance' => $ride->total_distance + $distanceAdded,
            'total_duration' => $ride->total_duration + $durationAdded,
            'last_latitude' => $location['latitude'],
            'last_longitude' => $location['longitude'],
            'last_location_timestamp' => $location['timestamp'],
            'current_speed' => $location['speed'] ?? 0,
        ]);
    }

    /**
     * Handle special location events
     *
     * @param TripRequest $ride
     * @param string $eventType
     * @param array $location
     * @return void
     */
    private function handleLocationEvent(TripRequest $ride, string $eventType, array $location): void
    {
        switch ($eventType) {
            case 'START':
                // Trip started - update status if not already started
                if (!in_array($ride->current_status, ['STARTED', 'ONGOING'])) {
                    $ride->update([
                        'current_status' => 'STARTED',
                        'trip_start_time' => now(),
                    ]);

                    // You can fire an event here if needed
                    // event(new DriverTripStartedEvent($ride));
                }
                break;

            case 'PICKUP':
                // Driver arrived at pickup location
                if ($ride->current_status === 'ACCEPTED') {
                    $ride->update([
                        'current_status' => 'ARRIVED',
                    ]);
                }
                break;

            case 'DROPOFF':
                // Trip completed - don't auto-complete, but log it
                Log::info('Driver reported dropoff', [
                    'ride_id' => $ride->id,
                    'location' => $location,
                ]);
                break;

            case 'SOS':
                // Emergency situation
                $this->handleSOS($ride, $location);
                break;

            case 'IDLE':
                // Driver is idle/stopped
                Log::info('Driver idle', [
                    'ride_id' => $ride->id,
                    'duration' => $location['timestamp'] - $ride->last_location_timestamp,
                ]);
                break;
        }
    }

    /**
     * Handle SOS event
     *
     * @param TripRequest $ride
     * @param array $location
     * @return void
     */
    private function handleSOS(TripRequest $ride, array $location): void
    {
        Log::critical('SOS activated', [
            'ride_id' => $ride->id,
            'driver_id' => $ride->driver_id,
            'customer_id' => $ride->customer_id,
            'location' => $location,
        ]);

        // Mark ride with SOS flag (you may want to add this column)
        // $ride->update(['sos_activated' => true]);

        // Notify admin/support team
        // You can implement this based on your notification system
        // event(new SOSActivatedEvent($ride, $location));
    }

    /**
     * Notify rider of driver location update
     *
     * @param TripRequest $ride
     * @param array $location
     * @return void
     */
    private function notifyRider(TripRequest $ride, array $location): void
    {
        // Throttle notifications to avoid overwhelming the rider
        $cacheKey = "rider_notified_{$ride->id}";
        $notifyInterval = config('tracking.notify_rider_interval', 10);

        if (!cache()->has($cacheKey)) {
            // Real-time location updates are now handled by Node.js WebSocket service
            // via the Redis pub/sub bridge in RealtimeEventPublisher
            // This endpoint primarily serves to store route history for business logic

            // Set cache to throttle next notification
            cache()->put($cacheKey, true, $notifyInterval);
        }
    }

    /**
     * Calculate estimated time of arrival (simplified)
     *
     * @param TripRequest $ride
     * @param array $location
     * @return int|null ETA in minutes
     */
    private function calculateETA(TripRequest $ride, array $location): ?int
    {
        // This is a simplified version
        // In production, you'd use routing API to get accurate ETA

        $destination = null;

        // Determine destination based on ride status
        if ($ride->current_status === 'ACCEPTED') {
            // Going to pickup
            $destination = $ride->coordinates?->pickup_coordinates;
        } elseif (in_array($ride->current_status, ['STARTED', 'ONGOING'])) {
            // Going to destination
            $destination = $ride->coordinates?->destination_coordinates;
        }

        if (!$destination) {
            return null;
        }

        // Calculate distance to destination
        $distance = haversineDistance(
            $location['latitude'],
            $location['longitude'],
            $destination->latitude,
            $destination->longitude
        );

        // Estimate speed (use current speed or average city speed)
        $speed = $location['speed'] ?? 8.33; // Default: ~30 km/h = 8.33 m/s

        if ($speed <= 0) {
            $speed = 8.33; // Use default if stopped
        }

        // Calculate time in seconds, then convert to minutes
        $timeSeconds = $distance / $speed;
        $timeMinutes = ceil($timeSeconds / 60);

        return $timeMinutes;
    }

    /**
     * Get current ride location history
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function history(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ride_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json(
                responseFormatter(
                    constant: DEFAULT_400,
                    errors: errorProcessor($validator)
                ),
                400
            );
        }

        $user = auth('api')->user();

        // Verify this is the driver's ride
        $ride = TripRequest::where('id', $request->ride_id)
            ->where('driver_id', $user->id)
            ->first();

        if (!$ride) {
            return response()->json(
                responseFormatter(constant: TRIP_REQUEST_404),
                404
            );
        }

        // Get route points
        $routePoints = TripRoutePoint::where('trip_request_id', $ride->id)
            ->orderBy('timestamp', 'asc')
            ->get()
            ->map(function ($point) {
                return [
                    'latitude' => $point->latitude,
                    'longitude' => $point->longitude,
                    'speed' => $point->speed,
                    'timestamp' => $point->timestamp,
                    'event_type' => $point->event_type,
                ];
            });

        return response()->json(
            responseFormatter(
                constant: DEFAULT_200,
                content: [
                    'ride_id' => $ride->id,
                    'total_distance' => $ride->total_distance,
                    'total_duration' => $ride->total_duration,
                    'route_points' => $routePoints,
                ]
            ),
            200
        );
    }
}
