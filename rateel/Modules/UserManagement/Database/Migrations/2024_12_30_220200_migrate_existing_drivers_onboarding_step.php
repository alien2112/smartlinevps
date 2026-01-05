<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Migrates existing drivers to set their onboarding_step based on their current data.
     * This ensures the new unified flow works correctly for existing drivers.
     */
    public function up(): void
    {
        // Get all existing drivers
        $drivers = DB::table('users')
            ->where('user_type', 'driver')
            ->get();

        $migrated = 0;
        $approved = 0;

        foreach ($drivers as $driver) {
            $step = $this->determineOnboardingStep($driver);
            
            DB::table('users')
                ->where('id', $driver->id)
                ->update([
                    'onboarding_step' => $step,
                    // Set timestamps based on what data exists
                    'otp_verified_at' => $driver->phone_verified_at ?? ($step !== 'phone' && $step !== 'otp' ? now() : null),
                    'password_set_at' => $driver->password ? now() : null,
                    'register_completed_at' => ($driver->first_name && $driver->last_name && $driver->identification_number) ? now() : null,
                    'vehicle_selected_at' => $this->hasVehicle($driver->id) ? now() : null,
                    'documents_completed_at' => $step === 'approved' || $step === 'pending_approval' ? now() : null,
                ]);

            $migrated++;
            if ($step === 'approved') {
                $approved++;
            }
        }

        Log::info("Driver onboarding migration completed", [
            'total_migrated' => $migrated,
            'approved' => $approved,
        ]);
    }

    /**
     * Determine the correct onboarding step based on existing data
     */
    protected function determineOnboardingStep($driver): string
    {
        // If driver is active and has all data, they're approved
        if ($driver->is_active && 
            $driver->first_name && 
            $driver->last_name && 
            $driver->password &&
            $this->hasVehicle($driver->id)) {
            return 'approved';
        }

        // If driver has vehicle and documents, pending approval
        if ($this->hasVehicle($driver->id) && $this->hasDocuments($driver)) {
            return 'pending_approval';
        }

        // If driver has vehicle but no documents
        if ($this->hasVehicle($driver->id)) {
            return 'documents';
        }

        // If driver has registration info
        if ($driver->first_name && $driver->last_name && $driver->identification_number) {
            return 'vehicle_type';
        }

        // If driver has password but no registration info
        if ($driver->password) {
            return 'register_info';
        }

        // If driver has verified phone but no password
        if ($driver->phone_verified_at) {
            return 'password';
        }

        // Default - need to start from phone verification
        return 'phone';
    }

    /**
     * Check if driver has a vehicle assigned
     */
    protected function hasVehicle(string $driverId): bool
    {
        return DB::table('vehicles')
            ->where('driver_id', $driverId)
            ->exists();
    }

    /**
     * Check if driver has uploaded documents
     */
    protected function hasDocuments($driver): bool
    {
        // Check the legacy document fields on user table
        $hasLegacyDocs = !empty($driver->identification_image) || !empty($driver->driving_license);
        
        // Also check new driver_documents table if it exists
        if (DB::getSchemaBuilder()->hasTable('driver_documents')) {
            $docCount = DB::table('driver_documents')
                ->where('driver_id', $driver->id)
                ->count();
            return $docCount >= 3 || $hasLegacyDocs; // At least 3 documents
        }
        
        return $hasLegacyDocs;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reset all onboarding steps to default
        DB::table('users')
            ->where('user_type', 'driver')
            ->update([
                'onboarding_step' => 'phone',
                'otp_verified_at' => null,
                'password_set_at' => null,
                'register_completed_at' => null,
                'vehicle_selected_at' => null,
                'documents_completed_at' => null,
            ]);
    }
};
