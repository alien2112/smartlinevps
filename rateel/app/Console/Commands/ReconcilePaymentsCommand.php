<?php

namespace App\Console\Commands;

use App\Services\Payment\PaymentReconciliationService;
use Illuminate\Console\Command;

class ReconcilePaymentsCommand extends Command
{
    protected $signature = 'payments:reconcile
                          {--limit=50 : Number of payments to reconcile}
                          {--force : Force reconciliation even if not due}';

    protected $description = 'Reconcile pending payments with gateway';

    public function handle(PaymentReconciliationService $reconciliationService): int
    {
        $this->info('Starting payment reconciliation...');

        $limit = (int) $this->option('limit');

        try {
            $reconciled = $reconciliationService->reconcileAll($limit);

            $this->info("Successfully reconciled {$reconciled} payments.");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Reconciliation failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
