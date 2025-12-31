<?php

namespace Modules\BusinessManagement\Http\Controllers\Api\New\Driver;

use DateTimeZone;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Modules\BusinessManagement\Service\Interface\BusinessSettingServiceInterface;
use Modules\BusinessManagement\Service\Interface\CancellationReasonServiceInterface;
use Modules\BusinessManagement\Service\Interface\ParcelCancellationReasonServiceInterface;
use Modules\BusinessManagement\Service\Interface\QuestionAnswerServiceInterface;
use Modules\BusinessManagement\Service\Interface\SafetyAlertReasonServiceInterface;
use Modules\BusinessManagement\Service\Interface\SafetyPrecautionServiceInterface;
use Modules\BusinessManagement\Service\Interface\SettingServiceInterface;
use Modules\TripManagement\Service\Interface\TripRequestServiceInterface;
use Modules\UserManagement\Service\Interface\UserLastLocationServiceInterface;
use Modules\ZoneManagement\Service\Interface\ZoneServiceInterface;

class ConfigController extends Controller
{
    protected $businessSettingService;
    protected $settingService;
    protected $cancellationReasonService;
    protected $parcelCancellationReasonService;
    protected $zoneService;
    protected $userLastLocationService;
    protected $tripRequestService;
    protected $questionAnswerService;
    protected $safetyAlertReasonService;

    protected $safetyPrecautionService;

    public function __construct(BusinessSettingServiceInterface    $businessSettingService, SettingServiceInterface $settingService,
                                CancellationReasonServiceInterface $cancellationReasonService, ParcelCancellationReasonServiceInterface $parcelCancellationReasonService,
                                ZoneServiceInterface               $zoneService, UserLastLocationServiceInterface $userLastLocationService,
                                TripRequestServiceInterface        $tripRequestService, QuestionAnswerServiceInterface $questionAnswerService,
                                SafetyAlertReasonServiceInterface  $safetyAlertReasonService, SafetyPrecautionServiceInterface $safetyPrecautionService)
    {
        $this->businessSettingService = $businessSettingService;
        $this->settingService = $settingService;
        $this->cancellationReasonService = $cancellationReasonService;
        $this->parcelCancellationReasonService = $parcelCancellationReasonService;
        $this->zoneService = $zoneService;
        $this->userLastLocationService = $userLastLocationService;
        $this->tripRequestService = $tripRequestService;
        $this->questionAnswerService = $questionAnswerService;
        $this->safetyAlertReasonService = $safetyAlertReasonService;
        $this->safetyPrecautionService = $safetyPrecautionService;
    }

    public function configuration()
    {
        $info = $this->businessSettingService->getAll(limit: 999, offset: 1);
        $loyaltyPoints = $info
            ->where('key_name', 'loyalty_points')
            ->firstWhere('settings_type', 'driver_settings')?->value;
        $appVersions = $this->businessSettingService->getBy(criteria: ['settings_type' => APP_VERSION]);
        $dataValues = $this->settingService->getBy(criteria: ['settings_type' => SMS_CONFIG]);
        if ($dataValues->where('live_values.status', 1)->isEmpty()) {
            $smsConfiguration = 0;
        } else {
            $smsConfiguration = 1;
        }
        $configs = [
            'is_demo' => (bool)env('APP_MODE') != 'live',
            'maintenance_mode' => checkMaintenanceMode(),
            'required_pin_to_start_trip' => (bool)$info->firstWhere('key_name', 'required_pin_to_start_trip')?->value ?? false,
            'add_intermediate_points' => (bool)$info->firstWhere('key_name', 'add_intermediate_points')?->value ?? false,
            'business_name' => $info->firstWhere('key_name', 'business_name')?->value ?? null,
            'logo' => $info->firstWhere('key_name', 'header_logo')?->value ?? null,
            'bid_on_fare' => (bool)$info->firstWhere('key_name', 'bid_on_fare')?->value ?? 0,
            'driver_completion_radius' => $info->firstWhere('key_name', 'driver_completion_radius')?->value ?? 10,
            'country_code' => $info->firstWhere('key_name', 'country_code')?->value ?? null,
            'business_address' => $info->firstWhere('key_name', 'business_address')->value ?? null,
            'business_contact_phone' => $info->firstWhere('key_name', 'business_contact_phone')?->value ?? null,
            'business_contact_email' => $info->firstWhere('key_name', 'business_contact_email')?->value ?? null,
            'business_support_phone' => $info->firstWhere('key_name', 'business_support_phone')?->value ?? null,
            'business_support_email' => $info->firstWhere('key_name', 'business_support_email')?->value ?? null,
            'conversion_status' => (bool)($loyaltyPoints['status'] ?? false),
            'conversion_rate' => (double)($loyaltyPoints['points'] ?? 0),
            'base_url' => url('/') . '/api/v1/',
            'websocket_url' => $info->firstWhere('key_name', 'websocket_url')?->value ?? null,
            'websocket_port' => (string)$info->firstWhere('key_name', 'websocket_port')?->value ?? 6001,
            'websocket_key' => env('PUSHER_APP_KEY'),
            'websocket_scheme' => env('PUSHER_SCHEME'),
            'review_status' => (bool)$info->firstWhere('key_name', DRIVER_REVIEW)?->value ?? null,
            'level_status' => (bool)$info->firstWhere('key_name', DRIVER_LEVEL)?->value ?? null,
            'image_base_url' => [
                'profile_image_customer' => asset('storage/app/public/customer/profile'),
                'profile_image_admin' => asset('storage/app/public/employee/profile'),
                'banner' => asset('storage/app/public/promotion/banner'),
                'vehicle_category' => asset('storage/app/public/vehicle/category'),
                'vehicle_model' => asset('storage/app/public/vehicle/model'),
                'vehicle_brand' => asset('storage/app/public/vehicle/brand'),
                'profile_image' => asset('storage/app/public/driver/profile'),
                'identity_image' => asset('storage/app/public/driver/identity'),
                'documents' => asset('storage/app/public/driver/document'),
                'pages' => asset('storage/app/public/business/pages'),
                'conversation' => asset('storage/app/public/conversation'),
                'parcel' => asset('storage/app/public/parcel/category'),
            ],
            'otp_resend_time' => (int)$info->firstWhere('key_name', 'otp_resend_time')?->value ?? 60,
            'currency_decimal_point' => $info->firstWhere('key_name', 'currency_decimal_point')?->value ?? null,
            'currency_code' => $info->firstWhere('key_name', 'currency_code')?->value ?? null,
            'currency_symbol' => $info->firstWhere('key_name', 'currency_symbol')->value ?? '$',
            'currency_symbol_position' => $info->firstWhere('key_name', 'currency_symbol_position')?->value ?? null,
            'about_us' => $info->firstWhere('key_name', 'about_us')?->value ?? null,
            'privacy_policy' => $info->firstWhere('key_name', 'privacy_policy')?->value ?? null,
            'terms_and_conditions' => $info->firstWhere('key_name', 'terms_and_conditions')?->value ?? null,
            'legal' => $info->firstWhere('key_name', 'legal')?->value,
            'refund_policy' => $info->firstWhere('key_name', 'refund_policy')?->value,
            'verification' => (bool)$info->firstWhere('key_name', 'driver_verification')?->value ?? 0,
            'sms_verification' => (bool)$info->firstWhere('key_name', 'sms_verification')?->value ?? 0,
            'email_verification' => (bool)$info->firstWhere('key_name', 'email_verification')?->value ?? 0,
            'facebook_login' => (bool)$info->firstWhere('key_name', 'facebook_login')?->value['status'] ?? 0,
            'google_login' => (bool)$info->firstWhere('key_name', 'google_login')?->value['status'] ?? 0,
            'self_registration' => (bool)$info->firstWhere('key_name', 'driver_self_registration')?->value ?? 0,
            'referral_earning_status' => (bool)referralEarningSetting('referral_earning_status', DRIVER)?->value,
            'parcel_return_time_fee_status' => (bool)businessConfig('parcel_return_time_fee_status', PARCEL_SETTINGS)?->value ?? false,
            'return_time_for_driver' => (int)businessConfig('return_time_for_driver', PARCEL_SETTINGS)?->value ?? 0,
            'return_time_type_for_driver' => businessConfig('return_time_type_for_driver', PARCEL_SETTINGS)?->value ?? "day",
            'return_fee_for_driver_time_exceed' => (double)businessConfig('return_fee_for_driver_time_exceed', PARCEL_SETTINGS)?->value ?? 0,
            'app_minimum_version_for_android' => (double)$appVersions->firstWhere('key_name', 'driver_app_version_control_for_android')?->value['minimum_app_version'] ?? 0,
            'app_url_for_android' => $appVersions->firstWhere('key_name', 'driver_app_version_control_for_android')?->value['app_url'] ?? null,
            'app_minimum_version_for_ios' => (double)$appVersions->firstWhere('key_name', 'driver_app_version_control_for_ios')?->value['minimum_app_version'] ?? 0,
            'app_url_for_ios' => $appVersions->firstWhere('key_name', 'driver_app_version_control_for_ios')?->value['app_url'] ?? null,
            'firebase_otp_verification' => (bool)$info->firstWhere('key_name', 'firebase_otp_verification_status')?->value == 1,
            'sms_gateway' => (bool)$smsConfiguration,
            'chatting_setup_status' => (bool)$info->firstWhere('key_name', 'chatting_setup_status')?->value == 1,
            'driver_question_answer_status' => (bool)$info->firstWhere('key_name', 'chatting_setup_status')?->value == 1 && (bool)$info->firstWhere('key_name', 'driver_question_answer_status')?->value == 1,
            'maximum_parcel_request_accept_limit_status_for_driver' => (bool)$info->firstWhere('key_name', 'maximum_parcel_request_accept_limit')?->value['status'] == 1,
            'maximum_parcel_request_accept_limit_for_driver' => (int)$info->firstWhere('key_name', 'maximum_parcel_request_accept_limit')?->value['limit'] ?? 0,
            'parcel_weight_unit' => businessConfig(key: 'parcel_weight_unit', settingsType: PARCEL_SETTINGS)?->value ?? 'kg',
            'safety_feature_status' => (bool)$info->firstWhere('key_name', 'safety_feature_status')?->value == 1,
            'safety_feature_minimum_trip_delay_time' => $info->firstWhere('key_name', 'safety_feature_status')?->value == 1 ? convertTimeToSecond(
                $info->firstWhere('key_name', 'for_trip_delay')?->value['minimum_delay_time'],
                $info->firstWhere('key_name', 'for_trip_delay')?->value['time_format']
            ) : null,
            'safety_feature_minimum_trip_delay_time_type' => $info->firstWhere('key_name', 'safety_feature_status')?->value == 1 ? $info->firstWhere('key_name', 'for_trip_delay')?->value['time_format'] : null,
            'after_trip_completed_safety_feature_active_status' => (bool)$info->firstWhere('key_name', 'safety_feature_status')?->value == 1 && (bool)$info->firstWhere('key_name', 'after_trip_complete')?->value['safety_feature_active_status'] == 1,
            'after_trip_completed_safety_feature_set_time' => $info->firstWhere('key_name', 'after_trip_complete')?->value['safety_feature_active_status'] == 1 ? convertTimeToSecond(
                $info->firstWhere('key_name', 'after_trip_complete')?->value['set_time'],
                $info->firstWhere('key_name', 'after_trip_complete_time_format')?->value
            )
                : null,
            'after_trip_completed_safety_feature_set_time_type' => $info->firstWhere('key_name', 'after_trip_complete')?->value['safety_feature_active_status'] == 1 ? $info->firstWhere('key_name', 'after_trip_complete_time_format')?->value : null,
            'safety_feature_emergency_govt_number' => $info->firstWhere('key_name', 'safety_feature_status')?->value == 1 ? $info->firstWhere('key_name', 'emergency_govt_number_for_call')?->value : null,
            'otp_confirmation_for_trip' => (bool)$info->firstWhere('key_name', 'driver_otp_confirmation_for_trip')?->value == 1,
            'fuel_types' => array_keys(FUEL_TYPES)
        ];

        return response()->json($configs);
    }

    public function cancellationReasonList()
    {
        $ongoingRide = $this->cancellationReasonService->getBy(criteria: ['cancellation_type' => 'ongoing_ride', 'user_type' => 'driver', 'is_active' => 1])->pluck('title')->toArray();
        $acceptedRide = $this->cancellationReasonService->getBy(criteria: ['cancellation_type' => 'accepted_ride', 'user_type' => 'driver', 'is_active' => 1])->pluck('title')->toArray();
        $data = [
            'ongoing_ride' => $ongoingRide,
            'accepted_ride' => $acceptedRide,
        ];
        return response(responseFormatter(DEFAULT_200, $data));
    }

    public function parcelCancellationReasonList()
    {
        $ongoingRide = $this->parcelCancellationReasonService->getBy(criteria: ['cancellation_type' => 'ongoing_ride', 'user_type' => 'driver', 'is_active' => 1])->pluck('title')->toArray();
        $acceptedRide = $this->parcelCancellationReasonService->getBy(criteria: ['cancellation_type' => 'accepted_ride', 'user_type' => 'driver', 'is_active' => 1])->pluck('title')->toArray();
        $data = [
            'ongoing_ride' => $ongoingRide,
            'accepted_ride' => $acceptedRide,
        ];
        return response(responseFormatter(DEFAULT_200, $data));
    }


    public function getZone(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lat' => 'required',
            'lng' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, null, null, null, errorProcessor($validator)), 400);
        }

        $point = new Point($request->lat, $request->lng, 4326);
        $zone = $this->zoneService->getByPoints($point)->where('is_active', 1)->first();

        if ($zone) {
            return response()->json(responseFormatter(DEFAULT_200, $zone), 200);
        }

        return response()->json(responseFormatter(ZONE_RESOURCE_404), 403);
    }

    /**
     * Summary of placeApiAutocomplete
     * @param Request $request
     * @return JsonResponse
     */
    public function placeApiAutocomplete(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'search_text' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, null, null, null, errorProcessor($validator)), 400);
        }

        // Allow manual API key override via request parameter
        $mapKey = $request->input('key') ?? businessConfig(GOOGLE_MAP_API)?->value['map_api_key_server'] ?? null;

        if (empty($mapKey)) {
            \Log::error('GeoLink API key not configured for text search');
            return response()->json(responseFormatter(DEFAULT_200, [
                'predictions' => [],
                'status' => 'ZERO_RESULTS'
            ]), 200);
        }

        // Get zone_id from header or query parameter for filtering
        $zoneId = $request->header('zoneId') ?? $request->input('zoneId') ?? $request->input('zone_id');

        // GeoLink Text Search API
        $response = Http::timeout(30)->get(MAP_API_BASE_URI . '/api/v2/text_search', [
            'query' => $request['search_text'],
            'key' => $mapKey
        ]);

        // Log the response for debugging
        if (!$response->successful()) {
            \Log::error('GeoLink text search API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'search_text' => $request['search_text']
            ]);
        }

        // Transform GeoLink response to match expected format
        $geoLinkData = $response->json();

        // Log the raw response for debugging
        \Log::info('GeoLink text search API response', [
            'http_status' => $response->status(),
            'response' => $geoLinkData,
            'search_text' => $request['search_text'],
            'api_url' => MAP_API_BASE_URI . '/api/v2/text_search',
            'zone_filtering' => !empty($zoneId)
        ]);

        $transformedData = $this->transformTextSearchResponse($geoLinkData, $zoneId);

        return response()->json(responseFormatter(DEFAULT_200, $transformedData), 200);
    }

    public function distanceApi(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'origin_lat' => 'required',
            'origin_lng' => 'required',
            'destination_lat' => 'required',
            'destination_lng' => 'required',
            'mode' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, null, null, null, errorProcessor($validator)), 400);
        }

        // Allow manual API key override via request parameter
        $mapKey = $request->input('key') ?? businessConfig(GOOGLE_MAP_API)?->value['map_api_key_server'] ?? null;
        
        // GeoLink Directions API
        $response = Http::get(MAP_API_BASE_URI . '/api/v2/directions', [
            'origin_latitude' => $request['origin_lat'],
            'origin_longitude' => $request['origin_lng'],
            'destination_latitude' => $request['destination_lat'],
            'destination_longitude' => $request['destination_lng'],
            'key' => $mapKey
        ]);
        
        // Transform GeoLink response to match expected distance matrix format
        $geoLinkData = $response->json();
        $transformedData = $this->transformDirectionsToDistanceMatrix($geoLinkData);

        return response()->json(responseFormatter(DEFAULT_200, $transformedData), 200);
    }

    public function placeApiDetails(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'placeid' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, null, null, null, errorProcessor($validator)), 400);
        }
        
        // Allow manual API key override via request parameter
        $mapKey = $request->input('key') ?? businessConfig(GOOGLE_MAP_API)?->value['map_api_key_server'] ?? null;
        
        if (empty($mapKey)) {
            return response()->json(responseFormatter(DEFAULT_400, null, null, null, ['message' => 'Map API key not configured']), 400);
        }
        
        $placeId = $request['placeid'];
        
        // Try to decode if it's a base64 encoded data
        $decodedData = null;
        $decoded = base64_decode($placeId, true);
        if ($decoded !== false) {
            $jsonData = json_decode($decoded, true);
            if (is_array($jsonData)) {
                $decodedData = $jsonData;
            }
        }
        
        // Determine which API endpoint to use based on available data
        if ($decodedData && isset($decodedData['lat']) && isset($decodedData['lng'])) {
            // Case 1: We have coordinates - use reverse geocode
            $response = Http::timeout(30)->get(MAP_API_BASE_URI . '/api/v2/reverse_geocode', [
                'latitude' => $decodedData['lat'],
                'longitude' => $decodedData['lng'],
                'key' => $mapKey
            ]);
            
            \Log::info('GeoLink place details using reverse_geocode', [
                'lat' => $decodedData['lat'],
                'lng' => $decodedData['lng']
            ]);
            
        } elseif ($decodedData && isset($decodedData['query'])) {
            // Case 2: We have a search query - use text search
            $response = Http::timeout(30)->get(MAP_API_BASE_URI . '/api/v2/text_search', [
                'query' => $decodedData['query'],
                'key' => $mapKey
            ]);
            
            \Log::info('GeoLink place details using text_search', [
                'query' => $decodedData['query']
            ]);
            
        } else {
            // Case 3: Try using place_id directly with geocode endpoint
            $response = Http::timeout(30)->get(MAP_API_BASE_URI . '/api/v2/geocode', [
                'query' => $placeId,  // GeoLink geocode needs 'query' not 'place_id'
                'key' => $mapKey
            ]);
            
            \Log::info('GeoLink place details using geocode with query', [
                'query' => $placeId
            ]);
        }
        
        // Check if HTTP request was successful
        if (!$response->successful()) {
            \Log::error('GeoLink place details API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'placeid' => $placeId,
                'decoded_data' => $decodedData
            ]);
            
            return response()->json(responseFormatter(DEFAULT_400, null, null, null, [
                'message' => 'Failed to fetch place details from map service',
                'status_code' => $response->status(),
                'debug' => [
                    'placeid' => $placeId,
                    'response' => $response->body()
                ]
            ]), 400);
        }
        
        // Transform GeoLink response to match expected place details format
        $geoLinkData = $response->json();
        $transformedData = $this->transformPlaceDetailsResponse($geoLinkData, $placeId);

        return response()->json(responseFormatter(DEFAULT_200, $transformedData), 200);
    }

    public function geocodeApi(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lat' => 'required',
            'lng' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, null, null, null, errorProcessor($validator)), 400);
        }
        
        // Allow manual API key override via request parameter (for testing)
        $mapKey = $request->input('key') ?? businessConfig(GOOGLE_MAP_API)?->value['map_api_key_server'] ?? null;
        
        if (empty($mapKey)) {
            \Log::error('GeoLink API key not configured for reverse geocode');
            return response()->json(responseFormatter(DEFAULT_200, [
                'results' => [],
                'status' => 'ZERO_RESULTS',
                '_debug' => [
                    'error' => 'API key not provided'
                ]
            ]), 200);
        }
        
        // GeoLink Reverse Geocode API
        $response = Http::timeout(30)->get(MAP_API_BASE_URI . '/api/v2/reverse_geocode', [
            'latitude' => $request->lat,
            'longitude' => $request->lng,
            'key' => $mapKey
        ]);
        
        // Log the response for debugging
        if (!$response->successful()) {
            \Log::error('GeoLink reverse geocode API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'lat' => $request->lat,
                'lng' => $request->lng
            ]);
        }
        
        // Transform GeoLink response to match expected geocode format
        $geoLinkData = $response->json();
        
        // Log the raw response for debugging
        \Log::info('GeoLink reverse geocode API response', [
            'http_status' => $response->status(),
            'response' => $geoLinkData,
            'lat' => $request->lat,
            'lng' => $request->lng,
            'api_url' => MAP_API_BASE_URI . '/api/v2/reverse_geocode'
        ]);
        
        if (!isset($geoLinkData['data'])) {
            \Log::warning('GeoLink reverse geocode API returned unexpected response', [
                'response' => $geoLinkData,
                'lat' => $request->lat,
                'lng' => $request->lng
            ]);
        }
        
        $transformedData = $this->transformReverseGeocodeResponse($geoLinkData, $request->lat, $request->lng);
        
        return response()->json(responseFormatter(DEFAULT_200, $transformedData), 200);
    }
    
    /**
     * Transform GeoLink text search response to Google Places format
     * @param array $geoLinkData The raw response from GeoLink API
     * @param string|null $zoneId Optional zone ID to filter results by zone boundaries
     */
    private function transformTextSearchResponse($geoLinkData, $zoneId = null): array
    {
        $predictions = [];
        $filteredCount = 0;

        // Get zone boundaries if zone_id is provided
        $zone = null;
        if (!empty($zoneId)) {
            $zone = $this->zoneService->findOne($zoneId);
            if (!$zone) {
                \Log::warning('Zone not found for autocomplete filtering', ['zone_id' => $zoneId]);
            }
        }

        // Check if response is null or empty
        if (empty($geoLinkData) || !is_array($geoLinkData)) {
            \Log::warning('GeoLink text search returned empty or invalid response', [
                'response' => $geoLinkData
            ]);
            return [
                'predictions' => $predictions,
                'status' => 'ZERO_RESULTS'
            ];
        }

        // Handle GeoLink API v2 response structure
        // Check for different possible response structures
        $data = null;

        if (isset($geoLinkData['data']) && is_array($geoLinkData['data'])) {
            $data = $geoLinkData['data'];
        } elseif (isset($geoLinkData['results']) && is_array($geoLinkData['results'])) {
            // Some APIs return 'results' instead of 'data'
            $data = $geoLinkData['results'];
        } elseif (is_array($geoLinkData) && isset($geoLinkData[0])) {
            // Response might be a direct array of results
            $data = $geoLinkData;
        }

        if ($data && is_array($data)) {
            foreach ($data as $result) {
                // Convert object to array if needed
                if (is_object($result)) {
                    $result = json_decode(json_encode($result), true);
                }

                if (is_array($result)) {
                    // Extract coordinates and identifiers
                    // GeoLink API uses nested location object with 'lat' and 'lng' fields
                    $lat = $result['location']['lat'] ?? $result['lat'] ?? $result['latitude'] ?? null;
                    $lng = $result['location']['lng'] ?? $result['lng'] ?? $result['longitude'] ?? null;
                    $placeId = $result['place_id'] ?? $result['id'] ?? null;
                    $name = $result['short_address'] ?? $result['name'] ?? '';
                    $address = $result['address'] ?? $result['formatted_address'] ?? '';

                    // Zone filtering: Skip results outside the zone
                    if ($zone && $lat && $lng) {
                        try {
                            $point = new Point($lat, $lng, 4326);
                            $isInZone = $this->zoneService->getByPoints($point)
                                ->where('id', $zoneId)
                                ->where('is_active', 1)
                                ->exists();

                            if (!$isInZone) {
                                $filteredCount++;
                                continue; // Skip this result
                            }
                        } catch (\Exception $e) {
                            \Log::warning('Error checking point in zone', [
                                'lat' => $lat,
                                'lng' => $lng,
                                'zone_id' => $zoneId,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }

                    // Create a smart place_id that can be used to retrieve details later
                    if (!$placeId) {
                        if ($lat && $lng) {
                            // Encode coordinates as place_id
                            $placeId = base64_encode(json_encode([
                                'lat' => $lat,
                                'lng' => $lng,
                                'name' => $name,
                                'address' => $address
                            ]));
                        } else {
                            // Last resort: use name/address as query
                            $placeId = base64_encode(json_encode([
                                'query' => $name ?: $address
                            ]));
                        }
                    }

                    $predictions[] = [
                        'place_id' => $placeId,
                        'description' => $address ?: $name,
                        'structured_formatting' => [
                            'main_text' => $name,
                            'secondary_text' => $address
                        ],
                        // Include coordinates for direct use
                        'geometry' => [
                            'location' => [
                                'lat' => $lat,
                                'lng' => $lng
                            ]
                        ]
                    ];
                }
            }
        } else {
            // Log if we couldn't find data in expected format
            \Log::warning('GeoLink text search response structure unexpected', [
                'response_keys' => array_keys($geoLinkData),
                'response' => $geoLinkData
            ]);
        }

        // Log zone filtering results
        if ($zone && $filteredCount > 0) {
            \Log::info('Autocomplete zone filtering applied', [
                'zone_id' => $zoneId,
                'total_results' => count($data ?? []),
                'filtered_out' => $filteredCount,
                'returned' => count($predictions)
            ]);
        }

        return [
            'predictions' => $predictions,
            'status' => !empty($predictions) ? 'OK' : 'ZERO_RESULTS',
            'zone_filtered' => !empty($zone)
        ];
    }
    
    /**
     * Transform GeoLink directions response to Google Distance Matrix format
     */
    private function transformDirectionsToDistanceMatrix($geoLinkData): array
    {
        $distance = 0;
        $duration = 0;
        $status = 'ZERO_RESULTS';
        
        if (isset($geoLinkData['data'])) {
            $data = $geoLinkData['data'];
            $distance = $data['distance'] ?? 0; // in meters
            $duration = $data['duration'] ?? 0; // in seconds
            $status = 'OK';
        }
        
        return [
            'rows' => [
                [
                    'elements' => [
                        [
                            'distance' => [
                                'text' => round($distance / 1000, 2) . ' km',
                                'value' => $distance
                            ],
                            'duration' => [
                                'text' => round($duration / 60) . ' mins',
                                'value' => $duration
                            ],
                            'status' => $status
                        ]
                    ]
                ]
            ],
            'status' => $status
        ];
    }
    
    /**
     * Transform GeoLink geocode response to Google Place Details format
     */
    private function transformPlaceDetailsResponse($geoLinkData, $originalPlaceId = null): array
    {
        $result = [];
        $status = 'NOT_FOUND';
        
        // Check if response is null or empty
        if (empty($geoLinkData) || !is_array($geoLinkData)) {
            return [
                'result' => $result,
                'status' => $status
            ];
        }
        
        // Check for API error responses
        if (isset($geoLinkData['error']) || isset($geoLinkData['message'])) {
            $status = 'ERROR';
            return [
                'result' => $result,
                'status' => $status,
                'error_message' => $geoLinkData['error'] ?? $geoLinkData['message'] ?? 'Unknown error'
            ];
        }
        
        if (isset($geoLinkData['data'])) {
            $data = $geoLinkData['data'];
            
            // Convert object to array if needed
            if (is_object($data)) {
                $data = json_decode(json_encode($data), true);
            }
            
            // Handle array of results (from text_search) - take first result
            if (is_array($data) && isset($data[0]) && is_array($data[0])) {
                $data = $data[0];
            }
            
            if (is_array($data) && !empty($data)) {
                $result = [
                    'place_id' => $originalPlaceId ?? $data['place_id'] ?? $data['id'] ?? '',
                    'name' => $data['name'] ?? '',
                    'formatted_address' => $data['formatted_address'] ?? $data['address'] ?? '',
                    'geometry' => [
                        'location' => [
                            'lat' => $data['lat'] ?? $data['latitude'] ?? 0,
                            'lng' => $data['lng'] ?? $data['longitude'] ?? 0
                        ]
                    ],
                    'address_components' => $data['address_components'] ?? []
                ];
                $status = 'OK';
            }
        }
        
        return [
            'result' => $result,
            'status' => $status
        ];
    }
    
    /**
     * Transform GeoLink reverse geocode response to Google Geocoding format
     */
    private function transformReverseGeocodeResponse($geoLinkData, $lat, $lng): array
    {
        $results = [];
        $status = 'ZERO_RESULTS';
        
        // Check if response is null or empty
        if (empty($geoLinkData) || !is_array($geoLinkData)) {
            \Log::warning('GeoLink reverse geocode returned empty or invalid response', [
                'response' => $geoLinkData
            ]);
            return [
                'results' => $results,
                'status' => $status
            ];
        }
        
        // Handle error responses from GeoLink API
        if (isset($geoLinkData['error']) || isset($geoLinkData['message'])) {
            \Log::warning('GeoLink API returned error', [
                'error' => $geoLinkData['error'] ?? $geoLinkData['message'] ?? 'Unknown error',
                'response' => $geoLinkData
            ]);
            return [
                'results' => $results,
                'status' => 'ERROR'
            ];
        }
        
        // Try different possible response structures
        $data = null;
        
        // Check for 'data' key (most common)
        if (isset($geoLinkData['data'])) {
            $data = $geoLinkData['data'];
        } 
        // Check for 'result' key
        elseif (isset($geoLinkData['result'])) {
            $data = $geoLinkData['result'];
        }
        // Check if data is at root level
        elseif (isset($geoLinkData['address']) || isset($geoLinkData['formatted_address']) || isset($geoLinkData['lat']) || isset($geoLinkData['latitude'])) {
            $data = $geoLinkData;
        }
        
        if ($data !== null) {
            // Convert object to array if needed
            if (is_object($data)) {
                $data = json_decode(json_encode($data), true);
            }
            
            // Handle if data is an array of results
            if (is_array($data) && isset($data[0]) && is_array($data[0])) {
                // Multiple results
                foreach ($data as $item) {
                    if (is_object($item)) {
                        $item = json_decode(json_encode($item), true);
                    }
                    if (is_array($item)) {
                        $address = $item['address'] ?? $item['formatted_address'] ?? '';
                        if (!empty($address) || !empty($item)) {
                            $results[] = [
                                'formatted_address' => $address,
                                'geometry' => [
                                    'location' => [
                                        'lat' => (float) ($item['lat'] ?? ($item['latitude'] ?? $lat)),
                                        'lng' => (float) ($item['lng'] ?? ($item['longitude'] ?? $lng))
                                    ]
                                ],
                                'address_components' => $item['address_components'] ?? [],
                                'place_id' => $item['place_id'] ?? ($item['id'] ?? '')
                            ];
                            $status = 'OK';
                        }
                    }
                }
            } 
            // Handle single result object
            elseif (is_array($data)) {
                $address = $data['address'] ?? $data['formatted_address'] ?? '';
                
                // If address is still empty but we have other location data, try to construct it
                if (empty($address) && (isset($data['lat']) || isset($data['latitude']))) {
                    // Try to get address from components if available
                    if (isset($data['address_components']) && is_array($data['address_components'])) {
                        $addressParts = [];
                        foreach ($data['address_components'] as $component) {
                            if (isset($component['long_name'])) {
                                $addressParts[] = $component['long_name'];
                            }
                        }
                        $address = implode(', ', $addressParts);
                    }
                    
                    // If still empty, create a basic address
                    if (empty($address)) {
                        $address = 'Location at ' . ($data['lat'] ?? $data['latitude']) . ', ' . ($data['lng'] ?? $data['longitude']);
                    }
                }
                
                // Only add result if we have some meaningful data
                if (!empty($address) || !empty($data)) {
                    $results[] = [
                        'formatted_address' => $address,
                        'geometry' => [
                            'location' => [
                                'lat' => (float) ($data['lat'] ?? ($data['latitude'] ?? $lat)),
                                'lng' => (float) ($data['lng'] ?? ($data['longitude'] ?? $lng))
                            ]
                        ],
                        'address_components' => $data['address_components'] ?? [],
                        'place_id' => $data['place_id'] ?? ($data['id'] ?? '')
                    ];
                    $status = 'OK';
                }
            }
        } else {
            // Log if we couldn't find data in expected format
            \Log::warning('GeoLink reverse geocode response structure unexpected', [
                'response_keys' => array_keys($geoLinkData),
                'response' => $geoLinkData
            ]);
        }
        
        return [
            'results' => $results,
            'status' => $status
        ];
    }

    public function getRoutes(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $trip = $this->tripRequestService->findOne(id: $request->trip_request_id, relations: ['coordinate', 'vehicleCategory']);
        if (!$trip) {

            return response()->json(responseFormatter(constant: TRIP_REQUEST_404, errors: errorProcessor($validator)), 403);
        }

        $pickupCoordinates = [
            auth()->user()->lastLocations->latitude,
            auth()->user()->lastLocations->longitude,
        ];

        $intermediateCoordinates = [];
        if ($trip->current_status == ONGOING) {
            $destinationCoordinates = [
                $trip->coordinate->destination_coordinates->latitude,
                $trip->coordinate->destination_coordinates->longitude,
            ];
            $intermediateCoordinates = $trip->coordinate->intermediate_coordinates ? json_decode($$trip->coordinate->intermediate_coordinates, true) : [];
        } else {
            $destinationCoordinates = [
                $trip->coordinate->pickup_coordinates->latitude,
                $trip->coordinate->pickup_coordinates->longitude,
            ];
        }

        $drivingMode = auth()->user()->vehicleCategory->category->type == 'motor_bike' ? 'TWO_WHEELER' : 'DRIVE';

        $getRoutes = getRoutes(
            originCoordinates: $pickupCoordinates,
            destinationCoordinates: $destinationCoordinates,
            intermediateCoordinates: $intermediateCoordinates,
        ); //["DRIVE", "TWO_WHEELER"]

        $result = [];
        foreach ($getRoutes as $route) {
            if ($route['drive_mode'] == $drivingMode) {
                if ($trip->current_status == 'completed' || $trip->current_status == 'cancelled') {
                    $result['is_dropped'] = true;
                } else {
                    $result['is_dropped'] = false;
                }
                if ($trip->current_status === PENDING || $trip->current_status === ACCEPTED) {
                    $result['is_picked'] = false;
                } else {
                    $result['is_picked'] = true;
                }
                return [array_merge($result, $route)];
            }
        }

    }

    public function predefinedQuestionAnswerList(): JsonResponse
    {
        $predefinedQAs = $this->questionAnswerService->getBy(criteria: ['is_active' => true], orderBy: ['created_at' => 'desc']);

        return response()->json(responseFormatter(DEFAULT_200, $predefinedQAs), 200);
    }

    public function otherEmergencyContactList(): JsonResponse
    {
        $criteria = [
            'settings_type' => SAFETY_FEATURE_SETTINGS,
            'key_name' => 'emergency_other_numbers_for_call'
        ];
        $emergencyOtherNumberList = businessConfig(key: 'emergency_number_for_call_status', settingsType: 'safety_feature_settings')?->value == 1 ? $this->businessSettingService->findOneBy(criteria: $criteria)?->value : null;
        return response()->json(responseFormatter(constant: DEFAULT_200, content: $emergencyOtherNumberList));
    }

    public function safetyAlertReasonList(): JsonResponse
    {
        $criteria = [
            'is_active' => 1,
            'reason_for_whom' => DRIVER
        ];
        $safetyAlertReasons = businessConfig(key: 'safety_alert_reasons_status', settingsType: 'safety_feature_settings')?->value == 1
            ? $this->safetyAlertReasonService->getBy(criteria: $criteria)->pluck('reason')->map(function ($reason) {
                return ['reason' => $reason];
            })
            : null;
        return response()->json(responseFormatter(constant: DEFAULT_200, content: $safetyAlertReasons));
    }

    public function safetyPrecautionList(): JsonResponse
    {
        $criteria = [
            'is_active' => 1,
            ['for_whom', 'like', '%' . DRIVER . '%'],
        ];
        $safetyPrecautions = $this->safetyPrecautionService->getBy(criteria: $criteria);
        $responseData = $safetyPrecautions->map(function ($item) {
            return [
                'title' => $item['title'],
                'description' => $item['description'],
            ];
        });
        return response()->json(responseFormatter(constant: DEFAULT_200, content: $responseData));
    }
}
