<?php

namespace App\Library;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Entities\UserAccount;
use Modules\TransactionManagement\Entities\Transaction;

class CustomerAddFundToWallet
{
    /**
     * Add funds to customer wallet after successful payment
     *
     * @param object $data Payment data with is_paid, payer_id, payment_amount, etc.
     * @return bool
     */
    public static function handle($data): bool
    {
        if (!$data || !$data->is_paid) {
            return false;
        }

        try {
            DB::beginTransaction();

            $customer = User::find($data->payer_id);
            if (!$customer) {
                DB::rollBack();
                return false;
            }

            $userAccount = UserAccount::firstOrCreate(
                ['user_id' => $customer->id],
                [
                    'wallet_balance' => 0,
                    'payable_balance' => 0,
                    'pending_balance' => 0,
                    'receivable_balance' => 0,
                    'received_balance' => 0,
                    'total_withdrawn' => 0,
                ]
            );

            $amount = (float) $data->payment_amount;
            $previousBalance = $userAccount->wallet_balance;
            $newBalance = $previousBalance + $amount;

            $userAccount->wallet_balance = $newBalance;
            $userAccount->save();

            Transaction::create([
                'id' => Str::uuid(),
                'attribute' => 'wallet_top_up',
                'attribute_id' => $data->id,
                'credit' => $amount,
                'debit' => 0,
                'balance' => $newBalance,
                'user_id' => $customer->id,
                'account' => 'wallet_balance',
                'trx_ref_id' => $data->transaction_id ?? $data->id,
            ]);

            DB::commit();

            if ($customer->fcm_token) {
                try {
                    sendDeviceNotification(
                        fcm_token: $customer->fcm_token,
                        title: translate('Wallet Top-up Successful'),
                        description: translate('Your wallet has been credited with ') . getCurrencyFormat($amount),
                        status: 1,
                        ride_request_id: null,
                        action: 'wallet_top_up',
                        user_id: $customer->id
                    );
                } catch (\Exception $e) {
                    // Notification failure is not critical
                }
            }

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CustomerAddFundToWallet failed: ' . $e->getMessage());
            return false;
        }
    }
}
