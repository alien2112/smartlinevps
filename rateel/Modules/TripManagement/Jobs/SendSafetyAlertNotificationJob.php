<?php

namespace Modules\TripManagement\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class SendSafetyAlertNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $topic;
    public $title;
    public $description;
    public $type;
    public $sentBy;
    public $tripReferenceId;
    public $route;
    public $defaultLanguage;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $topic,
        string $title,
        string $description,
        string $type,
        ?string $sentBy,
        ?string $tripReferenceId,
        string $route,
        string $defaultLanguage
    ) {
        $this->topic = $topic;
        $this->title = $title;
        $this->description = $description;
        $this->type = $type;
        $this->sentBy = $sentBy;
        $this->tripReferenceId = $tripReferenceId;
        $this->route = $route;
        $this->defaultLanguage = $defaultLanguage;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Set the locale to the default language for translation
            App::setLocale($this->defaultLanguage);

            // Send the topic notification
            sendTopicNotification(
                topic: $this->topic,
                title: $this->title,
                description: $this->description,
                type: $this->type,
                sentBy: $this->sentBy,
                tripReferenceId: $this->tripReferenceId,
                route: $this->route
            );
        } catch (\Exception $e) {
            // Log the error but don't fail the job - notifications are not critical
            Log::error('Failed to send safety alert notification', [
                'topic' => $this->topic,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 5;
}
