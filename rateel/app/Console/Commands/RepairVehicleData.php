<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\VehicleManagement\Entities\Vehicle;
use Modules\VehicleManagement\Entities\VehicleBrand;
use Modules\VehicleManagement\Entities\VehicleModel;
use Modules\VehicleManagement\Entities\VehicleCategory;

class RepairVehicleData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vehicle:repair-data {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Repair vehicles with missing or invalid brand/model/category references';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $this->info('🚗 Starting Vehicle Data Repair...');
        $this->newLine();

        try {
            DB::beginTransaction();

            // Step 1: Create default records if they don't exist
            $this->info('📝 Creating default records...');
            $defaultBrand = $this->getOrCreateDefaultBrand($isDryRun);
            $defaultModel = $this->getOrCreateDefaultModel($isDryRun, $defaultBrand->id);
            $defaultCategory = $this->getOrCreateDefaultCategory($isDryRun);

            // Step 2: Find and fix vehicles with null brand_id
            $this->info('🔧 Checking vehicles with missing brand...');
            $vehiclesWithNullBrand = Vehicle::whereNull('brand_id')
                ->orWhereNotIn('brand_id', VehicleBrand::pluck('id'))
                ->get();

            if ($vehiclesWithNullBrand->count() > 0) {
                $this->warn("  Found {$vehiclesWithNullBrand->count()} vehicles with missing/invalid brand");
                if (!$isDryRun) {
                    Vehicle::whereNull('brand_id')
                        ->orWhereNotIn('brand_id', VehicleBrand::pluck('id'))
                        ->update(['brand_id' => $defaultBrand->id]);
                    $this->info("  ✅ Fixed {$vehiclesWithNullBrand->count()} vehicles");
                }
            } else {
                $this->info('  ✅ All vehicles have valid brands');
            }

            // Step 3: Find and fix vehicles with null model_id
            $this->info('🔧 Checking vehicles with missing model...');
            $vehiclesWithNullModel = Vehicle::whereNull('model_id')
                ->orWhereNotIn('model_id', VehicleModel::pluck('id'))
                ->get();

            if ($vehiclesWithNullModel->count() > 0) {
                $this->warn("  Found {$vehiclesWithNullModel->count()} vehicles with missing/invalid model");
                if (!$isDryRun) {
                    Vehicle::whereNull('model_id')
                        ->orWhereNotIn('model_id', VehicleModel::pluck('id'))
                        ->update(['model_id' => $defaultModel->id]);
                    $this->info("  ✅ Fixed {$vehiclesWithNullModel->count()} vehicles");
                }
            } else {
                $this->info('  ✅ All vehicles have valid models');
            }

            // Step 4: Find and fix vehicles with null category_id
            $this->info('🔧 Checking vehicles with missing category...');
            $vehiclesWithNullCategory = Vehicle::whereNull('category_id')
                ->orWhereNotIn('category_id', VehicleCategory::pluck('id'))
                ->get();

            if ($vehiclesWithNullCategory->count() > 0) {
                $this->warn("  Found {$vehiclesWithNullCategory->count()} vehicles with missing/invalid category");
                if (!$isDryRun) {
                    Vehicle::whereNull('category_id')
                        ->orWhereNotIn('category_id', VehicleCategory::pluck('id'))
                        ->update(['category_id' => $defaultCategory->id]);
                    $this->info("  ✅ Fixed {$vehiclesWithNullCategory->count()} vehicles");
                }
            } else {
                $this->info('  ✅ All vehicles have valid categories');
            }

            if (!$isDryRun) {
                DB::commit();
                $this->newLine();
                $this->info('✅ Vehicle data repair completed successfully!');
            } else {
                DB::rollBack();
                $this->newLine();
                $this->warn('🔍 Dry run completed. Run without --dry-run to apply changes.');
            }

            $this->newLine();
            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Error during repair: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    /**
     * Get or create default "Unknown" brand
     */
    private function getOrCreateDefaultBrand($isDryRun)
    {
        $brand = VehicleBrand::where('name', 'Unknown')->first();

        if (!$brand) {
            $this->warn('  Creating default "Unknown" brand...');
            if (!$isDryRun) {
                $brand = VehicleBrand::create([
                    'name' => 'Unknown',
                    'description' => 'Default brand for vehicles without a specific brand',
                    'is_active' => 1,
                ]);
                $this->info('  ✅ Default brand created');
            } else {
                // For dry run, create a temporary object
                $brand = new VehicleBrand(['id' => 'temp-id', 'name' => 'Unknown']);
            }
        } else {
            $this->info('  ✅ Default brand already exists');
        }

        return $brand;
    }

    /**
     * Get or create default "Unknown" model
     */
    private function getOrCreateDefaultModel($isDryRun, $brandId)
    {
        $model = VehicleModel::where('name', 'Unknown')->first();

        if (!$model) {
            $this->warn('  Creating default "Unknown" model...');
            if (!$isDryRun) {
                $model = VehicleModel::create([
                    'name' => 'Unknown',
                    'description' => 'Default model for vehicles without a specific model',
                    'brand_id' => $brandId,
                    'seat_capacity' => 4,
                    'engine' => 0,
                    'hatch_bag_capacity' => 0,
                    'maximum_weight' => 0,
                    'is_active' => 1,
                ]);
                $this->info('  ✅ Default model created');
            } else {
                // For dry run, create a temporary object
                $model = new VehicleModel(['id' => 'temp-id', 'name' => 'Unknown']);
            }
        } else {
            $this->info('  ✅ Default model already exists');
        }

        return $model;
    }

    /**
     * Get or create default "Uncategorized" category
     */
    private function getOrCreateDefaultCategory($isDryRun)
    {
        $category = VehicleCategory::where('name', 'Uncategorized')->first();

        if (!$category) {
            $this->warn('  Creating default "Uncategorized" category...');
            if (!$isDryRun) {
                $category = VehicleCategory::create([
                    'name' => 'Uncategorized',
                    'description' => 'Default category for vehicles without a specific category',
                    'type' => 'car',
                    'is_active' => 1,
                ]);
                $this->info('  ✅ Default category created');
            } else{
                // For dry run, create a temporary object
                $category = new VehicleCategory(['id' => 'temp-id', 'name' => 'Uncategorized']);
            }
        } else {
            $this->info('  ✅ Default category already exists');
        }

        return $category;
    }
}
