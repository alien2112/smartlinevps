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
        $schedule->command('idempotency:cleanup')->hourly();

        // Database backups - daily at 2:00 AM
        $schedule->command('backup:run')->daily()->at('02:00');

        // FCM token cleanup - weekly on Sundays at 3:00 AM
        // Removes stale tokens from inactive users to reduce failed notifications
        $schedule->job(new \App\Jobs\CleanupInvalidFcmTokensJob())
            ->weekly()
            ->sundays()
            ->at('03:00')
            ->withoutOverlapping();

        // Queue health check - every 5 minutes
        // Logs queue depth for monitoring
        $schedule->call(function () {
            $queueSize = \Illuminate\Support\Facades\Redis::llen('queues:default');
            $highQueueSize = \Illuminate\Support\Facades\Redis::llen('queues:high');
            
            if ($queueSize > 1000 || $highQueueSize > 100) {
                \Illuminate\Support\Facades\Log::warning('Queue depth high', [
                    'default_queue' => $queueSize,
                    'high_queue' => $highQueueSize,
                ]);
            }
        })->everyFiveMinutes();
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
