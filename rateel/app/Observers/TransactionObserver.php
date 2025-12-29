<?php

namespace App\Observers;

use App\Services\AdminDashboardCacheService;
use Modules\TransactionManagement\Entities\Transaction;

class TransactionObserver
{
    /**
     * Handle the Transaction "created" event.
     */
    public function created(Transaction $transaction): void
    {
        // Clear transactions cache for the user
        AdminDashboardCacheService::clearTransactions($transaction->user_id);
    }

    /**
     * Handle the Transaction "updated" event.
     */
    public function updated(Transaction $transaction): void
    {
        // Clear transactions cache for the user
        AdminDashboardCacheService::clearTransactions($transaction->user_id);
    }

    /**
     * Handle the Transaction "deleted" event.
     */
    public function deleted(Transaction $transaction): void
    {
        // Clear transactions cache for the user
        AdminDashboardCacheService::clearTransactions($transaction->user_id);
    }

    /**
     * Handle the Transaction "restored" event.
     */
    public function restored(Transaction $transaction): void
    {
        // Clear transactions cache for the user
        AdminDashboardCacheService::clearTransactions($transaction->user_id);
    }

    /**
     * Handle the Transaction "force deleted" event.
     */
    public function forceDeleted(Transaction $transaction): void
    {
        // Clear transactions cache for the user
        AdminDashboardCacheService::clearTransactions($transaction->user_id);
    }
}
