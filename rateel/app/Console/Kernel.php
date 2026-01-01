<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('trip-request:cancel')->everyMinute();
        $schedule->command('lost-item:close-pending')->hourly();

        // Reset daily driver stats (VIP abuse tracking) at midnight
        $schedule->command('driver:reset-daily-stats')->dailyAt('00:00');

        // Wallet reconciliation - run at 3 AM daily
        $schedule->command('wallet:reconcile')
            ->dailyAt('03:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/wallet-reconcile.log'));

        // Coupon Management: Expire stale coupon reservations every 5 minutes
        $schedule->command('coupons:expire-reservations')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/coupon-expire.log'));

        // Offer Management: Deactivate expired offers daily at midnight
        $schedule->command('offers:deactivate-expired')
            ->daily()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/offers-expire.log'));
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
