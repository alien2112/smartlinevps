<?php

namespace App\Console\Commands;

use App\Models\IdempotencyKey;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CleanupIdempotencyKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'idempotency:cleanup {--hours=24 : Number of hours to keep idempotency keys}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup old idempotency keys older than specified hours (default: 24 hours)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hours = $this->option('hours');
        $cutoffTime = Carbon::now()->subHours($hours);

        $deleted = IdempotencyKey::where('created_at', '<', $cutoffTime)->delete();

        $this->info("Cleaned up {$deleted} idempotency keys older than {$hours} hours.");

        return Command::SUCCESS;
    }
}
