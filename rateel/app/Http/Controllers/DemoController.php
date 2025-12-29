<?php

namespace App\Http\Controllers;

use App\Traits\ActivationClass;
use App\Traits\UnloadedHelpers;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\BusinessManagement\Service\Interface\BusinessSettingServiceInterface;
use Modules\BusinessManagement\Service\Interface\ExternalConfigurationServiceInterface;
use Modules\BusinessManagement\Service\Interface\FirebasePushNotificationServiceInterface;
use Modules\BusinessManagement\Service\Interface\NotificationSettingServiceInterface;
use Modules\Gateways\Traits\SmsGateway;
use Modules\PromotionManagement\Entities\CouponSetup;
use Modules\PromotionManagement\Service\Interface\CouponSetupServiceInterface;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Entities\WithdrawRequest;
use Modules\UserManagement\Service\Interface\EmployeeRoleServiceInterface;
use Modules\UserManagement\Service\Interface\EmployeeServiceInterface;
use Modules\TripManagement\Entities\TripRequest;
use App\Events\NewMessage;
use App\Events\SampleEvent;

class DemoController extends Controller
{
    use UnloadedHelpers;
    use ActivationClass;
    use SmsGateway;

    protected $businessSetting;
    protected $notificationSettingService;
    protected $firebasePushNotificationService;
    protected $couponSetupService;

    public function __construct(BusinessSettingServiceInterface          $businessSetting, NotificationSettingServiceInterface $notificationSettingService,
                                FirebasePushNotificationServiceInterface $firebasePushNotificationService, CouponSetupServiceInterface $couponSetupService)
    {
        $this->businessSetting = $businessSetting;
        $this->notificationSettingService = $notificationSettingService;
        $this->firebasePushNotificationService = $firebasePushNotificationService;
        $this->couponSetupService = $couponSetupService;
    }

    public function demo()
    {
        $withdrawRequests = WithdrawRequest::get();
        foreach ($withdrawRequests as $withdrawRequest) {
            if ($withdrawRequest->is_approved == null) {
                $withdrawRequest->status = PENDING;
            } elseif ($withdrawRequest->is_approved == 1) {
                $withdrawRequest->status = SETTLED;
            } else {
                $withdrawRequest->status = DENIED;
            }
            $withdrawRequest->save();
        }
        $users = User::withTrashed()->get();
        foreach ($users as $user) {
            if (is_null($user->full_name)) {
                $user->full_name = $user->first_name . ' ' . $user->last_name;
                $user->save();
            }
        }
        if (Schema::hasColumns('coupon_setups', ['user_id', 'user_level_id', 'rules'])) {
            $couponSetups = CouponSetup::withTrashed()->get();
            if (count((array)$couponSetups) > 0) {
                foreach ($couponSetups as $couponSetup) {
                    $couponSetup->zone_coupon_type = ALL;
                    $couponSetup->save();
                    if ($couponSetup->user_id == ALL) {
                        $couponSetup->customer_coupon_type = ALL;
                        $couponSetup->save();
                    } else {
                        $couponSetup->customer_coupon_type = CUSTOM;
                        $couponSetup->save();
                        $couponSetup?->customers()->attach($couponSetup->user_id);
                    }
                    if ($couponSetup->user_level_id == ALL || $couponSetup->user_level_id == null) {
                        $couponSetup->customer_level_coupon_type = ALL;
                        $couponSetup->save();
                    } else {
                        $couponSetup->customer_level_coupon_type = CUSTOM;
                        $couponSetup->save();
                        $couponSetup?->customerLevels()->attach($couponSetup->user_level_id);
                    }
                    if ($couponSetup->rules == "default") {
                        $couponSetup->category_coupon_type = [ALL];
                        $couponSetup->save();
                    } else {
                        $couponSetup->category_coupon_type = [CUSTOM];
                        $couponSetup->save();
                    }
                }

            }
            Schema::table('coupon_setups', function (Blueprint $table) {
                $table->dropColumn(['user_id', 'user_level_id', 'rules']); // Replace 'column_name' with the actual column name
            });
        }

        $notificationSettings = $this->notificationSettingService->getAll();
        foreach ($notificationSettings as $setting) {
            if (in_array($setting->name, ['trip', 'rating_and_review'])) {
                $this->notificationSettingService->delete($setting->id);
            }
        }
        if ($this->notificationSettingService->findOneBy(criteria: ['name' => 'legal']) == false) {
            $this->notificationSettingService->create(data: [
                'name' => 'legal',
                'push' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        if ($this->firebasePushNotificationService->findOneBy(criteria: ['name' => 'identity_image_approved']) == false) {
            $this->firebasePushNotificationService->create(data: [
                'name' => 'identity_image_approved',
                'value' => 'Your identity image has been successfully reviewed and approved.',
                'status' => 1
            ]);
        }
        if ($this->firebasePushNotificationService->findOneBy(criteria: ['name' => 'identity_image_rejected']) == false) {
            $this->firebasePushNotificationService->create(data: ['name' => 'identity_image_rejected',
                'value' => 'Your identity image has been rejected during our review process.',
                'status' => 1
            ]);
        }
        if ($this->firebasePushNotificationService->findOneBy(criteria: ['name' => 'review_from_customer']) == false) {
            $this->firebasePushNotificationService->create(data: ['name' => 'review_from_customer',
                'value' => 'New review from a customer! See what they had to say about your service.',
                'status' => 1
            ]);
        }
        if ($this->firebasePushNotificationService->findOneBy(criteria: ['name' => 'review_from_driver']) == false) {
            $this->firebasePushNotificationService->create(data: ['name' => 'review_from_driver',
                'value' => 'New review from a driver! See what he had to say about your trip.',
                'status' => 1
            ]);
        }
        if ($this->firebasePushNotificationService->findOneBy(criteria: ['name' => 'bid_request_from_driver']) == false) {
            $this->firebasePushNotificationService->create(data: ['name' => 'bid_request_from_driver',
                'value' => 'Driver sent a bid request',
                'status' => 1
            ]);
        }
        if ($this->firebasePushNotificationService->findOneBy(criteria: ['name' => 'bid_request_cancel_by_customer']) == false) {
            $this->firebasePushNotificationService->create(data: ['name' => 'bid_request_cancel_by_customer',
                'value' => 'Customer has canceled your bid request',
                'status' => 1
            ]);
        }
        if ($this->firebasePushNotificationService->findOneBy(criteria: ['name' => 'fund_added_by_admin']) == false) {
            $this->firebasePushNotificationService->create(data: ['name' => 'fund_added_by_admin',
                'value' => 'Admin has added {walletAmount} to your wallet',
                'status' => 1
            ]);
        }
        if ($this->firebasePushNotificationService->findOneBy(criteria: ['name' => 'level_up']) == false) {
            $this->firebasePushNotificationService->create(data: ['name' => 'level_up',
                'value' => 'You have completed your challenges and reached level {levelName}',
                'status' => 1
            ]);
        }
        if ($this->firebasePushNotificationService->findOneBy(criteria: ['name' => 'vehicle_is_active']) == false) {
            $this->firebasePushNotificationService->create(data: ['name' => 'vehicle_is_active',
                'value' => 'Your vehicle status has been activated by admin',
                'status' => 1
            ]);
        }
        if ($this->firebasePushNotificationService->findOneBy(criteria: ['name' => 'driver_cancel_ride_request']) == false) {
            $this->firebasePushNotificationService->create(data: ['name' => 'driver_cancel_ride_request',
                'value' => 'Driver has canceled your ride',
                'status' => 1
            ]);
        }
        if ($this->firebasePushNotificationService->findOneBy(criteria: ['name' => 'driver_cancel_ride_request']) == false) {
            $this->firebasePushNotificationService->create(data: ['name' => 'driver_cancel_ride_request',
                'value' => 'Driver has canceled your ride',
                'status' => 1
            ]);
        }
        if ($this->firebasePushNotificationService->findOneBy(criteria: ['name' => 'tips_from_customer']) == false) {
            $this->firebasePushNotificationService->create(data: ['name' => 'tips_from_customer',
                'value' => 'Customer has given the tips {tipsAmount} with payment',
                'status' => 1
            ]);
        }
        if ($this->firebasePushNotificationService->findOneBy(criteria: ['name' => 'new_message']) == false) {
            $this->firebasePushNotificationService->create(data: ['name' => 'new_message',
                'value' => 'You got a new message from {userName}',
                'status' => 1
            ]);
        } else {
            $this->firebasePushNotificationService->updatedBy(criteria: ['name' => 'new_message'], data: [
                'value' => 'You got a new message from {userName}',
                'status' => 1
            ]);
        }
        if ($this->firebasePushNotificationService->findOneBy(criteria: ['name' => 'payment_successful']) == true) {
            $this->firebasePushNotificationService->updatedBy(criteria: ['name' => 'payment_successful'],
                data: [
                    'value' => '{paidAmount} payment successful on this trip by {methodName}.',
                    'status' => 1
                ]);
        }
        if ($this->firebasePushNotificationService->findOneBy(criteria: ['name' => 'withdraw_request_rejected']) == false) {
            $this->firebasePushNotificationService->create(data: ['name' => 'withdraw_request_rejected',
                'value' => 'Unfortunately, your withdrawal request has been rejected. {withdrawNote}',
                'status' => 1
            ]);
        }
        if ($this->firebasePushNotificationService->findOneBy(criteria: ['name' => 'withdraw_request_approved']) == false) {
            $this->firebasePushNotificationService->create(data: ['name' => 'withdraw_request_approved',
                'value' => 'We are pleased to inform you that your withdrawal request has been approved. The funds will be transferred to your account shortly.',
                'status' => 1
            ]);
        }
        if ($this->firebasePushNotificationService->findOneBy(criteria: ['name' => 'withdraw_request_settled']) == false) {
            $this->firebasePushNotificationService->create(data: ['name' => 'withdraw_request_settled',
                'value' => 'Your withdrawal request has been successfully settled. The funds have been transferred to your account.',
                'status' => 1
            ]);
        }
        if ($this->firebasePushNotificationService->findOneBy(criteria: ['name' => 'withdraw_request_reversed']) == false) {
            $this->firebasePushNotificationService->create(data: ['name' => 'withdraw_request_reversed',
                'value' => 'Your withdrawal request has been successfully settled. The funds have been transferred to your account.',
                'status' => 1
            ]);
        }
        if ($this->firebasePushNotificationService->findOneBy(criteria: ['name' => 'customer_bid_rejected']) == false) {
            $this->firebasePushNotificationService->create(data: ['name' => 'customer_bid_rejected',
                'value' => 'We regret to inform you that your bid request for trip ID {tripId} has been rejected by the customer',
                'status' => 1
            ]);
        }
        if ($this->firebasePushNotificationService->findOneBy(criteria: ['name' => 'legal_updated']) == false) {
            $this->firebasePushNotificationService->create(data: ['name' => 'legal_updated',
                'value' => 'We have updated our legal',
                'status' => 1
            ]);
        }
        if ($this->firebasePushNotificationService->findOneBy(criteria: ['name' => 'someone_used_your_code']) == false) {
            $this->firebasePushNotificationService->create(data: ['name' => 'someone_used_your_code',
                'value' => "Your code was successfully used by a friend. You'll receive your reward after their first ride is completed.",
                'status' => 1
            ]);
        }
        if ($this->firebasePushNotificationService->findOneBy(criteria: ['name' => 'referral_reward_received']) == false) {
            $this->firebasePushNotificationService->create(data: ['name' => 'referral_reward_received',
                'value' => "You've successfully received {referralRewardAmount} reward. You can use this amount on your next ride.",
                'status' => 1
            ]);
        }
        if ($this->firebasePushNotificationService->findOneBy(criteria: ['name' => 'parcel_returned']) == false) {
            $this->firebasePushNotificationService->create(data: ['name' => 'parcel_returned',
                'value' => "Parcel returned successfully",
                'status' => 1
            ]);
        }
        if ($this->firebasePushNotificationService->findOneBy(criteria: ['name' => 'parcel_returning_otp']) == false) {
            $this->firebasePushNotificationService->create(data: ['name' => 'parcel_returning_otp',
                'value' => "Your parcel returning OTP is {otp}",
                'status' => 1
            ]);
        }
        $emptyRefCodeUsers = User::withTrashed()->whereNull('ref_code')->get();
        foreach ($emptyRefCodeUsers as $user) {
            generateReferralCode($user);
        }
        insertBusinessSetting(keyName: 'return_time_for_driver', settingType: PARCEL_SETTINGS, value: 24);
        insertBusinessSetting(keyName: 'return_time_type_for_driver', settingType: PARCEL_SETTINGS, value: "hour");
        insertBusinessSetting(keyName: 'return_fee_for_driver_time_exceed', settingType: PARCEL_SETTINGS, value: 0);
        insertBusinessSetting(keyName: 'parcel_refund_validity', settingType: PARCEL_SETTINGS, value: 2);
        insertBusinessSetting(keyName: 'parcel_refund_validity_type', settingType: PARCEL_SETTINGS, value: 'day');
        $parcel_tracking_message = "Dear {CustomerName}
Parcel ID is {ParcelId} You can track this parcel from this link {TrackingLink}";
        insertBusinessSetting(keyName: 'parcel_tracking_message', settingType: PARCEL_SETTINGS, value: $parcel_tracking_message);

        #version 2.1
        $firebasePushNotifications = $this->firebasePushNotificationService->getBy(whereInCriteria: ['id' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]]);
        if (count($firebasePushNotifications) > 0) {
            foreach ($firebasePushNotifications as $firebasePushNotification) {
                if ($firebasePushNotification) {
                    $this->firebasePushNotificationService->delete(id: $firebasePushNotification->id);
                }
            }
        }

        if ($this->firebasePushNotificationService->findOneBy(criteria: ['name' => 'ride_is_started']) == false) {
            $this->firebasePushNotificationService->create(data: ['name' => 'ride_is_started',
                'value' => "Another driver already accept the trip request.",
                'status' => 1
            ]);
        }


        #version 2.2
        insertBusinessSetting(keyName: 'parcel_weight_unit', settingType: PARCEL_SETTINGS, value: 'kg');


        return redirect()->route("admin.dashboard");
    }

    public function smsGatewayTest(Request $request)
    {
        try {
            self::send("+8801740128172", "1234");
            dd("done");
        } catch (\Exception $exception) {
            dd($exception->getMessage());
        }
    }

    public function firebaseMessageConfigFileGen()
    {
        $apiKey = businessConfig(key: 'api_key',settingsType: NOTIFICATION_SETTINGS)?->value ?? '';
        $authDomain = businessConfig(key: 'auth_domain',settingsType: NOTIFICATION_SETTINGS)?->value ?? '';
        $projectId = businessConfig(key: 'project_id',settingsType: NOTIFICATION_SETTINGS)?->value ?? '';
        $storageBucket = businessConfig(key: 'storage_bucket',settingsType: NOTIFICATION_SETTINGS)?->value ?? '';
        $messagingSenderId = businessConfig(key: 'messaging_sender_id',settingsType: NOTIFICATION_SETTINGS)?->value ?? '';
        $appId = businessConfig(key: 'app_id',settingsType: NOTIFICATION_SETTINGS)?->value ?? '';
        $measurementId = businessConfig(key: 'measurement_id',settingsType: NOTIFICATION_SETTINGS)?->value ?? '';

        $filePath = base_path('firebase-messaging-sw.js');

        try {
            if (file_exists($filePath) && !is_writable($filePath)) {
                if (!chmod($filePath, 0644)) {
                    throw new \Exception('File is not writable and permission change failed: ' . $filePath);
                }
            }

            $fileContent = <<<JS
                importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-app.js');
                importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-messaging.js');

                firebase.initializeApp({
                    apiKey: "$apiKey",
                    authDomain: "$authDomain",
                    projectId: "$projectId",
                    storageBucket: "$storageBucket",
                    messagingSenderId: "$messagingSenderId",
                    appId: "$appId",
                    measurementId: "$measurementId"
                });

                const messaging = firebase.messaging();
                messaging.setBackgroundMessageHandler(function (payload) {
                    return self.registration.showNotification(payload.data.title, {
                        body: payload.data.body ? payload.data.body : '',
                        icon: payload.data.icon ? payload.data.icon : ''
                    });
                });
                JS;


            if (file_put_contents($filePath, $fileContent) === false) {
                throw new \Exception('Failed to write to file: ' . $filePath);
            }

        } catch (\Exception $e) {
            //
        }
    }

    /**
     * Test event sender route (converted from closure for route caching)
     */
    public function sender()
    {
        return event(new NewMessage("hello"));
    }

    /**
     * Test Pusher connection (converted from closure for route caching)
     */
    public function testConnection()
    {
        $trip = TripRequest::first();
        try {
            checkPusherConnection(\App\Events\CustomerTripRequestEvent::broadcast($trip->driver, $trip));
        } catch (\Exception $exception) {
            // Silent fail for test endpoint
        }
        return response()->json(['status' => 'connection test complete']);
    }

    /**
     * Trigger sample broadcast event (converted from closure for route caching)
     */
    public function trigger()
    {
        broadcast(new SampleEvent('Hello'));
        return response()->json(['status' => true]);
    }

    /**
     * Test topic notification (converted from closure for route caching)
     */
    public function testNotification()
    {
        sendTopicNotification(
            'admin_message',
            translate('new_request_notification'),
            translate('new_request_has_been_placed'),
            'null'
        );
        return response()->json(['status' => true]);
    }

}
