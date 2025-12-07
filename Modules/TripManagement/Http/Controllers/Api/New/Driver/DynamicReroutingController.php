<?php

namespace Modules\TripManagement\Http\Controllers\Api\New\Driver;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Modules\TripManagement\Entities\TripRequest;
use Modules\TripManagement\Service\DynamicReroutingService;

class DynamicReroutingController extends Controller
{
    private DynamicReroutingService $reroutingService;

    public function __construct(DynamicReroutingService $reroutingService)
    {
        $this->reroutingService = $reroutingService;
    }

    /**
     * Check driver position and return new route if deviation detected
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkDeviation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required|integer|exists:trip_requests,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'deviation_threshold' => 'nullable|numeric|min:10|max:1000', // meters
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $tripRequestId = $request->input('trip_request_id');
            $currentPosition = new Point($request->input('latitude'), $request->input('longitude'));
            $threshold = $request->input('deviation_threshold');

            // Verify driver owns this trip
            $tripRequest = TripRequest::find($tripRequestId);
            if ($tripRequest->driver_id !== auth()->id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to trip'
                ], 403);
            }

            // Check for deviation and get new route if needed
            $result = $this->reroutingService->checkAndReroute(
                $tripRequestId,
                $currentPosition,
                $threshold
            );

            if ($result === null) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Driver is on route',
                    'data' => [
                        'reroute_needed' => false,
                        'current_position' => [
                            'latitude' => $currentPosition->latitude,
                            'longitude' => $currentPosition->longitude
                        ]
                    ]
                ]);
            }

            // Update trip request with new route
            $tripRequest->encoded_polyline = $result['route']['encoded_polyline'];
            $tripRequest->save();

            return response()->json([
                'status' => 'success',
                'message' => 'New optimized route provided',
                'data' => [
                    'reroute_needed' => true,
                    'route' => $result['route'],
                    'alternatives_count' => count($result['alternatives']),
                    'rerouted_at' => $result['rerouted_at']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in checkDeviation API', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to check route deviation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manually request a new optimized route
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function requestNewRoute(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required|integer|exists:trip_requests,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $tripRequestId = $request->input('trip_request_id');
            $currentPosition = new Point($request->input('latitude'), $request->input('longitude'));

            // Verify driver owns this trip
            $tripRequest = TripRequest::with('coordinate')->find($tripRequestId);
            if ($tripRequest->driver_id !== auth()->id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to trip'
                ], 403);
            }

            // Request new optimized route
            $result = $this->reroutingService->requestOptimizedRoute(
                $tripRequest,
                $currentPosition
            );

            if ($result === null) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to get optimized route'
                ], 500);
            }

            // Update trip request with new route
            $tripRequest->encoded_polyline = $result['route']['encoded_polyline'];
            $tripRequest->save();

            return response()->json([
                'status' => 'success',
                'message' => 'New route generated successfully',
                'data' => [
                    'route' => $result['route'],
                    'alternatives' => $result['alternatives'],
                    'rerouted_at' => $result['rerouted_at']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in requestNewRoute API', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate new route',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get route alternatives for current trip
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getRouteAlternatives(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required|integer|exists:trip_requests,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $tripRequestId = $request->input('trip_request_id');
            $currentPosition = new Point($request->input('latitude'), $request->input('longitude'));

            // Verify driver owns this trip
            $tripRequest = TripRequest::with('coordinate')->find($tripRequestId);
            if ($tripRequest->driver_id !== auth()->id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to trip'
                ], 403);
            }

            // Get all route alternatives
            $result = $this->reroutingService->requestOptimizedRoute(
                $tripRequest,
                $currentPosition
            );

            if ($result === null) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to get route alternatives'
                ], 500);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Route alternatives retrieved successfully',
                'data' => [
                    'recommended_route' => $result['route'], // Shortest by duration
                    'all_routes' => $result['alternatives'],
                    'routes_count' => count($result['alternatives'])
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getRouteAlternatives API', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get route alternatives',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
