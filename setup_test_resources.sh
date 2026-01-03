#!/bin/bash

# Setup script to create test resources before running tests
# Run this before test_driver_features_corrected.sh

cd /var/www/laravel/smartlinevps/rateel

php artisan tinker --execute="
\$driver = \Modules\UserManagement\Entities\User::where('user_type', 'driver')->where('phone', '+201208673028')->first();
if (!\$driver) { echo 'Driver not found'; exit; }

echo 'Setting up test resources for driver: ' . \$driver->id . PHP_EOL;

// Delete old test data
DB::table('driver_notifications')->where('driver_id', \$driver->id)->delete();
DB::table('emergency_contacts')->where('driver_id', \$driver->id)->delete();
DB::table('support_tickets')->where('driver_id', \$driver->id)->delete();
DB::table('phone_change_requests')->where('driver_id', \$driver->id)->delete();
DB::table('account_deletion_requests')->where('driver_id', \$driver->id)->delete();
DB::table('promotion_claims')->where('driver_id', \$driver->id)->delete();

// Create FAQ if doesn't exist
\$faq = \App\Models\Faq::first();
if (!\$faq) {
    \$faq = \App\Models\Faq::create([
        'question' => 'How do I update my profile?',
        'answer' => 'You can update your profile from the Profile section in the app.',
        'category' => 'account',
        'is_active' => true,
        'user_type' => 'driver',
    ]);
}
echo 'FAQ_ID=' . \$faq->id . PHP_EOL;

// Create Notification
\$notification = \App\Models\DriverNotification::create([
    'driver_id' => \$driver->id,
    'type' => 'trip',
    'title' => 'Test Notification',
    'message' => 'This is a test notification for API testing.',
    'priority' => 'normal',
    'is_read' => false,
    'expires_at' => now()->addDays(7),
]);
echo 'NOTIFICATION_ID=' . \$notification->id . PHP_EOL;

// Create Support Ticket
\$ticket = \App\Models\SupportTicket::create([
    'driver_id' => \$driver->id,
    'subject' => 'Test Ticket for API Testing',
    'description' => 'This is a test ticket created for API testing purposes.',
    'category' => 'technical',
    'priority' => 'normal',
    'status' => 'open',
]);
echo 'TICKET_ID=' . \$ticket->id . PHP_EOL;

// Create Emergency Contact
\$contactId = \Illuminate\Support\Str::uuid();
DB::table('emergency_contacts')->insert([
    'id' => \$contactId,
    'driver_id' => \$driver->id,
    'name' => 'Test Emergency Contact',
    'relationship' => 'friend',
    'phone' => '+201111111111',
    'is_primary' => false,
    'notify_on_emergency' => true,
    'share_live_location' => false,
    'created_at' => now(),
    'updated_at' => now(),
]);
echo 'CONTACT_ID=' . \$contactId . PHP_EOL;

// Create Content Pages if don't exist
\$pages = [
    ['slug' => 'terms', 'title' => 'Terms & Conditions', 'page_type' => 'terms', 'user_type' => 'both'],
    ['slug' => 'privacy', 'title' => 'Privacy Policy', 'page_type' => 'privacy', 'user_type' => 'both'],
    ['slug' => 'about', 'title' => 'About Us', 'page_type' => 'about', 'user_type' => 'both'],
    ['slug' => 'help', 'title' => 'Help & Support', 'page_type' => 'help', 'user_type' => 'both'],
];
foreach (\$pages as \$page) {
    \$existing = DB::table('content_pages')->where('slug', \$page['slug'])->first();
    if (!\$existing) {
        DB::table('content_pages')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'slug' => \$page['slug'],
            'title' => \$page['title'],
            'content' => 'This is the content for ' . \$page['title'],
            'page_type' => \$page['page_type'],
            'user_type' => \$page['user_type'],
            'is_active' => true,
            'version' => 1,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
echo 'Content pages ready' . PHP_EOL;

// Create Promotion if doesn't exist
\$promotion = DB::table('driver_promotions')->first();
if (!\$promotion) {
    \$promoId = \Illuminate\Support\Str::uuid();
    DB::table('driver_promotions')->insert([
        'id' => \$promoId,
        'title' => 'Test Promotion',
        'description' => 'This is a test promotion for API testing.',
        'action_type' => 'link',
        'is_active' => true,
        'priority' => 10,
        'expires_at' => now()->addDays(30),
        'max_claims' => 100,
        'current_claims' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    \$promotion = DB::table('driver_promotions')->where('id', \$promoId)->first();
}
// Reset claims
DB::table('driver_promotions')->where('id', \$promotion->id)->update(['current_claims' => 0]);
echo 'PROMOTION_ID=' . \$promotion->id . PHP_EOL;

// Create Phone Change Request
\$phoneRequestId = \Illuminate\Support\Str::uuid();
DB::table('phone_change_requests')->insert([
    'id' => \$phoneRequestId,
    'driver_id' => \$driver->id,
    'old_phone' => \$driver->phone,
    'new_phone' => '+209999999999',
    'otp_code' => '123456',
    'old_phone_verified' => false,
    'new_phone_verified' => false,
    'status' => 'pending',
    'expires_at' => now()->addMinutes(30),
    'created_at' => now(),
    'updated_at' => now(),
]);
echo 'PHONE_REQUEST_ID=' . \$phoneRequestId . PHP_EOL;

// Create Account Deletion Request
\$deletionRequestId = \Illuminate\Support\Str::uuid();
DB::table('account_deletion_requests')->insert([
    'id' => \$deletionRequestId,
    'driver_id' => \$driver->id,
    'reason' => 'temporary_break',
    'status' => 'pending',
    'requested_at' => now(),
    'created_at' => now(),
    'updated_at' => now(),
]);
echo 'DELETION_REQUEST_ID=' . \$deletionRequestId . PHP_EOL;

echo 'All test resources created successfully!' . PHP_EOL;
"
