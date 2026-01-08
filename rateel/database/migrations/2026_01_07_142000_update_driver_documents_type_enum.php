<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Update driver_documents type ENUM to include new document types
 *
 * Adds: license_front, license_back, car_front, car_back
 * Removes unique constraint temporarily to allow migration
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop the unique constraint temporarily
        DB::statement('ALTER TABLE driver_documents DROP INDEX idx_driver_document_unique');

        // Update the ENUM to include new document types
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

        // Migrate existing data if needed
        // Convert 'license' to 'license_front' (if any exist)
        DB::statement("
            UPDATE driver_documents
            SET type = 'license_front'
            WHERE type = 'license'
        ");

        // Convert 'car_photo' to 'car_front' (if any exist)
        DB::statement("
            UPDATE driver_documents
            SET type = 'car_front'
            WHERE type = 'car_photo'
        ");

        // Re-add the unique constraint
        // Note: Changed to allow multiple documents of same type (different versions)
        // The is_active flag will determine which one is current
        DB::statement('
            CREATE INDEX idx_driver_document_type ON driver_documents(driver_id, type, is_active)
        ');
    }

    public function down(): void
    {
        // Drop the new index
        DB::statement('ALTER TABLE driver_documents DROP INDEX idx_driver_document_type');

        // Revert data migration
        DB::statement("
            UPDATE driver_documents
            SET type = 'license'
            WHERE type IN ('license_front', 'license_back')
        ");

        DB::statement("
            UPDATE driver_documents
            SET type = 'car_photo'
            WHERE type IN ('car_front', 'car_back')
        ");

        // Revert ENUM to original values
        DB::statement("
            ALTER TABLE driver_documents
            MODIFY COLUMN type ENUM(
                'id_front',
                'id_back',
                'license',
                'car_photo',
                'selfie',
                'national_id',
                'driving_license',
                'vehicle_registration',
                'vehicle_photo',
                'profile_photo',
                'criminal_record'
            ) NOT NULL
        ");

        // Re-add original unique constraint
        DB::statement('
            ALTER TABLE driver_documents
            ADD UNIQUE KEY idx_driver_document_unique (driver_id, type)
        ');
    }
};
