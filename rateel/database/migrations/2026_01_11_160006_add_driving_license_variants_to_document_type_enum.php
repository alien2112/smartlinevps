<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add driving_license_front and driving_license_back to driver_documents type ENUM
 *
 * The controller was already trying to insert these values, but they were missing from the ENUM.
 * This migration adds them to match the pattern of other documents (license_front, license_back, etc.)
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE driver_documents
            MODIFY COLUMN type ENUM(
                'id_front',
                'id_back',
                'license',
                'license_front',
                'license_back',
                'car_photo',
                'car_front',
                'car_back',
                'selfie',
                'national_id',
                'driving_license',
                'driving_license_front',
                'driving_license_back',
                'vehicle_registration',
                'vehicle_photo',
                'profile_photo',
                'criminal_record'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE driver_documents
            MODIFY COLUMN type ENUM(
                'id_front',
                'id_back',
                'license',
                'license_front',
                'license_back',
                'car_photo',
                'car_front',
                'car_back',
                'selfie',
                'national_id',
                'driving_license',
                'vehicle_registration',
                'vehicle_photo',
                'profile_photo',
                'criminal_record'
            ) NOT NULL
        ");
    }
};
