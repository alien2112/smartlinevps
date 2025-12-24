<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FirebasePushNotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Comprehensive seeder for all Firebase push notification templates
     * Used throughout the application for driver/customer notifications
     */
    public function run(): void
    {
        $now = Carbon::now();

        $notifications = [
            // ===== TRIP REQUEST NOTIFICATIONS =====
            [
                'name' => 'new_ride_request',
                'value' => 'New ride request available',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'new_parcel',
                'value' => 'New parcel delivery request available',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'trip_request_cancelled',
                'value' => 'Trip request has been cancelled',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ===== DRIVER ACCEPTANCE NOTIFICATIONS =====
            [
                'name' => 'driver_is_on_the_way',
                'value' => 'Driver is on the way to pickup location',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'driver_assigned',
                'value' => 'Driver has been assigned to your trip',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'ride_is_started',
                'value' => 'Another driver has accepted this ride',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'driver_after_bid_trip_rejected',
                'value' => 'Driver cancelled the ride request after bidding',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ===== TRIP STATUS NOTIFICATIONS =====
            [
                'name' => 'trip_started',
                'value' => 'Your trip has started',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'ride_accepted',
                'value' => 'Your ride has been accepted by driver',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'ride_ongoing',
                'value' => 'Your ride is currently ongoing',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'ride_completed',
                'value' => 'Your ride has been completed',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'ride_cancelled',
                'value' => 'Your ride has been cancelled',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ===== PARCEL STATUS NOTIFICATIONS =====
            [
                'name' => 'parcel_cancelled',
                'value' => 'Your parcel delivery has been cancelled',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'parcel_returned',
                'value' => 'Your parcel has been returned to sender',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'parcel_returning_otp',
                'value' => 'OTP for parcel return: {otp}',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ===== PAYMENT NOTIFICATIONS =====
            [
                'name' => 'payment_successful',
                'value' => 'Payment completed successfully',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'tips_from_customer',
                'value' => 'You received tips from customer',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ===== BIDDING NOTIFICATIONS =====
            [
                'name' => 'received_new_bid',
                'value' => 'You received a new bid for your trip',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'bid_accepted',
                'value' => 'Your bid has been accepted',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'customer_bid_rejected',
                'value' => 'Your bid has been rejected',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'driver_cancel_ride_request',
                'value' => 'Driver cancelled the ride request',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ===== REVIEW NOTIFICATIONS =====
            [
                'name' => 'review_from_driver',
                'value' => 'You received a review from driver',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'review_from_customer',
                'value' => 'You received a review from customer',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ===== REFERRAL NOTIFICATIONS =====
            [
                'name' => 'referral_reward_received',
                'value' => 'You received referral reward: {referralRewardAmount}',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ===== VEHICLE NOTIFICATIONS =====
            [
                'name' => 'vehicle_approved',
                'value' => 'Your vehicle has been approved',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ===== COUPON NOTIFICATIONS =====
            [
                'name' => 'coupon_applied',
                'value' => 'Coupon applied successfully',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ===== WAITING/IDLE NOTIFICATIONS =====
            [
                'name' => 'trip_paused',
                'value' => 'Trip has been paused',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'trip_resumed',
                'value' => 'Trip has been resumed',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ===== LOST ITEMS NOTIFICATIONS =====
            [
                'name' => 'lost_item_no_response',
                'value' => 'Lost item request closed due to no response',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ===== ADDITIONAL TRIP NOTIFICATIONS =====
            [
                'name' => 'trip_otp',
                'value' => 'Your trip OTP is {otp}',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'otp_matched',
                'value' => 'OTP verified successfully, trip started',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ===== LOST ITEMS NOTIFICATIONS (EXTENDED) =====
            [
                'name' => 'lost_item_reported',
                'value' => 'A lost item has been reported',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ===== REFUND NOTIFICATIONS =====
            [
                'name' => 'refund_request_approved',
                'value' => 'Your refund request has been approved',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'refund_request_denied',
                'value' => 'Your refund request has been denied',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'refunded_to_wallet',
                'value' => 'Amount has been refunded to your wallet',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'refunded_as_coupon',
                'value' => 'Refund has been provided as coupon',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ===== WALLET NOTIFICATIONS =====
            [
                'name' => 'debited_from_wallet',
                'value' => 'Amount has been debited from your wallet',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'amount_will_be_deducted',
                'value' => 'Amount will be deducted from your wallet',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        // Use updateOrInsert to avoid duplicates
        foreach ($notifications as $notification) {
            DB::table('firebase_push_notifications')->updateOrInsert(
                ['name' => $notification['name']], // Match on name
                $notification // Update or insert these values
            );
        }

        $this->command->info('Firebase push notifications seeded successfully!');
        $this->command->info('Total notifications: ' . count($notifications));
    }
}
