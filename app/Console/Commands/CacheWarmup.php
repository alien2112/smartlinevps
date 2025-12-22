<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cache Warmup Command
 * 
 * Pre-loads frequently accessed data into cache to improve
 * first-request performance after cache flush.
 */
class CacheWarmup extends Command
{
    protected $signature = 'cache:warmup {--flush : Flush cache before warming}';
    protected $description = 'Warm up performance-critical caches';

    public function handle(): int
    {
        $this->info('Starting cache warmup...');
        
        if ($this->option('flush')) {
            $this->info('Flushing existing cache...');
            Cache::flush();
        }

        try {
            $this->warmConfigCache();
            $this->warmZoneCache();
            $this->info('Cache warmup completed successfully!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Cache warmup failed: ' . $e->getMessage());
            Log::error('CacheWarmup failed', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }

    /**
     * Warm up business configuration cache
     */
    private function warmConfigCache(): void
    {
        $this->info('Warming configuration cache...');
        
        $commonConfigs = [
            'search_radius',
            'vat_percent',
            'trip_commission',
            'bid_on_fare',
            'idle_fee',
            'delay_fee',
            'trip_request_active_time',
            'currency_symbol',
            'currency_decimal_point',
            'currency_symbol_position',
            'pagination_limit',
        ];

        $count = 0;
        foreach ($commonConfigs as $key) {
            try {
                $value = get_cache($key);
                if ($value !== null) {
                    $count++;
                }
            } catch (\Exception $e) {
                $this->warn("  Failed to cache config: {$key}");
            }
        }
        
        $this->info("  Cached {$count} configuration values");
    }

    /**
     * Warm up zone cache
     */
    private function warmZoneCache(): void
    {
        $this->info('Warming zone cache...');
        
        try {
            $zones = \Modules\ZoneManagement\Entities\Zone::where('is_active', 1)
                ->select('id', 'name')
                ->get();
            
            $count = $zones->count();
            $this->info("  Found {$count} active zones");
            
            // Cache zone existence checks
            foreach ($zones as $zone) {
                Cache::put('zone:exists:' . $zone->id, true, 3600);
            }
            
            $this->info("  Cached zone data");
        } catch (\Exception $e) {
            $this->warn('  Zone cache warmup skipped: ' . $e->getMessage());
        }
    }
}
