<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\WhatsAppNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWhatsAppNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(public int $notificationId)
    {
        // Só envia após o commit, evitando avisar de agendamentos revertidos por rollback
        $this->afterCommit();
    }

    public function handle(WhatsAppNotificationService $whatsAppNotificationService): void
    {
        $whatsAppNotificationService->send($this->notificationId);
    }

    public function failed(?\Throwable $exception): void
    {
        app(WhatsAppNotificationService::class)->markFailed($this->notificationId);
    }
}
