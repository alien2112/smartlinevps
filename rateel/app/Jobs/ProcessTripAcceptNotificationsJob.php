<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Modules\Gateways\Traits\SmsGatewayForMessage;
use App\Services\ObservabilityService;

/**
 * Job to handle all post-trip-acceptance notifications asynchronously
 * This significantly reduces the response time for the trip-action endpoint
 */
class ProcessTripAcceptNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SmsGatewayForMessage;

    public $tries = 3;
    public $backoff = 5;

    protected string $tripId;
    protected string $customerId;
    protected ?string $customerFcmToken;
    protected ?string $customerPhone;
    protected string $tripType;
    protected ?string $tripOtp;
    protected bool $sendOtp;
    protected ?array $parcelReceiverInfo;
    protected string $driverId;
    protected ?string $driverFcmToken;
    protected string $customerLanguage;
    protected string $driverLanguage;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $tripId,
        string $customerId,
        ?string $customerFcmToken,
        ?string $customerPhone,
        string $tripType,
        ?string $tripOtp,
        bool $sendOtp,
        ?array $parcelReceiverInfo = null,
        string $driverId = null,
        ?string $driverFcmToken = null,
        string $customerLanguage = 'en',
        string $driverLanguage = 'en'
    ) {
        $this->tripId = $tripId;
        $this->customerId = $customerId;
        $this->customerFcmToken = $customerFcmToken;
        $this->customerPhone = $customerPhone;
        $this->tripType = $tripType;
        $this->tripOtp = $tripOtp;
        $this->sendOtp = $sendOtp;
        $this->parcelReceiverInfo = $parcelReceiverInfo;
        $this->driverId = $driverId;
        $this->driverFcmToken = $driverFcmToken;
        $this->customerLanguage = $customerLanguage;
        $this->driverLanguage = $driverLanguage;

        // Use high priority queue
        $this->onQueue('high');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // OBSERVABILITY: Log job start with queue wait time calculation
        $jobStartTime = ObservabilityService::observeJobStart(
            'ProcessTripAcceptNotificationsJob',
            $this->tripId,
            $this->driverId
        );
        
        \Log::info('OBSERVE: ProcessTripAcceptNotificationsJob started', [
            'event_type' => 'job_started',
            'source' => 'queue',
            'trip_id' => $this->tripId,
            'driver_id' => $this->driverId,
            'customer_id' => $this->customerId,
            'has_driver_fcm' => !empty($this->driverFcmToken),
            'has_customer_fcm' => !empty($this->customerFcmToken),
            'send_otp' => $this->sendOtp,
            'attempt' => $this->attempts(),
            'trace_id' => ObservabilityService::generateTraceId() // New trace for async job
        ]);

        $startTime = microtime(true);

        // Send notification to driver confirming trip acceptance (in driver's preferred language)
        if ($this->driverFcmToken) {
            // Set locale to driver's preferred language
            App::setLocale($this->driverLanguage);
            
            $fcmStartTime = ObservabilityService::observeFcmAttempt('trip_accepted_by_driver', $this->driverId, $this->tripId, $this->driverFcmToken);
            
            try {
                sendDeviceNotification(
                    fcm_token: $this->driverFcmToken,
                    title: translate('trip_accepted'),
                    description: translate('you_have_successfully_accepted_the_trip_please_proceed_to_pickup_location'),
                    status: 1,
                    ride_request_id: $this->tripId,
                    type: $this->tripType,
                    action: 'trip_accepted_by_driver',
                    user_id: $this->driverId
                );
                
                ObservabilityService::observeFcmResult('trip_accepted_by_driver', $fcmStartTime, true, $this->driverId, $this->tripId);
            } catch (\Exception $e) {
                ObservabilityService::observeFcmResult('trip_accepted_by_driver', $fcmStartTime, false, $this->driverId, $this->tripId, $e->getMessage());
            }
        }

        // Send "driver is on the way" notification (in customer's preferred language)
        // Set locale to customer's preferred language
        App::setLocale($this->customerLanguage);
        
        $push = getNotification('driver_is_on_the_way');
        if ($this->customerFcmToken) {
            $fcmStartTime = ObservabilityService::observeFcmAttempt('driver_assigned', $this->customerId, $this->tripId, $this->customerFcmToken);
            
            try {
                sendDeviceNotification(
                    fcm_token: $this->customerFcmToken,
                    title: translate($push['title']),
                    description: translate(textVariableDataFormat(value: $push['description'])),
                    status: $push['status'],
                    ride_request_id: $this->tripId,
                    type: $this->tripType,
                    action: 'driver_assigned',
                    user_id: $this->customerId
                );
                
                ObservabilityService::observeFcmResult('driver_assigned', $fcmStartTime, true, $this->customerId, $this->tripId);
            } catch (\Exception $e) {
                ObservabilityService::observeFcmResult('driver_assigned', $fcmStartTime, false, $this->customerId, $this->tripId, $e->getMessage());
            }
        }

        // Send OTP if required (customer's locale already set above)
        if ($this->sendOtp && $this->tripOtp) {
            \Log::info('OBSERVE: Sending OTP notifications', [
                'trip_id' => $this->tripId,
                'has_phone' => !empty($this->customerPhone),
                'has_fcm' => !empty($this->customerFcmToken)
            ]);
            
            // Translate OTP message using customer's language
            $otpMessage = translate('your_trip_otp_is') . ' ' . $this->tripOtp;

            // Send OTP via SMS
            if ($this->customerPhone) {
                $smsStartTime = microtime(true);
                try {
                    \Log::info('OBSERVE: Sending OTP SMS', [
                        'trip_id' => $this->tripId,
                        'phone_prefix' => substr($this->customerPhone, 0, 5) . '...'
                    ]);
                    self::send($this->customerPhone, $otpMessage);
                    
                    \Log::info('OBSERVE: OTP SMS sent successfully', [
                        'trip_id' => $this->tripId,
                        'duration_ms' => round((microtime(true) - $smsStartTime) * 1000, 2)
                    ]);
                } catch (\Exception $exception) {
                    \Log::warning('OBSERVE: Failed to send trip OTP SMS', [
                        'trip_id' => $this->tripId,
                        'error' => $exception->getMessage(),
                        'duration_ms' => round((microtime(true) - $smsStartTime) * 1000, 2)
                    ]);
                }
            }

            // Send OTP via push notification
            if ($this->customerFcmToken) {
                $fcmStartTime = ObservabilityService::observeFcmAttempt('trip_otp', $this->customerId, $this->tripId, $this->customerFcmToken);
                
                try {
                    sendDeviceNotification(
                        fcm_token: $this->customerFcmToken,
                        title: translate('trip_otp'),
                        description: $otpMessage,
                        status: 1,
                        ride_request_id: $this->tripId,
                        type: $this->tripType,
                        action: 'trip_otp',
                        user_id: $this->customerId
                    );
                    
                    ObservabilityService::observeFcmResult('trip_otp', $fcmStartTime, true, $this->customerId, $this->tripId);
                } catch (\Exception $e) {
                    ObservabilityService::observeFcmResult('trip_otp', $fcmStartTime, false, $this->customerId, $this->tripId, $e->getMessage());
                }
            }
        }

        // Send parcel tracking SMS if applicable
        if ($this->parcelReceiverInfo) {
            $parcelTemplateMessage = businessConfig('parcel_tracking_message')?->value;
            if ($parcelTemplateMessage && $this->parcelReceiverInfo['contact_number']) {
                $smsTemplate = smsTemplateDataFormat(
                    value: $parcelTemplateMessage,
                    customerName: $this->parcelReceiverInfo['name'] ?? '',
                    parcelId: $this->parcelReceiverInfo['ref_id'] ?? '',
                    trackingLink: $this->parcelReceiverInfo['tracking_link'] ?? ''
                );
                try {
                    self::send($this->parcelReceiverInfo['contact_number'], $smsTemplate);
                    \Log::info('OBSERVE: Parcel tracking SMS sent', ['trip_id' => $this->tripId]);
                } catch (\Exception $exception) {
                    \Log::warning('OBSERVE: Failed to send parcel tracking SMS', [
                        'trip_id' => $this->tripId,
                        'error' => $exception->getMessage()
                    ]);
                }
            }
        }

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        
        // OBSERVABILITY: Log job completion with full timing
        ObservabilityService::observeJobComplete(
            'ProcessTripAcceptNotificationsJob',
            $jobStartTime,
            'success',
            $this->tripId
        );
        
        \Log::info('OBSERVE: ProcessTripAcceptNotificationsJob completed', [
            'event_type' => 'job_completed',
            'source' => 'queue',
            'status' => 'success',
            'trip_id' => $this->tripId,
            'execution_time_ms' => $executionTime,
            'slow_job' => $executionTime > 5000
        ]);
    }
}

