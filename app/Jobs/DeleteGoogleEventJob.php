<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\GoogleCalendarSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeleteGoogleEventJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(public int $userId, public string $eventId)
    {
        $this->afterCommit();
    }

    public function handle(GoogleCalendarSyncService $syncService): void
    {
        $syncService->removeEvent($this->userId, $this->eventId);
    }
}
