<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Normalizes all phone numbers in the users table to international format (+20...)
     * This ensures consistent phone number format across the application.
     */
    public function up(): void
    {
        // Get all users with phone numbers that don't start with '+'
        $users = DB::table('users')
            ->whereNotNull('phone')
            ->where('phone', 'NOT LIKE', '+%')
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($users as $user) {
            $phone = $user->phone;
            $normalizedPhone = $this->normalizePhone($phone);

            // Update the phone number if it changed
            if ($phone !== $normalizedPhone) {
                // Check if normalized phone already exists (including soft-deleted users)
                $existingUser = DB::table('users')
                    ->where('phone', $normalizedPhone)
                    ->where('id', '!=', $user->id)
                    ->first();

                if ($existingUser) {
                    // If conflict with soft-deleted user, delete the soft-deleted user first
                    if ($existingUser->deleted_at !== null) {
                        DB::table('users')->where('id', $existingUser->id)->delete();
                        \Log::info('Deleted soft-deleted user with conflicting phone', [
                            'deleted_user_id' => $existingUser->id,
                            'phone' => $normalizedPhone,
                        ]);
                    } else {
                        // Skip if conflict with active user
                        \Log::warning('Skipped normalizing phone due to conflict with active user', [
                            'user_id' => $user->id,
                            'original_phone' => $phone,
                            'normalized_phone' => $normalizedPhone,
                            'conflicting_user_id' => $existingUser->id,
                        ]);
                        $skipped++;
                        continue;
                    }
                }

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['phone' => $normalizedPhone]);
                $updated++;
            }
        }

        \Log::info('Phone number normalization completed', [
            'total_users_checked' => count($users),
            'updated' => $updated,
            'skipped' => $skipped,
            'migration' => '2026_01_12_210713_normalize_phone_numbers_to_international_format',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not reversible as we don't store the original format
        \Log::warning('Phone number normalization rollback is not supported');
    }

    /**
     * Normalize phone number to international format
     */
    private function normalizePhone(string $phone): string
    {
        // Remove all non-numeric characters except leading +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Ensure + prefix for international format
        if (!str_starts_with($phone, '+')) {
            // Assume Egyptian number if starts with 0
            if (str_starts_with($phone, '0')) {
                $phone = '+20' . substr($phone, 1);
            } elseif (str_starts_with($phone, '20')) {
                $phone = '+' . $phone;
            } else {
                // For numbers that don't start with 0 or 20, assume they need +20 prefix
                $phone = '+20' . $phone;
            }
        }

        return $phone;
    }
};
