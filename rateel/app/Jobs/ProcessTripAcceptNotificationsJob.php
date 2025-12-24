<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Gateways\Traits\SmsGatewayForMessage;

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
        ?string $driverFcmToken = null
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

        // Use high priority queue
        $this->onQueue('high');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        \Log::info('ProcessTripAcceptNotificationsJob started', [
            'trip_id' => $this->tripId,
            'driver_id' => $this->driverId,
            'customer_id' => $this->customerId
        ]);

        $startTime = microtime(true);

        // Send notification to driver confirming trip acceptance
        if ($this->driverFcmToken) {
            \Log::info('Sending FCM to driver', ['trip_id' => $this->tripId]);
            sendDeviceNotification(
                fcm_token: $this->driverFcmToken,
                title: translate('Trip Accepted'),
                description: translate('You have successfully accepted the trip. Please proceed to pickup location.'),
                status: 1,
                ride_request_id: $this->tripId,
                type: $this->tripType,
                action: 'trip_accepted_by_driver',
                user_id: $this->driverId
            );
        }

        // Send "driver is on the way" notification
        $push = getNotification('driver_is_on_the_way');
        if ($this->customerFcmToken) {
            \Log::info('Sending FCM to customer', ['trip_id' => $this->tripId]);
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
        }

        // Send OTP if required
        if ($this->sendOtp && $this->tripOtp) {
            \Log::info('Sending OTP', ['trip_id' => $this->tripId, 'otp' => $this->tripOtp]);
            $otpMessage = 'Your trip OTP is ' . $this->tripOtp;

            // Send OTP via SMS
            if ($this->customerPhone) {
                try {
                    \Log::info('Sending OTP SMS', ['trip_id' => $this->tripId, 'phone' => $this->customerPhone]);
                    self::send($this->customerPhone, $otpMessage);
                } catch (\Exception $exception) {
                    \Log::warning('Failed to send trip OTP SMS', [
                        'trip_id' => $this->tripId,
                        'error' => $exception->getMessage()
                    ]);
                }
            }

            // Send OTP via push notification
            if ($this->customerFcmToken) {
                \Log::info('Sending OTP FCM', ['trip_id' => $this->tripId]);
                sendDeviceNotification(
                    fcm_token: $this->customerFcmToken,
                    title: translate('Trip OTP'),
                    description: translate($otpMessage),
                    status: 1,
                    ride_request_id: $this->tripId,
                    type: $this->tripType,
                    action: 'trip_otp',
                    user_id: $this->customerId
                );
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
                } catch (\Exception $exception) {
                    \Log::warning('Failed to send parcel tracking SMS', [
                        'trip_id' => $this->tripId,
                        'error' => $exception->getMessage()
                    ]);
                }
            }
        }

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        \Log::info('ProcessTripAcceptNotificationsJob completed', [
            'trip_id' => $this->tripId,
            'execution_time_ms' => $executionTime
        ]);
    }
}
