<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AgendaStatusEnum;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendAgendaNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $agendaId, public AgendaStatusEnum $status)
    {
        // Só processa após o commit, evitando notificar agendamentos revertidos por rollback
        $this->afterCommit();
    }

    public function handle(NotificationService $notificationService): void
    {
        $notificationService->notifyAgendaStatus(agendaId: $this->agendaId, status: $this->status);
    }
}
