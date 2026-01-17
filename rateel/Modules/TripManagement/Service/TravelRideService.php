<?php

namespace Modules\TripManagement\Service;

use App\Events\RideRequestEvent;
use App\Helpers\CoordinateHelper;
use App\Jobs\SendPushNotificationJob;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Modules\AdminModule\Entities\AdminNotification;
use Modules\FareManagement\Service\Interface\TripFareServiceInterface;
use Modules\TripManagement\Entities\TripRequest;
use Modules\TripManagement\Entities\TripRequestCoordinate;
use Modules\TripManagement\Entities\TripStatus;
use Modules\TripManagement\Service\Interface\TravelRideServiceInterface;
use Modules\TripManagement\Service\Interface\TripRequestServiceInterface;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Entities\UserLastLocation;
use Modules\VehicleManagement\Entities\VehicleCategory;

class TravelRideService implements TravelRideServiceInterface
{
    protected TripRequestServiceInterface $tripRequestService;
    protected TripFareServiceInterface $tripFareService;

    // Travel-specific configuration
    protected const TRAVEL_SEARCH_RADIUS_KM = 30;
    protected const TRAVEL_TIMEOUT_MINUTES = 5;

    public function __construct(
        TripRequestServiceInterface $tripRequestService,
        TripFareServiceInterface $tripFareService
    ) {
        $this->tripRequestService = $tripRequestService;
        $this->tripFareService = $tripFareService;
    }

    /**
     * Create a new travel ride request
     */
    public function createTravelRequest(Request $request, array $pickupCoordinates): TripRequest
    {
        DB::beginTransaction();
        try {
            // Calculate fixed price (no surge for travel)
            $fixedPrice = $this->calculateTravelPrice(
                $request->estimated_distance,
                $request->vehicle_category_id,
                $request->zone_id
            );

            // Create trip request with travel fields
            $tripData = [
                'customer_id' => auth('api')->id(),
                'zone_id' => $request->zone_id,
                'vehicle_category_id' => $request->vehicle_category_id,
                'type' => RIDE_REQUEST,
                'current_status' => PENDING,
                'payment_method' => $request->payment_method ?? 'cash',
                'estimated_fare' => $fixedPrice,
                'actual_fare' => $fixedPrice,
                'estimated_distance' => $request->estimated_distance,
                'note' => $request->note,
                // Travel-specific fields
                'is_travel' => true,
                'fixed_price' => $fixedPrice,
                'travel_date' => $request->travel_date,
                'travel_passengers' => $request->travel_passengers,
                'travel_luggage' => $request->travel_luggage,
                'travel_notes' => $request->travel_notes,
                'travel_status' => 'pending',
            ];

            $trip = TripRequest::create($tripData);

            // Create coordinates
            $pickupPoint = CoordinateHelper::createPointFromArray($pickupCoordinates);
            $destinationCoordinates = is_array($request->destination_coordinates)
                ? $request->destination_coordinates
                : json_decode($request->destination_coordinates, true);
            $destinationPoint = CoordinateHelper::createPointFromArray($destinationCoordinates);

            TripRequestCoordinate::create([
                'trip_request_id' => $trip->id,
                'pickup_coordinates' => $pickupPoint,
                'destination_coordinates' => $destinationPoint,
                'pickup_address' => $request->pickup_address,
                'destination_address' => $request->destination_address,
            ]);

            // Create trip status
            TripStatus::create([
                'trip_request_id' => $trip->id,
                'pending' => now(),
            ]);

            DB::commit();

            // Dispatch to VIP drivers
            $this->dispatchTravelRequest($trip->id);

            Log::info('Travel request created', [
                'trip_id' => $trip->id,
                'fixed_price' => $fixedPrice,
                'travel_date' => $request->travel_date,
            ]);

            return $trip->fresh(['coordinate', 'vehicleCategory', 'customer']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create travel request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Get available VIP drivers for travel request
     * 
     * CRITICAL: Only drivers with travel_status = 'approved' can receive travel bookings
     * This prevents:
     * - Unqualified travel drivers
     * - Fraud and VIP abuse
     * - Bad vehicles/service quality
     */
    public function getAvailableVipDrivers(float $lat, float $lng, float $radiusKm = 30): Collection
    {
        // Get VIP category IDs
        $vipCategoryIds = VehicleCategory::where('category_level', VehicleCategory::LEVEL_VIP)
            ->where('is_active', true)
            ->pluck('id');

        if ($vipCategoryIds->isEmpty()) {
            Log::warning('No active VIP categories found for travel dispatch');
            return collect();
        }

        // Find online VIP drivers within radius WITH APPROVED TRAVEL STATUS
        $drivers = UserLastLocation::query()
            ->whereHas('user', function ($q) use ($vipCategoryIds) {
                $q->where('user_type', 'driver')
                    ->where('is_active', true)
                    // Must have VIP vehicle
                    ->whereHas('vehicle', function ($vq) use ($vipCategoryIds) {
                        $vq->whereIn('category_id', $vipCategoryIds);
                    })
                    // Must be online and available
                    ->whereHas('driverDetails', function ($dq) {
                        $dq->where('is_online', true)
                            ->where('availability_status', 'available')
                            // CRITICAL: Only travel-approved drivers
                            ->where('travel_status', 'approved');
                    });
            })
            ->selectRaw("
                *,
                ST_Distance_Sphere(
                    location,
                    ST_GeomFromText('POINT({$lng} {$lat})', 4326)
                ) / 1000 as distance_km
            ")
            ->havingRaw('distance_km <= ?', [$radiusKm])
            ->orderBy('distance_km')
            ->limit(50)
            ->with(['user.vehicle', 'user.driverDetails'])
            ->get();

        Log::info('Travel dispatch - found approved drivers', [
            'lat' => $lat,
            'lng' => $lng,
            'radius_km' => $radiusKm,
            'approved_drivers_count' => $drivers->count(),
        ]);

        return $drivers;
    }

    /**
     * Dispatch travel request to VIP drivers
     */
    public function dispatchTravelRequest(string $tripId): bool
    {
        $trip = TripRequest::with(['coordinate', 'vehicleCategory'])->find($tripId);

        if (!$trip || !$trip->isTravel()) {
            Log::warning('Invalid travel request for dispatch', ['trip_id' => $tripId]);
            return false;
        }

        // Use helper methods to get correct coordinates (fixes Eloquent Spatial WKB parsing bug)
        $pickupCoords = $trip->coordinate->getPickupLatLng();
        $pickupLat = $pickupCoords[0];
        $pickupLng = $pickupCoords[1];
        $destCoords = $trip->coordinate->getDestinationLatLng();

        // Get nearby VIP drivers
        $vipDrivers = $this->getAvailableVipDrivers(
            $pickupLat,
            $pickupLng,
            env('TRAVEL_SEARCH_RADIUS_KM', self::TRAVEL_SEARCH_RADIUS_KM)
        );

        if ($vipDrivers->isEmpty()) {
            Log::warn('No VIP drivers available for travel request', ['trip_id' => $tripId]);
            // Still mark as dispatched so timeout handler can notify admin
            $trip->update([
                'travel_dispatched_at' => now(),
                'travel_status' => 'pending',
            ]);
            return false;
        }

        // Update trip dispatch time
        $trip->update([
            'travel_dispatched_at' => now(),
            'travel_status' => 'pending',
        ]);

        // Publish to Redis for Node.js realtime service
        $rideData = [
            'rideId' => $trip->id,
            'isTravel' => true,
            'pickupLatitude' => $pickupLat,
            'pickupLongitude' => $pickupLng,
            'destinationLatitude' => $destCoords[0],
            'destinationLongitude' => $destCoords[1],
            'vehicleCategoryId' => $trip->vehicle_category_id,
            'categoryLevel' => VehicleCategory::LEVEL_VIP,
            'customerId' => $trip->customer_id,
            'estimatedFare' => $trip->fixed_price,
            'fixedPrice' => $trip->fixed_price,
            'travelDate' => $trip->travel_date?->toISOString(),
            'travelPassengers' => $trip->travel_passengers,
            'travelLuggage' => $trip->travel_luggage,
            'travelNotes' => $trip->travel_notes,
            'travelRadius' => env('TRAVEL_SEARCH_RADIUS_KM', self::TRAVEL_SEARCH_RADIUS_KM),
        ];

        try {
            Redis::publish('ride.created', json_encode($rideData));
            Log::info('Travel request dispatched to realtime service', [
                'trip_id' => $tripId,
                'vip_drivers_count' => $vipDrivers->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to publish travel request to Redis', [
                'trip_id' => $tripId,
                'error' => $e->getMessage(),
            ]);
        }

        // Send push notifications to VIP drivers
        foreach ($vipDrivers as $driverLocation) {
            $driver = $driverLocation->user;
            if ($driver && $driver->fcm_token) {
                dispatch(new SendPushNotificationJob(
                    $driver->fcm_token,
                    'New Travel Ride Request',
                    "Travel ride available for {$trip->fixed_price} EGP - " .
                    ($trip->travel_date ? $trip->travel_date->format('M d, H:i') : 'Now'),
                    [
                        'type' => 'travel_ride_request',
                        'trip_id' => $trip->id,
                        'fixed_price' => $trip->fixed_price,
                    ]
                ));
            }
        }

        return true;
    }

    /**
     * Handle travel request timeout (no driver accepted)
     */
    public function handleTravelTimeout(string $tripId): void
    {
        $trip = TripRequest::find($tripId);

        if (!$trip || !$trip->isTravel() || $trip->travel_status !== 'pending') {
            return;
        }

        // Check if actually timed out
        $timeoutMinutes = env('TRAVEL_TIMEOUT_MINUTES', self::TRAVEL_TIMEOUT_MINUTES);
        $dispatchedAt = $trip->travel_dispatched_at;

        if ($dispatchedAt && $dispatchedAt->diffInMinutes(now()) < $timeoutMinutes) {
            return; // Not yet timed out
        }

        // Mark as expired
        $trip->update(['travel_status' => 'expired']);

        // Create admin notification
        AdminNotification::create([
            'model' => 'trip_request',
            'model_id' => $trip->id,
            'message' => 'travel_request_no_driver',
        ]);

        Log::warning('Travel request expired - no driver accepted', [
            'trip_id' => $tripId,
            'dispatched_at' => $dispatchedAt,
        ]);

        // Notify customer
        if ($trip->customer && $trip->customer->fcm_token) {
            dispatch(new SendPushNotificationJob(
                $trip->customer->fcm_token,
                'Travel Request Update',
                'No drivers are currently available for your travel request. Our team will assist you shortly.',
                ['type' => 'travel_expired', 'trip_id' => $trip->id]
            ));
        }
    }

    /**
     * Get pending travel requests for admin
     */
    public function getPendingTravelRequests(): Collection
    {
        return TripRequest::with(['customer', 'vehicleCategory', 'coordinate', 'zone'])
            ->where('is_travel', true)
            ->whereIn('travel_status', ['pending', 'expired'])
            ->where('current_status', PENDING)
            ->orderBy('travel_dispatched_at', 'asc')
            ->get();
    }

    /**
     * Manually assign a VIP driver to a travel request (admin action)
     */
    public function assignDriverToTravel(string $tripId, string $driverId): bool
    {
        DB::beginTransaction();
        try {
            $trip = TripRequest::find($tripId);
            $driver = User::with(['vehicle', 'driverDetails'])->find($driverId);

            if (!$trip || !$trip->isTravel()) {
                throw new \Exception('Invalid travel request');
            }

            if (!$driver || $driver->user_type !== 'driver') {
                throw new \Exception('Invalid driver');
            }

            // Verify driver is VIP
            $driverCategory = $driver->vehicle?->category;
            if (!$driverCategory || $driverCategory->category_level < VehicleCategory::LEVEL_VIP) {
                throw new \Exception('Driver must be VIP category');
            }

            // CRITICAL: Verify driver has approved travel status
            $driverDetails = $driver->driverDetails;
            if (!$driverDetails || $driverDetails->travel_status !== 'approved') {
                throw new \Exception('Driver must have approved travel status. Current status: ' . ($driverDetails->travel_status ?? 'none'));
            }

            // Assign driver
            $trip->update([
                'driver_id' => $driverId,
                'vehicle_id' => $driver->vehicle?->id,
                'current_status' => ACCEPTED,
                'accepted_by' => 'admin',
                'travel_status' => 'accepted',
            ]);

            // Update trip status
            $trip->tripStatus()->update(['accepted' => now()]);

            // Update driver availability
            $driver->driverDetails()->update(['availability_status' => 'on_trip']);

            DB::commit();

            // Notify driver
            if ($driver->fcm_token) {
                dispatch(new SendPushNotificationJob(
                    $driver->fcm_token,
                    'Travel Ride Assigned',
                    "You have been assigned a travel ride for {$trip->fixed_price} EGP",
                    ['type' => 'travel_assigned', 'trip_id' => $trip->id]
                ));
            }

            // Notify customer
            if ($trip->customer && $trip->customer->fcm_token) {
                dispatch(new SendPushNotificationJob(
                    $trip->customer->fcm_token,
                    'Driver Assigned',
                    'A driver has been assigned to your travel request.',
                    ['type' => 'driver_assigned', 'trip_id' => $trip->id]
                ));
            }

            Log::info('Travel request manually assigned', [
                'trip_id' => $tripId,
                'driver_id' => $driverId,
                'assigned_by' => auth()->id(),
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to assign driver to travel request', [
                'trip_id' => $tripId,
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Cancel a travel request
     */
    public function cancelTravelRequest(string $tripId, ?string $reason = null): bool
    {
        $trip = TripRequest::find($tripId);

        if (!$trip || !$trip->isTravel()) {
            return false;
        }

        $trip->update([
            'current_status' => CANCELLED,
            'travel_status' => 'cancelled',
            'trip_cancellation_reason' => $reason,
        ]);

        // Notify customer
        if ($trip->customer && $trip->customer->fcm_token) {
            dispatch(new SendPushNotificationJob(
                $trip->customer->fcm_token,
                'Travel Request Cancelled',
                $reason ?? 'Your travel request has been cancelled.',
                ['type' => 'travel_cancelled', 'trip_id' => $trip->id]
            ));
        }

        Log::info('Travel request cancelled', [
            'trip_id' => $tripId,
            'reason' => $reason,
        ]);

        return true;
    }

    /**
     * Calculate fixed price for travel trip
     * No surge pricing for travel trips
     */
    public function calculateTravelPrice(
        float $distance,
        string $vehicleCategoryId,
        string $zoneId
    ): float {
        // Get trip fare for the category and zone
        $tripFare = $this->tripFareService->findOneBy(criteria: [
            'vehicle_category_id' => $vehicleCategoryId,
            'zone_id' => $zoneId,
        ]);

        if (!$tripFare) {
            // Fallback to base calculation
            $basePrice = 50; // Default base
            $perKm = 5; // Default per km
            return round($basePrice + ($distance * $perKm), 2);
        }

        // Calculate without surge
        $baseFare = $tripFare->base_fare ?? 0;
        $perKmFare = $tripFare->base_fare_per_km ?? 0;
        $perMinFare = $tripFare->waiting_fee_per_min ?? 0;

        // Estimate time (assume 30 km/h average for travel)
        $estimatedMinutes = ($distance / 30) * 60;

        $price = $baseFare + ($distance * $perKmFare) + ($estimatedMinutes * $perMinFare);

        // Apply VAT if configured
        $vatPercent = (float) get_cache('vat_percent') ?? 0;
        if ($vatPercent > 0) {
            $price += ($price * $vatPercent / 100);
        }

        return round($price, 2);
    }
}
