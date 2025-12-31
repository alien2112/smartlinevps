<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\UserManagement\Entities\UserAccount;
use Modules\TransactionManagement\Entities\Transaction;

/**
 * Wallet Reconciliation Command
 *
 * Compares cached balances with ledger sums and reports discrepancies.
 * Should be run nightly via cron.
 *
 * Usage:
 *   php artisan wallet:reconcile              # Check all wallets
 *   php artisan wallet:reconcile --fix        # Auto-fix discrepancies
 *   php artisan wallet:reconcile --user=UUID  # Check specific user
 */
class WalletReconcileCommand extends Command
{
    protected $signature = 'wallet:reconcile
                            {--user= : Specific user ID to reconcile}
                            {--fix : Automatically fix discrepancies by rebuilding from ledger}
                            {--tolerance=0.01 : Tolerance for discrepancy (default 0.01)}';

    protected $description = 'Reconcile wallet balances against transaction ledger';

    public function handle(): int
    {
        $this->info('Starting wallet reconciliation...');

        $userId = $this->option('user');
        $autoFix = $this->option('fix');
        $tolerance = (float) $this->option('tolerance');

        $query = UserAccount::query();

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $accounts = $query->get();
        $totalChecked = 0;
        $discrepancies = [];
        $fixed = 0;

        $bar = $this->output->createProgressBar($accounts->count());
        $bar->start();

        foreach ($accounts as $account) {
            $result = $this->reconcileAccount($account, $tolerance);
            $totalChecked++;

            if (!$result['is_valid']) {
                $discrepancies[] = $result;

                if ($autoFix) {
                    $this->fixAccount($account, $result);
                    $fixed++;
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Summary
        $this->info("Reconciliation Complete");
        $this->table(['Metric', 'Value'], [
            ['Total Accounts Checked', $totalChecked],
            ['Discrepancies Found', count($discrepancies)],
            ['Auto-Fixed', $fixed],
        ]);

        // Report discrepancies
        if (count($discrepancies) > 0) {
            $this->warn("\nDiscrepancies Found:");
            $this->table(
                ['User ID', 'Cached Balance', 'Ledger Balance', 'Discrepancy'],
                array_map(fn($d) => [
                    substr($d['user_id'], 0, 8) . '...',
                    number_format($d['cached_balance'], 2),
                    number_format($d['ledger_balance'], 2),
                    number_format($d['discrepancy'], 2),
                ], array_slice($discrepancies, 0, 20))
            );

            if (count($discrepancies) > 20) {
                $this->warn("... and " . (count($discrepancies) - 20) . " more");
            }

            // Log all discrepancies
            Log::warning('Wallet reconciliation found discrepancies', [
                'count' => count($discrepancies),
                'discrepancies' => $discrepancies,
            ]);

            return 1;
        }

        $this->info("All wallets are in balance!");
        return 0;
    }

    private function reconcileAccount(UserAccount $account, float $tolerance): array
    {
        $cachedBalance = (float) $account->wallet_balance;

        // Calculate balance from ledger
        $credits = Transaction::where('user_id', $account->user_id)
            ->where('account', 'wallet_balance')
            ->sum('credit');

        $debits = Transaction::where('user_id', $account->user_id)
            ->where('account', 'wallet_balance')
            ->sum('debit');

        $ledgerBalance = (float) ($credits - $debits);
        $discrepancy = round($cachedBalance - $ledgerBalance, 2);

        return [
            'user_id' => $account->user_id,
            'cached_balance' => $cachedBalance,
            'ledger_balance' => $ledgerBalance,
            'discrepancy' => $discrepancy,
            'is_valid' => abs($discrepancy) < $tolerance,
            'total_credits' => (float) $credits,
            'total_debits' => (float) $debits,
        ];
    }

    private function fixAccount(UserAccount $account, array $result): void
    {
        DB::transaction(function () use ($account, $result) {
            $account->wallet_balance = $result['ledger_balance'];
            $account->save();

            Log::info('Wallet balance auto-fixed during reconciliation', [
                'user_id' => $account->user_id,
                'old_balance' => $result['cached_balance'],
                'new_balance' => $result['ledger_balance'],
            ]);
        });
    }
}
