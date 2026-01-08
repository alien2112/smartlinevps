<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * CreateDriverVehicleJob
 *
 * Non-blocking job to create vehicle record for a driver
 * Runs asynchronously to avoid blocking document upload response
 */
class CreateDriverVehicleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 10;

    protected string $driverId;
    protected array $vehicleData;

    /**
     * Create a new job instance.
     *
     * @param string $driverId
     * @param array $vehicleData
     */
    public function __construct(string $driverId, array $vehicleData)
    {
        $this->driverId = $driverId;
        $this->vehicleData = $vehicleData;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            DB::beginTransaction();

            // Check if driver already has a primary vehicle
            $existingVehicle = DB::table('vehicles')
                ->where('driver_id', $this->driverId)
                ->where('is_primary', true)
                ->first();

            if ($existingVehicle) {
                // Update existing primary vehicle
                DB::table('vehicles')
                    ->where('id', $existingVehicle->id)
                    ->update($this->prepareVehicleData());

                Log::info('Driver vehicle updated via job', [
                    'driver_id' => $this->driverId,
                    'vehicle_id' => $existingVehicle->id,
                ]);
            } else {
                // Create new vehicle
                $vehicleData = $this->prepareVehicleData();
                $vehicleData['id'] = (string) Str::uuid();
                $vehicleData['created_at'] = now();
                $vehicleData['updated_at'] = now();

                DB::table('vehicles')->insert($vehicleData);

                Log::info('Driver vehicle created via job', [
                    'driver_id' => $this->driverId,
                    'vehicle_id' => $vehicleData['id'],
                ]);
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('CreateDriverVehicleJob failed', [
                'driver_id' => $this->driverId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Prepare vehicle data for database insertion
     *
     * @return array
     */
    protected function prepareVehicleData(): array
    {
        $data = [
            'driver_id' => $this->driverId,
            'brand_id' => $this->vehicleData['brand_id'],
            'model_id' => $this->vehicleData['model_id'],
            'category_id' => $this->vehicleData['category_id'],
            'licence_plate_number' => $this->vehicleData['licence_plate_number'],
            'vehicle_request_status' => PENDING,
            'is_active' => false,
            'is_primary' => true,
            'updated_at' => now(),
        ];

        // Add optional fields if provided
        if (!empty($this->vehicleData['licence_expire_date'])) {
            $data['licence_expire_date'] = $this->vehicleData['licence_expire_date'];
        }

        if (!empty($this->vehicleData['ownership'])) {
            $data['ownership'] = $this->vehicleData['ownership'];
        }

        if (!empty($this->vehicleData['fuel_type'])) {
            $data['fuel_type'] = $this->vehicleData['fuel_type'];
        }

        if (!empty($this->vehicleData['vin_number'])) {
            $data['vin_number'] = $this->vehicleData['vin_number'];
        }

        if (!empty($this->vehicleData['transmission'])) {
            $data['transmission'] = $this->vehicleData['transmission'];
        }

        if (isset($this->vehicleData['parcel_weight_capacity'])) {
            $data['parcel_weight_capacity'] = $this->vehicleData['parcel_weight_capacity'];
        }

        if (!empty($this->vehicleData['year_id'])) {
            $data['year_id'] = $this->vehicleData['year_id'];
        }

        return $data;
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('CreateDriverVehicleJob permanently failed after retries', [
            'driver_id' => $this->driverId,
            'error' => $exception->getMessage(),
        ]);
    }
}
