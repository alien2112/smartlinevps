<?php

namespace App\Console\Commands;

use App\Services\LocationConfigService;
use Illuminate\Console\Command;

class InitLocationConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'location:init-config {--preset=normal : Preset to initialize (normal, high_traffic, emergency)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize location tracking configuration in Redis';

    /**
     * Execute the console command.
     */
    public function handle(LocationConfigService $service)
    {
        $preset = $this->option('preset');

        $this->info("Initializing location configuration with preset: {$preset}");

        $success = $service->setActivePreset($preset);

        if ($success) {
            $config = $service->getActiveConfig();

            $this->info('✓ Configuration initialized successfully');
            $this->newLine();

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Active Preset', $service->getCurrentPreset()],
                    ['Idle Interval', $config['idle']['interval_sec'] . 's'],
                    ['Idle Distance', $config['idle']['distance_m'] . 'm'],
                    ['Searching Interval', $config['searching']['interval_sec'] . 's'],
                    ['Searching Distance', $config['searching']['distance_m'] . 'm'],
                    ['On Trip Interval', $config['on_trip']['interval_sec'] . 's'],
                    ['On Trip Distance', $config['on_trip']['distance_m'] . 'm'],
                ]
            );

            $this->newLine();
            $this->info('Mobile apps will fetch this configuration every ' . config('tracking.config_refresh_interval') . ' seconds');

            return Command::SUCCESS;
        } else {
            $this->error('✗ Failed to initialize configuration');
            $this->error('Available presets: normal, high_traffic, emergency');
            return Command::FAILURE;
        }
    }
}
