<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * V2 Driver Onboarding - Enhanced driver_documents table
 *
 * Adds:
 * - verification_status enum (pending/approved/rejected) to replace boolean
 * - version tracking for re-uploads
 * - file_hash for deduplication
 * - is_active flag for soft-disabling old versions
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_documents', function (Blueprint $table) {
            // Add verification_status enum if not exists
            if (!Schema::hasColumn('driver_documents', 'verification_status')) {
                $table->enum('verification_status', ['pending', 'approved', 'rejected'])
                    ->default('pending')
                    ->after('verified');
            }

            // Add version tracking for re-uploads
            if (!Schema::hasColumn('driver_documents', 'version')) {
                $table->unsignedTinyInteger('version')->default(1)->after('verification_status');
            }

            // Add is_active flag (false = superseded by new upload)
            if (!Schema::hasColumn('driver_documents', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('version');
            }

            // Add file hash for deduplication
            if (!Schema::hasColumn('driver_documents', 'file_hash')) {
                $table->string('file_hash', 64)->nullable()->after('file_size');
            }

            // Add reviewed_at timestamp
            if (!Schema::hasColumn('driver_documents', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('rejection_reason');
            }
        });

        // Migrate existing data: convert verified boolean to verification_status
        DB::statement("
            UPDATE driver_documents
            SET verification_status = CASE
                WHEN verified = 1 THEN 'approved'
                WHEN rejection_reason IS NOT NULL THEN 'rejected'
                ELSE 'pending'
            END
            WHERE verification_status = 'pending'
        ");

        // Add composite index for efficient lookups
        Schema::table('driver_documents', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes('driver_documents');

            if (!isset($indexes['idx_doc_driver_type_active'])) {
                $table->index(['driver_id', 'type', 'is_active'], 'idx_doc_driver_type_active');
            }

            if (!isset($indexes['idx_doc_status'])) {
                $table->index('verification_status', 'idx_doc_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('driver_documents', function (Blueprint $table) {
            $columns = ['verification_status', 'version', 'is_active', 'file_hash', 'reviewed_at'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('driver_documents', $column)) {
                    $table->dropColumn($column);
                }
            }

            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes('driver_documents');

            if (isset($indexes['idx_doc_driver_type_active'])) {
                $table->dropIndex('idx_doc_driver_type_active');
            }
            if (isset($indexes['idx_doc_status'])) {
                $table->dropIndex('idx_doc_status');
            }
        });
    }
};
