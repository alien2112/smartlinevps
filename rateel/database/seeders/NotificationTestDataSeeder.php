<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Carbon\Carbon;

class NotificationTestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Get or create driver with phone number +201208673028
        $driverId = DB::table('users')
            ->where('phone', '+201208673028')
            ->where('user_type', 'driver')
            ->value('id');

        if (!$driverId) {
            // Create test driver if not exists
            $driverId = Uuid::uuid4()->toString();
            DB::table('users')->insert([
                'id' => $driverId,
                'first_name' => 'Test',
                'last_name' => 'Driver',
                'email' => 'testdriver@example.com',
                'phone' => '+201208673028',
                'password' => bcrypt('password123'),
                'user_type' => 'driver',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Define notification types and data
        $notificationTypes = [
            [
                'type' => 'trip_request',
                'title' => 'New Trip Request',
                'message' => 'You have a new trip request from customer. Pickup location: Downtown Cairo',
                'category' => 'trips',
                'priority' => 'high',
                'action_type' => 'deep_link',
                'action_url' => '/trips/accept-request',
                'data' => json_encode(['trip_id' => Uuid::uuid4()->toString(), 'customer_name' => 'Ahmed'])
            ],
            [
                'type' => 'trip_accepted',
                'title' => 'Trip Accepted',
                'message' => 'Your trip request has been accepted by driver. Driver is on the way.',
                'category' => 'trips',
                'priority' => 'normal',
                'action_type' => 'deep_link',
                'action_url' => '/trips/active',
                'data' => json_encode(['trip_id' => Uuid::uuid4()->toString(), 'driver_name' => 'Mohammed'])
            ],
            [
                'type' => 'trip_started',
                'title' => 'Trip Started',
                'message' => 'Your trip has started. Estimated time: 25 minutes',
                'category' => 'trips',
                'priority' => 'normal',
                'action_type' => 'deep_link',
                'action_url' => '/trips/active',
                'data' => json_encode(['trip_id' => Uuid::uuid4()->toString()])
            ],
            [
                'type' => 'trip_completed',
                'title' => 'Trip Completed',
                'message' => 'Great job! Trip completed successfully. Earnings: 85 EGP',
                'category' => 'trips',
                'priority' => 'normal',
                'action_type' => 'deep_link',
                'action_url' => '/trips/history',
                'data' => json_encode(['trip_id' => Uuid::uuid4()->toString(), 'earnings' => 85])
            ],
            [
                'type' => 'trip_cancelled',
                'title' => 'Trip Cancelled',
                'message' => 'The customer cancelled the trip request.',
                'category' => 'trips',
                'priority' => 'low',
                'action_type' => 'none',
                'action_url' => null,
                'data' => json_encode(['trip_id' => Uuid::uuid4()->toString()])
            ],
            [
                'type' => 'payment_received',
                'title' => 'Payment Received',
                'message' => 'You have received a payment of 250 EGP for completed trips.',
                'category' => 'earnings',
                'priority' => 'high',
                'action_type' => 'deep_link',
                'action_url' => '/wallet/transactions',
                'data' => json_encode(['amount' => 250, 'currency' => 'EGP'])
            ],
            [
                'type' => 'withdrawal_approved',
                'title' => 'Withdrawal Approved',
                'message' => 'Your withdrawal request of 500 EGP has been approved.',
                'category' => 'earnings',
                'priority' => 'high',
                'action_type' => 'deep_link',
                'action_url' => '/wallet/withdrawals',
                'data' => json_encode(['amount' => 500, 'reference' => 'WTH-2024-001'])
            ],
            [
                'type' => 'withdrawal_rejected',
                'title' => 'Withdrawal Rejected',
                'message' => 'Your withdrawal request has been rejected. Please try again.',
                'category' => 'earnings',
                'priority' => 'high',
                'action_type' => 'deep_link',
                'action_url' => '/wallet/withdrawals',
                'data' => json_encode(['reason' => 'Insufficient balance'])
            ],
            [
                'type' => 'document_verified',
                'title' => 'Document Verified',
                'message' => 'Your driver license has been verified successfully.',
                'category' => 'documents',
                'priority' => 'normal',
                'action_type' => 'deep_link',
                'action_url' => '/documents',
                'data' => json_encode(['document_type' => 'driver_license'])
            ],
            [
                'type' => 'document_rejected',
                'title' => 'Document Rejected',
                'message' => 'Your vehicle registration has been rejected. Please upload a clear image.',
                'category' => 'documents',
                'priority' => 'high',
                'action_type' => 'deep_link',
                'action_url' => '/documents/upload',
                'data' => json_encode(['document_type' => 'vehicle_registration', 'reason' => 'Image not clear'])
            ],
            [
                'type' => 'level_up',
                'title' => 'Level Up!',
                'message' => 'Congratulations! You reached level 5. Unlock new benefits.',
                'category' => 'promotions',
                'priority' => 'high',
                'action_type' => 'deep_link',
                'action_url' => '/profile/level',
                'data' => json_encode(['level' => 5])
            ],
            [
                'type' => 'achievement_unlocked',
                'title' => 'Achievement Unlocked',
                'message' => 'You have unlocked the "100 Trips" achievement!',
                'category' => 'promotions',
                'priority' => 'normal',
                'action_type' => 'deep_link',
                'action_url' => '/profile/achievements',
                'data' => json_encode(['achievement' => '100_trips'])
            ],
            [
                'type' => 'promotion',
                'title' => 'Special Promotion',
                'message' => 'Limited time offer! Earn 2x the usual amount on weekend trips.',
                'category' => 'promotions',
                'priority' => 'normal',
                'action_type' => 'deep_link',
                'action_url' => '/promotions',
                'data' => json_encode(['promo_id' => Uuid::uuid4()->toString(), 'multiplier' => 2])
            ],
            [
                'type' => 'system_announcement',
                'title' => 'System Update',
                'message' => 'A new version of the app is available. Please update to enjoy new features.',
                'category' => 'system',
                'priority' => 'urgent',
                'action_type' => 'external_url',
                'action_url' => 'https://play.google.com/store/apps/details?id=com.hexaride.driver',
                'data' => json_encode(['version' => '2.5.0'])
            ],
            [
                'type' => 'account_update',
                'title' => 'Account Updated',
                'message' => 'Your profile information has been updated successfully.',
                'category' => 'system',
                'priority' => 'normal',
                'action_type' => 'deep_link',
                'action_url' => '/profile',
                'data' => json_encode(['updated_fields' => ['phone', 'email']])
            ],
        ];

        // Insert notifications with different timestamps
        foreach ($notificationTypes as $index => $notification) {
            DB::table('driver_notifications')->insert([
                'id' => Uuid::uuid4()->toString(),
                'driver_id' => $driverId,
                'type' => $notification['type'],
                'title' => $notification['title'],
                'message' => $notification['message'],
                'category' => $notification['category'],
                'priority' => $notification['priority'],
                'action_type' => $notification['action_type'],
                'action_url' => $notification['action_url'],
                'data' => $notification['data'],
                'is_read' => $index % 3 == 0, // Mark some as read
                'read_at' => $index % 3 == 0 ? now()->subDays(rand(1, 7)) : null,
                'expires_at' => now()->addDays(30),
                'created_at' => now()->subDays(rand(0, 14))->subHours(rand(0, 23)),
                'updated_at' => now()->subDays(rand(0, 14))->subHours(rand(0, 23)),
            ]);
        }

        // Create notification settings for this driver
        $existingSettings = DB::table('notification_settings')
            ->where('driver_id', $driverId)
            ->first();

        if (!$existingSettings) {
            DB::table('notification_settings')->insert([
                'id' => Uuid::uuid4()->toString(),
                'driver_id' => $driverId,
                'push_notifications_enabled' => true,
                'email_notifications_enabled' => true,
                'sms_notifications_enabled' => true,
                'trip_requests_enabled' => true,
                'trip_updates_enabled' => true,
                'payment_notifications_enabled' => true,
                'promotional_notifications_enabled' => true,
                'system_notifications_enabled' => true,
                'quiet_hours_enabled' => false,
                'quiet_hours_start' => '22:00',
                'quiet_hours_end' => '08:00',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Test notifications added for driver with phone: +201208673028');
        $this->command->info('Total notifications created: ' . count($notificationTypes));
    }
}
