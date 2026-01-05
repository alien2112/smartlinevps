<?php

namespace Modules\UserManagement\Observers;

use App\Services\AdminDashboardCacheService;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Entities\UserAccount;

/**
 * Observer to ensure every User has a UserAccount
 * Prevents null pointer exceptions in financial views
 */
class UserObserver
{
    /**
     * Handle the User "created" event.
     * Automatically creates a UserAccount with zero balances for new users
     *
     * @param User $user
     * @return void
     */
    public function created(User $user): void
    {
        // Only create account if it doesn't exist
        if (!$user->userAccount()->exists()) {
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

            \Log::info('UserAccount auto-created for user', [
                'user_id' => $user->id,
                'user_type' => $user->user_type,
            ]);
        }

        // Clear dashboard cache when customers or drivers are created
        if (in_array($user->user_type, [CUSTOMER, DRIVER])) {
            AdminDashboardCacheService::clearUserCounts();
            AdminDashboardCacheService::clearLeaderBoards();
        }
    }

    /**
     * Handle the User "updated" event.
     *
     * @param User $user
     * @return void
     */
    public function updated(User $user): void
    {
        // Clear dashboard cache when customers or drivers are updated
        if (in_array($user->user_type, [CUSTOMER, DRIVER])) {
            AdminDashboardCacheService::clearLeaderBoards();
        }
    }

    /**
     * Handle the User "deleted" event.
     *
     * @param User $user
     * @return void
     */
    public function deleted(User $user): void
    {
        // Clear dashboard cache when customers or drivers are deleted
        if (in_array($user->user_type, [CUSTOMER, DRIVER])) {
            AdminDashboardCacheService::clearUserCounts();
            AdminDashboardCacheService::clearLeaderBoards();
        }
    }
}
