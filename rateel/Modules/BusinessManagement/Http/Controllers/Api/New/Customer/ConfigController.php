<?php

namespace Modules\BusinessManagement\Http\Controllers\Api\New\Customer;

use DateTimeZone;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Modules\BusinessManagement\Http\Requests\UserLocationStore;
use Modules\BusinessManagement\Service\Interface\BusinessSettingServiceInterface;
use Modules\BusinessManagement\Service\Interface\CancellationReasonServiceInterface;
use Modules\BusinessManagement\Service\Interface\ParcelCancellationReasonServiceInterface;
use Modules\BusinessManagement\Service\Interface\ParcelRefundReasonServiceInterface;
use Modules\BusinessManagement\Service\Interface\SafetyAlertReasonServiceInterface;
use Modules\BusinessManagement\Service\Interface\SafetyPrecautionServiceInterface;
use Modules\BusinessManagement\Service\Interface\SettingServiceInterface;
use Modules\TripManagement\Service\Interface\TripRequestServiceInterface;
use Modules\UserManagement\Repository\UserLastLocationRepositoryInterface;
use Modules\UserManagement\Service\Interface\UserLastLocationServiceInterface;
use Modules\ZoneManagement\Repository\ZoneRepositoryInterface;
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
    protected $parcelRefundReasonService;
    protected $safetyAlertReasonService;
    protected $safetyPrecautionService;

    public function __construct(BusinessSettingServiceInterface          $businessSettingService, SettingServiceInterface $settingService,
                                CancellationReasonServiceInterface       $cancellationReasonService, ZoneServiceInterface $zoneService,
                                UserLastLocationServiceInterface         $userLastLocationService, TripRequestServiceInterface $tripRequestService,
                                ParcelCancellationReasonServiceInterface $parcelCancellationReasonService, ParcelRefundReasonServiceInterface $parcelRefundReasonService,
                                SafetyAlertReasonServiceInterface        $safetyAlertReasonService, SafetyPrecautionServiceInterface $safetyPrecautionService)
    {
        $this->businessSettingService = $businessSettingService;
        $this->settingService = $settingService;
        $this->cancellationReasonService = $cancellationReasonService;
        $this->parcelCancellationReasonService = $parcelCancellationReasonService;
        $this->zoneService = $zoneService;
        $this->userLastLocationService = $userLastLocationService;
        $this->tripRequestService = $tripRequestService;
        $this->parcelRefundReasonService = $parcelRefundReasonService;
        $this->safetyAlertReasonService = $safetyAlertReasonService;
        $this->safetyPrecautionService = $safetyPrecautionService;
    }

    public function configuration()
    {
        $info = $this->businessSettingService->getAll(limit: 999, offset: 1);

        $loyaltyPoints = $info
            ->where('key_name', 'loyalty_points')
            ->firstWhere('settings_type', 'customer_settings')?->value;
        $martExternalSetting = false;
        if (checkSelfExternalConfiguration()) {
            $martBaseUrl = externalConfig('mart_base_url')?->value;
            $systemSelfToken = externalConfig('system_self_token')?->value;
            $martToken = externalConfig('mart_token')?->value;
            try {
                $response = Http::get($martBaseUrl . '/api/v1/configurations/get-external',
                    [
                        'mart_token' => $martToken,
                        'drivemond_base_url' => url('/'),
                        'drivemond_token' => $systemSelfToken,
                    ]);
                if ($response->successful()) {
                    $martResponse = $response->json();
                    $martExternalSetting = $martResponse['status'];
                }
            } catch (\Exception $exception) {

            }

        }
        $appVersions = $this->businessSettingService->getBy(criteria: ['settings_type' => APP_VERSION]);
        $dataValues = $this->settingService->getBy(criteria: ['settings_type' => SMS_CONFIG]);
        if ($dataValues->where('live_values.status', 1)->isEmpty()) {
            $smsConfiguration = 0;
        } else {
            $smsConfiguration = 1;
        }
        $zoneExtraFare = $this->zoneService->getBy(criteria: ['is_active' => 1, 'extra_fare_status' => 1]);
        $zoneExtraFare = $zoneExtraFare->map(function ($query) {
            return [
                'status' => $query->extra_fare_status,
                'zone_id' => $query->id,
                'reason' => $query->extra_fare_reason,
            ];
        });
        $configs = [
            'is_demo' => (bool)env('APP_MODE') != 'live',
            'maintenance_mode' => checkMaintenanceMode(),
            'required_pin_to_start_trip' => (bool)$info->firstWhere('key_name', 'required_pin_to_start_trip')?->value ?? false,
            'add_intermediate_points' => (bool)$info->firstWhere('key_name', 'add_intermediate_points')?->value ?? false,
            'business_name' => (string)$info->firstWhere('key_name', 'business_name')?->value ?? null,
            'logo' => $info->firstWhere('key_name', 'header_logo')->value ?? null,
            'bid_on_fare' => (bool)$info->firstWhere('key_name', 'bid_on_fare')?->value ?? 0,
            'country_code' => (string)$info->firstWhere('key_name', 'country_code')?->value ?? null,
            'business_address' => (string)$info->firstWhere('key_name', 'business_address')?->value ?? null,
            'business_contact_phone' => (string)$info->firstWhere('key_name', 'business_contact_phone')?->value ?? null,
            'business_contact_email' => (string)$info->firstWhere('key_name', 'business_contact_email')?->value ?? null,
            'business_support_phone' => (string)$info->firstWhere('key_name', 'business_support_phone')?->value ?? null,
            'business_support_email' => (string)$info->firstWhere('key_name', 'business_support_email')?->value ?? null,
            'conversion_status' => (bool)($loyaltyPoints['status'] ?? false),
            'conversion_rate' => (double)($loyaltyPoints['points'] ?? 0),
            'websocket_url' => $info->firstWhere('key_name', 'websocket_url')?->value ?? null,
            'websocket_port' => (string)$info->firstWhere('key_name', 'websocket_port')?->value ?? 6001,
            'websocket_key' => env('PUSHER_APP_KEY'),
            'websocket_scheme' => env('PUSHER_SCHEME'),
            'base_url' => url('/') . '/api/',
            'review_status' => (bool)$info->firstWhere('key_name', CUSTOMER_REVIEW)?->value ?? null,
            'level_status' => (bool)$info->firstWhere('key_name', CUSTOMER_LEVEL)?->value ?? null,
            'search_radius' => $info->firstWhere('key_name', 'search_radius')?->value ?? 10000,
            'popular_tips' => $this->tripRequestService->getPopularTips()?->tips ?? 5,
            'driver_completion_radius' => $info->firstWhere('key_name', 'driver_completion_radius')?->value ?? 1000,
            'image_base_url' => [
                'profile_image_driver' => asset('storage/app/public/driver/profile'),
                'profile_image_admin' => asset('storage/app/public/employee/profile'),
                'banner' => asset('storage/app/public/promotion/banner'),
                'vehicle_category' => asset('storage/app/public/vehicle/category'),
                'vehicle_model' => asset('storage/app/public/vehicle/model'),
                'vehicle_brand' => asset('storage/app/public/vehicle/brand'),
                'profile_image' => asset('storage/app/public/customer/profile'),
                'identity_image' => asset('storage/app/public/customer/identity'),
                'documents' => asset('storage/app/public/customer/document'),
                'level' => asset('storage/app/public/customer/level'),
                'pages' => asset('storage/app/public/business/pages'),
                'conversation' => asset('storage/app/public/conversation'),
                'parcel' => asset('storage/app/public/parcel/category'),
                'payment_method' => asset('storage/app/public/payment_modules/gateway_image')
            ],
            'currency_decimal_point' => $info->firstWhere('key_name', 'currency_decimal_point')?->value ?? null,
            'trip_request_active_time' => (int)$info->firstWhere('key_name', 'trip_request_active_time')?->value ?? 10,
            'currency_code' => $info->firstWhere('key_name', 'currency_code')?->value ?? null,
            'currency_symbol' => $info->firstWhere('key_name', 'currency_symbol')?->value ?? '$',
            'currency_symbol_position' => $info->firstWhere('key_name', 'currency_symbol_position')?->value ?? null,
            'about_us' => $info->firstWhere('key_name', 'about_us')?->value,
            'privacy_policy' => $info->firstWhere('key_name', 'privacy_policy')?->value,
            'refund_policy' => $info->firstWhere('key_name', 'refund_policy')?->value,
            'terms_and_conditions' => $info->firstWhere('key_name', 'terms_and_conditions')?->value,
            'legal' => $info->firstWhere('key_name', 'legal')?->value,
            'verification' => (bool)$info->firstWhere('key_name', 'customer_verification')?->value ?? 0,
            'sms_verification' => (bool)$info->firstWhere('key_name', 'sms_verification')?->value ?? 0,
            'email_verification' => (bool)$info->firstWhere('key_name', 'email_verification')?->value ?? 0,
            'facebook_login' => (bool)$info->firstWhere('key_name', 'facebook_login')?->value['status'] ?? 0,
            'google_login' => (bool)$info->firstWhere('key_name', 'google_login')?->value['status'] ?? 0,
            'otp_resend_time' => (int)($info->firstWhere('key_name', 'otp_resend_time')?->value ?? 60),
            'vat_tax' => (double)get_cache('vat_percent') ?? 1,
            'payment_gateways' => collect($this->getPaymentMethods()),
            'referral_earning_status' => (bool)referralEarningSetting('referral_earning_status', CUSTOMER)?->value,
            'external_system' => $martExternalSetting,
            'mart_business_name' => $martExternalSetting ? externalConfig('mart_business_name')?->value ?? "6amMart" : "",
            'mart_app_url_android' => $martExternalSetting ? externalConfig('mart_app_url_android')?->value : "",
            'mart_app_minimum_version_android' => $martExternalSetting ? externalConfig('mart_app_minimum_version_android')?->value : null,
            'mart_app_url_ios' => $martExternalSetting ? externalConfig('mart_app_url_ios')?->value : "",
            'mart_app_minimum_version_ios' => $martExternalSetting ? externalConfig('mart_app_minimum_version_ios')?->value : null,
            'app_minimum_version_for_android' => (double)$appVersions->firstWhere('key_name', 'customer_app_version_control_for_android')?->value['minimum_app_version'] ?? 0,
            'app_url_for_android' => $appVersions->firstWhere('key_name', 'customer_app_version_control_for_android')?->value['app_url'] ?? null,
            'app_minimum_version_for_ios' => (double)$appVersions->firstWhere('key_name', 'customer_app_version_control_for_ios')?->value['minimum_app_version'] ?? 0,
            'app_url_for_ios' => $appVersions->firstWhere('key_name', 'customer_app_version_control_for_ios')?->value['app_url'] ?? null,
            'parcel_refund_status' => (bool)$info->firstWhere('key_name', 'parcel_refund_status')?->value ?? false,
            'parcel_refund_validity' => (int)$info->firstWhere('key_name', 'parcel_refund_validity')?->value ?? 0,
            'parcel_refund_validity_type' => $info->firstWhere('key_name', 'parcel_refund_validity_type')?->value ?? 'day',
            'firebase_otp_verification' => (bool)$info->firstWhere('key_name', 'firebase_otp_verification_status')?->value == 1,
            'sms_gateway' => (bool)$smsConfiguration,
            'zone_extra_fare' => $zoneExtraFare,
            'maximum_parcel_weight_status' => (bool)$info->firstWhere('key_name', 'max_parcel_weight_status')?->value == 1,
            'maximum_parcel_weight_capacity' => $info->firstWhere('key_name', 'max_parcel_weight_status')?->value == 1 ? (double)$info->firstWhere('key_name', 'max_parcel_weight')?->value : null,
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

            'safety_feature_emergency_govt_number' => $info->firstWhere('key_name', 'emergency_number_for_call_status')?->value == 1 ? $info->firstWhere('key_name', 'emergency_govt_number_for_call')?->value : null,
            'otp_confirmation_for_trip' => (bool)$info->firstWhere('key_name', 'driver_otp_confirmation_for_trip')?->value == 1,
        ];

        return response()->json($configs);
    }

    public function getPaymentMethods()
    {
        $methods = $this->settingService->getBy(criteria: ['settings_type' => PAYMENT_CONFIG]);
        $data = [];
        foreach ($methods as $method) {
            $additionalData = json_decode($method->additional_data, true);
            if ($method?->is_active == 1) {
                $data[] = [
                    'gateway' => $method->key_name,
                    'gateway_title' => $additionalData['gateway_title'],
                    'gateway_image' => $additionalData['gateway_image']
                ];
            }
        }
        return collect($data);
    }

    public function pages($page_name)
    {
        $validated = in_array($page_name, ['about_us', 'privacy_and_policy', 'terms_and_conditions', 'legal']);

        if (!$validated) {
            return response()->json(responseFormatter(DEFAULT_400), 400);
        }

        $data = businessConfig(key: $page_name, settingsType: PAGES_SETTINGS);
        return response(responseFormatter(DEFAULT_200, [$data]));

    }


    public function placeApiAutocomplete(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'search_text' => 'required',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'language' => 'nullable|string|max:2',
            'country' => 'nullable|string|max:2',
            'zone_id' => 'nullable|string',
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
        
        // Optional zone filtering via header/query
        $zoneId = $request->header('zoneId') ?? $request->input('zone_id');

        // Optional biasing params (support both latitude/longitude and lat/lng)
        $latitude = $request->input('latitude') ?? $request->input('lat');
        $longitude = $request->input('longitude') ?? $request->input('lng');

        $apiParams = [
            'query' => $request->input('search_text'),
            'key' => $mapKey,
        ];
        if ($latitude !== null && $longitude !== null) {
            $apiParams['latitude'] = $latitude;
            $apiParams['longitude'] = $longitude;
        }
        if ($request->filled('language')) {
            $apiParams['language'] = $request->input('language');
        }
        if ($request->filled('country')) {
            $apiParams['country'] = $request->input('country');
        }

        // GeoLink Text Search API
        $response = Http::timeout(30)->get(MAP_API_BASE_URI . '/api/v2/text_search', $apiParams);
        
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
            'request_params' => $apiParams,
            'api_url' => MAP_API_BASE_URI . '/api/v2/text_search',
            'zone_filtering' => !empty($zoneId)
        ]);

        $transformedData = $this->transformTextSearchResponse($geoLinkData, $zoneId);
        
        return response()->json(responseFormatter(DEFAULT_200, $transformedData), 200);
    }
    
    /**
     * Transform GeoLink text search response to Google Places format
     */
    private function transformTextSearchResponse($geoLinkData, ?string $zoneId = null): array
    {
        $predictions = [];
        $filteredCount = 0;
        $zoneFiltered = false;
        
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

                    // Zone filtering (optional): skip results outside the selected zone
                    if (!empty($zoneId) && $lat !== null && $lng !== null) {
                        try {
                            $point = new Point((float)$lat, (float)$lng, 4326);
                            $isInZone = $this->zoneService->getByPoints($point)
                                ->where('id', $zoneId)
                                ->where('is_active', 1)
                                ->exists();
                            if (!$isInZone) {
                                $filteredCount++;
                                continue;
                            }
                            $zoneFiltered = true;
                        } catch (\Exception $e) {
                            \Log::warning('Zone filtering failed for autocomplete result', [
                                'zone_id' => $zoneId,
                                'lat' => $lat,
                                'lng' => $lng,
                                'error' => $e->getMessage(),
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
        
        return [
            'predictions' => $predictions,
            'status' => !empty($predictions) ? 'OK' : 'ZERO_RESULTS',
            'zone_filtered' => !empty($zoneId) && $zoneFiltered,
            'zone_filtered_out' => !empty($zoneId) ? $filteredCount : 0,
        ];
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
        
        if (empty($mapKey)) {
            \Log::error('GeoLink API key not configured for directions');
            return response()->json(responseFormatter(DEFAULT_200, [
                'rows' => [[
                    'elements' => [[
                        'distance' => ['text' => '0 km', 'value' => 0],
                        'duration' => ['text' => '0 mins', 'value' => 0],
                        'status' => 'ZERO_RESULTS'
                    ]]
                ]],
                'status' => 'ZERO_RESULTS',
                '_debug' => ['error' => 'API key not provided']
            ]), 200);
        }
        
        // GeoLink Directions API
        $response = Http::timeout(30)->get(MAP_API_BASE_URI . '/api/v2/directions', [
            'origin_latitude' => $request['origin_lat'],
            'origin_longitude' => $request['origin_lng'],
            'destination_latitude' => $request['destination_lat'],
            'destination_longitude' => $request['destination_lng'],
            'key' => $mapKey
        ]);
        
        // Log the response for debugging
        if (!$response->successful()) {
            \Log::error('GeoLink directions API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'params' => [
                    'origin_lat' => $request['origin_lat'],
                    'origin_lng' => $request['origin_lng'],
                    'destination_lat' => $request['destination_lat'],
                    'destination_lng' => $request['destination_lng']
                ]
            ]);
        }
        
        // Transform GeoLink response to match expected distance matrix format
        $geoLinkData = $response->json();
        
        // Log the raw response for debugging
        \Log::info('GeoLink directions API response', [
            'http_status' => $response->status(),
            'response' => $geoLinkData,
            'params' => [
                'origin_lat' => $request['origin_lat'],
                'origin_lng' => $request['origin_lng'],
                'destination_lat' => $request['destination_lat'],
                'destination_lng' => $request['destination_lng']
            ],
            'api_url' => MAP_API_BASE_URI . '/api/v2/directions'
        ]);
        
        $transformedData = $this->transformDirectionsToDistanceMatrix($geoLinkData, $response->status());

        return response()->json(responseFormatter(DEFAULT_200, $transformedData), 200);
    }
    
    /**
     * Transform GeoLink directions response to Google Distance Matrix format
     */
    private function transformDirectionsToDistanceMatrix($geoLinkData, $httpStatus = 200): array
    {
        $distance = 0;
        $duration = 0;
        $status = 'ZERO_RESULTS';
        
        // Check if response is null or empty
        if (empty($geoLinkData) || !is_array($geoLinkData)) {
            return [
                'rows' => [[
                    'elements' => [[
                        'distance' => ['text' => '0 km', 'value' => 0],
                        'duration' => ['text' => '0 mins', 'value' => 0],
                        'status' => 'ZERO_RESULTS'
                    ]]
                ]],
                'status' => 'ZERO_RESULTS',
                '_debug' => [
                    'error' => 'Empty or invalid response',
                    'geolink_response' => $geoLinkData,
                    'http_status' => $httpStatus
                ]
            ];
        }
        
        // Handle GeoLink API v2 response structure
        // GeoLink returns data as an array of route objects
        if (isset($geoLinkData['data']) && is_array($geoLinkData['data']) && !empty($geoLinkData['data'])) {
            // Get the first route
            $route = $geoLinkData['data'][0];
            
            // Extract distance and duration
            if (isset($route['distance']) && isset($route['duration'])) {
                // GeoLink structure: distance.meters and duration.seconds
                $distance = $route['distance']['meters'] ?? 0;
                $duration = $route['duration']['seconds'] ?? 0;
                
                if ($distance > 0) {
                    $status = 'OK';
                }
            }
        }
        
        // Handle error responses
        if (isset($geoLinkData['error']) || isset($geoLinkData['message'])) {
            \Log::warning('GeoLink directions API returned error', [
                'error' => $geoLinkData['error'] ?? $geoLinkData['message'] ?? 'Unknown error',
                'response' => $geoLinkData
            ]);
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
            'status' => $status,
            '_debug' => [
                'geolink_response' => $geoLinkData,
                'http_status' => $httpStatus,
                'has_data_key' => isset($geoLinkData['data']),
                'data_is_array' => isset($geoLinkData['data']) && is_array($geoLinkData['data']),
                'route_count' => isset($geoLinkData['data']) && is_array($geoLinkData['data']) ? count($geoLinkData['data']) : 0
            ]
        ];
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

    #
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
            $trip->driver?->lastLocations->latitude,
            $trip->driver?->lastLocations->longitude,
        ];

        $intermediateCoordinates = [];
        if ($trip->current_status == ONGOING) {
            $destinationCoordinates = [
                $trip->coordinate->destination_coordinates->latitude,
                $trip->coordinate->destination_coordinates->longitude,
            ];
            // Fixed: removed double $ sign
            $intermediateCoordinates = $trip->coordinate->intermediate_coordinates ? json_decode($trip->coordinate->intermediate_coordinates, true) : [];
        } else {
            $destinationCoordinates = [
                $trip->coordinate->pickup_coordinates->latitude,
                $trip->coordinate->pickup_coordinates->longitude,
            ];
        }

        // Fixed: assigned to variable instead of returning immediately
        $getRoutes = getRoutes(
            originCoordinates: $pickupCoordinates,
            destinationCoordinates: $destinationCoordinates,
            intermediateCoordinates: $intermediateCoordinates,
        ); //["DRIVE", "TWO_WHEELER"]

        // Safely get driving mode with null checks - default to DRIVE if category not available
        $vehicleType = $trip->driver?->vehicleCategory?->category?->type ?? null;
        $drivingMode = ($vehicleType && $vehicleType == 'motor_bike') ? 'TWO_WHEELER' : 'DRIVE';

        $result = [];
        foreach ($getRoutes as $route) {
            // Check if route has error status (from failed API call)
            if (isset($route['status']) && $route['status'] === 'ERROR') {
                return response()->json(responseFormatter(
                    constant: DEFAULT_400,
                    content: null,
                    errors: [['error_code' => 'route_error', 'message' => $route['error_detail'] ?? 'Failed to get route information']]
                ), 400);
            }

            if (isset($route['drive_mode']) && $route['drive_mode'] == $drivingMode) {
                $result['is_picked'] = $trip->current_status == ONGOING;
                $data = [array_merge($result, $route)];
                return response()->json(responseFormatter(constant: DEFAULT_200, content: $data));
            }
        }

        // If no matching route found, return empty array with success response
        return response()->json(responseFormatter(constant: DEFAULT_200, content: []));
    }

    #
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
        
        // Add debug information to response (temporary for debugging)
        $transformedData['_debug'] = [
            'geolink_response' => $geoLinkData,
            'http_status' => $response->status(),
            'has_data_key' => isset($geoLinkData['data']),
            'data_structure' => isset($geoLinkData['data']) ? array_keys($geoLinkData['data']) : []
        ];
        
        return response()->json(responseFormatter(DEFAULT_200, $transformedData), 200);
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

    #
    public function userLastLocation(UserLocationStore $request)
    {

        if (empty($request->header('zoneId'))) {

            return response()->json(responseFormatter(ZONE_404), 200);
        }

        $zone_id = $request->header('zoneId');
        $user = auth('api')->user();
        $request->merge([
            'user_id' => $user->id,
            'type' => $user->user_type,
            'zone_id' => $zone_id,
        ]);
        $userLastLocation = $this->userLastLocationService->findOneBy(criteria: ['user_id' => $user->id]);
        if ($userLastLocation) {
            return $this->userLastLocationService->update(id: $userLastLocation->id, data: $request->all());
        }
        return $this->userLastLocationService->create(data: $request->all());
    }

    #
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

    #
    public function cancellationReasonList()
    {
        $ongoingRide = $this->cancellationReasonService->getBy(criteria: ['cancellation_type' => 'ongoing_ride', 'user_type' => 'customer', 'is_active' => 1])->pluck('title')->toArray();
        $acceptedRide = $this->cancellationReasonService->getBy(criteria: ['cancellation_type' => 'accepted_ride', 'user_type' => 'customer', 'is_active' => 1])->pluck('title')->toArray();
        $data = [
            'ongoing_ride' => $ongoingRide,
            'accepted_ride' => $acceptedRide,
        ];
        return response(responseFormatter(DEFAULT_200, $data));
    }

    public function parcelCancellationReasonList()
    {
        $ongoingRide = $this->parcelCancellationReasonService->getBy(criteria: ['cancellation_type' => 'ongoing_ride', 'user_type' => 'customer', 'is_active' => 1])->pluck('title')->toArray();
        $acceptedRide = $this->parcelCancellationReasonService->getBy(criteria: ['cancellation_type' => 'accepted_ride', 'user_type' => 'customer', 'is_active' => 1])->pluck('title')->toArray();
        $data = [
            'ongoing_ride' => $ongoingRide,
            'accepted_ride' => $acceptedRide,
        ];
        return response(responseFormatter(DEFAULT_200, $data));
    }

    public function parcelRefundReasonList()
    {
        $parcelRefundReasonList = $this->parcelRefundReasonService->getBy(criteria: ['is_active' => 1])->pluck('title')->toArray();
        return response(responseFormatter(DEFAULT_200, $parcelRefundReasonList));
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
            'reason_for_whom' => CUSTOMER
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
            ['for_whom', 'like', '%' . CUSTOMER . '%'],
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
