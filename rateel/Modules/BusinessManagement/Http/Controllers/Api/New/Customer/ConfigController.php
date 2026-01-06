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

    /**
     * Full configuration endpoint (legacy - for backward compatibility)
     * Consider using the optimized endpoints instead:
     * - /config/core - Essential startup data (~2KB)
     * - /config/auth - Authentication settings
     * - /config/trip - Trip/ride settings
     * - /config/safety - Safety features
     * - /config/parcel - Parcel settings
     * - /config/external - External integrations
     * - /config/contact - Business contact info
     * - /config/loyalty - Loyalty points/levels settings
     * - /pages/{page_name} - Legal pages (on-demand)
     */
    public function configuration()
    {
        // Cache configuration for 5 minutes to reduce repeated queries
        return \Illuminate\Support\Facades\Cache::remember('customer_configuration', 300, function () {
            return $this->buildConfiguration();
        });
    }

    /**
     * OPTIMIZED: Core configuration - Essential data for app startup (~2KB)
     * Call this first on app launch
     */
    public function coreConfig()
    {
        return \Illuminate\Support\Facades\Cache::remember('customer_config_core', 300, function () {
            $info = $this->businessSettingService->getAll(limit: 999, offset: 1);
            $aiChatbotEnable = (bool)($info
                ->where('settings_type', 'ai_config')
                ->firstWhere('key_name', 'ai_chatbot_enable')
                ?->value ?? 0);

            $config = [
                'is_demo' => (bool)env('APP_MODE') != 'live',
                'maintenance_mode' => checkMaintenanceMode(),
                'business_name' => (string)$info->firstWhere('key_name', 'business_name')?->value ?? null,
                'logo' => $info->firstWhere('key_name', 'header_logo')->value ?? null,
                'country_code' => (string)$info->firstWhere('key_name', 'country_code')?->value ?? null,
                'base_url' => url('/') . '/api/',
                'ai_chatbot_enable' => $aiChatbotEnable,
                // Currency
                'currency_code' => $info->firstWhere('key_name', 'currency_code')?->value ?? null,
                'currency_symbol' => $info->firstWhere('key_name', 'currency_symbol')?->value ?? '$',
                'currency_symbol_position' => $info->firstWhere('key_name', 'currency_symbol_position')?->value ?? null,
                'currency_decimal_point' => $info->firstWhere('key_name', 'currency_decimal_point')?->value ?? null,
                'vat_tax' => (double)get_cache('vat_percent') ?? 1,
                // WebSocket
                'websocket_url' => $info->firstWhere('key_name', 'websocket_url')?->value ?? null,
                'websocket_port' => (string)$info->firstWhere('key_name', 'websocket_port')?->value ?? 6001,
                'websocket_key' => env('PUSHER_APP_KEY'),
                'websocket_scheme' => env('PUSHER_SCHEME'),
                // Image base URLs
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
                // App versions
                'app_minimum_version_for_android' => (double)$this->getAppVersion('customer_app_version_control_for_android'),
                'app_url_for_android' => $this->getAppUrl('customer_app_version_control_for_android'),
                'app_minimum_version_for_ios' => (double)$this->getAppVersion('customer_app_version_control_for_ios'),
                'app_url_for_ios' => $this->getAppUrl('customer_app_version_control_for_ios'),
            ];

            return response()->json($config)
                ->header('Cache-Control', 'public, max-age=300')
                ->header('X-Cache-TTL', '300');
        });
    }

    /**
     * OPTIMIZED: Authentication configuration
     */
    public function authConfig()
    {
        return \Illuminate\Support\Facades\Cache::remember('customer_config_auth', 300, function () {
            $info = $this->businessSettingService->getAll(limit: 999, offset: 1);
            $dataValues = $this->settingService->getBy(criteria: ['settings_type' => SMS_CONFIG]);
            $smsConfiguration = $dataValues->where('live_values.status', 1)->isNotEmpty() ? 1 : 0;

            $config = [
                'verification' => (bool)$info->firstWhere('key_name', 'customer_verification')?->value ?? 0,
                'sms_verification' => (bool)$info->firstWhere('key_name', 'sms_verification')?->value ?? 0,
                'email_verification' => (bool)$info->firstWhere('key_name', 'email_verification')?->value ?? 0,
                'facebook_login' => (bool)$info->firstWhere('key_name', 'facebook_login')?->value['status'] ?? 0,
                'google_login' => (bool)$info->firstWhere('key_name', 'google_login')?->value['status'] ?? 0,
                'firebase_otp_verification' => (bool)$info->firstWhere('key_name', 'firebase_otp_verification_status')?->value == 1,
                'otp_resend_time' => (int)($info->firstWhere('key_name', 'otp_resend_time')?->value ?? 60),
                'sms_gateway' => (bool)$smsConfiguration,
                'referral_earning_status' => (bool)referralEarningSetting('referral_earning_status', CUSTOMER)?->value,
            ];

            return response()->json($config)
                ->header('Cache-Control', 'public, max-age=300')
                ->header('X-Cache-TTL', '300');
        });
    }

    /**
     * OPTIMIZED: Trip/Ride settings
     */
    public function tripConfig()
    {
        return \Illuminate\Support\Facades\Cache::remember('customer_config_trip', 300, function () {
            $info = $this->businessSettingService->getAll(limit: 999, offset: 1);
            $loyaltyPoints = $info
                ->where('key_name', 'loyalty_points')
                ->firstWhere('settings_type', 'customer_settings')?->value;

            $zoneExtraFare = $this->zoneService->getBy(criteria: ['is_active' => 1, 'extra_fare_status' => 1]);
            $zoneExtraFare = $zoneExtraFare->map(function ($query) {
                return [
                    'status' => $query->extra_fare_status,
                    'zone_id' => $query->id,
                    'reason' => $query->extra_fare_reason,
                ];
            });

            $config = [
                'bid_on_fare' => (bool)$info->firstWhere('key_name', 'bid_on_fare')?->value ?? 0,
                'required_pin_to_start_trip' => (bool)$info->firstWhere('key_name', 'required_pin_to_start_trip')?->value ?? false,
                'add_intermediate_points' => (bool)$info->firstWhere('key_name', 'add_intermediate_points')?->value ?? false,
                'trip_request_active_time' => (int)$info->firstWhere('key_name', 'trip_request_active_time')?->value ?? 10,
                'search_radius' => $info->firstWhere('key_name', 'search_radius')?->value ?? 10000,
                'driver_completion_radius' => $info->firstWhere('key_name', 'driver_completion_radius')?->value ?? 1000,
                'popular_tips' => $this->tripRequestService->getPopularTips()?->tips ?? 5,
                'otp_confirmation_for_trip' => (bool)$info->firstWhere('key_name', 'driver_otp_confirmation_for_trip')?->value == 1,
                'review_status' => (bool)$info->firstWhere('key_name', CUSTOMER_REVIEW)?->value ?? null,
                'level_status' => (bool)$info->firstWhere('key_name', CUSTOMER_LEVEL)?->value ?? null,
                'conversion_status' => (bool)($loyaltyPoints['status'] ?? false),
                'conversion_rate' => (double)($loyaltyPoints['points'] ?? 0),
                'zone_extra_fare' => $zoneExtraFare,
                'payment_gateways' => collect($this->getPaymentMethods()),
            ];

            return response()->json($config)
                ->header('Cache-Control', 'public, max-age=300')
                ->header('X-Cache-TTL', '300');
        });
    }

    /**
     * OPTIMIZED: Safety features configuration
     */
    public function safetyConfig()
    {
        return \Illuminate\Support\Facades\Cache::remember('customer_config_safety', 300, function () {
            $info = $this->businessSettingService->getAll(limit: 999, offset: 1);
            $safetyEnabled = (bool)$info->firstWhere('key_name', 'safety_feature_status')?->value == 1;

            $config = [
                'safety_feature_status' => $safetyEnabled,
                'safety_feature_minimum_trip_delay_time' => $safetyEnabled ? convertTimeToSecond(
                    $info->firstWhere('key_name', 'for_trip_delay')?->value['minimum_delay_time'],
                    $info->firstWhere('key_name', 'for_trip_delay')?->value['time_format']
                ) : null,
                'safety_feature_minimum_trip_delay_time_type' => $safetyEnabled ? $info->firstWhere('key_name', 'for_trip_delay')?->value['time_format'] : null,
                'after_trip_completed_safety_feature_active_status' => $safetyEnabled && (bool)$info->firstWhere('key_name', 'after_trip_complete')?->value['safety_feature_active_status'] == 1,
                'after_trip_completed_safety_feature_set_time' => $info->firstWhere('key_name', 'after_trip_complete')?->value['safety_feature_active_status'] == 1 ? convertTimeToSecond(
                    $info->firstWhere('key_name', 'after_trip_complete')?->value['set_time'],
                    $info->firstWhere('key_name', 'after_trip_complete_time_format')?->value
                ) : null,
                'after_trip_completed_safety_feature_set_time_type' => $info->firstWhere('key_name', 'after_trip_complete')?->value['safety_feature_active_status'] == 1 ? $info->firstWhere('key_name', 'after_trip_complete_time_format')?->value : null,
                'safety_feature_emergency_govt_number' => $info->firstWhere('key_name', 'emergency_number_for_call_status')?->value == 1 ? $info->firstWhere('key_name', 'emergency_govt_number_for_call')?->value : null,
            ];

            return response()->json($config)
                ->header('Cache-Control', 'public, max-age=300')
                ->header('X-Cache-TTL', '300');
        });
    }

    /**
     * OPTIMIZED: Parcel settings
     */
    public function parcelConfig()
    {
        return \Illuminate\Support\Facades\Cache::remember('customer_config_parcel', 300, function () {
            $info = $this->businessSettingService->getAll(limit: 999, offset: 1);

            $config = [
                'parcel_refund_status' => (bool)$info->firstWhere('key_name', 'parcel_refund_status')?->value ?? false,
                'parcel_refund_validity' => (int)$info->firstWhere('key_name', 'parcel_refund_validity')?->value ?? 0,
                'parcel_refund_validity_type' => $info->firstWhere('key_name', 'parcel_refund_validity_type')?->value ?? 'day',
                'maximum_parcel_weight_status' => (bool)$info->firstWhere('key_name', 'max_parcel_weight_status')?->value == 1,
                'maximum_parcel_weight_capacity' => $info->firstWhere('key_name', 'max_parcel_weight_status')?->value == 1 ? (double)$info->firstWhere('key_name', 'max_parcel_weight')?->value : null,
                'parcel_weight_unit' => businessConfig(key: 'parcel_weight_unit', settingsType: PARCEL_SETTINGS)?->value ?? 'kg',
            ];

            return response()->json($config)
                ->header('Cache-Control', 'public, max-age=300')
                ->header('X-Cache-TTL', '300');
        });
    }

    /**
     * OPTIMIZED: External/Mart integration settings
     */
    public function externalConfig()
    {
        return \Illuminate\Support\Facades\Cache::remember('customer_config_external', 300, function () {
            $martExternalSetting = false;
            if (checkSelfExternalConfiguration()) {
                $martBaseUrl = externalConfig('mart_base_url')?->value;
                $systemSelfToken = externalConfig('system_self_token')?->value;
                $martToken = externalConfig('mart_token')?->value;
                try {
                    $response = Http::get($martBaseUrl . '/api/v1/configurations/get-external', [
                        'mart_token' => $martToken,
                        'drivemond_base_url' => url('/'),
                        'drivemond_token' => $systemSelfToken,
                    ]);
                    if ($response->successful()) {
                        $martResponse = $response->json();
                        $martExternalSetting = $martResponse['status'];
                    }
                } catch (\Exception $exception) {
                    // Silent fail
                }
            }

            $config = [
                'external_system' => $martExternalSetting,
                'mart_business_name' => $martExternalSetting ? externalConfig('mart_business_name')?->value ?? "6amMart" : "",
                'mart_app_url_android' => $martExternalSetting ? externalConfig('mart_app_url_android')?->value : "",
                'mart_app_minimum_version_android' => $martExternalSetting ? externalConfig('mart_app_minimum_version_android')?->value : null,
                'mart_app_url_ios' => $martExternalSetting ? externalConfig('mart_app_url_ios')?->value : "",
                'mart_app_minimum_version_ios' => $martExternalSetting ? externalConfig('mart_app_minimum_version_ios')?->value : null,
            ];

            return response()->json($config)
                ->header('Cache-Control', 'public, max-age=300')
                ->header('X-Cache-TTL', '300');
        });
    }

    /**
     * OPTIMIZED: Loyalty/Points configuration
     * Used by: My Level screen, Wallet, Trip completion (points earned)
     */
    public function loyaltyConfig()
    {
        return \Illuminate\Support\Facades\Cache::remember('customer_config_loyalty', 300, function () {
            $info = $this->businessSettingService->getAll(limit: 999, offset: 1);
            $loyaltyPoints = $info
                ->where('key_name', 'loyalty_points')
                ->firstWhere('settings_type', 'customer_settings')?->value;

            $config = [
                'level_status' => (bool)$info->firstWhere('key_name', CUSTOMER_LEVEL)?->value ?? false,
                'conversion_status' => (bool)($loyaltyPoints['status'] ?? false),
                'conversion_rate' => (double)($loyaltyPoints['points'] ?? 0),
            ];

            return response()->json($config)
                ->header('Cache-Control', 'public, max-age=300')
                ->header('X-Cache-TTL', '300');
        });
    }

    /**
     * OPTIMIZED: Business contact info
     */
    public function contactConfig()
    {
        return \Illuminate\Support\Facades\Cache::remember('customer_config_contact', 300, function () {
            $info = $this->businessSettingService->getAll(limit: 999, offset: 1);

            $config = [
                'business_address' => (string)$info->firstWhere('key_name', 'business_address')?->value ?? null,
                'business_contact_phone' => (string)$info->firstWhere('key_name', 'business_contact_phone')?->value ?? null,
                'business_contact_email' => (string)$info->firstWhere('key_name', 'business_contact_email')?->value ?? null,
                'business_support_phone' => (string)$info->firstWhere('key_name', 'business_support_phone')?->value ?? null,
                'business_support_email' => (string)$info->firstWhere('key_name', 'business_support_email')?->value ?? null,
            ];

            return response()->json($config)
                ->header('Cache-Control', 'public, max-age=300')
                ->header('X-Cache-TTL', '300');
        });
    }

    /**
     * Helper: Get app version from settings
     */
    private function getAppVersion(string $keyName): float
    {
        $appVersions = $this->businessSettingService->getBy(criteria: ['settings_type' => APP_VERSION]);
        return (double)$appVersions->firstWhere('key_name', $keyName)?->value['minimum_app_version'] ?? 0;
    }

    /**
     * Helper: Get app URL from settings
     */
    private function getAppUrl(string $keyName): ?string
    {
        $appVersions = $this->businessSettingService->getBy(criteria: ['settings_type' => APP_VERSION]);
        return $appVersions->firstWhere('key_name', $keyName)?->value['app_url'] ?? null;
    }

    private function buildConfiguration()
    {
        $info = $this->businessSettingService->getAll(limit: 999, offset: 1);

        $loyaltyPoints = $info
            ->where('key_name', 'loyalty_points')
            ->firstWhere('settings_type', 'customer_settings')?->value;
        $aiChatbotEnable = (bool)($info
            ->where('settings_type', 'ai_config')
            ->firstWhere('key_name', 'ai_chatbot_enable')
            ?->value ?? 0);
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
            'ai_chatbot_enable' => $aiChatbotEnable,
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

        return response()->json($configs)
            ->header('Cache-Control', 'public, max-age=300')
            ->header('X-Cache-TTL', '300');
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
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, null, null, null, errorProcessor($validator)), 400);
        }

        $mapKey = $request->input('key') ?? businessConfig(GOOGLE_MAP_API)?->value['map_api_key_server'] ?? null;

        if (empty($mapKey)) {
            return response()->json(responseFormatter(DEFAULT_200, [
                'predictions' => [],
                'status' => 'ZERO_RESULTS'
            ]), 200);
        }

        // Get zone_id from header or query parameter for filtering
        $zoneId = $request->header('zoneId') ?? $request->input('zone_id');
        $searchText = $request['search_text'];

        // Accept both 'lat'/'lng' and 'latitude'/'longitude' from request (Flutter sends lat/lng)
        $latitude = $request->input('latitude') ?? $request->input('lat');
        $longitude = $request->input('longitude') ?? $request->input('lng');

        // Create cache key based on search text, zone, and location
        $cacheKey = 'place_autocomplete_' . md5($searchText . $zoneId . $latitude . $longitude);

        // Try to get from cache first (60 seconds TTL)
        $cachedResult = \Cache::get($cacheKey);
        if ($cachedResult !== null) {
            return response()->json(responseFormatter(DEFAULT_200, $cachedResult), 200);
        }

        // Build GeoLink API parameters with location biasing
        // GeoLink text_search requires 'latitude' and 'longitude' per docs
        $apiParams = [
            'query' => $searchText,
            'key' => $mapKey,
            'country' => $request->input('country', 'eg')
        ];

        // GeoLink API requires 'latitude' and 'longitude' parameter names
        if ($latitude) $apiParams['latitude'] = $latitude;
        if ($longitude) $apiParams['longitude'] = $longitude;
        if ($request->filled('language')) $apiParams['language'] = $request->input('language');

        // GeoLink Text Search API
        $response = Http::get(MAP_API_BASE_URI . '/api/v2/text_search', $apiParams);

        // Debug log
        \Log::info('GeoLink autocomplete API call', [
            'params' => $apiParams,
            'response_status' => $response->status(),
            'response_preview' => array_slice($response->json()['data'] ?? [], 0, 2)
        ]);

        if (!$response->successful()) {
            return response()->json(responseFormatter(DEFAULT_200, [
                'predictions' => [],
                'status' => 'ZERO_RESULTS'
            ]), 200);
        }

        $transformedData = $this->transformTextSearchResponse($response->json(), $zoneId);

        // Cache for 60 seconds
        \Cache::put($cacheKey, $transformedData, 60);

        return response()->json(responseFormatter(DEFAULT_200, $transformedData), 200);
    }
    
    /**
     * Transform GeoLink text search response to Google Places format
     * GeoLink response structure:
     * {
     *   "data": [
     *     {
     *       "address": "Full address string",
     *       "short_address": "Short name",
     *       "location": { "lat": 31.xxx, "lng": 29.xxx },
     *       "address_parts": { "country": "EG", "district": "...", "governorate": "..." }
     *     }
     *   ],
     *   "success": true
     * }
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
        }

        // Check if response is null or empty
        if (empty($geoLinkData) || !is_array($geoLinkData)) {
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
                if (!is_array($result)) {
                    continue;
                }

                // GeoLink actual response: location.lat, location.lng (nested object)
                $lat = $result['location']['lat'] ?? $result['latitude'] ?? $result['lat'] ?? null;
                $lng = $result['location']['lng'] ?? $result['longitude'] ?? $result['lng'] ?? null;
                $name = $result['short_address'] ?? $result['name'] ?? '';
                // GeoLink actual response: 'address' field contains full address
                $address = $result['address'] ?? $result['long_address'] ?? $result['formatted_address'] ?? '';

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
                            continue;
                        }
                    } catch (\Exception $e) {
                        // Silently continue on zone check error
                    }
                }

                // Create place_id with embedded coordinates for fast detail retrieval
                $placeId = $result['place_id'] ?? $result['id'] ?? null;
                if (!$placeId && $lat && $lng) {
                    $placeId = base64_encode(json_encode([
                        'lat' => $lat,
                        'lng' => $lng,
                        'name' => $name,
                        'address' => $address
                    ]));
                }

                $predictions[] = [
                    'place_id' => $placeId,
                    'description' => $address ?: $name,
                    'structured_formatting' => [
                        'main_text' => $name,
                        'secondary_text' => $address
                    ],
                    'geometry' => [
                        'location' => [
                            'lat' => $lat,
                            'lng' => $lng
                        ]
                    ]
                ];
            }
        }

        return [
            'predictions' => $predictions,
            'status' => !empty($predictions) ? 'OK' : 'ZERO_RESULTS',
            'zone_filtered' => !empty($zone)
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

        $placeId = $request['placeid'];

        // Try to decode if it's a base64 encoded data (from autocomplete)
        $decoded = base64_decode($placeId, true);
        if ($decoded !== false) {
            $jsonData = json_decode($decoded, true);
            if (is_array($jsonData) && isset($jsonData['lat']) && isset($jsonData['lng'])) {
                // We already have all the data from autocomplete - return it directly
                $result = [
                    'place_id' => $placeId,
                    'name' => $jsonData['name'] ?? '',
                    'formatted_address' => $jsonData['address'] ?? '',
                    'geometry' => [
                        'location' => [
                            'lat' => (float)$jsonData['lat'],
                            'lng' => (float)$jsonData['lng']
                        ]
                    ],
                    'address_components' => []
                ];

                return response()->json(responseFormatter(DEFAULT_200, [
                    'result' => $result,
                    'status' => 'OK'
                ]), 200);
            }
        }

        // Fallback: If place_id is not base64 encoded with coordinates, return error
        return response()->json(responseFormatter(DEFAULT_400, null, null, null, [
            'message' => 'Invalid place_id format'
        ]), 400);
    }
    
    /**
     * Transform GeoLink geocode response to Google Place Details format
     */
    private function transformPlaceDetailsResponse($geoLinkData, $originalPlaceId = null, $decodedData = null): array
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
                // Extract coordinates with fallback to decoded place_id data
                $lat = $data['lat'] ?? $data['latitude'] ?? null;
                $lng = $data['lng'] ?? $data['longitude'] ?? null;

                // If GeoLink didn't return coordinates, use the ones from the original place_id
                if (($lat === null || $lat === 0) && $decodedData && isset($decodedData['lat'])) {
                    $lat = $decodedData['lat'];
                    \Log::info('Using fallback latitude from decoded place_id', ['lat' => $lat]);
                }
                if (($lng === null || $lng === 0) && $decodedData && isset($decodedData['lng'])) {
                    $lng = $decodedData['lng'];
                    \Log::info('Using fallback longitude from decoded place_id', ['lng' => $lng]);
                }

                // Use decoded data for name/address if not present in API response
                $name = $data['name'] ?? ($decodedData['name'] ?? '');
                $formattedAddress = $data['formatted_address'] ?? $data['address'] ?? ($decodedData['address'] ?? '');

                $result = [
                    'place_id' => $originalPlaceId ?? $data['place_id'] ?? $data['id'] ?? '',
                    'name' => $name,
                    'formatted_address' => $formattedAddress,
                    'geometry' => [
                        'location' => [
                            'lat' => (float)($lat ?? 0),
                            'lng' => (float)($lng ?? 0)
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

        // Cache zone lookup for 10 minutes based on rounded coordinates
        $cacheKey = 'zone_' . round($request->lat, 3) . '_' . round($request->lng, 3);
        $zone = \Illuminate\Support\Facades\Cache::remember($cacheKey, 600, function () use ($request) {
            $point = new Point($request->lat, $request->lng, 4326);
            return $this->zoneService->getByPoints($point)->where('is_active', 1)->first();
        });

        if ($zone) {
            return response()->json(responseFormatter(DEFAULT_200, $zone), 200)
                ->header('Cache-Control', 'public, max-age=600')
                ->header('X-Cache-TTL', '600');
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
