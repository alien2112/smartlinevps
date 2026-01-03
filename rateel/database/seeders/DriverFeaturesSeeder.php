<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DriverFeaturesSeeder extends Seeder
{
    /**
     * Seed all driver app features data
     */
    public function run(): void
    {
        $this->seedPromotions();
        $this->seedAchievements();
        $this->seedBadges();
        $this->seedFAQs();
        $this->seedContentPages();

        $this->command->info('Driver features seeded successfully!');
    }

    /**
     * Seed promotional banners
     */
    private function seedPromotions(): void
    {
        $promotions = [
            [
                'id' => Str::uuid(),
                'title' => 'Welcome Bonus',
                'description' => 'Complete 10 trips this week and earn a bonus reward!',
                'terms_conditions' => 'Complete a minimum of 10 trips within 7 days of registration. Bonus will be credited within 48 hours.',
                'image_url' => null,
                'action_type' => 'claim',
                'action_url' => null,
                'priority' => 100,
                'is_active' => true,
                'starts_at' => now(),
                'expires_at' => now()->addMonths(3),
                'max_claims' => null,
                'current_claims' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'title' => 'Peak Hours Bonus',
                'description' => 'Earn 20% extra during peak hours (7-9 AM & 5-8 PM)',
                'terms_conditions' => 'Bonus applies automatically to trips started during peak hours.',
                'image_url' => null,
                'action_type' => 'link',
                'action_url' => null,
                'priority' => 90,
                'is_active' => true,
                'starts_at' => now(),
                'expires_at' => now()->addYear(),
                'max_claims' => null,
                'current_claims' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'title' => 'Referral Program',
                'description' => 'Refer a driver and earn rewards when they complete their first trip!',
                'terms_conditions' => 'Referred driver must complete at least one trip within 30 days.',
                'image_url' => null,
                'action_type' => 'deep_link',
                'action_url' => 'app://referral',
                'priority' => 80,
                'is_active' => true,
                'starts_at' => now(),
                'expires_at' => null,
                'max_claims' => null,
                'current_claims' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'title' => 'Weekend Warrior',
                'description' => 'Complete 20 trips this weekend for exclusive rewards',
                'terms_conditions' => 'Trips must be completed between Friday 6 PM and Sunday 11:59 PM.',
                'image_url' => null,
                'action_type' => 'claim',
                'action_url' => null,
                'priority' => 70,
                'is_active' => true,
                'starts_at' => now(),
                'expires_at' => now()->addMonths(6),
                'max_claims' => 1000,
                'current_claims' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'title' => 'Safety First',
                'description' => 'Complete safety training and earn bonus points',
                'terms_conditions' => 'Complete all safety modules in the training center.',
                'image_url' => null,
                'action_type' => 'deep_link',
                'action_url' => 'app://training',
                'priority' => 60,
                'is_active' => true,
                'starts_at' => now(),
                'expires_at' => null,
                'max_claims' => null,
                'current_claims' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($promotions as $promo) {
            DB::table('driver_promotions')->updateOrInsert(
                ['title' => $promo['title']],
                $promo
            );
        }

        $this->command->info('  - Promotions seeded: ' . count($promotions));
    }

    /**
     * Seed achievements
     */
    private function seedAchievements(): void
    {
        $achievements = [
            // Trip Achievements (Bronze)
            ['key' => 'first_trip', 'title' => 'First Trip', 'description' => 'Complete your first trip', 'category' => 'trips', 'points' => 10, 'tier' => 1, 'requirements' => ['trips' => 1], 'order' => 1],
            ['key' => 'ten_trips', 'title' => 'Getting Started', 'description' => 'Complete 10 trips', 'category' => 'trips', 'points' => 25, 'tier' => 1, 'requirements' => ['trips' => 10], 'order' => 2],
            ['key' => 'fifty_trips', 'title' => 'Road Regular', 'description' => 'Complete 50 trips', 'category' => 'trips', 'points' => 50, 'tier' => 1, 'requirements' => ['trips' => 50], 'order' => 3],

            // Trip Achievements (Silver)
            ['key' => 'hundred_trips', 'title' => 'Century Driver', 'description' => 'Complete 100 trips', 'category' => 'trips', 'points' => 100, 'tier' => 2, 'requirements' => ['trips' => 100], 'order' => 4],
            ['key' => 'two_fifty_trips', 'title' => 'Road Warrior', 'description' => 'Complete 250 trips', 'category' => 'trips', 'points' => 150, 'tier' => 2, 'requirements' => ['trips' => 250], 'order' => 5],
            ['key' => 'five_hundred_trips', 'title' => 'Seasoned Driver', 'description' => 'Complete 500 trips', 'category' => 'trips', 'points' => 250, 'tier' => 2, 'requirements' => ['trips' => 500], 'order' => 6],

            // Trip Achievements (Gold)
            ['key' => 'thousand_trips', 'title' => 'Pro Driver', 'description' => 'Complete 1,000 trips', 'category' => 'trips', 'points' => 500, 'tier' => 3, 'requirements' => ['trips' => 1000], 'order' => 7],
            ['key' => 'two_thousand_trips', 'title' => 'Elite Driver', 'description' => 'Complete 2,000 trips', 'category' => 'trips', 'points' => 750, 'tier' => 3, 'requirements' => ['trips' => 2000], 'order' => 8],

            // Trip Achievements (Platinum)
            ['key' => 'five_thousand_trips', 'title' => 'Legend', 'description' => 'Complete 5,000 trips', 'category' => 'trips', 'points' => 1000, 'tier' => 4, 'requirements' => ['trips' => 5000], 'order' => 9],
            ['key' => 'ten_thousand_trips', 'title' => 'Master Driver', 'description' => 'Complete 10,000 trips', 'category' => 'trips', 'points' => 2000, 'tier' => 4, 'requirements' => ['trips' => 10000], 'order' => 10],

            // Ratings Achievements
            ['key' => 'first_five_star', 'title' => 'First Five Star', 'description' => 'Receive your first 5-star rating', 'category' => 'ratings', 'points' => 15, 'tier' => 1, 'requirements' => ['five_star_ratings' => 1], 'order' => 1],
            ['key' => 'ten_five_stars', 'title' => 'Rising Star', 'description' => 'Receive 10 five-star ratings', 'category' => 'ratings', 'points' => 50, 'tier' => 1, 'requirements' => ['five_star_ratings' => 10], 'order' => 2],
            ['key' => 'fifty_five_stars', 'title' => 'Customer Favorite', 'description' => 'Receive 50 five-star ratings', 'category' => 'ratings', 'points' => 100, 'tier' => 2, 'requirements' => ['five_star_ratings' => 50], 'order' => 3],
            ['key' => 'hundred_five_stars', 'title' => 'Superstar', 'description' => 'Receive 100 five-star ratings', 'category' => 'ratings', 'points' => 200, 'tier' => 3, 'requirements' => ['five_star_ratings' => 100], 'order' => 4],
            ['key' => 'perfect_rating', 'title' => 'Perfect Score', 'description' => 'Maintain 5.0 rating with 50+ reviews', 'category' => 'ratings', 'points' => 500, 'tier' => 4, 'requirements' => ['avg_rating' => 5.0, 'min_reviews' => 50], 'order' => 5],

            // Earnings Achievements
            ['key' => 'first_earning', 'title' => 'First Earnings', 'description' => 'Complete your first paid trip', 'category' => 'earnings', 'points' => 10, 'tier' => 1, 'requirements' => ['total_earnings' => 1], 'order' => 1],
            ['key' => 'hundred_earnings', 'title' => 'Money Maker', 'description' => 'Earn 100 in total', 'category' => 'earnings', 'points' => 30, 'tier' => 1, 'requirements' => ['total_earnings' => 100], 'order' => 2],
            ['key' => 'thousand_earnings', 'title' => 'Earning Pro', 'description' => 'Earn 1,000 in total', 'category' => 'earnings', 'points' => 100, 'tier' => 2, 'requirements' => ['total_earnings' => 1000], 'order' => 3],
            ['key' => 'five_thousand_earnings', 'title' => 'Top Earner', 'description' => 'Earn 5,000 in total', 'category' => 'earnings', 'points' => 250, 'tier' => 3, 'requirements' => ['total_earnings' => 5000], 'order' => 4],
            ['key' => 'ten_thousand_earnings', 'title' => 'High Roller', 'description' => 'Earn 10,000 in total', 'category' => 'earnings', 'points' => 500, 'tier' => 4, 'requirements' => ['total_earnings' => 10000], 'order' => 5],

            // Milestone Achievements
            ['key' => 'week_streak', 'title' => 'Weekly Warrior', 'description' => 'Complete trips every day for a week', 'category' => 'milestones', 'points' => 100, 'tier' => 2, 'requirements' => ['streak_days' => 7], 'order' => 1],
            ['key' => 'month_streak', 'title' => 'Monthly Champion', 'description' => 'Complete trips every day for a month', 'category' => 'milestones', 'points' => 500, 'tier' => 3, 'requirements' => ['streak_days' => 30], 'order' => 2],
            ['key' => 'early_bird', 'title' => 'Early Bird', 'description' => 'Complete 10 trips before 7 AM', 'category' => 'milestones', 'points' => 50, 'tier' => 1, 'requirements' => ['early_trips' => 10], 'order' => 3],
            ['key' => 'night_owl', 'title' => 'Night Owl', 'description' => 'Complete 10 trips after 10 PM', 'category' => 'milestones', 'points' => 50, 'tier' => 1, 'requirements' => ['night_trips' => 10], 'order' => 4],
            ['key' => 'weekend_hero', 'title' => 'Weekend Hero', 'description' => 'Complete 50 weekend trips', 'category' => 'milestones', 'points' => 75, 'tier' => 2, 'requirements' => ['weekend_trips' => 50], 'order' => 5],
            ['key' => 'zero_cancellations', 'title' => 'Reliable Driver', 'description' => 'Complete 100 trips without cancellation', 'category' => 'milestones', 'points' => 200, 'tier' => 3, 'requirements' => ['trips_without_cancel' => 100], 'order' => 6],
            ['key' => 'long_distance', 'title' => 'Long Haul', 'description' => 'Complete a trip over 50km', 'category' => 'milestones', 'points' => 30, 'tier' => 1, 'requirements' => ['trip_distance_km' => 50], 'order' => 7],
            ['key' => 'referral_master', 'title' => 'Referral Master', 'description' => 'Refer 10 drivers who complete their first trip', 'category' => 'milestones', 'points' => 300, 'tier' => 3, 'requirements' => ['successful_referrals' => 10], 'order' => 8],
        ];

        foreach ($achievements as $achievement) {
            DB::table('achievements')->updateOrInsert(
                ['key' => $achievement['key']],
                [
                    'id' => Str::uuid(),
                    'key' => $achievement['key'],
                    'title' => $achievement['title'],
                    'description' => $achievement['description'],
                    'icon' => null,
                    'category' => $achievement['category'],
                    'points' => $achievement['points'],
                    'tier' => $achievement['tier'],
                    'requirements' => json_encode($achievement['requirements']),
                    'is_active' => true,
                    'order' => $achievement['order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('  - Achievements seeded: ' . count($achievements));
    }

    /**
     * Seed badges
     */
    private function seedBadges(): void
    {
        $badges = [
            // Common Badges
            ['key' => 'verified_driver', 'title' => 'Verified Driver', 'description' => 'All documents verified', 'color' => '#4CAF50', 'rarity' => 'common', 'criteria' => ['documents_verified' => true]],
            ['key' => 'profile_complete', 'title' => 'Profile Complete', 'description' => 'Complete all profile information', 'color' => '#2196F3', 'rarity' => 'common', 'criteria' => ['profile_complete' => true]],
            ['key' => 'safety_trained', 'title' => 'Safety Trained', 'description' => 'Complete safety training', 'color' => '#FF9800', 'rarity' => 'common', 'criteria' => ['safety_training' => true]],

            // Rare Badges
            ['key' => 'top_weekly', 'title' => 'Top Weekly Driver', 'description' => 'Ranked in top 10 drivers this week', 'color' => '#9C27B0', 'rarity' => 'rare', 'criteria' => ['weekly_rank' => 10]],
            ['key' => 'high_acceptor', 'title' => 'High Acceptor', 'description' => 'Maintain 95%+ acceptance rate', 'color' => '#00BCD4', 'rarity' => 'rare', 'criteria' => ['acceptance_rate' => 95]],
            ['key' => 'quick_responder', 'title' => 'Quick Responder', 'description' => 'Average response time under 30 seconds', 'color' => '#E91E63', 'rarity' => 'rare', 'criteria' => ['avg_response_time' => 30]],
            ['key' => 'consistent_driver', 'title' => 'Consistent Driver', 'description' => 'Active for 30 consecutive days', 'color' => '#3F51B5', 'rarity' => 'rare', 'criteria' => ['active_days' => 30]],

            // Epic Badges
            ['key' => 'top_monthly', 'title' => 'Top Monthly Driver', 'description' => 'Ranked #1 in monthly leaderboard', 'color' => '#FF5722', 'rarity' => 'epic', 'criteria' => ['monthly_rank' => 1]],
            ['key' => 'five_star_club', 'title' => '5-Star Club', 'description' => 'Maintain 5.0 rating for 30 days', 'color' => '#FFC107', 'rarity' => 'epic', 'criteria' => ['perfect_rating_days' => 30]],
            ['key' => 'marathon_driver', 'title' => 'Marathon Driver', 'description' => 'Complete 50 trips in one day', 'color' => '#673AB7', 'rarity' => 'epic', 'criteria' => ['daily_trips' => 50]],
            ['key' => 'customer_choice', 'title' => 'Customer Choice', 'description' => 'Receive 50 positive comments', 'color' => '#8BC34A', 'rarity' => 'epic', 'criteria' => ['positive_comments' => 50]],

            // Legendary Badges
            ['key' => 'hall_of_fame', 'title' => 'Hall of Fame', 'description' => 'Complete 10,000 trips with 4.9+ rating', 'color' => '#FFD700', 'rarity' => 'legendary', 'criteria' => ['trips' => 10000, 'min_rating' => 4.9]],
            ['key' => 'elite_partner', 'title' => 'Elite Partner', 'description' => 'Top 1% of all drivers for 6 months', 'color' => '#B8860B', 'rarity' => 'legendary', 'criteria' => ['top_percentile_months' => 6]],
            ['key' => 'founding_driver', 'title' => 'Founding Driver', 'description' => 'One of the first 100 drivers', 'color' => '#C0C0C0', 'rarity' => 'legendary', 'criteria' => ['driver_number' => 100]],
        ];

        foreach ($badges as $badge) {
            DB::table('badges')->updateOrInsert(
                ['key' => $badge['key']],
                [
                    'id' => Str::uuid(),
                    'key' => $badge['key'],
                    'title' => $badge['title'],
                    'description' => $badge['description'],
                    'icon' => null,
                    'color' => $badge['color'],
                    'rarity' => $badge['rarity'],
                    'criteria' => json_encode($badge['criteria']),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('  - Badges seeded: ' . count($badges));
    }

    /**
     * Seed FAQs
     */
    private function seedFAQs(): void
    {
        $faqs = [
            // General
            ['category' => 'general', 'question' => 'How do I update my profile?', 'answer' => 'Go to Profile > Edit Profile. You can update your photo, name, and contact information. Some fields may require verification.', 'order' => 1],
            ['category' => 'general', 'question' => 'How do I change my password?', 'answer' => 'Go to Settings > Security > Change Password. Enter your current password and set a new one.', 'order' => 2],
            ['category' => 'general', 'question' => 'How do I contact support?', 'answer' => 'You can contact support through the Help section in the app, or open a support ticket for detailed assistance.', 'order' => 3],
            ['category' => 'general', 'question' => 'What are the app notification settings?', 'answer' => 'Go to Settings > Notifications to customize which notifications you receive via push, email, or SMS.', 'order' => 4],

            // Trips
            ['category' => 'trips', 'question' => 'How do I accept a trip request?', 'answer' => 'When you receive a trip request, you will see a popup with trip details. Tap Accept to take the trip, or Decline to pass.', 'order' => 1],
            ['category' => 'trips', 'question' => 'What happens if I cancel a trip?', 'answer' => 'Cancelling trips affects your acceptance rate and may result in temporary penalties. Cancel only when absolutely necessary.', 'order' => 2],
            ['category' => 'trips', 'question' => 'How is the fare calculated?', 'answer' => 'Fares are calculated based on base fare, distance, time, and any applicable surge pricing. You can see the breakdown after each trip.', 'order' => 3],
            ['category' => 'trips', 'question' => 'What should I do if a customer is not at the pickup location?', 'answer' => 'Wait for the designated time, then you can mark the customer as no-show. Make sure to call the customer first.', 'order' => 4],
            ['category' => 'trips', 'question' => 'How do I report an issue with a trip?', 'answer' => 'Go to Trip History, select the trip, and tap Report Issue. Describe the problem and our team will review it.', 'order' => 5],

            // Payments
            ['category' => 'payments', 'question' => 'When do I get paid?', 'answer' => 'Earnings are added to your wallet after each completed trip. You can withdraw when your balance meets the minimum threshold.', 'order' => 1],
            ['category' => 'payments', 'question' => 'How do I withdraw my earnings?', 'answer' => 'Go to Wallet > Withdraw. Select your payment method and enter the amount. Withdrawals are processed within 1-3 business days.', 'order' => 2],
            ['category' => 'payments', 'question' => 'What is the commission rate?', 'answer' => 'Commission rates vary by trip type and promotions. Check your earnings breakdown after each trip for details.', 'order' => 3],
            ['category' => 'payments', 'question' => 'How do I update my bank account?', 'answer' => 'Go to Profile > Payment Methods. You can add, edit, or remove bank accounts and other payment methods.', 'order' => 4],
            ['category' => 'payments', 'question' => 'Why is my withdrawal pending?', 'answer' => 'Withdrawals are processed within 1-3 business days. If pending longer, contact support with your withdrawal ID.', 'order' => 5],

            // Account
            ['category' => 'account', 'question' => 'How do I update my documents?', 'answer' => 'Go to Profile > Documents. Select the document you want to update and upload a new copy.', 'order' => 1],
            ['category' => 'account', 'question' => 'Why is my account suspended?', 'answer' => 'Accounts can be suspended for various reasons including document issues, low ratings, or policy violations. Contact support for details.', 'order' => 2],
            ['category' => 'account', 'question' => 'How do I delete my account?', 'answer' => 'Go to Settings > Account > Delete Account. There is a 30-day grace period where you can cancel the deletion.', 'order' => 3],
            ['category' => 'account', 'question' => 'How do I change my phone number?', 'answer' => 'Go to Settings > Account > Change Phone. You will need to verify both your old and new phone numbers via OTP.', 'order' => 4],

            // Vehicle
            ['category' => 'vehicle', 'question' => 'How do I add a new vehicle?', 'answer' => 'Go to Vehicle > Add Vehicle. Enter vehicle details and upload required documents. New vehicles require verification.', 'order' => 1],
            ['category' => 'vehicle', 'question' => 'How do I switch between vehicles?', 'answer' => 'Go to Vehicle and select "Set as Primary" on the vehicle you want to use. You can only drive with your primary vehicle.', 'order' => 2],
            ['category' => 'vehicle', 'question' => 'What documents are required for my vehicle?', 'answer' => 'Required documents include registration, insurance, and inspection certificate. Requirements may vary by region.', 'order' => 3],
            ['category' => 'vehicle', 'question' => 'My vehicle document is expiring. What should I do?', 'answer' => 'Update your documents before they expire to avoid interruptions. Go to Vehicle > Documents and upload renewed documents.', 'order' => 4],
        ];

        foreach ($faqs as $faq) {
            DB::table('faqs')->updateOrInsert(
                ['question' => $faq['question']],
                [
                    'id' => Str::uuid(),
                    'category' => $faq['category'],
                    'question' => $faq['question'],
                    'answer' => $faq['answer'],
                    'order' => $faq['order'],
                    'helpful_count' => 0,
                    'not_helpful_count' => 0,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('  - FAQs seeded: ' . count($faqs));
    }

    /**
     * Seed content pages (terms, privacy, etc.)
     */
    private function seedContentPages(): void
    {
        $pages = [
            [
                'slug' => 'terms',
                'title' => 'Terms and Conditions',
                'content' => '<h2>Terms of Service</h2><p>Welcome to our driver platform. By using our services, you agree to these terms and conditions.</p><h3>1. Account Registration</h3><p>You must provide accurate information when creating your account.</p><h3>2. Driver Responsibilities</h3><p>As a driver, you are responsible for maintaining valid licenses and insurance.</p><h3>3. Service Usage</h3><p>Use the platform only for legitimate ride-sharing purposes.</p>',
                'page_type' => 'legal',
            ],
            [
                'slug' => 'privacy',
                'title' => 'Privacy Policy',
                'content' => '<h2>Privacy Policy</h2><p>Your privacy is important to us.</p><h3>Data Collection</h3><p>We collect information necessary to provide our services.</p><h3>Data Usage</h3><p>Your data is used to improve our platform and provide better service.</p><h3>Data Protection</h3><p>We implement security measures to protect your information.</p>',
                'page_type' => 'legal',
            ],
            [
                'slug' => 'help',
                'title' => 'Help Center',
                'content' => '<h2>Help Center</h2><p>Welcome to our Help Center. Find answers to common questions here.</p><h3>Getting Started</h3><p>Learn how to use the app and start accepting trips.</p><h3>Common Issues</h3><p>Find solutions to frequently encountered problems.</p>',
                'page_type' => 'help',
            ],
            [
                'slug' => 'about',
                'title' => 'About Us',
                'content' => '<h2>About Our Platform</h2><p>We are dedicated to providing a reliable ride-sharing platform.</p><h3>Our Mission</h3><p>Connect drivers with passengers for safe and efficient transportation.</p>',
                'page_type' => 'info',
            ],
            [
                'slug' => 'safety',
                'title' => 'Safety Guidelines',
                'content' => '<h2>Safety Guidelines</h2><p>Safety is our top priority.</p><h3>Vehicle Safety</h3><p>Keep your vehicle well-maintained and clean.</p><h3>Personal Safety</h3><p>Follow all traffic laws and safety protocols.</p><h3>Emergency Procedures</h3><p>Know how to handle emergencies and contact support.</p>',
                'page_type' => 'safety',
            ],
        ];

        foreach ($pages as $page) {
            DB::table('content_pages')->updateOrInsert(
                ['slug' => $page['slug']],
                [
                    'id' => Str::uuid(),
                    'slug' => $page['slug'],
                    'title' => $page['title'],
                    'content' => $page['content'],
                    'page_type' => $page['page_type'],
                    'user_type' => 'driver',
                    'is_active' => true,
                    'version' => 1,
                    'published_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('  - Content pages seeded: ' . count($pages));
    }
}
