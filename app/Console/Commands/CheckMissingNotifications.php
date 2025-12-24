<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CheckMissingNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:check-missing
                            {--fix : Automatically add missing notifications to database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan codebase for getNotification() calls and identify missing database entries';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Scanning codebase for notification keys...');

        // Get all notification keys used in code
        $usedKeys = $this->scanCodebaseForNotificationKeys();
        $this->info('Found ' . count($usedKeys) . ' unique notification keys in code');

        // Get all keys from database
        $dbKeys = DB::table('firebase_push_notifications')
            ->pluck('name')
            ->toArray();
        $this->info('Found ' . count($dbKeys) . ' notification entries in database');

        // Find missing keys
        $missingKeys = array_diff($usedKeys, $dbKeys);

        if (empty($missingKeys)) {
            $this->info('✅ All notification keys are present in the database!');
            return Command::SUCCESS;
        }

        // Display missing keys
        $this->newLine();
        $this->warn('⚠️  Found ' . count($missingKeys) . ' missing notification keys:');
        $this->newLine();

        $tableData = [];
        foreach ($missingKeys as $key) {
            $tableData[] = [
                $key,
                $this->getKeyUsageCount($key),
                $this->suggestMessage($key)
            ];
        }

        $this->table(
            ['Notification Key', 'Usage Count', 'Suggested Message'],
            $tableData
        );

        // Auto-fix if requested
        if ($this->option('fix')) {
            $this->newLine();
            if ($this->confirm('Add these missing notifications to the database?', true)) {
                $this->addMissingNotifications($missingKeys);
                $this->info('✅ Missing notifications added successfully!');
            }
        } else {
            $this->newLine();
            $this->info('To automatically add missing notifications, run:');
            $this->line('  php artisan notifications:check-missing --fix');
        }

        return Command::SUCCESS;
    }

    /**
     * Scan codebase for getNotification() calls
     */
    private function scanCodebaseForNotificationKeys(): array
    {
        $keys = [];
        $pattern = '/getNotification\([\'"]([^\'"]+)[\'"]\)/';

        // Directories to scan
        $directories = [
            base_path('app'),
            base_path('Modules'),
        ];

        foreach ($directories as $directory) {
            $files = File::allFiles($directory);

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $content = file_get_contents($file->getPathname());
                preg_match_all($pattern, $content, $matches);

                if (!empty($matches[1])) {
                    $keys = array_merge($keys, $matches[1]);
                }
            }
        }

        return array_unique($keys);
    }

    /**
     * Get usage count for a notification key
     */
    private function getKeyUsageCount(string $key): int
    {
        $count = 0;
        $pattern = '/getNotification\([\'"]' . preg_quote($key, '/') . '[\'"]\)/';

        $directories = [
            base_path('app'),
            base_path('Modules'),
        ];

        foreach ($directories as $directory) {
            $files = File::allFiles($directory);

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $content = file_get_contents($file->getPathname());
                preg_match_all($pattern, $content, $matches);
                $count += count($matches[0]);
            }
        }

        return $count;
    }

    /**
     * Suggest a default message based on key name
     */
    private function suggestMessage(string $key): string
    {
        // Convert snake_case to Title Case
        $message = str_replace('_', ' ', $key);
        $message = ucwords($message);

        return $message;
    }

    /**
     * Add missing notifications to database
     */
    private function addMissingNotifications(array $keys): void
    {
        $now = now();

        foreach ($keys as $key) {
            DB::table('firebase_push_notifications')->insert([
                'name' => $key,
                'value' => $this->suggestMessage($key),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->line("  ✓ Added: {$key}");
        }
    }
}
