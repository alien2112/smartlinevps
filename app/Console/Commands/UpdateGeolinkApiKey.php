<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\BusinessManagement\Entities\BusinessSetting;

class UpdateGeolinkApiKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'geolink:update-api-key {api_key} {--client-only : Update only client key} {--server-only : Update only server key}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the GeoLink API key in business settings';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $apiKey = $this->argument('api_key');
        $clientOnly = $this->option('client-only');
        $serverOnly = $this->option('server-only');

        if ($clientOnly && $serverOnly) {
            $this->error('Cannot use both --client-only and --server-only options together.');
            return 1;
        }

        // Find existing business setting
        $businessSetting = BusinessSetting::where('key_name', GOOGLE_MAP_API)
            ->where('settings_type', GOOGLE_MAP_API)
            ->first();

        $value = [];
        if ($businessSetting && $businessSetting->value) {
            $value = is_array($businessSetting->value) ? $businessSetting->value : json_decode($businessSetting->value, true) ?? [];
        }

        // Update keys based on options
        if ($clientOnly) {
            $value['map_api_key'] = $apiKey;
            $this->info('Updating client API key only...');
        } elseif ($serverOnly) {
            $value['map_api_key_server'] = $apiKey;
            $this->info('Updating server API key only...');
        } else {
            $value['map_api_key'] = $apiKey;
            $value['map_api_key_server'] = $apiKey;
            $this->info('Updating both client and server API keys...');
        }

        // Save or create the setting
        if ($businessSetting) {
            $businessSetting->update(['value' => $value]);
            $this->info('✓ API key updated successfully!');
        } else {
            BusinessSetting::create([
                'key_name' => GOOGLE_MAP_API,
                'settings_type' => GOOGLE_MAP_API,
                'value' => $value
            ]);
            $this->info('✓ API key created successfully!');
        }

        // Clear cache
        \Illuminate\Support\Facades\Cache::forget(CACHE_BUSINESS_SETTINGS);
        $this->info('✓ Cache cleared.');

        return 0;
    }
}


















