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
            $newState = $this->determineProperState($data);

            // Skip if already in correct state
            if ($driver->onboarding_step === $newState) {
                $this->stats['skipped']++;
                return;
            }

            $oldState = $driver->onboarding_step ?? 'null';

            if (!$isDryRun) {
                // Update the driver
                DB::table('users')
                    ->where('id', $driver->id)
                    ->update([
                        'onboarding_step' => $newState,
                        'updated_at' => now(),
                    ]);

                Log::info('Legacy driver state migrated', [
                    'driver_id' => $driver->id,
                    'phone' => $driver->phone,
                    'old_state' => $oldState,
                    'new_state' => $newState,
                    'reason' => $this->getStateReason($data),
                ]);
            }

            $this->stats[$newState]++;

            if ($this->option('verbose')) {
                $this->line("  {$driver->phone}: {$oldState} → {$newState}");
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

        // Get documents
        $documents = DB::table('driver_documents')
            ->where('driver_id', $driver->id)
            ->where('deleted_at', null)
            ->get();

        // Calculate document verification status
        $totalDocs = $documents->count();
        $verifiedDocs = $documents->where('verified', true)->count();
        $allDocsVerified = $totalDocs > 0 && $totalDocs === $verifiedDocs;

        // Check required profile fields
        $hasCompleteProfile = !empty($driver->first_name)
            && !empty($driver->last_name)
            && !empty($driver->phone);

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
            'is_active' => (bool) $driver->is_active,
        ];
    }

    /**
     * Determine the proper onboarding state based on driver data
     */
    protected function determineProperState(array $data): string
    {
        $driver = $data['driver'];
        $hasVehicle = $data['vehicle_count'] > 0;
        $hasDocs = $data['document_count'] > 0;
        $allDocsVerified = $data['all_documents_verified'];
        $hasCompleteProfile = $data['has_complete_profile'];
        $isActive = $data['is_active'];

        // Priority 1: If driver is active and has vehicle + verified documents → approved
        if ($isActive && $hasVehicle && $hasDocs && $allDocsVerified && $hasCompleteProfile) {
            return 'approved';
        }

        // Priority 2: If driver has vehicle + documents but not all verified → pending_approval
        if ($hasVehicle && $hasDocs && !$allDocsVerified && $hasCompleteProfile) {
            return 'pending_approval';
        }

        // Priority 3: If driver has vehicle but no/incomplete documents → documents
        if ($hasVehicle && (!$hasDocs || $data['verified_document_count'] < $data['document_count']) && $hasCompleteProfile) {
            return 'documents';
        }

        // Priority 4: If driver has complete profile but no vehicle → vehicle_type
        if ($hasCompleteProfile && !$hasVehicle) {
            return 'vehicle_type';
        }

        // Priority 5: If driver has incomplete profile → register_info
        if (!$hasCompleteProfile) {
            return 'register_info';
        }

        // Default: Keep at current or set to vehicle_type
        return 'vehicle_type';
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
        if ($data['is_active'] && $data['vehicle_count'] > 0 && $data['all_documents_verified']) {
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
                ['Documents', $this->stats['documents']],
                ['Vehicle Type', $this->stats['vehicle_type']],
                ['Register Info', $this->stats['register_info']],
                ['Skipped (already correct)', $this->stats['skipped']],
                ['Errors', $this->stats['errors']],
            ]
        );
    }
}
