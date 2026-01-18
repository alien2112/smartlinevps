<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrateLegacyDriverStates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'drivers:migrate-legacy-states
                            {--batch-size=100 : Number of drivers to process per batch}
                            {--dry-run : Preview changes without applying them}
                            {--driver-id= : Process specific driver by ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate legacy drivers to proper onboarding states based on their existing data';

    protected $stats = [
        'total_processed' => 0,
        'approved' => 0,
        'pending_approval' => 0,
        // 'kyc_verification' => 0, // KYC verification - kept for future use
        'documents' => 0,
        'vehicle_type' => 0,
        'register_info' => 0,
        'skipped' => 0,
        'errors' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting legacy driver state migration...');
        $isDryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size');
        $specificDriverId = $this->option('driver-id');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be saved');
        }

        try {
            if ($specificDriverId) {
                $this->processSingleDriver($specificDriverId, $isDryRun);
            } else {
                $this->processAllLegacyDrivers($batchSize, $isDryRun);
            }

            $this->displayStats();
            $this->info('Migration completed successfully!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Migration failed: ' . $e->getMessage());
            Log::error('Legacy driver migration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Process a single driver
     */
    protected function processSingleDriver(string $driverId, bool $isDryRun): void
    {
        $driver = DB::table('users')
            ->where('id', $driverId)
            ->where('user_type', 'driver')
            ->first();

        if (!$driver) {
            $this->error("Driver not found: {$driverId}");
            return;
        }

        $this->info("Processing driver: {$driver->phone} ({$driver->first_name} {$driver->last_name})");
        $this->processDriver($driver, $isDryRun);
    }

    /**
     * Process all legacy drivers in batches
     */
    protected function processAllLegacyDrivers(int $batchSize, bool $isDryRun): void
    {
        // Get all legacy drivers (created before V2 onboarding system)
        $query = DB::table('users')
            ->where('user_type', 'driver')
            ->where('created_at', '<', '2026-01-03')
            ->whereNotIn('onboarding_step', ['approved']);

        $totalDrivers = $query->count();
        $this->info("Found {$totalDrivers} legacy drivers to process");

        $progressBar = $this->output->createProgressBar($totalDrivers);
        $progressBar->start();

        $query->orderBy('created_at', 'asc')->chunk($batchSize, function ($drivers) use ($isDryRun, $progressBar) {
            foreach ($drivers as $driver) {
                $this->processDriver($driver, $isDryRun);
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine();
    }

    /**
     * Process individual driver and determine their proper state
     */
    protected function processDriver($driver, bool $isDryRun): void
    {
        $this->stats['total_processed']++;

        try {
            // Collect driver data
            $data = $this->collectDriverData($driver);

            // Determine proper state
            $stateData = $this->determineProperState($data);
            $newStep = $stateData['step'];
            $newState = $stateData['state'];

            // Skip if already in correct state
            if ($driver->onboarding_step === $newStep && $driver->onboarding_state === $newState) {
                $this->stats['skipped']++;
                return;
            }

            $oldStep = $driver->onboarding_step ?? 'null';
            $oldState = $driver->onboarding_state ?? 'null';

            if (!$isDryRun) {
                // Update the driver with both step and state
                DB::table('users')
                    ->where('id', $driver->id)
                    ->update([
                        'onboarding_step' => $newStep,
                        'onboarding_state' => $newState,
                        'onboarding_state_version' => DB::raw('COALESCE(onboarding_state_version, 0) + 1'),
                        'updated_at' => now(),
                    ]);

                Log::info('Legacy driver state migrated', [
                    'driver_id' => $driver->id,
                    'phone' => $driver->phone,
                    'old_step' => $oldStep,
                    'new_step' => $newStep,
                    'old_state' => $oldState,
                    'new_state' => $newState,
                    'reason' => $this->getStateReason($data),
                ]);
            }

            $this->stats[$newStep]++;

            if ($this->option('verbose')) {
                $this->line("  {$driver->phone}: {$oldStep}/{$oldState} → {$newStep}/{$newState}");
            }

        } catch (\Exception $e) {
            $this->stats['errors']++;
            $this->error("Error processing driver {$driver->id}: " . $e->getMessage());
            Log::error('Driver processing error', [
                'driver_id' => $driver->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Collect all relevant driver data
     */
    protected function collectDriverData($driver): array
    {
        // Get driver details
        $driverDetails = DB::table('driver_details')
            ->where('user_id', $driver->id)
            ->first();

        // Get vehicles
        $vehicles = DB::table('vehicles')
            ->where('driver_id', $driver->id)
            ->where('deleted_at', null)
            ->get();

        // Get documents from NEW system
        $documents = DB::table('driver_documents')
            ->where('driver_id', $driver->id)
            ->where('deleted_at', null)
            ->get();

        // Calculate document verification status from NEW system
        $totalDocs = $documents->count();
        $verifiedDocs = $documents->where('verified', true)->count();

        // Check for LEGACY documents in users table
        $legacyDocCount = 0;
        $legacyDocTypes = [
            'identification_image',
            'old_identification_image',
            'driving_license',
            'vehicle_license',
            'car_front_image',
            'car_back_image',
            'profile_image',
            'other_documents',
        ];

        foreach ($legacyDocTypes as $docType) {
            $value = $driver->{$docType} ?? null;
            if (!empty($value) && $value !== '[]' && $value !== 'null') {
                // Check if it's a JSON array
                $decoded = json_decode($value, true);
                if (is_array($decoded) && count($decoded) > 0) {
                    $legacyDocCount += count($decoded);
                } elseif (!is_array($decoded)) {
                    // It's a single file path
                    $legacyDocCount++;
                }
            }
        }

        // If driver has legacy documents, consider them verified
        if ($legacyDocCount > 0 && $totalDocs === 0) {
            $totalDocs = $legacyDocCount;
            $verifiedDocs = $legacyDocCount;
        }

        $allDocsVerified = $totalDocs > 0 && $totalDocs === $verifiedDocs;

        // Check required profile fields
        $hasCompleteProfile = !empty($driver->first_name)
            && !empty($driver->last_name)
            && !empty($driver->phone);

        // Check if driver has password set
        $hasPassword = !empty($driver->password);

        return [
            'driver' => $driver,
            'driver_details' => $driverDetails,
            'vehicles' => $vehicles,
            'vehicle_count' => $vehicles->count(),
            'documents' => $documents,
            'document_count' => $totalDocs,
            'verified_document_count' => $verifiedDocs,
            'all_documents_verified' => $allDocsVerified,
            'has_complete_profile' => $hasCompleteProfile,
            'has_password' => $hasPassword,
            'has_legacy_documents' => $legacyDocCount > 0,
            'legacy_document_count' => $legacyDocCount,
            'is_active' => (bool) $driver->is_active,
            'is_approved' => (bool) ($driver->is_approved ?? false),
        ];
    }

    /**
     * Determine the proper onboarding state based on driver data
     * Returns both step and state for backward compatibility
     */
    protected function determineProperState(array $data): array
    {
        $driver = $data['driver'];
        $hasPassword = $data['has_password'];
        $hasVehicle = $data['vehicle_count'] > 0;
        $hasDocs = $data['document_count'] > 0;
        $allDocsVerified = $data['all_documents_verified'];
        $hasCompleteProfile = $data['has_complete_profile'];
        $isActive = $data['is_active'];
        $isApproved = $data['is_approved'];

        // Priority 1: If driver is marked as approved OR (active AND has everything) → approved
        // Note: Legacy drivers might be is_approved=1 but is_active=0 due to manual updates
        if ($isApproved && $hasVehicle && $hasDocs && $allDocsVerified && $hasCompleteProfile) {
            return [
                'step' => 'approved',
                'state' => 'approved',
            ];
        }

        // Also set to approved if they're active and have everything (even if is_approved flag is missing)
        if ($isActive && $hasVehicle && $hasDocs && $allDocsVerified && $hasCompleteProfile) {
            return [
                'step' => 'approved',
                'state' => 'approved',
            ];
        }

        // Priority 2: If driver has vehicle + documents but not all verified → pending_approval
        // Note: In future, this could be kyc_verification if you want to enable KYC step
        if ($hasVehicle && $hasDocs && $hasCompleteProfile) {
            if (!$allDocsVerified) {
                return [
                    'step' => 'pending_approval',
                    'state' => 'pending_approval',
                ];
            }

            // KYC Verification step (currently disabled, but logic kept for future use)
            // Uncomment this block when you want to enable KYC verification:
            /*
            if ($allDocsVerified && !$driver->kyc_verified_at) {
                return [
                    'step' => 'kyc_verification',
                    'state' => 'kyc_verification',
                ];
            }
            */

            // All documents verified, skip KYC for now, go to pending_approval
            return [
                'step' => 'pending_approval',
                'state' => 'pending_approval',
            ];
        }

        // Priority 3: If driver has vehicle but no/incomplete documents → documents
        if ($hasVehicle && $hasCompleteProfile) {
            if (!$hasDocs || $data['verified_document_count'] < $data['document_count']) {
                return [
                    'step' => 'documents',
                    'state' => 'vehicle_selected', // They've selected vehicle, now need docs
                ];
            }
        }

        // Priority 4: If driver has complete profile but no vehicle → vehicle_type
        if ($hasCompleteProfile && !$hasVehicle) {
            return [
                'step' => 'vehicle_type',
                'state' => 'profile_complete',
            ];
        }

        // Priority 5: If driver has password but incomplete profile → register_info
        if ($hasPassword && !$hasCompleteProfile) {
            return [
                'step' => 'register_info',
                'state' => 'password_set',
            ];
        }

        // Priority 6: If driver has no password and incomplete profile → password/register_info
        if (!$hasCompleteProfile) {
            return [
                'step' => 'register_info',
                'state' => 'otp_verified', // Legacy drivers likely verified OTP already
            ];
        }

        // Default: Keep at vehicle_type
        return [
            'step' => 'vehicle_type',
            'state' => 'profile_complete',
        ];
    }

    /**
     * Get human-readable reason for state assignment
     */
    protected function getStateReason(array $data): string
    {
        $reasons = [];

        if (!$data['has_complete_profile']) {
            $reasons[] = 'incomplete_profile';
        }
        if ($data['vehicle_count'] === 0) {
            $reasons[] = 'no_vehicle';
        }
        if ($data['document_count'] === 0) {
            $reasons[] = 'no_documents';
        }
        if ($data['document_count'] > 0 && !$data['all_documents_verified']) {
            $reasons[] = 'documents_not_verified';
        }
        if ($data['has_legacy_documents'] ?? false) {
            $reasons[] = 'has_legacy_docs_' . $data['legacy_document_count'];
        }
        if ($data['is_approved'] && $data['is_active'] && $data['vehicle_count'] > 0 && $data['all_documents_verified']) {
            $reasons[] = 'fully_complete';
        }

        return implode(', ', $reasons) ?: 'no_issues';
    }

    /**
     * Display migration statistics
     */
    protected function displayStats(): void
    {
        $this->newLine();
        $this->info('=== Migration Statistics ===');
        $this->table(
            ['Status', 'Count'],
            [
                ['Total Processed', $this->stats['total_processed']],
                ['Approved', $this->stats['approved']],
                ['Pending Approval', $this->stats['pending_approval']],
                // ['KYC Verification', $this->stats['kyc_verification'] ?? 0], // Disabled for now
                ['Documents', $this->stats['documents']],
                ['Vehicle Type', $this->stats['vehicle_type']],
                ['Register Info', $this->stats['register_info']],
                ['Skipped (already correct)', $this->stats['skipped']],
                ['Errors', $this->stats['errors']],
            ]
        );

        $this->newLine();
        $this->comment('Note: KYC verification step is currently disabled. Drivers with verified documents');
        $this->comment('are moved directly to pending_approval. Uncomment KYC logic in code to enable.');
    }
}
