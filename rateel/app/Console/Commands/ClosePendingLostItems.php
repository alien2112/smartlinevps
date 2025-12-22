<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Modules\TripManagement\Entities\LostItem;
use Modules\TripManagement\Entities\LostItemStatusLog;
use Modules\TripManagement\Transformers\LostItemResource;

class ClosePendingLostItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lost-item:close-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-close pending lost items if driver does not respond within configured timeout (default 24 hours)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Get timeout from cache/config, default to 24 hours
        $timeoutHours = get_cache('lost_item_response_timeout_hours') ?? 24;
        $cutoffTime = now()->subHours($timeoutHours);

        $this->info("Checking for pending lost items older than {$timeoutHours} hours (before {$cutoffTime})...");

        // Find pending lost items without driver response older than timeout
        $pendingItems = LostItem::where('status', LostItem::STATUS_PENDING)
            ->whereNull('driver_response')
            ->where('created_at', '<', $cutoffTime)
            ->with(['customer', 'driver', 'trip'])
            ->get();

        if ($pendingItems->isEmpty()) {
            $this->info('No pending lost items to close.');
            return Command::SUCCESS;
        }

        $this->info("Found {$pendingItems->count()} pending lost items to auto-close.");

        $closedCount = 0;

        foreach ($pendingItems as $lostItem) {
            DB::beginTransaction();
            try {
                $fromStatus = $lostItem->status;

                // Update status to no_driver_response
                $lostItem->update([
                    'status' => LostItem::STATUS_NO_DRIVER_RESPONSE,
                    'admin_notes' => ($lostItem->admin_notes ? $lostItem->admin_notes . "\n" : '') . 
                        'تم الإغلاق تلقائياً بسبب عدم رد الكابتن خلال ' . $timeoutHours . ' ساعة',
                ]);

                // Log status change
                LostItemStatusLog::create([
                    'lost_item_id' => $lostItem->id,
                    'changed_by' => null, // System action
                    'from_status' => $fromStatus,
                    'to_status' => LostItem::STATUS_NO_DRIVER_RESPONSE,
                    'notes' => 'Auto-closed: Driver did not respond within ' . $timeoutHours . ' hours',
                ]);

                DB::commit();

                // Send notification to customer
                $this->notifyCustomer($lostItem);

                // Publish Redis event for real-time updates
                $this->publishLostItemEvent('lost_item:auto_closed', $lostItem);

                $closedCount++;
                $this->line("  ✓ Closed lost item: {$lostItem->id}");

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Failed to auto-close lost item: ' . $e->getMessage(), [
                    'lost_item_id' => $lostItem->id,
                ]);
                $this->error("  ✗ Failed to close lost item: {$lostItem->id} - {$e->getMessage()}");
            }
        }

        $this->info("Successfully closed {$closedCount} lost items.");

        return Command::SUCCESS;
    }

    /**
     * Notify customer that their lost item report was auto-closed
     */
    protected function notifyCustomer(LostItem $lostItem): void
    {
        try {
            if (!$lostItem->customer || !$lostItem->customer->fcm_token) {
                return;
            }

            $push = getNotification('lost_item_no_response');

            // Default notification if not configured
            $title = $push['title'] ?? 'لم يتم الرد على طلبك';
            $description = $push['description'] ?? 'تم إغلاق بلاغ المفقودات تلقائياً لعدم رد الكابتن خلال المدة المحددة';

            sendDeviceNotification(
                fcm_token: $lostItem->customer->fcm_token,
                title: translate($title),
                description: translate($description),
                status: $push['status'] ?? 1,
                ride_request_id: $lostItem->trip_request_id,
                type: 'lost_item',
                action: 'lost_item_no_response',
                user_id: $lostItem->customer->id
            );

        } catch (\Exception $e) {
            Log::warning('Failed to send lost item auto-close notification: ' . $e->getMessage(), [
                'lost_item_id' => $lostItem->id,
                'customer_id' => $lostItem->customer_id,
            ]);
        }
    }

    /**
     * Publish lost item event to Redis for realtime updates
     */
    protected function publishLostItemEvent(string $event, LostItem $lostItem): void
    {
        try {
            $lostItem->loadMissing([
                'trip.coordinate',
                'customer',
                'driver',
                'statusLogs.changedBy',
            ]);

            $resource = (new LostItemResource($lostItem))->resolve();

            $payload = json_encode([
                'event' => $event,
                'id' => $lostItem->id,
                'trip_request_id' => $lostItem->trip_request_id,
                'customer_id' => $lostItem->customer_id,
                'driver_id' => $lostItem->driver_id,
                'category' => $lostItem->category,
                'status' => $lostItem->status,
                'driver_response' => $lostItem->driver_response,
                'driver_notes' => $lostItem->driver_notes,
                'admin_notes' => $lostItem->admin_notes,
                'contact_preference' => $lostItem->contact_preference,
                'image_url' => $lostItem->image_url,
                'item_lost_at' => $lostItem->item_lost_at?->toIso8601String(),
                'created_at' => $lostItem->created_at?->toIso8601String(),
                'updated_at' => $lostItem->updated_at?->toIso8601String(),
                'lost_item' => $resource,
            ]);

            Redis::publish($event, $payload);
        } catch (\Exception $e) {
            Log::warning('Failed to publish lost item auto-close event: ' . $e->getMessage(), [
                'event' => $event,
                'lost_item_id' => $lostItem->id ?? null,
            ]);
        }
    }
}
