<?php

namespace App\Observers;

use Modules\UserManagement\Entities\UserAccount;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Entities\AppNotification;

class UserAccountObserver
{
    /**
     * Handle the UserAccount "created" event.
     */
    public function created(UserAccount $userAccount): void
    {
        //
    }

    /**
     * Handle the UserAccount "updated" event.
     */
    public function updated(UserAccount $userAccount): void
    {
        // Only check if wallet_balance changed
        if ($userAccount->isDirty('wallet_balance')) {
            $this->checkNegativeBalanceLimit($userAccount);
        }
    }

    /**
     * Handle the UserAccount "deleted" event.
     */
    public function deleted(UserAccount $userAccount): void
    {
        //
    }

    /**
     * Handle the UserAccount "restored" event.
     */
    public function restored(UserAccount $userAccount): void
    {
        //
    }

    /**
     * Handle the UserAccount "force deleted" event.
     */
    public function forceDeleted(UserAccount $userAccount): void
    {
        //
    }

    /**
     * Check negative balance limit and trigger warnings/deactivation
     */
    private function checkNegativeBalanceLimit(UserAccount $userAccount): void
    {
        $user = $userAccount->user;

        // Only check for drivers
        if (!$user || $user->user_type !== DRIVER) {
            return;
        }

        $walletBalance = (float) $userAccount->wallet_balance;
        $maxNegativeBalance = (float) ($user->max_negative_balance ?? 200);

        // Only proceed if balance is negative
        if ($walletBalance >= 0) {
            // Reset warning flag if balance becomes positive
            if ($user->negative_balance_warning_sent) {
                $user->update(['negative_balance_warning_sent' => false]);
            }
            return;
        }

        $negativeAmount = abs($walletBalance);
        $warningThreshold = $maxNegativeBalance * 0.75; // 75%

        // Check if at or over 100% limit - Deactivate account
        if ($negativeAmount >= $maxNegativeBalance && $user->is_active) {
            $user->update(['is_active' => 0]);

            // Send deactivation notification
            AppNotification::create([
                'user_id' => $user->id,
                'title' => 'Account Deactivated',
                'description' => "Your account has been deactivated because your negative balance of " . getCurrencyFormat($negativeAmount) . " has reached the maximum limit of " . getCurrencyFormat($maxNegativeBalance) . ". Please add funds to reactivate your account.",
                'type' => 'negative_balance_limit_reached',
                'action' => 'wallet',
            ]);

            // Send push notification if FCM token exists
            if ($user->fcm_token) {
                $this->sendPushNotification($user, 'Account Deactivated', "Your account has been deactivated due to negative balance limit reached.");
            }
        }
        // Check if at or over 75% threshold - Send warning
        elseif ($negativeAmount >= $warningThreshold && !$user->negative_balance_warning_sent) {
            $user->update(['negative_balance_warning_sent' => true]);

            $remainingAmount = $maxNegativeBalance - $negativeAmount;

            // Send warning notification
            AppNotification::create([
                'user_id' => $user->id,
                'title' => 'Negative Balance Warning',
                'description' => "Warning: Your wallet balance is " . getCurrencyFormat($negativeAmount) . " in debt. You have " . getCurrencyFormat($remainingAmount) . " remaining before your account is deactivated. Please add funds soon.",
                'type' => 'negative_balance_warning',
                'action' => 'wallet',
            ]);

            // Send push notification if FCM token exists
            if ($user->fcm_token) {
                $this->sendPushNotification($user, 'Negative Balance Warning', "You have reached 75% of your negative balance limit. Please add funds soon.");
            }
        }
    }

    /**
     * Send push notification to driver
     */
    private function sendPushNotification($user, $title, $message): void
    {
        try {
            $data = [
                'user' => [[
                    'fcm_token' => $user->fcm_token,
                    'user_id' => $user->id,
                ]],
                'title' => $title,
                'description' => $message,
                'ride_request_id' => 'negative_balance_alert',
                'type' => 'negative_balance_alert',
                'action' => 'wallet',
            ];

            \App\Jobs\SendPushNotificationJob::dispatch($data)->onQueue('high');
        } catch (\Exception $e) {
            \Log::error('Failed to send negative balance push notification: ' . $e->getMessage());
        }
    }
}
