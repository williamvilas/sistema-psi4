<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\AgendaReminderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshAgendaRemindersJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(public int $userId)
    {
        $this->afterCommit();
    }

    public function handle(AgendaReminderService $agendaReminderService): void
    {
        $agendaReminderService->refreshByUser($this->userId);
    }
}
