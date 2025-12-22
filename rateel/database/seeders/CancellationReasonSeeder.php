<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CancellationReasonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Seeds default cancellation reasons for trips.
     * These can be updated/managed through the admin dashboard.
     *
     * @return void
     */
    public function run(): void
    {
        $defaultReasons = [
            // Customer reasons for accepted rides
            [
                'id' => Str::uuid()->toString(),
                'title' => 'Changed my mind',
                'cancellation_type' => 'accepted_ride',
                'user_type' => 'customer',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid()->toString(),
                'title' => 'Driver is taking too long',
                'cancellation_type' => 'accepted_ride',
                'user_type' => 'customer',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid()->toString(),
                'title' => 'Found another ride',
                'cancellation_type' => 'accepted_ride',
                'user_type' => 'customer',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid()->toString(),
                'title' => 'Wrong pickup location',
                'cancellation_type' => 'accepted_ride',
                'user_type' => 'customer',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid()->toString(),
                'title' => 'Personal emergency',
                'cancellation_type' => 'accepted_ride',
                'user_type' => 'customer',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Customer reasons for ongoing rides
            [
                'id' => Str::uuid()->toString(),
                'title' => 'Driver behavior issue',
                'cancellation_type' => 'ongoing_ride',
                'user_type' => 'customer',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid()->toString(),
                'title' => 'Safety concern',
                'cancellation_type' => 'ongoing_ride',
                'user_type' => 'customer',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid()->toString(),
                'title' => 'Vehicle condition issue',
                'cancellation_type' => 'ongoing_ride',
                'user_type' => 'customer',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid()->toString(),
                'title' => 'Emergency situation',
                'cancellation_type' => 'ongoing_ride',
                'user_type' => 'customer',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Driver reasons for accepted rides
            [
                'id' => Str::uuid()->toString(),
                'title' => 'Customer not responding',
                'cancellation_type' => 'accepted_ride',
                'user_type' => 'driver',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid()->toString(),
                'title' => 'Customer location is wrong',
                'cancellation_type' => 'accepted_ride',
                'user_type' => 'driver',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid()->toString(),
                'title' => 'Vehicle breakdown',
                'cancellation_type' => 'accepted_ride',
                'user_type' => 'driver',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid()->toString(),
                'title' => 'Personal emergency',
                'cancellation_type' => 'accepted_ride',
                'user_type' => 'driver',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid()->toString(),
                'title' => 'Customer requested cancellation',
                'cancellation_type' => 'accepted_ride',
                'user_type' => 'driver',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Driver reasons for ongoing rides
            [
                'id' => Str::uuid()->toString(),
                'title' => 'Customer behavior issue',
                'cancellation_type' => 'ongoing_ride',
                'user_type' => 'driver',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid()->toString(),
                'title' => 'Vehicle problem during trip',
                'cancellation_type' => 'ongoing_ride',
                'user_type' => 'driver',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid()->toString(),
                'title' => 'Road blocked or inaccessible',
                'cancellation_type' => 'ongoing_ride',
                'user_type' => 'driver',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid()->toString(),
                'title' => 'Emergency situation',
                'cancellation_type' => 'ongoing_ride',
                'user_type' => 'driver',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Generic "Other" reason for both users and types
            [
                'id' => Str::uuid()->toString(),
                'title' => 'Other reason',
                'cancellation_type' => 'accepted_ride',
                'user_type' => 'customer',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid()->toString(),
                'title' => 'Other reason',
                'cancellation_type' => 'ongoing_ride',
                'user_type' => 'customer',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid()->toString(),
                'title' => 'Other reason',
                'cancellation_type' => 'accepted_ride',
                'user_type' => 'driver',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid()->toString(),
                'title' => 'Other reason',
                'cancellation_type' => 'ongoing_ride',
                'user_type' => 'driver',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Insert only if the table is empty (preserves existing data)
        if (DB::table('cancellation_reasons')->count() === 0) {
            DB::table('cancellation_reasons')->insert($defaultReasons);
        }
    }
}
