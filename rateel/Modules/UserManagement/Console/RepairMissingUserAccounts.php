<?php

namespace Modules\UserManagement\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Entities\UserAccount;

/**
 * One-time command to repair missing user_accounts records
 *
 * Usage: php artisan users:repair-accounts
 */
class RepairMissingUserAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:repair-accounts
                            {--dry-run : Show what would be done without making changes}
                            {--user-type= : Only repair specific user type (driver, customer, etc)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create missing user_accounts records for users who don\'t have one';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🔍 Scanning for users without user_accounts...');
        $this->newLine();

        $dryRun = $this->option('dry-run');
        $userType = $this->option('user-type');

        // Build query to find users without user_accounts
        $query = User::query()
            ->select('users.*')
            ->leftJoin('user_accounts', 'users.id', '=', 'user_accounts.user_id')
            ->whereNull('user_accounts.id');

        if ($userType) {
            $query->where('users.user_type', $userType);
            $this->info("Filtering by user_type: {$userType}");
        }

        $usersWithoutAccounts = $query->get();

        if ($usersWithoutAccounts->isEmpty()) {
            $this->info('✅ All users already have user_accounts! Nothing to repair.');
            return Command::SUCCESS;
        }

        $count = $usersWithoutAccounts->count();

        // Group by user type for better reporting
        $grouped = $usersWithoutAccounts->groupBy('user_type');

        $this->warn("Found {$count} users without user_accounts:");
        foreach ($grouped as $type => $users) {
            $this->line("  - {$type}: {$users->count()} users");
        }
        $this->newLine();

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();

            $this->table(
                ['User ID', 'Name', 'Email', 'User Type', 'Created At'],
                $usersWithoutAccounts->take(10)->map(function ($user) {
                    return [
                        $user->id,
                        ($user->first_name ?? '') . ' ' . ($user->last_name ?? ''),
                        $user->email,
                        $user->user_type,
                        $user->created_at,
                    ];
                })
            );

            if ($count > 10) {
                $this->line("  ... and " . ($count - 10) . " more");
            }

            $this->newLine();
            $this->info("Run without --dry-run to create {$count} user_accounts");
            return Command::SUCCESS;
        }

        // Confirm before proceeding
        if (!$this->confirm("Create user_accounts for {$count} users?", true)) {
            $this->info('Operation cancelled.');
            return Command::CANCELLED;
        }

        // Create missing accounts in a transaction
        DB::beginTransaction();

        try {
            $created = 0;
            $failed = 0;
            $errors = [];

            $this->info('Creating user_accounts...');
            $progressBar = $this->output->createProgressBar($count);
            $progressBar->start();

            foreach ($usersWithoutAccounts as $user) {
                try {
                    UserAccount::create([
                        'user_id' => $user->id,
                        'payable_balance' => 0,
                        'receivable_balance' => 0,
                        'received_balance' => 0,
                        'pending_balance' => 0,
                        'wallet_balance' => 0,
                        'total_withdrawn' => 0,
                        'referral_earn' => 0,
                    ]);

                    $created++;
                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ];
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            DB::commit();

            // Report results
            $this->info("✅ Successfully created {$created} user_accounts");

            if ($failed > 0) {
                $this->warn("⚠️  Failed to create {$failed} user_accounts");

                if ($this->option('verbose')) {
                    $this->newLine();
                    $this->error('Errors:');
                    foreach ($errors as $error) {
                        $this->line("  User ID {$error['user_id']}: {$error['error']}");
                    }
                }
            }

            // Log to Laravel log
            \Log::info('user_accounts repair completed', [
                'created' => $created,
                'failed' => $failed,
                'user_type_filter' => $userType,
            ]);

            $this->newLine();
            $this->info('✅ Repair completed successfully!');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();

            $this->error('❌ Transaction failed: ' . $e->getMessage());

            \Log::error('user_accounts repair failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
