<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\UserManagement\Entities\DriverDetail;

class ResetDriverDailyStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'driver:reset-daily-stats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset daily driver statistics including VIP abuse tracking counters';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Resetting daily driver statistics...');

        try {
            DB::transaction(function () {
                // Reset low category trip counters for VIP abuse prevention
                $affectedRows = DriverDetail::query()
                    ->where('low_category_trips_today', '>', 0)
                    ->update([
                        'low_category_trips_today' => 0,
                        'low_category_trips_date' => now()->toDateString(),
                    ]);

                $this->info("Reset low_category_trips_today for {$affectedRows} drivers.");

                Log::info('Driver daily stats reset completed', [
                    'affected_drivers' => $affectedRows,
                    'reset_date' => now()->toDateString(),
                ]);
            });

            $this->info('Daily driver statistics reset complete.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to reset driver daily stats: ' . $e->getMessage());

            Log::error('Failed to reset driver daily stats', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
